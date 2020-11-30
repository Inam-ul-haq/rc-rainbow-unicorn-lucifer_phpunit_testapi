<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class NameTitlesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ([ 'Mr.', 'Mrs.', 'Miss', 'Ms.', 'Mx.' ] as $title) {
            App\NameTitle::create([ 'title' => $title ]);
        }
    }
}
