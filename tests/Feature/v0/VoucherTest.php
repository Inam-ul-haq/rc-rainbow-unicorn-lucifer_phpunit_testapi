<?php

namespace Tests\Feature\v0;

use DB;
use Faker;
use App\Pet;
use App\User;
use App\Coupon;
use App\Partner;
use App\Voucher;
use App\Consumer;
use App\Referrer;
use App\PartnerGroup;
use App\VoucherTerms;
use App\ReferrerGroup;
use App\ReferrerPoints;
use Tests\Feature\V0Test;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use App\Mail\VoucherCoupon;
use App\Traits\PHPUnitSetup;
use Illuminate\Support\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use App\Exports\VouchersGenerateUniqueCodesExport;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class VoucherTest extends V0Test
{
    use PHPUnitSetup;
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('public_web_assets');
    }

    public function testCanCreateNewVoucher()
    {
        $faker = Faker\Factory::create();

        $url = strtolower($faker->word);
        $name = $faker->words(4, true);
        $terms = $faker->paragraphs(2, true);
        $value_gbp = '1000';
        $value_eur = '1116';
        $published = 1;
        $public_name = $faker->words(6, true);
        $subscribe_from_date = '2019-04-01';

        $partner_group = factory(PartnerGroup::class)->create();
        $referrer_group = factory(ReferrerGroup::class)->create();

        Storage::fake('local');
        $file_path = '/tmp/fakefile.txt';
        file_put_contents($file_path, "AABC0000001\nAABC0000002\nAABC0000003");

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . 'voucher',
            [
                'url' => $url,
                'name' => $name,
                'terms' => $terms,
                'value_gbp' => $value_gbp,
                'value_eur' => $value_eur,
                'published' => $published,
                'public_name' => $public_name,
                'subscribe_from_date' => $subscribe_from_date,
                'unique_code_required' => 0,
                'unique_codes_file' => new UploadedFile($file_path, 'test.txt', null, null, null, true),
                'partner_group_ids' => [$partner_group->id],
                'referrer_group_ids' => [$referrer_group->id],
            ]
        )
        ->assertStatus(201)
        ->getData()
        ->data;

        $this->assertDatabaseHas(
            'vouchers',
            [
                'uuid' => $response->uuid,
                'url' => $url,
                'name' => $name,
                'value_gbp' => $value_gbp,
                'value_eur' => $value_eur,
                'published' => $published,
                'public_name' => $public_name,
                'subscribe_from_date' => $subscribe_from_date,
            ]
        );

        $this->assertDatabaseHas(
            'referrer_group_voucher_restriction',
            [
            'voucher_id' => Voucher::where('uuid', '=', $response->uuid)->first()->id,
                'referrer_group_id' => $referrer_group->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_partner_group_restrictions',
            [
                'voucher_id' => Voucher::where('uuid', '=', $response->uuid)->first()->id,
                'partner_group_id' => $partner_group->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_terms',
            [
                'voucher_id' => Voucher::where('uuid', '=', $response->uuid)->first()->id,
                'voucher_terms' => $terms,
            ]
        );

        for ($i = 1; $i <= 3; $i++) {
            $this->assertDatabaseHas(
                'voucher_unique_codes',
                [
                    'code' => "AABC000000{$i}",
                    'voucher_id' => Voucher::where('uuid', '=', $response->uuid)->first()->id,
                ]
            );
        }
    }

    public function testCanGenerateUniqueVoucherCodes()
    {
        $voucher = factory(Voucher::class)->create();

        $export = new VouchersGenerateUniqueCodesExport($voucher, 'AA', 3, 10, '0123456789');

        $export->generateCodes();

        for ($i = 0; $i < 10; $i++) {
            $this->assertDatabaseHas(
                'voucher_unique_codes',
                [
                    'code' => "AA{$i}",
                    'voucher_id' => $voucher->id,
                ]
            );
        }
    }

    public function testCanRequestGenerateUniqueVoucherCodes()
    {
        $voucher = factory(Voucher::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "voucher/{$voucher->uuid}/generate_unique_codes",
            [
                'prefix' => 'AA',
                'length' => 3,
                'quantity' => 10,
                'charset' => '0123456789',
            ]
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

    public function testCanRequestGenerateUniqueVoucherCodesUsingVoucherPrefix()
    {
        $voucher = factory(Voucher::class)->create(
            [
                'unique_code_prefix' => 'JD',
            ]
        );

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "voucher/{$voucher->uuid}/generate_unique_codes",
            [
                'length' => 3,
                'quantity' => 10,
                'charset' => '0123456789',
            ]
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

    public function testCanUploadUniqueVoucherCodes()
    {
        $voucher = factory(Voucher::class)->create();

        Storage::fake('local');
        $file_path = '/tmp/fakefile.txt';
        file_put_contents($file_path, "AABC0000001\nAABC0000002\nAABC0000003");

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "voucher/{$voucher->uuid}/upload_unique_codes",
            [
                'unique_codes_upload_skip_existing' => 1,
                'unique_codes_file' => new UploadedFile($file_path, 'test.txt', null, null, null, true),
            ]
        )
        ->assertStatus(200);
    }

    public function testCantUploadExistingUniqueVoucherCodes()
    {
        $voucher = factory(Voucher::class)->create();
        factory(VoucherUniqueCode::class)->create(
            [
                'voucher_id' => $voucher->id,
                'code' => 'AABC0000001',
            ]
        );

        Storage::fake('local');
        $file_path = '/tmp/fakefile.txt';
        file_put_contents($file_path, "AABC0000001\nAABC0000002\nAABC0000003");

        $response = $this->actingAs(User::first())
        ->json(
            'POST',
            $this->baseurl . "voucher/{$voucher->uuid}/upload_unique_codes",
            [
                'unique_codes_upload_skip_existing' => 0,
                'unique_codes_file' => new UploadedFile($file_path, 'test.txt', null, null, null, true),
            ]
        )
        ->assertStatus(422);
    }

    public function testCanCloneVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $terms = factory(VoucherTerms::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/{$voucher->uuid}/clone"
        )
        ->assertStatus(201)
        ->assertJsonStructure(
            [
                'data' => [
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
                    'limit_to_instant_redemption_partner',
                    'instant_redemption',
                    'unique_code_label',
                    'unique_code_placeholder',
                    'current_terms' => [
                        'id',
                        'voucher_uuid',
                        'voucher_terms',
                        'used_from',
                        'used_until',
                    ],
                ],
            ]
        )
        ->getData()
        ->data;

        $this->assertDatabaseHas(
            'vouchers',
            [
                'uuid' => $response->uuid,
                'name' => "(Copy of) {$voucher->name}",
                'value_gbp' => $voucher->value_gbp,
                'value_eur' => $voucher->value_eur,
                'published' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_terms',
            [
                'voucher_id' => Voucher::where('uuid', '=', $response->uuid)->first()->id,
                'used_until' => null,
                'voucher_terms' => $terms->voucher_terms,
            ]
        );
    }


    public function testCanGetVoucherDetails()
    {
        $voucher = factory(Voucher::class)->create();
        factory(VoucherTerms::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/{$voucher->uuid}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
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
                    'limit_to_instant_redemption_partner',
                    'instant_redemption',
                    'unique_code_label',
                    'unique_code_placeholder',
                    'current_terms' => [
                        'id',
                        'voucher_uuid',
                        'voucher_terms',
                        'used_from',
                        'used_until',
                    ],
                ],
            ]
        );
    }

    public function testCanGetVoucherDetailsByReference()
    {
        $voucher = factory(Voucher::class)->create();
        factory(VoucherTerms::class)->create();

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/reference/{$voucher->url}"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
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
                    'limit_to_instant_redemption_partner',
                    'instant_redemption',
                    'unique_code_label',
                    'unique_code_placeholder',
                    'current_terms' => [
                        'id',
                        'voucher_uuid',
                        'voucher_terms',
                        'used_from',
                        'used_until',
                    ],
                ],
            ]
        );
    }

    public function testCanGetPerVoucherReferrerPointsList()
    {
        extract($this->setUpForVoucherCouponTests());

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/{$voucher->uuid}/get_referrer_points_list"
        )
        ->assertStatus(200)
        ->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'transaction_type',
                        'transaction_points',
                        'voucher_uuid',
                        'consumer' => [
                            'uuid',
                            'name',
                            'email',
                        ],
                        'referrer' => [
                            'uuid',
                            'name',
                            'email',
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
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]
        );
    }

    public function testCanRequestPerVoucherReferrerPointsCsv()
    {
        extract($this->setUpForVoucherCouponTests());

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/{$voucher->uuid}/get_referrer_points_list/csv"
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

    public function testCanRequestAllVouchersReferrerPointsCsv()
    {
        extract($this->setUpForVoucherCouponTests());

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/get_referrer_points_list/csv"
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

    public function testCanGetVoucherPerformanceFigures()
    {
        extract($this->setUpForVoucherPerformanceTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/performance'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'expired',
                    'reissued',
                    'cancelled',
                    'subscriptions',
                    'redemptions_count',
                    'redemptions_percent',
                ]
            );
    }

    public function testCanGetVoucherPerformanceFiguresWithDays()
    {
        extract($this->setUpForVoucherPerformanceTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl .  'voucher/' .  $voucher->uuid .  '/performance',
                [
                    'days' => 30,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'expired',
                    'reissued',
                    'cancelled',
                    'subscriptions',
                    'redemptions_count',
                    'redemptions_percent',
                ]
            )
            ->assertJsonFragment(
                [
                    'expired' => 3, // 3 expired coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'reissued' => 3, // 3 expired coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'cancelled' => 3, // 3 cancelled coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'subscriptions' => 3, // 3 subscribed coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'redemptions_count' => 3, // 3 redeemed coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'redemptions_percent' => 100, // 100 percent redeemed coupons
                ]
            );
    }

    public function testCanGetVoucherPerformanceFiguresWithRange()
    {
        extract($this->setUpForVoucherPerformanceTests());

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl .  'voucher/' .  $voucher->uuid .  '/performance',
                [
                    'start' => Carbon::now()->subDays(10)->format('Y-m-d'),
                    'end' => Carbon::now()->format('Y-m-d'),
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'expired',
                    'reissued',
                    'cancelled',
                    'subscriptions',
                    'redemptions_count',
                    'redemptions_percent',
                ]
            )
            ->assertJsonFragment(
                [
                    'expired' => 2, // 2 expired coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'reissued' => 2, // 2 expired coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'cancelled' => 2, // 2 cancelled coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'subscriptions' => 2, // 2 subscribed coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'redemptions_count' => 2, // 2 redeemed coupons
                ]
            )
            ->assertJsonFragment(
                [
                    'redemptions_percent' => 100, // 100 percent redeemed coupons
                ]
            );
    }

    public function testCanGetValidPartnerListFromGroupsForVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $partners = factory(Partner::class, 5)->create();
        $partner_group = factory(PartnerGroup::class)->create();

        $partners[0]->accepts_vouchers = 1;
        $partners[0]->save();

        $partners[1]->accepts_vouchers = 0;
        $partners[1]->save();

        $partners[2]->accepts_vouchers = 1;
        $partners[2]->save();

        $partners[3]->accepts_vouchers = 1;
        $partners[3]->save();

        $partners[4]->accepts_vouchers = 1;
        $partners[4]->save();

        $partner_group->partners()->attach(
            [
                $partners[0]->id,
                $partners[1]->id,
                $partners[2]->id,
            ]
        );

        $partner_group->voucherRestrictions()->attach($voucher->id);

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/partners'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'public_name',
                        'uuid',
                        'public_town',
                        'crm_id',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partners[0]->uuid,  // In the group, accepts vouchers
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partners[2]->uuid,  // In the group, accepts vouchers
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $partners[1]->uuid,  // In the group, does not accept vouchers
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $partners[3]->uuid,  // Not in the group, accepts vouchers
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $partners[4]->uuid,  // Not in the group, does not accept vouchers
                ]
            );
    }

    public function testAllVoucherPartnersReturnedWhenGroupsNotUsed()
    {
        $voucher = factory(Voucher::class)->create();
        $partners = factory(Partner::class, 3)->create();

        $partners[0]->accepts_vouchers = 1;
        $partners[0]->save();

        $partners[1]->accepts_vouchers = 0;
        $partners[1]->save();

        $partners[2]->accepts_vouchers = 1;
        $partners[2]->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/partners'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'public_name',
                        'uuid',
                        'public_town',
                        'crm_id',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partners[0]->uuid,
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $partners[1]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $partners[2]->uuid,
                ]
            );
    }

    public function testCanGetReferrerRestrictionsThroughGroupsForVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 3)->create();
        $referrer_group = factory(ReferrerGroup::class)->create();

        $referrer_group->referrers()->attach(
            [
                $referrers[0]->id,
                $referrers[2]->id,
            ]
        );

        $referrer_group->voucherRestrictions()->attach($voucher->id);

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'first_name',
                        'last_name',
                        'uuid',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[0]->uuid,
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $referrers[1]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[2]->uuid,
                ]
            );
    }

    public function testCanGetReferrerRestrictionsNotThroughGroupsForVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 3)->create();

        $voucher->referrerRestrictions()->attach(
            [
                $referrers[0]->id,
                $referrers[1]->id,
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'first_name',
                        'last_name',
                        'uuid',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[0]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[1]->uuid,
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $referrers[2]->uuid,
                ]
            );
    }

    public function testCanGetReferrerRestrictionsThroughAndNotThroughGroupsForVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 6)->create();
        $referrer_groups = factory(ReferrerGroup::class, 2)->create();

        $voucher->referrerRestrictions()->attach(
            [
                $referrers[0]->id,
                $referrers[1]->id,
            ]
        );

        $referrer_groups[0]->referrers()->attach(
            [
                $referrers[2]->id,
                $referrers[3]->id,
            ]
        );

        $referrer_groups[0]->voucherRestrictions()->attach($voucher->id);

        $referrer_groups[1]->referrers()->attach(
            [
                $referrers[4]->id,
            ]
        );

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'first_name',
                        'last_name',
                        'uuid',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[0]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[1]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[2]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[3]->uuid,
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $referrers[4]->uuid,
                ]
            )
            ->assertJsonMissing(
                [
                    'uuid' => $referrers[5]->uuid,
                ]
            );
    }

    public function testCanGetAllReferrersListWithNoRestrictions()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 3)->create();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers'
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'first_name',
                        'last_name',
                        'uuid',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[0]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[1]->uuid,
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $referrers[2]->uuid,
                ]
            );
    }

    public function testCanUploadReferrerIDsToVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 3)->create();

        Storage::fake('local');
        $file_path = '/tmp/referrer_ids.csv';
        $fp = fopen('/tmp/referrer_ids.csv', 'w');
        fwrite($fp, $referrers[0]->uuid . "\n");
        fwrite($fp, $referrers[1]->uuid . "\n");
        fclose($fp);

        $response = $this->actingAs(User::first())
             ->json(
                 'POST',
                 $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers/upload',
                 [
                     'csv_file' => new UploadedFile($file_path, 'referrer_ids.csv', null, null, null, true),
                 ]
             )
             ->assertStatus(200);

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[0]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[1]->id,
            ]
        );

        $this->assertDatabaseMissing(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[2]->id,
            ]
        );
    }

    public function testCanRemoveIndividualReferrerRestrictionFromVoucher()
    {
        $voucher = factory(Voucher::class)->create();
        $referrers = factory(Referrer::class, 3)->create();

        $voucher->referrerRestrictions()->attach(
            [
                $referrers[0]->id,
                $referrers[1]->id,
                $referrers[2]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[0]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[1]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[2]->id,
            ]
        );

        $response = $this->actingAs(User::first())
             ->json(
                 'DELETE',
                 $this->baseurl . 'voucher/' . $voucher->uuid . '/referrers/' . $referrers[0]->uuid
             )
             ->assertStatus(200);

        $this->assertDatabaseMissing(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[0]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[1]->id,
            ]
        );

        $this->assertDatabaseHas(
            'voucher_referrer_restrictions',
            [
                'voucher_id' => $voucher->id,
                'referrer_id' => $referrers[2]->id,
            ]
        );
    }

    /**
     * @group TestGroup
     */
    public function testCanGetListOfVoucherUniqueCodes()
    {
        extract($this->setUpForVoucherCouponTests());
        $unique_codes[0]->voucher_id = $voucher->id;
        $unique_codes[0]->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "voucher/{$voucher->uuid}/unique_codes"
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '0' => [
                            'coupon_uuid',
                            'code',
                            'status',
                        ],
                    ],
                ]
            );
    }

    /**
     * @group TestGroup
     */
    public function testCanGetListOfVoucherUniqueCodesWithSearch()
    {
        extract($this->setUpForVoucherCouponTests());
        $unique_codes[0]->voucher_id = $voucher->id;
        $unique_codes[0]->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "voucher/{$voucher->uuid}/unique_codes",
                [
                    'search' => $unique_codes[0]->code,
                ]
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '0' => [
                            'coupon_uuid',
                            'code',
                            'status',
                        ],
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'code' => $unique_codes[0]->code,
                ]
            )
            ->assertJsonCount(1, 'data');
    }

    public function testCanRemoveUnusedUniqueVoucherCode()
    {
        extract($this->setUpForVoucherCouponTests());
        $unique_codes[0]->voucher_id = $voucher->id;
        $unique_codes[0]->save();

        DB::table('coupons')
            ->update(['vouchers_unique_codes_used_id' => null]);

        $this->assertDatabaseHas(
            'voucher_unique_codes',
            [
                'voucher_id' => $voucher->id,
                'code' => $unique_codes[0]->code,
            ]
        );

        $response = $this->actingAs(User::first())
             ->json(
                 'DELETE',
                 $this->baseurl . "voucher/{$voucher->uuid}/unique_codes/{$unique_codes[0]->code}"
             )
            ->assertStatus(204);

        $this->assertDatabaseMissing(
            'voucher_unique_codes',
            [
                'voucher_id' => $voucher->id,
                'code' => $unique_codes[0]->code,
            ]
        );
    }

    public function testCantRemoveUsedUniqueVoucherCode()
    {
        extract($this->setUpForVoucherCouponTests());
        $unique_codes[0]->voucher_id = $voucher->id;
        $unique_codes[0]->save();

        DB::table('coupons')
            ->update(['vouchers_unique_codes_used_id' => null]);

        /**
         * Perhaps there's a laravel bug involved here, hard to say and I don't have the time to create a whole
         * new instance to dig into it.
         *
         * Despite the documentation staging "When updating a belongsTo relationship, you may use the associate method."
         * it seems that if there is already a relationship in existence, then the associate() doesn't always update,
         * I saw it failing around 1 in 40 times.
         * Rather it seems to just empty the relationship instead. Perhaps it's falling into line 211 of
         * vendor/laravel/framework/src/Illuminate/Database/Eloquent/Relations/BelongsTo.php, not sure.
         * Anyway, this can be worked around by, in this case, setting the voucher_unique_codes_used_id to null on the
         * coupon, before then associating the voucher to the coupon.
         */

        $coupons[0]->voucher_id = $voucher->id;
        $coupons[0]->vouchers_unique_codes_used_id = null;
        $coupons[0]->save();

        $coupons[0]->uniqueVoucherCodeUsed()->associate($unique_codes[0]);
        $coupons[0]->save();

        $model = $unique_codes[0]->fresh();
        $coupon_model = $coupons[0]->fresh();

        /**
         * These asserts are to detect instances of the bug described above occuring again.
         */
        $this->assertCount(1, $coupon_model->uniqueVoucherCodeUsed()->get());
        $this->assertCount(1, $model->codeUsedOnCoupon()->get());

        $this->assertDatabaseHas(
            'voucher_unique_codes',
            [
                'voucher_id' => $voucher->id,
                'code' => $unique_codes[0]->code,
            ]
        );

        $response = $this->actingAs(User::first())
             ->json(
                 'DELETE',
                 $this->baseurl . "voucher/{$voucher->uuid}/unique_codes/{$unique_codes[0]->code}"
             )
            ->assertStatus(409)
            ->assertSee("Code can't be deleted, already used");


        $this->assertDatabaseHas(
            'voucher_unique_codes',
            [
                'voucher_id' => $voucher->id,
                'code' => $unique_codes[0]->code,
            ]
        );
    }

    public function testCanGetVoucherSubscribersList()
    {
        extract($this->setUpForVoucherCouponTests());

        $coupons[0]->restrict_consumer_id = $consumers[0]->id;
        $coupons[0]->redeemed_by_consumer_id = null;
        $coupons[0]->voucher_id = $voucher->id;
        $coupons[0]->save();

        $coupons[1]->restrict_consumer_id = null;
        $coupons[1]->redeemed_by_consumer_id = $consumers[1]->id;
        $coupons[1]->voucher_id = $voucher->id;
        $coupons[1]->save();

        $unique_codes[0]->voucher_id = $voucher->id;
        $unique_codes[0]->save();

        $coupons[2]->restrict_consumer_id = null;
        $coupons[2]->redeemed_by_consumer_id = $consumers[2]->id;
        $coupons[2]->voucher_id = $voucher->id;
        $coupons[2]->vouchers_unique_codes_used_id = $unique_codes[0]->id;
        $coupons[2]->save();

        $response = $this->actingAs(User::first())
            ->json(
                'GET',
                $this->baseurl . "voucher/{$voucher->uuid}/subscribers"
            )
            ->assertStatus(200)
            ->assertJsonStructure(
                [
                    'data' => [
                        '*' => [
                            'coupon_uuid',
                            'consumer_uuid',
                            'first_name',
                            'last_name',
                            'voucher_name',
                            'status',
                            'unique_code_used',
                            'access_code_used',
                            'issued_at',
                            'redeemed_at',
                        ],
                    ],
                    'current_page',
                    'first_page_url',
                    'from',
                    'last_page',
                    'last_page_url',
                    'next_page_url',
                    'path',
                    'per_page',
                    'prev_page_url',
                    'to',
                    'total',
                ]
            )
            ->assertJsonFragment(
                [
                    'coupon_uuid' => $coupons[0]->uuid,
                    'consumer_uuid' => $consumers[0]->uuid,
                    'first_name' => $consumers[0]->first_name,
                    'last_name' => $consumers[0]->last_name,
                    'voucher_name' => $voucher->public_name,
                ]
            )
            ->assertJsonFragment(
                [
                    'coupon_uuid' => $coupons[1]->uuid,
                    'consumer_uuid' => $consumers[1]->uuid,
                    'first_name' => $consumers[1]->first_name,
                    'last_name' => $consumers[1]->last_name,
                    'voucher_name' => $voucher->public_name,
                ]
            )
            ->assertJsonFragment(
                [
                    'coupon_uuid' => $coupons[2]->uuid,
                    'consumer_uuid' => $consumers[2]->uuid,
                    'first_name' => $consumers[2]->first_name,
                    'last_name' => $consumers[2]->last_name,
                    'voucher_name' => $voucher->public_name,
                    'unique_code_used' => $unique_codes[0]->code,
                ]
            );
    }

    public function testCanGetVoucherSubscribersListCsv()
    {
        extract($this->setUpForVoucherCouponTests());

        $response = $this->actingAs(User::first())
        ->json(
            'GET',
            $this->baseurl . "voucher/{$voucher->uuid}/subscribers/csv"
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

    public function testCanIssueVoucherCouponPetNotRequired()
    {
        extract($this->setUpForVoucherCouponTests());

        $voucher->published = 1;
        $voucher->email_copy = 'This is the email body';
        $voucher->send_by_email = 1;
        $voucher->redeem_to_date = Carbon::now()->addWeek();
        $voucher->redeem_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period = 'years';
        $voucher->subscribe_to_date = Carbon::now()->addWeek();
        $voucher->limit_pet_required = 0;
        $voucher->email_subject_line = 'This is the email subject';
        $voucher->subscribe_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period_count = 1;

        $voucher->partnerGroupRestrictions()->detach();

        $voucher->save();

        $partners[0]->accepts_vouchers = 1;
        $partners[0]->save();

        Mail::fake();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . "voucher/{$voucher->uuid}/{$consumers[0]->uuid}/issue",
                [
                    'partner_uuid' => $partners[0]->uuid,
                ]
            )
            ->assertStatus(201)
            ->assertJsonStructure(
                [
                    'data' => [
                    'uuid',
                    'issued_at',
                    'barcode',
                    'valid_from',
                    'valid_to',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $voucher->uuid,
                    'url' => $voucher->url,
                ]
            );

        $result = $response->decodeResponseJson();

        $this->assertDatabaseHas(
            'coupons',
            [
                'uuid' => $result['data']['uuid'],
                'voucher_id' => $voucher->id,
            ]
        );


        Mail::assertQueued(VoucherCoupon::class, function ($mail) use ($consumers) {
            return $mail->hasTo($consumers[0]->email);
        });
    }

    public function testCantIssueVoucherCouponPetRequiredNotSupplied()
    {
        extract($this->setUpForVoucherCouponTests());

        $voucher->published = 1;
        $voucher->email_copy = 'This is the email body';
        $voucher->send_by_email = 1;
        $voucher->redeem_to_date = Carbon::now()->addWeek();
        $voucher->redeem_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period = 'years';
        $voucher->subscribe_to_date = Carbon::now()->addWeek();
        $voucher->limit_pet_required = 1;
        $voucher->email_subject_line = 'This is the email subject';
        $voucher->subscribe_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period_count = 1;

        $voucher->partnerGroupRestrictions()->detach();

        $voucher->save();

        $partners[0]->accepts_vouchers = 1;
        $partners[0]->save();

        Mail::fake();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . "voucher/{$voucher->uuid}/{$consumers[0]->uuid}/issue",
                [
                    'partner_uuid' => $partners[0]->uuid,
                ]
            )
            ->assertStatus(422);
    }

    public function testCanIssueVoucherCouponPetRequiredAndSupplied()
    {
        extract($this->setUpForVoucherCouponTests());

        $voucher->published = 1;
        $voucher->email_copy = 'This is the email body';
        $voucher->send_by_email = 1;
        $voucher->redeem_to_date = Carbon::now()->addWeek();
        $voucher->redeem_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period = 'years';
        $voucher->subscribe_to_date = Carbon::now()->addWeek();
        $voucher->limit_pet_required = 1;
        $voucher->email_subject_line = 'This is the email subject';
        $voucher->subscribe_from_date = Carbon::now()->subWeek();
        $voucher->redemption_period_count = 1;

        $voucher->partnerGroupRestrictions()->detach();

        $voucher->save();

        $partners[0]->accepts_vouchers = 1;
        $partners[0]->save();

        $pets[0]->consumer_id = $consumers[0]->id;
        $pets[0]->save();

        Mail::fake();

        $response = $this->actingAs(User::first())
            ->json(
                'POST',
                $this->baseurl . "voucher/{$voucher->uuid}/{$consumers[0]->uuid}/issue",
                [
                    'partner_uuid' => $partners[0]->uuid,
                    'pet_uuid' => $pets[0]->uuid,
                ]
            )
            ->assertStatus(201)
            ->assertJsonStructure(
                [
                    'data' => [
                    'uuid',
                    'issued_at',
                    'barcode',
                    'valid_from',
                    'valid_to',
                    ],
                ]
            )
            ->assertJsonFragment(
                [
                    'uuid' => $voucher->uuid,
                    'url' => $voucher->url,
                ]
            );

        $result = $response->decodeResponseJson();

        $this->assertDatabaseHas(
            'coupons',
            [
                'uuid' => $result['data']['uuid'],
                'voucher_id' => $voucher->id,
                'pet_id' => $pets[0]->id,
            ]
        );


        Mail::assertQueued(VoucherCoupon::class, function ($mail) use ($consumers) {
            return $mail->hasTo($consumers[0]->email);
        });
    }

    private function setUpForVoucherCouponTests()
    {
        $voucher = factory(Voucher::class)->create();
        $terms = factory(VoucherTerms::class)->create();
        $access_codes = factory(VoucherAccessCode::class, 5)->create();
        $unique_codes = [];
        for ($i=1; $i<=10; $i++) {
            $unique_codes[] = factory(VoucherUniqueCode::class)->create(['code' => sprintf("AAAA%06d", $i)]);
        }
        $partners = factory(Partner::class, 3)->create();
        $consumers = factory(Consumer::class, 5)->create();
        $pets = factory(Pet::class, 5)->create();
        $referrers = factory(Referrer::class, 2)->create();
        $coupons = factory(Coupon::class, 20)->create();
        $transactions = factory(ReferrerPoints::class, 20)->create();

        return [
            'pets' => $pets,
            'terms' => $terms,
            'coupons' => $coupons,
            'voucher' => $voucher,
            'partners' => $partners,
            'consumers' => $consumers,
            'referrers' => $referrers,
            'transactions' => $transactions,
            'access_codes' => $access_codes,
            'unique_codes' => $unique_codes,
        ];
    }

    private function setUpForVoucherPerformanceTests()
    {
        extract($this->setUpForVoucherCouponTests());

        for ($i = 0; $i < count($coupons); $i++) {
            $coupons[$i]->voucher_id = $voucher->id;
            $coupons[$i]->valid_from = Carbon::now()->subDays(100);
            $coupons[$i]->valid_to = Carbon::now()->addDays(30);
            $coupons[$i]->cancelled = 0;
            $coupons[$i]->restrict_consumer_id = null;
            $coupons[$i]->restrict_partner_id = null;
            $coupons[$i]->referrer_id = null;
            $coupons[$i]->reissued_as_coupon_id = null;
            $coupons[$i]->redeemed_by_consumer_id = null;
            $coupons[$i]->save();
        }

        // Setup Date Offsets
        $offsets = [
            0  => 0,
            1  => 10,
            2  => 30,
            3  => 0,
            4  => 10,
            5  => 30,
            6  => 0,
            7  => 10,
            8  => 30,
            9  => 0,
            10 => 10,
            11 => 30,
            12 => 0,
            13 => 10,
            14 => 30,
        ];

        // Setup 3 Expired Coupons
        for ($i = 0; $i < 3; $i++) {
            $coupons[$i]->valid_to = Carbon::now()->subDays($offsets[$i]);
            $coupons[$i]->save();
        }

        // Setup 3 Re-Issued Coupons
        for ($i = 3; $i < 6; $i++) {
            $coupons[$i]->reissued_as_coupon_id = $coupons[$i + 14]->id;
            $coupons[$i]->cancelled = 0;
            $coupons[$i]->save();
            $coupons[$i + 14]->issued_at = Carbon::now()->subDays($offsets[$i]);
            $coupons[$i + 14]->save();
        }

        // Setup 3 Cancelled Coupons
        for ($i = 6; $i < 9; $i++) {
            $coupons[$i]->cancelled_at = Carbon::now()->subDays($offsets[$i]);
            $coupons[$i]->cancelled = 1;
            $coupons[$i]->save();
        }

        // Setup 3 Subscribed Coupons
        for ($i = 9; $i < 12; $i++) {
            $coupons[$i]->restrict_consumer_id = $consumers[$i - 9]->id;
            $coupons[$i]->issued_at = Carbon::now()->subDays($offsets[$i]);
            $coupons[$i]->save();
        }

        // Setup 3 Redeemed Coupons
        for ($i = 12; $i < 15; $i++) {
            $coupons[$i]->redeemed_by_consumer_id = $consumers[$i - 12]->id;
            $coupons[$i]->redeemed_datetime = Carbon::now()->subDays($offsets[$i]);
            $coupons[$i]->save();
        }

        return [
            'pets' => $pets,
            'terms' => $terms,
            'coupons' => $coupons,
            'voucher' => $voucher,
            'partners' => $partners,
            'consumers' => $consumers,
            'referrers' => $referrers,
            'transactions' => $transactions,
            'access_codes' => $access_codes,
            'unique_codes' => $unique_codes,
        ];
    }
}
