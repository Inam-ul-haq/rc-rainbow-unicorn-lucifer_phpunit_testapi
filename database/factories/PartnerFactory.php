<?php

use Faker\Generator as Faker;
use Grimzy\LaravelMysqlSpatial\Types\Point;

$factory->define(App\Partner::class, function (Faker $faker) {
    return [
        'type' => $faker->randomElement(['retailer', 'vet']),
        'subtype' => $faker->optional()->sentence(3),
        'public_name' => $faker->company(),
        'location_point' => new Point($faker->latitude(), $faker->longitude(), 4326),
        'contact_name_title_id' => App\NameTitle::all()->random()->id,
        'contact_first_name' => $faker->firstName(),
        'contact_last_name' => $faker->lastName(),
        'contact_telephone' => $faker->phoneNumber(),
        'contact_email' => $faker->safeEmail(),
        'public_street_line1' => $faker->streetAddress(),
        'public_town' => $faker->city(),
        'public_county' => $faker->county(),
        'public_postcode' => $faker->postcode(),
        'public_country' => $faker->randomElement(
            [
                'Channel Islands',
                'Isle of Man',
                'Republic of Ireland',
                'United Kingdom',
            ]
        ),
        'public_email' => $faker->email(),
        'public_vat_number' => 'GB' . $faker->numberBetween(1000000, 10000000),
        'accepts_vouchers' => $faker->boolean(),
        'accepts_loyalty' => $faker->boolean(),
        'access_question' => $faker->sentence(6, true),
        'crm_id' => $faker->unique()->numberBetween(100000, 999999),
    ];
});
