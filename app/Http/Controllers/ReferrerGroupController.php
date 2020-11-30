<?php

namespace App\Http\Controllers;

use DB;
use App\Referrer;
use App\ReferrerGroup;
use Illuminate\Http\Request;
use App\Http\Resources\ReferrerGroupResource;
use App\Http\Resources\ReferrerGroupsResource;
use App\Http\Resources\ReferrerGroupMemberResource;
use App\Http\Resources\ReferrerGroupMembersResource;

class ReferrerGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return new ReferrerGroupsResource(ReferrerGroup::all());
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
                'name' => 'required|max:100|unique:referrer_groups,group_name',
            ]
        );

        DB::beginTransaction();

        try {
            $group = ReferrerGroup::create(
                [
                    'group_name' => $request->name,
                ]
            );

            activity('referrer group actions')
                ->on($group)
                ->tap('setLogLabel', 'create new referrer group')
                ->log('New Referrer Group Added');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new ReferrerGroupResource($group);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ReferrerGroup  $referrerGroup
     * @return \Illuminate\Http\Response
     */
    public function show(ReferrerGroup $referrerGroup)
    {
        return new ReferrerGroupResource($referrerGroup);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\ReferrerGroup  $referrerGroup
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ReferrerGroup $referrerGroup)
    {
        $request->validate(
            [
                'name' => 'required|max:100|unique:referrer_groups,group_name,'.$referrerGroup->id,
            ]
        );

        DB::beginTransaction();

        try {
            $referrerGroup->update(
                [
                    'group_name' => $request->name,
                ]
            );

            activity('referrer group actions')
                ->on($referrerGroup)
                ->tap('setLogLabel', 'update referrer group')
                ->log('Referrer Group updated');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new ReferrerGroupResource($referrerGroup);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\ReferrerGroup  $referrerGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(ReferrerGroup $referrerGroup)
    {
        DB::beginTransaction();

        try {
            $referrerGroup->referrers()->detach();
            $referrerGroup->voucherRestrictions()->detach();
            $referrerGroup->delete();

            activity('referrer group actions')
                ->on($referrerGroup)
                ->tap('setLogLabel', 'remove referrer group')
                ->log('Referrer Group removed');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json([__('Generic.ok')], 204);
    }

    public function addReferrer(ReferrerGroup $referrer_group, Referrer $referrer)
    {
        if ($referrer_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if ($referrer_group->referrers()->where('referrer_id', $referrer->id)->exists()) {
            return response()->json([__('GroupController.uuid_already_member', ['uuid' => $referrer->uuid])], 422);
        }

        DB::beginTransaction();

        try {
            $referrer_group->referrers()->attach($referrer);

            activity('referrer group actions')
                ->on($referrer)
                ->tap('setLogLabel', 'add referrer to group')
                ->withProperties(
                    [
                        'group_id' => $referrer_group->id,
                        'group_name' => $referrer_group->group_name,
                    ]
                )
                ->log('Referrer added to group');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(['OK'], 200);
    }

    public function addMultipleReferrers(Request $request, ReferrerGroup $referrer_group)
    {
        if ($referrer_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (count($request->input('referrer_uuids', array())) === 0) {
            return response()->json([__('Generic.no_uuids')], 422);
        }

        $abortOnNotFoundReferrerFlag = $request->input('abortOnReferrerNotFound', 0);
        $abortOnReferrerAlreadyMemberFlag= $request->input('abortOnReferrerAlreadyMember', 0);

        $added_uuids = array();
        $not_found_uuids = array();
        $existing_member_uuids = array();

        DB::beginTransaction();

        try {
            foreach ($request->input('referrer_uuids', array()) as $referrer_uuid) {
                $referrer = Referrer::where('uuid', '=', $referrer_uuid)->first();

                if ($referrer === null) {
                    if ($abortOnNotFoundReferrerFlag) {
                        DB::rollBack();
                        return response()->json([__('Generic.uuid_not_found', ['uid' => $referrer_uuid])], 422);
                    }
                    $not_found_uuids[] = $referrer_uuid;
                    continue;
                } elseif ($referrer_group->referrers()->where('referrer_id', $referrer->id)->exists()) {
                    if ($abortOnReferrerAlreadyMemberFlag) {
                        DB::rollback();
                        return response()->json(
                            [
                                __('GroupController.uuid_already_member', ['uuid' => $referrer_uuid])
                            ],
                            422
                        );
                    }
                    $existing_member_uuids[] = $referrer_uuid;
                    continue;
                }

                $added_uuids[] = $referrer_uuid;
                $referrer_group->referrers()->attach($referrer);
                activity('referrer group actions')
                    ->on($referrer)
                    ->tap('setLogLabel', 'add referrer to group')
                    ->withProperties(
                        [
                            'group_id' => $referrer_group->id,
                            'group_name' => $referrer_group->group_name,
                        ]
                    )
                    ->log('Referrer added to group');
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

    public function removeReferrer(ReferrerGroup $referrer_group, Referrer $referrer)
    {
        if ($referrer_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (!$referrer_group->referrers()->where('referrer_id', $referrer->id)->exists()) {
            return response()->json([__('GroupController.uuid_not_member', ['uuid' => $referrer->uuid])], 422);
        }

        DB::beginTransaction();

        try {
            $referrer_group->referrers()->detach($referrer);

            activity('referrer group actions')
                ->on($referrer)
                ->tap('setLogLabel', 'remove referrer from group')
                ->withProperties(
                    [
                        'group_id' => $referrer_group->id,
                        'group_name' => $referrer_group->group_name,
                    ]
                )
                ->log('Referrer removed from group');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return response()->json(['OK'], 204);
    }

    public function removeMultipleReferrers(Request $request, ReferrerGroup $referrer_group)
    {
        if ($referrer_group->managed_remotely) {
            return response()->json([__('GroupController.managed_remotely')], 422);
        }

        if (count($request->input('referrer_uuids', array())) === 0) {
            return response()->json([__('Generic.no_uuids')], 422);
        }

        $abortOnNotFoundReferrerFlag = $request->input('abortOnReferrerNotFound', 0);
        $abortOnReferrerNotMemberFlag = $request->input('abortOnReferrerNotMember', 0);

        $removed_uuids = array();
        $not_found_uuids = array();
        $not_member_uuids= array();

        DB::beginTransaction();

        try {
            foreach ($request->input('referrer_uuids', array()) as $referrer_uuid) {
                $referrer = Referrer::where('uuid', '=', $referrer_uuid)->first();

                if ($referrer === null) {
                    if ($abortOnNotFoundReferrerFlag) {
                        DB::rollback();
                        return response()->json([__('Generic.uuid_not_found', ['uuid' => $referrer_uuid])], 422);
                    }
                    $not_found_uuids[] = $referrer_uuid;
                    continue;
                } elseif (!$referrer_group->referrers()->where('referrer_id', $referrer->id)->exists()) {
                    if ($abortOnReferrerNotMemberFlag) {
                        DB::rollback();
                        return response()->json(
                            [
                                __('GroupController.uuid_not_member', ['uuid' => $referrer_uuid])
                            ],
                            422
                        );
                    }
                    $not_member_uuids[] = $referrer_uuid;
                    continue;
                }

                $removed_uuids[] = $referrer_uuid;
                $referrer_group->referrers()->detach($referrer);
                activity('referrer group actions')
                    ->on($referrer)
                    ->tap('setLogLabel', 'remove referrer from group')
                    ->withProperties(
                        [
                            'group_id' => $referrer_group->id,
                            'group_name' => $referrer_group->group_name,
                        ]
                    )
                    ->log('Referrer removed from group');
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

    public function getGroupMembers(ReferrerGroup $referrer_group)
    {
        return new ReferrerGroupMembersResource($referrer_group->referrers()->get());
    }
}
