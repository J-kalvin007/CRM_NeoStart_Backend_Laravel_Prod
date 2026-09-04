<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with(['user'])->paginate(15);
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:congress,webinar,training,symposium,other',
            'status' => 'required|in:planned,ongoing,completed,cancelled',
            'location' => 'nullable|string|max:255',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
            'budget' => 'nullable|numeric|min:0',
        ]);

        $data['user_id'] = Auth::id();
        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $event = Event::create($data);

        return response()->json($event, 201);
    }

    public function show(Event $event)
    {
        return response()->json($event->load(['user']));
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'status' => 'sometimes|required|in:planned,ongoing,completed,cancelled',
            'budget' => 'sometimes|numeric|min:0',
        ]);

        $event->update($data);
        return response()->json($event);
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return response()->json(null, 204);
    }
}
