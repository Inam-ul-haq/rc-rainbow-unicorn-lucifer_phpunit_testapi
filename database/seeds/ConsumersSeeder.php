<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ConsumersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Consumer::class, 150)->create();
    }
}
