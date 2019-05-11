<?php

namespace App\Admin\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Http\Requests\Admin\HandleRefundRequest;
use App\Models\Order;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    use HasResourceActions;

    /**
     * Index interface.
     *
     * @param Content $content
     * @return Content
     */
    public function index(Content $content)
    {
        return $content
            ->header('订单列表')
            ->body($this->grid());
    }

    /**
     * Show interface.
     *
     * @param mixed $id
     * @param Content $content
     * @return Content
     */
    public function show(Order $order, Content $content)
    {
        return $content
            ->header('订单详情')
            ->body(view('admin.orders.show', ['order' => $order]));
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Order);

        $grid->model()->whereNotNull('paid_at')->orderBy('paid_at', 'desc');

        $grid->no('订单号');
        // 关联字段使用column
        $grid->column('user.name', '买家');
        $grid->total_amount('总金额')->sortable();
        $grid->paid_at('支付时间')->sortable();
        $grid->ship_status('物流')->display(function ($value) {
            return Order::$shipStatusMap[$value];
        });
        $grid->refund_status('退款状态')->display(function ($value) {
            return Order::$refundStatusMap[$value];
        });

        // 禁用创建按钮
        $grid->disableCreateButton();
        $grid->actions(function ($actions) {
            // 禁用删除和编辑
            $actions->disableDelete();
            $actions->disableEdit();
        });
        $grid->tools(function ($tools) {
            // 禁用批量删除
            $tools->batch(function ($batch) {
                $batch->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * 发货
     * @param Order $order
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws InvalidRequestException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ship(Order $order, Request $request)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未付款');
        }

        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已发货');
        }

        $data = $this->validate($request, [
            'express_company' => ['required'],
            'express_no' => ['required'],
        ], [], [
            'express_company' => '物流公司',
            'express_no' => '物流单号',
        ]);

        // 订单状态更改为已发货，并记录物流信息
        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            'ship_data' => $data
        ]);

        return redirect()->back();
    }

    public function handleRefund(Order $order, HandleRefundRequest $request)
    {
        if ($order->refund_status !== Order::REFUND_STATUS_APPLIED) {
            throw new InvalidRequestException('订单退款状态不正确');
        }

        if ($request->input('agree')) {
            // 同意退款
            $extra = $order->extra ?: [];
            unset($extra['refund_disagree_reason']);
            $order->update(['extra' => $extra]);

            // 处理退款
            $this->_refundOrder($order);
        } else {
            $extra = $order->extra ?: [];
            $extra['refund_disagree_reason'] = $request->input('reason');

            $order->update([
                'refund_status' => Order::REFUND_STATUS_PENDING, // 拒绝后改为未退款
                'extra' => $extra,
            ]);
        }

        return $order;
    }

    protected function _refundOrder(Order $order)
    {
        switch ($order->payment_method) {
            case 'wechat':
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no,
                    'total_fee' => $order->total_amount * 100,
                    'refund_fee' => $order->total_amount * 100,
                    'out_refund_no' => $refundNo,
                    'notify_url' => route('payment.wechat.refund_notify'),
                ]);
                // 更新订单为退款中
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                $refundNo = Order::getAvailableRefundNo();
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no,
                    'refund_amount' => $order->total_amount,
                    'out_request_no' => $refundNo,
                ]);

                // 根据文档，如果有 sub_code 表示操作失败
                if ($ret->sub_code) {
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // 将退款状态标记为退款失败
                    $order->update([
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                } else {
                    // 标记为退款成功并保存退款单号
                    $order->update([
                        'refund_status' => Order::REFUND_STATUS_SUCCESS,
                        'refund_no' => $refundNo,
                    ]);
                }
                break;
            default:
                throw new InvalidRequestException('未知支付方式：' . $order->payment_method);
                break;
        }
    }
}
