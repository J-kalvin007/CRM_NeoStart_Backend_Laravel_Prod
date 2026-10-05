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
                $type = self::notificationType($data);
                return [
                    'id' => (string) $n->id,
                    'type' => $type,
                    'data' => [
                        'id' => (string) $n->id,
                        'title' => $data['title'] ?? 'Notification SKYNITY',
                        'message' => $data['message'] ?? '',
                        'type' => $type,
                        'recipient_role' => $data['recipient_role'] ?? 'all',
                    ],
                    // Élément concerné, pour ouvrir la bonne page au clic
                    'target' => self::resolveTarget($data),
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
     * Types d'entité qu'on passait autrefois dans `extra['type']`, ce qui écrasait le type de la notification.
     */
    private const LEGACY_ENTITY_TYPES = [
        'visit' => 'validation', 'activity_report' => 'validation', 'tour_plan' => 'planning',
        'expense' => 'expense', 'sample' => 'sample', 'sample_request' => 'sample',
        'request' => 'request', 'delegate_request' => 'request',
        'contact' => 'validation', 'account' => 'validation', 'conversation' => 'message',
    ];

    private static function legacyEntityType(array $data): ?string
    {
        $type = $data['type'] ?? null;
        return !empty($data['id']) && is_string($type) && isset(self::LEGACY_ENTITY_TYPES[$type]) ? $type : null;
    }

    private static function notificationType(array $data): string
    {
        $legacy = self::legacyEntityType($data);
        return $legacy ? self::LEGACY_ENTITY_TYPES[$legacy] : ($data['type'] ?? 'info');
    }

    /**
     * Élément visé par la notification : ['kind' => activity|tour_plan|expense|contact|account|objective|
     * pharmacovigilance|coaching|product|sample_request|request|conversation, 'id' => ...] ou null.
     */
    private static function resolveTarget(array $data): ?array
    {
        $ids = [
            'activity_id' => 'activity', 'tour_plan_id' => 'tour_plan', 'expense_id' => 'expense',
            'contact_id' => 'contact', 'account_id' => 'account', 'objectif_id' => 'objective',
            'report_id' => 'pharmacovigilance', 'session_id' => 'coaching', 'product_id' => 'product',
        ];
        foreach ($ids as $key => $kind) {
            if (!empty($data[$key])) {
                return ['kind' => $kind, 'id' => (string) $data[$key]];
            }
        }

        $entity = $data['entity_type'] ?? self::legacyEntityType($data);

        if (!empty($data['request_id'])) {
            $isSample = ($data['type'] ?? null) === 'sample' || in_array($entity, ['sample', 'sample_request'], true);
            return ['kind' => $isSample ? 'sample_request' : 'request', 'id' => (string) $data['request_id']];
        }

        // Notifications Laravel (PendingValidationNotification) : entity_type = classe du modèle
        if (!empty($data['entity_id']) && is_string($entity)) {
            $kind = str_ends_with($entity, 'Contact') ? 'contact' : (str_ends_with($entity, 'Account') ? 'account' : null);
            return $kind ? ['kind' => $kind, 'id' => (string) $data['entity_id']] : null;
        }

        if (!empty($data['id']) && is_string($entity)) {
            $kind = [
                'visit' => 'activity', 'activity_report' => 'activity', 'sample' => 'sample_request',
                'delegate_request' => 'request',
            ][$entity] ?? $entity;
            if (in_array($kind, ['activity', 'tour_plan', 'expense', 'sample_request', 'request', 'contact', 'account', 'conversation'], true)) {
                return ['kind' => $kind, 'id' => (string) $data['id']];
            }
        }

        return null;
    }

    /**
     * Helper statique universel pour envoyer une notification In-App et Email.
     */
    public static function sendNotification($users, string $title, string $message, string $type = 'info', string $recipientRole = 'all', array $extra = [])
    {
        if (!$users) {
            return;
        }

        // Le type passé dans $extra décrit l'entité : il ne doit pas écraser le type de la notification
        if (isset($extra['type'])) {
            $extra['entity_type'] = $extra['entity_type'] ?? $extra['type'];
            unset($extra['type']);
        }

        if ($users instanceof \Illuminate\Support\Collection) {
            $userList = $users;
        } elseif (is_array($users)) {
            $userList = $users;
        } else {
            $userList = [$users];
        }

        foreach ($userList as $u) {
            if (!$u) {
                continue;
            }

            $userObj = null;
            $userId = null;

            if ($u instanceof User) {
                $userObj = $u;
                $userId = (string) $u->id;
            } elseif (is_array($u)) {
                $userId = (string) ($u['id'] ?? $u['user_id'] ?? '');
                if ($userId) {
                    $userObj = User::find($userId);
                }
            } elseif (is_string($u) || is_numeric($u)) {
                $userId = (string) $u;
                $userObj = User::find($userId);
            }

            if (!$userId) {
                continue;
            }

            $nameStr = $userObj ? ($userObj->name ?? $userObj->nom ?? 'Utilisateur') : 'Utilisateur';
            $emailStr = $userObj ? ($userObj->email ?? null) : null;
            $fcmToken = $userObj ? ($userObj->fcm_token ?? null) : null;

            // 1. Insertion en base de données (Table notifications)
            try {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\Notifications\GeneralNotification',
                    'notifiable_type' => 'App\Models\User',
                    'notifiable_id' => $userId,
                    'data' => json_encode(array_merge([
                        'title' => $title,
                        'message' => $message,
                        'type' => $type,
                        'recipient_role' => $recipientRole,
                        'user_id' => $userId,
                        'delegate_name' => $nameStr,
                    ], $extra)),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error("Erreur enregistrement notification DB: " . $e->getMessage());
            }

            // 2. Envoi d'Email au destinataire
            if ($userObj && !empty($emailStr)) {
                try {
                    Mail::to($emailStr)->send(new \App\Mail\SkynityNotificationMail(
                        $userObj,
                        $title,
                        $message,
                        $extra['action_url'] ?? null
                    ));
                } catch (\Throwable $e) {
                    Log::warning("Alerte envoi email notification ({$emailStr}): " . $e->getMessage());
                }
            }

            // 3. Envoi Push Notification FCM si disponible
            try {
                if ($userObj && !empty($fcmToken) && app()->bound(\App\Services\FcmNotificationService::class)) {
                    $fcmService = app(\App\Services\FcmNotificationService::class);
                    $fcmService->sendToUser($userObj, $title, $message, array_merge(['type' => $type], $extra));
                }
            } catch (\Throwable $e) {
                Log::info("FCM non configuré ou token expiré pour {$userId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Envoie une notification à tous les managers/superviseurs (ou au manager direct).
     */
    public static function notifyManagers(string $title, string $message, string $type = 'info', array $extra = [], $delegate = null)
    {
        $managers = collect();

        // Check direct manager if delegate user provided
        if ($delegate) {
            $delObj = is_string($delegate) ? User::find($delegate) : $delegate;
            if ($delObj && !empty($delObj->manager_id)) {
                $mgr = User::find($delObj->manager_id);
                if ($mgr) {
                    $managers->push($mgr);
                }
            }
        }

        // Fallback: all managers/supervisors in database
        if ($managers->isEmpty()) {
            $managers = User::where(function ($q) {
                $q->where('role', 'like', '%manager%')
                  ->orWhere('role', 'like', '%supervis%')
                  ->orWhere('role', 'like', '%admin%');
            })->get();
        }

        if ($managers->isNotEmpty()) {
            self::sendNotification($managers, $title, $message, $type, 'manager', $extra);
        }
    }

    /**
     * Envoie une notification au délégué et à son manager.
     */
    public static function notifyUserAndManagers($user, string $title, string $message, string $type = 'info', array $extra = [])
    {
        self::sendNotification($user, $title, $message, $type, 'delegate', $extra);
        self::notifyManagers($title, $message, $type, $extra, $user);
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
            } elseif (in_array($role, ['delegate', 'delegue', 'delegue_medical', 'delegue_medicale'])) {
                if ($sender && $sender->subordinates()->exists()) {
                    $targetUsers = $sender->subordinates;
                } else {
                    $targetUsers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['delegue', 'delegate', 'Délégué', 'Délégué Médical', 'Delegue_medicale', 'delegue_medicale', 'Superviseur', 'Superadmin']))->get();
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
