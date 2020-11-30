<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class PartnerGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\PartnerGroup::class, 5)->create();

        $partner_groups = App\PartnerGroup::all();
        App\Partner::all()->each(function ($partner) use ($partner_groups) {
            $partner->groups()->attach(
                $partner_groups->random(rand(0, count($partner_groups)))->pluck('id')->toArray()
            );
        });
    }
}
