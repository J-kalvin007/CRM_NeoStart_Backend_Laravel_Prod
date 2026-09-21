<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Lister les notifications non lues',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des notifications')
        ]
    )]
    public function index(Request $request)
    {
        return response()->json($request->user()->unreadNotifications);
    }

    #[OA\Post(
        path: '/api/v1/notifications',
        summary: 'Créer et envoyer une notification',
        security: [['bearerAuth' => []]],
        tags: ['Notifications'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'message'],
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'type', type: 'string', example: 'info'),
                    new OA\Property(property: 'recipient_role', type: 'string', example: 'delegate'),
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid', nullable: true)
                ]
            )
        ),
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
            try {
                if (in_array($role, ['manager', 'supervisor', 'superviseur'])) {
                    if ($sender?->manager) {
                        $targetUsers = collect([$sender->manager]);
                    } else {
                        $targetUsers = User::whereHas('roles', function ($q) {
                            $q->whereIn('name', ['manager', 'supervisor', 'superviseur', 'super_admin', 'Manager', 'Supervisor', 'Superviseur', 'dir-commercial']);
                        })->get();
                    }
                } elseif (in_array($role, ['delegate', 'delegue', 'delegue_medical'])) {
                    if ($sender && $sender->subordinates()->exists()) {
                        $targetUsers = $sender->subordinates;
                    } else {
                        $targetUsers = User::whereHas('roles', function ($q) {
                            $q->whereIn('name', ['delegue', 'delegate', 'Délégué', 'Delegue', 'delegue_medical']);
                        })->get();
                    }
                } else {
                    $targetUsers = User::all();
                }
            } catch (\Throwable $e) {
                $targetUsers = collect();
            }
        }

        if ($targetUsers->isEmpty() && $sender) {
            // If still empty and not explicitly for manager, fallback to sender
            if (!in_array(strtolower($validated['recipient_role'] ?? ''), ['manager', 'supervisor', 'superviseur'])) {
                $targetUsers = collect([$sender]);
            } else {
                $targetUsers = User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager', 'Superviseur', 'super_admin']);
                })->get();
            }
        }

        try {
            app(NotificationService::class)->send(
                $targetUsers,
                $validated['title'],
                $validated['message'],
                $validated['type'] ?? 'info',
                ['recipient_role' => $validated['recipient_role'] ?? 'all', 'sender_id' => $sender?->id]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Notification send error: " . $e->getMessage());
        }

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
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Notification marquée comme lue')
        ]
    )]
    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
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
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(null, 204);
    }
}
