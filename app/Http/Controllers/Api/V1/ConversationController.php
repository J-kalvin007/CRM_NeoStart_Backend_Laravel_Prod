<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = DB::table('conversations')
            ->join('conversation_participants as cp', 'conversations.id', '=', 'cp.conversation_id')
            ->where('cp.user_id', $user->id)
            ->leftJoin('messages as lm', 'conversations.last_message_id', '=', 'lm.id')
            ->leftJoin('users as other', function($join) use ($user) {
                $join->on('other.id', '=', DB::raw(
                    "(SELECT user_id FROM conversation_participants WHERE conversation_id = conversations.id AND user_id != '{$user->id}' LIMIT 1)"
                ));
            })
            ->select(
                'conversations.id',
                DB::raw("COALESCE(conversations.name, CONCAT(other.prenom, ' ', other.nom)) as name"),
                'conversations.type',
                'lm.content as last_message',
                'lm.created_at as last_message_at',
                DB::raw('0 as unread_count')
            )
            ->orderByDesc('lm.created_at')
            ->get();

        return response()->json(['data' => $conversations]);
    }

    public function messages(Request $request, string $conversationId)
    {
        $messages = DB::table('messages')
            ->join('users', 'messages.sender_id', '=', 'users.id')
            ->where('messages.conversation_id', $conversationId)
            ->select(
                'messages.id',
                'messages.content',
                'messages.created_at',
                'messages.sender_id',
                DB::raw("CONCAT(users.prenom, ' ', users.nom) as sender_name")
            )
            ->orderBy('messages.created_at')
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request, string $conversationId)
    {
        $v = $request->validate(['content' => 'required|string|max:2000']);

        $id = Str::uuid()->toString();
        DB::table('messages')->insert([
            'id'              => $id,
            'conversation_id' => $conversationId,
            'sender_id'       => $request->user()->id,
            'content'         => $v['content'],
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::table('conversations')->where('id', $conversationId)
            ->update(['last_message_id' => $id, 'updated_at' => now()]);

        return response()->json(['data' => DB::table('messages')->find($id)], 201);
    }
}
