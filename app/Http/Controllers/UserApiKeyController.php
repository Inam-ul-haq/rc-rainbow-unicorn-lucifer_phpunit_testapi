<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\UserApiKey;
use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use App\Http\Resources\UserApiKeyResource;

class UserApiKeyController extends Controller
{
    public function index()
    {
        if (Auth::user()->hasPermissionTo('admin api keys')) {
            return UserApiKeyResource::collection(UserApiKey::with('consumerSource')->get())->hide(['secret']);
        }

        return UserApiKeyResource::collection(
            UserApiKey::where('user_id', Auth::user()->id)->with('consumerSource')->get()
        )->hide(['secret']);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'expires_at' => 'nullable|date|date_format:Y-m-d',
                'source_id' => 'nullable|exists:consumer_sources,id',
            ]
        );

        DB::beginTransaction();
        $secret = ''; // Declare here, as it gets hashed before being put into the DB,
                      // so is unreadable if we just try to read the value from the object later
        try {
            $secret = str_replace('-', '', Uuid::uuid4());
            $apiKey = UserApiKey::create(
                [
                    'user_id' => Auth::user()->id,
                    'source_id' => $request->source_id,
                    'expires_at' => $request->expires_at,
                    'api_key' => str_replace('-', '', Uuid::uuid4()),
                    'secret' => $secret,
                ]
            );

            $apiKey->assignRole('api key');

            activity('api key actions')
                ->on($apiKey)
                ->tap('setLogLabel', 'create api key')
                ->log('New API Key Added');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return new UserApiKeyResource($apiKey, $secret);
    }

    public function destroy(UserApiKey $key)
    {
        if (!$key) {
            return response()->json([__('Generic.not_found')], 404);
        }

        if (!Auth::user()->hasPermissionTo('admin api keys') and
            $key->user_id !== Auth::user()->id) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        $key->delete();

        activity('api key actions')
            ->on($key)
            ->tap('setLogLabel', 'delete api key')
            ->log('API Key Removed');

        return response()->json([__('Generic.OK')], 200);
    }

    public function update(Request $request, UserApiKey $key)
    {
        if (!$key) {
            return response()->json([__('Generic.not_found')], 404);
        }

        if (!Auth::user()->hasPermissionTo('admin api keys') and
            $key->user_id !== Auth::user()->id) {
            return response()->json([__('Generic.permission_denied')], 403);
        }

        $request->validate(
            [
                'expires_at' => 'date|date_format:Y-m-d',
                'source_id' => 'exists:consumer_sources,id',
            ]
        );

        DB::beginTransaction();

        try {
            $key->update($request->only([
                'expires_at',
                'source_id',
            ]));

            activity('api key actions')
                ->on($key)
                ->tap('setLogLabel', 'edit api key')
                ->log('API Key Updated');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();
        return UserApiKeyResource::make($key)->hide(['secret']);
    }
}
