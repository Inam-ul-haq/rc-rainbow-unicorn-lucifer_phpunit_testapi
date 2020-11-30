<?php

namespace Tests\Feature\v0;

use App\User;
use Tests\TestCase;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SystemTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testCanUpdateMotd()
    {
        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'motd',
                'variable_value' => "Initial system seeding has been completed.",
            ]
        );

        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'motd_level',
                'variable_value' => 'info',
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'PATCH',
                $this->baseurl . 'system/motd',
                [
                    'motd' => 'The New MOTD',
                    'motd_level' => 'new_level',
                ]
            )
            ->assertStatus(200);

        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'motd',
                'variable_value' => 'The New MOTD',
            ]
        );

        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'motd_level',
                'variable_value' => 'new_level',
            ]
        );
    }

    public function testCanUpdateRunLevel()
    {
        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'open_mode',
                'variable_value' => '2',
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'PATCH',
                $this->baseurl . 'system/runlevel',
                [
                    'runlevel' => '0',
                ]
            )
            ->assertStatus(200);

        $this->assertDatabaseHas(
            'system_variables',
            [
                'variable_name' => 'open_mode',
                'variable_value' => '0',
            ]
        );
    }

    public function testCantUpdateRunLevelToInvalidLevel()
    {
        $response = $this->actingAs(User::first())
            ->json(
                'PATCH',
                $this->baseurl . 'system/runlevel',
                [
                    'runlevel' => '3',
                ]
            )
            ->assertStatus(422);
    }
}
