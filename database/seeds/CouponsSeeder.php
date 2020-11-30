<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class CouponsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherUniqueCodes");
        $code_prefix = 'ABCD';
        for ($i=1; $i <=200; $i++) {
            factory(App\VoucherUniqueCode::class)->create(['code' => sprintf("{$code_prefix}%06d", $i)]);
        }

        $this->command->getOutput()->writeln("<info>Seeding:</info>   VoucherAccessCodes");
        factory(App\VoucherAccessCode::class, 100)->create();

        factory(App\Coupon::class, 50)->create();
    }
}
