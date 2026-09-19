<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
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
            $role = $validated['recipient_role'] ?? 'all';
            if ($role === 'manager') {
                $targetUsers = User::role('manager')->get();
            } elseif ($role === 'delegate') {
                $targetUsers = User::role('delegue')->get();
            } else {
                $targetUsers = User::all();
            }
        }

        if ($targetUsers->isEmpty()) {
            $targetUsers = collect([$sender]);
        }

        app(NotificationService::class)->send(
            $targetUsers,
            $validated['title'],
            $validated['message'],
            $validated['type'] ?? 'info',
            ['recipient_role' => $validated['recipient_role'] ?? 'all', 'sender_id' => $sender->id]
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
