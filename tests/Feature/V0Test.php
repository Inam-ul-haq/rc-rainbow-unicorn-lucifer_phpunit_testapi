<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Traits\PHPUnitSetup;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class V0Test extends TestCase
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    protected $baseurl = 'api/v0/';

    public function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(
            \Illuminate\Routing\Middleware\ThrottleRequests::class
        );
        $this->setupDB();
        $this->setRunLevel(2);
    }

    public function testGetSystemStatus()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'system/status'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'level',
                'status',
                'motd_level',
                'motd',
            ]
        );
    }
}
