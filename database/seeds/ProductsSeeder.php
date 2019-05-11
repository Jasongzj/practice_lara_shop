<?php

use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 创建30个商品
        $products = factory(\App\Models\Product::class)->times(30)->create();

        foreach ($products as $product) {
            // 每个商品创建3个sku
            $skus = factory(\App\Models\ProductSku::class)->times(3)->create(['product_id' => $product->id]);

            // 更新产品的价格为sku里最低的价格
            $product->update(['price' => $skus->min('price')]);
        }
    }
}
