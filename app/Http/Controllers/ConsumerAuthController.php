<?php

namespace App\Http\Controllers;

use Auth;
use JWTAuth;
use App\Consumer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Password;
use App\Http\Resources\ConsumerResource;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Resources\ConsumerTokenResource;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ConsumerAuthController extends Controller
{
    use ResetsPasswords, SendsPasswordResetEmails {
        SendsPasswordResetEmails::broker insteadof ResetsPasswords;
        ResetsPasswords::credentials insteadof SendsPasswordResetEmails;
    }

    public function broker()
    {
        return Password::broker('consumers');
    }

    public function login()
    {
        $credentials = request(['email', 'password']);

        $consumer = Consumer::where('email', '=', $credentials['email'])->first();

        if ($consumer === null) {
            activity('consumer authentication')
                ->withProperties(['email' =>  $credentials['email']])
                ->tap('setLogLabel', 'login account not found')
                ->log('Log in failed - account not found');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if ($consumer->blacklisted) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'login blocked')
                ->log('Login prevented - account blacklisted');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if ($consumer->deactivated_at) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'login blocked')
                ->log('Login prevented - account deactivated');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$consumer->canLogin()) {
            return response()->json(['error' => __('Ganeric.unavailable')], 503);
        }

        if (! $token = auth('consumers')->attempt($credentials)) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'invalid login credentials')
                ->log('Log in failed - invalid credentials');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        activity('consumer authentication')
            ->on($consumer)
            ->tap('setLogLabel', 'consumer login')
            ->log('Logged in successfully');

        return $this->respondWithToken($token, $consumer);
    }

    public function logout()
    {
        if (auth('consumers')->getUser() !== null) {
            auth('consumers')->logout();
        }

        activity('consumer authentication')
            ->tap('setLogLabel', 'consumer logout')
            ->log('Logged out');

        return response()->json(['message' => __('ConsumerAuthController.logged_out') ]);
    }

    public function refresh()
    {
        $token = auth('consumers')->refresh();
        $consumer = auth('consumers')->user();

        if ($consumer->blacklisted) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'login blocked')
                ->log('Login prevented - account blacklisted');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if ($consumer->deactivated_at) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'login blocked')
                ->log('Login prevented - account deactivated');
            return response()->json(['error' => __('Generic.unauthorized')], 401);
        }

        if (!$consumer->canLogin()) {
            return response()->json(['error' => __('Ganeric.unavailable')], 503);
        }

        activity('consumer authentication')
            ->on($consumer)
            ->tap('setLogLabel', 'token refresh')
            ->log('Token refreshed');

        return $this->respondWithToken($token, $consumer);
    }

    public function forgotPw(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'reset_url' => 'required',
        ]);

        activity('consumer authentication')
            ->withProperties(['email'=>$request->email])
            ->tap('setLogLabel', 'password reset email')
            ->log('Password reset email requested');

        return $this->sendResetLinkEmail($request);
    }

    public function adminSendPasswordReset(Consumer $consumer, Request $request)
    {
        $request->validate([
            'reset_url' => 'required|url',
        ]);

        activity('consumer authentication')
            ->on($consumer)
            ->tap('setLogLabel', 'admin password reset email')
            ->log('Password reset email request');

        $response = $this->broker()->sendResetLink(['email'=>$consumer->email]);

        if ($response == Password::RESET_LINK_SENT) {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'admin password reset email failed')
                ->log('Password reset notification sent');

            response()->json(['message' => __('ConsumerAuthController.pw_reset_sent')], 200);
        } else {
            activity('consumer authentication')
                ->on($consumer)
                ->tap('setLogLabel', 'admin password reset email failed')
                ->log('Failed to send password reset notification');

            response()->json(['message' => __('ConsumerAuthController.pw_reset_send_failed')], 200);
        }
    }

    public function forgotPwReset(Request $request)
    {
        $request->merge(['password_confirmation' =>  $request->input('password')]);
        return $this->reset($request);
    }

    protected function resetPassword($user, $password)
    {
        $user->password = $password;
        $user->save();

        activity('consumer authentication')
            ->on($user)
            ->tap('setLogLabel', 'password reset')
            ->log('Password reset');

        event(new PasswordReset($user));
    }

    protected function sendResetLinkResponse($response)
    {
        activity('consumer authentication')
            ->tap('setLogLabel', 'password reset link send success')
            ->log('Password reset email sent successfully');

        return response()->json(['message' => __('ConsumerAuthController.pw_reset_send_if_ac_found')]);
    }

    protected function sendResetLinkFailedResponse($response)
    {
        activity('consumer authentication')
            ->tap('setLogLabel', 'password reset link send failed')
            ->log('Password reset email failed to send');

        return response()->json(['message' => __('ConsumerAuthController.pw_reset_sent_if_ac_found')]);
    }

    protected function sendResetResponse($response)
    {
        activity('consumer authentication')
            ->tap('setLogLabel', 'password reset successful')
            ->log('Password reset');

        return response()->json(['message' => __('ConsumerAuthController.pw_reset_success')]);
    }

    protected function sendResetFailedResponse(Request $request, $response)
    {
        activity('consumer authentication')
            ->tap('setLogLabel', 'password reset failed')
            ->log('Password reset failed');

        return response()->json(['message' => __('AuthController.invalid_token')]);
    }

    protected function respondWithToken($token, $consumer)
    {
        return new ConsumerTokenResource(
            $consumer,
            $token,
            'bearer',
            auth('consumers')->factory()->getTTL() * 60
        );
    }
}
