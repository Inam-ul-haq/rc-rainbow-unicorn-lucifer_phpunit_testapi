<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ReferrerGroup;
use Faker\Generator as Faker;

$factory->define(ReferrerGroup::class, function (Faker $faker) {
    return [
        'group_name' => $faker->words(5, true),
    ];
});
