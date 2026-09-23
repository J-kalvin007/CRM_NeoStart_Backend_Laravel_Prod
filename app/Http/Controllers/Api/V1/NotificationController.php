<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Lister les notifications',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des notifications')
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', 'App\Models\User')
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(function ($n) {
                $data = json_decode($n->data, true) ?: [];
                return [
                    'id' => (string) $n->id,
                    'type' => $data['type'] ?? 'info',
                    'data' => [
                        'id' => (string) $n->id,
                        'title' => $data['title'] ?? 'Notification SKYNITY',
                        'message' => $data['message'] ?? '',
                        'type' => $data['type'] ?? 'info',
                        'recipient_role' => $data['recipient_role'] ?? 'all',
                    ],
                    'title' => $data['title'] ?? 'Notification SKYNITY',
                    'message' => $data['message'] ?? '',
                    'body' => $data['message'] ?? '',
                    'recipient_role' => $data['recipient_role'] ?? 'all',
                    'read_at' => $n->read_at,
                    'unread' => is_null($n->read_at),
                    'created_at' => $n->created_at,
                ];
            });

        return response()->json($notifications);
    }

    /**
     * Helper statique universel pour envoyer une notification In-App et Email.
     */
    public static function sendNotification($users, string $title, string $message, string $type = 'info', string $recipientRole = 'all', array $extra = [])
    {
        if (!$users) {
            return;
        }

        if ($users instanceof \Illuminate\Support\Collection) {
            $userList = $users;
        } elseif (is_array($users)) {
            $userList = $users;
        } else {
            $userList = [$users];
        }

        foreach ($userList as $user) {
            if (!$user || empty($user->id)) {
                continue;
            }

            // 1. Insertion en base de données (Table notifications)
            try {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\Notifications\GeneralNotification',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $user->id,
                    'data' => json_encode(array_merge([
                        'title' => $title,
                        'message' => $message,
                        'type' => $type,
                        'recipient_role' => $recipientRole,
                    ], $extra)),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error("Erreur enregistrement notification DB: " . $e->getMessage());
                throw $e;
            }

            // 2. Envoi d'Email au destinataire
            if (!empty($user->email)) {
                try {
                    Mail::to($user->email)->send(new \App\Mail\SkynityNotificationMail(
                        $user,
                        $title,
                        $message,
                        $extra['action_url'] ?? null
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Alerte envoi email notification ({$user->email}): " . $e->getMessage());
                }
            }

            // 3. Envoi Push Notification FCM si disponible
            try {
                if (!empty($user->fcm_token) && app()->bound(\App\Services\FcmNotificationService::class)) {
                    $fcmService = app(\App\Services\FcmNotificationService::class);
                    $fcmService->sendToUser($user, $title, $message, array_merge(['type' => $type], $extra));
                }
            } catch (\Throwable $e) {
                Log::info("FCM non configuré ou token expiré pour {$user->id}: " . $e->getMessage());
            }
        }
    }

    #[OA\Post(
        path: '/api/v1/notifications',
        summary: 'Créer et envoyer une notification',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 201, description: 'Notification envoyée avec succès')
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'message'        => 'required|string',
            'type'           => 'nullable|string',
            'recipient_role' => 'nullable|string',
            'user_id'        => 'nullable|string',
        ]);

        $sender = $request->user();
        $targetUsers = collect();

        if (!empty($validated['user_id'])) {
            $targetUsers = User::where('id', $validated['user_id'])->get();
        } else {
            $role = strtolower($validated['recipient_role'] ?? 'all');
            if (in_array($role, ['manager', 'supervisor', 'superviseur'])) {
                if ($sender?->manager) {
                    $targetUsers->push($sender->manager);
                }
                $mgrs = User::whereHas('roles', fn($q) => $q->whereIn('name', ['manager', 'supervisor', 'superviseur', 'super_admin', 'Manager']))->get();
                $targetUsers = $targetUsers->concat($mgrs)->unique('id');
                if ($targetUsers->isEmpty()) {
                    $def = User::where('email', 'cmoucharif@gmail.com')->first();
                    if ($def) $targetUsers->push($def);
                }
            } elseif (in_array($role, ['delegate', 'delegue', 'delegue_medical'])) {
                if ($sender && $sender->subordinates()->exists()) {
                    $targetUsers = $sender->subordinates;
                } else {
                    $targetUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['delegue', 'delegate', 'Délégué', 'Délégué Médical']))->get();
                }
            } else {
                $targetUsers = User::all();
            }
        }

        if ($targetUsers->isEmpty() && $sender) {
            $targetUsers = collect([$sender]);
        }

        self::sendNotification(
            $targetUsers,
            $validated['title'],
            $validated['message'],
            $validated['type'] ?? 'info',
            $validated['recipient_role'] ?? 'all',
            ['sender_id' => $sender?->id]
        );

        return response()->json([
            'message' => 'Notification envoyée avec succès',
            'recipients_count' => $targetUsers->count()
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/notifications/{id}/mark-as-read',
        summary: 'Marquer une notification comme lue',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 204, description: 'Notification marquée comme lue')
        ]
    )]
    public function markAsRead(Request $request, string $id)
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/api/v1/notifications/mark-all-as-read',
        summary: 'Marquer toutes les notifications comme lues',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 204, description: 'Toutes les notifications marquées comme lues')
        ]
    )]
    public function markAllAsRead(Request $request)
    {
        DB::table('notifications')
            ->where('notifiable_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(null, 204);
    }

    #[OA\Delete(
        path: '/api/v1/notifications/{id}',
        summary: 'Supprimer une notification',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 204, description: 'Notification supprimée')
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->delete();

        return response()->json(null, 204);
    }
}
