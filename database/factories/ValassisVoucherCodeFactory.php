<?php

use Faker\Generator as Faker;

$factory->define(App\ValassisVoucherCode::class, function (Faker $faker) {
    return [
        'barcode' => $faker->numberBetween(100000000, 199999999),
        'pin' => $faker->numberBetween(1000, 9999),
    ];
});
