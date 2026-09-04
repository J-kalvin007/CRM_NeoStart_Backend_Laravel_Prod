<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controller pour la gestion des Champs Personnalisés (Custom Fields / Moteur EAV).
 * Permet aux administrateurs de configurer dynamiquement de nouveaux champs
 * pour n'importe quelle entité du CRM (Accounts, Contacts, Opportunities, etc.).
 */
class CustomFieldController extends Controller
{
    /**
     * Liste tous les champs personnalisés.
     * Peut être filtré par le type de modèle (ex: ?model_type=App\Models\Account).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = CustomField::query();

        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        $fields = $query->orderBy('order')->get();
        return response()->json($fields);
    }

    /**
     * Crée un nouveau champ personnalisé pour une entité donnée.
     * Exemple : Ajouter un champ "Numéro d'agrément" sur les Médecins.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'model_type' => 'required|string',
            'name' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'type' => 'required|in:text,textarea,number,date,boolean,select,multiselect',
            'options' => 'nullable|array',
            'is_required' => 'boolean',
            'order' => 'integer',
        ]);

        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $field = CustomField::create($data);

        return response()->json($field, 201);
    }

    /**
     * Affiche les détails d'un champ personnalisé spécifique.
     *
     * @param CustomField $customField
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(CustomField $customField)
    {
        return response()->json($customField);
    }

    /**
     * Met à jour les propriétés d'un champ personnalisé (ex: le rendre obligatoire).
     *
     * @param Request $request
     * @param CustomField $customField
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, CustomField $customField)
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:255',
            'options' => 'sometimes|nullable|array',
            'is_required' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'order' => 'sometimes|integer',
        ]);

        $customField->update($data);
        return response()->json($customField);
    }

    /**
     * Supprime un champ personnalisé.
     * Attention: Cela ne supprime pas automatiquement les valeurs associées dans custom_field_values (géré par SoftDeletes ou cascade).
     *
     * @param CustomField $customField
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(CustomField $customField)
    {
        $customField->delete();
        return response()->json(null, 204);
    }
}
