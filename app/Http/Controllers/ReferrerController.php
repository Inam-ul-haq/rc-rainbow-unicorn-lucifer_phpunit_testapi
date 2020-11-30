<?php

namespace App\Http\Controllers;

use Auth;
use App\Referrer;
use App\JobNotification;
use App\Jobs\ExportComplete;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Exports\ReferrersExport;
use App\Http\Resources\CouponsResource;
use App\Http\Resources\ReferrerResource;
use App\Http\Resources\ReferrersResource;
use App\Exports\ReferrerCouponRedemptionsExport;
use Illuminate\Support\Facades\Schema as Schema;

class ReferrerController extends Controller
{
    private $search_limit = 1000;
    private $default_per_page = 10;
    private $csvDiskName = 'csv_exports';

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
     * @param  \App\Referrer  $referrer
     * @return \Illuminate\Http\Response
     */
    public function show(Referrer $referrer)
    {
        return new ReferrerResource($referrer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Referrer  $referrer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Referrer $referrer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Referrer  $referrer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Referrer $referrer)
    {
        //
    }

    public function search(Request $request)
    {
        $referrers = $request->input('search') ?
                        Referrer::search($request->input('search')) :
                        new Referrer;

        if (is_numeric($request->input('blacklist'))) {
            $referrers = $referrers->where('blacklisted', $request->input('blacklist'));
        }

        // We use search_limit+1 here, as this allows us to detect if there are more results available than the limit,
        // and return a 413 if that's the case so the user can be prompted to be more specific in their search.
        $limit = $this->search_limit + 1;
        $referrers = $referrers->take($limit)->get();

        if (count($referrers) == $limit) {
            return response()->json([__('Generic.payload_too_large')], 413);
        }

        return new ReferrersResource($referrers);
    }

    public function searchCSV(Request $request)
    {
        activity('referrer actions')
            ->withProperties([
                'search' => $request->input('search'),
                'blacklist' => $request->input('blacklist', null),
            ])
            ->tap('setLogLabel', 'csv export request')
            ->log('Referrer data CSV export');

        $export = new ReferrersExport(
            $request->input('search').
            $request->input('blacklist', null)
        );
        $filename = Auth::user()->id . '-' . date('YmdGis') . '-' . 'referrers.csv';

        $notification = new JobNotification();
        $notification->type = 'csv_export';
        $notification->user_id = Auth::user()->id;
        $notification->disk = $this->getCsvDiskName();
        $notification->filename = $filename;
        $notification->download_limit = 1;
        $notification->status = 'requested';
        $notification->save();

        $export->queue($filename, $this->getCsvDiskName())->chain([
            new ExportComplete($notification),
        ]);
        return $notification;
    }

    public function list(Request $request)
    {
        $rules = [
            'blacklist' => Rule::in([0,1]),
            'order' => Rule::in(['asc','desc']),
        ];
        $messages = [
            'order.in' => __('Generic.invalid_order'),
            'blacklist.in' => 'Value should be 0 or 1',
        ];

        $request->validate($rules, $messages);

        $referrers = new Referrer;

        if (is_numeric($request->input('blacklist'))) {
            $referrers = $referrers->where('blacklisted', $request->input('blacklist'));
        }

        if ($request->input('orderby', null)) {
            if (Schema::hasColumn('referrers', $request->input('orderby'))) {
                $referrers= $referrers->orderBy($request->input('orderby'), $request->input('order', 'asc'));
            }
        }

        return new ReferrersResource($referrers->paginate($request->input('per_page', $this->default_per_page)));
    }

    private function getCsvDiskName()
    {
        return $this->csvDiskName;
    }

    public function couponRedemptions(Request $request, Referrer $referrer)
    {

        // Referrers can only view their own redemptions. RC staff (so 'see internal data') can view
        // redemptions for any referrer.
        if (! Auth::user()->hasPermissionTo('see internal data') and
            ! Auth::user()->isAUserOfReferrer($referrer)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        return new CouponsResource($referrer->couponRedemptions($request));
    }

    public function couponRedemptionsCsv(Request $request, Referrer $referrer)
    {
        if (! Auth::user()->hasPermissionTo('see internal data') and
            ! Auth::user()->isAUserOfReferrer($referrer)) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        activity('referrer actions')
            ->withProperties([
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ])
            ->on($referrer)
            ->tap('setLogLabel', 'csv export request')
            ->log('Referrer redemptions CSV export');

        $export = new ReferrerCouponRedemptionsExport(
            $referrer,
            $request->input('start_date'),
            $request->input('end_date')
        );
        $filename = $referrer->id . '-' . date('YmdGis') . '-' . 'voucher_redemptions.csv';

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
}
