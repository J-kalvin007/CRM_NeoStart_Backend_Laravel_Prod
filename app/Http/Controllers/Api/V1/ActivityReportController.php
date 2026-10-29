<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Objection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Activity Reports', description: 'Gestion des comptes-rendus de visites')]
class ActivityReportController extends Controller
{
    #[OA\Put(
        path: '/api/v1/activities/{activity}/report',
        summary: 'Soumettre ou modifier un compte-rendu',
        security: [['bearerAuth' => []]],
        tags: ['Activity Reports'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'report_content', type: 'string'),
                    new OA\Property(property: 'report_status', type: 'string', enum: ['draft', 'submitted']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu mis à jour'),
        ]
    )]
    public function update(Request $request, Activity $activity)
    {
        abort_unless((string) $activity->user_id === (string) $request->user()->id, 403, 'Seul le délégué de cette visite peut rédiger son compte-rendu.');

        return DB::transaction(function () use ($request, $activity) {
            User::withoutGlobalScopes()->whereKey($activity->user_id)->lockForUpdate()->firstOrFail();
            $activity->refresh();

            return $this->updateReport($request, $activity);
        });
    }

    private function updateReport(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'report_content' => 'nullable|string',
            'report_status' => 'required|in:draft,submitted',
            'pharmacy_inventory' => 'nullable|array',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|uuid|exists:products,id',
            'objections' => 'nullable|array',
            'objections.*.objection_id' => 'nullable|uuid|exists:objections,id',
            'objections.*.product_id' => 'nullable|uuid|exists:products,id',
            'objections.*.title' => 'nullable|string|max:255',
            'objections.*.physician_reaction' => 'required|in:convaincu,partiellement_convaincu,refus',
            'samples' => 'nullable|array',
            'samples.*.product_id' => 'required|uuid|exists:products,id',
            'samples.*.lot_number' => 'required|string|max:100',
            'samples.*.quantity' => 'required|integer|min:1',
            'statut' => 'nullable|string|in:realisee',
            'notes' => 'nullable|string',
            'prochain_rdv' => 'nullable|date',
            'gps_debut_lat' => 'nullable|numeric|between:-90,90',
            'gps_debut_lng' => 'nullable|numeric|between:-180,180',
            'gps_fin_lat' => 'nullable|numeric|between:-90,90',
            'gps_fin_lng' => 'nullable|numeric|between:-180,180',
            'signature_base64' => 'nullable|string',
            'niveau_interet' => 'nullable|integer',
            'motif' => 'nullable|string',
        ]);

        abort_unless((string) $activity->user_id === (string) auth()->id(), 403, 'Seul le délégué de cette visite peut rédiger son compte-rendu.');
        abort_if($activity->isCalendarActivity(), 422, 'Cette activité ne nécessite pas de compte-rendu de visite.');

        abort_if(($validated['report_status'] === 'submitted' || ($validated['statut'] ?? null) === 'realisee') && ! in_array($activity->statut, ['en_cours', 'realisee', 'effectuee', 'terminee', 'cloturee', 'soumis_definitif'], true), 422, 'Démarrez la visite puis terminez son exécution avant de soumettre le compte-rendu.');

        // RÈGLE : Si on tente de soumettre le rapport d'aujourd'hui mais qu'il existe des rapports non soumis des jours précédents
        if ($validated['report_status'] === 'submitted') {
            $activityDate = Carbon::parse($activity->date_activite ?? $activity->created_at);
            $todayStart = Carbon::today()->startOfDay();

            // Si la visite est celle d'aujourd'hui ou récente
            if ($activityDate->gte($todayStart)) {
                $userId = $activity->user_id ?? auth()->id();
                // Comptes-rendus en retard : visites des jours précédents effectivement démarrées ou réalisées
                // dont le rapport n'a pas été soumis (ou a été rejeté et doit être corrigé)
                $unsubmittedPastCount = Activity::visits()->where('user_id', $userId)
                    ->where('id', '!=', $activity->id)
                    ->whereDate('date_activite', '<', $todayStart)
                    ->whereIn('statut', ['en_cours', 'realisee'])
                    ->where(function ($q) {
                        $q->whereNull('report_status')
                            ->orWhereIn('report_status', ['draft', 'brouillon']);
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

        // A queued mobile report can carry the checkout that never reached the server.
        // Reuse the normal checkout validation and stock transaction, under the same owner lock.
        if (($request->input('statut') === 'realisee' || $validated['report_status'] === 'submitted') && $activity->statut === 'en_cours') {
            $checkout = UpdateActivityRequest::createFrom($request);
            $checkoutData = $request->only(['statut', 'heure_fin', 'duree_minutes', 'gps_fin_lat', 'gps_fin_lng', 'products', 'samples', 'pharmacy_inventory']);
            $checkoutData['statut'] = 'realisee';
            $checkout->request = new InputBag($checkoutData);
            $checkout->setJson(new InputBag($checkoutData));
            $checkout->setContainer(app())->setRedirector(app('redirect'));
            $route = clone $request->route();
            $route->setParameter('activity', $activity->id);
            $checkout->setRouteResolver(fn () => $route);
            $checkout->validateResolved();
            $response = app(ActivityController::class)->update($checkout, $activity->id);
            if ($response instanceof Response && $response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }
            $activity->refresh();
        }
        if (! empty($validated['samples'])) {
            $expected = collect($validated['samples'])->groupBy(fn ($item) => $item['product_id'].'|'.$item['lot_number'])->map(fn ($items) => (int) $items->sum('quantity'))->sortKeys()->all();
            $recorded = $activity->sampleMovements()->where('movement_type', 'distribution')->get()->groupBy(fn ($item) => $item->product_id.'|'.$item->lot_number)->map(fn ($items) => (int) $items->sum('quantity'))->sortKeys()->all();
            abort_unless($expected === $recorded, 422, 'Les échantillons du rapport ne correspondent pas aux remises enregistrées. Aucun mouvement de stock supplémentaire n’a été créé.');
        }

        $data = $validated;
        unset($data['samples'],$data['objections']);
        if ($validated['report_status'] === 'submitted') {
            $data['statut'] = 'realisee';
            // Nouvelle soumission : l'ancien motif de rejet ne s'applique plus
            $data['rejection_reason'] = null;
        }
        if ($request->has('notes') && ! empty($request->input('notes'))) {
            $data['notes'] = $request->input('notes');
        }
        if ($request->has('signature') && ! empty($request->input('signature'))) {
            $data['signature'] = $request->input('signature');
        }
        // Prochaine visite (date et heure) ; une valeur vide efface celle enregistrée
        if ($request->has('prochain_rdv')) {
            $data['prochain_rdv'] = $request->filled('prochain_rdv') ? $request->input('prochain_rdv') : null;
        }
        if ($request->has('heure_fin') && ! empty($request->input('heure_fin'))) {
            $data['heure_fin'] = $request->input('heure_fin');
        }
        if ($request->has('duree_minutes') && ! empty($request->input('duree_minutes'))) {
            $data['duree_minutes'] = $request->input('duree_minutes');
        }
        if ($request->has('gps_fin_lat') && ! empty($request->input('gps_fin_lat'))) {
            $data['gps_fin_lat'] = $request->input('gps_fin_lat');
        }
        if ($request->has('gps_fin_lng') && ! empty($request->input('gps_fin_lng'))) {
            $data['gps_fin_lng'] = $request->input('gps_fin_lng');
        }

        // Résultat global, prochaine action et relevé stock pharmacie : conservés dans meta
        $meta = $activity->meta ?? [];
        if ($request->has('pharmacy_inventory')) {
            $meta['pharmacy_inventory'] = $request->input('pharmacy_inventory');
        }
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

        // Synchronisation des produits présentés (notamment lors du rejeu de la file hors-ligne)
        if ($request->has('products') && is_array($request->input('products'))) {
            $syncData = [];
            foreach ($request->input('products') as $p) {
                if (! empty($p['product_id'])) {
                    $syncData[$p['product_id']] = [
                        'id' => Str::uuid()->toString(),
                        'quantity' => $p['quantity'] ?? 0,
                        'customer_interest' => $p['customer_interest'] ?? null,
                        'remarques' => $p['remarques'] ?? null,
                    ];
                }
            }
            if (! empty($syncData)) {
                $activity->products()->sync($syncData);
            }
        }

        // Synchronisation des objections formulées par le prescripteur lors de la visite
        if ($request->has('objections') && is_array($request->input('objections'))) {
            $syncObjections = [];
            foreach ($request->input('objections') as $obj) {
                if (! is_array($obj)) {
                    continue;
                }
                $objection = null;
                $objId = $obj['objection_id'] ?? null;
                if (! empty($objId) && Str::isUuid($objId)) {
                    $objection = Objection::find($objId);
                }
                if (! $objection && ! empty($obj['title'])) {
                    $productId = (! empty($obj['product_id']) && Str::isUuid($obj['product_id']))
                        ? $obj['product_id']
                        : null;

                    abort_unless($productId, 422, 'Indiquez le produit réellement concerné par cette objection.');
                    if ($productId) {
                        $objection = Objection::firstOrCreate([
                            'product_id' => $productId,
                            'title' => $obj['title'],
                        ], [
                            'recommended_argument' => $obj['recommended_argument'] ?? '',
                        ]);
                    }
                }
                if ($objection) {
                    $syncObjections[$objection->id] = [
                        'physician_reaction' => $obj['physician_reaction'],
                        'notes' => $obj['notes'] ?? null,
                    ];
                }
            }
            if (! empty($syncObjections)) {
                $activity->objections()->syncWithoutDetaching($syncObjections);
            }
        }

        if ($activity->statut === 'realisee') {
            $activity->lock();
        }

        if ($validated['report_status'] === 'submitted') {
            $user = $activity->delegue ?? $activity->user ?? auth()->user();
            NotificationController::notifyUserAndManagers(
                $user ?? auth()->id(),
                'Nouveau compte-rendu de visite soumis',
                'Le compte-rendu de visite a été soumis avec succès.',
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
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['validated', 'rejected']),
                    new OA\Property(property: 'rejection_reason', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu traité'),
        ]
    )]
    public function validateReport(Request $request, Activity $activity)
    {
        abort(422, 'Les comptes rendus sont uniquement enregistrés en brouillon ou soumis, sans validation ni rejet.');
    }
}
