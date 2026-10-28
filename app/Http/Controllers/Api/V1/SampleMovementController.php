<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use App\Http\Resources\SampleMovementResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Stocks', description: 'Gestion des stocks et mouvements d\'échantillons médicaux')]
class SampleMovementController extends Controller
{
    #[OA\Get(
        path: '/api/v1/sample-movements',
        summary: 'Lister les mouvements de stock d\'échantillons',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        parameters: [
            new OA\Parameter(name: 'product_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par produit'),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par utilisateur/délégué'),
            new OA\Parameter(name: 'movement_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['reception', 'attribution', 'distribution', 'retour', 'destruction', 'inventaire']), description: 'Type de mouvement'),
            new OA\Parameter(name: 'lot_number', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Numéro de lot'),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début (YYYY-MM-DD)'),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin (YYYY-MM-DD)'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des mouvements paginée'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SampleMovement::with(['product', 'user', 'activity'])
            ->when($request->filled('product_id'), fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->filled('movement_type'), fn($q) => $q->where('movement_type', $request->movement_type))
            ->when($request->filled('lot_number'), fn($q) => $q->where('lot_number', 'like', "%{$request->lot_number}%"))
            ->when($request->filled('contact_id'), fn($q) => $q->where('contact_id', $request->contact_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to));

        if (!$user->isAdminOrDirection()) {
            $query->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds()));
        }
        if ($request->filled('user_id')) {
            abort_unless($user->canEditRecordOf((string) $request->user_id), 403);
            $query->where('user_id', $request->user_id);
        }

        $movements = $query->latest()->paginate($request->integer('per_page', 25));

