<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Messagerie interne : uniquement les conversations dont l'utilisateur est participant.
 * Aucune conversation ni aucun message de démonstration.
 */
class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $data = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'messages' => fn ($q) => $q->latest()->limit(1),
                'users:id,name',
            ])
            ->get()
            ->map(function (Conversation $c) use ($user) {
                $lastMsg = $c->messages->first();
                $others = $c->users->where('id', '!=', $user->id)->pluck('name')->filter()->values();
                $name = $c->title ?: ($others->isNotEmpty() ? $others->join(', ') : 'Conversation');

                return [
                    'id' => (string) $c->id,
                    'name' => $name,
                    'title' => $name,
                    'type' => $c->type ?? 'direct',
                    'last_message' => $lastMsg?->content ?? '',
                    'last_message_at' => ($lastMsg?->created_at ?? $c->updated_at)->toIso8601String(),
                    'unread_count' => Message::where('conversation_id', $c->id)
                        ->where('sender_id', '!=', $user->id)
                        ->where('is_read', false)
                        ->count(),
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

        return response()->json(['data' => $data]);
    }

    /** Démarrer une conversation avec un destinataire (et un premier message). */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|uuid|exists:users,id',
            'title' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:100',
            'message' => 'nullable|string',
        ]);
        $user = $request->user();

        if ($validated['recipient_id'] === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.'], 422);
        }

        $conversation = Conversation::create([
            'title' => $validated['title'] ?? null,
            'type' => $validated['type'] ?? 'direct',
        ]);
        $conversation->users()->attach([$user->id, $validated['recipient_id']]);

        if (!empty($validated['message'])) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'content' => $validated['message'],
                'is_read' => false,
            ]);
        }

        $recipient = User::find($validated['recipient_id']);
        if ($recipient && !empty($validated['message'])) {
            NotificationController::sendNotification(
                $recipient,
                'Nouveau message',
                "{$user->name} : " . mb_strimwidth($validated['message'], 0, 120, '…'),
                'message',
                'all',
                ['id' => (string) $conversation->id, 'type' => 'conversation']
            );
        }

        return response()->json(['data' => ['id' => (string) $conversation->id]], 201);
    }

    public function messages($id, Request $request)
    {
        $conversation = $this->participantConversation($id, $request->user());

        // Les messages reçus sont marqués comme lus à l'ouverture
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $data = Message::where('conversation_id', $conversation->id)
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => (string) $m->id,
                'conversation_id' => (string) $m->conversation_id,
                'sender_id' => (string) $m->sender_id,
                'sender_name' => $m->sender?->name ?? '',
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $data]);
    }

    public function sendMessage($id, Request $request)
    {
        $validated = $request->validate(['content' => 'required|string']);
        $user = $request->user();
        $conversation = $this->participantConversation($id, $user);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => $validated['content'],
            'is_read' => false,
        ]);
        $conversation->touch();

        return response()->json([
            'data' => [
                'id' => (string) $msg->id,
                'conversation_id' => (string) $msg->conversation_id,
                'sender_id' => (string) $msg->sender_id,
                'sender_name' => $user->name,
                'content' => $msg->content,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ], 201);
    }

    private function participantConversation(string $id, User $user): Conversation
    {
        $conversation = Conversation::findOrFail($id);
        abort_unless(
            $conversation->users()->where('users.id', $user->id)->exists(),
            403,
            'Vous ne participez pas à cette conversation.'
        );

        return $conversation;
    }
}
