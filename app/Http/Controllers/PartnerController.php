<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\User;
use App\Coupon;
use App\Partner;
use App\JobNotification;
use App\Jobs\ExportComplete;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\UsersResource;
use App\Http\Resources\CouponsResource;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\PartnersResource;
use App\Exports\PartnerCouponRedemptionsExport;
use Illuminate\Support\Facades\Schema as Schema;

class PartnerController extends Controller
{
    private $search_limit = 1000;
    private $default_per_page = 10;
    private $csvDiskName = 'csv_exports';

    public function list(Request $request)
    {
        $rules = [
            'order' => Rule::in(['asc','desc']),
        ];
        $messages = [
            'order.in' => __('Generic.invalid_order'),
        ];

        $request->validate($rules, $messages);

        $partners = new Partner;

        if ($request->input('orderby', null)) {
            if (Schema::hasColumn('partners', $request->input('orderby'))) {
                $partners = $partners->orderBy(
                    $request->input('orderby'),
                    $request->input('order', 'asc')
                );
            }
        }

        return new PartnersResource($partners->paginate($request->input('per_page', $this->default_per_page)));
    }

    public function search(Request $request)
    {
        $partners = $request->input('search') ?
            Partner::search($request->input('search')) :
            new Partner;

       // We use search_limit+1 here, as this allows us to detect if there are more results available than the limit,
       // and return a 413 if that's the case so the user can be prompted to be more specific in their search.
        $limit = $this->search_limit + 1;
        $partners = $partners->take($limit)->get();

        if (count($partners) == $limit) {
            return response()->json([__('Generic.payload_too_large')], 413);
        }

        return new PartnersResource($partners);
    }

    public function getByDistance(Request $request)
    {
        $request->validate(
            [
                'lat' => 'required|between:-90,90',
                'long' => 'required|between:-180,180',
                'limit' => 'nullable|integer|min:1|max:100',
                'radius' => 'nullable|min:0',
            ]
        );

        $lat = $request->input('lat');
        $long = $request->input('long');
        $limit = $request->input('limit', 10);
        $radius = $request->input('radius', 100) * 1000;

        $results = DB::table('partners')
            ->select(
                [
                    'partners.public_name',
                    'partners.uuid',
                    'partners.crm_id',
                    'partners.type',
                    'partners.accepts_loyalty',
                    'partners.public_street_line1',
                    'partners.public_street_line2',
                    'partners.public_street_line3',
                    'partners.public_town',
                    'partners.public_county',
                    'partners.public_postcode',
                    'partners.contact_telephone',
                    'partners.latitude',
                    'partners.longitude',
                ]
            )
            ->selectRaw(
                "ROUND(
                    ST_Distance_Sphere(
                        partners.location_point,
                        ST_GeomFromText('POINT($lat $long)', 4326)
                    ),
                    0
                ) AS distance"
            )
            ->where('partners.accepts_vouchers', '=', 1)
            ->whereNull('partners.deleted_at')
            ->having('distance', '<', $radius)
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        foreach ($results as $key => $result) {
            $results[$key]->weight_management_centre = false;
        }

        return $results;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Partner $partner
     * @return \Illuminate\Http\Response
     */
    public function show(Partner $partner)
    {
        return new PartnerResource($partner);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Partner $partner
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Partner $partner)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Partner $partner
     * @return \Illuminate\Http\Response
     */
    public function destroy(Partner $partner)
    {
        //
    }

    public function accessQuestion(Partner $partner)
    {
        return [
            'id' => $partner->id,
            'question' => $partner->access_question,
        ];
    }

    public function checkAccessAnswer(Request $request, Partner $partner)
    {
        return password_verify($request->answer, $partner->access_password) ?
            array( 'status' => 1, 'message' => 'Correct answer given' ) :
            array( 'status' => 0, 'message' => 'Incorrect answer given' );
    }

