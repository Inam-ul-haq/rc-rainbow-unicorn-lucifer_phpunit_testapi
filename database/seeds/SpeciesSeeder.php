<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class SpeciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ([ 'Cat', 'Dog' ] as $species) {
            App\Species::create([ 'species_name' => $species ]);
        }
    }
}
