<?php

use Faker\Generator as Faker;

$factory->define(App\Voucher::class, function (Faker $faker) {
    $value = $faker->numberBetween(100, 1000);
    return [
        'url' => $faker->unique()->words(1, true),
        'name' => $faker->words(5, true),
        'value_gbp' => $value,
        'value_eur' => round($value *= 1.16),  // 31st October '19
        'subscribe_from_date' => $faker->dateTimeBetween('-6 months', '-3 months'),
        'subscribe_to_date' => $faker->optional(0.5)->dateTimeBetween('-2 months', '12 months'),
        'redeem_from_date' => $faker->dateTimeBetween('-2 months', '8 months'),
        'redeem_to_date' => $faker->optional(0.5)->dateTimeBetween('-4 months', '12 months'),
        'redemption_period_count' => $faker->numberBetween(1, 12),
        'redemption_period' => $faker->randomElement(['days','months','years']),
        'public_name' => $faker->sentences(1, true),
        'page_copy' => $faker->paragraphs(3, true),
        'unique_code_required' => $faker->boolean(0.5),
        'unique_code_prefix' => strtoupper($faker->randomLetter() .
                                           $faker->randomLetter() .
                                           $faker->randomLetter() .
                                           $faker->randomLetter()),
        'send_by_email' => $faker->boolean(0.5),
        'email_subject_line' => $faker->sentences(1, true),
        'email_copy' => $faker->paragraphs(3, true),
    ];
});
