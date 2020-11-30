<?php

use App\Helpers\Helper;
use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class AddAdminUserSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pw = str_random(8);
        $user = App\User::create([
            'name' => 'Admin User',
            'email' => 'webmaster@coastdigital.co.uk',
            'password' => $pw,
            'password_change_needed' => true,
            'name_title_id' => App\NameTitle::where('title', '=', 'Mx.')->first()->id,
        ]);
        $user->assignRole('admin');

        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>" .
            Helper::formatConsoleLine("Admin account created - webmaster@coastdigital.co.uk Temporary Password: {$pw}").
            "</comment>"
        );
        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );
    }
}
