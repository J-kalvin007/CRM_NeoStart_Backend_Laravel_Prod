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

        $query = Activity::with(['delegue', 'contact', 'account', 'products'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->activity_type, fn($q, $v) => $q->where('activity_type', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->report_status, fn($q, $v) => $q->where('report_status', $v))
            ->when($request->date_debut, fn($q, $v) => $q->whereDate('date_activite', '>=', $v))
            ->when($request->date_fin, fn($q, $v) => $q->whereDate('date_activite', '<=', $v))
            ->when($request->contact_id, fn($q, $v) => $q->where('contact_id', $v))
            ->when($request->account_id, fn($q, $v) => $q->where('account_id', $v))
            ->when($request->tour_plan_id, fn($q, $v) => $q->where('tour_plan_id', $v))
            ->orderByDesc('date_activite');

        $perPage = (int) $request->query('per_page', 200);
        return ActivityResource::collection($query->paginate($perPage));
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
        unset($data['samples']);

        // Convertir 'soumis_definitif' en 'realisee' pour la logique interne (optionnel, mais utile)
        if (isset($data['statut']) && $data['statut'] === 'soumis_definitif') {
            $data['statut'] = 'realisee';
        }

        $activity = Activity::create(array_merge($data, ['user_id' => auth()->id()]));

        // Attacher les échantillons (SampleMovement)
        if (!empty($samples)) {
            foreach ($samples as $sample) {
                \App\Models\SampleMovement::create([
                    'product_id' => $sample['product_id'],
                    'user_id' => auth()->id(),
                    'contact_id' => $data['contact_id'] ?? null,
                    'activity_id' => $activity->id,
                    'movement_type' => 'out',
                    'quantity' => reset($sample['quantite']) ?? $sample['quantite'], // in case it's parsed as array somehow
                    'lot_number' => $sample['num_lot'] ?? null,
                    'expiry_date' => $sample['date_peremption'] ?? null,
                ]);
            }
        }

        // Verrouiller automatiquement si statut = realisee ou soumis_definitif
        if (in_array($activity->statut, ['realisee', 'soumis_definitif'])) {
            $activity->lock();
        }

        // Notification au manager
        $user = auth()->user();
        $manager = $user?->manager ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager', 'Superviseur', 'dir-commercial']))->first();
        if ($manager && $user) {
            if (in_array($activity->statut, ['realisee', 'soumis_definitif'])) {
                app(\App\Services\NotificationService::class)->send(
                    $manager,
                    'Nouveau compte-rendu de visite',
                    "Le délégué {$user->name} a finalisé son compte-rendu de visite.",
                    'report',
                    ['activity_id' => $activity->id, 'recipient_role' => 'manager', 'sender_id' => $user->id]
                );
            } else {
                app(\App\Services\NotificationService::class)->send(
                    $manager,
                    'Nouvelle visite planifiée',
                    "Le délégué {$user->name} a planifié une nouvelle visite.",
                    'planning',
                    ['activity_id' => $activity->id, 'recipient_role' => 'manager', 'sender_id' => $user->id]
                );
            }
        }

        return new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products', 'sampleMovements']));
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
        $activity = Activity::with(['delegue', 'contact', 'account', 'tourPlan', 'products', 'sampleMovements'])->findOrFail($id);
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

        $activity->update($request->validated());

        if ($activity->statut === 'realisee') {
            $activity->lock();
        }

        return new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products']));
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
        if ($activity->is_locked) {
            return response()->json(['message' => 'Activité verrouillée.'], 403);
        }
        $activity->delete();
    }

    public function validateActivity(Request $request, string $id)
    {
        $activity = Activity::findOrFail($id);

        $request->validate([
            'statut' => 'required|in:validee,rejetee',
            'rejection_reason' => 'required_if:statut,rejetee|string|nullable'
        ]);

        $newStatut = $request->statut === 'validee' ? 'realisee' : 'annulee';

        $notes = $activity->notes;
        if ($request->statut === 'rejetee') {
            $notes = $notes ? $notes . "\n[Rejeté]: " . $request->rejection_reason : "[Rejeté]: " . $request->rejection_reason;
        }

        $activity->update([
            'statut' => $newStatut,
            'notes' => $notes
        ]);

        return response()->json([
            'message' => 'Activity validated successfully', 
            'data' => new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products']))
        ]);
    }
}
