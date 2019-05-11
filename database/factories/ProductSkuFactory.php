<?php

use Faker\Generator as Faker;

$factory->define(App\Models\ProductSku::class, function (Faker $faker) {
    return [
        'title' => $faker->word,
        'description' => $faker->sentence,
        'price' => $faker->randomFloat(2, 0, 1000),
        'stock' => $faker->randomNumber(5),
    ];
});
