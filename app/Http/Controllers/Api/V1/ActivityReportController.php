<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Resources\ActivityResource;

#[OA\Tag(name: 'Activity Reports', description: 'Gestion des comptes-rendus de visites')]
class ActivityReportController extends Controller
{
    #[OA\Put(
        path: '/api/v1/activities/{activity}/report',
        summary: 'Soumettre ou modifier un compte-rendu',
        security: [['bearerAuth' => []]],
        tags: ['Activity Reports'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'report_content', type: 'string'),
                    new OA\Property(property: 'report_status', type: 'string', enum: ['draft', 'submitted'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu mis à jour')
        ]
    )]
    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'report_content' => 'nullable|string',
            'report_status'  => 'required|in:draft,submitted'
        ]);

        abort_unless((string) $activity->user_id === (string) auth()->id(), 403, 'Seul le délégué de cette visite peut rédiger son compte-rendu.');
        abort_if($activity->report_status === 'validated', 422, 'Ce compte-rendu a déjà été validé par votre responsable.');

        // RÈGLE : Si on tente de soumettre le rapport d'aujourd'hui mais qu'il existe des rapports non soumis des jours précédents
        if ($validated['report_status'] === 'submitted') {
            $activityDate = Carbon::parse($activity->date_activite ?? $activity->created_at);
            $todayStart = Carbon::today()->startOfDay();

            // Si la visite est celle d'aujourd'hui ou récente
            if ($activityDate->gte($todayStart)) {
                $userId = $activity->user_id ?? auth()->id();
                // Comptes-rendus en retard : visites des jours précédents effectivement démarrées ou réalisées
                // dont le rapport n'a pas été soumis (ou a été rejeté et doit être corrigé)
                $unsubmittedPastCount = Activity::where('user_id', $userId)
                    ->where('id', '!=', $activity->id)
                    ->whereDate('date_activite', '<', $todayStart)
                    ->whereIn('statut', ['en_cours', 'realisee'])
                    ->where(function ($q) {
                        $q->whereNull('report_status')
                          ->orWhereIn('report_status', ['draft', 'rejected']);
                    })
                    ->count();

                if ($unsubmittedPastCount > 0) {
                    NotificationController::sendNotification(
                        $userId,
                        'Rapports des jours précédents non soumis',
                        "Vous avez {$unsubmittedPastCount} compte-rendu(s) de visite en retard. Vous devez impérativement les soumettre avant de soumettre celui d'aujourd'hui.",
                        'warning',
                        'delegate',
                        ['activity_id' => (string) $activity->id]
                    );

                    return response()->json([
                        'error' => 'REPORTS_BLOCKED_UNSUBMITTED_PAST',
                        'message' => "Impossible de soumettre le compte-rendu d'aujourd'hui : Vous avez {$unsubmittedPastCount} compte-rendu(s) de visite des jours précédents (hier ou antérieurs) non soumis. Veuillez d'abord soumettre vos rapports en retard.",
                        'unsubmitted_count' => $unsubmittedPastCount,
                    ], 422);
                }
            }
        }

        $data = $validated;
        if ($validated['report_status'] === 'submitted') {
            $data['statut'] = 'realisee';
            // Nouvelle soumission : l'ancien motif de rejet ne s'applique plus
            $data['rejection_reason'] = null;
        }
        if ($request->has('notes') && !empty($request->input('notes'))) {
            $data['notes'] = $request->input('notes');
        }
        if ($request->has('signature') && !empty($request->input('signature'))) {
            $data['signature'] = $request->input('signature');
        }
        // Prochaine visite (date et heure) ; une valeur vide efface celle enregistrée
        if ($request->has('prochain_rdv')) {
            $data['prochain_rdv'] = $request->filled('prochain_rdv') ? $request->input('prochain_rdv') : null;
        }
        if ($request->has('heure_fin') && !empty($request->input('heure_fin'))) {
            $data['heure_fin'] = $request->input('heure_fin');
        }
        if ($request->has('duree_minutes') && !empty($request->input('duree_minutes'))) {
            $data['duree_minutes'] = $request->input('duree_minutes');
        }
        if ($request->has('gps_fin_lat') && !empty($request->input('gps_fin_lat'))) {
            $data['gps_fin_lat'] = $request->input('gps_fin_lat');
        }
        if ($request->has('gps_fin_lng') && !empty($request->input('gps_fin_lng'))) {
            $data['gps_fin_lng'] = $request->input('gps_fin_lng');
        }

        // Résultat global et prochaine action : conservés dans meta pour la consultation du compte-rendu
        $meta = $activity->meta ?? [];
        foreach (['resultat' => 'resultat', 'actions' => 'prochaine_action'] as $input => $key) {
            if ($request->has($input)) {
                $value = trim((string) $request->input($input));
                if ($value === '') {
                    unset($meta[$key]);
                } else {
                    $meta[$key] = $value;
                }
            }
        }
        $data['meta'] = $meta;

        $activity->update($data);

        if ($activity->statut === 'realisee') {
            $activity->lock();
        }

        if ($validated['report_status'] === 'submitted') {
            $user = $activity->delegue ?? $activity->user ?? auth()->user();
            NotificationController::notifyUserAndManagers(
                $user ?? auth()->id(),
                'Nouveau compte-rendu de visite soumis',
                "Le compte-rendu de visite a été soumis avec succès.",
                'report',
                ['activity_id' => (string) $activity->id]
            );
        }

        return response()->json(new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products', 'sampleMovements.product', 'objections.product'])));
    }

    #[OA\Post(
        path: '/api/v1/activities/{activity}/report/validate',
        summary: 'Valider ou rejeter un compte-rendu (Manager)',
        security: [['bearerAuth' => []]],
        tags: ['Activity Reports'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['validated', 'rejected']),
                    new OA\Property(property: 'rejection_reason', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu traité')
        ]
    )]
    public function validateReport(Request $request, Activity $activity)
    {
        $user = $request->user();
        abort_unless(
            $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']) || $user->manages($activity->user_id),
            403,
            "Ce compte-rendu n'appartient pas à un délégué de votre équipe."
        );

        $status = $request->input('status') ?? ($request->input('action') === 'validate' || $request->input('action') === 'validated' ? 'validated' : ($request->input('action') === 'reject' || $request->input('action') === 'rejected' ? 'rejected' : null));
        $request->merge(['status' => $status]);

        $validated = $request->validate([
            'status' => 'required|in:validated,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string'
        ]);

        $activity->update([
            'report_status' => $validated['status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'validated_by_id' => Auth::id(),
            'validation_date' => Carbon::now()
        ]);

        // Notification au délégué & manager
        $title = $validated['status'] === 'validated' ? 'Compte-rendu validé' : 'Compte-rendu rejeté';
        $message = "Votre compte-rendu a été {$validated['status']}.";
        if ($validated['status'] === 'rejected') {
            $message .= " Motif : " . $validated['rejection_reason'];
        }

        NotificationController::sendNotification(
            $activity->user_id ?? $activity->user,
            $title,
            $message,
            'report_validation',
            'delegate',
            ['activity_id' => (string) $activity->id]
        );

        return response()->json($activity);
    }
}
