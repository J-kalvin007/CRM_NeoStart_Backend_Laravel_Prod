<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
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
        $isGlobal = $user->hasAnyRole(['superadmin', 'admin']) || $user->can('scope:global');
        $isManager = $user->hasAnyRole(['superviseur', 'directeur_regional', 'directeur_commercial', 'directeur_marketing', 'manager']);

        $query = Activity::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)
            ->with([
                'delegue',
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

        // Filtrage par périmètre organisationnel sélectionné dans le frontend (header X-Organizational-Unit-Id)
        $perimeterUnitId = app()->bound('active_perimeter_unit_id') ? app('active_perimeter_unit_id') : null;
        if ($perimeterUnitId && !$request->user_id && !$isGlobal) {
            $userIdsInUnit = $this->getUserIdsInUnit($perimeterUnitId);
            if (!empty($userIdsInUnit)) {
                $query->whereIn('activities.user_id', $userIdsInUnit);
            }
        }

        // Restreindre la visibilité si pas d'accès global
        if (!$isGlobal) {
            $query->where(function ($q) use ($user, $isManager) {
                // Voit toujours ses propres visites
                $q->where('activities.user_id', $user->id);

                if ($isManager) {
                    // S'il est manager, voit les visites de son équipe ou des délégués sous lui
                    $q->orWhereHas('delegue', function ($delegueQuery) use ($user) {
                        $delegueQuery->where('manager_id', $user->id);
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

        // Si l'utilisateur est admin/superviseur et qu'il fournit un user_id, on l'utilise
        $user = auth()->user();
        $targetUserId = $user->id;
        
        if (isset($data['user_id']) && ($user->hasRole(['superadmin', 'admin', 'superviseur', 'manager']) || $user->can('create-others-activities'))) {
            $targetUserId = $data['user_id'];
        }

        // Vérification de chevauchement : interdire deux visites à la même heure pour le même utilisateur
        if (!empty($data['date_activite']) && !empty($data['heure_debut'])) {
            $overlap = Activity::where('user_id', $targetUserId)
                ->where('date_activite', $data['date_activite'])
                ->where('heure_debut', $data['heure_debut'])
                ->whereNotIn('statut', ['annulee', 'rejetee'])
                ->exists();
            if ($overlap) {
                return response()->json([
                    'message' => 'Ce délégué a déjà une visite planifiée à cette date et à cette heure.'
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
        $activity = Activity::with([
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
        if ((!empty($validatedData['date_activite']) || !empty($activity->date_activite)) &&
            (!empty($validatedData['heure_debut']) || !empty($activity->heure_debut))) {
            $checkDate = $validatedData['date_activite'] ?? $activity->date_activite;
            $checkTime = $validatedData['heure_debut'] ?? $activity->heure_debut;
            
            // Si la date ou l'heure changent, on vérifie
            if ($checkDate !== $activity->date_activite || $checkTime !== $activity->heure_debut) {
                $overlap = Activity::where('user_id', $activity->user_id)
                    ->where('id', '!=', $activity->id)
                    ->where('date_activite', $checkDate)
                    ->where('heure_debut', $checkTime)
                    ->whereNotIn('statut', ['annulee', 'rejetee'])
                    ->exists();
                if ($overlap) {
                    return response()->json([
                        'message' => 'Ce délégué a déjà une visite planifiée à cette date et à cette heure.'
                    ], 422);
                }
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
        $activity = Activity::findOrFail($id);

        $request->validate([
            'statut' => 'required|in:validee,validated,rejetee,refusee',
            'rejection_reason' => 'required_if:statut,rejetee,refusee|string|nullable'
        ]);

        $st = strtolower($request->statut);
        $newStatut = ($st === 'validee' || $st === 'validated') ? 'validee' : 'refusee';

        $notes = $activity->notes;
        if (in_array($st, ['rejetee', 'refusee']) && $request->rejection_reason) {
            $notes = $notes ? $notes . "\n[Rejeté]: " . $request->rejection_reason : "[Rejeté]: " . $request->rejection_reason;
        }

        $activity->update([
            'statut' => $newStatut,
            'validated_by_id' => auth()->id(),
            'validation_date' => now(),
            'notes' => $notes,
            'rejection_reason' => $request->rejection_reason ?? null,
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
        $validated = $request->validate([
            'user_id'       => 'nullable|string',
            'delegate_id'   => 'nullable|string',
            'contact_id'    => 'nullable|string',
            'account_id'    => 'nullable|string',
            'date_activite' => 'nullable|string',
            'date_duo'      => 'nullable|string',
            'notes'         => 'nullable|string',
            'objective'     => 'nullable|string',
            'activity_type' => 'nullable|string',
        ]);

        $targetUserId = $validated['user_id'] ?? $validated['delegate_id'] ?? auth()->id();
        $subsidiary = \App\Models\Subsidiary::first();
        $dateAct = $validated['date_activite'] ?? $validated['date_duo'] ?? now()->toDateString();

        $activity = Activity::create([
            'subsidiary_id' => $subsidiary?->id,
            'user_id' => $targetUserId,
            'contact_id' => $validated['contact_id'] ?? null,
            'account_id' => $validated['account_id'] ?? null,
            'date_activite' => $dateAct,
            'activity_type' => 'visite',
            'statut' => 'planifiee',
            'is_duo' => true,
            'notes' => '[VISITE DUO SUPERVISEUR] ' . ($validated['notes'] ?? $validated['objective'] ?? 'Visite conjointe de coaching avec le superviseur.'),
        ]);

        // Send Notification to Delegate
        $delegate = \App\Models\User::find($targetUserId);
        if ($delegate) {
            \App\Models\Notification::create([
                'user_id' => $delegate->id,
                'title' => 'Visite en Duo Programmée',
                'message' => "Votre superviseur a programmé une visite en duo pour le {$dateAct}.",
                'type' => 'duo',
                'read' => false,
            ]);
        }

        return response()->json([
            'message' => 'Visite en Duo programmée avec succès',
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account']))
        ], 201);
    }
}
