<?php

use Faker\Generator as Faker;

$factory->define(App\PartnerGroup::class, function (Faker $faker) {
    return [
        'group_ref' => strtolower($faker->unique()->word()),
        'group_name' => $faker->words(5, true),
    ];
});
