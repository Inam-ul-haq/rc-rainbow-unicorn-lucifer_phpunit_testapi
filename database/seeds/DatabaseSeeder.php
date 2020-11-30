<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        if (App\NameTitle::all()->count() > 0) {
            $this->command->getOutput()->writeln("<error>Not seeding - Database already seeded.");
            return 0;
        }

        $this->call(NameTitlesSeeder::class);
        $this->call(SpeciesSeeder::class);
        $this->call(BreedsSeeder::class);
        $this->call(UserPermissionsSeeder::class);
        $this->call(AddAdminUserSeed::class);
        $this->call(InitialSystemVariablesSeeder::class);
    }
}
