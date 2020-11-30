<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ReferrerPointsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\ReferrerPoints::class, 200)->create();
    }
}
