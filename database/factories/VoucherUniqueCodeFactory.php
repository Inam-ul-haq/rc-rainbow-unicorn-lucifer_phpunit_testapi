<?php

use Faker\Generator as Faker;

$factory->define(App\VoucherUniqueCode::class, function (Faker $faker) {
    return [
        'voucher_id' => App\Voucher::all()->random()->id,
    ];
});
