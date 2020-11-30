<?php

use App\Helpers\Helper;
use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class TestDataDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        if (App\NameTitle::all()->count() > 0) {
            $this->command->getOutput()->writeln("<error>Not seeding: Database already seeded.");
            return 0;
        }

        $this->call(NameTitlesSeeder::class);
        $this->call(ConsumersSeeder::class);
        $this->call(SpeciesSeeder::class);
        $this->call(BreedsSeeder::class);
        $this->call(PetsSeeder::class);
        $this->call(PetWeightsSeeder::class);
        $this->call(PartnersSeeder::class);
        $this->call(PartnerGroupSeeder::class);
        $this->call(ReferrersSeeder::class);
        $this->call(ReferrerGroupSeeder::class);
        $this->call(ProductsSeeder::class);
        $this->call(UserPermissionsSeeder::class);
        $this->call(AddAdminUserSeed::class);
        $this->call(UsersSeeder::class);
        $this->call(VouchersSeeder::class);
        $this->call(VoucherTermsSeeder::class);
        $this->call(TagsSeeder::class);
        $this->call(CouponsSeeder::class);
        $this->call(ReferrerPointsSeeder::class);
        $this->call(InitialTestSystemVariablesSeeder::class);
        $this->call(ActivityLogSeeder::class);

        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );

        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("You have seeded using the TestDataDatabase class.") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("Nothing wrong with that at all, but one thing to be aware of is that") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("the data might not make perfect sense in some places!") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("For example, the Coupon factory just (optionally) chooses a Consumer") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("id for both restrict_consumer_id & redeemed_by_consumer_id but of") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("course in a live environment, if the restrict value is set, the") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("redeemed value would always be the same (or null).") .
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );
    }
}
