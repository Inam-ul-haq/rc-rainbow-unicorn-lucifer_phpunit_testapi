<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Passport\Token;
use Laravel\Passport\Passport;
use App\Http\Resources\PersonalAccessTokenResource;

class PersonalAccessTokenController extends Controller
{
    private $defaultExpireTime = '2047-09-14 03:28:00'; // http://www.religioustolerance.org/end_wrl21.htm

    public function index()
    {
        if (Auth::user()->hasPermissionTo('admin api keys')) {
            return PersonalAccessTokenResource::collection(\Laravel\Passport\Token::all());
        }

        return PersonalAccessTokenResource::collection(Auth::user()->tokens());
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'description' => 'required|max:180',
                'expires_at' => 'nullable|date|date_format:Y-m-d',
            ]
        );

        Passport::personalAccessTokensExpireIn(
            $request->expires_at === null ?
                new Carbon($this->defaultExpireTime) :
                Carbon::createFromFormat('Y-m-d', $request->expires_at)->endOfDay()
        );

        DB::beginTransaction();
        try {
            $token = Auth::user()->createToken($request->description);


            activity('personal access token actions')
                ->on(Auth::user())
                ->tap('setLogLabel', 'create token')
                ->log('New Personal Access token created');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new PersonalAccessTokenResource($token);
    }

    public function revoke(Token $token)
    {
        if (!Auth::user()->hasPermissionTo('admin api keys') and
            !Auth::user()->id === $token->user_id) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        if ($token->revoked) {
            return response()->json([__('PersonalAccessTokenController.already_revoked')], 422);
        }

        $token->revoke();
        return response()->json([__('Generic.OK')], 200);
    }

    public function update(Request $request, PersonalAccessToken $token)
    {
    }
}
