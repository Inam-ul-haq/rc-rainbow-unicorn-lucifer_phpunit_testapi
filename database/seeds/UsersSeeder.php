<?php

use App\Helpers\Helper;
use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = factory(App\User::class, 5)->create()->each(function ($u) {
            $u->assignRole('customer care');
        });

        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );

        foreach ($users as $user) {
            $pw = $this->generatePassword();
            $user->password = $pw;
            $user->save();
            $this->command->getOutput()->writeln(
                "<comment>" .
                Helper::formatConsoleLine("Customer care account created - {$user->email}, {$pw}") .
                "</comment>"
            );
        }

        $users = factory(App\User::class, 5)->create()->each(function ($u) {
            $u->assignRole('rc admin');
        });
        foreach ($users as $user) {
            $pw = $this->generatePassword();
            $user->password = $pw;
            $user->save();
            $this->command->getOutput()->writeln(
                "<comment>" .
                Helper::formatConsoleLine("RC Admin account created, {$user->email}, {$pw}") .
                "</comment>"
            );
        }

        $users = factory(App\User::class, 5)->create()->each(function ($u) {
            $u->assignRole('marketing');
        });

        foreach ($users as $user) {
            $pw = $this->generatePassword();
            $user->password = $pw;
            $user->save();
            $this->command->getOutput()->writeln(
                "<comment>" .
                Helper::formatConsoleLine("Marketing account created, {$user->email}, {$pw}") .
                "</comment>"
            );
        }

        $users = factory(App\User::class, 200)->create()->each(function ($u) {
            $u->assignRole('partner user');
        });

        foreach ($users as $user) {
            $pw = $this->generatePassword();
            $user->password = $pw;
            $user->save();
            $this->command->getOutput()->writeln(
                "<comment>" .
                Helper::formatConsoleLine("Partner user account created, {$user->email}, {$pw}") .
                "</comment>"
            );
        }

        $users = factory(App\User::class, 100)->create()->each(function ($u) {
            $u->assignRole('business manager');
        });

        foreach ($users as $user) {
            $pw = $this->generatePassword();
            $user->password = $pw;
            $user->save();
            $this->command->getOutput()->writeln(
                "<comment>" .
                Helper::formatConsoleLine("Business manager account created, {$user->email}, {$pw}") .
                "</comment>"
            );
        }

        App\Partner::all()->each(function ($partner) {

            $partner->partnerUsers()->attach(
                App\User::role('partner user')
                ->get()
                ->random(2),
                [
                'manager' => 1,
                'approved' => 1,
                'approved_at' => now(),
                ]
            );

            $partner->partnerUsers()->attach(
                App\User::role('partner user')
                ->get()
                ->random(5),
                [
                'manager' => 0,
                'approved' => 1,
                'approved_at' => now(),
                ]
            );

            $partner->partnerUsers()->attach(
                App\User::role('partner user')
                ->get()
                ->random(2),
                [
                'manager' => 0,
                'approved' => 0,
                ]
            );

            $partner->salesReps()->attach(
                App\User::role('business manager')
                ->has('salesRepPartners', '=', 0)
                ->has('userPartners', '=', 0)
                ->get()
                ->random(2)
            );
        });

        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );
    }

    private function generatePassword($length = 8)
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $password = '';
        for ($i=0; $i < $length; $i++) {
            $password .= $alphabet[rand(0, strlen($alphabet)-1)];
        }
        return $password;
    }
}
