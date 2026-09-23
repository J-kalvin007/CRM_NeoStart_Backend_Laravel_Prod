<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Expenses', description: 'Gestion des notes de frais et dépenses terrain')]
class ExpenseController extends Controller
{
    #[OA\Get(
        path: '/api/v1/expenses',
        summary: 'Lister les notes de frais',
        description: 'Retourne la liste paginée des notes de frais. Un délégué voit ses propres notes, tandis qu\'un manager ou administrateur peut filtrer sur les membres de son équipe.',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'submitted', 'approved', 'rejected', 'paid']), description: 'Statut du workflow'),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['travel', 'meal', 'accommodation', 'material', 'other']), description: 'Catégorie de dépense'),
            new OA\Parameter(name: 'user_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par utilisateur (Managers/Admins)'),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de début (YYYY-MM-DD)'),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'), description: 'Date de fin (YYYY-MM-DD)'),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des notes de frais paginée'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Expense::with(['user', 'approver'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('expense_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('expense_date', '<=', $request->date_to));

        if ($request->filled('user_id') && $user->hasAnyRole(['super-admin', 'admin-pays', 'dir-general', 'dir-commercial', 'manager'])) {
            $query->where('user_id', $request->user_id);
        } elseif (!$user->hasAnyRole(['super-admin', 'admin-pays', 'dir-general', 'dir-commercial', 'manager'])) {
            $query->where('user_id', $user->id);
        }

        $expenses = $query->latest('expense_date')->paginate($request->integer('per_page', 15));
        return ExpenseResource::collection($expenses);
    }

    #[OA\Post(
        path: '/api/v1/expenses',
        summary: 'Créer une note de frais',
        description: 'Enregistre une nouvelle note de frais à l\'état brouillon (draft). La dépense peut être rattachée à une Visite médicale (Activity) ou à un Congrès (Event).',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'type', 'amount', 'expense_date'],
                properties: [
                    new OA\Property(property: 'title',             type: 'string', example: 'Déjeuner de travail Dr. Lawson'),
                    new OA\Property(property: 'type',              type: 'string', enum: ['travel', 'meal', 'accommodation', 'material', 'other'], example: 'meal'),
                    new OA\Property(property: 'amount',            type: 'number', format: 'float', example: 15000),
                    new OA\Property(property: 'currency',          type: 'string', example: 'XOF', default: 'XOF'),
                    new OA\Property(property: 'expense_date',      type: 'string', format: 'date', example: '2026-09-14'),
                    new OA\Property(property: 'expensable_id',     type: 'string', format: 'uuid', nullable: true, description: 'ID de la visite ou de l\'événement associé'),
                    new OA\Property(property: 'expensable_type',   type: 'string', nullable: true, example: 'App\\Models\\Activity'),
                    new OA\Property(property: 'receipt_file_path', type: 'string', nullable: true, description: 'Chemin ou URL du justificatif / photo du reçu'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Note de frais créée au statut draft'),
            new OA\Response(response: 422, description: 'Validation échouée'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();

        $data['user_id'] = $user->id;
        $data['subsidiary_id'] = $user->subsidiary_id;
        $data['status'] = 'draft';

        $expense = Expense::create($data);

        return response()->json(new ExpenseResource($expense->load(['user', 'approver'])), 201);
    }

    #[OA\Get(
        path: '/api/v1/expenses/{id}',
        summary: 'Afficher une note de frais',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de la note de frais'),
            new OA\Response(response: 403, description: 'Accès non autorisé'),
            new OA\Response(response: 404, description: 'Note de frais introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function show(Expense $expense)
    {
        $user = Auth::user();

        if ($expense->user_id !== $user->id && !$user->hasAnyRole(['super-admin', 'admin-pays', 'dir-general', 'dir-commercial', 'manager'])) {
            return response()->json(['message' => 'Accès non autorisé à cette note de frais.'], 403);
        }

        $expense->load(['user', 'approver']);
        return response()->json(new ExpenseResource($expense));
    }

    #[OA\Put(
        path: '/api/v1/expenses/{id}',
        summary: 'Modifier une note de frais',
        description: 'Modifie une note de frais. Cette action n\'est possible que tant que la note est à l\'état draft (brouillon) ou rejected (rejetée).',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title',             type: 'string'),
                    new OA\Property(property: 'type',              type: 'string', enum: ['travel', 'meal', 'accommodation', 'material', 'other']),
                    new OA\Property(property: 'amount',            type: 'number', format: 'float'),
                    new OA\Property(property: 'expense_date',      type: 'string', format: 'date'),
                    new OA\Property(property: 'receipt_file_path', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Note de frais modifiée avec succès'),
            new OA\Response(response: 403, description: 'Modification interdite (note déjà soumise ou validée)'),
            new OA\Response(response: 404, description: 'Note de frais introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        if (!in_array($expense->status, ['draft', 'rejected'])) {
            return response()->json(['message' => 'Impossible de modifier une note de frais déjà soumise ou validée.'], 403);
        }

        $expense->update($request->validated());
        return response()->json(new ExpenseResource($expense->load(['user', 'approver'])));
    }

    #[OA\Post(
        path: '/api/v1/expenses/{id}/submit',
        summary: 'Soumettre une note de frais pour approbation',
        description: 'Fait passer la note de frais du statut draft ou rejected à submitted. La note est alors verrouillée et envoyée au manager pour validation.',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Note de frais soumise au manager avec succès'),
            new OA\Response(response: 400, description: 'Statut invalide pour la soumission'),
            new OA\Response(response: 404, description: 'Note de frais introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function submit(Expense $expense)
    {
        if ($expense->status !== 'draft' && $expense->status !== 'rejected') {
            return response()->json(['message' => 'Seule une note en brouillon ou rejetée peut être soumise.'], 400);
        }

        $expense->update(['status' => 'submitted']);

        // Notification manager
        $user = Auth::user();
        $manager = $user?->manager ?? \App\Models\User::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)->where('id', '!=', $user->id)->first();
        if ($manager && $user) {
            NotificationController::sendNotification(
                $manager,
                'Nouvelle note de frais soumise',
                "Le délégué {$user->name} a soumis une note de frais de {$expense->amount} {$expense->currency}.",
                'expense',
                'superviseur',
                ['expense_id' => $expense->id]
            );
        }

        return response()->json(new ExpenseResource($expense->load(['user', 'approver'])));
    }

    #[OA\Delete(
        path: '/api/v1/expenses/{id}',
        summary: 'Supprimer une note de frais',
        description: 'Supprime définitivement une note de frais. Possible uniquement si elle est en draft (brouillon) ou rejected (rejetée).',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Note de frais supprimée avec succès'),
            new OA\Response(response: 403, description: 'Suppression interdite (note déjà soumise ou validée)'),
            new OA\Response(response: 404, description: 'Note de frais introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function destroy(Expense $expense)
    {
        if (!in_array($expense->status, ['draft', 'rejected'])) {
            return response()->json(['message' => 'Impossible de supprimer une note de frais déjà soumise ou validée.'], 403);
        }

        $expense->delete();
        return response()->json(['message' => 'Note de frais supprimée avec succès.'], 200);
    }

    #[OA\Patch(
        path: '/api/v1/expenses/{id}/status',
        summary: 'Valider ou rejeter une note de frais (Manager / Direction)',
        description: 'Permet au manager de valider (approved) ou de refuser (rejected) une note de frais soumise. En cas de rejet, un motif explicatif est requis.',
        security: [['bearerAuth' => []]],
        tags: ['Expenses'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status',           type: 'string', enum: ['approved', 'rejected', 'pending'], example: 'approved'),
                    new OA\Property(property: 'rejection_reason', type: 'string', nullable: true, example: 'Reçu illisible ou justificatif manquant', description: 'Requis si status=rejected'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut de la note de frais mis à jour'),
            new OA\Response(response: 422, description: 'Données de validation invalides'),
            new OA\Response(response: 404, description: 'Note de frais introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function updateStatus(Request $request, Expense $expense)
    {
        $v = $request->validate([
            'status'           => 'required|in:approved,rejected,pending',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $expense->update([
            'status'           => $v['status'],
            'approved_by'      => $v['status'] === 'approved' ? Auth::id() : $expense->approved_by,
            'rejection_reason' => $v['rejection_reason'] ?? null,
            'approved_at'      => $v['status'] === 'approved' ? now() : null,
        ]);

        // Notification délégué
        if ($expense->user) {
            $statusLabel = $v['status'] === 'approved' ? 'approuvée' : ($v['status'] === 'rejected' ? 'refusée' : 'mise en attente');
            $msg = "Votre note de frais '{$expense->title}' a été {$statusLabel}.";
            if ($v['status'] === 'rejected' && !empty($v['rejection_reason'])) {
                $msg .= " Motif : " . $v['rejection_reason'];
            }
            NotificationController::sendNotification(
                $expense->user,
                'Note de frais ' . ucfirst($statusLabel),
                $msg,
                'expense',
                'delegue',
                ['expense_id' => $expense->id]
            );
        }

        return response()->json(new ExpenseResource($expense->load(['user', 'approver'])));
    }
}
