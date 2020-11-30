<?php

namespace Tests\Feature\v0;

use DB;
use JWTAuth;
use App\Pet;
use App\Tag;
use App\User;
use App\Coupon;
use App\Partner;
use App\Voucher;
use App\Consumer;
use App\Referrer;
use App\PetWeights;
use Tests\Feature\V0Test;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ConsumerTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testConsumerCantLoginAtRunLevel0()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'password' => 'TestPassword',
            ]
        );

        $this->setRunLevel(0);

        $response = $this->json(
            'POST',
            $this->baseurl . 'consumer/login',
            [
                'email' => $consumer->email,
                'password' => 'TestPassword',
            ]
        )
        ->assertStatus(503);
    }

    public function testConsumerCantLoginAtRunLevel1()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'password' => 'TestPassword',
            ]
        );

        $this->setRunLevel(1);

        $response = $this->json(
            'POST',
            $this->baseurl . 'consumer/login',
            [
                'email' => $consumer->email,
                'password' => 'TestPassword',
            ]
        )
        ->assertStatus(503);
    }

    public function testConsumerCanLoginAtRunLevel2()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'active' => 1,
                'blacklisted' => 0,
                'deactivated_at' => null,
                'password' => 'TestPassword',
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'consumer/login',
            [
                'email' => $consumer->email,
                'password' => 'TestPassword',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'first_name',
                    'last_name',
                    'uuid',
                    'email',
                    'expires_in',
                    'token_type',
                    'access_token',
                    'password_change_needed',
                ]
            ]
        );
    }

    public function testConsumerCantLoginWithWrongPassword()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'consumer/login',
            [
                'email' => $consumer->email,
                'password' => 'ThisIsNotTheCorrectPassword',
            ]
        )
        ->assertStatus(401);
    }

    public function testCanRefreshToken()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'active' => 1,
                'blacklisted' => 0,
                'deactivated_at' => null,
                'password' => 'TestPassword',
            ]
        );
        $consumer->save();

        $this->setRunLevel(2);

        $token = JWTAuth::fromUser($consumer);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'consumer/refresh_token'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'first_name',
                    'last_name',
                    'uuid',
                    'email',
                    'expires_in',
                    'token_type',
                    'access_token',
                    'password_change_needed',
                ]
            ]
        )
        ->assertJsonFragment(
            [
                'uuid' => $consumer->uuid,
            ]
        );
    }

    public function testActivateConsumerAccount()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'active' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'active' => 0,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/activate"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'active' => 1,
            ]
        );
    }

    public function testDeactivateConsumerAccount()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'active' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'active' => 1,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/deactivate"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'active' => 0,
            ]
        );
    }

    public function testAddConsumerToBlacklist()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'blacklisted' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'blacklisted' => 0,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/add_blacklist"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'blacklisted' => 1,
            ]
        );
    }

    public function removeConsumerFromBlacklist()
    {
        $consumer = factory(Consumer::class)->create(
            [
                'blacklisted' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'blacklisted' => 1,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/remove_blacklist"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'uuid' => $consumer->uuid,
                'blacklisted' => 0,
            ]
        );
    }

    public function testUpdateConsumerSubscriptions()
    {
        $consumer = factory(Consumer::class)->create();
        $tags = factory(Tag::class, 5)->create();

        $this->assertDatabaseMissing(
            'consumer_tag',
            [
                'consumer_id' => $consumer->id,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "consumer/{$consumer->uuid}/update_tags",
            [
                'tags' => $tags->pluck('uuid'),
            ]
        );

        foreach ($tags as $tag) {
            $this->assertDatabaseHas(
                'consumer_tag',
                [
                    'tag_id' => $tag->id,
                    'consumer_id' => $consumer->id,
                ]
            );
        }
    }

    public function testCanAddNewUserToCampaign()
    {
        $tags = factory(Tag::class, 5)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'consumer/add_to_campaign',
                [
                    'email' => 'testuser@test.com',
                    'campaign_tag' => $tags[0]->tag,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'uuid',
                ]
            )
            ->getData();

        $this->assertDatabaseHas(
            'consumers',
            [
                'uuid' => $response->uuid,
                'email' => 'testuser@test.com',
            ]
        );

        $consumer = Consumer::where('email', '=', 'testuser@test.com')->first();

        $this->assertDatabaseHas(
            'consumer_tag',
            [
                'consumer_id' => $consumer->id,
                'tag_id' => $tags[0]->id,
            ]
        );
    }

    public function testCanAddExistingUserToCampaign()
    {
        $tag = factory(Tag::class)->create();
        $consumer = factory(Consumer::class)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'consumer/add_to_campaign',
                [
                    'email' => $consumer->email,
                    'campaign_tag' => $tag->tag,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'uuid',
                ]
            );

        $this->assertDatabaseHas(
            'consumer_tag',
            [
                'consumer_id' => $consumer->id,
                'tag_id' => $tag->id,
            ]
        );
    }

    public function testCantAddSameCampaignTagTwice()
    {
        $tag = factory(Tag::class)->create();
        $consumer = factory(Consumer::class)->create();

        $consumer->tags()->attach($tag);

        $this->assertDatabaseHas(
            'consumer_tag',
            [
                'consumer_id' => $consumer->id,
                'tag_id' => $tag->id,
            ]
        );

        /**
         * Laravel 7 introducted an assertDatabaseCount() function. We're, however, using
         * Laravel 6 so have to do it this way instead.
         */
        $count = DB::table('consumer_tag')
            ->where('consumer_id', $consumer->id)
            ->where('tag_id', $tag->id)
            ->count();

        $this->assertEquals(1, $count);

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . 'consumer/add_to_campaign',
                [
                    'email' => $consumer->email,
                    'campaign_tag' => $tag->tag,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'uuid',
                ]
            );

        $count = DB::table('consumer_tag')
            ->where('consumer_id', $consumer->id)
            ->where('tag_id', $tag->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function testGetUserActivityLog()
    {
        $consumer = factory(Consumer::class)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/activity"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'id',
                        'log_name',
                        'log_label',
                        'description',
                        'subject_id',
                        'subject_type',
                        'causer_id',
                        'causer_type',
                        'ip_address',
                        'properties' => [],
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]
        );
    }

    public function testRequestConsumerDataZipFile()
    {
        $consumer = factory(Consumer::class)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/export_data"
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'type',
                'status',
                'updated_at',
                'created_at',
                'uuid',
            ]
        );
    }

    public function testSendConsumerResetPasswordEmail()
    {
        $consumer = factory(Consumer::class)->create();

        $result = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "consumer/{$consumer->uuid}/reset_pw",
            [
                'reset_url' => 'http://www.test.local',
            ]
        )
        ->assertStatus(200);
    }

    public function testCantSendConsumerResetPasswordEmailWithoutResetURL()
    {
        $consumer = factory(Consumer::class)->create();

        $result = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "consumer/{$consumer->uuid}/reset_pw"
        )
        ->assertStatus(422);
    }

    public function testSearchConsumersGetAll()
    {
        $consumers = factory(Consumer::class, 50)->create();

        $result = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'consumers/search'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name_title',
                        'first_name',
                        'last_name',
                        'crm_id',
                        'last_update_from_crm',
                        'address_line_1',
                        'town',
                        'county',
                        'country',
                        'postcode',
                        'email',
                        'telephone',
                        'blacklisted',
                        'active',
                        'deactivated_at',
                        'blacklisted_at',
                        'relationships' => [
                            'name_title' => [
                                'id',
                                'title',
                            ],
                            'coupons' => [
                            ],
                        ],
                    ],
                ],
            ]
        )
        ->baseResponse
        ->getData()
        ->data;

        $this->assertEquals(count($consumers), count($result));
    }

    public function testSearchConsumersCSVExport()
    {
        $consumers = factory(Consumer::class, 20)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'consumers/search/csv'
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'type',
                'status',
                'updated_at',
                'created_at',
                'uuid',
            ]
        );
    }

    public function testSearchConsumersByName()
    {
        factory(Consumer::class, 50)->create();
        $test_users = factory(Consumer::class, 5)->create(
            [
                'first_name' => 'ThisNameWillNotExist',
            ]
        );

        $result = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumers/search",
            [
                'search' => 'ThisNameWillNotExist',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name_title',
                        'first_name',
                        'last_name',
                        'crm_id',
                        'last_update_from_crm',
                        'address_line_1',
                        'town',
                        'county',
                        'postcode',
                        'email',
                        'telephone',
                        'blacklisted',
                        'active',
                        'deactivated_at',
                        'blacklisted_at',
                        'relationships' => [
                            'name_title' => [
                                'id',
                                'title',
                            ],
                            'coupons' => [
                            ],
                        ],
                    ],
                ],
            ]
        )
        ->baseResponse
        ->getData()
        ->data;

        $this->assertEquals(count($test_users), count($result));
    }

    public function testSearchConsumersActiveFlag()
    {
        $active_users = factory(Consumer::class, 10)->create(
            [
                'active' => 1,
            ]
        );
        $inactive_users = factory(Consumer::class, 5)->create(
            [
                'active' => 0,
            ]
        );

        $result = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumers/search",
            [
                'active' => 1,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name_title',
                        'first_name',
                        'last_name',
                        'crm_id',
                        'last_update_from_crm',
                        'address_line_1',
                        'town',
                        'county',
                        'postcode',
                        'email',
                        'telephone',
                        'blacklisted',
                        'active',
                        'deactivated_at',
                        'blacklisted_at',
                        'relationships' => [
                            'name_title' => [
                                'id',
                                'title',
                            ],
                            'coupons' => [
                            ],
                        ],
                    ],
                ],
            ]
        )
        ->baseResponse
        ->getData()
        ->data;

        $this->assertEquals(count($active_users), count($result));
    }

    public function testSearchConsumersBlacklistFlag()
    {
        $blacklist_users = factory(Consumer::class, 10)->create(
            [
                'blacklisted' => 1,
            ]
        );
        $non_blacklist_users = factory(Consumer::class, 5)->create(
            [
                'blacklisted' => 0,
            ]
        );

        $result = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumers/search",
            [
                'blacklist' => 0,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name_title',
                        'first_name',
                        'last_name',
                        'crm_id',
                        'last_update_from_crm',
                        'address_line_1',
                        'town',
                        'county',
                        'postcode',
                        'email',
                        'telephone',
                        'blacklisted',
                        'active',
                        'deactivated_at',
                        'blacklisted_at',
                        'relationships' => [
                            'name_title' => [
                                'id',
                                'title',
                            ],
                            'coupons' => [
                            ],
                        ],
                    ],
                ],
            ]
        )
        ->baseResponse
        ->getData()
        ->data;

        $this->assertEquals(count($non_blacklist_users), count($result));
    }

    public function testGetListOfConsumersWithPagination()
    {
        $per_page = 5;
        $consumers = factory(Consumer::class, 30)->create();
        $result = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'consumers',
            [
                'per_page' => $per_page,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name_title',
                        'first_name',
                        'last_name',
                        'crm_id',
                        'last_update_from_crm',
                        'address_line_1',
                        'town',
                        'county',
                        'postcode',
                        'email',
                        'telephone',
                        'blacklisted',
                        'active',
                        'deactivated_at',
                        'blacklisted_at',
                        'relationships' => [
                            'name_title' => [
                                'id',
                                'title',
                            ],
                            'coupons' => [
                            ],
                        ],
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
                    'path',
                    'per_page',
                    'to',
                ],
            ]
        )
        ->baseResponse
        ->getData()
        ->data;

        $this->assertEquals($per_page, count($result));
    }

    public function testGetConsumerInfo()
    {
        $consumer = factory(Consumer::class)->create();
        $pets = factory(Pet::class)->create(
            [
                'consumer_id' => $consumer->id,
            ]
        );
        $pet_weights = factory(PetWeights::class, 10)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}",
            [
                'relations' => [
                    'name_title',
                    'pets',
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name_title',
                    'first_name',
                    'last_name',
                    'crm_id',
                    'last_update_from_crm',
                    'address_line_1',
                    'town',
                    'county',
                    'postcode',
                    'email',
                    'telephone',
                    'blacklisted',
                    'active',
                    'deactivated_at',
                    'blacklisted_at',
                    'relationships' => [
                        'name_title' => [
                            'id',
                            'title',
                        ],
                        'pets' => [
                            'data' => [
                                '*' => [
                                    'uuid',
                                    'consumer_uuid',
                                    'pet_name',
                                    'pet_dob',
                                    'pet_gender',
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
                            ],
                            'meta' => [
                                'pet_count',
                            ],
                        ],
                        'coupons' => [
                        ],
                    ],
                ],
            ]
        );
    }

    public function testGetConsumerInfoByEmail()
    {
        $consumer = factory(Consumer::class)->create();
        $pets = factory(Pet::class)->create(
            [
                'consumer_id' => $consumer->id,
            ]
        );
        $pet_weights = factory(PetWeights::class, 10)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/email/{$consumer->email}",
            [
                'relations' => [
                    'name_title',
                    'pets',
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name_title',
                    'first_name',
                    'last_name',
                    'crm_id',
                    'last_update_from_crm',
                    'address_line_1',
                    'town',
                    'county',
                    'postcode',
                    'email',
                    'telephone',
                    'blacklisted',
                    'active',
                    'deactivated_at',
                    'blacklisted_at',
                    'relationships' => [
                        'name_title' => [
                            'id',
                            'title',
                        ],
                        'pets' => [
                            'data' => [
                                '*' => [
                                    'uuid',
                                    'consumer_uuid',
                                    'pet_name',
                                    'pet_dob',
                                    'pet_gender',
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
                            ],
                            'meta' => [
                                'pet_count',
                            ],
                        ],
                        'coupons' => [
                        ],
                    ],
                ],
            ]
        );
    }

    public function testRemoveConsumer()
    {
        $consumer = factory(Consumer::class)->create();

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
            ]
        );

        $this->actingAs(User::first())
            ->json(
                'DELETE',
                $this->baseurl . "consumer/{$consumer->uuid}"
            )
            ->assertStatus(200);

        $this->assertSoftDeleted(
            'consumers',
            [
                'id' => $consumer->id,
            ]
        );
    }

    public function testUpdateConsumerEmailOnly()
    {
        $new_email_address = 'hello@world.test';

        $consumer = factory(Consumer::class)->create();

        $this->actingAs(User::first())
            ->json(
                'PATCH',
                $this->baseurl . "consumer/{$consumer->uuid}",
                [
                    'email' => $new_email_address,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                'data' => [
                    'uuid',
                    'name_title',
                    'first_name',
                    'last_name',
                    'crm_id',
                    'last_update_from_crm',
                    'address_line_1',
                    'town',
                    'county',
                    'postcode',
                    'email',
                    'telephone',
                    'blacklisted',
                    'active',
                    'deactivated_at',
                    'blacklisted_at',
                    'relationships' => [
                        'name_title' => [
                            'id',
                            'title',
                        ],
                        'coupons' => [
                        ],
                    ],
                ],
                ]
            )
        ->assertJsonFragment(
            [
                'email' => $new_email_address,
            ]
        );

        $this->assertDatabaseHas(
            'consumers',
            [
                'id' => $consumer->id,
                'email' => $new_email_address,
            ]
        );
    }

    public function testCantUpdateConsumerToUsedEmail()
    {
        $consumers = factory(Consumer::class, 2)->create();

        $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . "consumer/{$consumers[0]->uuid}",
            [
                'email' => $consumers[1]->email,
            ]
        )
        ->assertStatus(422);
    }

    public function cantUpdateConsumerOtherThanEmail()
    {
        $consumer = factory(Consumer::class)->create();

        $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . "consumer/{$consumer->uuid}",
            [
                'first_name' => 'Wibble',
            ]
        )
        ->assertStatus(422);
    }

    public function testGetConsumerCoupons()
    {
        factory(Partner::class)->create();
        factory(Voucher::class)->create();
        factory(VoucherAccessCode::class)->create();
        $consumer = factory(Consumer::class)->create();
        factory(Referrer::class)->create();
        factory(VoucherUniqueCode::class)->create();
        $coupons = factory(Coupon::class, 10)->create();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "consumer/{$consumer->uuid}/coupons"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'issued_at',
                        'voucher_uuid',
                        'barcode',
                        'valid_from',
                        'valid_to',
                        'redeemed_datetime',
                        'redemption_method',
                        'created_at',
                        'updated_at',
                        'cancelled_at',
                        'vouchers_unique_codes_used_id',
                        'redeemed_by_consumer_uuid',
                        'restrict_consumer_uuid',
                        'restrict_partner_uuid',
                        'referrer' => [
                        ],
                        'maximum_uses',
                        'shared_code',
                        'redemption_partner_uuid',
                        'reissued_as_coupon_uuid',
                        'redemption_partner',
                        'restricted_to_partner',
                        'status',
                        'voucher' => [
                            'uuid',
                            'url',
                            'name',
                            'value_gbp',
                            'value_eur',
                            'page_copy',
                            'created_at',
                            'updated_at',
                            'referrer_points_at_create',
                            'referrer_points_at_redeem',
                            'unique_code_prefix',
                            'unique_code_required',
                        ],
                    ],
                ],
                'meta' => [
                    'coupon_count',
                ],
            ]
        );
    }

    public function testGetConsumersRelatedPartners()
    {
        $consumer = factory(Consumer::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        factory(Referrer::class)->create();
        factory(Voucher::class)->create();
        factory(\App\VoucherTerms::class)->create();
        factory(VoucherAccessCode::class)->create();
        factory(VoucherUniqueCode::class)->create();

        $coupon1 = factory(Coupon::class)->create();
        $coupon2 = factory(Coupon::class)->create();
        $coupon3 = factory(Coupon::class)->create();
        $coupon4 = factory(Coupon::class)->create();

        $coupon1->redeemed_by_consumer_id = $consumer->id;
        $coupon1->redemption_partner_id = $partner1->id;
        $coupon1->save();

        $coupon2->redeemed_by_consumer_id = $consumer->id;
        $coupon2->restrict_partner_id = $partner2->id;
        $coupon2->save();

        $coupon3->redeemed_by_consumer_id = $consumer->id;
        $coupon3->redemption_partner_id = $partner3->id;
        $coupon3->save();

        $coupon4->redeemed_by_consumer_id = $consumer->id;
        $coupon4->redemption_partner_id = $partner2->id;
        $coupon4->save();

        $result = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumer->uuid . '/partners'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'uuid',
                            'type',
                            'subtype',
                            'public_name',
                            'public_latitude',
                            'public_longitude',
                            'contact_first_name',
                            'contact_last_name',
                            'contact_telephone',
                            'contact_email',
                            'public_street_line1',
                            'public_street_line2',
                            'public_street_line3',
                            'public_town',
                            'public_county',
                            'public_postcode',
                            'public_country',
                            'public_email',
                            'public_vat_number',
                            'accepts_vouchers',
                            'accepts_loyalty',
                            'crm_id',
                        ],
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partner1->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partner2->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partner3->uuid
                ]
            )
            ->baseResponse
            ->getData()
            ->data;

        $this->assertEquals(3, count($result));
    }

    /**
     * This test checks the voucher lists that a consumer with no pets, but one coupon
     * for a voucher with a limit of 1 gets.
     */
    public function testGetAvailableVouchersNoPetsWithCoupon()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[0]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    /**
     * This test checks the voucher lists that a consumer with no pets, and no coupons gets
     */
    public function testGetAvailableVouchersNoPetsWithoutCoupon()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[1]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    /**
     * This test checks the vouchers list that a user with 1 pet (species id 1), with one
     * coupon for a voucher with a limit of 1 gets
     */
    public function testGetAvailableVouchersOnePetWithCoupon()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[2]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    /**
     * This test checks the vouchers list that a user with 1 pet (species id 2), with one
     * coupon for a voucher with a limit of 1 gets
     */
    public function testGetAvailableVouchersOnePetDifferentSpeciesWithCoupon()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[3]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    public function testGetAvailableVouchersOnePetSameSpeciesAsSpeciesRestriction()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[4]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    public function testGetAvailableVouchersOnePetDifferentSpeciesAsSpeciesRestrictionNoCoupons()
    {
        extract($this->setUpForAvailableVouchersTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'consumer/' . $consumers[5]->uuid . '/available_vouchers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'vouchers_available' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                    'invalid_vouchers' => [
                        '*' => [
                            'name',
                            'uuid',
                        ],
                    ],
                ]
            )
            ->getData();

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[0]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[1]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[2]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[3]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[4]->uuid->toString()
            )
        );

        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );
        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[5]->uuid->toString()
            )
        );

        $this->assertTrue(
            $this->arrayOfArraysSearch(
                $response->vouchers_available,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
        $this->assertFalse(
            $this->arrayOfArraysSearch(
                $response->invalid_vouchers,
                'uuid',
                $vouchers[6]->uuid->toString()
            )
        );
    }

    private function setUpForAvailableVouchersTests()
    {
        /**
         * All vouchers valid for todays date and published, unless otherwise specified.
         *
         * $vouchers[0] - no limits applied
         * $vouchers[1] - no limits applied, not published.
         * $vouchers[2] - expired, no limits applied
         * $vouchers[3] - limit_per_account set to 1
         * $vouchers[4] - limit_pet_required set to 1,
         * $vouchers[5] - limit_species_id set to 1
         * $vouchers[6] - limit_per_pet set to 2
         *
         * $consumers[0] - has no pets, has one coupon for $vouchers[3]
         * $consumers[1] - has no pets, no coupons
         * $consumers[2] - has 1 pet, species id 1, one coupon for $vouchers[3]
         * $consumers[3] - has 1 pet, species id 2, one coupon for $vouchers[3]
         * $consumers[4] - has 1 pet, species id 1, no coupons
         * $consumers[5] - has 1 pet, species id 2, no coupons
         */

        $vouchers = [];
        $vouchers[0] = factory(Voucher::class)->create([
            'public_name' => 'no limits applied',
        ]);
        $vouchers[1] = factory(Voucher::class)->create([
            'public_name' => 'no limits applied, not published',
        ]);
        $vouchers[2] = factory(Voucher::class)->create([
            'public_name' => 'expired, no limits applied',
        ]);
        $vouchers[3] = factory(Voucher::class)->create([
            'public_name' => 'limit_per_account set to 1',
        ]);
        $vouchers[4] = factory(Voucher::class)->create([
            'public_name' => 'limit_pet_required set to 1',
        ]);
        $vouchers[5] = factory(Voucher::class)->create([
            'public_name' => 'limit_species_id set to 1',
        ]);
        $vouchers[6] = factory(Voucher::class)->create([
            'public_name' => 'limit_per_pet set to 2',
        ]);

        $consumers = [];
        $consumers[0] = factory(Consumer::class)->create([
            'first_name' => 'No Pets',
            'last_name' => "One coupon for {$vouchers[3]->id}",
        ]);
        $consumers[1] = factory(Consumer::class)->create([
            'first_name' => 'No Pets',
            'last_name' => 'No Coupons',
        ]);
        $consumers[2] = factory(Consumer::class)->create([
            'first_name' => 'One pet, species id 1',
            'last_name' => "One coupon, for {$vouchers[3]->id}",
        ]);
        $consumers[3] = factory(Consumer::class)->create([
            'first_name' => 'One pet, species id 2',
            'last_name' => "One coupon, for {$vouchers[3]->id}",
        ]);
        $consumers[4] = factory(Consumer::class)->create([
            'first_name' => 'One pet, species id 1',
            'last_name' => 'No Coupons',
        ]);
        $consumers[5] = factory(Consumer::class)->create([
            'first_name' => 'One pet, species id 2',
            'last_name' => 'No Coupons',
        ]);

//        $consumers = factory(Consumer::class, 6)->create();

        $vouchers[0]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[0]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[0]->limit_per_account = 0;
        $vouchers[0]->limit_per_account_per_date_period = '0';
        $vouchers[0]->limit_pet_required = 0;
        $vouchers[0]->limit_per_pet = 0;
        $vouchers[0]->limit_species_id = null;
        $vouchers[0]->published = 1;
        $vouchers[0]->save();

        $vouchers[1]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[1]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[1]->limit_per_account = 0;
        $vouchers[1]->limit_per_account_per_date_period = '0';
        $vouchers[1]->limit_pet_required = 0;
        $vouchers[1]->limit_per_pet = 0;
        $vouchers[1]->limit_species_id = null;
        $vouchers[1]->published = 0;
        $vouchers[1]->save();

        $vouchers[2]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[2]->subscribe_to_date = Carbon::now()->subDays(5);
        $vouchers[2]->limit_per_account = 0;
        $vouchers[2]->limit_per_account_per_date_period = '0';
        $vouchers[2]->limit_pet_required = 0;
        $vouchers[2]->limit_per_pet = 0;
        $vouchers[2]->limit_species_id = null;
        $vouchers[2]->published = 1;
        $vouchers[2]->save();

        $vouchers[3]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[3]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[3]->limit_per_account = 1;
        $vouchers[3]->limit_per_account_per_date_period = '0';
        $vouchers[3]->limit_pet_required = 0;
        $vouchers[3]->limit_per_pet = 0;
        $vouchers[3]->limit_species_id = null;
        $vouchers[3]->published = 1;
        $vouchers[3]->save();

        $vouchers[4]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[4]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[4]->limit_per_account = 0;
        $vouchers[4]->limit_per_account_per_date_period = '0';
        $vouchers[4]->limit_pet_required = 1;
        $vouchers[4]->limit_per_pet = 0;
        $vouchers[4]->limit_species_id = null;
        $vouchers[4]->published = 1;
        $vouchers[4]->save();

        $vouchers[5]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[5]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[5]->limit_per_account = 0;
        $vouchers[5]->limit_per_account_per_date_period = '0';
        $vouchers[5]->limit_pet_required = 1;
        $vouchers[5]->limit_per_pet = 0;
        $vouchers[5]->limit_species_id = 1;
        $vouchers[5]->published = 1;
        $vouchers[5]->save();

        $vouchers[6]->subscribe_from_date = Carbon::now()->subDays(10);
        $vouchers[6]->subscribe_to_date = Carbon::now()->addDays(10);
        $vouchers[6]->limit_per_account = 0;
        $vouchers[6]->limit_per_account_per_date_period = '0';
        $vouchers[6]->limit_pet_required = 1;
        $vouchers[6]->limit_per_pet = 2;
        $vouchers[6]->limit_species_id = null;
        $vouchers[6]->published = 1;
        $vouchers[6]->save();

        $coupons = [];
        $coupons[0] = new Coupon(
            [
                'voucher_id' => $vouchers[3]->id,
                'restrict_consumer_id' => $consumers[0]->id,
                'issued_at' => now(),
                'valid_from' => now(),
                'valid_to' => now(),
            ]
        );
        $coupons[0]->save();

        $coupons[1] = new Coupon(
            [
                'voucher_id' => $vouchers[3]->id,
                'restrict_consumer_id' => $consumers[2]->id,
                'issued_at' => now(),
                'valid_from' => now(),
                'valid_to' => now(),
            ]
        );
        $coupons[1]->save();

        $coupons[2] = new Coupon(
            [
                'voucher_id' => $vouchers[3]->id,
                'restrict_consumer_id' => $consumers[3]->id,
                'issued_at' => now(),
                'valid_from' => now(),
                'valid_to' => now(),
            ]
        );
        $coupons[2]->save();

        $pets = [];
        $pets[0] = new Pet(
            [
                'consumer_id' => $consumers[2]->id,
                'pet_name' => 'Pet 0',
                'pet_dob' => Carbon::now()->subDays(90),
                'breed_id' => \App\Breed::where('species_id', '=', 1)->get()->random(1)->first()->id,
                'pet_gender' => 'male',
            ]
        );
        $pets[0]->save();

        $pets[1] = new Pet(
            [
                'consumer_id' => $consumers[3]->id,
                'pet_name' => 'Pet 1',
                'pet_dob' => Carbon::now()->subDays(90),
                'breed_id' => \App\Breed::where('species_id', '=', 2)->get()->random(1)->first()->id,
                'pet_gender' => 'male',
            ]
        );
        $pets[1]->save();

        $pets[2] = new Pet(
            [
                'consumer_id' => $consumers[4]->id,
                'pet_name' => 'Pet 2',
                'pet_dob' => Carbon::now()->subDays(90),
                'breed_id' => \App\Breed::where('species_id', '=', 1)->get()->random(1)->first()->id,
                'pet_gender' => 'male',
            ]
        );
        $pets[2]->save();

        $pets[3] = new Pet(
            [
                'consumer_id' => $consumers[5]->id,
                'pet_name' => 'Pet 3',
                'pet_dob' => Carbon::now()->subDays(90),
                'breed_id' => \App\Breed::where('species_id', '=', 2)->get()->random(1)->first()->id,
                'pet_gender' => 'male',
            ]
        );
        $pets[3]->save();

        return [
            'pets' => $pets,
            'coupons' => $coupons,
            'vouchers' => $vouchers,
            'consumers' => $consumers,
        ];
    }
}
