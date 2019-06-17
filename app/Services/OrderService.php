<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Jobs\RefundInstallmentOrder;
use App\Models\CouponCode;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        if ($coupon) {
            $coupon->checkAvailable($user);
        }

        $order = DB::transaction(function () use ($user, $address, $remark, $items, $coupon) {
            // 更新地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建订单
            $order = new Order([
                'address' => [
                    'address' => $address->full_address,
                    'zip' => $address->zip,
                    'contact_name' => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => $remark,
                'total_amount' => 0,
                'type' => Order::TYPE_NORMAL,
            ]);
            // 订单关联到用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;

            foreach ($items as $data) {
                $sku = ProductSku::find($data['sku_id']);

                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price' => $sku->price,
                ]);
                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku->id);
                $item->save();
                $totalAmount += $sku->price * $data['amount'];
                if ($sku->decreaseStock($data['amount']) <= 0 ) {
                    throw new InvalidRequestException('该商品库存不足');
                }
            }

            if ($coupon) {
                // 检查是否满足最小金额
                $coupon->checkAvailable($user, $totalAmount);
                // 获取折后价
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                // 订单关联优惠券码
                $order->couponCode()->associate($coupon);
                // 增加优惠券已使用量
                if ($coupon->increaseUsed() <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                }
            }

            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);

            // 将对应商品从购物车中移除
            $skuIds = collect($items)->pluck('sku_id')->all();
            app(CartService::class)->remove($skuIds);

            return $order;
        });

        dispatch(new CloseOrder($order, config('app.order_ttl')));

        return $order;
    }

    public function crowdfunding(User $user, UserAddress $address, ProductSku $sku, $amount)
    {
        $order = DB::transaction(function () use ($user, $address, $sku, $amount) {
            $address->update(['last_used_at' => Carbon::now()]);

            $order = new Order([
                'address' => [
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark' => '',
                'total_amount' => $sku->price * $amount,
                'type' => Order::TYPE_CROWDFUNDING,
            ]);

            $order->user()->associate($user);
            $order->save();

            // 创建一个订单项和sku关联
            $item = $order->items()->make([
                'amount' => $amount,
                'price' => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();

            if ($sku->decreaseStock($amount) < 0) {
                throw new InvalidRequestException('该商品库存不足');
            }
            return $order;
        });

        // 众筹剩余时间(秒)
        $crowdfundingTtl = $sku->product->crowdfunding->end_at->getTimestamp() - time();
        // 订单关闭时间和众筹剩余时间的较小值作为订单关闭时间
        dispatch(new CloseOrder($order, min(config('app.order_ttl'), $crowdfundingTtl)));

        return $order;
    }

    public function seckill(User $user, array $addressData, ProductSku $sku)
    {
        $order = DB::transaction(function () use ($user, $addressData, $sku) {
            if ($sku->decreaseStock(1) <= 0) {
                throw new InvalidRequestException('该商品库存不足');
            }

            // 创建一笔订单
            $order = new Order([
                'address'      => [
                    'address'       => $addressData['province'].$addressData['city'].$addressData['district'].$addressData['address'],
                    'zip'           => $addressData['zip'],
                    'contact_name'  => $addressData['contact_name'],
                    'contact_phone' => $addressData['contact_phone'],
                ],
                'remark'       => '',
                'total_amount' => $sku->price,
                'type'         => Order::TYPE_SECKILLL,
            ]);
            $order->user()->associate($user);
            $order->save();
            // 创建一个订单项
            $item = $order->items()->make([
                'amount' => 1,
                'price' => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();
            \Redis::decr('seckill_sku_' . $sku->id);
            return $order;
        });
        dispatch(new CloseOrder($order, config('app.seckill_order_ttl')));
        return $order;
    }

    public function refundOrder(Order $order)
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
            case 'installment':
                $order->update([
                    'refund_no' => Order::findAvailableNo(),
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                dispatch(new RefundInstallmentOrder($order));  //触发异步退分期订单任务
                break;
            default:
                throw new InvalidRequestException('未知支付方式：' . $order->payment_method);
                break;
        }
    }
}
