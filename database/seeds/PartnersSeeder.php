<?php

use App\Helpers\Helper;
use Illuminate\Database\Seeder;

// @codingStandardsIgnoreLine
class PartnersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->getOutput()->writeln(
            "<comment>*************************************************************************************</comment>"
        );

        $partners = factory(App\Partner::class, 50)->create();
        foreach ($partners as $partner) {
            $pw = $this->generatePassword();
            $partner->access_password = Hash::make($pw);
            $partner->save();
            $this->command->getOutput()->writeln(
                "<comment>" . Helper::formatConsoleLine("Partner created, {$partner->crm_id} - {$pw}") . "</comment>"
            );
        }

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