    public function couponRedemptions(Request $request, Partner $partner)
    {

        // Partners can only view their own redemptions. RC staff (so 'see internal data') can view
        // redemptions for any partner.
        if (! Auth::user()->hasPermissionTo('see internal data') and
            ! Auth::user()->isAUserOfPartner($partner)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        return new CouponsResource($partner->couponRedemptions($request));
    }

    public function couponRedemptionsCsv(Request $request, Partner $partner)
    {
        if (! Auth::user()->hasPermissionTo('see internal data') and
            ! Auth::user()->isAUserOfPartner($partner)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        activity('partner actions')
            ->withProperties([
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ])
            ->on($partner)
            ->tap('setLogLabel', 'csv export request')
            ->log('Partner redemptions CSV export');

        $export = new PartnerCouponRedemptionsExport(
            $partner,
            $request->input('start_date'),
            $request->input('end_date')
        );
        $filename = $partner->id . '-' . date('YmdGis') . '-' . 'voucher_redemptions.csv';

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

    public function acceptLoyalty(Partner $partner)
    {
        if ($partner->accepts_loyalty === 1) {
            return response()->json([__('PartnerController.already_accepts_loyalty')], 422);
        }

        activity('partner actions')
            ->on($partner)
            ->tap('setLogLabel', 'partner accepts loyalty')
            ->log('Partner Accepts Loyalty');

        $partner->accepts_loyalty = 1;
        $partner->save();

        return new PartnerResource($partner);
    }

    public function refuseLoyalty(Partner $partner)
    {
        if ($partner->accepts_loyalty === 0) {
            return response()->json([__('PartnerController.already_refuses_loyalty')], 422);
        }

        activity('partner actions')
            ->on($partner)
            ->tap('setLogLabel', 'partner refuses loyalty')
            ->log('Partner Refuses Loyalty');

        $partner->accepts_loyalty = 0;
        $partner->save();

        return new PartnerResource($partner);
    }

    public function acceptVouchers(Partner $partner)
    {
        if ($partner->accepts_vouchers === 1) {
            return response()->json([__('PartnerController.already_accepts_vouchers')], 422);
        }

        activity('partner actions')
            ->on($partner)
            ->tap('setLogLabel', 'partner accepts vouchers')
            ->log('Partner Accepts Vouchers');

        $partner->accepts_vouchers = 1;
        $partner->save();

        return new PartnerResource($partner);
    }

    public function refuseVouchers(Partner $partner)
    {
        if ($partner->accepts_vouchers === 0) {
            return response()->json([__('PartnerController.already_refuses_vouchers')], 422);
        }

        activity('partner actions')
            ->on($partner)
            ->tap('setLogLabel', 'partner refuses vouchers')
            ->log('Partner Refuses Vouchers');

        $partner->accepts_vouchers = 0;
        $partner->save();

        return new PartnerResource($partner);
    }

    public function getPendingAccountApprovals(Partner $partner)
    {
        if (!Auth::user()->hasPermissionTo('see internal data') and
            !Auth::user()->isAPartnerManagerOfPartner($partner)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        return new UsersResource($partner->pendingPartnerUsers()->get());
    }

    public function getPartnerAccounts(Partner $partner)
    {
        if (!Auth::user()->hasPermissionTo('see internal data') and
            !Auth::user()->isAPartnerManagerOfPartner($partner)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        return new UsersResource($partner->partnerUsers()->withPivot('manager')->get());
    }

    public function getIdsList()
    {
        return Partner::select('uuid', 'crm_id', 'public_name', 'type', 'subtype')->get();
    }

    public function addUserAccount(Partner $partner, Request $request)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfPartner($partner)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        if (!$user = User::where('email', '=', $request->input('email'))->first()) {
            return response()->json([__('PartnerController.user_needs_signup')], 422);
        }

        if (!$user->hasRole('partner user')) {
            return response()->json([__('PartnerController.not_partner_user')], 409);
        }

        if ($user->isAUserOfPartner($partner)) {
            return response()->json([__('PartnerController.already_partner_user')], 409);
        }

        if ($user->isPendingApprovalForPartner($partner)) {
            $user->userPartners()->updateExistingPivot($partner, [
                'approved' => 1,
                'approved_at' => now(),
            ]);
            return response()->json([__('Generic.ok')], 200);
        }

        $user->userPartners()->attach($partner, [
            'approved' => 1,
            'approved_at' => now(),
        ]);
        return response()->json([__('Generic.ok')], 200);
    }
}
