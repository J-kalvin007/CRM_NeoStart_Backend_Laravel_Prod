<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationMessageSeeder extends Seeder
{
    public function run(): void
    {
        $delegueId = DB::table('users')->where('email', 'delegue@crm.local')->value('id') ?? DB::table('users')->first()->id ?? null;
        // Assume manager exists or pick another user, fallback to the same if only 1 user exists
        $managerId = DB::table('users')->where('email', 'superviseur@crm.local')->value('id') ?? DB::table('users')->orderBy('id', 'desc')->first()->id ?? null;

        if ($delegueId && $managerId) {
            $conversationId = Str::uuid();

            DB::table('conversations')->insert([
                'id' => $conversationId,
                'title' => 'Équipe Abidjan Est - Coaching & Visites DUO',
                'type' => 'direct',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('conversation_user')->insert([
                ['conversation_id' => $conversationId, 'user_id' => $delegueId, 'created_at' => now(), 'updated_at' => now()],
                ['conversation_id' => $conversationId, 'user_id' => $managerId, 'created_at' => now(), 'updated_at' => now()],
            ]);

            DB::table('messages')->insert([
                [
                    'id' => Str::uuid(),
                    'conversation_id' => $conversationId,
                    'sender_id' => $managerId,
                    'content' => 'Bonjour Alice, n\'oublie pas de valider la visite en DUO avec le Dr. Marie Koné à 10:30.',
                    'is_read' => true,
                    'created_at' => now()->subHours(3),
                    'updated_at' => now()->subHours(3),
                ],
                [
                    'id' => Str::uuid(),
                    'conversation_id' => $conversationId,
                    'sender_id' => $delegueId,
                    'content' => 'Bien reçu Marc ! Les échantillons et le support ADV sont prêts.',
                    'is_read' => true,
                    'created_at' => now()->subHours(2),
                    'updated_at' => now()->subHours(2),
                ]
            ]);
        }
    }
}
