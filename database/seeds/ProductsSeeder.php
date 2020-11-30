<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Product::class, 50)->create();
    }
}
