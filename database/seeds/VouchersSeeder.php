<?php

use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

// @codingStandardsIgnoreLine
class VouchersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Voucher::class, 10)->create();

/*
 * Seed some product restrictions
 */
        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherProductRestrictions");
        App\Voucher::all()->each(function ($voucher) {

            $faker = Faker::create();
            $products = App\Product::all();

            if ($faker->boolean(30)) { // restrict around 30% of test vouchers to specific products
                $voucher->productRestrictions()->attach(
                    // add 5'ish product restrictions (might be less if we get unlucky on the rand)
                    array_unique($products->pluck('id')->random(5)->toArray())
                );
            }
        });

/*
 * Add a breed restriction to a voucher
 */
        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherBreedRestrictions");
        $breeds = App\Breed::all();
        $voucher = App\Voucher::all()->random();
        $voucher->breedRestrictions()->attach(
            $breeds->random(2)->pluck('id')->toArray()
        );

/**
 * And a species restriction (though not for a voucher that already has a breed restriction)
 */
        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherSpeciesRestrictions");
        $species = App\Species::all();
        $voucher = App\Voucher::whereDoesntHave('breedRestrictions')->get()->random();
        $voucher->speciesRestrictions()->attach(
            $species->random(2)->pluck('id')->toArray()
        );

/**
 * A couple of partner group restrictions for a couple of vouchers next
 */
        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherPartnerGroupRestrictions");
        foreach (App\Voucher::inRandomOrder()->take(2)->get() as $voucher) {
            $voucher->partnerGroupRestrictions()->attach(
                App\PartnerGroup::inRandomOrder()->limit(2)->get()
            );
        }
    }
}
