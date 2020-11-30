<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ReferrerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\ReferrerGroup::class, 5)->create();

        $referrer_groups = App\ReferrerGroup::all();
        App\Referrer::all()->each(function ($referrer) use ($referrer_groups) {
            $referrer->referrerGroups()->attach(
                $referrer_groups->random(rand(0, count($referrer_groups)))->pluck('id')->toArray()
            );
        });
    }
}
