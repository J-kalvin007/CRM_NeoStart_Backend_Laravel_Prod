<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::with(['user'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->from, fn($q, $v) => $q->where('start_time', '>=', $v))
            ->orderBy('start_time')
            ->paginate(min(max($request->integer('per_page', 50), 1), 200));
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
        abort_unless(Auth::user()->canEditRecordOf($event->user_id), 403, 'Seul l\'organisateur ou sa hiérarchie peut modifier cet événement.');

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'type' => 'sometimes|required|in:congress,webinar,training,symposium,other',
            'status' => 'sometimes|required|in:planned,ongoing,completed,cancelled',
            'location' => 'sometimes|nullable|string|max:255',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|nullable|date|after_or_equal:' . ($request->input('start_time') ?? $event->start_time),
            'budget' => 'sometimes|nullable|numeric|min:0',
        ]);

        $event->update($data);
        return response()->json($event->fresh(['user']));
    }

    public function destroy(Event $event)
    {
        abort_unless(Auth::user()->canEditRecordOf($event->user_id), 403, 'Seul l\'organisateur ou sa hiérarchie peut supprimer cet événement.');
        $event->delete();
        return response()->json(null, 204);
    }
}
