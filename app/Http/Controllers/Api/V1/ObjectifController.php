<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ObjectifResource;
use App\Http\Requests\StoreObjectifRequest;
use App\Http\Requests\UpdateObjectifRequest;
use App\Models\Objectif;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ObjectifController extends Controller
{
    #[OA\Get(path: '/api/v1/objectifs', summary: 'Lister les objectifs de performance', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'team_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'indicator_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'statut', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['actif','atteint','depasse','non_atteint','annule'])),
            new OA\Parameter(name: 'periode', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Ex: Q3-2026, Oct-2026'),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des objectifs')]
    )]
    public function index(Request $request)
    {
        $query = Objectif::with(['user', 'team', 'product', 'indicator', 'type', 'organizationalUnit'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->team_id, fn($q, $v) => $q->where('team_id', $v))
            ->when($request->indicator_id, fn($q, $v) => $q->where('indicator_id', $v))
            ->when($request->statut, fn($q, $v) => $q->where('statut', $v))
            ->when($request->periode, fn($q, $v) => $q->where('periode', 'like', "%$v%"));
        return ObjectifResource::collection($query->paginate(25));
    }

    #[OA\Post(path: '/api/v1/objectifs', summary: 'Créer un objectif de performance', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['indicator_id', 'valeur_cible', 'date_debut', 'date_fin'],
            properties: [
                new OA\Property(property: 'user_id', type: 'integer', nullable: true),
                new OA\Property(property: 'team_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'organizational_unit_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'indicator_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'objectif_type_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'valeur_cible', type: 'number', format: 'float', example: 100),
                new OA\Property(property: 'date_debut', type: 'string', format: 'date'),
                new OA\Property(property: 'date_fin', type: 'string', format: 'date'),
                new OA\Property(property: 'periode', type: 'string', example: 'Q3-2026', nullable: true),
                new OA\Property(property: 'product_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid'),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Objectif créé avec succès')]
    )]
    public function store(StoreObjectifRequest $request)
    {
        // Le délégué médical n'est pas autorisé à créer des objectifs
        if (auth()->user()->hasRole('delegue-medical')) {
            return response()->json(['message' => 'Non autorisé. Seul un superviseur ou directeur peut créer des objectifs.'], 403);
        }
        $data = $request->validated();
        $data['assigned_by_user_id'] = auth()->id();
        $objectif = Objectif::create($data);
        $objectif->recalculerTaux();
        return new ObjectifResource($objectif->load(['user', 'team', 'product', 'indicator', 'type', 'organizationalUnit']));
    }

    #[OA\Get(path: '/api/v1/objectifs/{id}', summary: 'Détails d\'un objectif', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Détails de l\'objectif'), new OA\Response(response: 404, description: 'Non trouvé')]
    )]
    public function show(string $id)
    {
        return new ObjectifResource(Objectif::with(['user', 'team', 'product', 'indicator', 'type', 'organizationalUnit'])->findOrFail($id));
    }

    #[OA\Put(path: '/api/v1/objectifs/{id}', summary: 'Mettre à jour un objectif (valeur réalisée)', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'valeur_realisee', type: 'number', format: 'float'),
            new OA\Property(property: 'statut', type: 'string', enum: ['actif','atteint','depasse','non_atteint','annule']),
        ])),
        responses: [new OA\Response(response: 200, description: 'Objectif mis à jour')]
    )]
    public function update(UpdateObjectifRequest $request, string $id)
    {
        // Tout le monde peut mettre à jour, mais le délégué médical ne peut théoriquement
        // mettre à jour que la valeur réalisée (contrôlé par le frontend/form request).
        // On n'empêche pas l'accès ici pour permettre l'enregistrement de la valeur réalisée.
        $objectif = Objectif::findOrFail($id);
        $objectif->update($request->validated());
        $objectif->recalculerTaux();
        return new ObjectifResource($objectif->load(['user', 'team', 'product', 'indicator', 'type', 'organizationalUnit']));
    }

    #[OA\Delete(path: '/api/v1/objectifs/{id}', summary: 'Supprimer un objectif', security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimé avec succès')]
    )]
    public function destroy(string $id)
    {
        if (auth()->user()->hasRole('delegue-medical')) {
            return response()->json(['message' => 'Non autorisé. Seul un superviseur ou directeur peut supprimer des objectifs.'], 403);
        }

        Objectif::findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    #[OA\Get(path: '/api/v1/objectifs/{id}/history', summary: "Historique d'évolution d'un objectif", security: [['bearerAuth' => []]], tags: ['Objectifs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Historique des modifications')]
    )]
    public function history(string $id)
    {
        $objectif = Objectif::findOrFail($id);
        
        // On récupère les logs en les chargeant avec l'utilisateur qui a fait l'action
        $activities = $objectif->activities()->with('causer')->orderBy('created_at', 'desc')->get();
        
        $history = $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'description' => $activity->description,
                'properties' => $activity->properties,
                'causer_name' => $activity->causer ? $activity->causer->name : 'Système',
                'created_at' => $activity->created_at,
            ];
        });

        return response()->json(['data' => $history]);
    }

    #[OA\Get(
        path: '/api/v1/me/objectifs-summary',
        summary: 'Résumé des objectifs du mois en cours avec calcul des primes',
        security: [['bearerAuth' => []]],
        tags: ['Objectifs'],
        responses: [
            new OA\Response(response: 200, description: 'Résumé des objectifs')
        ]
    )]
    public function summary()
    {
        $user = auth()->user();
        $now = now();
        $objectifs = Objectif::where('user_id', $user->id)
            ->whereMonth('date_debut', '<=', $now->month)
            ->whereMonth('date_fin', '>=', $now->month)
            ->whereYear('date_debut', '<=', $now->year)
            ->whereYear('date_fin', '>=', $now->year)
            ->get();

        $summary = [];
        $totalTaux = 0;
        $count = 0;

        foreach ($objectifs as $obj) {
            $realise = 0;
            if ($obj->type_objectif === 'nombre_visites') {
                $realise = \App\Models\Activity::where('user_id', $user->id)
                    ->where('activity_type', 'visite')
                    ->where('statut', 'realisee')
                    ->whereMonth('date_activite', $now->month)
                    ->count();
            } elseif ($obj->type_objectif === 'couverture_categorie_a') {
                // Mock logic for catégorie A
                $realise = \App\Models\Activity::where('user_id', $user->id)
                    ->where('activity_type', 'visite')
                    ->where('statut', 'realisee')
                    ->whereMonth('date_activite', $now->month)
                    ->whereHas('contact', function ($q) {
                        // Assuming category exists, maybe in 'meta' or directly
                        $q->where('meta->categorie', 'A');
                    })
                    ->distinct('contact_id')
                    ->count('contact_id');
            } elseif ($obj->type_objectif === 'produit_prioritaire') {
                $realise = \App\Models\Activity::where('user_id', $user->id)
                    ->where('activity_type', 'visite')
                    ->where('statut', 'realisee')
                    ->whereMonth('date_activite', $now->month)
                    ->whereHas('products', function ($q) use ($obj) {
                        $q->where('product_id', $obj->product_id);
                    })
                    ->count();
            } elseif ($obj->type_objectif === 'ca_ventes') {
                $realise = \App\Models\Order::where('user_id', $user->id)
                    ->whereIn('status', ['confirmed', 'invoiced', 'validee', 'livree'])
                    ->whereMonth('order_date', $now->month)
                    ->sum('total_amount');
            }

            // Mettre à jour l'objectif avec la valeur calculée
            $obj->valeur_realisee = $realise;
            $obj->recalculerTaux();
            $obj->save();

            $taux = $obj->valeur_cible > 0 ? ($realise / $obj->valeur_cible) * 100 : 0;
            $totalTaux += $taux;
            $count++;

            $summary[] = [
                'id' => $obj->id,
                'type' => $obj->type_objectif,
                'cible' => $obj->valeur_cible,
                'realise' => $realise,
                'taux_atteinte' => round(min(100, $taux), 2),
            ];
        }

        // Calcul simple de la prime (ex: prime max = 500 € par mois)
        $primeMax = 500;
        $tauxMoyen = $count > 0 ? ($totalTaux / $count) : 0;
        $primeEstimee = round(($tauxMoyen / 100) * $primeMax, 2);

        return response()->json([
            'objectifs' => $summary,
            'taux_moyen' => round(min(100, $tauxMoyen), 2),
            'prime_estimee' => $primeEstimee,
            'devise' => '€'
        ]);
    }
}
