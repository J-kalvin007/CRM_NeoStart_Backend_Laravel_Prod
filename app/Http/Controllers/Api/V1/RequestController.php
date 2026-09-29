<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DelegateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class RequestController extends Controller
{
    #[OA\Post(
        path: '/api/v1/requests',
        summary: 'Soumettre une demande (Arbitrage, Congés, etc.)',
        security: [['bearerAuth' => []]],
        tags: ['Requests'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['request_type', 'priority'],
                properties: [
                    new OA\Property(property: 'request_type', type: 'string', example: 'arbitrage_portfolio'),
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'reason', type: 'string', example: 'Changement de secteur du médecin.'),
                    new OA\Property(property: 'priority', type: 'string', example: 'high', enum: ['low', 'normal', 'high']),
                    new OA\Property(property: 'status', type: 'string', example: 'pending_manager_approval'),
                    new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande soumise avec succès'),
        ]
    )]
    public function store(Request $request)
    {
        $data = $request->validate([
            'request_type' => 'required|string',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reason' => 'nullable|string',
            'priority' => 'required|in:low,normal,high',
            'status' => 'nullable|string',
            'local_uuid' => 'nullable|uuid',
        ]);

        $data['user_id'] = Auth::id();
        $data['status'] = $data['status'] ?? 'pending_manager_approval';

        $delegateRequest = DelegateRequest::create($data);

        // Notify supervisor / manager
        $user = Auth::user();
        $manager = $user?->manager ?? \App\Models\User::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)->where('id', '!=', $user->id)->first();
        if ($manager) {
            NotificationController::sendNotification(
                $manager,
                'Nouvelle demande soumise',
                "Le délégué {$user->name} a soumis une demande ({$delegateRequest->request_type}).",
                'request',
                'superviseur',
                ['request_id' => $delegateRequest->id]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Demande soumise avec succès.',
            'data' => $delegateRequest
        ], 201);
    }

    #[OA\Get(
        path: '/api/v1/requests',
        summary: 'Lister les demandes',
        security: [['bearerAuth' => []]],
        tags: ['Requests'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des demandes')
        ]
    )]
    public function index()
    {
        $requests = DelegateRequest::where('user_id', Auth::id())->latest()->get();
        return response()->json($requests);
    }

    #[OA\Put(
        path: '/api/v1/requests/{id}',
        summary: 'Mettre à jour une demande',
        security: [['bearerAuth' => []]],
        tags: ['Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'request_type', type: 'string'),
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'reason', type: 'string'),
                    new OA\Property(property: 'priority', type: 'string', enum: ['low', 'normal', 'high']),
                    new OA\Property(property: 'status', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Demande mise à jour')
        ]
    )]
    public function update(Request $request, $id)
    {
        $delegateRequest = DelegateRequest::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'request_type' => 'sometimes|required|string',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reason' => 'nullable|string',
            'priority' => 'sometimes|required|in:low,normal,high',
            'status' => 'nullable|string',
        ]);

        $delegateRequest->update($data);

        return response()->json([
            'status' => 'success',
            'data' => $delegateRequest
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/requests/{id}',
        summary: 'Supprimer une demande',
        security: [['bearerAuth' => []]],
        tags: ['Requests'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Demande supprimée')
        ]
    )]
    public function destroy($id)
    {
        $delegateRequest = DelegateRequest::where('user_id', Auth::id())->findOrFail($id);
        $delegateRequest->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Demande supprimée.'
        ]);
    }
}
