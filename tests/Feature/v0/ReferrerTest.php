<?php

namespace Tests\Feature\v0;

use Hash;
use App\User;
use App\Coupon;
use App\Partner;
use App\Voucher;
use App\Consumer;
use App\Referrer;
use App\NameTitle;
use App\VoucherTerms;
use App\ReferrerGroup;
use Ramsey\Uuid\Uuid;
use Tests\Feature\V0Test;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use App\Traits\PHPUnitSetup;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ReferrerTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testGetReferrerRedeemedCouponsList()
    {
        extract($this->setUpForRedeemedCouponsTests());

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "referrer/{$referrer->uuid}/redemptions"
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
                        'voucher' => [
                            'uuid',
                            'url',
                            'name',
                            'published',
                            'value_gbp',
                            'value_eur',
                            'subscribe_from_date',
                            'subscribe_to_date',
                            'redeem_from_date',
                            'redeem_to_date',
                            'redemption_period',
                            'redemption_period_count',
                            'public_name',
                            'page_copy',
                            'page_copy_image',
                            'unique_code_required',
                            'limit_pet_required',
                            'limit_species_id',
                            'current_terms' => [
                                'id',
                                'voucher_uuid',
                                'voucher_terms',
                                'used_from',
                                'used_until',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    public function testGetReferrerRedeemedCouponsCSVExport()
    {
        extract($this->setUpForRedeemedCouponsTests());

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "referrer/{$referrer->uuid}/redemptions/csv"
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

    public function testGetJobStatus()
    {
        extract($this->setUpForRedeemedCouponsTests());

        $job = $this->actingAs(User::first())
        ->get($this->baseurl . "referrer/{$referrer->uuid}/redemptions/csv")
        ->decodeResponseJson();

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "notification/{$job['uuid']}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'uuid',
                'type',
                'status',
                'created_at',
                'updated_at',
            ]
        );
    }

    public function testCanAddReferrerGroup()
    {
        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups',
            [
                'name' => 'My Group Name',
            ]
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'data' => [
                    'id',
                    'name',
                ],
            ]
        )
        ->getData()
        ->data;

        $this->assertDatabaseHas(
            'referrer_groups',
            [
                'id' => $response->id,
                'group_name' => 'My Group Name',
            ]
        );
    }

    public function testCantAddReferrerGroupWithExistingName()
    {
        $group = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups',
            [
                'name' => $group->name,
            ]
        )
        ->assertStatus(422);
    }

    public function testCanUpdateReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . 'referrers/groups/' . $group->id,
            [
                'name' => 'My New Group Name',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'id',
                    'name',
                ]
            ]
        );

        $this->assertDatabaseHas(
            'referrer_groups',
            [
                'id' => $group->id,
                'group_name' => 'My New Group Name',
            ]
        );
    }

    public function testCantUpdateReferrerGroupNameToExistingValue()
    {
        $group1 = factory(ReferrerGroup::class)->create();
        $group2 = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . 'referrers/groups/' . $group1->id,
            [
                'name' => $group2->group_name,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'referrer_groups',
            [
                'id' => $group1->id,
                'group_name' => $group1->group_name,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_groups',
            [
                'id' => $group2->id,
                'group_name' => $group2->group_name,
            ]
        );
    }

    public function testCanGetReferrerGroupDetails()
    {
        $group = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups/' . $group->id
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'id',
                    'name',
                ]
            ]
        )
        ->assertJsonFragment(
            [
                'id' => $group->id,
                'name' => $group->group_name,
            ]
        );
    }

    public function testCanGetReferrerGroupList()
    {
        $group1 = factory(ReferrerGroup::class)->create();
        $group2 = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ]
                ]
            ]
        )
        ->assertJsonFragment(
            [
                'id' => $group1->id,
                'name' => $group1->group_name,
            ]
        )
        ->assertJsonFragment(
            [
                'id' => $group2->id,
                'name' => $group2->group_name,
            ]
        );
    }

    public function testCanRemoveReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'referrers/groups/' . $group->id
        )
        ->assertStatus(204);

        $this->assertDatabaseMissing(
            'referrer_groups',
            [
                'id' => $group->id,
                'group_name' => $group->name
            ]
        );
    }

    public function testCanAddReferrerToReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add/' . $referrer->uuid
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer->id,
                'referrer_group_id' => $group->id,
            ]
        );
    }

    public function testCantAddExistingMemberToReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer = factory(Referrer::class)->create();
        $group->referrers()->attach($referrer);

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add/' . $referrer->uuid
        )
        ->assertStatus(422);
    }

    public function testCanAddMultipleReferrersToReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'referrer_uuids' => [
                        $referrer1->uuid,
                        $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'added_uuids',
                'existing_uuids',
                'notfound_uuids',
            ]
        )
        ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->added_uuids
        );

        $this->assertContains(
            $referrer2->uuid->toString(),
            $content->added_uuids
        );

        $this->assertCount(
            2,
            $content->added_uuids
        );

        $this->assertCount(
            0,
            $content->existing_uuids
        );

        $this->assertCount(
            0,
            $content->notfound_uuids
        );
    }

    public function testCanAddExistingMembersToReferrerGroupWithoutAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach($referrer1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'referrer_uuids' => [
                        $referrer1->uuid,
                        $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'added_uuids',
                'existing_uuids',
                'notfound_uuids',
            ]
        )
         ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->existing_uuids
        );

        $this->assertContains(
            $referrer2->uuid->toString(),
            $content->added_uuids
        );

        $this->assertCount(
            0,
            $content->notfound_uuids
        );
    }

    public function testCantAddExistingMembersToReferrerGroupWithAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach($referrer1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'referrer_uuids' => [
                        $referrer1->uuid,
                        $referrer2->uuid,
                ],
                'abortOnReferrerAlreadyMember' => 1,
            ]
        )
        ->assertStatus(422);
    }


    public function testCanAddNonExistantReferrersToReferrerGroupWithoutIgnoreNotFoundFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach($referrer1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'referrer_uuids' => [
                        $referrer1->uuid,
                        $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'added_uuids',
                'existing_uuids',
                'notfound_uuids',
            ]
        )
        ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->existing_uuids
        );

        $this->assertContains(
            $referrer2->uuid->toString(),
            $content->added_uuids
        );

        $this->assertCount(
            1,
            $content->existing_uuids
        );

        $this->assertCount(
            1,
            $content->added_uuids
        );

        $this->assertCount(
            0,
            $content->notfound_uuids
        );
    }

    public function testCantAddExistantMultipleReferrersToReferrerGroupWithNotFoundAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'referrer_uuids' => [
                        $referrer->uuid,
                        'an-invalid-uuid',
                ],
                'abortOnReferrerNotFound' => 1,
            ]
        )
        ->assertStatus(422);
    }

    public function testCanRemoveReferrerFromReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach($referrer1);
        $group->referrers()->attach($referrer2);

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/' . $referrer2->uuid
        )
        ->assertStatus(204);

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );
    }

    public function testCantRemoveNonMemberFromReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach($referrer1);

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/' . $referrer2->uuid
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );
    }

    public function testCanRemoveMultipleReferrersFromReferrerGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
                $referrer3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'removed_uuids',
                'notfound_uuids',
                'notmember_uuids',
            ]
        )
        ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer3->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $referrer2->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertNotContains(
            $referrer3->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertCount(
            0,
            $content->notfound_uuids
        );

        $this->assertCount(
            0,
            $content->notmember_uuids
        );
    }

    public function testCanRemoveNonExistantMultipleReferrersFromReferrerGroupWithoutAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
                $referrer3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    'a-non-existant-uuid',
                    $referrer3->uuid,
                ]
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'removed_uuids',
                'notfound_uuids',
                'notmember_uuids',
            ]
        )
        ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer3->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $referrer3->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            'a-non-existant-uuid',
            $content->notfound_uuids
        );

        $this->assertCount(
            0,
            $content->notmember_uuids
        );

        $this->assertCount(
            2,
            $content->removed_uuids
        );
        $this->assertCount(
            1,
            $content->notfound_uuids
        );
    }

    public function testCantRemoveNonExistantMultipleReferrersFromReferrerGroupWithAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
                $referrer3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    'a-non-existant-uuid',
                    $referrer3->uuid,
                ],
                'abortOnReferrerNotFound' => 1,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer3->id,
                'referrer_group_id' => $group->id,
            ]
        );
    }

    public function testCanRemoveNonMembersFromReferrerGroupsWithoutAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    $referrer3->uuid,
                ]
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'removed_uuids',
                'notfound_uuids',
                'notmember_uuids',
            ]
        )
        ->getContent();

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $referrer1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $referrer3->uuid->toString(),
            $content->notmember_uuids
        );

        $this->assertCount(
            1,
            $content->notmember_uuids
        );

        $this->assertCount(
            1,
            $content->removed_uuids
        );
        $this->assertCount(
            0,
            $content->notfound_uuids
        );
    }

    public function testCantRemoveNonMembersFromReferrerGroupsWithAbortFlagSet()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    $referrer3->uuid,
                ],
                'abortOnReferrerNotMember' => 1,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer1->id,
                'referrer_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_referrer',
            [
                'referrer_id' => $referrer2->id,
                'referrer_group_id' => $group->id,
            ]
        );
    }

    public function testCanGetReferrerGroupMembersList()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups/' . $group->id . '/members'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                    ],
                ],
            ]
        )
        ->assertJsonFragment(
            [
                'uuid' => $referrer1->uuid,
            ]
        )
        ->assertJsonFragment(
            [
                'uuid' => $referrer2->uuid,
            ]
        );
    }

    public function testCanGetListOfReferrers()
    {
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();
        $referrer3 = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'referrers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'uuid',
                            'email',
                            'name_title',
                            'first_name',
                            'last_name',
                            'blacklisted',
                            'blacklisted_at',
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
            ->assertJsonFragment(
                [
                    'uuid' => $referrer1->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrer2->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrer3->uuid,
                ]
            );
    }

    public function testCanGetReferrerDetails()
    {
        $referrer = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'referrer/' . $referrer->uuid
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        'uuid',
                        'email',
                        'name_title',
                        'first_name',
                        'last_name',
                        'blacklisted',
                        'blacklisted_at',
                        'referrer_points',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrer->uuid,
                ]
            );
    }

    public function testSearchReferrersGetAll()
    {
        $referrers = factory(Referrer::class, 10)->create();

        $result = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'referrers/search'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'uuid',
                            'email',
                            'name_title',
                            'first_name',
                            'last_name',
                            'blacklisted',
                            'blacklisted_at',
                        ],
                    ],
                ]
            )
            ->baseResponse
            ->getData()
            ->data;

        // See comment in PartnerTest for why we use Referrer::count() here, rather than
        // count($referrers)
        $this->assertEquals(Referrer::count(), count($result));
    }

    public function testCantAddMemberToExternalGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $referrer = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add/' . $referrer->uuid
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantAddMultipleMembersToExternalGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/add',
            [
                'partner_uuids' => [
                    $referrer1->uuid,
                    $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantRemoveMemberFromExternalGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer = factory(Referrer::class)->create();
        $group->referrers()->attach($referrer);

        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/' . $referrer->uuid
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantRemoveMultipleMembersFromExternalGroup()
    {
        $group = factory(ReferrerGroup::class)->create();
        $referrer1 = factory(Referrer::class)->create();
        $referrer2 = factory(Referrer::class)->create();

        $group->referrers()->attach(
            [
                $referrer1->id,
                $referrer2->id,
            ]
        );

        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'referrers/groups/' . $group->id . '/referrers/remove',
            [
                'referrer_uuids' => [
                    $referrer1->uuid,
                    $referrer2->uuid,
                ],
            ]
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    private function setUpForUserRegistrationTest($overrides = [])
    {
        $name_title = NameTitle::firstOrFail();

        $referrer = factory(Referrer::class)->create(
            [
                'access_password' => Hash::make('HelloWorld'),
            ]
        );

        $default_params = [
            'name' => 'Test Person',
            'email' => 'myname@test.example',
            'password' => 'HelloWorld',
            'referrer_uuid' => $referrer->uuid,
            'access_answer' => 'HelloWorld',
            'name_title_id' => $name_title->id,
        ];

        return(array_merge($default_params, $overrides));
    }

    private function setUpForReferrerUsersTests($overrides = [])
    {
        $referrer = factory(Referrer::class)->create();
        $referrer->referrerUsers()->attach(
            factory(User::class, 10)->create()->each(function ($u) {
                $u->assignRole('referrer user');
            }),
            [
                'approved' => isset($overrides['users_approved']) ? $overrides['users_approved'] : 1,
            ]
        );

        $manager = factory(User::class)->create();
        $manager->assignRole('referrer user');
        $referrer->referrerUsers()->attach(
            $manager,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        return [
            'manager' => $manager,
            'referrer' => $referrer,
        ];
    }

    private function setUpForReferrerAccountActionsTests()
    {
        $referrer = factory(Referrer::class)->create();
        $users = factory(User::class, 4)->create()->each(function ($u) {
            $u->assignRole('referrer user');
        });

        $referrer->referrerUsers()->attach(
            [
                $users->get(0)->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $users->get(1)->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $users->get(2)->id => [
                    'manager' => 0,
                    'approved' => 0,
                ],
                $users->get(3)->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
            ]
        );

        return [
            'referrer' => $referrer,
            'manager1' => $users->get(0),
            'manager2' => $users->get(1),
            'pending_user' => $users->get(2),
            'standard_user' => $users->get(3),
        ];
    }

    private function setUpForRedeemedCouponsTests()
    {
        $referrer = factory(Referrer::class)->create();
        factory(Partner::class)->create();
        factory(Consumer::class)->create();
        factory(Voucher::class)->create();
        factory(VoucherTerms::class)->create();
        factory(VoucherAccessCode::class)->create();
        factory(VoucherUniqueCode::class)->create();
        factory(Coupon::class, 20)->create();
        return [
            'referrer' => $referrer,
        ];
    }
}
