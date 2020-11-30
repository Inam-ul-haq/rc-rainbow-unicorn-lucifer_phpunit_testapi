<?php

namespace App\Http\Controllers;

use DB;
use App\Consumer;
use App\ConsumerEvent;
use App\Helpers\Helper;
use Illuminate\Http\Request;
use App\Http\Resources\ConsumerEventResource;
use App\Http\Resources\ConsumerEventResourceCollection;

class ConsumerEventController extends Controller
{
    private $default_per_page = 15;

    public function store(Request $request)
    {
        $request->validate(
            [
                'consumer_uuid' => 'required|exists:consumers,uuid',
                'event' => 'required|max:100',
                'properties' => 'json',
            ]
        );

        $source = Helper::getCurrentSource();

        $consumerEvent = new ConsumerEvent();
        $consumerEvent->event_source = $source;
        $consumerEvent->consumer_id = Consumer::where('uuid', $request->consumer_uuid)->firstOrFail()->id;
        $consumerEvent->event = $request->event;
        $consumerEvent->properties = $request->properties ?? json_encode([]);
        $consumerEvent->save();

        return new ConsumerEventResource($consumerEvent);
    }

    public function show(ConsumerEvent $consumerEvent)
    {
        return new ConsumerEventResource($consumerEvent);
    }

    public function destroy(ConsumerEvent $consumerEvent)
    {
        $consumerEvent->delete();
        return response()->json(['OK'], 200);
    }

    public function search(Request $request)
    {
        $request->validate(
            [
                'consumer_uuid' => 'uuid',
                'event' => 'max:100',
                'event_source' => 'max:100',
                'start_datetime' => 'date_format:Y-m-d H:i:s',
                'end_datetime' => 'date_format:Y-m-d H:i:s',
            ]
        );

        $consumerEvents = new ConsumerEvent;
        if ($request->consumer_uuid) {
            $consumerEvents = $consumerEvents->where('consumer_id', Consumer::where('uuid', $request->consumer_uuid)
                                             ->first()
                                             ->id);
        }

        if ($request->event) {
            $consumerEvents = $consumerEvents->where('event', $request->event);
        }

        if ($request->event_source) {
            $consumerEvents = $consumerEvents->where('event_source', $request->event_source);
        }

        if ($request->start_datetime) {
            $consumerEvents = $consumerEvents->where('created_at', '>=', $request->start_datetime);
        }

        if ($request->end_datetime) {
            $consumerEvents = $consumerEvents->where('created_at', '<=', $request->end_datetime);
        }

        return new ConsumerEventResourceCollection(
            $consumerEvents->paginate($request->per_page ?? $this->default_per_page)
        );
    }
}
