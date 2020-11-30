<?php

use Faker\Generator as Faker;

$factory->define(App\Referrer::class, function (Faker $faker) {
    $blacklisted = $faker->boolean(10);
    return [
        'email' => $faker->unique()->email(),
        'name_title_id' => App\NameTitle::all()->random()->id,
        'first_name' => $faker->firstName(),
        'last_name' => $faker->lastName(),
        'referrer_points' => $faker->numberBetween(0, 1000),
        'blacklisted' => $blacklisted,
        'blacklisted_at' => $blacklisted ? $faker->dateTimeBetween('-6 months', 'now') : null,
    ];
});
