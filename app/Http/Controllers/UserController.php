<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\User;
use App\Partner;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\UserResource;
use App\Http\Resources\UsersResource;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountApplicationRefused;
use App\Mail\AccountApplicationApproved;
use Illuminate\Support\Facades\Schema as Schema;

class UserController extends Controller
{
    private $search_limit = 1000;
    private $default_per_page = 10;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (! Auth::user()->hasPermissionTo('admin users')) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        $rules = [
            'order' => Rule::in(['asc', 'desc']),
        ];
        $messages = [
            'order.in' => 'Invalid order set.',
        ];

        $request->validate($rules, $messages);

        $users = new User;

        if ($request->input('orderby', null)) {
            if (Schema::hasColumn('users', $request->input('orderby'))) {
                $users = $users->orderBy(
                    $request->input('orderby'),
                    $request->input('order', 'asc')
                );
            }
        }
        return new UsersResource(
            $users->with('nameTitle')
            ->paginate($request->input('per_page', 10))
        );
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Auth::user()->hasPermissionTo('admin users')) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        $request->validate([
            'email' => 'required|email|unique:users,email|max:255',
            'password'  => 'required',
            'name' => 'required|max:100',
            'name_title_id' => 'required|exists:name_titles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'name_title_id' => $request->name_title_id,
            'password_change_needed' => 1,
        ]);

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @param  App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function show(User $user)
    {
        if (Auth::user()->hasPermissionTo('admin users') or
           (Auth::user()->hasRole('partner user') and
           Auth::user()->isAPartnerManagerOfUser($user))) {
            return new UserResource($user);
        }

        return response()->json([__('Generic.permission_denied')], 403);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, User $user)
    {
        $current_user = Auth::user();

        // users with 'admin users' can change most things
        if ($current_user->hasPermissionTo('admin users')) {
            $user->update(
                array_merge(
                    $request->only(
                        [
                            'name',
                            'email',
                            'name_title_id',
                            'password',
                            'blocked',
                        ]
                    ),
                    $request->password ? ['password_change_needed' => 1] : []
                )
            );
            return new UserResource($user);

        // partner managers can change some things for their users.
        } elseif ($user->hasRole('partner user') and
            Auth::user()->isAPartnerManagerOfUser($user)) {
            $user->update(
                array_merge(
                    $request->only(
                        [
                            'name',
                            'email',
                            'name_title_id',
                            'password',
                        ]
                    ),
                    $request->password ? ['password_change_needed' => 1] : []
                )
            );
            return new UserResource($user);

        // otherwise, if you're editing your own account, you're restricted to name, email, and password only.
        } elseif ($user->id === $current_user->id) {
            $user->update(
                array_merge(
                    $request->only(
                        [
                            'name',
                            'email',
                            'name_title_id',
                            'password',
                        ]
                    ),
                    $request->password ? ['password_change_needed' => 0] : []
                )
            );
            return new UserResource($user);
        }

        return response()->json([__('Generic.permission_denied')], 403);
    }

    /**
     * If the user is a partner user, detach them from the specified partner.
     * (admin users permission only) - if partner is not specifed, delete the user.
     * (admin users permission only) - if not a partner user, delete the user.
     *
     * Can't delete admin user, can't delete current user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user, Request $request)
    {
        if (!$user) {
            return response()->json([__('Generic.not_found')], 404);
        }

        if ($user->id === 1) {
            return response()->json([__('UserController.wont_delete_primary_user')], 403);
        }

        if ($user->id === Auth::user()->id) {
            return response()->json([__('UserController.wont_delete_current_user')], 403);
        }

        /**
         * Optional. If set, treat this as a request to remove a user from a partner, rather than to
         * remove the user entirely.
         */
        $partner = Partner::find($request->input('partner_id'));

        if (Auth::user()->hasPermissionTo('admin users')) {
            if ($partner) {
                $user->userPartners()->detach($partner);
                return response()->json([__('Generic.ok')], 200);
            }

            return User::destroy($user->id) ?
                response()->json(['OK'], 204) :
                response()->json([__('Generic.not_found')], 404);
        }

        if (!$partner) {
            return response()->json([__('Generic.type_not_found', ['type' => 'Partner'])], 400);
        }

        if ($user->hasRole('partner user') and Auth::user()->isAPartnerManagerOfUserForPartner($partner, $user)) {
            $user->userPartners()->detach($partner->id);
            return response()->json([__('Generic.ok')], 200);
        }

        return response()->json([__('Generic.not_found')], 404);
    }

    public function permissions(User $user)
    {
        $this->authorize('permissions', $user);
        return $user->getAllPermissions();
    }

    public function rejectPartnerAccountApplication(User $user, Partner $partner, Request $request)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfPartner($partner)) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$user->isPendingApprovalForPartner($partner)) {
            return response()->json(['error' => __('UserController.ac_not_pending_partner_approval')], 401);
        }

        if ($request->input('reject_message', null)) {
            Mail::to($user->email)->queue(new AccountApplicationRefused($request->input('reject_message')));
        }

        activity('user actions')
            ->on($user)
            ->tap('setLogLabel', 'reject partner user account')
            ->log('Partner user account application rejected');

        $user->userPartners()->detach($partner);

        return response()->json(__('Generic.ok'), 200);
    }

    public function approvePartnerAccountApplication(User $user, Partner $partner, Request $request)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfPartner($partner)) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$user->isPendingApprovalForPartner($partner)) {
            return response()->json(['error' => __('UserController.ac_not_pending_partner_approval')], 401);
        }

        if ($request->input('accept_message', null)) {
            Mail::to($user->email)->queue(new AccountApplicationApproved($request->input('accept_message')));
        }

        activity('user actions')
            ->on($user)
            ->tap('setLogLabel', 'approve partner user acccount')
            ->log('Partner user account application approved');

        $user->userPartners()->updateExistingPivot($partner, [
            'approved' => 1,
            'approved_at' => now(),
        ]);

        return response()->json(__('Generic.ok'), 200);
    }

    public function makePartnerManager(User $user, Partner $partner)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfUserForPartner($partner, $user)) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if ($user->isAPartnerManagerOfPartner($partner)) {
            return response()->json(__('UserController.already_manager'), 409);
        }

        if (! $user->isAUserOfPartner($partner)) {
            return response()->json(__('UserController.not_user_of_partner'), 409);
        }

        if (!$user->hasRole('partner user')) {
            return response()->json(__('UserController.not_partner_user'), 409);
        }

        activity('user actions')
            ->on($user)
            ->tap('setLogLabel', 'partner user promotion')
            ->log('Partner user account promoted to manager');

        $user->userPartners()->updateExistingPivot($partner, ['manager' => 1]);

        return response()->json(__('Generic.ok'), 200);
    }

    public function removePartnerManager(User $user, Partner $partner)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfUserForPartner($partner, $user)) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!Auth::user()->isAUserOfPartner($partner)) {
            return response()->json([__('UserController.not_user_of_partner')], 409);
        }

        if (!$user->isAPartnerManagerOfPartner($partner)) {
            return response()->json(__('UserController.not_partner_manager_of_partner'), 409);
        }

        if ($partner->partnerManagers()->count() === 1) {
            return response()->json(__('UserController.wont_demote_last_manager'), 409);
        }

        activity('user actions')
            ->on($user)
            ->tap('setLogLabel', 'partner manager demotion')
            ->log('Partner manager account demoted to user');

        $user->userPartners()->updateExistingPivot($partner, ['manager' => 0]);

        return response()->json(__('Generic.ok'), 200);
    }

    public function removeAccountFromPartner(User $user, Partner $partner)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            !Auth::user()->isAPartnerManagerOfUserForPartner($partner, $user)) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!Auth::user()->isAUserOfPartner($partner)) {
            return response()->json([__('UserController.not_user_of_partner')], 409);
        }

        if ($user->isAPartnerManagerOfPartner($partner) and
            $partner->partnerManagers()->count() === 1) {
            return response()->json(__('UserController.wont_demote_last_manager'), 409);
        }

        activity('user actions')
            ->on($user)
            ->tap('setLogLabel', 'account removed from partner')
            ->log('Partner account removed from partner');

        $partner->partnerUsers()->detach($user->id);

        return response()->json(__('Generic.ok'), 200);
    }

    public function getPartnerAccounts(User $user)
    {
        if (!Auth::user()->hasPermissionTo('admin users') and
            Auth::user()->id != $user->id) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        return $user->userPartners()
                    ->select(['uuid','public_name','manager'])
                    ->get()
                    ->makeHidden('pivot');
    }

    public function block(User $user)
    {
        $user->blocked = 1;
        $user->blocked_at = now();
        $user->save();
        return response()->json(__('Generic.ok'), 200);
    }

    public function unblock(User $user)
    {
        $user->blocked = 0;
        $user->blocked_at = null;
        $user->save();
        return response()->json(__('Generic.ok'), 200);
    }

    public function search(Request $request)
    {
        $request->validate(
            [
                'include_blocked' => 'boolean',
            ]
        );

        $users = $request->input('search') ?
                    User::search($request->input('search')) :
                    new User;

        $include_blocked = $request->input('blocked', null);
        if ($include_blocked !== null) {
            $users->where('blocked', $request->input('blocked'));
        }

        $limit = $this->search_limit + 1;
        $users = $users->take($limit)->get();

        if (count($users) == $limit) {
            return response()->json([__('Generic.payload_too_large')], 413);
        }

        return new UsersResource($users);
    }
}
