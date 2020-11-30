<?php

namespace App\Http\Controllers;

use Auth;
use JWTAuth;
use App\User;
use App\Partner;
use App\SystemVariable;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Resources\UserTokenResource;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class UserAuthController extends Controller
{
    use ResetsPasswords, SendsPasswordResetEmails {
        SendsPasswordResetEmails::broker insteadof ResetsPasswords;
        ResetsPasswords::credentials insteadof SendsPasswordResetEmails;
    }

    /**
     * /register routes to here. This is used for the anonymous signups, so partners. The role they're given is set in
     * UserPermissionsSeeder.php.
     * When an admin creates an account, they use a POST to /users which drops into the standard restful crud
     * UserController stuff.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|max:100',
            'email' => "required|email|unique:users,email|max:255",
            'password' => 'required|min:8',
            'partner_id' => 'required|exists:partners,crm_id',
            'access_answer' => 'required|max:255',
            'name_title_id' => 'required|exists:name_titles,id',
        ]);

        $partner = Partner::where('crm_id', $request->partner_id)->firstOrFail();
        if (! password_verify($request->access_answer, $partner->access_password)) {
            activity('user authentication')
                ->on($partner)
                ->withProperties(['email' => $request->email])
                ->tap('setLogLabel', 'invalid access answer')
                ->log('Invalid access password used for partner');

            return response()->json([
                'message' => __('UserAuthController.incorrect_access_password'),
                'errors' => [
                    'access_answer' => [
                        __('UserAuthController.incorrect_access_answer'),
                    ],
                ],
            ], 422);
        }

        $user = User::create([
            'email'                  => $request->email,
            'password'               => $request->password,
            'name'                   => $request->name,
            'email_verified_at'      => null,
            'password_change_needed' => 0,
            'blocked'                => false,
            'name_title_id'          => $request->name_title_id,
        ]);
        $user->assignRole(SystemVariable::where('variable_name', 'anonymous_signup_role')->first()->variable_value);
        $user->userPartners()->attach($partner->id);

        activity('user authentication')
            ->on($user)
            ->tap('setLogLabel', 'account registration')
            ->log('New account registered');

        return new UserResource($user);
    }

    public function login()
    {
        $credentials = request(['email', 'password']);

        $user = User::where('email', '=', $credentials['email'])->with('roles')->first();

        if ($user === null) {
            activity('user authentication')
                ->withProperties(['email' =>  $credentials['email']])
                ->tap('setLogLabel', 'login account not found')
                ->log('Log in failed - account not found');

            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if ($user->blocked) {
            activity('user authentication')
                ->on($user)
                ->tap('setLogLabel', 'login blocked')
                ->log('Login prevented - account blocked');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$user->canLogin()) {
            return response()->json(['error' => __('Ganeric.unavailable')], 503);
        }

        if ($user->hasRole('partner user') and
            !$user->isAnApprovedPartnerUser()) {
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (! $token = auth()->attempt($credentials)) {
            activity('user authentication')
                ->on($user)
                ->tap('setLogLabel', 'invalid login credentials')
                ->log('Log in failed - invalid credentials');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        activity('user authentication')
            ->on($user)
            ->tap('setLogLabel', 'user login')
            ->log('Logged in successfully');

        return $this->respondWithToken($token, $user);
    }

    public function logout()
    {
        if (auth()->getUser() !== null) {
            auth()->logout();
        }

        activity('user authentication')
            ->tap('setLogLabel', 'user logout')
            ->log('Logged out');

        return response()->json(['message' => __('UserAuthController.logged_out') ]);
    }

    public function refresh()
    {
        $token = auth()->refresh();
        $user=Auth::user();

        if ($user->blocked) {
            activity('user authentication')
                ->on($user)
                ->tap('setLogLabel', 'login blocked')
                ->log('Token refresh prevented - account blocked');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$user->canLogin()) {
            return response()->json(['error' => __('Generic.unavailable')], 503);
        }

        activity('user authentication')
            ->on($user)
            ->tap('setLogLabel', 'token refresh')
            ->log('Token refreshed');

        return $this->respondWithToken($token, $user);
    }

    public function forgotPw(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_url' => 'required',
        ]);

        activity('user authentication')
            ->withProperties(['email' => $request->email])
            ->tap('setLogLabel', 'password reset email')
            ->log('Password reset email requested');

        return $this->sendResetLinkEmail($request);
    }

    public function forgotPwReset(Request $request)
    {
        $request->merge(['password_confirmation' =>  $request->input('password')]);
        return $this->reset($request);
    }

    protected function resetPassword($user, $password)
    {
        $user->password = $password;
        $user->password_change_needed = 0;
        $user->save();
        activity('user authentication')
            ->on($user)
            ->tap('setLogLabel', 'password reset')
            ->log('Password reset');

        event(new PasswordReset($user));
    }

    protected function sendResetLinkResponse($response)
    {
        activity('user authentication')
            ->tap('setLogLabel', 'reset email sent')
            ->log('Password reset email sent');

        return response()->json(['message' => __('UserAuthController.pw_reset_email_sent')]);
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        activity('user authentication')
            ->tap('setLogLabel', 'reset email failed')
            ->log('Password reset email failed to send');

        return response()->json(['message' => __('UserAuthController.pw_reset_email_failed')], 422);
    }

    protected function sendResetResponse($response)
    {
        activity('user authentication')
            ->tap('setLogLabel', 'reset email sent')
            ->log('Password reset email sent');

        return response()->json(['message'=> __('UserAuthController.pw_reset_success')]);
    }

    protected function sendResetFailedResponse(Request $request, $response)
    {
        activity('user authentication')
            ->tap('setLogLabel', 'reset email failed')
            ->log('Password reset email failed to send');

        return response()->json(['message' => __('UserAuthController.invalid_token')]);
    }

    protected function respondWithToken($token, $user)
    {
        return new UserTokenResource(
            $user,
            $token,
            'bearer',
            auth()->factory()->getTTL() * 60
        );
    }
}
