<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Blink;
use App\Pet;
use Storage;
use App\Partner;
use App\Coupon;
use App\Voucher;
use App\Consumer;
use App\Referrer;
use App\VoucherTerms;
use App\ReferrerPoints;
use App\Helpers\Helper;
use App\JobNotification;
use App\VoucherAccessCode;
use App\VoucherUniqueCode;
use App\Mail\VoucherCoupon;
use App\Jobs\ExportComplete;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Resources\CouponResource;
use App\Http\Resources\VoucherResource;
use App\Http\Resources\VouchersResource;
use Grimzy\LaravelMysqlSpatial\Eloquent;
use App\Imports\VoucherReferrerIDsImport;
use App\Exports\VouchersSubscribersExport;
use Grimzy\LaravelMysqlSpatial\Types\Point;
use App\Exports\VouchersReferrerPointsExport;
use Illuminate\Support\Facades\Schema as Schema;
use App\Http\Resources\VoucherUniqueCodeResource;
use App\Http\Resources\ReferrerPointsListResource;
use App\Http\Resources\VoucherUniqueCodesResource;
use App\Exports\VouchersGenerateUniqueCodesExport;

class VoucherController extends Controller
{
    private $csvDiskName = 'csv_exports';

    public function index()
    {
        return new VouchersResource(Voucher::all());
    }

