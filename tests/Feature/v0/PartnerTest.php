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
use App\PartnerGroup;
use App\VoucherTerms;
use Ramsey\Uuid\Uuid;
use Tests\Feature\V0Test;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use Illuminate\Support\Str;
use App\Traits\PHPUnitSetup;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PartnerTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testPartnerAcceptLoyalty()
    {
        $partner = factory(Partner::class)->create(
            [
                'accepts_loyalty' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_loyalty' => 0,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/accept_loyalty"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_loyalty' => 1,
            ]
        );
    }

    public function testPartnerRefuseLoyalty()
    {
        $partner = factory(Partner::class)->create(
            [
                'accepts_loyalty' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_loyalty' => 1,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/refuse_loyalty"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_loyalty' => 0,
            ]
        );
    }

    public function testPartnerAcceptVouchers()
    {
        $partner = factory(Partner::class)->create(
            [
                'accepts_vouchers' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_vouchers' => 0,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/accept_vouchers"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_vouchers' => 1,
            ]
        );
    }

    public function testPartnerRefuseVouchers()
    {
        $partner = factory(Partner::class)->create(
            [
                'accepts_vouchers' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_vouchers' => 1,
            ]
        );

        $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/refuse_vouchers"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partners',
            [
                'id' => $partner->id,
                'uuid' => $partner->uuid,
                'accepts_vouchers' => 0,
            ]
        );
    }

    public function testGetPartnerAccessQuestion()
    {
        $partner = factory(Partner::class)->create(
            [
                'access_question' => 'HelloWorld',
            ]
        );

        $response = $this->json(
            'GET',
            $this->baseurl . "partner/{$partner->crm_id}/get_access_question"
        )
        ->assertStatus(200)
        ->assertSeeText('HelloWorld');
    }

    public function testGetInvalidPartnerAccessQuestion()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'partner/0/get_access_question'
        )
        ->assertStatus(404);
    }

    public function testvalidateCorrectPartnerAccessQuestion()
    {
        $partner = factory(Partner::class)->create(
            [
                'access_password' => Hash::make('HelloWorld')
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . "partner/{$partner->crm_id}/check_access_answer",
            [
                'answer' => 'HelloWorld',
            ]
        )
        ->assertStatus(200)
        ->assertJsonFragment(
            [
                'status' => 1,
                'message' => 'Correct answer given',
            ]
        );
    }

    public function testValidateIncorrectPartnerAccessQuestion()
    {
        $partner = factory(Partner::class)->create();

        $response = $this->json(
            'POST',
            $this->baseurl . "partner/{$partner->crm_id}/check_access_answer",
            [
                'answer' => 'TheWrongAnswer',
            ]
        )
        ->assertStatus(200)
        ->assertJsonFragment(
            [
                'status' => 0,
                'message' => 'Incorrect answer given',
            ]
        );
    }

    public function testPartnerUserCanLogIn()
    {
        $user = factory(User::class)->create();
        $user->password = 'HelloWorld';
        $user->save();
        $user->assignRole('partner user');

        $partner = factory(Partner::class)->create();
        $user->userPartners()->attach($partner->id, ['approved' => 1]);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'name',
                    'uuid',
                    'roles',
                    'expires_in',
                    'token_type',
                    'permissions',
                    'access_token',
                    'password_change_needed',
                ]
            ]
        )
        ->assertJsonFragment(
            [
                'uuid' => $user->uuid,
            ]
        );
    }

    public function testPartnerUserCantLogInWithoutApprovedPartner()
    {
        $user = factory(User::class)->create();
        $user->password = 'HelloWorld';
        $user->save();
        $user->assignRole('partner user');

        $partner = factory(Partner::class)->create();
        $user->userPartners()->attach($partner->id, ['approved' => 0]);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(401);
    }

    public function testPartnerUserCantLoginWithoutPartner()
    {
        $user = factory(User::class)->create();
        $user->password = 'HelloWorld';
        $user->save();
        $user->assignRole('partner user');

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(401);
    }

    public function testUserCanRegisterWithPartner()
    {
        $params = $this->setUpForUserRegistrationTest();

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/register',
            [
                'name' => $params['name'],
                'email' => $params['email'],
                'password' => $params['password'],
                'partner_id' => $params['partner_crmid'],
                'access_answer'=> $params['access_answer'],
                'name_title_id' => $params['name_title_id'],
            ]
        )
        ->assertStatus(201)
        ->assertJsonFragment(
            [
                'name' => $params['name'],
                'email' => $params['email'],
            ]
        );
    }

    public function testUserCantRegisterWithInvalidAnswer()
    {
        $params = $this->setUpForUserRegistrationTest();

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/register',
            [
                'name' => $params['name'],
                'email' => $params['email'],
                'password' => $params['password'],
                'partner_id' => $params['partner_crmid'],
                'access_answer'=> 'An Invalid Answer',
                'name_title_id' => $params['name_title_id'],
            ]
        )
        ->assertStatus(422)
        ->assertJsonFragment(
            [
                'message' => 'The access password is incorrect.',
            ]
        );
    }

    public function testUserCantRegisterWithExistingEmail()
    {
        $user = factory(User::class)->create(
            [
                'email' => 'hello@example.test',
            ]
        );

        $params = $this->setUpForUserRegistrationTest(
            [
                'email' => 'hello@example.test',
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/register',
            [
                'name' => $params['name'],
                'email' => $params['email'],
                'password' => $params['password'],
                'partner_id' => $params['partner_crmid'],
                'access_answer'=> $params['access_answer'],
                'name_title_id' => $params['name_title_id'],
            ]
        )
        ->assertStatus(422);
    }

    public function testUserCantRegisterWithInvalidPartner()
    {
        $params = $this->setUpForUserRegistrationTest();

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/register',
            [
                'name' => $params['name'],
                'email' => $params['email'],
                'password' => $params['password'],
                'partner_id' => '121212',
                'access_answer'=> $params['access_answer'],
                'name_title_id' => $params['name_title_id'],
            ]
        )
        ->assertStatus(422);
    }

    public function testGetListOfUserAccountsForPartnerByManager()
    {
        $params = $this->setUpForPartnerUsersTests();

        $response = $this->actingAs($params['manager'])
        ->json(
            'GET',
            $this->baseurl . "partner/{$params['partner']->uuid}/partner_accounts"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'email',
                        'blocked',
                        'blocked_at',
                        'created_at',
                        'name_title' => [
                            'id',
                            'title',
                        ],
                        'updated_at',
                        'name_title_id',
                    ],
                ]
            ]
        );
    }

    public function testGetListOfPendingAccountsForPartnerByManager()
    {
        $params = $this->setUpForPartnerUsersTests(
            [
                'users_approved' => 0,
            ]
        );

        $response = $this->actingAs($params['manager'])
        ->json(
            'GET',
            $this->baseurl . "partner/{$params['partner']->uuid}/pending_accounts"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'email',
                        'blocked',
                        'blocked_at',
                        'created_at',
                        'name_title' => [
                            'id',
                            'title',
                        ],
                        'updated_at',
                        'name_title_id',
                    ],
                ]
            ]
        );
    }

    public function testCantGetListOfUserAccountsForPartnerByWrongManager()
    {
        $params = $this->setUpForPartnerUsersTests();

        $partner2 = factory(Partner::class)->create();
        $manager2 = factory(User::class)->create();
        $partner2->partnerUsers()->attach(
            $manager2,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        $response = $this->actingAs($manager2)
        ->json(
            'GET',
            $this->baseurl . "partner/{$params['partner']->uuid}/partner_accounts"
        )
        ->assertStatus(403);
    }

    public function testCantGetListOfPendingAccountsByWrongManager()
    {
        $params = $this->setUpForPartnerUsersTests(
            [
                'users_approved' => 0,
            ]
        );

        $partner2 = factory(Partner::class)->create();
        $manager2 = factory(User::class)->create();
        $partner2->partnerUsers()->attach(
            $manager2,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        $response = $this->actingAs($manager2)
        ->json(
            'GET',
            $this->baseurl . "partner/{$params['partner']->uuid}/pending_accounts"
        )
        ->assertStatus(403);
    }

    public function testRejectAPartnerAccountApplication()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "user/{$pending_user->uuid}/{$partner->uuid}/reject_partner_account_application",
            [
                'reject_message' => 'A Test Rejection Message',
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $pending_user->id,
            ]
        );
    }

    public function testCantRejectApprovedPartnerAccount()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "user/{$standard_user->uuid}/{$partner->uuid}/reject_partner_account_application",
            [
                'reject_message' => 'A Test Rejection Message',
            ]
        )
        ->assertStatus(401);
    }

    public function testApproveAPartnerAccountApplication()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "user/{$pending_user->uuid}/{$partner->uuid}/approve_partner_account_application",
            [
                'accept_message' => 'A Test Accept Message',
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $pending_user->id,
                'approved' => 1,
            ]
        );
    }

    public function testCantApproveANonPendingPartnerAccountApplication()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "user/{$standard_user->uuid}/{$partner->uuid}/approve_partner_account_application",
            [
                'accept_message' => 'A Test Accept Message',
            ]
        )
        ->assertStatus(401);
    }

    public function testPromotePartnerAccountToManager()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$standard_user->uuid}/{$partner->uuid}/make_manager"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $standard_user->id,
                'approved' => 1,
                'manager' => 1,
            ]
        );
    }

    public function testCantPromoteExistingManager()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$manager2->uuid}/{$partner->uuid}/make_manager"
        )
        ->assertStatus(409);
    }

    public function testCantPromoteNoPartnerUserRole()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $new_user = factory(User::class)->create();

        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$new_user->uuid}/{$partner->uuid}/make_manager"
        )
        ->assertStatus(401);
    }

    public function testCantPromoteNonPartnerAccount()
    {
        extract($this->setUpForPartnerAccountActionsTests());

        $new_user = factory(User::class)->create();
        $new_user->assignRole('partner user');

        $new_partner = factory(Partner::class)->create();
        $new_partner->partnerUsers()->attach(
            $new_user,
            [
                'approved' => 1,
                'manager' => 0,
            ]
        );

        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$new_user->uuid}/{$partner->uuid}/make_manager"
        )
        ->assertStatus(401);
    }

    public function testDemotePartnerManagerAccount()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$manager2->uuid}/{$partner->uuid}/remove_manager"
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $manager2->id,
                'approved' => 1,
                'manager' => 0,
            ]
        );
    }

    public function testCantDemoteLastManagerAccount()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$manager2->uuid}/{$partner->uuid}/remove_manager"
        )
        ->assertStatus(200);

        $response = $this->actingAs($manager1)
        ->json(
            'GET',
            $this->baseurl . "user/{$manager1->uuid}/{$partner->uuid}/remove_manager"
        )
        ->assertStatus(409);
    }

    public function testRemoveAccountFromPartner()
    {
        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs($manager1)
        ->json(
            'DELETE',
            $this->baseurl . "user/{$standard_user->uuid}/{$partner->uuid}"
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $standard_user->id,
            ]
        );
    }

    public function testCantRemoveLastAccountFromPartner()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');

        $partner = factory(Partner::class)->create();
        $partner->partnerUsers()->attach(
            $user,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        extract($this->setUpForPartnerAccountActionsTests());
        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . "user/{$user->uuid}/{$partner->uuid}"
        )
        ->assertStatus(409);
    }

    public function testCanAddExistingUserToPartner()
    {
        extract($this->setUpForPartnerAccountActionsTests());

        // Need another user assigned to a different partner for this test
        $user = factory(User::class)->create();
        $user->assignRole('partner user');

        $other_partner = factory(Partner::class)->create();
        $other_partner->partnerUsers()->attach(
            $user,
            [
                'approved' => 1,
            ]
        );

        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "partner/{$partner->uuid}/add/user",
            [
                'email' => $user->email,
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partner_user',
            [
                'partner_id' => $partner->id,
                'user_id' => $user->id,
                'approved' => 1,
                'manager' => 0,
            ]
        );
    }

    public function testCantAddNonExistantPartnerToUser()
    {
        extract($this->setUpForPartnerAccountActionsTests());

        $response = $this->actingAs($manager1)
        ->json(
            'POST',
            $this->baseurl . "partner/{$partner->uuid}/add/user",
            [
                'email' => 'nonexistant@test.com',
            ]
        )
        ->assertStatus(422);
    }


    public function testGetListOfAllPartnersIdsWithNames()
    {
        factory(Partner::class, 10)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/get_ids'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'uuid',
                    'crm_id',
                    'public_name',
                    'type',
                    'subtype',
                ],
            ]
        );
    }

    public function testGetUsersPartnerAccounts()
    {
        $user = factory(User::class)->create();

        $partners = factory(Partner::class, 5)->create()->each(function ($p) use ($user) {
            $p->partnerUsers()->attach(
                $user,
                [
                    'approved' => 1,
                    'manager' => 1,
                ]
            );
        });

        $response = $this->actingAs($user)
        ->json(
            'GET',
            $this->baseurl . "user/{$user->uuid}/partners"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'uuid',
                    'public_name',
                    'manager',
                ],
            ]
        );
    }

    public function testGetPartnerRedeemedCouponsList()
    {
        extract($this->setUpForRedeemedCouponsTests());

        $response = $this->actingAs($manager_user)
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/redemptions"
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

    public function testGetPartnerRedeemedCouponsCSVExport()
    {
        extract($this->setUpForRedeemedCouponsTests());

        $response = $this->actingAs($manager_user)
        ->json(
            'GET',
            $this->baseurl . "partner/{$partner->uuid}/redemptions/csv"
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

        $job = $this->actingAs($manager_user)
        ->get($this->baseurl . "partner/{$partner->uuid}/redemptions/csv")
        ->decodeResponseJson();

        $response = $this->actingAs($manager_user)
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

    public function testCanAddPartnerGroupWithoutReference()
    {
        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups',
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
            'partner_groups',
            [
                'id' => $response->id,
                'group_ref' => Str::kebab('My Group Name'),
                'group_name' => 'My Group Name',
            ]
        );
    }

    public function testCanAddPartnerGroupWithReference()
    {
        $response = $this->actingAs(User::first())
             ->json(
                 'POST',
                 $this->baseurl . 'partners/groups',
                 [
                     'name' => 'My Group Name',
                     'ref' => 'some_ref_or_other',
                 ]
             )
             ->assertStatus(201)
             ->assertJsonStructure(
                 [
                     'data' => [
                         'id',
                         'ref',
                         'name',
                     ],
                 ]
             )
             ->getData()
             ->data;

        $this->assertDatabaseHas(
            'partner_groups',
            [
                'id' => $response->id,
                'group_ref' => 'some_ref_or_other',
                'group_name' => 'My Group Name',
            ]
        );
    }

    public function testCantAddPartnerGroupWithExistingName()
    {
        $group = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups',
            [
                'name' => $group->name,
            ]
        )
        ->assertStatus(422);
    }

    public function testCanUpdatePartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . 'partners/groups/' . $group->id,
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
            'partner_groups',
            [
                'id' => $group->id,
                'group_name' => 'My New Group Name',
            ]
        );
    }

    public function testCantUpdatePartnerGroupNameToExistingValue()
    {
        $group1 = factory(PartnerGroup::class)->create();
        $group2 = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . 'partners/groups/' . $group1->id,
            [
                'name' => $group2->group_name,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'partner_groups',
            [
                'id' => $group1->id,
                'group_name' => $group1->group_name,
            ]
        );

        $this->assertDatabaseHas(
            'partner_groups',
            [
                'id' => $group2->id,
                'group_name' => $group2->group_name,
            ]
        );
    }

    public function testCanGetPartnerGroupDetails()
    {
        $group = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups/' . $group->id
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

    public function testCanGetPartnerGroupList()
    {
        $group1 = factory(PartnerGroup::class)->create();
        $group2 = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups'
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

    public function testCanRemovePartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'partners/groups/' . $group->id
        )
        ->assertStatus(204);

        $this->assertDatabaseMissing(
            'partner_groups',
            [
                'id' => $group->id,
                'group_name' => $group->name
            ]
        );
    }

    public function testCanAddPartnerToPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add/' . $partner->uuid
        )
        ->assertStatus(200);

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner->id,
                'partner_group_id' => $group->id,
            ]
        );
    }

    public function testCantAddExistingMemberToPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner = factory(Partner::class)->create();
        $group->partners()->attach($partner);

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add/' . $partner->uuid
        )
        ->assertStatus(422);
    }

    public function testCanAddMultiplePartnersToPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                        $partner1->uuid,
                        $partner2->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->added_uuids
        );

        $this->assertContains(
            $partner2->uuid->toString(),
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

    public function testCanAddExistingMembersToPartnerGroupWithoutAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach($partner1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                        $partner1->uuid,
                        $partner2->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->existing_uuids
        );

        $this->assertContains(
            $partner2->uuid->toString(),
            $content->added_uuids
        );

        $this->assertCount(
            0,
            $content->notfound_uuids
        );
    }

    public function testCantAddExistingMembersToPartnerGroupWithAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach($partner1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                        $partner1->uuid,
                        $partner2->uuid,
                ],
                'abortOnPartnerAlreadyMember' => 1,
            ]
        )
        ->assertStatus(422);
    }


    public function testCanAddNonExistantPartnersToPartnerGroupWithoutIgnoreNotFoundFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach($partner1);

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                        $partner1->uuid,
                        $partner2->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->existing_uuids
        );

        $this->assertContains(
            $partner2->uuid->toString(),
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

    public function testCantAddExistantMultiplePartnersToPartnerGroupWithNotFoundAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                        $partner->uuid,
                        'an-invalid-uuid',
                ],
                'abortOnPartnerNotFound' => 1,
            ]
        )
        ->assertStatus(422);
    }

    public function testCanRemovePartnerFromPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach($partner1);
        $group->partners()->attach($partner2);

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/' . $partner2->uuid
        )
        ->assertStatus(204);

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );
    }

    public function testCantRemoveNonMemberFromPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach($partner1);

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/' . $partner2->uuid
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );
    }

    public function testCanRemoveMultiplePartnersFromPartnerGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
                $partner3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    $partner2->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner3->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $partner2->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertNotContains(
            $partner3->uuid->toString(),
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

    public function testCanRemoveNonExistantMultiplePartnersFromPartnerGroupWithoutAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
                $partner3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    'a-non-existant-uuid',
                    $partner3->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner3->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $partner3->uuid->toString(),
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

    public function testCantRemoveNonExistantMultiplePartnersFromPartnerGroupWithAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
                $partner3->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    'a-non-existant-uuid',
                    $partner3->uuid,
                ],
                'abortOnPartnerNotFound' => 1,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner3->id,
                'partner_group_id' => $group->id,
            ]
        );
    }

    public function testCanRemoveNonMembersFromPartnerGroupsWithoutAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    $partner3->uuid,
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
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseMissing(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $content = json_decode($response);

        $this->assertContains(
            $partner1->uuid->toString(),
            $content->removed_uuids
        );

        $this->assertContains(
            $partner3->uuid->toString(),
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

    public function testCantRemoveNonMembersFromPartnerGroupsWithAbortFlagSet()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    $partner3->uuid,
                ],
                'abortOnPartnerNotMember' => 1,
            ]
        )
        ->assertStatus(422);

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner1->id,
                'partner_group_id' => $group->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_group_members',
            [
                'partner_id' => $partner2->id,
                'partner_group_id' => $group->id,
            ]
        );
    }

    public function testCantAddMemberToExternalGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $partner = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add/' . $partner->uuid
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantAddMultipleMembersToExternalGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/add',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    $partner2->uuid,
                ],
            ]
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantRemoveMemberFromExternalGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner = factory(Partner::class)->create();
        $group->partners()->attach($partner);

        $group->forceFill(
            [
                'managed_remotely' => true,
            ]
        );
        $group->save();

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/' . $partner->uuid
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCantRemoveMultipleMembersFromExternalGroup()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
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
            $this->baseurl . 'partners/groups/' . $group->id . '/partners/remove',
            [
                'partner_uuids' => [
                    $partner1->uuid,
                    $partner2->uuid,
                ],
            ]
        )
        ->assertStatus(422)
        ->assertSee('Group membership is managed remotely');
    }

    public function testCanGetPartnerGroupMembersList()
    {
        $group = factory(PartnerGroup::class)->create();
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $group->partners()->attach(
            [
                $partner1->id,
                $partner2->id,
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'partners/groups/' . $group->id . '/members'
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
                'uuid' => $partner1->uuid,
            ]
        )
        ->assertJsonFragment(
            [
                'uuid' => $partner2->uuid,
            ]
        );
    }

    public function testCanGetListOfPartners()
    {
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner3 = factory(Partner::class)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'partners'
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
                    'uuid' => $partner3->uuid,
                ]
            );
    }

    public function testSearchPartnersGetAll()
    {
        $partners = factory(Partner::class, 10)->create();

        $result = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'partners/search'
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
            ->baseResponse
            ->getData()
            ->data;

        // Have to use Partner::count() here, rather than count($partners), as one
        // or more records might already be in the database (so get returned in the result)
        // despite the use of DatabaseTransactions.
        // That's because there's an implicit commit in MySQL when starting a new transaction,
        // and since some of the functions start their own transaction, any records added by
        // phpunit prior to that then get committed.
        //
        $this->assertEquals(Partner::count(), count($result));
    }

    public function testCanGetPartnerDetails()
    {
        $partner = factory(Partner::class)->create();

        $result = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'partner/' . $partner->uuid
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
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
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partner->uuid,
                ]
            );
    }

    public function testCanGetPartnersByDistance()
    {
        $partners = factory(Partner::class, 3)->create();

        $partners[0]->location_point = new Point(64.670282, -5.116359, 4326); // 1462154
        $partners[0]->accepts_vouchers = true;
        $partners[0]->save();
        $partners[0]->fresh();

        $partners[1]->location_point = new Point(36.248414, 2.934903, 4326); // 1746811
        $partners[1]->accepts_vouchers = true;
        $partners[1]->save();
        $partners[1]->fresh();

        $partners[2]->location_point = new Point(40.838848, 20.59164, 4326); // 1937702
        $partners[2]->accepts_vouchers = true;
        $partners[2]->save();
        $partners[2]->fresh();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'partners/distance',
                [
                    'lat' => 51.89017,
                    'long' => 0.88311,
                    'radius' => 10000000000,
                ]
            );

        $response
            ->assertStatus(200)
            ->assertExactJson([
                [
                    'public_name' => $partners[0]->public_name,
                    'uuid' => $partners[0]->uuid,
                    'crm_id' => strval($partners[0]->crm_id),
                    'type' => $partners[0]->type,
                    'accepts_loyalty' => $partners[0]->accepts_loyalty ? 1 : 0,
                    'public_street_line1' => $partners[0]->public_street_line1,
                    'public_street_line2' => $partners[0]->public_street_line2,
                    'public_street_line3' => $partners[0]->public_street_line3,
                    'public_town' => $partners[0]->public_town,
                    'public_county' => $partners[0]->public_county,
                    'public_postcode' => $partners[0]->public_postcode,
                    'contact_telephone' => $partners[0]->contact_telephone,
                    'latitude' => strval(64.670282),
                    'longitude' => strval(-5.116359),
                    'distance' => 1462154,
                    'weight_management_centre' => false,
                ],
                [
                    'public_name' => $partners[1]->public_name,
                    'uuid' => $partners[1]->uuid,
                    'crm_id' => strval($partners[1]->crm_id),
                    'type' => $partners[1]->type,
                    'accepts_loyalty' => $partners[1]->accepts_loyalty ? 1 : 0,
                    'public_street_line1' => $partners[1]->public_street_line1,
                    'public_street_line2' => $partners[1]->public_street_line2,
                    'public_street_line3' => $partners[1]->public_street_line3,
                    'public_town' => $partners[1]->public_town,
                    'public_county' => $partners[1]->public_county,
                    'public_postcode' => $partners[1]->public_postcode,
                    'contact_telephone' => $partners[1]->contact_telephone,
                    'latitude' => strval(36.248414),
                    'longitude' => strval(2.934903),
                    'distance' => 1746811,
                    'weight_management_centre' => false,
                ],
                [
                    'public_name' => $partners[2]->public_name,
                    'uuid' => $partners[2]->uuid,
                    'crm_id' => strval($partners[2]->crm_id),
                    'type' => $partners[2]->type,
                    'accepts_loyalty' => $partners[2]->accepts_loyalty ? 1 : 0,
                    'public_street_line1' => $partners[2]->public_street_line1,
                    'public_street_line2' => $partners[2]->public_street_line2,
                    'public_street_line3' => $partners[2]->public_street_line3,
                    'public_town' => $partners[2]->public_town,
                    'public_county' => $partners[2]->public_county,
                    'public_postcode' => $partners[2]->public_postcode,
                    'contact_telephone' => $partners[2]->contact_telephone,
                    'latitude' => strval(40.838848),
                    'longitude' => strval(20.59164),
                    'distance' => 1937702,
                    'weight_management_centre' => false,
                ],
            ]);
    }

    private function setUpForUserRegistrationTest($overrides = [])
    {
        $name_title = NameTitle::firstOrFail();

        $partner = factory(Partner::class)->create(
            [
                'access_password' => Hash::make('HelloWorld'),
            ]
        );

        $default_params = [
            'name' => 'Test Person',
            'email' => 'myname@test.example',
            'password' => 'HelloWorld',
            'partner_uuid' => $partner->uuid,
            'partner_crmid' => $partner->crm_id,
            'access_answer' => 'HelloWorld',
            'name_title_id' => $name_title->id,
        ];

        return(array_merge($default_params, $overrides));
    }

    private function setUpForPartnerUsersTests($overrides = [])
    {
        $partner = factory(Partner::class)->create();
        $partner->partnerUsers()->attach(
            factory(User::class, 10)->create()->each(function ($u) {
                $u->assignRole('partner user');
            }),
            [
                'approved' => isset($overrides['users_approved']) ? $overrides['users_approved'] : 1,
            ]
        );

        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $partner->partnerUsers()->attach(
            $manager,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        return [
            'manager' => $manager,
            'partner' => $partner,
        ];
    }

    private function setUpForPartnerAccountActionsTests()
    {
        $partner = factory(Partner::class)->create();
        $users = factory(User::class, 4)->create()->each(function ($u) {
            $u->assignRole('partner user');
        });

        $partner->partnerUsers()->attach(
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
            'partner' => $partner,
            'manager1' => $users->get(0),
            'manager2' => $users->get(1),
            'pending_user' => $users->get(2),
            'standard_user' => $users->get(3),
        ];
    }

    private function setUpForRedeemedCouponsTests()
    {
        $partner = factory(Partner::class)->create();
        $standard_user = factory(User::class)->create();
        $manager_user = factory(User::class)->create();
        $partner->partnerUsers()->attach(
            [
                $standard_user->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
                $manager_user->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
            ]
        );
        factory(Referrer::class)->create();
        factory(Consumer::class)->create();
        factory(Voucher::class)->create();
        factory(VoucherTerms::class)->create();
        factory(VoucherAccessCode::class)->create();
        factory(VoucherUniqueCode::class)->create();
        factory(Coupon::class, 20)->create();
        return [
            'partner' => $partner,
            'manager_user' => $manager_user,
            'standard_user' => $standard_user,
        ];
    }
}
