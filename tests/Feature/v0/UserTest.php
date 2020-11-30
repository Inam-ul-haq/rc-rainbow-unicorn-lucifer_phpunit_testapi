<?php

namespace Tests\Feature\v0;

use DB;
use Hash;
use Faker;
use JWTAuth;
use App\User;
use App\Partner;
use App\NameTitle;
use App\UserApiKey;
use Ramsey\Uuid\Uuid;
use Tests\Feature\V0Test;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function testAdminCanLogInAtRunLevel0()
    {
        $user = User::findOrFail(1);
        $user->password = 'HelloWorld';
        $user->save();

        $this->setRunLevel(0);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => 'webmaster@coastdigital.co.uk',
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'name',
                    'uuid',
                    'email',
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
                'id' => 1,
            ]
        )
        ->assertJsonFragment(
            [
                'password_change_needed' => 1,
            ]
        );
    }

    public function testUserCantLoginAtRunLevel0()
    {
        $user = factory(User::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );
        $user->assignRole('customer care');

        $this->setRunLevel(0);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(503);
    }

    public function testUserCanLoginAtRunLevel1()
    {
        $user = factory(User::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );
        $user->assignRole('admin');

        $this->setRunLevel(1);

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
                    'email',
                    'roles',
                    'expires_in',
                    'token_type',
                    'permissions',
                    'access_token',
                    'password_change_needed',
                ]
            ]
        );
    }

    public function testUserCantLoginAtRunLevel1()
    {
        $user = factory(User::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );
        $user->assignRole('marketing');

        $this->setRunLevel(1);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(503);
    }

    public function testUserCanLoginAtRunLevel2()
    {
        $user = factory(User::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );
        $user->assignRole('marketing');

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
                    'email',
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
                'id' => 1,
            ]
        );
    }

    public function testUserCantLoginWithWrongPassword()
    {
        $user = factory(User::class)->create(
            [
                'password' => 'HelloWorld',
            ]
        );
        $user->assignRole('marketing');

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'ThisIsNotTheCorrectPassword',
            ]
        )
        ->assertStatus(401);
    }

    public function testSendResetPasswordEmailToUser()
    {
        $user = factory(User::class)->create();

        $response = $this->json(
            'GET',
            $this->baseurl . 'user/forgot_password',
            [
                'email' => $user->email,
                'reset_url' => 'http://test.com/test',
            ]
        )
        ->assertStatus(200)
        ->assertJsonFragment(
            [
                'message' => 'Password reset email sent',
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'password_change_needed' => 0,
            ]
        );
    }

    public function testSendResetPasswordEmailToNonExistantUser()
    {
        $response = $this->json(
            'GET',
            $this->baseurl . 'user/forgot_password',
            [
                'email' => 'invalid@something.invalid',
                'reset_url' => 'http://test.com/test',
            ]
        )
        ->assertStatus(422)
        ->assertJsonFragment(
            [
                'message' => 'Failed to send password reset email to this address',
            ]
        );
    }

    public function testGetAllUsersList()
    {
        factory(User::class, 10)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'users'
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
                        'email_verified_at',
                        'password_change_needed',
                    ],
                ],
            ]
        );
    }

    public function testCreateNewUserAccount()
    {
        $faker = Faker\Factory::create();
        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'users',
            [
                'name' => $faker->firstName(),
                'email' => $faker->safeEmail,
                'name_title_id' => NameTitle::firstOrFail()->id,
                'password' => $faker->password(),
            ]
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        )
        ->assertJsonFragment(
            [
                'password_change_needed' => 1,
            ]
        )
        ->getData()
        ->data;

        $this->assertDatabaseHas(
            'users',
            [
                'uuid' => $response->uuid,
                'password_change_needed' => 1,
            ]
        );
    }

    public function testCantCreateUserAccountWithExistingEmailAddress()
    {
        $user = factory(User::class)->create();
        $faker = Faker\Factory::create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'users',
            [
                'name' => $faker->firstName(),
                'email' => $user->email,
                'name_title_id' => NameTitle::firstOrFail()->id,
                'password' => $faker->password(),
            ]
        )
        ->assertStatus(422);
    }

    public function testUpdateUserAccountAsAdmin()
    {
        $faker = Faker\Factory::create();
        $user = factory(User::class)->create(
            [
                'blocked' => 0,
            ]
        );

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 1,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'name' => $new_name,
                'uuid' => $user->uuid,
                'email' => $new_email,
                'blocked' => 1,
            ]
        );
    }

    public function testUpdateUserPasswordAsAdmin()
    {
        $faker = Faker\Factory::create();
        $user = factory(User::class)->create(
            [
                'blocked' => 0,
                'password_change_needed' => 1,
            ]
        );

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs(User::first())
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 1,
                'password' => 'ANewPassword',
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'name' => $new_name,
                'uuid' => $user->uuid,
                'email' => $new_email,
                'blocked' => 1,
                'password_change_needed' => 1,
            ]
        );
    }

    public function testUpdateUserAccountAsPartnerManager()
    {
        $faker = Faker\Factory::create();
        $partner = factory(Partner::class)->create();
        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $partner->partnerUsers()->attach(
            [
                $manager->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $user->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
            ]
        );

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs($manager)
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $new_name,
                'email' => $new_email,
            ]
        );
    }

    public function testUpdateUserAccountAsSelf()
    {
        $faker = Faker\Factory::create();
        $user = factory(User::class)->create();

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs($user)
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $new_name,
                'email' => $new_email,
            ]
        );
    }

    public function testCantUpdateRestrictedFieldsAsPartnerManager()
    {
        $faker = Faker\Factory::create();
        $partner = factory(Partner::class)->create();
        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $user = factory(User::class)->create(
            [
                'blocked' => 0,
            ]
        );
        $user->assignRole('partner user');
        $partner->partnerUsers()->attach(
            [
                $manager->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $user->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
            ]
        );

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs($manager)
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 1,
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 1,
            ]
        );
    }

    public function testCantUpdateRestrictedFieldsAsSelf()
    {
        $faker = Faker\Factory::create();
        $user = factory(User::class)->create(
            [
                'blocked' => 1,
            ]
        );

        $new_name = $faker->firstName() . ' ' . $faker->lastName();
        $new_email = $faker->safeEmail();

        $response = $this->actingAs($user)
        ->json(
            'PATCH',
            $this->baseurl . "users/{$user->uuid}",
            [
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 0,
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $new_name,
                'email' => $new_email,
                'blocked' => 0,
            ]
        );
    }

    public function testRemoveUserAccountAsAdmin()
    {
        $user = factory(User::class)->create();
        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . "users/{$user->uuid}"
        )
        ->assertStatus(204);

        $this->assertDatabaseMissing(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'deleted_at' => null,
            ]
        );
    }

    public function testRemoveUserPartnerAccountAsAdmin()
    {
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $user->userPartners()->attach(
            [
                $partner1->id => [
                    'approved' => 1,
                    'manager' => 0,
                ],
                $partner2->id => [
                    'approved' => 1,
                    'manager' => 1,
                ],
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'DELETE',
            $this->baseurl . "users/{$user->uuid}",
            [
                'partner_id' => $partner1->id,
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'partner_user',
            [
                'user_id' => $user->id,
                'partner_id' => $partner1->id,
            ]
        );

        $this->assertDatabaseHas(
            'partner_user',
            [
                'user_id' => $user->id,
                'partner_id' => $partner2->id,
            ]
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
            ]
        );
    }

    public function testRemoveUserAccountAsPartnerManager()
    {
        $partner = factory(Partner::class)->create();
        $user1 = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user1->assignRole('partner user');
        $user2->assignRole('partner user');

        $partner->partnerUsers()->attach(
            [
                $user1->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $user2->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
            ]
        );

        $response = $this->actingAs($user1)
        ->json(
            'DELETE',
            $this->baseurl . "users/{$user2->uuid}",
            [
                'partner_id' => $partner->id,
            ]
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'partner_user',
            [
                'user_id' => $user2->uuid,
                'partner_id' => $partner->id,
            ]
        );
    }

    public function testCantRemoveUserAccountWithoutPartnerIDAsPartnerManager()
    {
        $partner = factory(Partner::class)->create();
        $user1 = factory(User::class)->create();
        $user2 = factory(User::class)->create();
        $user1->assignRole('partner user');
        $user2->assignRole('partner user');

        $partner->partnerUsers()->attach(
            [
                $user1->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
                $user2->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
            ]
        );

        $response = $this->actingAs($user1)
        ->json(
            'DELETE',
            $this->baseurl . "users/{$user2->uuid}"
        )
        ->assertStatus(400);

        $this->assertDatabaseHas(
            'partner_user',
            [
                'user_id' => $user2->id,
                'partner_id' => $partner->id,
            ]
        );
    }

    public function testCantRemoveCurrentUser()
    {
        $user = factory(User::class)->create();
        $user->givePermissionTo('admin users');

        $response = $this->actingAs($user)
        ->json(
            'DELETE',
            $this->baseurl . "users/{$user->uuid}"
        )->assertStatus(403);

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'uuid' => $user->uuid,
            ]
        );
    }

    public function testCantRemoveAdminAccount()
    {
        $user = factory(User::class)->create();
        $user->givePermissionTo('admin users');

        $admin_user = User::find(1);

        $response = $this->actingAs($user)
        ->json(
            'DELETE',
            $this->baseurl . "users/{$admin_user->uuid}"
        )
        ->assertStatus(403);

        $this->assertDatabaseHas(
            'users',
            [
                'id' => 1,
                'uuid' => $admin_user->uuid,
            ]
        );
    }

    public function testGetUserDetailsAsAdmin()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "users/{$user->uuid}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );
    }

    public function testGetUserDetailsAsPartnerManager()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $partner = factory(Partner::class)->create();
        $partner->partnerUsers()->attach(
            [
                $user->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
                $manager->id => [
                    'manager' => 1,
                    'approved' => 1,
                ],
            ]
        );

        $response = $this->actingAs($manager)
        ->json(
            'GET',
            $this->baseurl . "users/{$user->uuid}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'blocked',
                    'blocked_at',
                    'created_at',
                    'updated_at',
                    'name_title_id',
                    'email_verified_at',
                    'password_change_needed',
                ],
            ]
        );
    }

    public function testCantGetOtherPartnerUserDetailsAsPartnerManager()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $partner1 = factory(Partner::class)->create();
        $partner2 = factory(Partner::class)->create();
        $partner1->partnerUsers()->attach(
            $user,
            [
                'manager' => 0,
                'approved' => 1,
            ]
        );

        $partner2->partnerUsers()->attach(
            $manager,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        $response = $this->actingAs($manager)
        ->json(
            'GET',
            $this->baseurl . "users/{$user->uuid}"
        )
        ->assertStatus(403);
    }

    public function testCantGetNonPartnerUserDetailsAsPartnerManager()
    {
        $manager = factory(User::class)->create();
        $manager->assignRole('partner user');
        $partner = factory(Partner::class)->create();
        $partner->partnerUsers()->attach(
            $manager,
            [
                'manager' => 1,
                'approved' => 1,
            ]
        );

        $first_user = User::find(1);

        $response = $this->actingAs($manager)
        ->json(
            'GET',
            $this->baseurl . "users/{$first_user->uuid}"
        )
        ->assertStatus(403);
    }

    public function testPartnerUserCantGetOtherUserDetails()
    {
        $user1 = factory(User::class)->create();
        $user1->assignRole('partner user');
        $user2= factory(User::class)->create();
        $user2->assignRole('partner user');
        $partner = factory(Partner::class)->create();
        $partner->partnerUsers()->attach(
            [
                $user1->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
                $user2->id => [
                    'manager' => 0,
                    'approved' => 1,
                ],
            ]
        );

        $response = $this->actingAs($user2)
        ->json(
            'GET',
            $this->baseurl . "users/{$user1->uuid}"
        )
        ->assertStatus(403);
    }

    public function testGetUserPermissionsAsAdmin()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "user/{$user->uuid}/permissions"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'name',
                    'guard_name',
                    'created_at',
                    'updated_at',
                    'pivot' => [
                        'role_id',
                        'permission_id',
                    ],
                ],
            ]
        );
    }

    public function testGetUserPermissionsAsSelf()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');

        $response = $this->actingAs($user)
        ->json(
            'GET',
            $this->baseurl . "user/{$user->uuid}/permissions"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                '*' => [
                    'id',
                    'name',
                    'guard_name',
                    'created_at',
                    'updated_at',
                    'pivot' => [
                        'role_id',
                        'permission_id',
                    ],
                ],
            ]
        );
    }

    public function testCantGetOtherUserPermissions()
    {
        $user1 = factory(User::class)->create();
        $user1->assignRole('partner user');
        $user2 = factory(User::class)->create();
        $user2->assignRole('partner user');

        $response = $this->actingAs($user1)
        ->json(
            'GET',
            $this->baseurl . "user/{$user2->uuid}/permissions"
        )
        ->assertStatus(403);
    }

    public function testCanBlockUserAccount()
    {
        $user = factory(User::class)->create();
        $user->assignRole('admin');
        $user->password = 'HelloWorld';
        $user->save();

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
                    'email',
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

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'user/' . $user->uuid . '/block'
        )
        ->assertStatus(200);

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(401);

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'blocked' => 1,
            ]
        );
    }

    public function testCanUnblockUserAccount()
    {
        $user = factory(User::class)->create();
        $user->assignRole('admin');
        $user->password = 'HelloWorld';
        $user->blocked = 1;
        $user->blocked_at = now();
        $user->save();

        $response = $this->json(
            'POST',
            $this->baseurl . 'user/login',
            [
                'email' => $user->email,
                'password' => 'HelloWorld',
            ]
        )
        ->assertStatus(401);

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . 'user/' . $user->uuid . '/unblock'
        )
        ->assertStatus(200);

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
                    'email',
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

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->id,
                'blocked' => 0,
                'blocked_at' => null,
            ]
        );
    }

    public function testCanRefreshToken()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $user->save();

        $this->setRunLevel(2);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'user/refresh_token'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'name',
                    'uuid',
                    'email',
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

    public function testCantRefreshTokenWhenBlocked()
    {
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $user->blocked = 1;
        $user->save();

        $this->setRunLevel(2);

        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'user/refresh_token'
        )
        ->assertStatus(401);
    }

    public function testCantRefreshTokenRunLevel1WithoutPermission()
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
            $this->baseurl . 'user/refresh_token'
        )
        ->assertStatus(503);
    }

    /*
    public function testCanGetUserApiKey()
    {
        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'api_keys',
            [
                'expires_at' => null,
                'source_id' => null,
            ]
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'data' => [
                    'id',
                    'user_id',
                    'source_id',
                    'expires_at',
                    'api_key',
                    'secret',
                ]
            ]
        )
        ->getData()
        ->data;

        $this->assertDatabaseHas(
            'user_api_keys',
            [
                'id' => $response->id,
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $response->api_key,
            ]
        );

        $hashed_secret = DB::table('user_api_keys')->where('api_key', $response->api_key)->pluck('secret')[0];
        $this->assertTrue(Hash::check($response->secret, $hashed_secret));
    }

    public function testCanLoginWithApiKey()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => $secret,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'expires_in',
                    'token_type',
                    'access_token',
                ]
            ]
        );
    }

    public function testCantLoginWithBadApiKey()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => 'this is not valid',
            ]
        )
        ->assertStatus(401);
    }

    public function testCantLoginWithExpiredKey()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => Carbon::now()->subDays(1),
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => 'this is not valid',
            ]
        )
        ->assertStatus(401);
    }

    public function testCanLoginWithUnExpiredKey()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => Carbon::now()->addDays(1),
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => $secret,
            ]
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'expires_in',
                    'token_type',
                    'access_token',
                ]
            ]
        );
    }

    public function testApiKeyCanLoginWithBlockedUser()
    {
        $user = factory(User::class)->create();
        $user->blocked = true;
        $user->blocked_at = Carbon::now();
        $user->save();
        $user->assignRole('admin');
        $this->setApiKeysBlockWithUser('dont_block');

        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => $user->id,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => $secret,
            ]
        )
        ->assertStatus(200);
    }

    public function testApiKeyCantLoginAtRunLevel0()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $this->setRunLevel(0);

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => $secret,
            ]
        )
        ->assertStatus(503);
    }

    public function testApiKeyCantLoginAtRunLevel1()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $this->setRunLevel(1);

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->json(
            'POST',
            $this->baseurl . 'key/authenticate',
            [
                'api_key' => $api_key,
                'password' => $secret,
            ]
        )
        ->assertStatus(503);
    }

    public function testCanRefreshApiToken()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $token = JWTAuth::fromUser($key);

        $response = $this->withHeaders(
            [
                'Authorization' => 'Bearer ' . $token,
            ]
        )
        ->json(
            'GET',
            $this->baseurl . 'key/refresh_token'
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    'expires_in',
                    'token_type',
                    'access_token',
                ]
            ]
        );
    }

    public function testCanRemoveApiToken()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $response = $this->actingAs(User::first())->json(
            'DELETE',
            $this->baseurl . 'api_keys/' . $key->id
        )
        ->assertStatus(200);

        $this->assertDatabaseMissing(
            'user_api_keys',
            [
                'api_key' => $api_key,
                'deleted_at' => null,
            ]
        );
    }

    public function testCanUpdateApiToken()
    {
        $api_key = str_replace('-', '', Uuid::uuid4());
        $secret = str_replace('-', '', Uuid::uuid4());

        $key = UserApiKey::create(
            [
                'user_id' => 1,
                'source_id' => null,
                'expires_at' => null,
                'api_key' => $api_key,
                'secret' => $secret,
            ]
        );

        $source = new \App\ConsumerSource();
        $source->source_name = 'this is the source, the source of all evil';
        $source->save();

        $expires_at = Carbon::now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs(User::first())->json(
            'PATCH',
            $this->baseurl . 'api_keys/' . $key->id,
            [
                'expires_at' => $expires_at,
                'source_id' => $source->id,
            ]
        )->assertStatus(200);

        $this->assertDatabaseHas(
            'user_api_keys',
            [
                'api_key' => $api_key,
                'id' => $key->id,
                'source_id' => $source->id,
                'expires_at' => $expires_at,
            ]
        );
    }
     */
}
