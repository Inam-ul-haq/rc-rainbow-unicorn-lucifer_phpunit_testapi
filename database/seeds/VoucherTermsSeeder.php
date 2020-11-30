<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class VoucherTermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\VoucherTerms::class, 20)->create();
    }
}
