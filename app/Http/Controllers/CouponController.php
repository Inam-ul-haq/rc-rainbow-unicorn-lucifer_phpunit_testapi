<?php

namespace App\Http\Controllers;

use Auth;
use App\Coupon;
use App\Partner;
use App\Consumer;
use Illuminate\Http\Request;
use App\Http\Resources\CouponResource;
use App\Http\Resources\CouponsResource;
use App\Http\Resources\CouponRedemptionStatusResource;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
     * @param  \App\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function show(Coupon $coupon)
    {

        if (Auth::user()->hasPermissionTo('see internal data')) {
            return new CouponResource($coupon);
        }

        $restricted_partner = Partner::where('id', $coupon->restrict_partner_id)->first();
        $redemption_partner = Partner::where('id', $coupon->redemption_partner_id)->first();

        if (($restricted_partner !== null and Auth::user()->isAUserOfPartner($restricted_partner)) or
            ($redemption_partner !== null and Auth::user()->isAUserOfPartner($redemption_partner))) {
            return new CouponResource($coupon);
        }

        return response()->json([__('Generic.permission_denied')], 403);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function edit(Coupon $coupon)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Coupon $coupon)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function destroy(Coupon $coupon)
    {
        //
    }

    /**
     * Return a list of all coupons available to, and redeemed by, a consumer.
     *
     * @param \App\Consumer  $consumer
     * @return \Illuminate\Http\Response
     */
    public function consumerCoupons(Consumer $consumer)
    {
        $coupons = Coupon::where('restrict_consumer_id', $consumer->id)
                         ->orWhere('redeemed_by_consumer_id', $consumer->id)
                         ->with('voucher')
                         ->with('referrer')
                         ->with('redemptionPartner')
                         ->with('restrictPartner')
                         ->orderBy('updated_at', 'desc')
                         ->get();
        return new CouponsResource($coupons);
    }

    public function cancel(Coupon $coupon)
    {
        $coupon->cancelled_at = now();
        $coupon->cancelled = 1;
        $coupon->status = 'CANCELLED ' . date('d/m/Y');
        $coupon->save();
        return new CouponResource($coupon);
    }

    public function checkValidity(Coupon $coupon)
    {
        if (!$coupon->isValid()) {
            return response()->json([$coupon->last_error], 409);
        }

        if ($coupon->restrict_partner_id) {
            $partner = \App\Partner::where('id', $coupon->restrict_partner_id)->first();
            return response()->json(
                [
                    'partner' => [
                        'uuid' => $partner->uuid,
                        'name' => $partner->public_name,
                    ],
                ],
                200
            );
        }

        if (count($partner_groups = $coupon->voucher->partnerGroupRestrictions()->get())) {
            return response()->json(
                [
                    'partner_groups' => $partner_groups,
                ],
                200
            );
        }

        return response()->json(__('CouponController.no_partner_restrictions'), 200);
    }

    public function redeem(Coupon $coupon, Partner $partner)
    {
        if (!$coupon->isValid()) {
            return response()->json([$coupon->last_error], 409);
        }

        if ($coupon->restrict_partner_id and
            $coupon->restrict_partner_id !== $partner->id) {
            return response()->json([__('CouponController.not_valid_for_partner')], 409);
        } elseif (count($partner_groups = $coupon->voucher->partnerGroupRestrictions()->get())) {
            $partner_is_valid = false;

            foreach ($partner_groups as $partner_group) {
                if ($partner_group->partners()->where('uuid', $partner->uuid)->count()) {
                    $partner_is_valid = true;
                    break;
                }
            }

            if (!$partner_is_valid) {
                return response()->json([__('CouponController.not_valid_for_partners_groups')], 409);
            }
        }

        $coupon->redemption_partner_id = $partner->id;
        $coupon->redeemed_datetime = now();
        $coupon->status = 'Redeemed, not yet sent to Valassis';
        $coupon->save();

        return new CouponRedemptionStatusResource($coupon);
    }
}
