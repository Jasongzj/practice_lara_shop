<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function payByAliPay(Order $order)
    {
        // 判断订单是否属于当前用户
        $this->authorize('own', $order);

        if ($order->closed || $order->paid_at) {
            throw new InvalidRequestException('订单状态不正确');
        }

        // 调用支付宝的网页支付
        return app('alipay')->web([
            'out_trade_no' => $order->no,
            'total_amount' => $order->total_amount,
            'subject' => '支付 Lara-shop 的订单：' . $order->no,
        ]);
    }

    public function alipayReturn()
    {
        try {
            app('alipay')->verify();
        } catch (\Exception $e) {
            return view('pages.error', ['msg' => '数据不正确']);
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    public function alipayNotify()
    {
        $data = app('alipay')->verify();
        // 如果订单状态不是成功或结束，则跳出逻辑
        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }

        $order = Order::query()->where('no', $data->out_trade_no)->first();
        if (!$order) {
            return 'fail';
        }
        // 如果已支付，则跳出逻辑
        if ($order->paid_at) {
            return app('alipay')->success();
        }

        $order->update([
            'paid_at' => Carbon::now(),
            'payment_method' => 'alipay',
            'payment_no' => $data->trade_no, // 支付宝订单号
        ]);

        $this->afterPaid($order);

        return app('alipay')->success();
    }

    public function payByWechat(Order $order)
    {
        // 校验订单是否属于本人
        $this->authorize('own', $order);

        if ($order->paid_at || $order->closed) {
            throw new InvalidRequestException('订单状态不正确');
        }

        $wechatOrder = app('wechat_pay')->scan([
            'out_trade_no' => $order->no,
            'total_fee' => $order->total_amount * 100,
            'body' => '支付 Lara-shop 的订单：' . $order->no,
        ]);

        // 将支付参数生成二维码
        $qrCode = new QrCode($wechatOrder->code_url);

        // 将二维码以字符串的形式返回响应
        return response($qrCode->writeString(), 200, ['Content-Type' => $qrCode->getContentType()]);
    }

    public function wechatNotify()
    {
        // 校验参数是否正确
        $data = app('wechat_pay')->verify();

        $order = Order::query()->where('no', $data->out_trade_no)->first();

        if (!$order) {
            return 'fail';
        }

        if ($order->paid_at) {
            return app('wechat_pay')->success();
        }

        // 标记为已支付
        $order->update([
            'paid_at' => Carbon::now(),
            'payment_method' => 'wechat',
            'payment_no' => $data->transaction_id,
        ]);

        $this->afterPaid($order);

        return app('wechat_pay')->success();
    }

    public function wechatRefundNotify(Request $request)
    {
        $data = app('wechat_pay')->verify(null, true);

        if (!$order = Order::where('no', $data['out_trade_no'])->first()) {
            $failXML = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[FAIL]]></return_msg></xml>';
            return $failXML;
        }

        if ($data['refund_status'] === 'SUCCESS') {
            $order->update([
                'refund_status' => Order::REFUND_STATUS_SUCCESS,
            ]);
        } else {
            $extra = $order->extra;
            $extra['refund_failed_code'] = $data['refund_status'];
            $order->update([
                'refund_status' => Order::REFUND_STATUS_FAILED,
                'extra' => $extra,
            ]);
        }

        return app('wechat_pay')->success();
    }


    protected function afterPaid(Order $order)
    {
        event(new OrderPaid($order));
    }
}
