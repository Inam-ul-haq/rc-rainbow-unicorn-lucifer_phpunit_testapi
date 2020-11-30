<?php

namespace App\Http\Controllers;

use DB;
use App\Partner;
use App\PartnerGroup;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\PartnerGroupResource;
use App\Http\Resources\PartnerGroupsResource;
use App\Http\Resources\PartnerGroupMemberResource;
use App\Http\Resources\PartnerGroupMembersResource;

class PartnerGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new PartnerGroupsResource(PartnerGroup::all());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate(
            [
                'ref' => 'max:100|unique:partner_groups,group_ref',
                'name' => 'required|max:100|unique:partner_groups,group_name',
            ]
        );

        DB::beginTransaction();

        try {
            $group = PartnerGroup::create(
                [
                    'group_ref' => $request->ref ? $request->ref : Str::kebab($request->name),
                    'group_name' => $request->name,
                ]
            );

            activity('partner group actions')
                ->on($group)
                ->tap('setLogLabel', 'create new partner group')
                ->log('New Partner Group Added');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new PartnerGroupResource($group);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\PartnerGroup  $partnerGroup
     * @return \Illuminate\Http\Response
     */
    public function show(PartnerGroup $partnerGroup)
    {
        return new PartnerGroupResource($partnerGroup);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\PartnerGroup  $partnerGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PartnerGroup $partnerGroup)
    {
        $request->validate(
            [
                'name' => 'required|max:100|unique:partner_groups,group_name,' . $partnerGroup->id,
                'ref' => 'max:100|unique:partner_groups,group_ref',
            ]
        );

        DB::beginTransaction();

        try {
            $partnerGroup->update(
                [
                    'group_ref' => $request->ref ?? Str::kebab($request->name),
                    'group_name' => $request->name,
                ]
            );

            activity('partner group actions')
                ->on($partnerGroup)
                ->tap('setLogLabel', 'update partner group')
                ->log('Partner Group updated');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new PartnerGroupResource($partnerGroup);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\PartnerGroup  $partnerGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(PartnerGroup $partnerGroup)
    {
        DB::beginTransaction();

        try {
            $partnerGroup->partners()->detach();
            $partnerGroup->voucherRestrictions()->detach();
            $partnerGroup->delete();

            activity('partner group actions')
                ->on($partnerGroup)
                ->tap('setLogLabel', 'remove partner group')
                ->log('Partner Group removed');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json([__('Generic.OK')], 204);
    }

    public function addPartner(PartnerGroup $partner_group, Partner $partner)
    {
        if ($partner_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if ($partner_group->partners()->where('partner_id', $partner->id)->exists()) {
            return response()->json([__('GroupController.uuid_already_member', ['uuid' => $partner->uuid])], 422);
        }

        DB::beginTransaction();

        try {
            $partner_group->partners()->attach($partner);

            activity('partner group actions')
                ->on($partner)
                ->tap('setLogLabel', 'add partner to group')
                ->withProperties(
                    [
                        'group_id' => $partner_group->id,
                        'group_name' => $partner_group->group_name,
                    ]
                )
                ->log('Partner added to group');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json([__('Generic.ok')], 200);
    }

    public function addMultiplePartners(Request $request, PartnerGroup $partner_group)
    {
        if ($partner_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (count($request->input('partner_uuids', array())) === 0) {
            return response()->json([__('Generic.no_uuids')], 422);
        }

        $abortOnNotFoundPartnerFlag = $request->input('abortOnPartnerNotFound', 0);
        $abortOnPartnerAlreadyMemberFlag= $request->input('abortOnPartnerAlreadyMember', 0);

        $added_uuids = array();
        $not_found_uuids = array();
        $existing_member_uuids = array();

        DB::beginTransaction();

        try {
            foreach ($request->input('partner_uuids', array()) as $partner_uuid) {
                $partner = Partner::where('uuid', '=', $partner_uuid)->first();

                if ($partner === null) {
                    if ($abortOnNotFoundPartnerFlag) {
                        DB::rollBack();
                        return response()->json([__('Generic.uuid_not_found', ['uuid' => $partner_uuid])], 422);
                    }
                    $not_found_uuids[] = $partner_uuid;
                    continue;
                } elseif ($partner_group->partners()->where('partner_id', $partner->id)->exists()) {
                    if ($abortOnPartnerAlreadyMemberFlag) {
                        DB::rollback();
                        return response()->json(
                            [
                                __('GroupController.uuid_already_member', ['uuid' => $partner_uuid])
                            ],
                            422
                        );
                    }
                    $existing_member_uuids[] = $partner_uuid;
                    continue;
                }

                $added_uuids[] = $partner_uuid;
                $partner_group->partners()->attach($partner);
                activity('partner group actions')
                    ->on($partner)
                    ->tap('setLogLabel', 'add partner to group')
                    ->withProperties(
                        [
                            'group_id' => $partner_group->id,
                            'group_name' => $partner_group->group_name,
                        ]
                    )
                    ->log('Partner added to group');
            }
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(
            [
                'added_uuids' => $added_uuids,
                'existing_uuids' => $existing_member_uuids,
                'notfound_uuids' => $not_found_uuids,
            ],
            200
        );
    }

    public function removePartner(PartnerGroup $partner_group, Partner $partner)
    {
        if ($partner_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (!$partner_group->partners()->where('partner_id', $partner->id)->exists()) {
            return response()->json([__('GroupController.uuid_not_member', ['uuid' => $partner->uuid])], 422);
        }

        DB::beginTransaction();

        try {
            $partner_group->partners()->detach($partner);

            activity('partner group actions')
                ->on($partner)
                ->tap('setLogLabel', 'remove partner from group')
                ->withProperties(
                    [
                        'group_id' => $partner_group->id,
                        'group_name' => $partner_group->group_name,
                    ]
                )
                ->log('Partner removed from group');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json([__('Generic.ok')], 204);
    }

    public function removeMultiplePartners(Request $request, PartnerGroup $partner_group)
    {
        if ($partner_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (count($request->input('partner_uuids', array())) === 0) {
            return response()->json([__('Generic.no_uuids')], 422);
        }

        $abortOnNotFoundPartnerFlag = $request->input('abortOnPartnerNotFound', 0);
        $abortOnPartnerNotMemberFlag = $request->input('abortOnPartnerNotMember', 0);

        $removed_uuids = array();
        $not_found_uuids = array();
        $not_member_uuids= array();

        DB::beginTransaction();

        try {
            foreach ($request->input('partner_uuids', array()) as $partner_uuid) {
                $partner = Partner::where('uuid', '=', $partner_uuid)->first();

                if ($partner === null) {
                    if ($abortOnNotFoundPartnerFlag) {
                        DB::rollback();
                        return response()->json([__('Generic.uuid_not_found', ['uuid' => $partner_uuid])], 422);
                    }
                    $not_found_uuids[] = $partner_uuid;
                    continue;
                } elseif (!$partner_group->partners()->where('partner_id', $partner->id)->exists()) {
                    if ($abortOnPartnerNotMemberFlag) {
                        DB::rollback();
                        return response()->json(
                            [
                                __('GroupController.uuid_not_member', ['uuid' => $partner->uuid])
                            ],
                            422
                        );
                    }
                    $not_member_uuids[] = $partner_uuid;
                    continue;
                }

                $removed_uuids[] = $partner_uuid;
                $partner_group->partners()->detach($partner);
                activity('partner group actions')
                    ->on($partner)
                    ->tap('setLogLabel', 'remove partner from group')
                    ->withProperties(
                        [
                            'group_id' => $partner_group->id,
                            'group_name' => $partner_group->group_name,
                        ]
                    )
                    ->log('Partner removed from group');
            }
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(
            [
                'removed_uuids' => $removed_uuids,
                'notfound_uuids' => $not_found_uuids,
                'notmember_uuids' => $not_member_uuids,
            ],
            200
        );
    }

    public function getGroupMembers(PartnerGroup $partner_group)
    {
        return new PartnerGroupMembersResource($partner_group->partners()->get());
    }
}
