<?php

use Faker\Generator as Faker;

$factory->define(App\VoucherAccessCode::class, function (Faker $faker) {
    return [
      'voucher_id' => App\Voucher::all()->random()->id,
      'access_code' => $faker->unique()->word(),
      'max_uses' => $faker->optional()->numberBetween(10, 1000),
      'start_date' => $faker->dateTimeBetween('-4 months', 'now'),
      'expiry_date' => $faker->dateTimeBetween('now', '4 months'),
    ];
});
