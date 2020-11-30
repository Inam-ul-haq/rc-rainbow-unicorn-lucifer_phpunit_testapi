<?php

use Faker\Generator as Faker;

$factory->define(App\Consumer::class, function (Faker $faker) {
    $blacklisted = $faker->boolean(10);
    $active = $faker->boolean(90);
    return [
        'name_title_id' => App\NameTitle::all()->random()->id,
        'first_name'    => $faker->firstName(),
        'last_name'     => $faker->lastName(),
        'crm_id'        => $faker->randomNumber(),
        'last_update_from_crm' => $faker->dateTimeBetween('-1 years', 'now'),
        'optin_email_sent_date' => $faker->dateTimeBetween('-3 months', '-2 months'),
        'optin_email_reminder_send_date' => $faker->optional(0.5)->dateTimeBetween('-2 months', '-1 months'),
        'email_optin_date' => $faker->optional(0.9)->dateTimeBetween('-1 months', 'now'),
        'address_line_1'   => $faker->streetAddress(),
        'town' => $faker->city(),
        'county' => $faker->county(),
        'country' => $faker->country(),
        'postcode' => $faker->postcode(),
        'email' => $faker->unique()->safeEmail(),
        'telephone' => $faker->phoneNumber(),
        'password' => $faker->password(),
        'gdpr_optin_email_date' => $faker->optional(0.8)->dateTimeBetween('-1 months', 'now'),
        'gdpr_optin_phone_date' => $faker->optional(0.5)->dateTimeBetween('-1 months', 'now'),
        'blacklisted' => $blacklisted,
        'blacklisted_at' => $blacklisted ? $faker->dateTimeBetween('-2 months', 'now') : null,
        'active' => $active,
        'deactivated_at' => $active == 0 ? $faker->dateTimeBetween('-2 months', 'now') : null,
        'password_change_needed' => $faker->boolean(50),
        'source' => 'factory',
    ];
});
