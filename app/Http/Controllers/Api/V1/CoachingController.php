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
        path: '/api/v1/coaching/sessions',
        summary: 'Créer une session de coaching',
        security: [['bearerAuth' => []]],
        tags: ['Coaching'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delegate_id', 'status'],
                properties: [
                    new OA\Property(property: 'delegate_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'strengths', type: 'string'),
                    new OA\Property(property: 'weaknesses', type: 'string'),
                    new OA\Property(property: 'action_plan', type: 'string'),
                    new OA\Property(property: 'next_review_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'completed']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Session créée avec succès'),
        ]
    )]
    public function storeSession(Request $request)
    {
        $validated = $request->validate([
            'delegate_id' => 'required|uuid|exists:users,id',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'next_review_date' => 'nullable|date',
            'status' => 'required|in:draft,active,completed'
        ]);

        $validated['supervisor_id'] = Auth::id();

        $session = CoachingSession::create($validated);

        if ($session->status === 'active') {
            $delegate = User::find($session->delegate_id);
            app(NotificationService::class)->send(
                $delegate,
                'Nouveau Plan de Coaching',
                'Votre manager a enregistré un nouveau plan d\'action.',
                'coaching_plan',
                ['session_id' => $session->id]
            );
        }

        return response()->json($session->load('supervisor', 'delegate'), 201);
    }

    #[OA\Post(
        path: '/api/v1/activities/duo',
        summary: 'Planifier une visite conjointe (Duo)',
        security: [['bearerAuth' => []]],
        tags: ['Coaching'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['delegate_id', 'contact_id', 'date_activite'],
                properties: [
                    new OA\Property(property: 'delegate_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'date_activite', type: 'string', format: 'date'),
                    new OA\Property(property: 'heure_debut', type: 'string', example: '14:00'),
                    new OA\Property(property: 'notes', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Visite en duo créée'),
        ]
    )]
    public function scheduleDuoVisit(Request $request)
    {
        $validated = $request->validate([
            'delegate_id' => 'required|uuid|exists:users,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'date_activite' => 'required|date',
            'heure_debut' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string'
        ]);

        $activity = Activity::create([
            'user_id' => $validated['delegate_id'],
            'accompanying_user_id' => Auth::id(),
            'contact_id' => $validated['contact_id'],
            'activity_type' => 'visite',
            'statut' => 'planifiee',
            'date_activite' => $validated['date_activite'],
            'heure_debut' => $validated['heure_debut'] ?? null,
            'notes' => $validated['notes'] ?? 'Visite en Duo',
        ]);

        $delegate = User::find($validated['delegate_id']);
        app(NotificationService::class)->send(
            $delegate,
            'Visite en Duo programmée',
            'Votre manager a planifié une visite conjointe avec vous.',
            'duo_visit',
            ['activity_id' => $activity->id]
        );

        return response()->json($activity->load('delegue', 'accompanyingUser', 'contact'), 201);
    }
}
