<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\CoachingSession;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Coaching', description: 'Gestion des sessions de coaching et visites en duo')]
class CoachingController extends Controller
{
    private function filterUserCountry(Builder $users, string $value): Builder
    {
        return $users->whereHas('country', fn ($country) => $country->where(
            fn ($match) => $match->where('id', $value)->orWhere('name', $value)->orWhere('iso_code', $value)
        ));
    }

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

        // Profil et indicateurs réels du mois du délégué (mêmes règles que le tableau de bord superviseur)
        $delegate = User::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['roles'])
            ->findOrFail($id);
        $dash = app(SupervisorDashboardController::class);
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();
        $month = fn () => $dash->visitQuery([$id])->whereBetween('date_activite', [$start, $end]);

        $done = $month()->whereIn('statut', SupervisorDashboardController::COMPLETED)->count();
        $planned = $month()->whereNotIn('statut', ['annulee', 'brouillon'])->count();
        $missed = $month()->where('statut', 'manquee')->count();

        $role = $delegate->roles->first()?->name;

        return response()->json([
            'sessions' => $sessions,
            'delegate' => [
                'id' => (string) $delegate->id,
                'name' => $delegate->name,
                'role' => $role ? ucfirst(str_replace(['_', '-'], ' ', $role)) : null,
                'sector' => $dash->delegateSector($delegate),
            ],
            'kpis' => [
                'visits_done' => $done,
                'visits_target' => $dash->weeklyTarget() * 4,
                'no_show_rate' => $planned > 0 ? round($missed / $planned * 100, 1) : 0,
                'coverage_rate' => $dash->coverageRate([$id], $start, $end),
            ],
        ]);
    }

    /**
     * Sessions DUO visibles par l'utilisateur : celles qu'il anime (superviseur),
     * celles de son équipe, ou celles où il est le délégué accompagné.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin', 'super-admin']) || $user->can('scope:global');
        $teamIds = $user->teamMemberIds();

        $sessions = CoachingSession::with([
                'supervisor', 'delegate', 'evaluator', 'evaluated', 
                'activity.contact', 'activity.account', 'delegate.country', 'evaluated.country'
            ])
            ->where(function ($q) use ($user, $teamIds, $isGlobal) {
                if ($isGlobal) return;
                $q->where('supervisor_id', $user->id)
                  ->orWhere('delegate_id', $user->id)
                  ->orWhere('evaluator_id', $user->id)
                  ->orWhere('evaluated_id', $user->id)
                  ->orWhereIn('delegate_id', $teamIds)
                  ->orWhereIn('evaluated_id', $teamIds);
            })
            ->when($request->query('session_type'), fn ($q, $v) => $q->where('session_type', $v))
            ->when($request->query('delegate_id'), fn ($q, $v) => $q->where(fn($sub) => $sub->where('delegate_id', $v)->orWhere('evaluated_id', $v)))
            ->when($request->query('evaluated_id'), fn ($q, $v) => $q->where('evaluated_id', $v))
            ->when($request->query('supervisor_id') ?? $request->query('evaluator_id'), fn ($q, $v) => $q->where(fn($sub) => $sub->where('supervisor_id', $v)->orWhere('evaluator_id', $v)))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('country') ?? $request->query('pays'), function ($q, $pays) {
                $q->whereHas('delegate', fn ($u) => $this->filterUserCountry($u, $pays));
            })
            ->when($request->query('type'), fn ($q, $v) => $q->whereHas('activity.duoActivityType', fn ($t) => $t->where('code', $v)))
            ->when($request->boolean('this_month'), fn ($q) => $q->whereBetween('date_duo', [now()->startOfMonth(), now()->endOfMonth()]))
            ->when($request->query('sort_by') === 'note_desc', fn ($q) => $q->orderByDesc('note_sur_20'))
            ->when($request->query('sort_by') === 'note_asc', fn ($q) => $q->orderBy('note_sur_20'))
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

        abort_unless(
            Auth::user()->manages($validated['delegate_id']) || 
            Auth::user()->hasAnyRole(['superadmin', 'admin', 'super-admin', 'superviseur', 'supervisor', 'manager', 'sales_manager', 'sales-manager', 'directeur-commercial', 'directeur_commercial']) || 
            Auth::user()->can('scope:global'), 
            403, 
            "Ce délégué ne fait pas partie de votre équipe."
        );

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
        $user = Auth::user();
        abort_unless(
            $session->supervisor_id === $user->id || 
            $session->evaluator_id === $user->id || 
            $user->manages($session->delegate_id) ||
            $user->hasAnyRole(['superadmin', 'admin', 'super-admin', 'directeur-commercial', 'directeur_commercial', 'superviseur', 'supervisor', 'manager', 'sales_manager', 'sales-manager']) ||
            $user->can('scope:global'),
            403,
            'Seul l\'évaluateur ou un responsable peut clôturer cette session.'
        );

        $validated = $request->validate([
            'resultats_constates'      => 'nullable|array',
            'taux_atteinte_pct'        => 'nullable|numeric|min:0|max:100',
            'note_sur_20'              => 'nullable|numeric|min:0|max:20',
            'note_globale'             => 'nullable|numeric|min:0|max:100',
            'respect_canevas_pct'      => 'nullable|numeric|min:0|max:100',
            'canevas_evaluation'       => 'nullable|array',
            'points_forts'             => 'nullable|string',
            'axes_amelioration'        => 'nullable|string',
            'commentaires_superviseur' => 'required|string',
            'plan_action_recommande'   => 'nullable|string',
        ]);

        $noteSur20 = $validated['note_sur_20'] ?? null;
        $canevas = $validated['canevas_evaluation'] ?? null;
        if ($noteSur20 === null && !empty($canevas) && is_array($canevas)) {
            $totalPoints = 0;
            $maxPoints = 0;
            foreach ($canevas as $c) {
                $score = floatval($c['score'] ?? 0);
                $max = floatval($c['max_score'] ?? 5);
                $totalPoints += $score;
                $maxPoints += ($max > 0 ? $max : 5);
            }
            if ($maxPoints > 0) {
                $noteSur20 = round(($totalPoints / $maxPoints) * 20, 2);
            }
        }

        $tauxAtteinte = $validated['taux_atteinte_pct'] 
            ?? $validated['note_globale'] 
            ?? ($noteSur20 !== null ? round($noteSur20 * 5, 1) : 0);

        $respectCanevas = $validated['respect_canevas_pct'] ?? $tauxAtteinte;

        $updateData = [
            'status'                   => 'completed',
            'commentaires_superviseur' => $validated['commentaires_superviseur'],
            'plan_action_recommande'   => $validated['plan_action_recommande'] ?? null,
            'taux_atteinte_pct'        => $tauxAtteinte,
            'note_globale'             => $tauxAtteinte,
            'note_sur_20'              => $noteSur20,
            'respect_canevas_pct'      => $respectCanevas,
        ];
        if (!empty($validated['resultats_constates'])) {
            $updateData['resultats_constates_json'] = $validated['resultats_constates'];
        }
        if (!empty($canevas)) {
            $updateData['canevas_evaluation_json'] = $canevas;
        }
        if (!empty($validated['points_forts'])) {
            $updateData['points_forts'] = $validated['points_forts'];
            $updateData['strengths'] = $validated['points_forts'];
        }
        if (!empty($validated['axes_amelioration'])) {
            $updateData['axes_amelioration'] = $validated['axes_amelioration'];
            $updateData['weaknesses'] = $validated['axes_amelioration'];
        }

        $session->update($updateData);

        // Notifier le collaborateur évalué
        $targetUserId = $session->evaluated_id ?: $session->delegate_id;
        $evaluatedUser = User::withoutGlobalScopes()->find($targetUserId);
        if ($evaluatedUser) {
            $isTrio = strtolower((string) $session->session_type) === 'trio';
            $title = $isTrio ? 'Évaluation TRIO enregistrée' : 'Évaluation DUO enregistrée';
            $noteDisplay = $noteSur20 !== null ? "{$noteSur20}/20" : "{$tauxAtteinte}%";
            $msg = $isTrio 
                ? "Votre DV {$user->name} a finalisé votre évaluation TRIO. Note attribuée: {$noteDisplay}."
                : "Votre superviseur {$user->name} a finalisé votre évaluation DUO (Canevas de visite). Note attribuée: {$noteDisplay}.";

            NotificationController::sendNotification(
                $evaluatedUser,
                $title,
                $msg,
                'coaching',
                $isTrio ? 'superviseur' : 'delegue',
                ['session_id' => (string) $session->id, 'note' => $noteSur20]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Évaluation clôturée et enregistrée avec succès.',
            'data' => $session->fresh(['supervisor', 'delegate', 'evaluator', 'evaluated', 'activity'])
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

    /**
     * Mes Évaluations : accessible par le collaborateur connecté pour voir ses notes et feedbacks
     */
    public function myEvaluations(Request $request)
    {
        $user = Auth::user();

        // Récupère les évaluations où l'utilisateur connecté est la personne évaluée
        $evaluations = CoachingSession::with([
                'evaluator', 'supervisor', 'activity.contact', 'activity.account', 'delegate.country', 'evaluated.country'
            ])
            ->where(function ($q) use ($user) {
                $q->where('evaluated_id', $user->id)
                  ->orWhere('delegate_id', $user->id);
            })
            ->where('status', 'completed')
            ->orderByDesc('date_duo')
            ->get();

        $avgScore = $evaluations->avg('note_sur_20');
        $avgCompliance = $evaluations->avg('respect_canevas_pct');
        $latest = $evaluations->first();

        return response()->json([
            'success' => true,
            'data' => $evaluations,
            'kpis' => [
                'total_evaluations'   => $evaluations->count(),
                'avg_note_sur_20'     => $avgScore !== null ? round($avgScore, 1) : null,
                'avg_compliance_pct'  => $avgCompliance !== null ? round($avgCompliance, 1) : null,
                'latest_note_sur_20'  => $latest?->note_sur_20,
                'latest_evaluator'    => $latest?->evaluator?->name ?? $latest?->supervisor?->name ?? 'Responsable',
                'latest_date'         => $latest?->date_duo?->format('d/m/Y'),
                'latest_points_forts' => $latest?->points_forts ?? $latest?->strengths,
                'latest_axes'         => $latest?->axes_amelioration ?? $latest?->weaknesses,
                'latest_plan'         => $latest?->plan_action_recommande ?? $latest?->action_plan,
            ],
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'role' => $user->roles->first()?->name,
            ]
        ]);
    }

    /**
     * Enregistrer et évaluer directement une visite DUO ou TRIO
     */
    public function storeEvaluation(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'session_type'             => 'nullable|string|in:duo,trio,DUO,TRIO',
            'delegate_id'              => 'nullable|uuid|exists:users,id',
            'evaluated_id'             => 'nullable|uuid|exists:users,id',
            'activity_id'              => 'nullable|uuid|exists:activities,id',
            'date_duo'                 => 'nullable|date',
            'zone'                     => 'nullable|string',
            'motif'                    => 'nullable|string',
            'note_sur_20'              => 'nullable|numeric|min:0|max:20',
            'note_globale'             => 'nullable|numeric|min:0|max:100',
            'respect_canevas_pct'      => 'nullable|numeric|min:0|max:100',
            'canevas_evaluation'       => 'nullable|array',
            'points_forts'             => 'nullable|string',
            'axes_amelioration'        => 'nullable|string',
            'commentaires_superviseur' => 'nullable|string',
            'plan_action_recommande'   => 'nullable|string',
        ]);

        $rawType = strtoupper($validated['session_type'] ?? 'DUO');
        $sessionType = in_array($rawType, ['TRIO', 'DUO'], true) ? $rawType : 'DUO';
        $evaluatedId = $validated['evaluated_id'] ?? $validated['delegate_id'] ?? null;

        if (!$evaluatedId) {
            return response()->json(['message' => 'L\'identifiant du collaborateur évalué est requis.'], 422);
        }

        $noteSur20 = $validated['note_sur_20'] ?? null;
        $canevas = $validated['canevas_evaluation'] ?? null;
        if ($noteSur20 === null && !empty($canevas) && is_array($canevas)) {
            $totalPoints = 0;
            $maxPoints = 0;
            foreach ($canevas as $c) {
                $score = floatval($c['score'] ?? 0);
                $max = floatval($c['max_score'] ?? 5);
                $totalPoints += $score;
                $maxPoints += ($max > 0 ? $max : 5);
            }
            if ($maxPoints > 0) {
                $noteSur20 = round(($totalPoints / $maxPoints) * 20, 2);
            }
        }

        $tauxAtteinte = $validated['taux_atteinte_pct'] 
            ?? $validated['note_globale'] 
            ?? ($noteSur20 !== null ? round($noteSur20 * 5, 1) : 0);

        $respectCanevas = $validated['respect_canevas_pct'] ?? $tauxAtteinte;
        $commentaires = $validated['commentaires_superviseur'] ?? $validated['points_forts'] ?? 'Évaluation validée';

        $session = CoachingSession::create([
            'session_type'             => $sessionType,
            'supervisor_id'            => $user->id,
            'evaluator_id'             => $user->id,
            'delegate_id'              => $evaluatedId,
            'evaluated_id'             => $evaluatedId,
            'activity_id'              => $validated['activity_id'] ?? null,
            'date_duo'                 => $validated['date_duo'] ?? now()->toDateString(),
            'zone'                     => $validated['zone'] ?? '',
            'motif'                    => $validated['motif'] ?? ($sessionType === 'TRIO' ? 'Évaluation TRIO' : 'Évaluation DUO'),
            'note_sur_20'              => $noteSur20,
            'note_globale'             => $tauxAtteinte,
            'taux_atteinte_pct'        => $tauxAtteinte,
            'respect_canevas_pct'      => $respectCanevas,
            'canevas_evaluation_json'  => $canevas,
            'points_forts'             => $validated['points_forts'] ?? null,
            'strengths'                => $validated['points_forts'] ?? null,
            'axes_amelioration'        => $validated['axes_amelioration'] ?? null,
            'weaknesses'               => $validated['axes_amelioration'] ?? null,
            'commentaires_superviseur' => $commentaires,
            'plan_action_recommande'   => $validated['plan_action_recommande'] ?? null,
            'action_plan'              => $validated['plan_action_recommande'] ?? null,
            'status'                   => 'completed',
        ]);

        // Notification
        $evaluatedUser = User::withoutGlobalScopes()->find($evaluatedId);
        if ($evaluatedUser) {
            $isTrio = $sessionType === 'TRIO';
            $title = $isTrio ? 'Évaluation TRIO enregistrée' : 'Évaluation DUO enregistrée';
            $noteDisplay = $noteSur20 !== null ? "{$noteSur20}/20" : "{$tauxAtteinte}%";
            $msg = $isTrio 
                ? "Votre DV {$user->name} a évalué votre accompagnement TRIO. Note: {$noteDisplay}."
                : "Votre superviseur {$user->name} a évalué votre respect du canevas de visite. Note: {$noteDisplay}.";

            NotificationController::sendNotification(
                $evaluatedUser,
                $title,
                $msg,
                'coaching',
                $isTrio ? 'superviseur' : 'delegue',
                ['session_id' => (string) $session->id, 'note' => $noteSur20]
            );
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Évaluation enregistrée avec succès.',
            'data' => $session->load(['supervisor', 'delegate', 'evaluator', 'evaluated', 'activity'])
        ], 201);
    }

    /**
     * Tableau de bord pour suivre les DUOs (tri par délégué, notes, canevas)
     */
    public function duoDashboard(Request $request)
    {
        $user = Auth::user();
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin', 'super-admin']) || $user->can('scope:global');
        $teamIds = $user->teamMemberIds();

        // Récupérer les délégués dans le périmètre
        $delegatesQuery = User::withoutGlobalScopes()
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['delegue', 'délégué', 'delegue-medical', 'delegue_medical']))
            ->when(!$isGlobal, fn ($q) => $q->whereIn('id', $teamIds))
            ->when($request->query('country') ?? $request->query('pays'), fn ($q, $p) => $this->filterUserCountry($q, $p))
            ->when($request->query('delegate_id'), fn ($q, $id) => $q->where('id', $id));

        $delegates = $delegatesQuery->with('country')->orderBy('name')->get();

        // Sessions DUO complétées
        $sessionsQuery = CoachingSession::with([
                'evaluator', 'supervisor', 'delegate', 'evaluated',
                'activity.contact', 'activity.account', 'delegate.country', 'evaluated.country'
            ])
            ->where(function ($q) {
                $q->whereIn('session_type', ['duo', 'DUO'])->orWhereNull('session_type');
            })
            ->where('status', 'completed')
            ->when(!$isGlobal, function ($q) use ($user, $teamIds) {
                $q->where(fn($sub) => $sub->where('supervisor_id', $user->id)
                    ->orWhere('evaluator_id', $user->id)
                    ->orWhereIn('delegate_id', $teamIds)
                    ->orWhereIn('evaluated_id', $teamIds));
            })
            ->when($request->query('delegate_id'), fn ($q, $v) => $q->where(fn($s) => $s->where('delegate_id', $v)->orWhere('evaluated_id', $v)))
            ->when($request->query('country') ?? $request->query('pays'), function ($q, $p) {
                $q->whereHas('delegate', fn ($u) => $this->filterUserCountry($u, $p));
            })
            ->when($request->boolean('this_month'), fn ($q) => $q->whereBetween('date_duo', [now()->startOfMonth(), now()->endOfMonth()]))
            ->when($request->query('date_debut'), fn ($q, $d) => $q->whereDate('date_duo', '>=', $d))
            ->when($request->query('date_fin'), fn ($q, $d) => $q->whereDate('date_duo', '<=', $d));

        $sessions = $sessionsQuery->orderByDesc('date_duo')->get();

        $totalDuos = $sessions->count();
        $avgScore = $sessions->avg('note_sur_20');
        $avgCanevas = $sessions->avg('respect_canevas_pct');

        // Répartition et suivi par délégué
        $byDelegate = $delegates->map(function ($del) use ($sessions) {
            $delSessions = $sessions->filter(fn ($s) => $s->delegate_id === $del->id || $s->evaluated_id === $del->id)->values();
            $count = $delSessions->count();
            $avg = $delSessions->avg('note_sur_20');
            $latest = $delSessions->first();

            return [
                'delegate_id'         => (string) $del->id,
                'delegate_name'       => $del->name,
                'delegate_email'      => $del->email,
                'delegate_country'    => $del->country?->name ?? '—',
                'duos_count'          => $count,
                'avg_note_sur_20'     => $avg !== null ? round($avg, 1) : null,
                'avg_respect_canevas' => $delSessions->avg('respect_canevas_pct'),
                'latest_note_sur_20'  => $latest?->note_sur_20,
                'latest_date'         => $latest?->date_duo?->format('d/m/Y'),
                'latest_feedback'     => $latest?->commentaires_superviseur,
                'latest_plan'         => $latest?->plan_action_recommande ?? $latest?->action_plan,
                'evaluations'         => $delSessions,
            ];
        });

        // Tri paramétrable
        $sortBy = $request->query('sort_by', 'delegate');
        if ($sortBy === 'note_desc') {
            $byDelegate = $byDelegate->sortByDesc(fn($d) => $d['avg_note_sur_20'] ?? -1)->values();
        } elseif ($sortBy === 'note_asc') {
            $byDelegate = $byDelegate->sortBy(fn($d) => $d['avg_note_sur_20'] !== null ? $d['avg_note_sur_20'] : 99)->values();
        } elseif ($sortBy === 'count_desc') {
            $byDelegate = $byDelegate->sortByDesc('duos_count')->values();
        } else {
            $byDelegate = $byDelegate->sortBy('delegate_name')->values();
        }

        return response()->json([
            'success'   => true,
            'kpis' => [
                'total_duos'             => $totalDuos,
                'avg_note_sur_20'        => $avgScore !== null ? round($avgScore, 1) : null,
                'avg_respect_canevas'    => $avgCanevas !== null ? round($avgCanevas, 1) : null,
                'delegates_count'        => $delegates->count(),
                'evaluated_count'        => $byDelegate->filter(fn($d) => $d['duos_count'] > 0)->count(),
            ],
            'delegates' => $byDelegate,
            'sessions'  => $sessions,
        ]);
    }

    /**
     * Tableau de bord pour suivre les TRIOs (évaluation des superviseurs par les DVs)
     */
    public function trioDashboard(Request $request)
    {
        $user = Auth::user();
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin', 'super-admin', 'directeur-commercial', 'directeur_commercial']) || $user->can('scope:global');
        $teamIds = $user->teamMemberIds();

        // Récupérer les superviseurs
        $supervisorsQuery = User::withoutGlobalScopes()
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['superviseur', 'supervisor', 'manager']))
            ->when(!$isGlobal, fn ($q) => $q->whereIn('id', $teamIds))
            ->when($request->query('country') ?? $request->query('pays'), fn ($q, $p) => $this->filterUserCountry($q, $p))
            ->when($request->query('supervisor_id'), fn ($q, $id) => $q->where('id', $id));

        $supervisors = $supervisorsQuery->with('country')->orderBy('name')->get();

        // Sessions TRIO complétées
        $sessionsQuery = CoachingSession::with([
                'evaluator', 'supervisor', 'delegate', 'evaluated', 
                'activity.contact', 'activity.account', 'delegate.country', 'evaluated.country'
            ])
            ->whereIn('session_type', ['trio', 'TRIO'])
            ->where('status', 'completed')
            ->when(!$isGlobal, function ($q) use ($user, $teamIds) {
                $q->where(fn($sub) => $sub->where('evaluator_id', $user->id)
                    ->orWhere('supervisor_id', $user->id)
                    ->orWhereIn('evaluated_id', $teamIds)
                    ->orWhereIn('delegate_id', $teamIds));
            })
            ->when($request->query('supervisor_id'), fn ($q, $v) => $q->where(fn($s) => $s->where('evaluated_id', $v)->orWhere('delegate_id', $v)))
            ->when($request->query('country') ?? $request->query('pays'), function ($q, $p) {
                $q->whereHas('delegate', fn ($u) => $this->filterUserCountry($u, $p));
            })
            ->when($request->boolean('this_month'), fn ($q) => $q->whereBetween('date_duo', [now()->startOfMonth(), now()->endOfMonth()]))
            ->when($request->query('date_debut'), fn ($q, $d) => $q->whereDate('date_duo', '>=', $d))
            ->when($request->query('date_fin'), fn ($q, $d) => $q->whereDate('date_duo', '<=', $d));

        $sessions = $sessionsQuery->orderByDesc('date_duo')->get();

        $totalTrios = $sessions->count();
        $avgScore = $sessions->avg('note_sur_20');

        // Répartition par superviseur
        $bySupervisor = $supervisors->map(function ($sup) use ($sessions) {
            $supSessions = $sessions->filter(fn ($s) => $s->evaluated_id === $sup->id || $s->delegate_id === $sup->id)->values();
            $count = $supSessions->count();
            $avg = $supSessions->avg('note_sur_20');
            $latest = $supSessions->first();

            return [
                'supervisor_id'       => (string) $sup->id,
                'supervisor_name'     => $sup->name,
                'supervisor_email'    => $sup->email,
                'supervisor_country'  => $sup->country?->name ?? '—',
                'trios_count'         => $count,
                'avg_note_sur_20'     => $avg !== null ? round($avg, 1) : null,
                'avg_respect_canevas' => $supSessions->avg('respect_canevas_pct'),
                'latest_note_sur_20'  => $latest?->note_sur_20,
                'latest_date'         => $latest?->date_duo?->format('d/m/Y'),
                'latest_feedback'     => $latest?->commentaires_superviseur,
                'latest_plan'         => $latest?->plan_action_recommande ?? $latest?->action_plan,
                'evaluations'         => $supSessions,
            ];
        });

        // Tri
        $sortBy = $request->query('sort_by', 'supervisor');
        if ($sortBy === 'note_desc') {
            $bySupervisor = $bySupervisor->sortByDesc(fn($d) => $d['avg_note_sur_20'] ?? -1)->values();
        } elseif ($sortBy === 'note_asc') {
            $bySupervisor = $bySupervisor->sortBy(fn($d) => $d['avg_note_sur_20'] !== null ? $d['avg_note_sur_20'] : 99)->values();
        } else {
            $bySupervisor = $bySupervisor->sortBy('supervisor_name')->values();
        }

        return response()->json([
            'success'     => true,
            'kpis' => [
                'total_trios'             => $totalTrios,
                'avg_note_sur_20'         => $avgScore !== null ? round($avgScore, 1) : null,
                'avg_respect_canevas'     => $sessions->avg('respect_canevas_pct'),
                'supervisors_count'       => $supervisors->count(),
                'evaluated_count'         => $bySupervisor->filter(fn($d) => $d['trios_count'] > 0)->count(),
            ],
            'supervisors' => $bySupervisor,
            'sessions'    => $sessions,
        ]);
    }

    /**
     * Exporter l'état des DUOs en Excel (CSV UTF-8 BOM)
     */
    public function exportDuoExcel(Request $request)
    {
        $dash = $this->duoDashboard($request)->getData(true);
        $sessions = $dash['sessions'] ?? [];

        $csv = "\xEF\xBB\xBF";
        $csv .= "Date,Délégé,Pays,Superviseur,Note /20,Respect Canevas %,Points Forts,Axes d'Amélioration,Commentaires Superviseur,Plan d'Action\n";

        foreach ($sessions as $s) {
            $date = !empty($s['date_duo']) ? date('d/m/Y', strtotime($s['date_duo'])) : '';
            $delegue = $s['delegate']['name'] ?? $s['evaluated']['name'] ?? '—';
            $pays = $s['delegate']['country']['name'] ?? '—';
            $superviseur = $s['evaluator']['name'] ?? $s['supervisor']['name'] ?? '—';
            $note = $s['note_sur_20'] !== null ? $s['note_sur_20'] . '/20' : ($s['taux_atteinte_pct'] ? $s['taux_atteinte_pct'] . '%' : '—');
            $canevas = $s['respect_canevas_pct'] ? $s['respect_canevas_pct'] . '%' : '—';
            $forts = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['points_forts'] ?? $s['strengths'] ?? ''));
            $axes = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['axes_amelioration'] ?? $s['weaknesses'] ?? ''));
            $comm = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['commentaires_superviseur'] ?? ''));
            $plan = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['plan_action_recommande'] ?? $s['action_plan'] ?? ''));

            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $date, $delegue, $pays, $superviseur, $note, $canevas, $forts, $axes, $comm, $plan
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport_duos_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Exporter l'état des DUOs en PDF / HTML prêt pour impression
     */
    public function exportDuoPdf(Request $request)
    {
        $dash = $this->duoDashboard($request)->getData(true);
        $kpis = $dash['kpis'] ?? [];
        $sessions = $dash['sessions'] ?? [];

        $rowsHtml = '';
        foreach ($sessions as $s) {
            $date = !empty($s['date_duo']) ? date('d/m/Y', strtotime($s['date_duo'])) : '—';
            $delegue = htmlspecialchars($s['delegate']['name'] ?? $s['evaluated']['name'] ?? '—');
            $pays = htmlspecialchars($s['delegate']['country']['name'] ?? '—');
            $superviseur = htmlspecialchars($s['evaluator']['name'] ?? $s['supervisor']['name'] ?? '—');
            $note = $s['note_sur_20'] !== null ? "<strong>{$s['note_sur_20']}/20</strong>" : ($s['taux_atteinte_pct'] ? "{$s['taux_atteinte_pct']}%" : '—');
            $canevas = $s['respect_canevas_pct'] ? "{$s['respect_canevas_pct']}%" : '—';
            $feedback = htmlspecialchars(mb_strimwidth((string)($s['commentaires_superviseur'] ?? ''), 0, 150, '...'));
            $plan = htmlspecialchars(mb_strimwidth((string)($s['plan_action_recommande'] ?? $s['action_plan'] ?? ''), 0, 100, '...'));

            $rowsHtml .= "<tr>
                <td>{$date}</td>
                <td><strong>{$delegue}</strong><br><small style='color:#64748b;'>{$pays}</small></td>
                <td>{$superviseur}</td>
                <td style='text-align:center;'><span class='badge-score'>{$note}</span></td>
                <td style='text-align:center;'>{$canevas}</td>
                <td style='font-size:11px; color:#334155;'>{$feedback}</td>
                <td style='font-size:11px; color:#0050cb;'>{$plan}</td>
            </tr>";
        }

        $printDate = date('d/m/Y H:i');
        $avgScore = $kpis['avg_note_sur_20'] ?? '—';
        $totalDuos = $kpis['total_duos'] ?? 0;

        $html = "<!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <title>Tableau de Bord DUO — SKYNITY CRM</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; padding: 24px; margin: 0; background: #fff; }
                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0050cb; padding-bottom: 12px; margin-bottom: 16px; }
                .title { font-size: 20px; font-weight: bold; color: #0050cb; margin: 0; }
                .meta { font-size: 12px; color: #64748b; }
                .kpis { display: flex; gap: 16px; margin-bottom: 20px; }
                .kpi-card { flex: 1; padding: 12px 16px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
                .kpi-title { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold; }
                .kpi-value { font-size: 20px; font-weight: bold; color: #0050cb; margin-top: 4px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-align: left; padding: 8px 10px; border-bottom: 1px solid #cbd5e1; }
                td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
                tr:nth-child(even) { background-color: #f8fafc; }
                .badge-score { display: inline-block; padding: 3px 8px; font-size: 12px; font-weight: bold; border-radius: 6px; background: #ecfdf5; color: #059669; }
                .actions { margin-bottom: 16px; }
                .btn-print { background: #0050cb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
                @media print { .actions { display: none; } body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class='actions'>
                <button class='btn-print' onclick='window.print()'>🖨️ Imprimer / Enregistrer en PDF</button>
            </div>
            <div class='header'>
                <div>
                    <h1 class='title'>SKYNITY CRM — Tableau de Bord des Visites en DUO</h1>
                    <div class='meta'>Suivi du Canevas de Visite & Évaluations des Délégués</div>
                </div>
                <div class='meta' style='text-align: right;'>
                    Édité le : {$printDate}<br>
                    Direction Médicale & Commerciale
                </div>
            </div>
            <div class='kpis'>
                <div class='kpi-card'>
                    <div class='kpi-title'>Total DUOs Réalisés</div>
                    <div class='kpi-value'>{$totalDuos}</div>
                </div>
                <div class='kpi-card'>
                    <div class='kpi-title'>Note Moyenne de l'Équipe</div>
                    <div class='kpi-value'>{$avgScore} / 20</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Délégué & Pays</th>
                        <th>Superviseur</th>
                        <th style='text-align:center;'>Note /20</th>
                        <th style='text-align:center;'>Canevas %</th>
                        <th>Commentaires & Points Clés</th>
                        <th>Plan d'Action Recommandé</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </body>
        </html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Exporter l'état des TRIOs en Excel
     */
    public function exportTrioExcel(Request $request)
    {
        $dash = $this->trioDashboard($request)->getData(true);
        $sessions = $dash['sessions'] ?? [];

        $csv = "\xEF\xBB\xBF";
        $csv .= "Date,Superviseur,Pays,Directeur des Ventes (DV),Note /20,Posture Managériale,Feedback DV,Plan d'Action Superviseur\n";

        foreach ($sessions as $s) {
            $date = !empty($s['date_duo']) ? date('d/m/Y', strtotime($s['date_duo'])) : '';
            $superviseur = $s['evaluated']['name'] ?? $s['delegate']['name'] ?? '—';
            $pays = $s['evaluated']['country']['name'] ?? $s['delegate']['country']['name'] ?? '—';
            $dv = $s['evaluator']['name'] ?? $s['supervisor']['name'] ?? '—';
            $note = $s['note_sur_20'] !== null ? $s['note_sur_20'] . '/20' : ($s['taux_atteinte_pct'] ? $s['taux_atteinte_pct'] . '%' : '—');
            $comm = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['commentaires_superviseur'] ?? ''));
            $plan = str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($s['plan_action_recommande'] ?? $s['action_plan'] ?? ''));

            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $date, $superviseur, $pays, $dv, $note, '', $comm, $plan
            );
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport_trios_' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * Exporter l'état des TRIOs en PDF
     */
    public function exportTrioPdf(Request $request)
    {
        $dash = $this->trioDashboard($request)->getData(true);
        $kpis = $dash['kpis'] ?? [];
        $sessions = $dash['sessions'] ?? [];

        $rowsHtml = '';
        foreach ($sessions as $s) {
            $date = !empty($s['date_duo']) ? date('d/m/Y', strtotime($s['date_duo'])) : '—';
            $superviseur = htmlspecialchars($s['evaluated']['name'] ?? $s['delegate']['name'] ?? '—');
            $pays = htmlspecialchars($s['evaluated']['country']['name'] ?? $s['delegate']['country']['name'] ?? '—');
            $dv = htmlspecialchars($s['evaluator']['name'] ?? $s['supervisor']['name'] ?? '—');
            $note = $s['note_sur_20'] !== null ? "<strong>{$s['note_sur_20']}/20</strong>" : ($s['taux_atteinte_pct'] ? "{$s['taux_atteinte_pct']}%" : '—');
            $feedback = htmlspecialchars(mb_strimwidth((string)($s['commentaires_superviseur'] ?? ''), 0, 150, '...'));
            $plan = htmlspecialchars(mb_strimwidth((string)($s['plan_action_recommande'] ?? $s['action_plan'] ?? ''), 0, 100, '...'));

            $rowsHtml .= "<tr>
                <td>{$date}</td>
                <td><strong>{$superviseur}</strong><br><small style='color:#64748b;'>{$pays}</small></td>
                <td>{$dv}</td>
                <td style='text-align:center;'><span class='badge-score'>{$note}</span></td>
                <td style='font-size:11px; color:#334155;'>{$feedback}</td>
                <td style='font-size:11px; color:#0050cb;'>{$plan}</td>
            </tr>";
        }

        $printDate = date('d/m/Y H:i');
        $avgScore = $kpis['avg_note_sur_20'] ?? '—';
        $totalTrios = $kpis['total_trios'] ?? 0;

        $html = "<!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <title>Tableau de Bord TRIO — SKYNITY CRM</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; padding: 24px; margin: 0; background: #fff; }
                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0050cb; padding-bottom: 12px; margin-bottom: 16px; }
                .title { font-size: 20px; font-weight: bold; color: #0050cb; margin: 0; }
                .meta { font-size: 12px; color: #64748b; }
                .kpis { display: flex; gap: 16px; margin-bottom: 20px; }
                .kpi-card { flex: 1; padding: 12px 16px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
                .kpi-title { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold; }
                .kpi-value { font-size: 20px; font-weight: bold; color: #0050cb; margin-top: 4px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 12px; }
                th { background-color: #f1f5f9; color: #334155; font-weight: bold; text-align: left; padding: 8px 10px; border-bottom: 1px solid #cbd5e1; }
                td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
                tr:nth-child(even) { background-color: #f8fafc; }
                .badge-score { display: inline-block; padding: 3px 8px; font-size: 12px; font-weight: bold; border-radius: 6px; background: #ecfdf5; color: #059669; }
                .actions { margin-bottom: 16px; }
                .btn-print { background: #0050cb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
                @media print { .actions { display: none; } body { padding: 0; } }
            </style>
        </head>
        <body>
            <div class='actions'>
                <button class='btn-print' onclick='window.print()'>🖨️ Imprimer / Enregistrer en PDF</button>
            </div>
            <div class='header'>
                <div>
                    <h1 class='title'>SKYNITY CRM — Tableau de Bord des Visites en TRIO</h1>
                    <div class='meta'>Évaluation des Superviseurs par la Direction des Ventes (DV)</div>
                </div>
                <div class='meta' style='text-align: right;'>
                    Édité le : {$printDate}<br>
                    Direction Commerciale
                </div>
            </div>
            <div class='kpis'>
                <div class='kpi-card'>
                    <div class='kpi-title'>Total TRIOs Réalisés</div>
                    <div class='kpi-value'>{$totalTrios}</div>
                </div>
                <div class='kpi-card'>
                    <div class='kpi-title'>Note Moyenne Superviseurs</div>
                    <div class='kpi-value'>{$avgScore} / 20</div>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Superviseur & Pays</th>
                        <th>Directeur des Ventes</th>
                        <th style='text-align:center;'>Note /20</th>
                        <th>Feedback DV</th>
                        <th>Plan d'Action Superviseur</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </body>
        </html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

