<?php

use Faker\Generator as Faker;

$factory->define(App\User::class, function (Faker $faker) {
    return [
## user_type set by seeder
        'name' => $faker->firstName() . ' ' . $faker->lastName(),
        'name_title_id' => App\NameTitle::all()->random()->id,
        'email' => $faker->unique()->safeEmail(),
        'password' => $faker->password(),
    ];
});
