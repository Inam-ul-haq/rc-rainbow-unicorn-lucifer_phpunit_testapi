<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class InitialTestSystemVariablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        App\SystemVariable::create([
            'variable_name' => 'motd',
            'variable_value' => 'Initial system seeding has been completed.',
        ]);

        App\SystemVariable::create([
            'variable_name' => 'motd_level',
            'variable_value' => 'info',
        ]);

        App\SystemVariable::create([
            'variable_name' => 'open_mode',
            'variable_value' => 2,  // 0- Closed, 1- Open for RC staff, 2- Open for all
        ]);

        App\SystemVariable::create([
            'variable_name' => 'apikeys_block_with_user',
            'variable_value' => 'dont_block',
        ]);
    }
}
