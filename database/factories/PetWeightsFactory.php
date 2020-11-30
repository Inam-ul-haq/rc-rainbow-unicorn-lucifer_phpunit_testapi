<?php

use Faker\Generator as Faker;

$factory->define(App\PetWeights::class, function (Faker $faker) {
    return [
        'pet_weight'   => $faker->numberBetween(1000, 20000),
        'date_entered' => $faker->dateTimeBetween('-3 years', 'now'),
        'pet_id'       => App\Pet::all()->random()->id,
    ];
});
