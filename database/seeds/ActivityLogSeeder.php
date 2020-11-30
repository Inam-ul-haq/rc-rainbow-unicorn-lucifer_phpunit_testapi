<?php

use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class ActivityLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder should run after all the other test seeds have run, as it loops over the objects that have been
     * created so far during seeder, creating log entries for each of them. Has to be done this wasy unfortunately,
     * as it appears there's no way in Laravel factory() to run code after each individual model is created (and a
     * listener won't do the job, as that would have no way of knowing if the action is from seeding, or from normal
     * user functions).
     *
     * @return void
     */
    public function run()
    {
        $this->command->getOutput()->writeln("<info>  Consumers</info>");
        foreach (\App\Consumer::all() as $consumer) {
            activity('database test seed')
                ->on($consumer)
                ->tap('setLogLabel', 'create consumer account')
                ->log('Consumer Account Created');
        }

        foreach (\App\Consumer::where('active', '=', 0)->get() as $consumer) {
            activity('database test seed')
                ->on($consumer)
                ->causedBy(\App\User::all()->random())
                ->tap('setLogLabel', 'deactivate consumer account')
                ->log('Account Deactivated');
        }

        foreach (\App\Consumer::where('blacklisted', '=', 1)->get() as $consumer) {
            activity('database test seed')
                ->on($consumer)
                ->causedBy(\App\User::all()->random())
                ->tap('setLogLabel', 'blacklist consumer account')
                ->log('Account Blacklisted');
        }

        $this->command->getOutput()->writeln("<info>  Pets</info>");
        foreach (\App\Pet::all() as $pet) {
            activity('database test seed')
                ->on($pet)
                ->causedBy($pet->consumer)
                ->tap('setLogLabel', 'create pet')
                ->log('Pet Added');
        }

        $this->command->getOutput()->writeln("<info>  Pet Weights</info>");
        foreach (\App\PetWeights::all() as $weight) {
            activity('database test seed')
                ->on($weight)
                ->causedBy($weight->pet->consumer)
                ->tap('setLogLabel', 'add pet weight')
                ->log('Pet Weight Added');
        }

        $this->command->getOutput()->writeln("<info>  Coupons</info>");

        foreach (\App\Coupon::where('redemption_partner_id', '!=', null)->get() as $coupon) {
            activity('database test seed')
                ->on($coupon)
                ->causedBy($coupon->redemptionConsumer)
                ->withProperties([
                    'partner_id' => $coupon->redemption_partner_id,
                ])
                ->tap('setLogLabel', 'voucher redemption')
                ->log('Voucher redeemed');
        }
    }
}
