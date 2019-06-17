<?php

namespace App\Http\Requests;


use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSku;
use function GuzzleHttp\Psr7\uri_for;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SeckillOrderRequest extends Request
{

    public function rules()
    {
        return [
            'address.province'      => 'required',
            'address.city'          => 'required',
            'address.district'      => 'required',
            'address.address'       => 'required',
            'address.zip'           => 'required',
            'address.contact_name'  => 'required',
            'address.contact_phone' => 'required',
            'sku_id'     => [
                'required',
                function ($attribute, $value, $fail) {
                    $stock = \Redis::get('seckill_sku_' . $value);

                    if (is_null($stock)) {
                        return $fail('该商品不存在');
                    }

                    if ($stock < 1) {
                        return $fail('该商品已售完');
                    }

                    $sku = ProductSku::query()->find($value);
                    if ($sku->product->seckill->is_before_start) {
                        return $fail('秒杀尚未开始');
                    }
                    if ($sku->product->seckill->is_after_end) {
                        return $fail('秒杀已结束');
                    }

                    if (!$user = Auth::user()) {
                        throw new AuthenticationException('请先登录');
                    }

                    if (!$user->email_verified_at) {
                        throw new InvalidRequestException('请先验证邮箱');
                    }

                    $order  = Order::query()
                        ->where('user_id', $this->user()->id)
                        ->whereHas('items', function (Builder $query) use ($value) {
                            $query->where('product_sku_id', $value);
                        })
                        ->where(function (Builder $query) {
                            $query->whereNotNull('paid_at')
                                ->orWhere('closed', false);
                        })
                        ->first();
                    if ($order) {
                        if ($order->paid_at) {
                            return $fail('你已经抢购了该商品');
                        }
                        return $fail('你已经下单该商品，请到订单页面支付');
                    }
                }
            ]
        ];
    }
}
