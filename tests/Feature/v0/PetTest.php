<?php

namespace Tests\Feature\v0;

use App\Pet;
use App\User;
use App\Consumer;
use App\PetWeights;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PetTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testCanAddPet()
    {
        $consumer = factory(Consumer::class)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'pet/add',
                [
                    'consumer_uuid' => $consumer->uuid,
                    'name' => 'James',
                    'dob' => '2018-09-11',
                    'breed_id' => 1,
                    'gender' => 'female',
                ]
            )
            ->assertStatus(201)
            ->assertJsonStructure(
                [
                    'data' => [
                        'uuid',
                        'consumer_uuid',
                        'pet_name',
                        'pet_dob',
                        'pet_gender',
                        'neutered',
                        'created_at',
                        'updated_at',
                        'breed' => [
                            'id',
                            'breed_name',
                            'species' => [
                                'id',
                                'species_name',
                            ],
                        ],
                        'weights' => [
                            '*' => [
                                'id',
                                'pet_id',
                                'pet_weight',
                                'date_entered',
                                'created_at',
                                'updated_at',
                            ],
                        ],
                    ],
                ]
            );
    }

    public function testGetPetInformation()
    {
        $consumer = factory(Consumer::class)->create();
        $pet = factory(Pet::class)->create(
            [
                'consumer_id' => $consumer->id,
            ]
        );
        factory(PetWeights::class, 5)->create(
            [
                'pet_id' => $pet->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "pet/{$pet->uuid}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'consumer_uuid',
                    'pet_name',
                    'pet_dob',
                    'pet_gender',
                    'neutered',
                    'created_at',
                    'updated_at',
                    'breed' => [
                        'id',
                        'breed_name',
                        'species' => [
                            'id',
                            'species_name',
                        ],
                    ],
                    'weights' => [
                        '*' => [
                            'id',
                            'pet_id',
                            'pet_weight',
                            'date_entered',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ]
        );
    }

    public function testCantGetPetInformation()
    {
        $consumer = factory(Consumer::class)->create();
        $pet = factory(Pet::class)->create(
            [
                'consumer_id' => $consumer->id,
            ]
        );
        factory(PetWeights::class, 5)->create(
            [
                'pet_id' => $pet->id,
            ]
        );

        $user = factory(User::class)->create();

        $response = $this->actingAs($user)
        ->json(
            'GET',
            $this->baseurl . "pet/{$pet->uuid}"
        )
        ->assertStatus(403);
    }
}
