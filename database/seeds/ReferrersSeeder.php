<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ReferrersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Referrer::class, 50)->create();
    }
}
