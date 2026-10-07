<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->with(['messages'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $tickets
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'required|string',
            'priority' => 'nullable|string',
            'attachment_url' => 'nullable|string',
        ]);

        $ticketCount = SupportTicket::count() + 1001;
        $ticketNumber = 'SUP-' . $ticketCount;

        $ticket = SupportTicket::create([
            'ticket_number' => $ticketNumber,
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'type' => 'ticket',
            'category' => $validated['category'],
            'description' => $validated['description'],
            'status' => 'Ouvert',
            'priority' => $validated['priority'] ?? 'Normale',
            'attachment_url' => $validated['attachment_url'] ?? null,
        ]);

        // Add initial message
        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['description'],
            'attachment_url' => $validated['attachment_url'] ?? null,
            'is_staff_reply' => false,
        ]);

        return response()->json([
            'message' => 'Demande de support enregistrée avec succès.',
            'ticket' => $ticket->load('messages'),
        ], 201);
    }

    public function report(Request $request)
    {
        $validated = $request->validate([
            'type_probleme' => 'nullable|string',
            'category' => 'nullable|string',
            'description' => 'required|string',
            'screenshot' => 'nullable|string',
            'app_version' => 'nullable|string',
            'device' => 'nullable|string',
            'operating_system' => 'nullable|string',
        ]);

        $ticketCount = SupportTicket::count() + 1001;
        $ticketNumber = 'BUG-' . $ticketCount;

        $category = $validated['type_probleme'] ?? $validated['category'] ?? 'Signalement Bug';

        $ticket = SupportTicket::create([
            'ticket_number' => $ticketNumber,
            'user_id' => $request->user()->id,
            'subject' => 'Signalement de problème: ' . Str::limit($validated['description'], 50),
            'type' => 'report',
            'category' => $category,
            'description' => $validated['description'],
            'status' => 'Ouvert',
            'priority' => 'Élevée',
            'attachment_url' => $validated['screenshot'] ?? null,
            'meta' => [
                'app_version' => $validated['app_version'] ?? '1.0.0',
                'device' => $validated['device'] ?? 'Mobile Device',
                'operating_system' => $validated['operating_system'] ?? 'iOS/Android',
            ],
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['description'],
            'attachment_url' => $validated['screenshot'] ?? null,
            'is_staff_reply' => false,
        ]);

        return response()->json([
            'message' => 'Signalement de problème envoyé à l\'équipe technique.',
            'ticket' => $ticket->load('messages'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with(['messages.user'])
            ->firstOrFail();

        return response()->json([
            'ticket' => $ticket
        ]);
    }

    public function reply(Request $request, $id)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'message' => 'required|string',
            'attachment_url' => 'nullable|string',
        ]);

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachment_url' => $validated['attachment_url'] ?? null,
            'is_staff_reply' => false,
        ]);

        if ($ticket->status === 'Résolu' || $ticket->status === 'Fermé') {
            $ticket->update(['status' => 'En cours']);
        }

        return response()->json([
            'message' => 'Message ajouté au ticket.',
            'data' => $message,
            'ticket' => $ticket->load('messages'),
        ]);
    }
}