    public function create(Request $request)
    {
        $request->validate(
            [
                'url' => 'required|max:50|unique:vouchers',
                'name' => 'required|max:100',
                'terms' => 'required',
                'page_copy' => 'nullable|max:65536',
                'value_gbp' => 'required|integer',
                'value_eur' => 'required|integer',
                'published' => 'nullable|boolean',
                'email_copy' => 'required_if:send_by_email,1|max:65536',
                'public_name' => 'required|max:100',
                'valassis_pin' => 'nullable|max:20',
                'limit_per_pet' => 'nullable|integer',
                'send_by_email' => 'nullable|boolean',
                'voucher_terms' => 'nullable|max:65536',
                'redeem_to_date' => 'nullable|date',
                'page_copy_image' => 'nullable|file',
                'limit_species_id' => 'nullable|exists:species,id',
                'redeem_from_date' => 'nullable|date',
                'unique_codes_url' => 'nullable|url',
                'valassis_barcode' => 'nullable|max:50',
                'limit_per_account' => 'nullable|integer',
                'partner_group_ids' => 'nullable|array',
                'redemption_period' => 'in:days,months,years',
                'subscribe_to_date' => 'nullable|date',
                'unique_codes_file' => 'nullable|file',
                'email_subject_line' => 'required_if:send_by_email,1|max:300',
                'limit_pet_required' => 'nullable|boolean',
                'referrer_group_ids' => 'nullable|array',
                'unique_code_prefix' => 'required_if:unique_code_required,1|max:10',
                'partner_group_ids.*' => 'nullable:exists:partner_groups,id',
                'subscribe_from_date' => 'required|date',
                'referrer_group_ids.*' => 'nullable:exists:referrer_groups,id',
                'unique_code_required' => 'required|boolean',
                'redemption_period_count' => 'integer',
                'referrer_points_at_create' => 'nullable|integer',
                'referrer_points_at_redeem' => 'nullable|integer',
                'retrieve_unique_codes_every_type' => 'nullable|in:hours,days,months,years',
                'limit_per_account_per_date_period' => 'nullable|in:0,3,6,12',
                'retrieve_unique_codes_every_count' => 'nullable|integer',
                'retrieve_unique_codes_every_day_at_time' => 'nullable|date_format:H:i',
            ]
        );

        if ($request->unique_code_required) {
            $request->validate(
                [
                    'unique_code_prefix' => 'unique:vouchers',
                ]
            );
        }

        $unique_codes = [];
        if ($request->hasFile('unique_codes_file')) {
            $unique_codes = file($request->file('unique_codes_file')->getRealPath(), FILE_IGNORE_NEW_LINES);

            if ($request->input('unique_codes_upload_skip_existing', 0) == 0) {
                $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
                if (count($existing_codes)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'unique_codes_file' => 'Codes already in use: ' . implode(',', $existing_codes->toArray()),
                    ]);
                }
            }
        }

        $image_path = '';
        DB::beginTransaction();

        try {
            $voucher = Voucher::create($request->only([
                'url',
                'name',
                'terms',
                'page_copy',
                'value_gbp',
                'value_eur',
                'published',
                'email_copy',
                'public_name',
                'valassis_pin',
                'limit_per_pet',
                'send_by_email',
                'redeem_to_date',
                'limit_species_id',
                'redeem_from_date',
                'unique_codes_url',
                'valassis_barcode',
                'limit_per_account',
                'partner_group_ids',
                'redemption_period',
                'subscribe_to_date',
                'instant_redemption',
                'email_subject_line',
                'limit_pet_required',
                'unique_code_prefix',
                'subscribe_from_date',
                'unique_code_required',
                'redemption_period_count',
                'referrer_points_at_create',
                'referrer_points_at_redeem',
                'retrieve_unique_codes_every_type',
                'limit_per_account_per_date_period',
                'retrieve_unique_codes_every_count',
                'limit_to_instant_redemption_partner',
                'retrieve_unique_codes_every_day_at_time',
            ]));

            if ($request->page_copy_image) {
                $image_path = $request->file('page_copy_image')->store('voucher_images', 'public_web_assets');
                $voucher->update(
                    [
                        'page_copy_image' => $image_path,
                    ]
                );
            }

            VoucherTerms::create(
                [
                    'used_from' => now(),
                    'voucher_id' => $voucher->id,
                    'voucher_terms' => $request->terms,
                ]
            );

            if (isset($request->partner_group_ids)) {
                $voucher->partnerGroupRestrictions()->attach($request->partner_group_ids);
            }

            if (isset($request->referrer_group_ids)) {
                $voucher->referrerGroupRestrictions()->attach($request->referrer_group_ids);
            }

            $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
            $remaining_codes = array_diff($unique_codes, $existing_codes->toArray());

            foreach ($remaining_codes as $code) {
                VoucherUniqueCode::create(
                    [
                        'code' => $code,
                        'voucher_id' => $voucher->id,
                    ]
                );
            }


            activity('voucher actions')
                ->on($voucher)
                ->tap('setLogLabel', 'create new voucher')
                ->log('New Voucher created');
        } catch (\Exception $e) {
            DB::rollback();
            Storage::disk('public_web_assets')->delete($image_path);

            throw $e;
        }

        DB::commit();
        return new VoucherResource($voucher);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $request->validate(
            [
                'url' => 'required|max:50|unique:vouchers,url,'.$voucher->id,
                'name' => 'required|max:100',
                'terms' => 'required',
                'page_copy' => 'nullable|max:65536',
                'value_gbp' => 'required|integer',
                'value_eur' => 'required|integer',
                'published' => 'nullable|boolean',
                'email_copy' => 'required_if:send_by_email,1|max:65536',
                'public_name' => 'required|max:100',
                'valassis_pin' => 'nullable|max:20',
                'limit_per_pet' => 'nullable|integer',
                'send_by_email' => 'nullable|boolean',
                'voucher_terms' => 'nullable|max:65536',
                'redeem_to_date' => 'nullable|date',
                'page_copy_image' => 'nullable|file',
                'limit_species_id' => 'nullable|exists:species,id',
                'redeem_from_date' => 'nullable|date',
                'unique_codes_url' => 'nullable|url',
                'valassis_barcode' => 'nullable|max:50',
                'limit_per_account' => 'nullable|integer',
                'partner_group_ids' => 'nullable|array',
                'redemption_period' => 'in:days,months,years',
                'subscribe_to_date' => 'nullable|date',
                'instant_redemption' => 'boolean',
                'email_subject_line' => 'required_if:send_by_email,1|max:300',
                'limit_pet_required' => 'nullable|boolean',
                'referrer_group_ids' => 'nullable|array',
                'unique_code_prefix' => 'required_if:unique_code_required,1|max:10',
                'partner_group_ids.*' => 'nullable:exists:partner_groups,id',
                'subscribe_from_date' => 'required|date',
                'referrer_group_ids.*' => 'nullable:exists:referrer_groups,id',
                'unique_code_required' => 'required|boolean',
                'redemption_period_count' => 'integer',
                'referrer_points_at_create' => 'nullable|integer',
                'referrer_points_at_redeem' => 'nullable|integer',
                'retrieve_unique_codes_every_type' => 'nullable|in:hours,days,months,years',
                'limit_per_account_per_date_period' => 'nullable|in:0,3,6,12',
                'retrieve_unique_codes_every_count' => 'nullable|integer',
                'limit_to_instant_redemption_partner' => 'boolean',
                'retrieve_unique_codes_every_day_at_time' => 'nullable|date_format:H:i',
            ]
        );

        if (isset($request->unique_code_required) and $request->unique_code_required) {
            $request->validate(
                [
                    'unique_code_prefix' => 'unique:vouchers,unique_code_prefix,' . $voucher->id,
                ]
            );
        }

        $unique_codes = [];
        if ($request->hasFile('unique_codes_file')) {
            $unique_codes = file($request->file('unique_codes_file')->getRealPath(), FILE_IGNORE_NEW_LINES);

            if ($request->input('unique_codes_upload_skip_existing', 0) == 0) {
                $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
                if (count($existing_codes)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'unique_codes_file' => 'Codes already in use: ' . implode(',', $existing_codes->toArray()),
                    ]);
                }
            }
        }

        $image_path = '';
        DB::beginTransaction();

        try {
            $voucher->update($request->only([
                'url',
                'name',
                'terms',
                'page_copy',
                'value_gbp',
                'value_eur',
                'published',
                'email_copy',
                'public_name',
                'valassis_pin',
                'limit_per_pet',
                'send_by_email',
                'redeem_to_date',
                'limit_species_id',
                'redeem_from_date',
                'unique_codes_url',
                'valassis_barcode',
                'limit_per_account',
                'partner_group_ids',
                'redemption_period',
                'subscribe_to_date',
                'instant_redemption',
                'email_subject_line',
                'limit_pet_requried',
                'unique_code_prefix',
                'subscribe_from_date',
                'unique_code_required',
                'redemption_period_count',
                'referrer_points_at_create',
                'referrer_points_at_redeem',
                'retrieve_unique_codes_every_type',
                'limit_per_account_per_date_period',
                'retrieve_unique_codes_every_count',
                'limit_to_instant_redemption_partner',
            ]));

            if ($request->page_copy_image) {
                $image_path = $request->file('page_copy_image')->store('voucher_images', 'public_web_assets');
            }
            $voucher->update(
                [
                    'page_copy_image' => $image_path,
                ]
            );

            if ($request->terms != $voucher->currentTerms->voucher_terms) {
                $voucher->currentTerms->used_until = now();
                $voucher->currentTerms->save();

                VoucherTerms::create(
                    [
                        'used_from' => now(),
                        'voucher_id' => $voucher->id,
                        'voucher_terms' => $request->terms,
                    ]
                );
            }

            $voucher->partnerGroupRestrictions()->sync(
                isset($request->partner_group_ids) ?
                $request->partner_group_ids :
                array()
            );

            $voucher->referrerGroupRestrictions()->sync(
                isset($request->referrer_group_ids) ?
                $request->referrer_group_ids :
                array()
            );

            $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
            $remaining_codes = array_diff($unique_codes, $existing_codes->toArray());

            foreach ($remaining_codes as $code) {
                VoucherUniqueCode::create(
                    [
                        'code' => $code,
                        'voucher_id' => $voucher->id,
                    ]
                );
            }

            activity('voucher actions')
                ->on($voucher)
                ->tap('setLogLabel', 'update voucher')
                ->log('Voucher updated');
        } catch (\Exception $e) {
            DB::rollback();
            Storage::disk('public_web_assets')->delete($image_path);

            throw $e;
        }

        DB::commit();
        return new VoucherResource($voucher);
    }

    public function show(Voucher $voucher)
    {
        return new VoucherResource($voucher);
    }

    public function getByReference(string $reference = '')
    {
        $voucher = Voucher::where('url', '=', $reference)->first();

        if ($voucher === null) {
            return response()->json([__('Generic.not_found')], 404);
        }

        return new VoucherResource($voucher);
    }

    public function uploadUniqueCodes(Request $request, Voucher $voucher)
    {
        $unique_codes = [];
        if (!$request->hasFile('unique_codes_file')) {
            return response()->json(['Missing codes file'], 422);
        }

        $unique_codes = file($request->file('unique_codes_file')->getRealPath(), FILE_IGNORE_NEW_LINES);

        if ($request->input('unique_codes_upload_skip_existing', 0) == 0) {
            $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
            if (count($existing_codes)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'unique_codes_file' => 'Codes already in use: ' . implode(',', $existing_codes->toArray()),
                ]);
            }
        }

        $code_count = 0;
        DB::beginTransaction();

        try {
            $existing_codes = $this->checkForExistingUniqueCodes($unique_codes);
            $remaining_codes = array_diff($unique_codes, $existing_codes->toArray());

            foreach ($remaining_codes as $code) {
                VoucherUniqueCode::create(
                    [
                        'code' => $code,
                        'voucher_id' => $voucher->id,
                    ]
                );
                $code_count++;
            }

            activity('voucher actions')
            ->tap('setLogLabel', 'codes uploaded')
            ->log("Uploaded {$code_count} new unique codes for voucher");
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(['OK'], 200);
    }

    public function generateUniqueCodes(Request $request, Voucher $voucher)
    {
        $request->validate(
            [
                'prefix' => 'nullable|max:6',
                'length' => 'nullable|integer|max:20',
                'quantity' => 'nullable|integer|max:100000',
                'charset' => 'nullable|alpha_num',
            ]
        );

        activity('voucher actions')
        ->tap('setLogLabel', 'voucher generate unique codes csv export request')
        ->log('Vouchers - Generate unique codes CSV export');

        $export = new VouchersGenerateUniqueCodesExport(
            $voucher,
            $request->input('prefix', $voucher->unique_code_prefix ? $voucher->unique_code_prefix : ''),
            $request->input('length', 10),
            $request->input('quantity', 100),
            $request->input('charset', 'ABCDEFGHJKLMNPQRTUVWXYZ012346789'),
        );

        $filename = 'voucher_generate_unique_codes' . '-' . date('YmdGis') . '.csv';
        $notification = new JobNotification();
        $notification->type = 'csv_export';
        $notification->user_id = Auth::user()->id;
        $notification->disk = $this->csvDiskName;
        $notification->filename = $filename;
        $notification->download_limit = 1;
        $notification->status = 'requested';
        $notification->save();
        $export->queue($filename, $this->csvDiskName)->chain([
            new ExportComplete($notification),
        ]);

        return $notification;
    }

    public function clone(Voucher $voucher)
    {
        DB::beginTransaction();

        try {
            $cloned_voucher = $voucher->duplicate();
            $cloned_voucher->push();

            if (!$voucher->currentTerms) {
                throw new \Exception("Can't clone - no terms attached");
            }

            $terms = $voucher->currentTerms->duplicate();
            $terms->voucher_id = $cloned_voucher->id;
            $terms->save();

            activity('voucher actions')
            ->withProperties([
                'old voucher' => $voucher->id,
                'new_voucher' => $cloned_voucher->id,
            ])
            ->tap('setLogLabel', 'clone voucher')
            ->log('Voucher cloned');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();

        return new VoucherResource($cloned_voucher);
    }

    public function referrerPointsList(Request $request, Voucher $voucher)
    {
        $request->validate(
            [
                'order_by' => 'array',
            ]
        );

        $orders = [];

        if ($request->input('orderby', null) !== null) {
            foreach ($request->input('orderby') as $order_field => $order_dir) {
                list($table, $field) = explode('.', $order_field);
                if (Schema::hasColumn($table, $field)) {
                    $orders[$order_field] = $order_dir;
                }
            }
        }

        $records = ReferrerPoints
            ::join('coupons', 'referrer_points.coupon_id', '=', 'coupons.id')
            ->join('vouchers', 'coupons.voucher_id', '=', 'vouchers.id')
            ->join('consumers', 'referrer_points.consumer_id', '=', 'consumers.id')
            ->join('referrers', 'referrer_points.referrer_id', '=', 'referrers.id')
            ->select(
                [
                    'referrer_points.id AS transaction_id',
                    'referrer_points.transaction_date',
                    'referrer_points.transaction_type',
                    'referrer_points.points',
                    'consumers.first_name AS consumer_firstname',
                    'consumers.last_name AS consumer_lastname',
                    'consumers.email AS consumer_email',
                    'consumers.uuid AS consumer_uuid',
                    'referrers.first_name AS referrer_firstname',
                    'referrers.last_name AS referrer_lastname',
                    'referrers.email AS referrer_email',
                    'referrers.uuid AS referrer_uuid',
                    'vouchers.uuid AS voucher_uuid',
                ]
            )
            ->where('vouchers.id', '=', $voucher->id);

        if (count($orders)) {
            foreach ($orders as $field => $dir) {
                $records = $records->orderBy($field, $dir);
            }
        }

        return new ReferrerPointsListResource($records->paginate($request->input('per_page', 10)));
    }

    public function referrerPointsListCsv(Voucher $voucher = null)
    {
        activity('voucher actions')
        ->tap('setLogLabel', 'voucher referrer points csv export request')
        ->log('Vouchers - Referrer points CSV export');

        $export = new VouchersReferrerPointsExport($voucher);

        $filename = 'voucher_referrer_points' . '-' . date('YmdGis') . '.csv';
        $notification = new JobNotification();
        $notification->type = 'csv_export';
        $notification->user_id = Auth::user()->id;
        $notification->disk = $this->csvDiskName;
        $notification->filename = $filename;
        $notification->download_limit = 1;
        $notification->status = 'requested';
        $notification->save();
        $export->queue($filename, $this->csvDiskName)->chain([
            new ExportComplete($notification),
        ]);

        return $notification;
    }

    /**
     * Accepts several parameters to define a date period for the performance data.
     *
     * @param {int} days The number of days.
     * @param {string} start Start date in Y-m-d format.
     * @param {string} end End date in Y-m-d format.
     */
    public function performance(Voucher $voucher, Request $request)
    {
        $start = Carbon::createFromTimestamp(0)->startOfDay();
        $end = Carbon::now();

        if ($request->input('days') && ($request->input('start') || $request->input('end'))) {
            return response()->json(['error' => __('Coupon.mixed_params')], 400);
        }

        if ($request->input('days')) {
            $start = Carbon::now()->subDays(intval($request->input('days')))->startOfDay();
        }

        if ($request->input('start') && Helper::dateMatchesFormat($request->input('start'), 'Y-m-d')) {
            $start = Carbon::parse($request->input('start'))->startOfDay();
        }

        if ($request->input('end') && Helper::dateMatchesFormat($request->input('end'), 'Y-m-d')) {
            $end = Carbon::parse($request->input('end'))->endOfDay();
        }

        return response()->json(
            [
                'expired' => $this->getExpiredCount($voucher, $start, $end),
                'reissued' => $this->getReissuedCount($voucher, $start, $end),
                'cancelled' => $this->getCancelledCount($voucher, $start, $end),
                'subscriptions' => $this->getSubscriptionsCount($voucher, $start, $end),
                'redemptions_count' => $this->getRedemptionsCount($voucher, $start, $end),
                'redemptions_percent' => $this->getRedemptionsPercent($voucher, $start, $end),
            ],
            200
        );
    }

    public function getValidPartnersList(Request $request, Voucher $voucher)
    {
        return $voucher->validPartners($request->input('search'));
    }

    public function getValidPartnersCount(Request $request, Voucher $voucher)
    {
        return [
            'total' => count($voucher->validPartners($request->input('search')))
        ];
    }

    public function getValidPartnersListByDistance(Request $request, Voucher $voucher)
    {
        $request->validate(
            [
                'lat' => 'required|between:-90,90',
                'long' => 'required|between:-180,180',
            ]
        );

        return $voucher->validPartnersByDistance(
            $request->input('lat'),
            $request->input('long'),
            $request->input('per_page', 10),
            $request->input('search')
        );
    }

    public function getValidReferrersList(Request $request, Voucher $voucher)
    {
        return $voucher->validReferrers($request->input('search'));
    }

    public function uploadReferrerIDsFile(Voucher $voucher, Request $request)
    {
        $request->validate(
            [
                'csv_file' => 'file|required',
            ]
        );

        Excel::import(new VoucherReferrerIDsImport($voucher), request()->file('csv_file'));
    }

    public function removeReferrerRestriction(Voucher $voucher, Referrer $referrer)
    {
        $voucher->referrerRestrictions()->detach($referrer);
        return response()->json(['OK'], 200);
    }

    public function getUniqueCodesList(Voucher $voucher, Request $request)
    {
        return new VoucherUniqueCodesResource(
            $voucher->uniqueCodes($request)
        );
    }

    public function getSubscribers(Voucher $voucher, Request $request)
    {
        /**
         * $voucher->subscribers() used to be pushed through a resource file here. A change in Laravel means
         * that results from a DB:: call can not longer go through resource files. The fact they used to was
         * accidental, so the fact they no longer do so is not considered to be a bug.
         * So, we just return the query results directly now.
         */
        return $subscribers = $voucher->subscribers($request);
    }

    public function subscribersCsv(Voucher $voucher = null)
    {
        activity('voucher actions')
        ->tap('setLogLabel', 'voucher subscribers csv export request')
        ->log('Vouchers - Subscribers CSV export');

        $export = new VouchersSubscribersExport($voucher);

        $filename = 'voucher_subscribers' . '-' . date('YmdGis') . '.csv';
        $notification = new JobNotification();
        $notification->type = 'csv_export';
        $notification->user_id = Auth::user()->id;
        $notification->disk = $this->csvDiskName;
        $notification->filename = $filename;
        $notification->download_limit = 1;
        $notification->status = 'requested';
        $notification->save();
        $export->queue($filename, $this->csvDiskName)->chain([
            new ExportComplete($notification),
        ]);

        return $notification;
    }

    public function issueVoucherCoupon(Voucher $voucher, Consumer $consumer, Request $request)
    {
        if (!$voucher->validForIssue($consumer)) {
            return response()->json([$voucher->getLastMessage()], 422);
        }

        $partner = null;
        if ($request->input('partner_uuid')) {
            $partner = Partner::where('uuid', '=', $request->input('partner_uuid'))->firstOrFail();
            if ($voucher->validPartners($partner->uuid)->count() === 0) {
                return response()->json(['Partner is not valid for this voucher'], 422);
            }
        }

        $referrer = null;
        if ($request->input('referrer_uuid')) {
            $referrer = \App\Referrer::where('uuid', '=', $request->input('referrer_uuid'))->firstOrFail();
            if ($voucher->validReferrers($referrer->uuid)->count() === 0) {
                return response()->json(['Referrer is not valid for this voucher'], 422);
            }
        }

        $pet = null;
        if ($voucher->limit_pet_required) {
            if (!$request->input('pet_uuid')) {
                return response()->json(['pet_uuid is required'], 422);
            }
            $pet = Pet
                ::where('consumer_id', '=', $consumer->id)
                ->where('uuid', '=', $request->input('pet_uuid'))->firstOrFail();
        }

        if ($voucher->limit_per_pet and
            $pet->coupons()->where('voucher_id', '=', $voucher->id)->count() >= $voucher->limit_pet_pet) {
            return response()->json(['Vouchers per pet limit reached'], 422);
        }

        if ($voucher->limit_species_id) {
            if ($pet->breed->species_id !== $voucher->limit_species_id) {
                return response()->json(['Supplied pet is not correct species'], 422);
            }
        }

        $unique_code = null;
        if ($voucher->unique_code_required) {
            if (!$request->input('access_code')) {
                return response()->json(['access_code is required'], 422);
            }
            $unique_code = VoucherUniqueCode
                ::where('code', '=', $request->input('access_code'))
                ->where('voucher_id', '=', $voucher->id)->firstOrFail();
        }

        $shared_code = null;
        if ($voucher->shared_code) {
            if (!$request->input('access_code')) {
                return response()->json(['access code is required'], 422);
            }
            $shared_code = $request->input('access_code');
        }

        $access_code = null;
        if ($voucher->access_code_required) {
            if (!$request->input('access_code')) {
                return response()->json(['access code is required'], 422);
            }
            $access_code = VoucherAccessCode
                ::where('access_code', '=', $request->input('access_code'))
                ->where('voucher_id', '=', $voucher->id)->firstOrFail();

            $date = new Carbon();

            if ($date < $access_code->start_date) {
                return response()->json(['Access code is not valid yet'], 422);
            }
            if ($date > $access_code->expiry_date) {
                return response()->json(['Access code has expired'], 422);
            }

            if ($access_code->max_uses !== null) {
                if ($access_code->coupons()->count() >= $access_code->max_uses) {
                    return response()->json(['Access code usage limit has been reached'], 422);
                }
            }
        }

        $coupon = null;

        DB::beginTransaction();

        try {
            $validTo = null;
            if ($voucher->redemption_period_count) {
                switch ($voucher->redemption_period) {
                    case 'days':
                        $validTo = Carbon::now()->addDay($voucher->redemption_period_count);
                        break;

                    case 'months':
                        $validTo = Carbon::now()->addMonth($voucher->redemption_period_count);
                        break;

                    case 'years':
                        $validTo = Carbon::now()->addYear($voucher->redemption_period_count);
                        break;

                    default:  // Hard to specify a valid default, surely two years is enough?
                        $validTo = Carbon::now()->addYear(2);
                        break;
                }
            }

            $coupon = new Coupon(
                [
                    'voucher_id' => $voucher->id,
                    'issued_at' => now(),
                    'access_code_id' => $voucher->access_code_required ? $access_code->id : null,
                    'restrict_consumer_id' => $consumer->id,
                    'restrict_partner_id' => $partner ? $partner->id : null,
                    'referrer_id' => $referrer ? $referrer->id : null,
                    'barcode' => \Helper::generateBarcodeNumber(13, '501', 'coupons', 'barcode'),
                    'valid_from' => now(),
                    'valid_to' => $validTo,
                    'maximum_uses' => 1,
                    'shared_code' => $voucher->shared_code ? $shared_code->id : null,
                    'vouchers_unique_codes_used_id' => $voucher->unique_code_required ? $unique_code->id : null,
                    'status' => 'ISSUED',
                    'pet_id' => $pet ? $pet->id : null,
                ]
            );
            $coupon->save();

            activity('voucher actions')
                ->on($voucher)
                ->tap('setLogLabel', 'coupon for voucher issued')
                ->withProperties(
                    [
                        'coupon_id' => $coupon->id,
                        'access_code_id' => $access_code ? $access_code->id : null,
                        'restrict_consumer_id' => $coupon->restrict_consumer_id,
                        'restrict_partner_id' => $coupon->restrict_partner_id,
                        'referrer_id' => $coupon->referrer_id,
                        'barcode' => $coupon->barcode,
                        'valid_from' => $coupon->valid_from,
                        'valid_to' => $coupon->valid_to,
                        'shared_code' => $coupon->shared_code,
                        'unique_code_id' => $coupon->vouchers_unique_codes_used_id,
                        'pet_id' => $coupon->pet_id,
                    ]
                )
                ->log('New Coupon for Voucher issued');

            if ($voucher->send_by_email) {
                $partner_address = '';
                if ($partner) {
                    $partner_address = $partner->public_street_line1 ? $partner->public_street_line1 . ', ' : null;
                    $partner_address .= $partner->public_street_line2 ? $partner->public_street_line2 . ', ' : null;
                    $partner_address .= $partner->public_street_line3 ? $partner->public_street_line3 . ', ' : null;
                    $partner_address .= $partner->public_town ? $partner->public_town . ', ' : null;
                    $partner_address .= $partner->public_county ? $partner->public_county . ', ' : null;
                    $partner_address .= $partner->public_postcode ? $partner->public_postcode . ', ' : null;
                    $partner_address = substr($partner_address, 0, -2); // strip out the last ', '
                }

                Helper::write1DBarcodePngImage($coupon->barcode, 'barcodes/' . $coupon->uuid . '.png');

                $placeholders = [
                    '[[firstname]]' => $consumer->first_name,
                    '[[lastname]]' => $consumer->last_name,
                    '[[title]]' => $consumer->nameTitle->title,
                    '[[barcode]]' => $coupon->barcode,
                    '[[barcode_url]]' => Storage::disk('public_web_assets')->url('barcodes/' . $coupon->uuid . '.png'),
                    '[[partner_name]]' => $partner ? $partner->public_name : null,
                    '[[partner_address]]' => $partner_address,
                    '[[redeem_from]]' => date('d/m/Y', strtotime($coupon->valid_from)),
                    '[[redeem_to]]' => date('d/m/Y', strtotime($coupon->valid_to)),
                    '[[coupon_uuid]]' => $coupon->uuid,
                ];

                $email_copy = str_replace(array_keys($placeholders), $placeholders, $voucher->email_copy);

                Mail::to($consumer->email)
                    ->queue(
                        new VoucherCoupon(
                            $voucher->email_subject_line,
                            $email_copy
                        )
                    );
            }
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();

        return new CouponResource($coupon);
    }

    private function checkForExistingUniqueCodes(array $unique_codes)
    {
        return VoucherUniqueCode::whereIn('code', $unique_codes)->pluck('code');
    }

    private function getExpiredCount(Voucher $voucher, string $start, string $end)
    {
        return blink()->once(
            'expiredCount_' . $voucher->id,
            function () use ($voucher, $start, $end) {
                return Coupon
                    ::whereNull(
                        [
                            'redeemed_by_consumer_id',
                            'reissued_as_coupon_id',
                        ]
                    )
                    ->where(
                        [
                            ['voucher_id', $voucher->id],
                            ['cancelled', 0],
                            ['valid_to', '>=', $start],
                            ['valid_to', '<=', $end],
                        ]
                    )
                    ->get()
                    ->count();
            }
        );
    }

    private function getReissuedCount(Voucher $voucher, string $start, string $end)
    {
        return blink()->once(
            'reissuedCount_' . $voucher->id,
            function () use ($voucher, $start, $end) {
                return Coupon
                    ::whereNotNull(
                        [
                            'reissued_as_coupon_id',
                        ]
                    )
                    ->where(
                        [
                            ['voucher_id', $voucher->id],
                            ['cancelled', 0],
                        ]
                    )
                    ->whereHas('reissuedAsCoupon', function ($query) use ($start, $end) {
                        $query->where('issued_at', '>=', $start)
                              ->where('issued_at', '<=', $end);
                    })
                    ->get()
                    ->count();
            }
        );
    }

    private function getCancelledCount(Voucher $voucher, string $start, string $end)
    {
        return blink()->once(
            'cancelledCount_' . $voucher->id,
            function () use ($voucher, $start, $end) {
                return Coupon
                    ::whereNull(
                        [
                            'reissued_as_coupon_id',
                        ]
                    )
                    ->where(
                        [
                            ['voucher_id', $voucher->id],
                            ['cancelled', 1],
                        ]
                    )
                    ->where(function ($query) use ($start, $end) {
                        $query->where('cancelled_at', '>=', $start)
                              ->where('cancelled_at', '<=', $end);
                    })
                    ->get()
                    ->count();
            }
        );
    }

    private function getSubscriptionsCount(Voucher $voucher, string $start, string $end)
    {
        return blink()->once(
            'subscriptionsCount_' . $voucher->id,
            function () use ($voucher, $start, $end) {
                return Coupon
                    ::whereNull(
                        [
                            'reissued_as_coupon_id',
                        ]
                    )
                    ->whereNotNull(
                        [
                            'restrict_consumer_id',
                        ]
                    )
                    ->where(
                        [
                            ['voucher_id', $voucher->id],
                            ['cancelled', 0],
                        ]
                    )
                    ->where(function ($query) use ($start, $end) {
                        $query->where('issued_at', '>=', $start)
                              ->where('issued_at', '<=', $end);
                    })
                    ->get()
                    ->count();
            }
        );
    }

    private function getRedemptionsCount(Voucher $voucher, string $start, string $end)
    {
        return blink()->once(
            'redemptionsCount_' . $voucher->id,
            function () use ($voucher, $start, $end) {
                return Coupon
                    ::whereNotNull(
                        [
                            'redeemed_by_consumer_id',
                        ]
                    )
                    ->where(
                        [
                            ['voucher_id', $voucher->id],
                            ['cancelled', 0],
                        ]
                    )
                    ->where(function ($query) use ($start, $end) {
                        $query->where('redeemed_datetime', '<=', $end)
                              ->where('redeemed_datetime', '>=', $start);
                    })
                    ->get()
                    ->count();
            }
        );
    }

    private function getRedemptionsPercent(Voucher $voucher, string $start, string $end)
    {
        if ($this->getSubscriptionsCount($voucher, $start, $end) === 0) {
            return 0;
        }

        return round(
            $this->getRedemptionsCount($voucher, $start, $end) /
                $this->getSubscriptionsCount($voucher, $start, $end) * 100,
            2
        );
    }

    private function calculateValidToDateForCoupon(Voucher $voucher)
    {
    }
}
