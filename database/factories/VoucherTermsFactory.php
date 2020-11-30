<?php

use Faker\Generator as Faker;

$factory->define(App\VoucherTerms::class, function (Faker $faker) {
    return [
        'voucher_id' => App\Voucher::has('terms', '=', 0)->get()->random()->id,
        'voucher_terms' => $faker->text(600),
        'used_from' => $faker->dateTimeBetween('-6 months', '-2 months'),
        'used_until' => null,
    ];
});
