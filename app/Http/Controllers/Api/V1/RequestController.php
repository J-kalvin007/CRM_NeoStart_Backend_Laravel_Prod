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

        return response()->json([
            'status' => 'success',
            'message' => 'Demande soumise avec succès.',
            'data' => $delegateRequest
        ], 201);
    }
}
