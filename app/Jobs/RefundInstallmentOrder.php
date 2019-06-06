<?php

namespace App\Jobs;

use App\Exceptions\InvalidRequestException;
use App\Models\Installment;
use App\Models\InstallmentItem;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class RefundInstallmentOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Order
     */
    protected $order;


    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    public function handle()
    {

        // 如果订单不是分期付款，订单未支付或者订单退款状态不是退款中，则不处理
        if ($this->order->payment_method !== 'installment'
            || !$this->order->paid_at
            || $this->order->refund_status !== Order::REFUND_STATUS_PROCESSING) {
            return;
        }
        if (!$installment = Installment::query()->where('order_id', $this->order->id)->first()) {
            return;
        }
        foreach ($installment->items as $item) {
            // 如果还款计划未支付，或退款状态为退款成功或退款中，则跳过
            if (!$item->paid_at || in_array($item->refund_status, [InstallmentItem::REFUND_STATUS_SUCCESS, InstallmentItem::REFUND_STATUS_PROCESSING])) {
                continue;
            }
            // 退款逻辑
            try {
                $this->refundInstallmentItem($item);
            } catch (\Exception $e) {
                Log::warning('分期退款失败：' . $e->getMessage(), [
                    'installment_item_id' => $item->id,
                    'item_base' => $item->base,
                ]);
                // 假如某个还款计划退款报错了，则暂时跳过，继续处理下一个还款计划的退款
                continue;
            }
        }

        // 更新订单的退款状态
        $installment->refreshRefundStatus();
    }

    protected function refundInstallmentItem(InstallmentItem $item)
    {
        // 退款单号等于订单的退款单号拼接当期还款计划序号
        $refundNo = $this->order->refund_no . '_' . $item->sequence;
        switch ($item->payment_method) {
            case 'alipay':
                $attribute = [
                    'trade_no'       => $item->payment_no, // 使用支付宝交易号来退款
                    'refund_amount'  => $item->base, // 退款金额，单位元，只退回本金
                    'out_request_no' => $refundNo,    // 部分退款时必须提供的参数
                ];
                Log::debug(json_encode($attribute));
                $ret = app('alipay')->refund($attribute);

                Log::debug(json_encode($ret));
                // 根据支付宝的文档，如果返回值里有 sub_code 字段说明退款失败
                if ($ret->sub_code) {
                    $item->update([
                        'refund_status' => InstallmentItem::REFUND_STATUS_FAILED,
                    ]);
                } else {
                    $item->update([
                        'refund_status' => InstallmentItem::REFUND_STATUS_SUCCESS,
                    ]);
                }
                break;
            case 'wechat':
                app('wechat_pay')->refund([
                    'transaction_id' => $item->payment_no,
                    'total_fee'      => $item->total * 100, // 原订单金额
                    'refund_fee'     => $item->base * 100,  // 只退本金
                    'out_refund_no'  => $item->$refundNo,
                    'notify_url'     => '',
                ]);
                $item->update([
                    'refund_status' => InstallmentItem::REFUND_STATUS_PROCESSING, // 退款状态改为处理中
                ]);
                break;
            default:
                throw new InvalidRequestException('未知订单支付方式：' . $item->payment_method);
                break;
        }
    }
}
