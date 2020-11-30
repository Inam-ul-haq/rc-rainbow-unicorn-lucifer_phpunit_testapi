<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\Tag;
use App\Pet;
use App\Partner;
use App\Voucher;
use App\Consumer;
use League\CSV\Writer;
use SplTempFileObject;
use App\Helpers\Helper;
use Illuminate\Support\Arr;
use App\JobNotification;
use App\Jobs\ExportComplete;
use Illuminate\Http\Request;
use App\Exports\ConsumersExport;
use Illuminate\Validation\Rule;
use App\Http\Resources\TagsResource;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use App\Http\Resources\ConsumerResource;
use App\Http\Resources\PartnersResource;
use App\Helpers\NotifyPersonalDataExport;
use App\Http\Resources\ConsumersResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\LogActivityResource;
use App\Http\Resources\LogActivitiesResource;
use Illuminate\Support\Facades\Schema as Schema;

class ConsumerController extends Controller
{
    private $search_limit = 1000;
    private $default_per_page = 10;
    private $csvDiskName = 'csv_exports';

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
     * @param  \App\Consumer  $consumer
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Consumer $consumer)
    {
        foreach ($request->input('relations', array()) as $relation) {
            if (in_array($relation, $consumer->allowedApiRelations)) {
                $consumer = $consumer->load($relation);
            }
        }

        return new ConsumerResource($consumer);
    }

    /**
     * Get consumer resource by email.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Consumer  $consumer
     * @return \Illuminate\Http\Response
     */
    public function getByEmail(Request $request, Consumer $consumer)
    {
        foreach ($request->input('relations', array()) as $relation) {
            if (in_array($relation, $consumer->allowedApiRelations)) {
                $consumer = $consumer->load($relation);
            }
        }

        return new ConsumerResource($consumer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Consumer  $consumer
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Consumer $consumer)
    {
        /**
         * At time of writing, the only thing that staff can change on a consumer profile is email, so if that's
         * not changing, then there's nothing to do. This'll change when they change their mind about that, or
         * when we add in the functionality for users to update profiles. For now though...
         */

        if ($request->input('email') == $consumer->email) {
            return response()->json(['Unprocessable Entity'], 422);
        }

        $rules = ['email'=> 'required|email|unique:consumers,email'];
        $messages = ['email.unique' => __('ConsumerController.email_in_use')];
        $request->validate($rules, $messages);

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'update consumer account')
            ->log('Account email address updated');

        $consumer->email = $request->input('email');
        $consumer->save();
        return new ConsumerResource($consumer);
    }

    /**
     * Add a tag/campaign to a consumer account, based on the email address.
     * If an account with that email address does not exist, create one.
     * Don't allow the same tag to be added to the same account more than once.
     */
    public function addToCampaign(Request $request)
    {
        $request->validate(
            [
                'email' => 'required|email',
                'campaign_tag' => 'required|max:40|exists:tags,tag',
            ]
        );

        $source = Helper::getCurrentSource();

        $consumer = Consumer::where('email', '=', $request->email)->first();
        if ($consumer === null) {
            $consumer = new Consumer();
            $consumer->email = $request->email;
            $consumer->source = $source;
            $consumer->save();
        }

        // Don't allow the tag to be added twice
        if ($consumer->tags()->where('tag', '=', $request->campaign_tag)->count() === 0) {
            $consumer->tags()->attach(Tag::where('tag', '=', $request->campaign_tag)->first());
        }
        return ['uuid' => $consumer->uuid];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Consumer  $consumer
     * @return \Illuminate\Http\Response
     */
    public function destroy(Consumer $consumer)
    {
        /**
         * Since this model uses soft deletes to maintain database integrity, and people have the right to be
         * forgotten etc., we redact their consumer entry before calling delete().
         */

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'delete consumer account')
            ->log('Account Deleted');

        $consumer->first_name = 'Deleted';
        $consumer->last_name = 'Deleted';
        $consumer->address_line_1 = null;
        $consumer->town = null;
        $consumer->county = null;
        $consumer->postcode = null;
        $consumer->email = 'deleted@deleted';
        $consumer->telephone = null;
        $consumer->delete();

        return response()->json([__('Generic.ok')], 200);
    }

    public function activate(Consumer $consumer)
    {
        if ($consumer->active === 1) {
            return response()->json([__('ConsumerController.already_active')], 422);
        }

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'activate consumer account')
            ->log('Account Activated');

        $consumer->active = 1;
        $consumer->deactivated_at = null;
        $consumer->save();
        $consumer->refresh();

        return new ConsumerResource($consumer);
    }

    public function deactivate(Consumer $consumer)
    {
        if ($consumer->active === 0) {
            return response()->json(['User Not Active'], 422);
        }

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'deactivate consumer account')
            ->log('Account Deactivated');

        $consumer->active = 0;
        $consumer->deactivated_at = now();
        $consumer->save();
        $consumer->refresh();

        return new ConsumerResource($consumer);
    }

    public function addToBlacklist(Consumer $consumer)
    {
        if ($consumer->blacklisted === 1) {
            return response()->json([__('ConsumerController.already_on_blacklist')], 422);
        }

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'blacklist consumer account')
            ->log('Account Blacklisted');

        $consumer->blacklisted = 1;
        $consumer->blacklisted_at = now();
        $consumer->save();
        $consumer->refresh();

        return new ConsumerResource($consumer);
    }

    public function removeFromBlacklist(Consumer $consumer)
    {
        if ($consumer->blacklisted === 0) {
            return response()->json([__('ConsumerController.not_on_blacklist')], 422);
        }

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'remove from blacklist')
            ->log('Account removed from blacklist');

        $consumer->blacklisted = 0;
        $consumer->blacklisted_at = null;
        $consumer->save();
        $consumer->refresh();

        return new ConsumerResource($consumer);
    }

    public function search(Request $request)
    {
        $consumers = $request->input('search') ?
                        Consumer::search($request->input('search')) :
                        new Consumer;

        if (is_numeric($request->input('active'))) {
            $consumers = $consumers->where('active', $request->input('active'));
        }

        if (is_numeric($request->input('blacklist'))) {
            $consumers = $consumers->where('blacklisted', $request->input('blacklist'));
        }

        // We use search_limit+1 here, as this allows us to detect if there are more results available than the limit,
        // and return a 413 if that's the case so the user can be prompted to be more specific in their search.
        $limit = $this->search_limit + 1;
        $consumers = $consumers->take($limit)->get();

        if (count($consumers) == $limit) {
            return response()->json([__('Generic.payload_too_large')], 413);
        }

        return new ConsumersResource($consumers);
    }

    public function list(Request $request)
    {
        $rules = [
            'active' => Rule::in([0,1]),
            'blacklist' => Rule::in([0,1]),
            'source'  => 'max:100',
            'order' => Rule::in(['asc','desc']),
        ];
        $messages = [
            'source.max' => __('ConsumerController.validation_sourcemax', ['maxlength' => 100]),
            'order.in' => __('ConsumerController.validation_order_in'),
            'active.in' => __('ConsumerController.validation_active_in'),
            'blacklist.in' => ('ConsumerController.validation_blacklist_in'),
        ];

        $request->validate($rules, $messages);

        $consumers = new Consumer;

        if (is_numeric($request->input('active'))) {
            $consumers = $consumers->where('active', $request->input('active'));
        }

        if (is_numeric($request->input('blacklist'))) {
            $consumers = $consumers->where('blacklisted', $request->input('blacklist'));
        }

        if ($request->input('source', null)) {
            $consumers = $consumers->where('source', 'like', $request->input('source'));
        }

        if ($request->input('orderby', null)) {
            if (Schema::hasColumn('consumers', $request->input('orderby'))) {
                $consumers = $consumers->orderBy($request->input('orderby'), $request->input('order', 'asc'));
            }
        }

        return new ConsumersResource($consumers->simplePaginate($request->input('per_page', $this->default_per_page)));
    }

    public function searchCSV(Request $request)
    {
        activity('consumer actions')
            ->withProperties([
                'search' => $request->input('search'),
                'active' => $request->input('active'),
                'blacklist'=>$request->input('blacklist'),
            ])
            ->tap('setLogLabel', 'csv export request')
            ->log('Consumer data CSV export');

        $export = new ConsumersExport(
            $request->input('search'),
            $request->input('active'),
            $request->input('blacklist')
        );
        $filename = Auth::user()->id . '-' . date('YmdGis') . '-' . 'consumers.csv';

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

    public function updateTags(Request $request, Consumer $consumer)
    {
        activity('consumer actions')
            ->on($consumer)
            ->withProperties(['tags' => $request->input('tags')])
            ->tap('setLogLabel', 'update user tags')
            ->log('Update user tags');

        $consumer->tags()
                 ->sync(Tag::whereIn('uuid', $request->input('tags'))->get()->pluck('id'));
        return new TagsResource($consumer->tags);
    }

    public function getActivity(Consumer $consumer, Request $request)
    {
        return new LogActivitiesResource(
            Activity::when(!$request->input('includeModelEvents'), function ($query) {
                $query->whereNotIn('description', ['created','updated','deleted']);
            })
            ->whereRaw(
                '( (subject_id=? and subject_type=?) or (causer_id=? and causer_type=?) )',
                [$consumer->id,'App\Consumer',$consumer->id,'App\Consumer']
            )
            ->get()
        );
    }

    public function exportPersonalData(Consumer $consumer)
    {
        $notification = new JobNotification();
        $notification->type = 'user_data_export';
        $notification->user_id = Auth::user()->id;
        $notification->status = 'requested';
        $notification->download_limit = 1;
        $notification->save();

        activity('consumer actions')
            ->on($consumer)
            ->tap('setLogLabel', 'export user data request')
            ->withProperties([
                'job_id' => $notification->id,
            ])
            ->log('Consumer data export requested');

        dispatch(new NotifyPersonalDataExport($consumer, $notification));

        return $notification;
    }

    public function getAssociatedPartners(Consumer $consumer)
    {
        $partner_ids = array_unique(
            Arr::collapse(
                [
                    $consumer->restrictedCoupons->pluck('restrict_partner_id'),
                    $consumer->restrictedCoupons->pluck('redemption_partner_id'),
                    $consumer->redeemedCoupons->pluck('restrict_partner_id'),
                    $consumer->redeemedCoupons->pluck('redemption_partner_id'),
                ]
            )
        );

        return new PartnersResource(Partner::whereIn('id', $partner_ids)->get());
    }

    public function vouchersAvailableForIssue(Consumer $consumer)
    {
        return $consumer->validVouchers();
    }

    private function getCsvDiskName()
    {
        return $this->csvDiskName;
    }
}
