<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use App\Pet;
use App\Consumer;
use Illuminate\Http\Request;
use App\Http\Resources\PetResource;

class PetController extends Controller
{
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
                'consumer_uuid' => 'required|exists:consumers,uuid',
                'name'          => 'required|max:100',
                'dob'           => 'required|date',
                'breed_id'      => 'required|exists:breeds,id',
                'gender'        => 'required|in:male,female',
                'neutered'      => 'nullable|boolean',
            ]
        );

        DB::beginTransaction();

        try {
            $pet = Pet::create([
                'consumer_id' => Consumer::where('uuid', $request->consumer_uuid)->firstOrFail()->id,
                'pet_name' => $request->name,
                'pet_dob' => $request->dob,
                'breed_id' => $request->breed_id,
                'gender' => $request->gender,
                'neutered' => ($request->neutered) ? $request->neutered : null,
            ]);

            activity('pet actions')
                ->withProperties(['consumer_id' => $request->consumer_id])
                ->tap('setLogLabel', 'add pet')
                ->log('Pet Added');
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        DB::commit();

        return new PetResource($pet);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Pet  $pet
     * @return \Illuminate\Http\Response
     */
    public function show(Pet $pet)
    {
        if (Auth::user()->hasPermissionTo('view consumers')) {
            return new PetResource($pet);
        }

        return response()->json([__('Generic.unauthorized')], 403);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Pet  $pet
     * @return \Illuminate\Http\Response
     */
    public function edit(Pet $pet)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Pet  $pet
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Pet $pet)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Pet  $pet
     * @return \Illuminate\Http\Response
     */
    public function destroy(Pet $pet)
    {
        //
    }
}
