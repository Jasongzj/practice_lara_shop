<?php

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrdersSeeder extends Seeder
{
    public function run()
    {
        $faker = app(Faker\Generator::class);

        $orders = factory(Order::class, 100)->create();

        $products = collect([]);

        foreach ($orders as $order) {
            $items = factory(OrderItem::class, random_int(1, 3))->create([
                'order_id' => $order->id,
                'rating' => $order->reviewed ? random_int(1, 5) : null,
                'review' => $order->reviewed ? $faker->sentence : null,
                'reviewed_at' => $order->reviewed ? $faker->dateTimeBetween($order->paid_at) : null,
            ]);

            // 计算总价
            $total = $items->sum(function (OrderItem $item) {
                return $item->price * $item->amount;
            });

            // 计算优惠券价格
            if ($order->couponCode) {
                $total = $order->couponCode->getAdjustedPrice($total);
            }

            // 更新订单总价
            $order->update([
                'total_amount' => $total,
            ]);

            // 将订单商品合并到商品合集中
            $products = $products->merge($items->pluck('product'));
        }

        // 根据id过滤掉重复的商品
        $products->unique('id')->each(function (Product $product) {
            // 查出该商品的销量、评分、评价数
            $soldCount = OrderItem::query()->where('product_id', $product->id)
                ->whereHas('order', function ($query) {
                    $query->whereNotNull('paid_at');
                })->first([
                    DB::raw('sum(amount) as sold_count')
                ]);
            $result = OrderItem::query()->where('product_id', $product->id)
                ->whereNotNull('reviewed_at')
                ->first([
                    DB::raw('count(*) as review_count'),
                    DB::raw('avg(rating) as rating'),
                ]);

            // 更新统计和评分
            $product->update([
                'sold_count' => $soldCount->sold_count,
                'rating' => $result->rating ?: 5,
                'review_count' => $result->review_count,
            ]);
        });
    }
}