        return SampleMovementResource::collection($movements);
    }

    #[OA\Get(
        path: '/api/v1/my-stock',
        summary: 'Consulter l\'état consolidé du stock du délégué connecté',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Synthèse du stock par produit avec quantités disponibles et distribuées'
            ),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function myStock(Request $request)
    {
        $user = $request->user() ?? auth()->user();
        if (!$user) {
            return response()->json([], 200);
        }

        $movements = SampleMovement::with('product')
            ->where('user_id', $user->id)
            ->get();

        // L'inventaire dépend des dotations du délégué, pas du catalogue commercial actif.
        $stock = $movements->groupBy('product_id')->map(function ($items, $productId) {
            $product = $items->first()->product;
            if (!$product) {
                return null;
            }

            // Types d'entrées : reception, attribution, retour
            $in = $items->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity');
            // Types de sorties : distribution, destruction
            $out = $items->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');

            $available = max(0, $in - $out);

            $alertThreshold = (int) $product->stock_alerte;

            // Découpage par lots disponibles
            $lots = $items->whereNotNull('lot_number')
                ->groupBy('lot_number')
                ->map(function ($lotItems, $lotNum) {
                    $lotIn = $lotItems->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity');
                    $lotOut = $lotItems->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');
                    return [
                        'lot_number'  => $lotNum,
                        'expiry_date' => $lotItems->first()->expiry_date ? $lotItems->first()->expiry_date->format('Y-m-d') : null,
                        'available'   => max(0, $lotIn - $lotOut),
                    ];
                })
                ->filter(fn($l) => $l['available'] > 0)
                ->values();

            return [
                'product_id'           => $productId,
                'product_name'         => $product->nom ?? 'Produit inconnu',
                'product'              => [
                    'id'           => $productId,
                    'name'         => $product->nom ?? 'Produit inconnu',
                    'nom'          => $product->nom ?? 'Produit inconnu',
                    'code'         => $product->code ?? null,
                    'stock_alerte' => $alertThreshold,
                    'gamme'        => $product->gamme,
                    'image_url'    => $product->image_url,
                ],
                'quantity_available'   => $available,
                'quantity_received'    => $in,
                'quantity_distributed' => $items->where('movement_type', 'distribution')->sum('quantity'),
                'alert_threshold'      => $alertThreshold,
                'status'               => $available <= $alertThreshold ? 'Critique' : 'Normal',
                'lots'                 => $lots,
            ];
        })->filter()->values();

        return response()->json($stock);
    }

    #[OA\Post(
        path: '/api/v1/sample-movements',
        summary: 'Créer un mouvement de stock (Entrée / Sortie / Réassort / Distribution)',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id', 'movement_type', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id',    type: 'string',  format: 'uuid', example: 'd3b07384-d113-4f57-9d7a-8869152c9748'),
                    new OA\Property(property: 'user_id',       type: 'string',  format: 'uuid', nullable: true, description: 'Délégué bénéficiaire (défaut: utilisateur connecté)'),
                    new OA\Property(property: 'movement_type', type: 'string',  enum: ['reception', 'attribution', 'distribution', 'retour', 'destruction', 'inventaire'], example: 'reception'),
                    new OA\Property(property: 'quantity',      type: 'integer', minimum: 1, example: 50),
                    new OA\Property(property: 'lot_number',    type: 'string',  nullable: true, example: 'LOT-2026-X9'),
                    new OA\Property(property: 'expiry_date',   type: 'string',  format: 'date', nullable: true, example: '2027-12-31'),
                    new OA\Property(property: 'activity_id',   type: 'string',  format: 'uuid', nullable: true, description: 'Activité associée en cas de distribution terrain'),
                    new OA\Property(property: 'contact_id',    type: 'string',  format: 'uuid', nullable: true, description: 'Médecin destinataire (requis si distribution)'),
                    new OA\Property(property: 'signature_data',type: 'string',  nullable: true, description: 'Image base64 de la signature'),
                    new OA\Property(property: 'signature_url', type: 'string',  nullable: true),
                    new OA\Property(property: 'qr_code',       type: 'string',  nullable: true),
                    new OA\Property(property: 'remarques',     type: 'string',  nullable: true, example: 'Réassort dotation mensuelle'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Mouvement de stock créé avec succès'),
            new OA\Response(response: 422, description: 'Validation échouée'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function store(Request $request)
    {
        // Normalisation des alias pour compatibilité front
        $type = $request->input('movement_type') ?? $request->input('type_mouvement');
        $typeMap = [
            'entree'        => 'reception',
            'reception'     => 'reception',
            'attribution'   => 'attribution',
            'sortie'        => 'distribution',
            'sortie_visite' => 'distribution',
            'distribution'  => 'distribution',
            'retour'        => 'retour',
            'destruction'   => 'destruction',
            'perte'         => 'destruction',
            'inventaire'    => 'inventaire',
        ];
        $normalizedType = $typeMap[$type] ?? $type;
        $request->merge(['movement_type' => $normalizedType]);

        $quantity = $request->input('quantity') ?? $request->input('quantite');

        $validated = $request->validate([
            'product_id'    => 'required|uuid|exists:products,id',
            'user_id'       => 'nullable|uuid|exists:users,id',
            'contact_id'    => 'nullable|uuid|exists:contacts,id',
            'movement_type' => 'required|string|in:reception,attribution,distribution,retour,destruction,inventaire,entree,sortie,sortie_visite,perte',
            'quantity'      => 'required_without:quantite|nullable|integer|min:1',
            'quantite'      => 'required_without:quantity|nullable|integer|min:1',
            'lot_number'    => 'nullable|string|max:100',
            'numero_lot'    => 'nullable|string|max:100',
            'expiry_date'   => 'nullable|date',
            'activity_id'   => 'nullable|uuid|exists:activities,id',
            'signature_url' => 'nullable|string|max:255',
            'signature_data'=> 'nullable|string',
            'qr_code'       => 'nullable|string|max:255',
            'remarques'     => 'nullable|string|max:1000',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $userId = $validated['user_id'] ?? $request->user()->id;
        abort_unless($request->user()->canEditRecordOf((string) $userId), 403, 'Ce délégué ne fait pas partie de votre périmètre.');
        User::withoutGlobalScope(\App\Models\Scopes\DynamicPerimeterScope::class)->findOrFail($userId);
        $quantity = (int) $quantity;
        $lotNumber = $validated['lot_number'] ?? $request->input('numero_lot');
        $expiryDate = $validated['expiry_date'] ?? null;
        $remarques = $validated['remarques'] ?? $request->input('notes');

        // Validation stricte en cas de distribution
        if ($normalizedType === 'distribution') {
            if (empty($validated['contact_id'])) {
                return response()->json(['message' => 'Le contact (médecin) est obligatoire pour une distribution.'], 422);
            }
            if (empty($lotNumber)) {
                return response()->json(['message' => 'Le numéro de lot est obligatoire pour une distribution.'], 422);
            }
        }

        // Sauvegarde de la signature si fournie en base64
        $signatureUrl = $validated['signature_url'] ?? null;
        if (!empty($validated['signature_data'])) {
            $base64Image = $validated['signature_data'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
                $type = strtolower($type[1]); // png, jpg, etc.
                if (in_array($type, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $base64Image = base64_decode(str_replace(' ', '+', $base64Image));
                    $fileName = 'signatures/' . Str::uuid() . '.' . $type;
                    Storage::disk('public')->put($fileName, $base64Image);
                    $signatureUrl = Storage::url($fileName);
                }
            } else {
                // Si pas de préfixe data:image
                $base64Image = base64_decode(str_replace(' ', '+', $base64Image));
                $fileName = 'signatures/' . Str::uuid() . '.png';
                Storage::disk('public')->put($fileName, $base64Image);
                $signatureUrl = Storage::url($fileName);
            }
        }

        return DB::transaction(function () use ($validated, $userId, $normalizedType, $quantity, $lotNumber, $expiryDate, $remarques, $signatureUrl) {
            $product = Product::whereKey($validated['product_id'])->lockForUpdate()->firstOrFail();

            // Calcul du stock actuel pour cet utilisateur et ce produit
            $previousIn = SampleMovement::where('user_id', $userId)
                ->where('product_id', $product->id)
                ->whereIn('movement_type', ['reception', 'attribution', 'retour'])
                ->sum('quantity');

            $previousOut = SampleMovement::where('user_id', $userId)
                ->where('product_id', $product->id)
                ->whereIn('movement_type', ['distribution', 'destruction'])
                ->sum('quantity');

            $currentStock = max(0, $previousIn - $previousOut);

            // Nouveau stock calculé
            $isPositive = in_array($normalizedType, ['reception', 'attribution', 'retour']);
            
            if (!$isPositive && $currentStock < $quantity) {
                // Return explicitly the message required by Prompt 5
                abort(response()->json(['message' => "Stock insuffisant. Solde disponible : {$currentStock} boîtes"], 422));
            }
            
            if (!$isPositive && $lotNumber !== null) {
                $lotMovements = SampleMovement::where('user_id', $userId)
                    ->where('product_id', $product->id)->where('lot_number', $lotNumber)->get();
                $lotBalance = $lotMovements->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity')
                    - $lotMovements->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');
                abort_if($quantity > $lotBalance, 422, 'Stock insuffisant pour le lot sélectionné.');
            }

            $newStock = $isPositive ? ($currentStock + $quantity) : max(0, $currentStock - $quantity);

            $movement = SampleMovement::create([
                'product_id'    => $product->id,
                'user_id'       => $userId,
                'contact_id'    => $validated['contact_id'] ?? null,
                'activity_id'   => $validated['activity_id'] ?? null,
                'movement_type' => $normalizedType,
                'quantity'      => $quantity,
                'lot_number'    => $lotNumber,
                'expiry_date'   => $expiryDate,
                'signature_url' => $signatureUrl,
                'qr_code'       => $validated['qr_code'] ?? null,
                'stock_apres'   => $newStock,
                'remarques'     => $remarques,
            ]);

            // Le stock central est indépendant des mouvements du stock déjà confié.

            if (!$isPositive && $newStock <= ($product->stock_alerte ?? 10)) {
                $userObj = User::find($userId);
                if ($userObj && $userObj->manager) {
                    app(NotificationService::class)->send(
                        $userObj->manager,
                        'Alerte Stock Critique',
                        "Le délégué {$userObj->name} a un stock critique ({$newStock}) pour le produit {$product->nom}.",
                        'low_stock',
                        ['product_id' => $product->id, 'user_id' => $userId]
                    );
                }
            }

            return response()->json(new SampleMovementResource($movement->load(['product', 'user', 'activity'])), 201);
        });
    }

    #[OA\Get(
        path: '/api/v1/sample-movements/{id}',
        summary: 'Détails d\'un mouvement de stock',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails du mouvement'),
            new OA\Response(response: 404, description: 'Mouvement introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function show(string $id)
    {
        $movement = SampleMovement::with(['product', 'user', 'activity'])->findOrFail($id);
        abort_unless(auth()->user()->canEditRecordOf($movement->user_id), 403);
        return new SampleMovementResource($movement);
    }

    #[OA\Put(
        path: '/api/v1/sample-movements/{id}',
        summary: 'Modifier un mouvement de stock',
        description: 'Permet de corriger la quantité, le numéro de lot, la date de péremption ou les remarques d\'un mouvement.',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'quantity',      type: 'integer', minimum: 1),
                    new OA\Property(property: 'movement_type', type: 'string',  enum: ['reception', 'attribution', 'distribution', 'retour', 'destruction', 'inventaire']),
                    new OA\Property(property: 'lot_number',    type: 'string',  nullable: true),
                    new OA\Property(property: 'expiry_date',   type: 'string',  format: 'date', nullable: true),
                    new OA\Property(property: 'signature_url', type: 'string',  nullable: true),
                    new OA\Property(property: 'qr_code',       type: 'string',  nullable: true),
                    new OA\Property(property: 'remarques',     type: 'string',  nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mouvement mis à jour avec succès'),
            new OA\Response(response: 404, description: 'Mouvement introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function update(Request $request, string $id)
    {
        $movement = SampleMovement::findOrFail($id);
        abort_unless(auth()->user()->canEditRecordOf($movement->user_id), 403);

        $validated = $request->validate([
            'quantity'      => 'sometimes|integer|min:1',
            'movement_type' => 'sometimes|string|in:reception,attribution,distribution,retour,destruction,inventaire',
            'lot_number'    => 'nullable|string|max:100',
            'expiry_date'   => 'nullable|date',
            'signature_url' => 'nullable|string|max:255',
            'qr_code'       => 'nullable|string|max:255',
            'remarques'     => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($movement, $validated) {
            Product::whereKey($movement->product_id)->lockForUpdate()->firstOrFail();
            $movement->update($validated);
            $balance = $this->assertStockBalances($movement->user_id, $movement->product_id);
            $movement->update(['stock_apres' => $balance]);

            return new SampleMovementResource($movement->load(['product', 'user', 'activity']));
        });
    }

    #[OA\Delete(
        path: '/api/v1/sample-movements/{id}',
        summary: 'Supprimer un mouvement de stock',
        description: 'Supprime un mouvement de stock erroné et réajuste les compteurs.',
        security: [['bearerAuth' => []]],
        tags: ['Stocks'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Mouvement supprimé avec succès'),
            new OA\Response(response: 404, description: 'Mouvement introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function destroy(string $id)
    {
        $movement = SampleMovement::findOrFail($id);
        abort_unless(auth()->user()->canEditRecordOf($movement->user_id), 403);

        DB::transaction(function () use ($movement) {
            Product::whereKey($movement->product_id)->lockForUpdate()->firstOrFail();
            $movement->delete();
            $this->assertStockBalances($movement->user_id, $movement->product_id);
        });

        return response()->json([
            'message' => 'Mouvement de stock supprimé avec succès.',
        ], 200);
    }
    /** Une correction ne peut pas consommer un lot inexistant ni retirer une réception déjà distribuée. */
    private function assertStockBalances(string $userId, string $productId): int
    {
        $movements = SampleMovement::where('user_id', $userId)->where('product_id', $productId)->get();
        $balance = 0;
        foreach ($movements->groupBy('lot_number') as $items) {
            $lotBalance = $items->whereIn('movement_type', ['reception', 'attribution', 'retour'])->sum('quantity')
                - $items->whereIn('movement_type', ['distribution', 'destruction'])->sum('quantity');
            if ($lotBalance < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quantity' => 'Cette correction rendrait le stock du lot négatif.',
                ]);
            }
            $balance += $lotBalance;
        }
        return $balance;
    }

}
