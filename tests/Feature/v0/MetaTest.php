<?php

namespace Tests\Feature\v0;

use App\Tag;
use JWTAuth;
use App\User;
use App\Species;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MetaTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testGetNameTitles()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'metadata/name_titles'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'title',
                ]
            ]
        );
    }

    public function testGetSpecies()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'metadata/pets/species'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'species_name',
                ]
            ]
        );
    }

    public function testGetBreeds()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'metadata/pets/breeds'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'breed_name',
                    'species_id',
                ]
            ]
        );
    }

    public function testGetSpeciesBreeds()
    {
        $species = Species::firstOrFail();

        $response = $this->json(
            'GET',
            $this->baseurl . "metadata/pets/species/{$species->id}/breeds"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'breed_name',
                    'species_id',
                ]
            ]
        )
        ->assertJsonFragment(
            [
                'species_id' => $species->id,
            ]
        );
    }

    public function testGetTags()
    {
        factory(Tag::class, 5)->create();
        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'metadata/tags'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'tag',
                        'uuid',
                    ],
                ],
            ]
        );
    }

    public function testStandardUserCantGetMetaAtRunlevel1()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $user->blocked = 0;
        $user->save();

        $this->setRunLevel(1);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'metadata/pets/breeds'
        )
        ->assertStatus(503);
    }

    public function testStandardUserCantGetMetaAtRunlevel0()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $user->blocked = 0;
        $user->save();

        $this->setRunLevel(0);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'metadata/pets/breeds'
        )
        ->assertStatus(503);
    }
}
