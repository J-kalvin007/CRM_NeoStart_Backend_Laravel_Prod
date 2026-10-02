<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Account;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Validations', description: 'API de modération des fiches en attente')]
class ValidationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/validations/pending',
        summary: 'Lister les créations en attente de validation',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des éléments en attente'),
        ]
    )]
    public function index(Request $request)
    {
        $user = auth()->user();
        // Admin/global : tout ; hiérarchie : uniquement les éléments soumis par les collaborateurs encadrés
        $isGlobal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);
        $teamIds = array_merge([$user->id], $user->teamMemberIds());
        $inTeam = fn ($q) => $isGlobal ? $q : $q->whereIn('user_id', $teamIds);

        $pendingContacts = Contact::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['account', 'user', 'owner'])
            ->tap($inTeam)
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('statut_validation', ['en_attente', 'pending'])
                  ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']);
            })
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => (string) $contact->id,
                    'type' => 'contact',
                    'nom' => $contact->nom,
                    'prenom' => $contact->prenom,
                    'titre' => $contact->titre,
                    'specialite' => $contact->specialite ?? $contact->meta['specialite'] ?? 'Médecin',
                    'telephone' => $contact->telephone,
                    'email' => $contact->email,
                    'etablissement' => $contact->account ? $contact->account->nom : null,
                    'ville' => $contact->account ? $contact->account->ville : null,
                    'created_by' => $contact->owner?->name ?? ($contact->user ? ($contact->user->name ?: $contact->user->email) : 'Délégué Médical'),
                    'created_at' => $contact->created_at,
                ];
            });

        $pendingAccounts = Account::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['user', 'owner'])
            ->tap($inTeam)
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('statut_validation', ['en_attente', 'pending'])
                  ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']);
            })
            ->get()
            ->map(function ($account) {
                return [
                    'id' => (string) $account->id,
                    'type' => 'account',
                    'nom' => $account->nom,
                    'prenom' => null,
                    'titre' => null,
                    'specialite' => 'Établissement',
                    'telephone' => $account->telephone,
                    'email' => $account->email,
                    'etablissement' => $account->nom,
                    'ville' => $account->ville,
                    'created_by' => $account->owner?->name ?? ($account->user ? ($account->user->name ?: $account->user->email) : 'Délégué Médical'),
                    'created_at' => $account->created_at,
                ];
            });

        $pendingActivities = \App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['contact', 'account', 'delegue', 'user'])
            ->tap($inTeam)
            ->where(function ($q) {
                $q->whereIn('report_status', ['submitted', 'soumis', 'en_attente'])
                  ->orWhere(function ($q2) {
                      $q2->whereIn('statut', ['realisee', 'effectuee'])
                         ->where(function ($q3) {
                             $q3->whereNull('report_status')
                                ->orWhereNotIn('report_status', ['validated', 'approved']);
                         });
                  });
            })
            ->get()
            ->map(function ($act) {
                $contactName = $act->contact?->name ?? $act->contact_name ?? 'Dr. Praticien';
                $accountName = $act->account?->name ?? $act->establishment ?? 'Établissement';
                return [
                    'id' => (string) $act->id,
                    'type' => 'activity_report',
                    'activity_type' => $act->activity_type,
                    'date_activite' => $act->date_activite?->toDateString(),
                    'heure_debut' => $act->heure_debut,
                    'nom' => $contactName,
                    'etablissement' => $accountName,
                    'report_content' => $act->report_content ?? $act->notes,
                    'statut' => $act->statut,
                    'report_status' => $act->report_status,
                    'created_by' => $act->delegue?->name ?? ($act->user ? ($act->user->name ?: $act->user->email) : 'Délégué Médical'),
                    'created_at' => $act->created_at,
                ];
            });

        $pendingTourPlans = \App\Models\TourPlan::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['user', 'delegue'])
            ->tap($inTeam)
            ->whereIn('statut', ['soumis', 'en_attente_validation', 'en_attente'])
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => (string) $plan->id,
                    'type' => 'tour_plan',
                    'nom' => $plan->nom ?? 'Planning Hebdomadaire',
                    'statut' => $plan->statut,
                    'created_by' => $plan->delegue?->name ?? ($plan->user ? ($plan->user->name ?: $plan->user->email) : 'Délégué Médical'),
                    'created_at' => $plan->created_at,
                ];
            });

        $pendingExpenses = \App\Models\Expense::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['user'])
            ->tap($inTeam)
            ->whereIn('status', ['submitted', 'en_attente'])
            ->get()
            ->map(function ($exp) {
                return [
                    'id' => (string) $exp->id,
                    'type' => 'expense',
                    'nom' => $exp->title,
                    'title' => $exp->title,
                    'category' => $exp->type,
                    'amount' => $exp->amount,
                    'currency' => $exp->currency ?? 'FCFA',
                    'expense_date' => $exp->expense_date?->toDateString(),
                    'created_by' => $exp->user ? ($exp->user->name ?: $exp->user->email) : 'Délégué Médical',
                    'user' => $exp->user ? ['id' => (string) $exp->user->id, 'name' => $exp->user->name, 'email' => $exp->user->email] : null,
                    'created_at' => $exp->created_at,
                ];
            });

        $pendingSampleRequests = \App\Models\SampleRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['product', 'user'])
            ->tap($inTeam)
            ->whereIn('status', ['pending', 'en_attente'])
            ->get()
            ->map(function ($sr) {
                return [
                    'id' => (string) $sr->id,
                    'type' => 'sample',
                    'nom' => $sr->product ? ($sr->product->nom ?: $sr->product->name) : 'Échantillon Médical',
                    'product' => $sr->product ? ($sr->product->nom ?: $sr->product->name) : 'Échantillon Médical',
                    'quantityRequested' => "{$sr->quantity} boîtes",
                    'stockCurrent' => 'Demande de réassort',
                    'reason' => $sr->notes ?? $sr->motif ?? 'Réassort de stock',
                    'created_by' => $sr->user ? ($sr->user->name ?: $sr->user->email) : 'Délégué Médical',
                    'created_at' => $sr->created_at,
                    'isRequest' => true,
                ];
            });

        $pendingDelegateRequests = \App\Models\DelegateRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with(['user', 'contact'])
            ->tap($inTeam)
            ->whereIn('status', ['pending_manager_approval', 'en_attente', 'pending', 'soumis'])
            ->get()
            ->map(function ($req) {
                return [
                    'id' => (string) $req->id,
                    'type' => 'request',
                    'nom' => $req->subject ?? $req->objet ?? $req->request_type ?? 'Demande terrain',
                    'title' => $req->subject ?? $req->objet ?? $req->request_type ?? 'Demande terrain',
                    'description' => $req->description ?? $req->reason ?? '',
                    'request_type' => $req->request_type ?? $req->type_demande ?? 'Demande',
                    'statut' => 'en_attente_superviseur',
                    'created_by' => $req->user ? ($req->user->name ?: $req->user->email) : 'Délégué Médical',
                    'created_at' => $req->created_at,
                ];
            });

        $pending = $pendingContacts
            ->concat($pendingAccounts)
            ->concat($pendingActivities)
            ->concat($pendingTourPlans)
            ->concat($pendingExpenses)
            ->concat($pendingSampleRequests)
            ->concat($pendingDelegateRequests)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'data' => $pending
        ]);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/approve',
        summary: 'Approuver une création en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Création approuvée'),
        ]
    )]
    public function approve(Request $request, string $type, string $id)
    {
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['delegue', 'delegue_medical', 'delegate', 'Délégué', 'Délégué Médical'])) {
            return response()->json([
                'message' => 'Action non autorisée. Les délégués ne peuvent pas valider de fiches.'
            ], 403);
        }

        if (in_array($type, ['visit', 'activity_report'])) {
            $activity = \App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($activity->user_id);
            // Visite planifiée : validation du créneau ; compte-rendu : validation du rapport,
            // la visite reste « réalisée » (son statut n'est pas écrasé)
            $activity->update(array_merge(
                $type === 'activity_report'
                    ? ['report_status' => 'validated']
                    : ['statut' => 'validee'],
                ['rejection_reason' => null, 'validated_by_id' => auth()->id(), 'validation_date' => now()]
            ));

            $recipient = $activity->user ?? $activity->delegue;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Visite / Compte-rendu validé',
                    "Votre " . ($type === 'activity_report' ? 'compte-rendu de visite' : 'visite planifiée') . " a été validé(e) par votre responsable.",
                    'validation',
                    'delegate',
                    ['id' => (string) $activity->id, 'type' => $type]
                );
            }

            return response()->json(['message' => 'Activité validée avec succès', 'data' => $activity]);
        }

        if ($type === 'tour_plan') {
            $plan = \App\Models\TourPlan::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($plan->user_id);
            $plan->update([
                'statut' => 'valide',
                'valide_par' => auth()->id(),
                'valide_le' => now(),
            ]);

            // Seules les visites encore en attente sont validées (les visites réalisées, annulées… gardent leur statut)
            \App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                ->where('tour_plan_id', $plan->id)
                ->whereIn('statut', ['brouillon', 'en_attente', 'soumis'])
                ->update(['statut' => 'validee', 'validated_by_id' => auth()->id(), 'validation_date' => now()]);

            $recipient = $plan->delegue ?? $plan->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Planning de tournée validé',
                    "Votre plan de tournée '{$plan->nom}' a été validé par votre superviseur.",
                    'planning',
                    'delegate',
                    ['id' => (string) $plan->id, 'type' => $type]
                );
            }

            return response()->json(['message' => 'Plan de tournée validé avec succès', 'data' => $plan]);
        }

        if ($type === 'expense') {
            $expense = \App\Models\Expense::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($expense->user_id);
            $expense->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $recipient = $expense->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Note de frais validée',
                    "Votre note de frais '{$expense->title}' ({$expense->amount} {$expense->currency}) a été approuvée.",
                    'expense',
                    'delegate',
                    ['id' => (string) $expense->id, 'type' => 'expense']
                );
            }

            return response()->json(['message' => 'Note de frais approuvée avec succès', 'data' => $expense]);
        }

        if (in_array($type, ['sample', 'sample_request'])) {
            $sampleRequest = \App\Models\SampleRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($sampleRequest->user_id);
            $sampleRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
            ]);

            $lotNumber = 'LOT-' . date('Y-m') . '-' . strtoupper(substr(uniqid(), -4));
            $expiryDate = date('Y-m-d', strtotime('+1 year'));

            $stock = \App\Models\SampleStock::firstOrCreate([
                'user_id' => $sampleRequest->user_id,
                'product_id' => $sampleRequest->product_id,
                'lot_number' => $lotNumber
            ], [
                'expiry_date' => $expiryDate,
                'quantity' => 0
            ]);

            $stock->increment('quantity', $sampleRequest->quantity);

            \App\Models\SampleMovement::create([
                'user_id' => $sampleRequest->user_id,
                'product_id' => $sampleRequest->product_id,
                'movement_type' => 'reception',
                'quantity' => $sampleRequest->quantity,
                'remarques' => 'Approuvé par ' . (auth()->user()?->name ?? 'Manager'),
                'lot_number' => $lotNumber,
                'expiry_date' => $expiryDate,
                'date_mouvement' => date('Y-m-d')
            ]);

            $recipient = $sampleRequest->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Demande d\'Échantillons Approuvée',
                    "Votre demande de réassort d'échantillons ({$sampleRequest->quantity} boîtes) a été approuvée.",
                    'sample',
                    'delegate',
                    ['id' => (string) $sampleRequest->id, 'type' => 'sample']
                );
            }

            return response()->json(['message' => 'Demande d\'échantillons approuvée avec succès', 'data' => $sampleRequest]);
        }

        if (in_array($type, ['request', 'delegate_request'])) {
            $req = \App\Models\DelegateRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($req->user_id);
            $req->update([
                'status' => 'approved',
            ]);

            $recipient = $req->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Demande Approuvée',
                    "Votre demande '" . ($req->subject ?? $req->request_type) . "' a été approuvée par votre manager.",
                    'request',
                    'delegate',
                    ['id' => (string) $req->id, 'type' => 'request']
                );
            }

            return response()->json(['message' => 'Demande approuvée avec succès', 'data' => $req]);
        }

        $model = $this->getModel($type, $id);
        $model->update([
            'statut_validation' => 'valide',
            'statut' => 'actif',
        ]);

        $recipient = $model->owner ?? ($model->user_id ? \App\Models\User::find($model->user_id) : null);
        if ($recipient) {
            try {
                app(\App\Services\NotificationService::class)->send(
                    $recipient,
                    ucfirst($type) . ' approuvé(e)',
                    "Votre demande de création de {$type} '{$model->nom}' a été validée par votre manager.",
                    'contact',
                    ['id' => $model->id, 'type' => $type]
                );
            } catch (\Throwable $e) {}

            NotificationController::sendNotification(
                $recipient,
                'Fiche Validée par le Manager',
                "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " a été approuvée.",
                'validation',
                'delegate',
                ['id' => (string) $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' approuvé avec succès', 'data' => $model]);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/reject',
        summary: 'Rejeter une création en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account', 'visit', 'activity_report', 'tour_plan'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Création rejetée'),
        ]
    )]
    public function reject(Request $request, string $type, string $id)
    {
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['delegue', 'delegue_medical', 'delegate', 'Délégué', 'Délégué Médical'])) {
            return response()->json([
                'message' => 'Action non autorisée. Les délégués ne peuvent pas rejeter de fiches.'
            ], 403);
        }

        $reason = $request->input('rejection_reason') ?? $request->input('motif');

        if (in_array($type, ['visit', 'activity_report'])) {
            $activity = \App\Models\Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($activity->user_id);
            // Compte-rendu rejeté : la visite reste réalisée, le délégué corrige son rapport ;
            // visite planifiée refusée : le créneau est refusé
            $activity->update(array_merge(
                $type === 'activity_report'
                    ? ['report_status' => 'rejected', 'is_locked' => false]
                    : ['statut' => 'refusee'],
                ['rejection_reason' => $reason, 'validated_by_id' => auth()->id(), 'validation_date' => now()]
            ));

            $recipient = $activity->user ?? $activity->delegue;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Visite / Compte-rendu refusé',
                    "Votre " . ($type === 'activity_report' ? 'compte-rendu de visite' : 'visite planifiée') . " a été refusé(e)." . ($reason ? " Motif : {$reason}" : ""),
                    'validation',
                    'delegate',
                    ['id' => (string) $activity->id, 'type' => $type]
                );
            }

            return response()->json(['message' => 'Activité refusée avec succès', 'data' => $activity]);
        }

        if ($type === 'tour_plan') {
            $plan = \App\Models\TourPlan::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($plan->user_id);
            $plan->update([
                'statut' => 'rejete',
                'valide_par' => auth()->id(),
                'valide_le' => now(),
                'commentaire_validation' => $reason,
            ]);

            $recipient = $plan->delegue ?? $plan->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Planning de tournée rejeté',
                    "Votre plan de tournée '{$plan->nom}' a été rejeté par votre superviseur." . ($reason ? " Motif : {$reason}" : ""),
                    'planning',
                    'delegate',
                    ['id' => (string) $plan->id, 'type' => $type]
                );
            }

            return response()->json(['message' => 'Plan de tournée rejeté avec succès', 'data' => $plan]);
        }

        if ($type === 'expense') {
            $expense = \App\Models\Expense::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($expense->user_id);
            $expense->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => auth()->id(),
            ]);

            $recipient = $expense->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Note de frais refusée',
                    "Votre note de frais '{$expense->title}' n'a pas été validée par votre superviseur." . ($reason ? " Motif : {$reason}" : ""),
                    'expense',
                    'delegate',
                    ['id' => (string) $expense->id, 'type' => 'expense']
                );
            }

            return response()->json(['message' => 'Note de frais refusée avec succès', 'data' => $expense]);
        }

        if (in_array($type, ['sample', 'sample_request'])) {
            $sampleRequest = \App\Models\SampleRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($sampleRequest->user_id);
            $sampleRequest->update([
                'status' => 'rejected',
                'approved_by' => auth()->id(),
            ]);

            $recipient = $sampleRequest->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Demande d\'Échantillons Refusée',
                    "Votre demande de réassort d'échantillons n'a pas été validée par votre manager." . ($reason ? " Motif : {$reason}" : ""),
                    'sample',
                    'delegate',
                    ['id' => (string) $sampleRequest->id, 'type' => 'sample']
                );
            }

            return response()->json(['message' => 'Demande d\'échantillons refusée avec succès', 'data' => $sampleRequest]);
        }

        if (in_array($type, ['request', 'delegate_request'])) {
            $req = \App\Models\DelegateRequest::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);
            $this->ensureManaged($req->user_id);
            $req->update([
                'status' => 'rejected',
            ]);

            $recipient = $req->user;
            if ($recipient) {
                NotificationController::sendNotification(
                    $recipient,
                    'Demande Refusée',
                    "Votre demande '" . ($req->subject ?? $req->request_type) . "' a été refusée par votre manager." . ($reason ? " Motif : {$reason}" : ""),
                    'request',
                    'delegate',
                    ['id' => (string) $req->id, 'type' => 'request']
                );
            }

            return response()->json(['message' => 'Demande refusée avec succès', 'data' => $req]);
        }

        $model = $this->getModel($type, $id);
        $model->update([
            'statut_validation' => 'rejete',
            'statut' => 'rejete',
        ]);

        $recipient = $model->owner ?? ($model->user_id ? \App\Models\User::find($model->user_id) : null);
        if ($recipient) {
            try {
                app(\App\Services\NotificationService::class)->send(
                    $recipient,
                    ucfirst($type) . ' rejeté(e)',
                    "Votre demande de création de {$type} '{$model->nom}' a été rejetée par votre manager.",
                    'contact',
                    ['id' => $model->id, 'type' => $type]
                );
            } catch (\Throwable $e) {}

            NotificationController::sendNotification(
                $recipient,
                'Fiche Non Validée',
                "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " n'a pas été validée par votre superviseur.",
                'validation',
                'delegate',
                ['id' => (string) $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' rejeté avec succès', 'data' => $model]);
    }

    private function getModel(string $type, string $id)
    {
        if ($type === 'contact') {
            return Contact::findOrFail($id);
        }
        if ($type === 'account') {
            return Account::findOrFail($id);
        }
        abort(400, "Type invalide");
    }

    /**
     * Validation / rejet par lot.
     * Corps : { action: approve|reject, rejection_reason?, items: [ {type, id}, ... ] }
     * Chaque élément passe par approve()/reject() (mêmes contrôles d'équipe et notifications) ;
     * un échec n'interrompt pas le lot. Réponse : compte des réussites + détail par élément.
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|nullable|string|min:2',
            'items' => 'required|array|min:1|max:200',
            'items.*.type' => 'required|string|in:contact,account,visit,activity_report,tour_plan,expense,sample,sample_request,request,delegate_request',
            'items.*.id' => 'required|string',
        ], [
            'rejection_reason.required_if' => 'Le motif du rejet est obligatoire.',
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $sub = $request->duplicate();
            $sub->replace(['rejection_reason' => $validated['rejection_reason'] ?? null]);

            try {
                $response = \Illuminate\Support\Facades\DB::transaction(fn () => $validated['action'] === 'approve'
                    ? $this->approve($sub, $item['type'], $item['id'])
                    : $this->reject($sub, $item['type'], $item['id']));
                $ok = $response->getStatusCode() < 300;
                $message = $response->getData(true)['message'] ?? null;
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
                $ok = false;
                $message = $e->getMessage();
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $ok = false;
                $message = 'Élément introuvable ou hors de votre périmètre.';
            } catch (\Throwable $e) {
                report($e);
                $ok = false;
                $message = 'Erreur lors du traitement.';
            }

            $results[] = ['type' => $item['type'], 'id' => $item['id'], 'success' => $ok, 'message' => $message];
        }

        $done = collect($results)->where('success', true)->count();

        return response()->json([
            'message' => "{$done} / " . count($results) . ' élément(s) ' . ($validated['action'] === 'approve' ? 'validé(s)' : 'rejeté(s)') . '.',
            'processed' => $done,
            'failed' => count($results) - $done,
            'results' => $results,
        ], $done > 0 || empty($results) ? 200 : 422);
    }

    /**
     * Un validateur ne peut traiter que les éléments de ses collaborateurs (sauf admin/global).
     */
    private function ensureManaged(?string $ownerId): void
    {
        $user = auth()->user();
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) {
            return;
        }
        abort_unless($ownerId && $user->manages($ownerId), 403, "Cet élément n'appartient pas à un délégué de votre équipe.");
    }
}
