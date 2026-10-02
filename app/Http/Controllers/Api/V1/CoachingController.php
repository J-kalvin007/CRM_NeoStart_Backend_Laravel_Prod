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
        $user = Auth::user();
        abort_unless($id === $user->id || $user->manages($id), 403, "Ce délégué ne fait pas partie de votre équipe.");

        $sessions = CoachingSession::with(['supervisor', 'activity'])
            ->where('delegate_id', $id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($sessions);
    }

    /**
     * Sessions DUO visibles par l'utilisateur : celles qu'il anime (superviseur),
     * celles de son équipe, ou celles où il est le délégué accompagné.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $teamIds = $user->teamMemberIds();

        $sessions = CoachingSession::with(['supervisor', 'delegate', 'activity'])
            ->where(function ($q) use ($user, $teamIds) {
                $q->where('supervisor_id', $user->id)
                  ->orWhere('delegate_id', $user->id)
                  ->orWhereIn('delegate_id', $teamIds);
            })
            ->when($request->query('delegate_id'), fn ($q, $v) => $q->where('delegate_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->whereHas('activity.duoActivityType', fn ($t) => $t->where('code', $v)))
            ->when($request->boolean('this_month'), fn ($q) => $q->whereBetween('date_duo', [now()->startOfMonth(), now()->endOfMonth()]))
            ->orderByDesc('date_duo')
            ->get();

        return response()->json(['data' => $sessions]);
    }

    /**
     * Refus d'une session DUO (par le délégué concerné ou son superviseur), motif obligatoire.
     */
    public function refuseSession(Request $request, string $id)
    {
        $user = Auth::user();
        $session = CoachingSession::findOrFail($id);
        abort_unless(
            in_array($user->id, [$session->delegate_id, $session->supervisor_id], true) || $user->manages($session->delegate_id),
            403,
            "Vous n'êtes pas concerné par cette session."
        );

        $reason = trim((string) ($request->input('rejection_reason') ?? $request->input('motif')));
        if (mb_strlen($reason) < 2) {
            return response()->json(['message' => 'Le motif de refus est obligatoire.', 'errors' => ['rejection_reason' => ['Le motif de refus est obligatoire.']]], 422);
        }

        $session->update(['status' => 'refused', 'commentaires_superviseur' => $reason]);

        // L'accompagnement de la visite est refusé (la visite reste planifiée)
        if ($session->activity_id) {
            $refusedByDelegate = $user->id === $session->delegate_id;
            \App\Models\Activity::withoutGlobalScopes()
                ->where('id', $session->activity_id)
                ->where('duo_user_id', $session->supervisor_id)
                ->update($refusedByDelegate
                    ? ['duo_delegue_status' => 'rejected', 'duo_delegue_rejection_reason' => $reason]
                    : ['duo_status' => 'rejected', 'duo_rejection_reason' => $reason, 'is_duo' => false]);
        }

        $notify = User::withoutGlobalScopes()->find($user->id === $session->delegate_id ? $session->supervisor_id : $session->delegate_id);
        if ($notify) {
            NotificationController::sendNotification(
                $notify,
                'Visite DUO refusée',
                "{$user->name} a refusé la visite DUO du " . $session->date_duo?->format('d/m/Y') . " : {$reason}",
                'coaching',
                $user->id === $session->delegate_id ? 'superviseur' : 'delegue',
                ['session_id' => (string) $session->id]
            );
        }

        return response()->json(['message' => 'Session DUO refusée.', 'data' => $session]);
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
        // Deux usages : planification d'un DUO (date, zone, motif, objectifs)
        // ou fiche d'évaluation individuelle (points forts, axes de progrès, plan d'action)
        $validated = $request->validate([
            'delegate_id' => 'required|uuid|exists:users,id',
            'date_duo' => 'nullable|date',
            'zone' => 'nullable|string',
            'motif' => 'nullable|string',
            'objectifs_prevus' => 'nullable|array',
            'strengths' => 'nullable|string',
            'weaknesses' => 'nullable|string',
            'action_plan' => 'nullable|string',
            'next_review_date' => 'nullable|date',
        ]);
        $isEvaluation = empty($validated['date_duo']) && empty($validated['objectifs_prevus']);
        $validated['date_duo'] ??= now()->toDateString();
        $validated['zone'] ??= '';
        $validated['motif'] ??= $isEvaluation ? 'Évaluation individuelle' : 'Visite DUO';
        $validated['objectifs_prevus'] ??= [];

        abort_unless(Auth::user()->manages($validated['delegate_id']), 403, "Ce délégué ne fait pas partie de votre équipe.");

        $validated['supervisor_id'] = Auth::id();
        $validated['objectifs_prevus_json'] = $validated['objectifs_prevus'];
        unset($validated['objectifs_prevus']);
        $validated['status'] = 'active';

        $session = CoachingSession::create($validated);

        $delegate = User::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->find($session->delegate_id);
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                $isEvaluation ? 'Nouveau plan d\'action de coaching' : 'Visite en Duo programmée',
                $isEvaluation
                    ? Auth::user()->name . ' a enregistré votre évaluation et votre plan d\'action.'
                    : 'Votre manager a planifié une visite conjointe avec vous.',
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
        abort_unless($session->supervisor_id === Auth::id(), 403, 'Seul le superviseur qui anime le duo peut le clôturer.');

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
            'status' => 'completed',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Rapport de duo clôturé et enregistré.',
            'data' => $session
        ], 200);
    }

    public function show(string $id)
    {
        $session = CoachingSession::with(['supervisor', 'delegate', 'activity'])->findOrFail($id);
        $user = Auth::user();
        abort_unless(
            in_array($user->id, [$session->supervisor_id, $session->delegate_id], true) || $user->manages($session->delegate_id),
            403,
            "Vous n'êtes pas concerné par cette session."
        );

        return response()->json(['data' => $session]);
    }

    /**
     * Modifier une session (superviseur qui l'anime) : date, zone, motif, objectifs, plan d'action…
     */
    public function update(Request $request, string $id)
    {
        $session = CoachingSession::findOrFail($id);
        abort_unless($session->supervisor_id === Auth::id(), 403, 'Seul le superviseur qui anime la session peut la modifier.');

        $validated = $request->validate([
            'date_duo'                 => 'nullable|date',
            'zone'                     => 'nullable|string',
            'motif'                    => 'nullable|string',
            'objectifs_prevus'         => 'nullable|array',
            'resultats_constates'      => 'nullable|array',
            'taux_atteinte_pct'        => 'nullable|numeric|min:0|max:100',
            'commentaires_superviseur' => 'nullable|string',
            'plan_action_recommande'   => 'nullable|string',
            'action_plan'              => 'nullable|string',
            'strengths'                => 'nullable|string',
            'weaknesses'               => 'nullable|string',
            'next_review_date'         => 'nullable|date',
        ]);

        if (array_key_exists('objectifs_prevus', $validated)) {
            $validated['objectifs_prevus_json'] = $validated['objectifs_prevus'];
            unset($validated['objectifs_prevus']);
        }
        if (array_key_exists('resultats_constates', $validated)) {
            $validated['resultats_constates_json'] = $validated['resultats_constates'];
            unset($validated['resultats_constates']);
        }

        $session->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json(['message' => 'Session mise à jour.', 'data' => $session->fresh(['supervisor', 'delegate', 'activity'])]);
    }

    /**
     * Supprimer une session (superviseur qui l'anime). Si elle est liée à une visite DUO, le DUO est annulé.
     */
    public function destroy(string $id)
    {
        $session = CoachingSession::findOrFail($id);
        abort_unless($session->supervisor_id === Auth::id(), 403, 'Seul le superviseur qui anime la session peut la supprimer.');

        if ($session->activity_id) {
            Activity::withoutGlobalScopes()
                ->where('id', $session->activity_id)
                ->where('duo_user_id', $session->supervisor_id)
                ->update(['is_duo' => false, 'duo_status' => 'cancelled']);
        }
        $session->delete();

        return response()->json(['message' => 'Session supprimée.']);
    }
}
