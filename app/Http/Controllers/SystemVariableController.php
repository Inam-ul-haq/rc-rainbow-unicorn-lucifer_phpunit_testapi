<?php

namespace App\Http\Controllers;

use App\SystemVariable;
use Illuminate\Http\Request;

class SystemVariableController extends Controller
{

    public function updateMotd(Request $request)
    {
        $request->validate(
            [
                'motd' => 'max:200',
                'motd_level' => 'max:200',
            ]
        );

        $motd_var = SystemVariable::where('variable_name', '=', 'motd')->first();
        $motd_var->variable_value = $request->input('motd', $motd_var->variable_value);
        $motd_var->save();

        $level_var = SystemVariable::where('variable_name', '=', 'motd_level')->first();
        $level_var->variable_value = $request->input('motd_level', $level_var->variable_value);
        $level_var->save();

        return [
            'motd' => $motd_var->variable_value,
            'motd_level' => $level_var->variable_value,
        ];
    }

    public function updateRunLevel(Request $request)
    {
        $request->validate(
            [
                'runlevel' => 'required|in:0,1,2',
            ]
        );

        $var = SystemVariable::where('variable_name', '=', 'open_mode')->first();
        $var->variable_value = $request->input('runlevel');
        $var->save();

        return [
            'open_mode' => $var->variable_value,
        ];
    }
}
