<?php

namespace Tests\Feature\v0;

use App\User;
use App\Coupon;
use App\Partner;
use App\Voucher;
use App\Consumer;
use App\Referrer;
use App\PartnerGroup;
use App\VoucherTerms;
use Tests\Feature\V0Test;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CouponTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('public_web_assets');
    }

    public function testGetCouponDetailsByPartnerUser()
    {
        extract($this->setUpForCouponTests());

        $response = $this->actingAs($user)
        ->json(
            'GET',
            $this->baseurl . "coupon/{$coupon->uuid}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
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
            ]
        );
    }

    public function testCantGetCouponDetailsByWrongPartnerUser()
    {
        extract($this->setUpForCouponTests());
        $partner2 = factory(Partner::class)->create();
        $user2 = factory(User::class)->create();
        $user2->assignRole('partner user');
        $partner2->partnerUsers()->attach(
            $user,
            [
                'approved' => 1,
            ]
        );

        $response = $this->actingAs($user2)
        ->json(
            'GET',
            $this->baseurl . "coupon/{$coupon->uuid}"
        )
        ->assertStatus(403);
    }

    public function testCanCancelCoupon()
    {
        extract($this->setUpForCouponTests());

        $this->assertDatabaseHas(
            'coupons',
            [
                'id' => $coupon->id,
                'uuid' => $coupon->uuid,
                'cancelled' => 0,
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "coupon/{$coupon->uuid}/cancel"
            )
            ->assertStatus(200)
            ->assertJsonFragment(
                [
                    'uuid' => $coupon->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'cancelled' => 1,
                ]
            );

        $this->assertDatabaseHas(
            'coupons',
            [
                'id' => $coupon->id,
                'uuid' => $coupon->uuid,
                'cancelled' => 1,
            ]
        );
    }

    public function testCheckValidityCancelledCoupon()
    {
        extract($this->setupForCouponTests());

        $coupon->cancelled = 1;
        $coupon->barcode = '987654321';
        $coupon->save();

        $this->assertDatabaseHas(
            'coupons',
            [
                'id' => $coupon->id,
                'uuid' => $coupon->uuid,
                'cancelled' => 1,
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(409)
            ->assertSee('Coupon is cancelled');
    }

    public function testCheckValidityCouponNotYetValid()
    {
        extract($this->setupForCouponTests());

        $coupon->valid_from = Carbon::now()->addDays(10);
        $coupon->barcode = '987654321';
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(409)
            ->assertSee('Coupon start date not yet reached');
    }

    public function testCheckValidityCouponExpired()
    {
        extract($this->setupForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->subDays(5);
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(409)
            ->assertSee('Coupon has expired');
    }

    public function testCheckValidityCouponAlreadyRedeemed()
    {
        extract($this->setupForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = Carbon::now()->subDays(7);
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(409)
            ->assertSee('Coupon already redeemed');
    }

    public function testCheckValidityCouponReissued()
    {
        extract($this->setupForCouponTests());

        $new_coupon = factory(Coupon::class)->create();

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->barcode = '987654321';
        $coupon->cancelled_at = null;
        $coupon->reissued_as_coupon_id = $new_coupon->id;
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(409)
            ->assertSee("Coupon has been reissued as " . $new_coupon->uuid);
    }

    public function testCanGetReissuedAsCoupon()
    {
        extract($this->setupForCouponTests());

        $new_coupon = factory(Coupon::class)->create();

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->barcode = '987654321';
        $coupon->cancelled_at = null;
        $coupon->reissued_as_coupon_id = $new_coupon->id;
        $coupon->save();

        $coupon->refresh();

        $this->assertInstanceOf(Coupon::class, $coupon->reissuedAsCoupon);
        $this->assertEquals($coupon->reissuedAsCoupon->id, $new_coupon->id);
    }

    public function testReissuedCouponCanGetOriginal()
    {
        extract($this->setupForCouponTests());

        $new_coupon = factory(Coupon::class)->create();

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->barcode = '987654321';
        $coupon->cancelled_at = null;
        $coupon->reissued_as_coupon_id = $new_coupon->id;
        $coupon->save();

        $coupon->refresh();
        $new_coupon->refresh();

        $this->assertInstanceOf(Coupon::class, $new_coupon->originalCoupon);
        $this->assertEquals($new_coupon->originalCoupon->id, $coupon->id);
    }

    public function testCanGetReissuedCouponDatetime()
    {
        extract($this->setupForCouponTests());

        $new_coupon = factory(Coupon::class)->create();

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->barcode = '987654321';
        $coupon->cancelled_at = null;
        $coupon->reissued_as_coupon_id = $new_coupon->id;
        $coupon->save();

        $coupon->refresh();

        $this->assertEquals($coupon->reissued_datetime, $new_coupon->created_at);
    }

    public function testCheckValidityRestrictPartner()
    {
        extract($this->setUpForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = null;
        $coupon->restrict_partner_id = $partner->id;
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
            )
            ->assertStatus(200)
            ->assertJsonStructure([
                'partner' => [
                    'uuid',
                    'name',
                ],
            ]);
    }

    public function testCheckValidityRestrictPartnerGroups()
    {
        extract($this->setUpForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = null;
        $coupon->restrict_partner_id = null;
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        $groups = factory(PartnerGroup::class, 3)->create();
        $groups[0]->voucherRestrictions()->attach($voucher);
        $groups[1]->voucherRestrictions()->attach($voucher);

        $response = $this->actingAs(User::first())
             ->json(
                 'GET',
                 $this->baseurl . "barcode/{$coupon->barcode}/check_validity"
             )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'partner_groups' => [
                        '*' => [
                            'id',
                            'group_name',
                        ],
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'id' => $groups[0]->id,
                    'group_name' => $groups[0]->group_name,
                ]
            )
            ->assertJsonFragment(
                [
                    'id' => $groups[1]->id,
                    'group_name' => $groups[1]->group_name,
                ]
            )
            ->assertJsonMissing(
                [
                    'id' => $groups[2]->id,
                    'group_name' => $groups[2]->group_name,
                ]
            );
    }

    public function testCanRedeemCoupon()
    {
        extract($this->setUpForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = null;
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/{$partner->uuid}/redeem"
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        'uuid',
                        'status',
                        'barcode',
                        'redeemed_datetime',
                        'redemption_partner_uuid',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $coupon->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'status' => 'Redeemed, not yet sent to Valassis',
                ]
            )
            ->assertJsonFragment(
                [
                    'barcode' => $coupon->barcode,
                ]
            )
            ->assertJsonFragment(
                [
                    'redemption_partner_uuid' => $partner->uuid,
                ]
            );
    }

    public function testCantRedeemCouponWhenRestrictedToDifferentPartner()
    {
        extract($this->setUpForCouponTests());

        $other_partner = factory(Partner::class)->create();

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = null;
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->restrict_partner_id = $partner->id;
        $coupon->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/{$other_partner->uuid}/redeem"
            )
            ->assertStatus(409)
            ->assertSee('Coupon not valid for this specific partner');
    }

    public function testCantRedeemCouponWhenPartnerNotInRestrictedGroups()
    {
        extract($this->setUpForCouponTests());

        $coupon->valid_from = Carbon::now()->subDays(10);
        $coupon->valid_to = Carbon::now()->addDays(5);
        $coupon->redeemed_datetime = null;
        $coupon->cancelled_at = null;
        $coupon->barcode = '987654321';
        $coupon->save();

        /**
         * Voucher needs $group[0], partner is only a member of $group[1]
         */
        $groups = factory(PartnerGroup::class, 2)->create();
        $groups[0]->voucherRestrictions()->attach($voucher);

        $partner->groups()->attach($groups[1]);

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "barcode/{$coupon->barcode}/{$partner->uuid}/redeem"
            )
            ->assertStatus(409)
            ->assertSee("Coupon not valid for this partner's groups");
    }

    private function setUpForCouponTests()
    {
        $partner = factory(Partner::class)->create();
        $user = factory(User::class)->create();
        $user->assignRole('partner user');
        $partner->partnerUsers()->attach(
            $user,
            [
                'approved' => 1,
            ]
        );
        $voucher = factory(Voucher::class)->create();
        factory(VoucherTerms::class)->create();
        factory(VoucherAccessCode::class)->create();
        factory(Consumer::class)->create();
        factory(Referrer::class)->create();
        factory(VoucherUniqueCode::class)->create();
        $coupon = factory(Coupon::class)->create(
            [
                'restrict_partner_id' => $partner->id,
            ]
        );

        return [
            'user' => $user,
            'coupon' => $coupon,
            'partner' => $partner,
            'voucher' => $voucher,
        ];
    }
}
