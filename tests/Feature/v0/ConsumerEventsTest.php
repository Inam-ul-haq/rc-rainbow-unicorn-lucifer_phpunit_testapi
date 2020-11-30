<?php

namespace Tests\Feature\v0;

use DB;
use App\User;
use App\Consumer;
use App\ConsumerEvent;
use App\Helpers\Helper;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConsumerEventsTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testCanCreateConsumerEvent()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event',
                [
                    'consumer_uuid' => $consumers[0]->uuid,
                    'event' => 'The Event Name',
                ]
            )
            ->assertStatus(201)
            ->assertJsonStructure(
                [
                    'data' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ]
                ]
            )
            ->getData()
            ->data;

        $this->assertDatabaseHas(
            'consumer_events',
            [
                'id' => $response->id,
                'event' => 'The Event Name',
                'event_source' => Helper::getCurrentSource(),
                'consumer_id' => $consumers[0]->id,
            ]
        );
    }

    public function testCanRemoveConsumerEvent()
    {
        extract($this->setUpForConsumerEventsTests());

        $this->assertDatabaseHas(
            'consumer_events',
            [
                'id' => $consumer_events[0]->id,
                'deleted_at' => null,
            ]
        );

        $response = $this->actingAs(User::first())
             ->json(
                 'DELETE',
                 $this->baseurl . 'event/' . $consumer_events[0]->id,
             )
             ->assertStatus(200);

        $this->assertDatabaseMissing(
            'consumer_events',
            [
                'id' => $consumer_events[0]->id,
                'deleted_at' => null,
            ]
        );
    }

    public function testCanGetConsumerEvent()
    {
        extract($this->setUpForConsumerEventsTests());

        $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'event/' . $consumer_events[0]->id
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ]
                ]
            )
            ->assertJsonFragment(
                [
                    'event_source' => $consumer_events[0]->event_source,
                    'consumer_id' => $consumer_events[0]->consumer_id,
                    'event' => $consumer_events[0]->event,
                ]
            );
    }

    public function testCanSearchConsumerEventsBySourceOnly()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event/search',
                [
                    'event_source' => 'source 1',  // 4 out of the 5 events should have this source
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                'data' => [
                    '*' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
                ]
            )
        ->getData()
        ->data;

        $this->assertEquals(4, count($response));
    }

    public function testCanSearchConsumerEventsByUuidOnly()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event/search',
                [
                    'consumer_uuid' => $consumers[0]->uuid, // 3 out of the 5 events should have this uuid
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                'data' => [
                    '*' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
                ]
            )
        ->getData()
        ->data;

        $this->assertEquals(3, count($response));
    }

    public function testCanSearchConsumerEventsByEventOnly()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event/search',
                [
                    'event' => 'Event 2', // 3 out of the 5 events should have this event
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                'data' => [
                    '*' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
                ]
            )
            ->getData()
            ->data;

        $this->assertEquals(3, count($response));
    }

    public function testCanSearchConsumerEventsByEventAndUuid()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event/search',
                [
                    'consumer_uuid' => $consumers[0]->uuid,
                    'event' => 'Event 1', // 2 out of the 5 events should have this combo
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                'data' => [
                    '*' => [
                        'event_source',
                        'consumer_id',
                        'event',
                        'properties',
                        'created_at',
                        'id',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
                ]
            )
        ->getData()
        ->data;

        $this->assertEquals(2, count($response));
    }

    public function testCanSearchConsumerEventsUsingDates()
    {
        extract($this->setUpForConsumerEventsTests());

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'event/search',
                [
                    'start_datetime' => '2020-07-03 01:00:00',
                    'end_datetime' => '2020-07-12 01:00:00',
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'event_source',
                            'consumer_id',
                            'event',
                            'properties',
                            'created_at',
                            'id',
                        ],
                    ],
                    'links' => [
                        'first',
                        'last',
                        'prev',
                        'next',
                    ],
                    'meta' => [
                        'current_page',
                        'from',
                        'last_page',
                        'path',
                        'per_page',
                        'to',
                        'total',
                    ],
                ]
            )
            ->getData()
            ->data;

        $this->assertEquals(2, count($response));
    }

    private function setUpForConsumerEventsTests()
    {
        $consumers = factory(Consumer::class, 5)->create();
        $consumer_events = [];

        $consumer_events[] = new ConsumerEvent(
            [
                'event' => 'Event 1',
                'consumer_id' => $consumers[0]->id,
                'event_source' => 'source 1',
            ]
        );

        $consumer_events[] = new ConsumerEvent(
            [
                'event' => 'Event 1',
                'consumer_id' => $consumers[0]->id,
                'event_source' => 'source 1',
            ]
        );

        $consumer_events[] = new ConsumerEvent(
            [
                'event' => 'Event 2',
                'consumer_id' => $consumers[0]->id,
                'event_source' => 'source 1',
                'created_at' => '2020-07-05 06:00:00',
            ]
        );

        $consumer_events[] = new ConsumerEvent(
            [
                'event' => 'Event 2',
                'consumer_id' => $consumers[1]->id,
                'event_source' => 'source 1',
                'created_at' => '2020-07-10 06:00:00',
            ]
        );

        $consumer_events[] = new ConsumerEvent(
            [
                'event' => 'Event 2',
                'consumer_id' => $consumers[2]->id,
                'event_source' => 'source 2',
                'created_at' => '2020-07-15 06:00:00',
            ]
        );

        foreach ($consumer_events as $event) {
            $event->save();
        }

        return [
            'consumers' => $consumers,
            'consumer_events' => $consumer_events,
        ];
    }
}
