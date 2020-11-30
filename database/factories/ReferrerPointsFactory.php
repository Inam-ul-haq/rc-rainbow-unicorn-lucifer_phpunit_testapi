<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ReferrerPoints;
use Faker\Generator as Faker;

$factory->define(ReferrerPoints::class, function (Faker $faker) {
    return [
        'points' => $faker->numberBetween(0, 50),
        'coupon_id' => App\Coupon::all()->random()->id,
        'consumer_id' => App\Consumer::all()->random()->id,
        'referrer_id' => App\Referrer::all()->random()->id,
        'transaction_date' => $faker->dateTimeBetween('-6 months', 'now'),
    ];
});
