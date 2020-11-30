<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class PetWeightsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\PetWeights::class, 500)->create();
    }
}
