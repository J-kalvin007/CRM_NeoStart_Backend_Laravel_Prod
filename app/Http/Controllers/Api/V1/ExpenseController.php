<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;

/**
 * @OA\Tag(
 *     name="Expenses",
 *     description="Gestion des notes de frais"
 * )
 * @group Transactions Financières
 * 
 * Gestion des Notes de Frais (Expenses) pour les utilisateurs terrains (Délégués).
 * Intègre un workflow d'approbation et un système polymorphique (lier une dépense à n'importe quelle entité : Activité, Evénement, etc.).
 */
class ExpenseController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/expenses",
     *     summary="Lister les notes de frais",
     *     tags={"Expenses"},
     *     @OA\Response(
     *         response=200,
     *         description="Liste paginée des notes de frais",
     *     )
     * )
     * Lister les Notes de Frais
     * 
     * Retourne les notes de frais. Un utilisateur standard ne verra que ses propres notes.
     * Un manager ou un administrateur verra celles de son périmètre (à configurer via Policies).
     * 
     * @queryParam page int Le numéro de la page pour la pagination.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Expense::with(['user', 'approver']);

        $query->where('user_id', $user->id);

        $expenses = $query->latest()->paginate(15);
        return ExpenseResource::collection($expenses);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses",
     *     summary="Créer une note de frais",
     *     tags={"Expenses"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","type","amount","expense_date"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="amount", type="number"),
     *             @OA\Property(property="currency", type="string"),
     *             @OA\Property(property="expense_date", type="string", format="date")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Note de frais créée")
     * )
     */
    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();
        
        $data['user_id'] = $user->id;
        $data['subsidiary_id'] = $user->subsidiary_id;
        $data['status'] = 'draft';

        $expense = Expense::create($data);

        return response()->json(new ExpenseResource($expense), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/expenses/{id}",
     *     summary="Afficher une note de frais",
     *     tags={"Expenses"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Détails de la note de frais")
     * )
     */
    public function show(Expense $expense)
    {
        if ($expense->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $expense->load(['user', 'approver']);
        return response()->json(new ExpenseResource($expense));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/expenses/{id}",
     *     summary="Modifier une note de frais",
     *     tags={"Expenses"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Note de frais modifiée")
     * )
     */
    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        if (!in_array($expense->status, ['draft', 'rejected'])) {
            return response()->json(['message' => 'Cannot edit a submitted/approved expense.'], 403);
        }

        $expense->update($request->validated());
        return response()->json(new ExpenseResource($expense));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/expenses/{id}/submit",
     *     summary="Soumettre une note de frais",
     *     tags={"Expenses"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Note de frais soumise")
     * )
     */
    public function submit(Expense $expense)
    {
        if ($expense->status !== 'draft' && $expense->status !== 'rejected') {
            return response()->json(['message' => 'Invalid status for submission.'], 400);
        }

        $expense->update(['status' => 'submitted']);
        return response()->json(new ExpenseResource($expense));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/expenses/{id}",
     *     summary="Supprimer une note de frais",
     *     tags={"Expenses"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Note de frais supprimée")
     * )
     */
    public function destroy(Expense $expense)
    {
        if (!in_array($expense->status, ['draft', 'rejected'])) {
            return response()->json(['message' => 'Cannot delete a submitted/approved expense.'], 403);
        }

        $expense->delete();
        return response()->json(null, 204);
    }
}
