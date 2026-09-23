<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CoachingSession;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Coaching', description: 'Gestion des sessions de coaching et visites en duo')]
class CoachingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/coaching/delegates/{id}',
        summary: 'Récupérer les sessions de coaching d\'un délégué',
        security: [['bearerAuth' => []]],
        tags: ['Coaching'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des sessions de coaching'),
        ]
    )]
    public function getSessions(string $id)
    {
        $sessions = CoachingSession::with('supervisor')
            ->where('delegate_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($sessions);
    }

    #[OA\Post(
        path: '/api/v1/coaching-sessions',
        summary: 'Planifier une session en duo (Superviseur)',
        security: [['bearerAuth' => []]],
        tags: ['Coaching'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delegate_id', 'date_duo', 'zone', 'motif', 'objectifs_prevus'],
                properties: [
                    new OA\Property(property: 'delegate_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'date_duo', type: 'string', format: 'date'),
                    new OA\Property(property: 'zone', type: 'string'),
                    new OA\Property(property: 'motif', type: 'string'),
                    new OA\Property(property: 'objectifs_prevus', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Duo planifié avec succès'),
        ]
    )]
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'delegate_id' => 'required|uuid|exists:users,id',
            'date_duo' => 'required|date',
            'zone' => 'required|string',
            'motif' => 'required|string',
            'objectifs_prevus' => 'required|array',
        ]);

        $validated['supervisor_id'] = Auth::id();
        $validated['objectifs_prevus_json'] = $validated['objectifs_prevus'];
        unset($validated['objectifs_prevus']);
        $validated['status'] = 'active';

        $session = CoachingSession::create($validated);

        $delegate = User::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)->find($session->delegate_id);
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Visite en Duo programmée',
                'Votre manager a planifié une visite conjointe avec vous.',
                'coaching',
                'delegue',
                ['session_id' => $session->id]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Duo planifié avec succès.',
            'data' => $session->load('supervisor', 'delegate')
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/coaching-sessions/{id}/complete',
        summary: 'Clôturer une session en duo (Superviseur)',
        security: [['bearerAuth' => []]],
        tags: ['Coaching'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['resultats_constates', 'taux_atteinte_pct', 'commentaires_superviseur'],
                properties: [
                    new OA\Property(property: 'resultats_constates', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'taux_atteinte_pct', type: 'number', format: 'float'),
                    new OA\Property(property: 'commentaires_superviseur', type: 'string'),
                    new OA\Property(property: 'plan_action_recommande', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Rapport de duo clôturé et enregistré'),
        ]
    )]
    public function completeSession(Request $request, string $id)
    {
        $session = CoachingSession::findOrFail($id);

        $validated = $request->validate([
            'resultats_constates' => 'required|array|min:1',
            'taux_atteinte_pct' => 'required|numeric|min:0|max:100',
            'commentaires_superviseur' => 'required|string',
            'plan_action_recommande' => 'nullable|string',
        ]);

        $session->update([
            'resultats_constates_json' => $validated['resultats_constates'],
            'taux_atteinte_pct' => $validated['taux_atteinte_pct'],
            'commentaires_superviseur' => $validated['commentaires_superviseur'],
            'plan_action_recommande' => $validated['plan_action_recommande'] ?? null,
            'status' => 'cloture',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rapport de duo clôturé et enregistré.',
            'data' => $session
        ], 200);
    }
}
