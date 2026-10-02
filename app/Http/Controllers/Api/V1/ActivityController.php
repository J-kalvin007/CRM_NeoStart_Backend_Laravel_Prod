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
        $isCommercialDirector = $user->hasAnyRole(['directeur-commercial', 'directeur_commercial']);
        $isManager = $isCommercialDirector || $user->hasAnyRole(['superviseur', 'supervisor', 'directeur_regional', 'directeur-marketing', 'directeur_marketing', 'sales_manager', 'manager']);

        $query = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with([
                'delegue' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']),
                'contacts' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']),
                'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'accompanyingUser' => fn($q) => $q->withoutGlobalScopes(),
                'duoActivityType',
                'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'sampleMovements' => fn($q) => $q->where('movement_type', 'distribution')->with('product'),
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
            $query->where(function ($q) use ($user, $isManager, $isCommercialDirector) {
                // Voit toujours ses propres visites ou là où il est accompagnant
                $q->where('activities.user_id', $user->id)
                  ->orWhere('activities.accompanying_user_id', $user->id)
                  ->orWhere('activities.duo_user_id', $user->id);

                if ($isCommercialDirector) {
                    // Le Sales Manager voit les plannings de tous les délégués (sauf brouillons)
                    $q->orWhere(function ($subQ) {
                        $subQ->whereNotNull('activities.user_id')
                             ->where('activities.statut', '!=', 'brouillon');
                    });
                } elseif ($user->hasRole('sales_manager')) {
                    $teamMemberIds = $user->teamMemberIds();
                    if (!empty($teamMemberIds)) {
                        $q->orWhere(function ($subQ) use ($teamMemberIds) {
                            $subQ->where('activities.statut', '!=', 'brouillon')
                                ->whereIn('activities.user_id', $teamMemberIds);
                        });
                    }
                } elseif ($isManager) {
                    // S'il est superviseur/manager, voit les visites de son équipe ou des délégués sous lui
                    // Inclut : planifiées, en attente, en cours, réalisées, manquées, annulées, brouillons, et toutes les visites duo
                    $q->orWhere(function ($subQ) use ($user) {
                        $subQ->whereHas('delegue', function ($delegueQuery) use ($user) {
                            $delegueQuery->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
                                ->where('manager_id', $user->id);
                            if ($user->team_id) {
                                $delegueQuery->orWhere('team_id', $user->team_id);
                            }
                        });
                    });
                    // Voir aussi les visites où il est le duo_user_id (visites duo qu'il anime) - tous les statuts
                    $q->orWhere('activities.duo_user_id', $user->id);
                }
            });
        }

        $perPage = (int) $request->query('per_page', 200);
        return response()->json([
            'data' => $query->paginate($perPage)->items()
        ]);
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

        // Définir le statut initial selon le workflow
        // - brouillon : quand le délégué crée une visite sans la soumettre
        // - en_attente : quand le délégué soumet la visite pour validation
        // - planifiee : quand le superviseur valide la visite
        // - realisee : quand la visite est terminée (avec GPS debut et fin)
        // - annulee : quand le délégué annule la visite
        // - visite_duo : quand c'est une visite en duo

        $isManagerRole = $user->hasRole(['superadmin', 'admin', 'superviseur', 'supervisor', 'manager']);
        // Un délégué ne crée pas une visite déjà validée, refusée ou manquée : elle part en validation
        if (!$isManagerRole && in_array($data['statut'] ?? null, ['validee', 'valide', 'refusee', 'rejetee', 'manquee', 'annulee'], true)) {
            $data['statut'] = 'en_attente';
        }

        if (!isset($data['statut']) || $data['statut'] === 'planifiee') {
            if (isset($data['is_draft']) && $data['is_draft'] === true) {
                // Le délégué crée un brouillon
                $data['statut'] = 'brouillon';
            } elseif ($user->hasRole(['superadmin', 'admin', 'superviseur', 'manager'])) {
                // Le superviseur valide directement la visite
                $data['statut'] = 'planifiee';
            } else {
                // Le délégué soumet pour validation
                $data['statut'] = 'en_attente';
            }
        }

        // Si c'est une visite en duo, on peut ajouter un indicateur
        if (isset($data['is_duo']) && $data['is_duo'] === true) {
            // Le statut reste tel quel (en_attente ou planifiee)
            // mais on peut avoir un champ is_duo à true
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

        // Attach multiple contacts if provided in meta.group_contacts
        if (!empty($data['meta']['group_contacts']) && is_array($data['meta']['group_contacts'])) {
            $activity->contacts()->sync($data['meta']['group_contacts']);
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
                'contacts' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']),
                'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
                'tourPlan',
                'duoActivityType',
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

        // Validation / refus : décision du responsable (circuit validations), jamais du délégué lui-même
        $user = auth()->user();
        $canDecide = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']) || ($activity->user_id && $user->manages((string) $activity->user_id));
        $decisionStatuts = ['planifiee', 'validee', 'valide', 'refusee', 'rejetee'];
        if (!$canDecide && $targetStatus !== null && $targetStatus !== $activity->statut && in_array($targetStatus, $decisionStatuts, true)) {
            return response()->json(['message' => 'La validation ou le refus d\'une visite revient à votre responsable.'], 403);
        }
        if (!$canDecide && in_array($validatedData['report_status'] ?? null, ['validated', 'rejected'], true)) {
            return response()->json(['message' => 'La validation d\'un compte-rendu revient à votre responsable.'], 403);
        }
        if (in_array($targetStatus, ['en_cours', 'realisee'], true) && (string) $activity->user_id !== (string) auth()->id()) {
            return response()->json([
                'message' => 'Seul le délégué auquel cette visite est attribuée peut la démarrer ou la terminer.'
            ], 403);
        }
        if ($activity->isTrio() && in_array($targetStatus, ['validee', 'rejetee', 'refusee'], true)) {
            return response()->json(['message' => 'Une visite Trio doit être validée ou rejetée via son circuit dédié.'], 403);
        }
        $products = $validatedData['products'] ?? null;
        if (array_key_exists('products', $validatedData)) {
            unset($validatedData['products']);
        }
        $samples = $validatedData['samples'] ?? [];
        unset($validatedData['samples']);

        // Contrôle de sécurité 1: Démarrer la visite (en_cours) nécessite que la visite ne soit pas annulée ou refusée
        if ($targetStatus === 'en_cours' && !in_array(strtolower($activity->statut), ['planifiee', 'planifiée', 'validee', 'validée', 'validated', 'en_attente_validation', 'en_cours', 'realisee'])) {
            return response()->json([
                'message' => 'Démarrage impossible : Cette visite a été annulée ou refusée.'
            ], 403);
        }

        // Prevent starting a visit that can never pass the GPS check-out validation.
        $gpsStartLat = $validatedData['gps_debut_lat'] ?? $activity->gps_debut_lat;
        $gpsStartLng = $validatedData['gps_debut_lng'] ?? $activity->gps_debut_lng;
        $hasGpsStart = $gpsStartLat !== null && $gpsStartLng !== null;
        if ($targetStatus === 'en_cours' && $activity->statut !== 'en_cours' && !$hasGpsStart) {
            return response()->json([
                'message' => 'Démarrage impossible : le Check-in GPS est obligatoire.'
            ], 422);
        }

        // Contrôle de sécurité 2: Passer au statut réalisé nécessite un Check-in GPS effectif.
        if ($targetStatus === 'realisee' && !$hasGpsStart) {
            return response()->json([
                'message' => 'Finalisation impossible : Le Check-in GPS de début de visite est obligatoire.'
            ], 422);
        }
        if ($samples !== [] && ($targetStatus !== 'realisee' || $activity->statut !== 'en_cours')) {
            return response()->json(['message' => 'Les échantillons peuvent être enregistrés uniquement lors de la fin de la visite en cours.'], 422);
        }
        if ($samples !== [] && !$activity->contact_id) {
            return response()->json(['message' => 'Un prescripteur doit être associé à la visite pour enregistrer la remise d’échantillons.'], 422);
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($activity, $validatedData, $products, $samples): void {
            $activity->update($validatedData);

            // Attach presented products
            if ($products !== null) {
                $existingPivot = \Illuminate\Support\Facades\DB::table('activity_product')
                    ->where('activity_id', $activity->id)
                    ->pluck('id', 'product_id')->toArray();

                $syncData = [];
                foreach ($products as $product) {
                    $syncData[$product['product_id']] = [
                        'id' => $existingPivot[$product['product_id']] ?? \Illuminate\Support\Str::uuid()->toString(),
                        'quantity' => $product['quantity'] ?? 0,
                        'customer_interest' => $product['customer_interest'] ?? null,
                        'remarques' => $product['remarques'] ?? null,
                    ];
                }
                $activity->products()->sync($syncData);
            }

            foreach ($samples as $index => $sample) {
                $sampleMovements = \App\Models\SampleMovement::where('user_id', $activity->user_id)
                    ->where('product_id', $sample['product_id'])
                    ->where('lot_number', $sample['lot_number'])
                    ->lockForUpdate()
                    ->get(['movement_type', 'quantity', 'expiry_date']);

                $received = $sampleMovements->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity');
                $distributed = $sampleMovements->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');
                $available = max(0, $received - $distributed);
                $quantity = (int) $sample['quantity'];

                if ($quantity > $available) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "samples.{$index}.quantity" => "Stock insuffisant pour le lot {$sample['lot_number']}. Disponible : {$available}.",
                    ]);
                }

                $newLotBalance = $available - $quantity;
                \App\Models\SampleMovement::create([
                    'user_id' => $activity->user_id,
                    'product_id' => $sample['product_id'],
                    'contact_id' => $activity->contact_id,
                    'activity_id' => $activity->id,
                    'movement_type' => 'distribution',
                    'quantity' => $quantity,
                    'lot_number' => $sample['lot_number'],
                    'expiry_date' => $sampleMovements->first()?->expiry_date,
                    'stock_apres' => $newLotBalance,
                    'remarques' => 'Remis au prescripteur pendant la visite ' . $activity->id,
                ]);

                $product = \App\Models\Product::find($sample['product_id']);
                if ($product && $product->stock_actuel >= $quantity) {
                    $product->decrement('stock_actuel', $quantity);
                }
            }

            if ($activity->statut === 'realisee') {
                $activity->lock();
            }
        });

        return new ActivityResource($activity->load([
            'delegue', 
            'contact' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->with(['account', 'speciality', 'potential', 'influence']), 
            'account' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class), 
            'products' => fn($q) => $q->withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class),
            'sampleMovements' => fn($q) => $q->where('movement_type', 'distribution')->with('product'),
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
        // Le superviseur peut supprimer les visites des délégués qu'il encadre
        $isManager = $user && !$isOwner && $user->manages($activity->user_id);

        if (!$isSuperAdmin && !$isOwner && !$isManager) {
            return response()->json(['message' => 'Vous n\'êtes pas autorisé à supprimer cette visite.'], 403);
        }

        if ($isManager && $activity->delegue) {
            NotificationController::sendNotification(
                $activity->delegue,
                'Visite supprimée',
                "{$user->name} a supprimé votre visite du " . $activity->date_activite?->format('d/m/Y') . ($activity->heure_debut ? ' à ' . substr($activity->heure_debut, 0, 5) : '') . '.',
                'visite_supprimee',
                'delegate',
                ['activity_id' => (string) $activity->id]
            );
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
        abort_unless(!$activity->isTrio(), 403, 'Une visite Trio doit être validée via son circuit dédié.');
        $this->ensureSupervises($activity->user_id);

        $request->validate([
            'statut' => 'required|in:validee,validated,rejetee,refusee',
            'rejection_reason' => 'required_if:statut,rejetee,refusee|string|nullable'
        ]);

        $st = strtolower($request->statut);
        // Le superviseur valide : en_attente -> planifiee
        // Le superviseur refuse : en_attente -> annulee
        $newStatut = ($st === 'validee' || $st === 'validated') ? 'planifiee' : 'annulee';
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
            $title = $newStatut === 'planifiee' ? 'Visite Validée' : 'Visite Refusée';
            $dateStr = $activity->date_activite ? \Illuminate\Support\Carbon::parse($activity->date_activite)->format('d/m/Y') : now()->format('d/m/Y');
            $msg = $newStatut === 'planifiee'
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

    public function validateActivitiesBulk(Request $request)
    {
        $request->validate([
            'activity_ids' => 'required|array',
            'activity_ids.*' => 'string|exists:activities,id',
            'statut' => 'required|in:validee,validated,rejetee,refusee',
            'rejection_reason' => 'required_if:statut,rejetee,refusee|string|nullable'
        ]);

        $activityIds = $request->activity_ids;
        $st = strtolower($request->statut);
        $newStatut = ($st === 'validee' || $st === 'validated') ? 'validee' : 'refusee';
        $rejectionReason = $request->rejection_reason ?? $request->motif ?? null;

        if (in_array($st, ['rejetee', 'refusee']) && (empty($rejectionReason) || strlen(trim($rejectionReason)) < 2)) {
            return response()->json([
                'message' => 'Le motif du rejet est obligatoire.',
                'errors'  => ['rejection_reason' => ['Le motif du rejet est obligatoire.']]
            ], 422);
        }

        $activities = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->whereIn('id', $activityIds)
            ->get();

        if ($activities->contains(fn (Activity $activity) => $activity->isTrio())) {
            return response()->json(['message' => 'Les visites Trio doivent être traitées individuellement via leur circuit dédié.'], 422);
        }

        $validatedCount = 0;
        $groupedUsers = [];

        foreach ($activities as $activity) {
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

            $validatedCount++;

            // Group by user for notification
            if ($activity->user) {
                $userId = $activity->user->id;
                if (!isset($groupedUsers[$userId])) {
                    $groupedUsers[$userId] = [
                        'user' => $activity->user,
                        'activities' => []
                    ];
                }
                $groupedUsers[$userId]['activities'][] = $activity;
            }
        }

        // Send one notification per user (not per activity)
        foreach ($groupedUsers as $userData) {
            $user = $userData['user'];
            $userActivities = $userData['activities'];
            $title = $newStatut === 'validee' ? 'Visites Validées' : 'Visites Refusées';
            $count = count($userActivities);
            $msg = $newStatut === 'validee'
                ? "Vous avez {$count} visite(s) validée(s) par votre responsable."
                : "Vous avez {$count} visite(s) refusée(s) par votre responsable.";

            try {
                app(\App\Services\NotificationService::class)->send(
                    $user,
                    $title,
                    $msg,
                    'planning',
                    ['bulk_validation' => true, 'count' => $count, 'sender_id' => auth()->id()]
                );
            } catch (\Exception $e) {}
        }

        return response()->json([
            'message' => "{$validatedCount} activité(s) mise(s) à jour avec succès",
            'validated_count' => $validatedCount
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

        // Une visite reprogrammée est directement validée et prête à démarrer sans ré-attendre de validation
        $newStatus = 'validee';

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
                'message' => 'Seul le superviseur, le Sales Manager ou la hiérarchie peut planifier des visites DUO.'
            ], 403);
        }

        $validated = $request->validate([
            'activity_id'   => 'nullable|string|exists:activities,id',
            'user_id'       => 'nullable|string',
            'delegate_id'   => 'nullable|string',
            'contact_id'    => 'nullable|uuid',
            'account_id'    => 'nullable|uuid',
            'duo_type'      => 'nullable|string',
            'date_activite' => 'required|date|after_or_equal:today',
            'heure_debut'   => 'required|string',
            'heure_fin'     => 'nullable|string',
            'duree_minutes' => 'nullable|integer',
            'notes'         => 'nullable|string',
            'objective'     => 'nullable|string',
        ]);

        // Vérifier que le type d'activité duo existe si fourni
        if (!empty($validated['duo_type'])) {
            $duoTypeExists = \Illuminate\Support\Facades\DB::table('duo_activity_types')
                ->where('name', $validated['duo_type'])
                ->exists();
            if (!$duoTypeExists) {
                return response()->json(['message' => 'Le type d\'activité duo sélectionné est invalide.'], 422);
            }
        }

        $targetUserId = $validated['user_id'] ?? $validated['delegate_id'];
        $existingActivityId = $validated['activity_id'] ?? null;
        if (!$targetUserId) {
            return response()->json(['message' => 'Le champ délégué est obligatoire.'], 422);
        }
        if (!$user->manages($targetUserId)) {
            return response()->json(['message' => "Ce délégué ne fait pas partie de votre équipe."], 403);
        }

        $existingActivity = $existingActivityId ? Activity::withoutGlobalScopes()->find($existingActivityId) : null;
        if ($existingActivity && (string) $existingActivity->user_id !== (string) $targetUserId) {
            return response()->json(['message' => 'Cette activité ne correspond pas au délégué sélectionné.'], 422);
        }

        // Vérification immédiate du conflit de planning
        $hasOverlap = Activity::checkOverlap(
            $targetUserId,
            $validated['date_activite'],
            $validated['heure_debut'],
            $validated['heure_fin'] ?? null,
            $validated['duree_minutes'] ?? null,
            $existingActivityId
        );

        if ($hasOverlap) {
            return response()->json([
                'message' => 'Ce délégué a déjà une visite ou indisponibilité planifiée sur ce créneau horaire.'
            ], 422);
        }

        $subsidiary = \App\Models\Subsidiary::first();

        if ($existingActivity) {
            $existingActivity->update([
                'subsidiary_id' => $subsidiary?->id ?? $existingActivity->subsidiary_id,
                'contact_id' => $validated['contact_id'] ?? $existingActivity->contact_id,
                'account_id' => $validated['account_id'] ?? $existingActivity->account_id,
                'is_duo' => true,
                'is_trio' => false,
                'duo_user_id' => auth()->id(),
                'duo_activity_type_id' => $this->duoTypeId($request->input('duo_type')),
                'duo_status' => 'accepted',
                'duo_delegue_status' => 'pending',
                'date_activite' => $validated['date_activite'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'] ?? $existingActivity->heure_fin,
                'duree_minutes' => $validated['duree_minutes'] ?? $existingActivity->duree_minutes,
                'activity_type' => 'visite',
                'statut' => in_array($existingActivity->statut, ['brouillon', 'en_attente', 'planifiee'], true) ? 'en_attente' : $existingActivity->statut,
                'notes' => trim(($existingActivity->notes ?? '') . "\n[VISITE DUO SUPERVISEUR] " . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe DUO avec le superviseur.')),
            ]);

            $activity = $existingActivity->fresh();
        } else {
            $activity = Activity::create([
                'subsidiary_id' => $subsidiary?->id,
                'user_id' => $targetUserId,
                'contact_id' => $validated['contact_id'] ?? null,
                'account_id' => $validated['account_id']
                    ?? (isset($validated['contact_id']) ? \App\Models\Contact::withoutGlobalScopes()->whereKey($validated['contact_id'])->value('account_id') : null),
                'is_duo' => true,
                'is_trio' => false,
                'duo_user_id' => auth()->id(),
                'duo_activity_type_id' => $this->duoTypeId($request->input('duo_type')),
                'duo_status' => 'accepted',
                'duo_delegue_status' => 'pending',
                'date_activite' => $validated['date_activite'],
                'heure_debut' => $validated['heure_debut'],
                'heure_fin' => $validated['heure_fin'] ?? null,
                'duree_minutes' => $validated['duree_minutes'] ?? null,
                'activity_type' => 'visite',
                'statut' => 'en_attente',
                'notes' => '[VISITE DUO SUPERVISEUR] ' . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe DUO avec le superviseur.'),
            ]);
        }

        // Notification au Délégué (nouveau_duo)
        $delegate = \App\Models\User::find($targetUserId);
        if ($delegate) {
            $rawDate = $validated['date_activite'] ?? null;
            $rawTime = $validated['heure_debut'] ?? null;

            try {
                $dateLabel = $rawDate ? \Carbon\Carbon::parse($rawDate)->locale('fr')->translatedFormat('d/m/Y') : 'la date prévue';
                $timeLabel = $rawTime ? substr((string) $rawTime, 0, 5) : null;
            } catch (\Throwable $e) {
                $dateLabel = is_string($rawDate) ? preg_replace('/T.*$/', '', $rawDate) : 'la date prévue';
                $timeLabel = is_string($rawTime) ? substr($rawTime, 0, 5) : null;
            }

            $dateMessage = $timeLabel ? "le {$dateLabel} à {$timeLabel}" : "le {$dateLabel}";

            NotificationController::sendNotification(
                $delegate,
                'Nouvelle proposition DUO',
                "Votre superviseur a proposé une visite DUO pour {$dateMessage}.",
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
        abort_unless($activity->user_id === auth()->id(), 403, 'Seul le délégué concerné peut répondre à cette proposition DUO.');

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
        $contactsList = $validated['contacts'] ?? (!empty($validated['contact_id']) ? [$validated['contact_id']] : []);

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

        // Visite existante rejointe par le superviseur : seul le DUO est accepté, la visite reste planifiée
        if ($activity->statut !== 'en_attente') {
            $activity->update(['duo_delegue_status' => 'accepted', 'duo_delegue_rejection_reason' => null]);
            $this->notifyDuoSupervisor($activity, 'Visite DUO acceptée', "{$activity->delegue?->name} a accepté votre accompagnement.", 'duo_accepte');

            return response()->json([
                'message' => 'Accompagnement DUO accepté.',
                'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account'])),
            ]);
        }

        $activity->update([
            'contact_id'    => $contactsList[0] ?? $activity->contact_id,
            'account_id'    => $validated['account_id'] ?? $activity->account_id,
            'activity_type' => $typeAct,
            'campaign_id'   => $validated['campaign_id'] ?? $activity->campaign_id,
            'notes'         => $activity->notes . (isset($validated['notes']) && $validated['notes'] ? "\n" . $validated['notes'] : ''),
            'statut'        => 'validee',
            'duo_delegue_status' => 'accepted',
        ]);
        $this->notifyDuoSupervisor($activity, 'Visite DUO acceptée', "{$activity->delegue?->name} a accepté la visite DUO.", 'duo_accepte');

        return response()->json([
            'message' => 'Visite DUO acceptée et validée avec succès.',
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account']))
        ]);
    }

    public function refuseDuo(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);
        abort_unless($activity->user_id === auth()->id(), 403, 'Seul le délégué concerné peut répondre à cette proposition DUO.');

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du refus est obligatoire.'
        ]);

        $duoFields = ['duo_delegue_status' => 'rejected', 'duo_delegue_rejection_reason' => $validated['motif']];

        // Proposition créée par le superviseur : la visite elle-même est refusée.
        // Visite existante rejointe : seul l'accompagnement est refusé, la visite reste planifiée.
        $activity->update($activity->statut === 'en_attente'
            ? $duoFields + ['statut' => 'refusee', 'rejection_reason' => $validated['motif'], 'motif' => $validated['motif']]
            : $duoFields);

        \App\Models\CoachingSession::where('activity_id', $activity->id)
            ->where('status', 'active')
            ->update(['status' => 'refused', 'commentaires_superviseur' => $validated['motif']]);

        $this->notifyDuoSupervisor(
            $activity,
            'Visite DUO refusée',
            "Le délégué {$activity->delegue?->name} a refusé la visite DUO. Motif : {$validated['motif']}",
            'duo_refuse',
            ['motif' => $validated['motif']]
        );

        return response()->json([
            'message' => 'Visite DUO refusée avec enregistrement du motif.',
            'data' => new ActivityResource($activity)
        ]);
    }

    /**
     * Le superviseur se joint en DUO à une visite déjà planifiée par un délégué,
     * en précisant s'il s'agit d'une formation ou d'un coaching.
     */
    public function joinDuo(Request $request, string $id)
    {
        $user = auth()->user();
        if ($user->hasAnyRole(['superadmin', 'super-admin', 'Super Administrateur', 'admin'])) {
            return response()->json(['message' => 'Le Super Administrateur ne peut pas accompagner de visite.'], 403);
        }
        if ($user->hasAnyRole(['delegue', 'delegue_medical', 'delegate', 'Délégué', 'Délégué Médical'])) {
            return response()->json(['message' => 'Seul le superviseur ou la hiérarchie peut accompagner une visite en DUO.'], 403);
        }

        $validated = $request->validate([
            'type'     => 'required|in:formation,coaching',
            'objectif' => 'nullable|string|max:1000',
        ]);

        $activity = Activity::withoutGlobalScopes()->with(['account'])->whereNull('deleted_at')->findOrFail($id);

        if (!$user->manages($activity->user_id)) {
            return response()->json(['message' => "Cette visite n'appartient pas à un délégué de votre équipe."], 403);
        }
        if ($activity->user_id === $user->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous accompagner vous-même.'], 422);
        }
        if ($activity->hasActiveDuo() && $activity->duo_user_id !== $user->id) {
            return response()->json(['message' => 'Cette visite est déjà accompagnée par un autre superviseur.'], 422);
        }
        if ($activity->hasActiveDuo() && $activity->duo_user_id === $user->id) {
            return response()->json(['message' => 'Vous accompagnez déjà cette visite.'], 422);
        }
        // Bloquer uniquement les visites terminées ou annulées — pas les validées
        // (une visite validée = planifiée + approuvée = état idéal pour un DUO coaching)
        $blockedStatuts = ['realisee', 'réalisée', 'effectuee', 'effectuée', 'terminee', 'annulee', 'annulée', 'manquee'];
        if (in_array(strtolower((string) $activity->statut), $blockedStatuts)) {
            return response()->json(['message' => 'Impossible de rejoindre une visite déjà réalisée ou annulée.'], 422);
        }
        // Bloquer les visites passées (date strictement avant aujourd'hui) sauf si déjà en cours d'exécution DUO
        if ($activity->date_activite && $activity->date_activite->lt(now()->startOfDay()) && !$activity->is_duo) {
            return response()->json(['message' => 'Impossible de rejoindre une visite passée.'], 422);
        }

        $typeLabel = $validated['type'] === 'formation' ? 'Formation' : 'Coaching';

        $session = \Illuminate\Support\Facades\DB::transaction(function () use ($activity, $user, $validated, $typeLabel) {
            $activity->update([
                'is_duo'                       => true,
                'duo_user_id'                  => $user->id,
                'duo_activity_type_id'         => $this->duoTypeId($validated['type']),
                'duo_status'                   => 'accepted',
                'duo_delegue_status'           => 'pending',
                'duo_rejection_reason'         => null,
                'duo_delegue_rejection_reason' => null,
            ]);

            // Fiche de coaching liée à la visite (objectifs puis résultats en fin de DUO)
            return \App\Models\CoachingSession::create([
                'supervisor_id'         => $user->id,
                'delegate_id'           => $activity->user_id,
                'activity_id'           => $activity->id,
                'date_duo'              => $activity->date_activite,
                'zone'                  => $activity->account?->zone_quartier ?? $activity->account?->ville ?? '',
                'motif'                 => "Visite DUO — {$typeLabel}",
                'objectifs_prevus_json' => array_filter([$validated['objectif'] ?? null]),
                'status'                => 'active',
            ]);
        });

        $delegate = User::find($activity->user_id);
        if ($delegate) {
            $date = $activity->date_activite?->format('d/m/Y');
            $heure = $activity->heure_debut ? substr($activity->heure_debut, 0, 5) : '';
            NotificationController::sendNotification(
                $delegate,
                "Visite DUO — {$typeLabel}",
                "{$user->name} vous accompagnera ({$typeLabel}) lors de votre visite du {$date} à {$heure}.",
                'nouveau_duo',
                'delegate',
                ['activity_id' => (string) $activity->id, 'session_id' => (string) $session->id]
            );
        }

        return response()->json([
            'message' => "Vous accompagnerez cette visite en DUO ({$typeLabel}).",
            'data'    => [
                'activity_id' => (string) $activity->id,
                'session'     => $session,
            ],
        ], 201);
    }

    public function storeTrio(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->hasRole('sales_manager'), 403, 'Seul un Sales Manager peut proposer une visite Trio.');

        $validated = $request->validate([
            'user_id'       => 'required|uuid|exists:users,id',
            'date_activite' => 'required|date|after_or_equal:today',
            'heure_debut'   => 'required|date_format:H:i',
            'heure_fin'     => 'nullable|date_format:H:i|after:heure_debut',
            'duree_minutes' => 'nullable|integer',
            'contact_id'    => 'nullable|uuid|exists:contacts,id',
            'account_id'    => 'nullable|uuid|exists:accounts,id',
            'activity_type' => 'nullable|string',
            'objective'     => 'nullable|string',
            'notes'         => 'nullable|string',
        ]);

        // Vérifier que le type d'activité trio existe si fourni
        if (!empty($validated['activity_type'])) {
            $trioTypeExists = \Illuminate\Support\Facades\DB::table('trio_activity_types')
                ->where('name', $validated['activity_type'])
                ->exists();
            if (!$trioTypeExists) {
                return response()->json(['message' => 'Le type d\'activité trio sélectionné est invalide.'], 422);
            }
        }

        $delegate = User::withoutGlobalScopes()->findOrFail($validated['user_id']);
        abort_unless($delegate->hasAnyRole(['delegue-medical', 'delegue_medical', 'delegue', 'delegate']), 422, 'La visite Trio doit être planifiée avec un délégué médical.');
        $this->ensureSupervises($delegate->id);
        $supervisor = $delegate->manager_id
            ? User::withoutGlobalScopes()->find($delegate->manager_id)
            : null;
        abort_unless(
            $supervisor
                && (string) $supervisor->manager_id === (string) $user->id
                && $supervisor->hasAnyRole(['superviseur', 'supervisor', 'manager', 'Manager']),
            422,
            'Le délégué doit être rattaché à un superviseur directement encadré par vous.'
        );

        $contact = !empty($validated['contact_id'])
            ? \App\Models\Contact::withoutGlobalScopes()->findOrFail($validated['contact_id'])
            : null;
        if ($contact) {
            abort_unless((string) $contact->user_id === (string) $delegate->id, 403, 'Ce prescripteur n’appartient pas au délégué sélectionné.');
        }

        $account = !empty($validated['account_id'])
            ? \App\Models\Account::withoutGlobalScopes()->findOrFail($validated['account_id'])
            : null;
        if ($account) {
            $belongsToDelegate = (string) $account->user_id === (string) $delegate->id
                || (string) $account->delegue_id === (string) $delegate->id
                || ($contact && (string) $contact->account_id === (string) $account->id);
            abort_unless($belongsToDelegate, 403, 'Cet établissement n’appartient pas au délégué sélectionné.');
        }

        $hasOverlap = Activity::checkOverlap(
            $delegate->id,
            $validated['date_activite'],
            $validated['heure_debut'],
            $validated['heure_fin'] ?? null,
            $validated['duree_minutes'] ?? null
        );
        if ($hasOverlap) {
            return response()->json(['message' => 'Le délégué a déjà une visite sur ce créneau.'], 422);
        }

        $activity = Activity::create([
            'subsidiary_id'        => $user->subsidiary_id ?? $supervisor->subsidiary_id,
            'user_id'              => $delegate->id,
            'accompanying_user_id' => $user->id,
            'is_duo'               => true,
            'is_trio'              => true,
            'duo_user_id'          => $supervisor->id,
            'contact_id'           => $validated['contact_id'] ?? null,
            'account_id'           => $validated['account_id'] ?? $contact?->account_id,
            'date_activite'        => $validated['date_activite'],
            'heure_debut'          => $validated['heure_debut'],
            'heure_fin'            => $validated['heure_fin'] ?? null,
            'duree_minutes'        => $validated['duree_minutes'] ?? null,
            'activity_type'        => $validated['activity_type'] ?? 'visite',
            'statut'               => 'en_attente',
            'notes'                => '[VISITE TRIO SALES MANAGER] ' . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe TRIO.'),
        ]);

        $activity->load(['delegue', 'accompanyingUser', 'contact', 'account']);
        $date = $activity->date_activite?->format('d/m/Y');
        $time = substr($activity->heure_debut, 0, 5);
        $visitName = $this->trioVisitName($activity);

        NotificationController::sendNotification(
            $supervisor,
            'Proposition Trio à valider',
            "{$user->name} propose une visite Trio avec {$delegate->name} le {$date} à {$time} ({$visitName}). Vous êtes le superviseur direct chargé de la valider.",
            'trio_transmis',
            'supervisor',
            [
                'activity_id' => (string) $activity->id,
                'action_url' => '/visites',
                'sales_manager_name' => $user->name,
                'delegate_name' => $delegate->name,
                'supervisor_name' => $supervisor->name,
            ]
        );

        return response()->json([
            'message' => 'Visite TRIO créée et transmise au Superviseur pour validation.',
            'data' => new ActivityResource($activity)
        ], 201);
    }

    public function validateTrio(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScopes()
            ->with(['delegue' => fn ($query) => $query->withoutGlobalScopes(), 'accompanyingUser' => fn ($query) => $query->withoutGlobalScopes(), 'contact' => fn ($query) => $query->withoutGlobalScopes(), 'account' => fn ($query) => $query->withoutGlobalScopes()])
            ->findOrFail($id);
        $supervisor = $this->authorizeTrioSupervisor($activity);
        abort_unless($activity->statut === 'en_attente', 422, 'Cette proposition Trio a déjà été traitée.');

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
            'statut'          => 'planifiee',
            'validated_by_id' => auth()->id(),
            'validation_date' => now(),
        ]);

        $activity->load(['delegue', 'accompanyingUser', 'contact', 'account']);
        $delegate = $activity->delegue;
        $salesManager = $activity->accompanyingUser;
        $date = $activity->date_activite?->format('d/m/Y');
        $time = substr($activity->heure_debut, 0, 5);
        $visitName = $this->trioVisitName($activity);

        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Visite Trio planifiée',
                "Votre visite Trio avec {$salesManager?->name} le {$date} à {$time} ({$visitName}) a été validée par votre superviseur {$supervisor->name}.",
                'nouveau_trio',
                'delegate',
                [
                    'activity_id' => (string) $activity->id,
                    'sales_manager_name' => $salesManager?->name,
                    'supervisor_name' => $supervisor->name,
                    'action_url' => '/visites'
                ]
            );
        }

        if ($salesManager) {
            NotificationController::sendNotification(
                $salesManager,
                'Votre demande Trio a été acceptée',
                "Votre demande de visite Trio avec {$delegate?->name} le {$date} à {$time} ({$visitName}) a été acceptée par {$supervisor->name}.",
                'trio_accepte',
                'sales_manager',
                [
                    'activity_id' => (string) $activity->id,
                    'delegate_name' => $delegate?->name,
                    'supervisor_name' => $supervisor->name,
                    'action_url' => '/visites'
                ]
            );
        }

        return response()->json([
            'message' => 'Visite TRIO validée et ajoutée au planning du délégué.',
            'data' => new ActivityResource($activity->load(['delegue']))
        ]);
    }

    public function rejectTrio(Request $request, string $id)
    {
        $activity = Activity::withoutGlobalScopes()
            ->with(['delegue' => fn ($query) => $query->withoutGlobalScopes(), 'accompanyingUser' => fn ($query) => $query->withoutGlobalScopes(), 'contact' => fn ($query) => $query->withoutGlobalScopes(), 'account' => fn ($query) => $query->withoutGlobalScopes()])
            ->findOrFail($id);
        $supervisor = $this->authorizeTrioSupervisor($activity);
        abort_unless($activity->statut === 'en_attente', 422, 'Cette proposition Trio a déjà été traitée.');

        $validated = $request->validate([
            'motif' => 'required|string|min:2',
        ], [
            'motif.required' => 'Le motif du rejet est obligatoire.'
        ]);

        $activity->update([
            'statut'           => 'annulee',
            'rejection_reason' => $validated['motif'],
            'motif'            => $validated['motif'],
            'validated_by_id'  => auth()->id(),
        ]);

        $activity->load(['delegue', 'accompanyingUser', 'contact', 'account']);
        $delegate = $activity->delegue;
        $salesManager = $activity->accompanyingUser;
        $date = $activity->date_activite?->format('d/m/Y');
        $visitName = $this->trioVisitName($activity);

        if ($salesManager) {
            NotificationController::sendNotification(
                $salesManager,
                'Votre demande Trio a été refusée',
                "Votre proposition de visite Trio avec {$delegate?->name} le {$date} ({$visitName}) a été refusée par {$supervisor->name}. Motif : {$validated['motif']}",
                'trio_rejete',
                'sales_manager',
                ['activity_id' => (string) $activity->id, 'motif' => $validated['motif'], 'delegate_name' => $delegate?->name, 'supervisor_name' => $supervisor->name]
            );
        }

        if ($delegate) {
            NotificationController::sendNotification(
                $delegate,
                'Proposition de visite Trio refusée',
                "La proposition de visite Trio avec {$salesManager?->name} le {$date} ({$visitName}) a été refusée par votre superviseur {$supervisor->name}.",
                'trio_rejete',
                'delegate',
                ['activity_id' => (string) $activity->id, 'sales_manager_name' => $salesManager?->name, 'supervisor_name' => $supervisor->name]
            );
        }

        return response()->json([
            'message' => 'Visite TRIO rejetée avec enregistrement du motif.',
            'data' => new ActivityResource($activity)
        ]);
    }

    private function authorizeTrioSupervisor(Activity $activity): User
    {
        abort_unless($activity->isTrio(), 404, 'Cette activité n’est pas une proposition Trio.');

        $supervisor = auth()->user();
        abort_unless($supervisor && $supervisor->hasAnyRole(['superviseur', 'supervisor', 'manager', 'Manager']), 403, 'Seul le superviseur concerné peut traiter cette proposition Trio.');

        $delegate = User::withoutGlobalScopes()->findOrFail($activity->user_id);
        $salesManager = User::withoutGlobalScopes()->findOrFail($activity->accompanying_user_id);

        abort_unless(
            (string) $delegate->manager_id === (string) $supervisor->id
                && (string) $supervisor->manager_id === (string) $salesManager->id
                && $salesManager->hasRole('sales_manager'),
            403,
            'Cette visite Trio ne relève pas de votre hiérarchie directe.'
        );

        return $supervisor;
    }

    private function trioVisitName(Activity $activity): string
    {
        $contactName = trim(($activity->contact?->prenom ?? '') . ' ' . ($activity->contact?->nom ?? ''));
        return $activity->contact?->nom_complet ?: ($contactName ?: ($activity->account?->nom ?: 'visite médicale'));
    }

    /**
     * Les actions de supervision ne portent que sur les collaborateurs encadrés (sauf admin/global).
     */
    private function ensureSupervises(?string $ownerId): void
    {
        $user = auth()->user();
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])) {
            return;
        }
        abort_unless($ownerId && $user->manages($ownerId), 403, "Cette activité n'appartient pas à un délégué de votre équipe.");
    }

    /** ID du type d'activité DUO (duo_activity_types.name). */
    private function duoTypeId(?string $name): ?string
    {
        if (!$name) {
            return null;
        }
        return \Illuminate\Support\Facades\DB::table('duo_activity_types')->where('name', $name)->value('id');
    }

    private function notifyDuoSupervisor(Activity $activity, string $title, string $message, string $type, array $extra = []): void
    {
        $supervisor = $activity->duoUser ?? $activity->accompanyingUser ?? $activity->delegue?->manager;
        if ($supervisor) {
            NotificationController::sendNotification(
                $supervisor, $title, $message, $type, 'supervisor',
                ['activity_id' => (string) $activity->id] + $extra
            );
        }
    }

    /**
     * PATCH activities/{id}/duo — le superviseur modifie le type d'activité ou l'objectif de son DUO.
     */
    public function updateDuo(Request $request, string $id)
    {
        $activity = $this->ownDuo($id);
        $validated = $request->validate([
            'type'     => 'nullable|in:formation,coaching',
            'objectif' => 'nullable|string|max:1000',
        ]);

        if (!empty($validated['type'])) {
            $activity->update(['duo_activity_type_id' => $this->duoTypeId($validated['type'])]);
        }
        if (array_key_exists('objectif', $validated)) {
            \App\Models\CoachingSession::where('activity_id', $activity->id)
                ->where('supervisor_id', auth()->id())
                ->whereIn('status', ['draft', 'active'])
                ->update(['objectifs_prevus_json' => json_encode(array_values(array_filter([$validated['objectif']])))]);
        }

        $this->notifyDuoDelegate($activity, 'Visite DUO modifiée', auth()->user()->name . ' a modifié la visite DUO du ' . $activity->date_activite?->format('d/m/Y') . '.');

        return response()->json(['message' => 'Visite DUO modifiée.', 'data' => new ActivityResource($activity->fresh(['delegue', 'duoUser', 'duoActivityType']))]);
    }

    /**
     * DELETE activities/{id}/duo — le superviseur annule son DUO.
     * Proposition créée de toutes pièces (statut en_attente) : la visite est supprimée ;
     * visite planifiée par le délégué : seule la participation est retirée.
     */
    public function cancelDuo(string $id)
    {
        $activity = $this->ownDuo($id);

        \App\Models\CoachingSession::where('activity_id', $activity->id)
            ->where('supervisor_id', auth()->id())
            ->whereIn('status', ['draft', 'active'])
            ->delete();

        $this->notifyDuoDelegate($activity, 'Visite DUO annulée', auth()->user()->name . ' a annulé la visite DUO du ' . $activity->date_activite?->format('d/m/Y') . '.');

        if ($activity->statut === 'en_attente') {
            $activity->delete();
        } else {
            $activity->update(['is_duo' => false, 'duo_status' => 'cancelled', 'duo_delegue_status' => null]);
        }

        return response()->json(['message' => 'Visite DUO annulée.']);
    }

    private function ownDuo(string $id): Activity
    {
        $activity = Activity::withoutGlobalScopes()->whereNull('deleted_at')->with(['delegue'])->findOrFail($id);
        abort_unless($activity->is_duo && $activity->duo_user_id === auth()->id(), 403, "Vous n'animez pas ce DUO.");
        abort_if(in_array($activity->statut, ['realisee', 'effectuee', 'validee', 'terminee'], true) && $activity->date_activite?->lt(now()->startOfDay()), 422, 'Ce DUO est déjà passé.');

        return $activity;
    }

    private function notifyDuoDelegate(Activity $activity, string $title, string $message): void
    {
        if ($activity->delegue) {
            NotificationController::sendNotification($activity->delegue, $title, $message, 'nouveau_duo', 'delegate', ['activity_id' => (string) $activity->id]);
        }
    }

    /**
     * POST /api/v1/activities/{id}/start - Démarrer une visite et enregistrer la position GPS
     */
    public function startVisit(Request $request, string $id)
    {
        $user = auth()->user();
        $activity = Activity::withoutGlobalScopes()->findOrFail($id);

        // Vérifier que l'utilisateur est le délégué assigné ou le superviseur en duo
        abort_unless(
            $activity->user_id === $user->id || $activity->duo_user_id === $user->id,
            403,
            "Vous n'êtes pas autorisé à démarrer cette visite."
        );

        // Vérifier que la visite n'est pas déjà en cours ou terminée
        abort_if(
            in_array($activity->statut, ['en_cours', 'realisee', 'terminee', 'annulee']),
            422,
            "Cette visite ne peut pas être démarrée dans son état actuel."
        );

        $validated = $request->validate([
            'gps_lat' => 'required|numeric|between:-90,90',
            'gps_lng' => 'required|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
        ]);

        // Mettre à jour la visite avec la position GPS et le statut en_cours
        $activity->update([
            'statut' => 'en_cours',
            'gps_debut_lat' => $validated['gps_lat'],
            'gps_debut_lng' => $validated['gps_lng'],
            'gps_debut_accuracy' => $validated['gps_accuracy'] ?? null,
            'start_time' => now(),
        ]);

        return response()->json([
            'message' => 'Visite démarrée avec succès',
            'data' => new ActivityResource($activity->fresh(['delegue', 'contact', 'account']))
        ]);
    }

    /**
     * POST /api/v1/activities/{id}/complete - Terminer une visite et enregistrer la position GPS finale
     */
    public function completeVisit(Request $request, string $id)
    {
        $user = auth()->user();
        $activity = Activity::withoutGlobalScopes()->findOrFail($id);

        // Vérifier que l'utilisateur est le délégué assigné ou le superviseur en duo
        abort_unless(
            $activity->user_id === $user->id || $activity->duo_user_id === $user->id,
            403,
            "Vous n'êtes pas autorisé à terminer cette visite."
        );

        // Vérifier que la visite est en cours
        abort_if(
            !in_array($activity->statut, ['en_cours', 'planifiee', 'en_attente']),
            422,
            "Cette visite n'est pas en cours."
        );

        $validated = $request->validate([
            'gps_lat' => 'required|numeric|between:-90,90',
            'gps_lng' => 'required|numeric|between:-180,180',
            'gps_accuracy' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'report_content' => 'nullable|string',
        ]);

        // Calculer la durée si on a un start_time
        $dureeMinutes = null;
        if ($activity->start_time) {
            $dureeMinutes = now()->diffInMinutes($activity->start_time);
        }

        // Mettre à jour la visite avec la position GPS finale et le statut réalisee
        $activity->update([
            'statut' => 'realisee',
            'gps_fin_lat' => $validated['gps_lat'],
            'gps_fin_lng' => $validated['gps_lng'],
            'gps_fin_accuracy' => $validated['gps_accuracy'] ?? null,
            'end_time' => now(),
            'duree_minutes' => $dureeMinutes,
            'notes' => $validated['notes'] ?? $activity->notes,
            'report_content' => $validated['report_content'] ?? $activity->report_content,
        ]);

        // Verrouiller l'activité après réalisation
        $activity->lock();

        return response()->json([
            'message' => 'Visite terminée avec succès',
            'data' => new ActivityResource($activity->fresh(['delegue', 'contact', 'account']))
        ]);
    }
}
