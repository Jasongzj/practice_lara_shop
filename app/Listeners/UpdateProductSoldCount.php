<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\OrderItem;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateProductSoldCount implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  OrderPaid  $event
     * @return void
     */
    public function handle(OrderPaid $event)
    {
        $order = $event->getOrder();
        // 预加载商品数据
        $order->load('items.product');

        foreach ($order->items as $item) {

            // 统计订单中售出商品的销量
            $soldCount = OrderItem::query()
                ->where('product_id', $item->product->id)
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('paid_at');   //
                })->sum('amount');

            // 更新商品销量
            $item->product->update([
                'sold_count' => $soldCount,
            ]);
        }
    }
}
