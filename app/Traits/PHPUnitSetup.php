<?php

namespace App\Traits;

use Artisan;
use App\SystemVariable;

trait PHPUnitSetup
{
    public function setupDB()
    {
        if (getenv('TEST_DB_NEEDS_SEEDING') == '1') {
            Artisan::call('migrate:fresh');
            Artisan::call('db:seed');
            putenv('TEST_DB_NEEDS_SEEDING=0');
        }
    }

    public function setRunLevel($new_runlevel = '2')
    {
        $runlevel = SystemVariable::where('variable_name', 'open_mode')->firstOrFail();
        $runlevel->variable_value = $new_runlevel;
        $runlevel->save();
    }

    public function setApiKeysBlockWithUser($mode = 'dont_block')
    {
        $setting = SystemVariable::where('variable_name', 'apikeys_block_with_user')->firstOrFail();
        $setting->variable_value = $mode;
        $setting->save();
    }
}
