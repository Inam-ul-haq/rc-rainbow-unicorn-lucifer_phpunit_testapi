<?php

namespace App\Http\Controllers;

use DB;
use App\Voucher;
use App\VoucherUniqueCode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VoucherUniqueCodeController extends Controller
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
     * @param  \App\VoucherUniqueCode  $voucherUniqueCode
     * @return \Illuminate\Http\Response
     */
    public function show(VoucherUniqueCode $voucherUniqueCode)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\VoucherUniqueCode  $voucherUniqueCode
     * @return \Illuminate\Http\Response
     */
    public function edit(VoucherUniqueCode $voucherUniqueCode)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\VoucherUniqueCode  $voucherUniqueCode
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, VoucherUniqueCode $voucherUniqueCode)
    {
        //
    }

    /**
     * Return the Unique Code Status.
     *
     * @param  \App\Voucher  $voucher
     * @param  \App\VoucherUniqueCode  $voucherUniqueCode
     * @return \Illuminate\Http\Response
     */
    public function checkStatus(Voucher $voucher, $code)
    {
        $voucherUniqueCode =
            VoucherUniqueCode
                ::where('voucher_id', $voucher->id)
                ->where('code', 'LIKE', $code)
                ->first();

        if (! $voucherUniqueCode) {
            return response()->json([__('VoucherUniqueCodeController.code_not_found')], 404);
        }

        if ($voucherUniqueCode->codeUsedOnCoupon()->get()->count()) {
            return response()->json([__('VoucherUniqueCodeController.code_already_used')], 409);
        }

        if ($voucher->published === 0) {
            return response()->json([__('VoucherUniqueCodeController.voucher_not_published')], 422);
        }

        $date = new Carbon();
        if ($date < $voucher->subscribe_from_date) {
            return response()->json([__('VoucherUniqueCodeController.voucher_start_date')], 422);
        }

        if ($voucher->subscribe_to_date and $date > $voucher->subscribe_to_date) {
            return response()->json([__('VoucherUniqueCodeController.voucher_end_date')], 422);
        }

        return response()->json([__('VoucherUniqueCodeController.code_valid')], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\VoucherUniqueCode  $voucherUniqueCode
     * @return \Illuminate\Http\Response
     */
    public function destroy(Voucher $voucher, string $uniqueCode)
    {
        $voucherUniqueCode =
            VoucherUniqueCode
                ::where('voucher_id', $voucher->id)
                ->where('code', 'LIKE', $uniqueCode)
                ->first();

        if (! $voucherUniqueCode) {
            return response()->json([__('VoucherUniqueCodeController.code_not_found')], 404);
        }

        if ($voucherUniqueCode->codeUsedOnCoupon()->get()->count()) {
            return response()->json([__('VoucherUniqueCodeController.wont_delete_already_used')], 409);
        }

        DB::beginTransaction();

        try {
            activity('unique code actions')
                ->on($voucherUniqueCode)
                ->tap('setLogLabel', 'delete unique code')
                ->withProperties(
                    [
                        'code' => $voucherUniqueCode->code,
                        'voucher_id' => $voucherUniqueCode->voucher_id,
                    ]
                )
                ->log('Unique code deleted');

            $voucherUniqueCode->delete();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(__('Generic.ok'), 204);
    }
}
