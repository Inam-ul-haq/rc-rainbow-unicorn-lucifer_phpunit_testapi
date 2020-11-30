<?php

use Faker\Generator as Faker;

$factory->define(App\Product::class, function (Faker $faker) {
    return [
        'name' => $faker->words(4, true),
        'display_name' => $faker->words(4, true),
    ];
});
