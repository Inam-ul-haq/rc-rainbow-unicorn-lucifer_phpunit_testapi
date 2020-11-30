<?php

use Faker\Generator as Faker;

$factory->define(App\Pet::class, function (Faker $faker) {
    return [
        'consumer_id' => App\Consumer::all()->random()->id,
        'pet_name'    => $faker->firstName(),
        'pet_dob'     => $faker->dateTimeBetween('-10 years', 'now'),
        'breed_id'    => App\Breed::all()->random()->id,
        'pet_gender'  => $faker->randomElement(['male', 'female']),
        'neutered'    => (bool)random_int(0, 1),
    ];
});
