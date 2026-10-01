<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ActivityController extends Controller
{
    #[OA\Get(
        path: '/api/v1/activities',
        summary: 'Lister les activités terrain (visites, appels, emails...)',
        security: [['bearerAuth' => []]],
        tags: ['Activities'],
        parameters: [
            new OA\Parameter(name: 'activity_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['visite', 'appel', 'email', 'reunion', 'conference'])),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['planifiee', 'en_cours', 'realisee', 'manquee', 'annulee'])),
            new OA\Parameter(name: 'date_debut', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_fin', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'contact_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'account_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'tour_plan_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des activités récupérée avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        Activity::markMissedPastActivities();

        $user = auth()->user();
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin', 'super-admin']) || $user->can('scope:global');
        $isSalesManager = $user->hasAnyRole(['sales_manager', 'sales-manager', 'directeur_commercial']);
        $isManager = $isSalesManager || $user->hasAnyRole(['superviseur', 'supervisor', 'directeur_regional', 'directeur_marketing', 'manager']);

        $query = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with([
                'delegue' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']),
                'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
            ])
            ->when($request->user_id, fn($q, $v) => $q->where('activities.user_id', $v))
            ->when($request->activity_type, fn($q, $v) => $q->where('activity_type', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->report_status, fn($q, $v) => $q->where('report_status', $v))
            ->when($request->date_debut, fn($q, $v) => $q->whereDate('date_activite', '>=', $v))
            ->when($request->date_fin, fn($q, $v) => $q->whereDate('date_activite', '<=', $v))
            ->when($request->contact_id, fn($q, $v) => $q->where('contact_id', $v))
            ->when($request->account_id, fn($q, $v) => $q->where('account_id', $v))
            ->when($request->tour_plan_id, fn($q, $v) => $q->where('tour_plan_id', $v))
            ->orderByDesc('date_activite');

        // Restreindre la visibilité si pas d'accès global
        if (!$isGlobal) {
            $query->where(function ($q) use ($user, $isManager, $isSalesManager) {
                // Voit toujours ses propres visites ou là où il est accompagnant
                $q->where('activities.user_id', $user->id)
                  ->orWhere('activities.accompanying_user_id', $user->id);

                if ($isSalesManager) {
                    // Le Sales Manager voit les plannings de tous les délégués
                    $q->orWhereNotNull('activities.user_id');
                } elseif ($isManager) {
                    // S'il est superviseur/manager, voit les visites de son équipe ou des délégués sous lui
                    $q->orWhereHas('delegue', function ($delegueQuery) use ($user) {
                        $delegueQuery->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                            ->where('manager_id', $user->id);
                        if ($user->team_id) {
                            $delegueQuery->orWhere('team_id', $user->team_id);
                        }
                    });
                }
            });
        }

        $perPage = (int) $request->query('per_page', 200);
        return ActivityResource::collection($query->paginate($perPage));
    }

    /**
     * Retourne les IDs de tous les utilisateurs appartenant à une unité organisationnelle et ses descendants
     */
    private function getUserIdsInUnit(string $unitId): array
    {
        // Récupérer récursivement l'unité + tous ses enfants
        $allUnitIds = $this->getDescendantUnitIds([$unitId]);

        return \Illuminate\Support\Facades\DB::table('user_scopes')
            ->whereIn('organizational_unit_id', $allUnitIds)
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique()
            ->toArray();
    }

    /**
     * Récupère tous les IDs descendants récursivement
     */
    private function getDescendantUnitIds(array $parentIds): array
    {
        if (empty($parentIds)) return [];

        $allIds = $parentIds;
        $currentParentIds = $parentIds;

        while (true) {
            $childrenIds = \Illuminate\Support\Facades\DB::table('organizational_units')
                ->whereIn('parent_id', $currentParentIds)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            if (empty($childrenIds)) break;

            $allIds = array_merge($allIds, $childrenIds);
            $currentParentIds = $childrenIds;
        }

        return array_unique($allIds);
    }


    #[OA\Post(
        path: '/api/v1/activities',
        summary: 'Créer une nouvelle activité (visite, appel, email...)',
        description: 'Crée une activité terrain et enregistre optionnellement les produits présentés/distribués. Supporte le mode offline via local_uuid.',
        security: [['bearerAuth' => []]],
        tags: ['Activities'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['activity_type', 'date_activite'],
                properties: [
                    new OA\Property(property: 'tour_plan_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'account_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'activity_type', type: 'string', enum: ['visite', 'appel', 'email', 'reunion', 'conference']),
                    new OA\Property(property: 'date_activite', type: 'string', format: 'date'),
                    new OA\Property(property: 'heure_debut', type: 'string', example: '09:30'),
                    new OA\Property(property: 'heure_fin', type: 'string', example: '10:00'),
                    new OA\Property(property: 'gps_debut_lat', type: 'number', format: 'float'),
                    new OA\Property(property: 'gps_debut_lng', type: 'number', format: 'float'),
                    new OA\Property(property: 'gps_fin_lat', type: 'number', format: 'float'),
                    new OA\Property(property: 'gps_fin_lng', type: 'number', format: 'float'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                    new OA\Property(property: 'statut', type: 'string', enum: ['planifiee', 'en_cours', 'realisee', 'manquee', 'annulee']),
                    new OA\Property(property: 'local_uuid', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'products', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'quantity', type: 'integer', default: 0),
                            new OA\Property(property: 'customer_interest', type: 'string', enum: ['faible', 'moyen', 'fort']),
                            new OA\Property(property: 'remarques', type: 'string', nullable: true),
                        ]
                    )),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Activité créée avec succès'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function store(StoreActivityRequest $request)
    {
        $data = $request->validated();
        $samples = $data['samples'] ?? [];
        $products = $data['products'] ?? [];
        unset($data['samples'], $data['products']);

        // Convertir 'soumis_definitif' en 'realisee' pour la logique interne (optionnel, mais utile)
        if (isset($data['statut']) && $data['statut'] === 'soumis_definitif') {
            $data['statut'] = 'realisee';
        }

        // Si l'utilisateur est Super Administrateur, interdiction de planifier des visites
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['superadmin', 'super-admin', 'Super Administrateur', 'admin'])) {
            return response()->json([
                'message' => 'Le Super Administrateur ne peut pas planifier de visite.'
            ], 403);
        }

        $targetUserId = $user->id;
        
        if (isset($data['user_id']) && ($user->hasRole(['superadmin', 'admin', 'superviseur', 'supervisor', 'manager']) || $user->can('create-others-activities'))) {
            $targetUserId = $data['user_id'];
        }

        // Vérification de chevauchement horaire (partiel, identique ou total)
        if (!empty($data['date_activite']) && !empty($data['heure_debut'])) {
            $hasOverlap = Activity::checkOverlap(
                $targetUserId,
                $data['date_activite'],
                $data['heure_debut'],
                $data['heure_fin'] ?? null,
                $data['duree_minutes'] ?? null
            );

            if ($hasOverlap) {
                return response()->json([
                    'message' => 'Ce délégué a déjà une visite ou indisponibilité planifiée sur ce créneau horaire.'
                ], 422);
            }
        }

        // Définir le statut initial : En attente pour le délégué, Planifiée/Validée si par un manager
        if (!isset($data['statut']) || $data['statut'] === 'planifiee') {
            if ($user->hasRole(['superadmin', 'admin', 'superviseur', 'manager'])) {
                $data['statut'] = 'planifiee';
            } else {
                $data['statut'] = 'en_attente';
            }
        }

        $activity = Activity::create(array_merge($data, ['user_id' => $targetUserId]));

        // Attacher les échantillons (SampleMovement)
        if (!empty($samples)) {
            foreach ($samples as $sample) {
                \App\Models\SampleMovement::create([
                    'product_id' => $sample['product_id'],
                    'user_id' => auth()->id(),
                    'contact_id' => $data['contact_id'] ?? null,
                    'activity_id' => $activity->id,
                    'movement_type' => 'distribution',
                    'quantity' => is_array($sample['quantite']) ? (int) reset($sample['quantite']) : (int) ($sample['quantite'] ?? 1),
                    'lot_number' => $sample['num_lot'] ?? null,
                    'expiry_date' => $sample['date_peremption'] ?? null,
                ]);
            }
        }

        // Attach presented products
        if (!empty($products)) {
            $existingPivot = \Illuminate\Support\Facades\DB::table('activity_product')
                ->where('activity_id', $activity->id)
                ->pluck('id', 'product_id')->toArray();
            
            $syncData = [];
            foreach ($products as $p) {
                $syncData[$p['product_id']] = [
                    'id' => $existingPivot[$p['product_id']] ?? \Illuminate\Support\Str::uuid()->toString(),
                    'quantity' => $p['quantity'] ?? 0,
                    'customer_interest' => $p['customer_interest'] ?? null,
                    'remarques' => $p['remarques'] ?? null,
                ];
            }
            $activity->products()->sync($syncData);
        }

        // Verrouiller automatiquement si statut = realisee ou soumis_definitif
        if (in_array($activity->statut, ['realisee', 'soumis_definitif'])) {
            $activity->lock();
        }

        // Notification au manager
        $user = auth()->user();
        $manager = $user?->manager ?? ($user?->manager_id ? \App\Models\User::find($user->manager_id) : null) ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager', 'Superviseur', 'dir-commercial']))->first();
        if ($manager && $user) {
            $isRealized = in_array($activity->statut, ['realisee', 'soumis_definitif']);
            $title = $isRealized ? 'Nouveau compte-rendu de visite' : 'Nouvelle visite planifiée';
            $msg = $isRealized ? "Le délégué {$user->name} a finalisé son compte-rendu de visite." : "Le délégué {$user->name} a planifié une nouvelle visite.";
            
            NotificationController::sendNotification(
                $manager,
                $title,
                $msg,
                $isRealized ? 'report' : 'planning',
                'manager',
                ['activity_id' => (string) $activity->id, 'sender_id' => (string) $user->id]
            );
        }

        return new ActivityResource($activity->load([
            'delegue', 
            'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']), 
            'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
            'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
            'sampleMovements'
        ]));
    }

    #[OA\Get(
        path: '/api/v1/activities/{id}',
        summary: 'Afficher les détails d\'une activité',
        security: [['bearerAuth' => []]],
        tags: ['Activities'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de l\'activité'),
            new OA\Response(response: 404, description: 'Activité non trouvée'),
        ]
    )]
    public function show(string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with([
                'delegue', 
                'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']), 
                'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
                'tourPlan', 
                'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
                'sampleMovements'
            ])->findOrFail($id);
        return new ActivityResource($activity);
    }

    #[OA\Put(
        path: '/api/v1/activities/{id}',
        summary: 'Mettre à jour une activité (bloqué si verrouillée)',
        security: [['bearerAuth' => []]],
        tags: ['Activities'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Utilisez cette route pour le Check-in (statut: en_cours, gps_debut) et le Check-out (statut: realisee, gps_fin)',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'date_activite', type: 'string', format: 'date', description: 'Ne peut pas être dans le passé'),
                    new OA\Property(property: 'notes', type: 'string'),
                    new OA\Property(property: 'statut', type: 'string', enum: ['planifiee', 'en_cours', 'realisee', 'manquee', 'annulee']),
                    new OA\Property(property: 'heure_debut', type: 'string', example: '09:00'),
                    new OA\Property(property: 'heure_fin', type: 'string', example: '09:45'),
                    new OA\Property(property: 'gps_debut_lat', type: 'number', format: 'float', example: 5.3450),
                    new OA\Property(property: 'gps_debut_lng', type: 'number', format: 'float', example: -3.9820),
                    new OA\Property(property: 'gps_fin_lat', type: 'number', format: 'float', example: 5.3452),
                    new OA\Property(property: 'gps_fin_lng', type: 'number', format: 'float', example: -3.9821),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Activité mise à jour avec succès'),
            new OA\Response(response: 403, description: 'Activité verrouillée — modification interdite'),
            new OA\Response(response: 404, description: 'Activité non trouvée'),
        ]
    )]
    public function update(UpdateActivityRequest $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        if ($activity->is_locked) {
            return response()->json(['message' => 'Cette activité est verrouillée et ne peut plus être modifiée.'], 403);
        }

        $validatedData = $request->validated();
        $targetStatus = $validatedData['statut'] ?? null;
        $products = $validatedData['products'] ?? null;
        if (array_key_exists('products', $validatedData)) {
            unset($validatedData['products']);
        }

        // Contrôle de sécurité 1: Démarrer la visite (en_cours) nécessite que la visite ne soit pas annulée ou refusée
        if ($targetStatus === 'en_cours' && !in_array(strtolower($activity->statut), ['planifiee', 'planifiée', 'validee', 'validée', 'validated', 'en_attente_validation', 'en_cours', 'realisee'])) {
            return response()->json([
                'message' => 'Démarrage impossible : Cette visite a été annulée ou refusée.'
            ], 403);
        }

        // Contrôle de sécurité 2: Passer au statut réalisé nécessite un Check-in GPS effectif (gps_debut_lat)
        $hasGpsStart = !empty($activity->gps_debut_lat) || !empty($validatedData['gps_debut_lat']);
        if ($targetStatus === 'realisee' && !$hasGpsStart) {
            return response()->json([
                'message' => 'Finalisation impossible : Le Check-in GPS de début de visite est obligatoire.'
            ], 422);
        }

        // Contrôle de chevauchement pour modification
        $checkDate = $validatedData['date_activite'] ?? $activity->date_activite;
        $checkTime = $validatedData['heure_debut'] ?? $activity->heure_debut;
        $checkEndTime = $validatedData['heure_fin'] ?? $activity->heure_fin;
        $checkDuration = $validatedData['duree_minutes'] ?? $activity->duree_minutes;

        if ($checkDate && $checkTime) {
            $hasOverlap = Activity::checkOverlap(
                $activity->user_id,
                $checkDate,
                $checkTime,
                $checkEndTime,
                $checkDuration,
                $activity->id
            );
            if ($hasOverlap) {
                return response()->json([
                    'message' => 'Ce délégué a déjà une visite ou indisponibilité planifiée sur ce créneau horaire.'
                ], 422);
            }
        }

        // Trace of previous date/time if changed
        if (isset($validatedData['date_activite']) || isset($validatedData['heure_debut'])) {
            $newDate = $validatedData['date_activite'] ?? $activity->date_activite;
            $newTime = $validatedData['heure_debut'] ?? $activity->heure_debut;
            
            if ($newDate !== $activity->date_activite || $newTime !== $activity->heure_debut) {
                $oldD = date('d/m/Y', strtotime($activity->date_activite));
                $oldT = $activity->heure_debut ? substr($activity->heure_debut, 0, 5) : '';
                $newD = date('d/m/Y', strtotime($newDate));
                $newT = $newTime ? substr($newTime, 0, 5) : '';

                $trace = "\nVisite reportée le " . date('d/m/Y à H:i') . " (du " . $oldD . ($oldT ? " à " . $oldT : "") . " au " . $newD . ($newT ? " à " . $newT : "") . ")";
                $validatedData['notes'] = ($validatedData['notes'] ?? $activity->notes) . $trace;
            }
        }

        $activity->update($validatedData);

        // Attach presented products
        if ($products !== null) {
            $existingPivot = \Illuminate\Support\Facades\DB::table('activity_product')
                ->where('activity_id', $activity->id)
                ->pluck('id', 'product_id')->toArray();
                
            $syncData = [];
            foreach ($products as $p) {
                $syncData[$p['product_id']] = [
                    'id' => $existingPivot[$p['product_id']] ?? \Illuminate\Support\Str::uuid()->toString(),
                    'quantity' => $p['quantity'] ?? 0,
                    'customer_interest' => $p['customer_interest'] ?? null,
                    'remarques' => $p['remarques'] ?? null,
                ];
            }
            $activity->products()->sync($syncData);
        }

        if ($activity->statut === 'realisee') {
            $activity->lock();
        }

        return new ActivityResource($activity->load([
            'delegue', 
            'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']), 
            'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
            'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
        ]));
    }

    #[OA\Delete(
        path: '/api/v1/activities/{id}',
        summary: 'Supprimer (soft-delete) une activité',
        security: [['bearerAuth' => []]],
        tags: ['Activities'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Activité supprimée avec succès'),
            new OA\Response(response: 403, description: 'Activité verrouillée — suppression interdite'),
        ]
    )]
    public function destroy(string $id)
    {
        $activity = Activity::findOrFail($id);
        $user = auth()->user();
        
        $isSuperAdmin = $user && ($user->hasAnyRole(['superadmin', 'admin']) || $user->can('scope:global'));
        $isOwner = $user && $activity->user_id === $user->id;

        if (!$isSuperAdmin && !$isOwner) {
            return response()->json(['message' => 'Vous n\'êtes pas autorisé à supprimer cette visite.'], 403);
        }

        if ($activity->is_locked && !$isSuperAdmin) {
            return response()->json(['message' => 'Activité verrouillée - suppression interdite.'], 403);
        }

        $activity->delete();
        
        return response()->json(null, 204);
    }

    public function validateActivity(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);

        $request->validate([
            'statut' => 'required|in:validee,validated,rejetee,refusee',
            'rejection_reason' => 'required_if:statut,rejetee,refusee|string|nullable'
        ]);

        $st = strtolower($request->statut);
        $newStatut = ($st === 'validee' || $st === 'validated') ? 'validee' : 'refusee';
        $rejectionReason = $request->rejection_reason ?? $request->motif ?? null;

        if (in_array($st, ['rejetee', 'refusee']) && (empty($rejectionReason) || strlen(trim($rejectionReason)) < 2)) {
            return response()->json([
                'message' => 'Le motif du rejet est obligatoire.',
                'errors'  => ['rejection_reason' => ['Le motif du rejet est obligatoire.']]
            ], 422);
        }

        $notes = $activity->notes;
        if (in_array($st, ['rejetee', 'refusee']) && $rejectionReason) {
            $notes = $notes ? $notes . "\n[Rejeté]: " . $rejectionReason : "[Rejeté]: " . $rejectionReason;
        }

        $activity->update([
            'statut' => $newStatut,
            'validated_by_id' => auth()->id(),
            'validation_date' => now(),
            'notes' => $notes,
            'rejection_reason' => $rejectionReason,
        ]);

        // Notifier le délégué de la décision du manager
        if ($activity->user) {
            $title = $newStatut === 'validee' ? 'Visite Validée' : 'Visite Refusée';
            $dateStr = $activity->date_activite ? \Illuminate\Support\Carbon::parse($activity->date_activite)->format('d/m/Y') : now()->format('d/m/Y');
            $msg = $newStatut === 'validee'
                ? "Votre visite prévue le {$dateStr} a été validée par votre responsable."
                : "Votre visite prévue le {$dateStr} a été refusée par votre responsable.";
            
            try {
                app(\App\Services\NotificationService::class)->send(
                    $activity->user,
                    $title,
                    $msg,
                    'planning',
                    ['activity_id' => $activity->id, 'recipient_role' => 'delegate', 'sender_id' => auth()->id()]
                );
            } catch (\Exception $e) {}
        }

        return response()->json([
            'message' => 'Activité mise à jour avec succès', 
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products']))
        ]);
    }

    public function reschedule(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        if ($activity->is_locked) {
            return response()->json(['message' => 'Cette activité est verrouillée et ne peut pas être reprogrammée.'], 403);
        }

        $validated = $request->validate([
            'date_activite' => 'required|date',
            'heure_debut'   => 'nullable|string',
            'heure_fin'     => 'nullable|string',
            'motif'         => 'required|string', // Justificatif obligatoire
        ]);

        $prevStatus = $activity->statut;
        $isAlreadyValidated = in_array(strtolower($prevStatus), ['validee', 'validated']) ||
            ($activity->tourPlan && in_array(strtolower($activity->tourPlan->statut), ['valide', 'validated', 'approuve']));

        // Si le planning global avait déjà été validé, conserver le statut validé
        $newStatus = $isAlreadyValidated ? 'validee' : ($prevStatus === 'manquee' ? 'planifiee' : $prevStatus);

        $justification = "[REPROGRAMMÉE]: " . $validated['motif'];
        $notes = $activity->notes ? $activity->notes . "\n" . $justification : $justification;

        $updateData = [
            'date_activite' => $validated['date_activite'],
            'notes' => $notes,
            'motif' => $validated['motif'],
            'statut' => $newStatus,
        ];

        if (!empty($validated['heure_debut'])) {
            $updateData['heure_debut'] = $validated['heure_debut'];
        }
        if (!empty($validated['heure_fin'])) {
            $updateData['heure_fin'] = $validated['heure_fin'];
        }

        $activity->update($updateData);

        return response()->json([
            'message' => 'Visite reprogrammée avec succès sans nécessiter de nouvelle validation manager.',
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products']))
        ]);
    }

    public function storeDuo(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['superadmin', 'super-admin', 'Super Administrateur', 'admin'])) {
            return response()->json([
                'message' => 'Le Super Administrateur ne peut pas planifier de visite.'
            ], 403);
        }

        if ($user && $user->hasAnyRole(['delegue', 'delegue_medical', 'delegate', 'Délégué', 'Délégué Médical'])) {
            return response()->json([
                'message' => 'Seul le superviseur ou la hiérarchie peut planifier des visites DUO.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'       => 'nullable|string',
            'delegate_id'   => 'nullable|string',
            'date_activite' => 'required|date',
            'heure_debut'   => 'required|string',
            'heure_fin'     => 'nullable|string',
            'duree_minutes' => 'nullable|integer',
            'notes'         => 'nullable|string',
            'objective'     => 'nullable|string',
        ]);

        $targetUserId = $validated['user_id'] ?? $validated['delegate_id'];
        if (!$targetUserId) {
            return response()->json(['message' => 'Le champ délégué est obligatoire.'], 422);
        }

        // Vérification immédiate du conflit de planning
        $hasOverlap = Activity::checkOverlap(
            $targetUserId,
            $validated['date_activite'],
            $validated['heure_debut'],
            $validated['heure_fin'] ?? null,
            $validated['duree_minutes'] ?? null
        );

        if ($hasOverlap) {
            return response()->json([
                'message' => 'Ce délégué a déjà une visite ou indisponibilité planifiée sur ce créneau horaire.'
            ], 422);
        }

        $subsidiary = \App\Models\Subsidiary::first();

        $activity = Activity::create([
            'subsidiary_id' => $subsidiary?->id,
            'user_id' => $targetUserId,
            'accompanying_user_id' => auth()->id(),
            'date_activite' => $validated['date_activite'],
            'heure_debut' => $validated['heure_debut'],
            'heure_fin' => $validated['heure_fin'] ?? null,
            'duree_minutes' => $validated['duree_minutes'] ?? null,
            'activity_type' => 'visite',
            'statut' => 'en_attente',
            'notes' => '[VISITE DUO SUPERVISEUR] ' . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe DUO avec le superviseur.'),
        ]);

        // Notification au Délégué (nouveau_duo)
        $delegate = \App\Models\User::find($targetUserId);
        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Nouvelle proposition DUO',
                "Votre superviseur a proposé une visite DUO pour le {$validated['date_activite']} à {$validated['heure_debut']}.",
                'nouveau_duo',
                'delegate',
                ['activity_id' => (string) $activity->id]
            );
        }

        return response()->json([
            'message' => 'Proposition de visite DUO créée avec succès.',
            'data' => new ActivityResource($activity->load(['delegue']))
        ], 201);
    }

    public function acceptDuo(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        $validated = $request->validate([
            'contact_id'    => 'nullable|string',
            'contacts'      => 'nullable|array',
            'account_id'    => 'nullable|string',
            'activity_type' => 'nullable|string',
            'objective'     => 'nullable|string',
            'notes'         => 'nullable|string',
            'campaign_id'   => 'nullable|string',
            'products'      => 'nullable|array',
        ]);

        $typeAct = strtolower($validated['activity_type'] ?? $activity->activity_type ?? 'visite');
        $contactsList = $validated['contacts'] ?? ($validated['contact_id'] ? [$validated['contact_id']] : []);

        // Règle de sélection des prescripteurs (RG11 & RG12)
        if (in_array($typeAct, ['one to one', 'one_to_one', 'visite', 'rp', 'relations publiques']) && count($contactsList) > 1) {
            return response()->json([
                'message' => "Une activité One to One ou RP ne peut concerner qu'un seul prescripteur."
            ], 422);
        }

        // Vérification de conflit de planning lors de l'acceptation
        if (!empty($activity->date_activite) && !empty($activity->heure_debut)) {
            $hasOverlap = Activity::checkOverlap(
                $activity->user_id,
                $activity->date_activite,
                $activity->heure_debut,
                $activity->heure_fin,
                $activity->duree_minutes,
                $activity->id
            );

            if ($hasOverlap) {
                return response()->json([
                    'message' => 'Impossible d\'accepter : un conflit de planning existe sur ce créneau.'
                ], 422);
            }
        }

        $activity->update([
            'contact_id'    => $contactsList[0] ?? $activity->contact_id,
            'account_id'    => $validated['account_id'] ?? $activity->account_id,
            'activity_type' => $typeAct,
            'campaign_id'   => $validated['campaign_id'] ?? $activity->campaign_id,
            'notes'         => $activity->notes . (isset($validated['notes']) && $validated['notes'] ? "\n" . $validated['notes'] : ''),
            'statut'        => 'validee',
        ]);

        return response()->json([
            'message' => 'Visite DUO acceptée et validée avec succès.',
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account']))
        ]);
    }

    public function refuseDuo(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du refus est obligatoire.'
        ]);

        $activity->update([
            'statut'           => 'refusee',
            'rejection_reason' => $validated['motif'],
            'motif'            => $validated['motif'],
        ]);

        // Notification au Superviseur (duo_refuse)
        $supervisor = $activity->accompanyingUser ?? $activity->delegue?->manager;
        if ($supervisor) {
            NotificationController::sendNotification(
                $supervisor,
                'Visite DUO refusée',
                "Le délégué {$activity->delegue?->name} a refusé la visite DUO. Motif : {$validated['motif']}",
                'duo_refuse',
                'supervisor',
                ['activity_id' => (string) $activity->id, 'motif' => $validated['motif']]
            );
        }

        return response()->json([
            'message' => 'Visite DUO refusée avec enregistrement du motif.',
            'data' => new ActivityResource($activity)
        ]);
    }

    public function storeTrio(Request $request)
    {
        $user = auth()->user();
        if ($user && $user->hasAnyRole(['superadmin', 'super-admin', 'Super Administrateur', 'admin'])) {
            return response()->json([
                'message' => 'Le Super Administrateur ne peut pas planifier de visite.'
            ], 403);
        }

        $validated = $request->validate([
            'user_id'       => 'required|string',
            'date_activite' => 'required|date',
            'heure_debut'   => 'required|string',
            'heure_fin'     => 'nullable|string',
            'duree_minutes' => 'nullable|integer',
            'contact_id'    => 'nullable|string',
            'account_id'    => 'nullable|string',
            'activity_type' => 'nullable|string',
            'objective'     => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        $subsidiary = \App\Models\Subsidiary::first();
        $delegate = User::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($validated['user_id']);
        $supervisor = $delegate->manager ?? User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor']))->first();

        $activity = Activity::create([
            'subsidiary_id'        => $subsidiary?->id,
            'user_id'              => $delegate->id,
            'accompanying_user_id' => $user->id,
            'contact_id'           => $validated['contact_id'] ?? null,
            'account_id'           => $validated['account_id'] ?? null,
            'date_activite'        => $validated['date_activite'],
            'heure_debut'          => $validated['heure_debut'],
            'heure_fin'            => $validated['heure_fin'] ?? null,
            'duree_minutes'        => $validated['duree_minutes'] ?? null,
            'activity_type'        => $validated['activity_type'] ?? 'visite',
            'statut'               => 'en_attente',
            'notes'                => '[VISITE TRIO SALES MANAGER] ' . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe TRIO.'),
        ]);

        // Transmis au Superviseur pour validation (JAMAIS directement au Délégué)
        if ($supervisor) {
            NotificationController::sendNotification(
                $supervisor,
                'Nouveau TRIO à valider',
                "Le Sales Manager {$user->name} a créé une visite TRIO pour le délégué {$delegate->name} le {$validated['date_activite']}.",
                'trio_transmis',
                'supervisor',
                ['activity_id' => (string) $activity->id]
            );
        }

        return response()->json([
            'message' => 'Visite TRIO créée et transmise au Superviseur pour validation.',
            'data' => new ActivityResource($activity->load(['delegue']))
        ], 201);
    }

    public function validateTrio(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);

        // Appliquer la détection de conflit de planning du délégué
        if (!empty($activity->date_activite) && !empty($activity->heure_debut)) {
            $hasOverlap = Activity::checkOverlap(
                $activity->user_id,
                $activity->date_activite,
                $activity->heure_debut,
                $activity->heure_fin,
                $activity->duree_minutes,
                $activity->id
            );

            if ($hasOverlap) {
                return response()->json([
                    'message' => 'Validation impossible : le créneau chevauche une visite existante du délégué.'
                ], 422);
            }
        }

        $activity->update([
            'statut'          => 'validee',
            'validated_by_id' => auth()->id(),
            'validation_date' => now(),
        ]);

        // Notifier le Délégué (uniquement notification, aucune action de sa part)
        if ($activity->delegue) {
            NotificationController::sendNotification(
                $activity->delegue,
                'Nouvelle visite TRIO au planning',
                "Une visite TRIO avec votre hiérarchie a été validée pour le {$activity->date_activite} à {$activity->heure_debut}.",
                'nouveau_trio',
                'delegate',
                ['activity_id' => (string) $activity->id]
            );
        }

        return response()->json([
            'message' => 'Visite TRIO validée et ajoutée au planning du délégué.',
            'data' => new ActivityResource($activity->load(['delegue']))
        ]);
    }

    public function rejectTrio(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($id);

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du rejet est obligatoire.'
        ]);

        $activity->update([
            'statut'           => 'rejetee',
            'rejection_reason' => $validated['motif'],
            'motif'            => $validated['motif'],
            'validated_by_id'  => auth()->id(),
        ]);

        // Notifier le Sales Manager du rejet
        if ($activity->accompanyingUser) {
            NotificationController::sendNotification(
                $activity->accompanyingUser,
                'Visite TRIO rejetée par le superviseur',
                "La visite TRIO a été rejetée. Motif : {$validated['motif']}",
                'trio_rejete',
                'sales_manager',
                ['activity_id' => (string) $activity->id, 'motif' => $validated['motif']]
            );
        }

        return response()->json([
            'message' => 'Visite TRIO rejetée avec enregistrement du motif.',
            'data' => new ActivityResource($activity)
        ]);
    }
}
