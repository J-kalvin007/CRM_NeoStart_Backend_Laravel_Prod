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
        $user = Auth::user();

        $reqType = $request->input('type_demande') ?? $request->input('request_type') ?? 'general';
        $subject = $request->input('objet') ?? $request->input('subject') ?? 'Demande du délégué';
        $description = $request->input('description') ?? $request->input('reason') ?? '';
        $justification = $request->input('justification');
        $priority = $request->input('priority') ?? 'normal';
        if (!in_array($priority, ['low', 'normal', 'high'])) {
            $priority = 'normal';
        }

        $delegateRequest = DelegateRequest::create([
            'user_id' => $user?->id ?? Auth::id(),
            'delegate_id' => $request->input('delegate_id') ?? $user?->id,
            'request_type' => $reqType,
            'subject' => $subject,
            'description' => $description,
            'reason' => $description,
            'justification' => $justification,
            'priority' => $priority,
            'status' => 'pending_manager_approval',
        ]);

        // Notify supervisor / manager
        // La demande est adressée au supérieur direct du délégué
        $manager = $user?->manager;
        if ($manager) {
            NotificationController::sendNotification(
                $manager,
                'Nouvelle demande soumise',
                "Le délégué {$user->name} a soumis une demande : {$subject}.",
                'request',
                'superviseur',
                ['request_id' => (string) $delegateRequest->id]
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
    public function index(Request $request)
    {
        $user = Auth::user();
        $isManager = $user && $user->isAdminOrSupervisor();

        $isGlobal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);

        // Délégué : ses demandes ; hiérarchie : les siennes + celles de son équipe ; admin : tout
        $requests = DelegateRequest::with(['user', 'contact'])
            ->when(!$isGlobal, fn ($q) => $q->whereIn('user_id', $isManager
                ? array_merge([$user->id], $user->teamMemberIds())
                : [$user->id]))
            ->latest()
            ->get()
            ->map(fn ($r) => $r->toArray() + ['objet' => $r->subject, 'description' => $r->reason]);

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
        $delegateRequest = $this->ownPendingRequest($id);

        // Le statut est fixé par le validateur, jamais par le demandeur
        $data = $request->validate([
            'request_type' => 'sometimes|required|string',
            'type_demande' => 'sometimes|string',
            'subject' => 'sometimes|nullable|string|max:255',
            'objet' => 'sometimes|nullable|string|max:255',
            'contact_id' => 'nullable|uuid|exists:contacts,id',
            'reason' => 'nullable|string',
            'description' => 'nullable|string',
            'justification' => 'nullable|string',
            'priority' => 'sometimes|required|in:low,normal,high',
        ]);

        $delegateRequest->update(array_filter([
            'request_type' => $data['request_type'] ?? $data['type_demande'] ?? null,
            'subject' => $data['subject'] ?? $data['objet'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'reason' => $data['reason'] ?? $data['description'] ?? null,
            'justification' => $data['justification'] ?? null,
            'priority' => $data['priority'] ?? null,
        ], fn ($v) => $v !== null));

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
        $delegateRequest = $this->ownPendingRequest($id);
        $delegateRequest->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Demande supprimée.'
        ]);
    }

    private function ownPendingRequest($id): DelegateRequest
    {
        $delegateRequest = DelegateRequest::where('user_id', Auth::id())->findOrFail($id);
        abort_unless(
            in_array($delegateRequest->status, ['pending_manager_approval', 'pending', 'en_attente', 'soumis'], true),
            422,
            'Cette demande a déjà été traitée et ne peut plus être modifiée.'
        );

        return $delegateRequest;
    }
}
