<?php

namespace App\Services;

use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;

class CartService
{
    public function get()
    {
        return Auth::user()->cartItems()->with(['productSku.product'])->get();
    }

    public function add($skuId, $amount)
    {
        $user = Auth::user();

        // 如果商品已存在购物车
        if ($item = $user->cartItems()->where('product_sku_id', $skuId)->first()) {
            // 直接增加数量
            $item->update([
                'amount' => $item->amount + $amount,
            ]);
        } else {
            // 创建一条新记录
            $item = new CartItem(['amount' => $amount]);
            $item->user()->associate($user);
            $item->productSku()->associate($skuId);
            $item->save();
        }
        return $item;
    }

    public function remove($skuIds)
    {
        if (!is_array($skuIds)) {
            $skuIds = [$skuIds];
        }

        Auth::user()->cartItems()->whereIn('product_sku_id', $skuIds)->delete();
    }
}
