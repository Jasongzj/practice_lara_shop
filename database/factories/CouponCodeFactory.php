<?php

use Faker\Generator as Faker;

$factory->define(App\Models\CouponCode::class, function (Faker $faker) {
    // 随机获取一个优惠券类型
    $type = $faker->randomElement(array_keys(\App\Models\CouponCode::$typeMap));
    // 根据优惠券类型随机生成对应折扣
    $value = $type === \App\Models\CouponCode::TYPE_FIXED ? random_int(1, 200) : random_int(1, 50);

    // 固定金额优惠，最低消费金额要比优惠金额高 0.01
    if ($type === \App\Models\CouponCode::TYPE_FIXED) {
        $minAmount = $value + 0.01;
    } else {
        if (random_int(1, 100) < 50) {
            $minAmount = 0;
        } else {
            $minAmount = random_int(100, 1000);
        }
    }

    return [
        'name' => implode(' ', $faker->words),
        'code' => \App\Models\CouponCode::findAvailableCode(),
        'type' => $type,
        'value' => $value,
        'total' => 1000,
        'used' => 0,
        'min_amount' => $minAmount,
        'not_before' => null,
        'not_after' => null,
        'enabled' => true,
    ];
});
