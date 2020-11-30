<?php

use Faker\Generator as Faker;

$factory->define(App\Coupon::class, function (Faker $faker) {
    return [
        'issued_at' => $faker->dateTimeBetween('-3 months', 'now'),
        'access_code_id' => $faker->optional(0.3)->passthrough(App\VoucherAccessCode::all()->random()->id),
        'restrict_consumer_id' => $faker->optional(0.5)->passthrough(App\Consumer::all()->random()->id),
        'restrict_partner_id' => $faker->optional(0.5)->passthrough(App\Partner::all()->random()->id),
        'referrer_id' => $faker->optional(0.2)->passthrough(App\Referrer::all()->random()->id),
        'voucher_id' => App\Voucher::all()->random()->id,
        'barcode' => $faker->optional(0.5)->ean13(),
        'valid_from' => $faker->dateTimeBetween('-3 months', '-1 months'),
        'valid_to' => $faker->dateTimeBetween('-1 months', '3 months'),
        'maximum_uses' => $faker->optional(0.5)->numberBetween(1, 100),
        'shared_code' => strtoupper($faker->word()),
        'redeemed_datetime' => $faker->optional(0.4)->dateTimeBetween('-2 months', '-1 months'),
        'redemption_partner_id' => $faker->optional(0.4)->passthrough(App\Partner::all()->random()->id),
        'redeemed_by_consumer_id' => $faker->optional(0.4)->passthrough(App\Consumer::all()->random()->id),
        'cancelled_at' => $faker->optional(0.2)->dateTimeBetween('-2 months', '2 days'),
        'vouchers_unique_codes_used_id' => $faker->optional(0.2)
                                                 ->passthrough(App\VoucherUniqueCode::all()->random()->id),
    ];
});
