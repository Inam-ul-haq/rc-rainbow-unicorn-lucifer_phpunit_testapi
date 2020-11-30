<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class PetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Pet::class, 75)->create();
    }
}
