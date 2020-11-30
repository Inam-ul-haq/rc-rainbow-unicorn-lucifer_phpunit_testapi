<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class BreedsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cat_species_id = App\Species::where('species_name', 'Cat')->first()['id'];
        $dog_species_id = App\Species::where('species_name', 'Dog')->first()['id'];

        foreach ([ 'Abyssinian', 'American Curl', 'American Shorthair', 'American Wirehair' ] as $breed) {
            App\Breed::create([ 'breed_name' => $breed,
                                 'species_id' => $cat_species_id ]);
        }
        foreach ([ 'Affenpinscher', 'Afghan Hound', 'Aidi', 'Airedale Terrior' ] as $breed) {
            App\Breed::create([ 'breed_name' => $breed,
                                 'species_id' => $dog_species_id ]);
        }
    }
}
