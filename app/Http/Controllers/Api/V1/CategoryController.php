<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(path: '/api/v1/categories', summary: 'Lister toutes les catégories/tags', security: [['bearerAuth' => []]], tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtrer par type (ex: specialite_medicale, segment_client)'),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des catégories')]
    )]
    public function index(Request $request)
    {
        $query = Category::query()
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('ordre')->orderBy('nom');
        return CategoryResource::collection($query->get());
    }

    #[OA\Post(path: '/api/v1/categories', summary: 'Créer une catégorie/tag', security: [['bearerAuth' => []]], tags: ['Categories'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['nom'],
            properties: [
                new OA\Property(property: 'nom', type: 'string', example: 'Cardiologie'),
                new OA\Property(property: 'type', type: 'string', example: 'specialite_medicale', description: 'Groupement libre défini par l\'Admin'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'couleur', type: 'string', example: '#EF4444'),
                new OA\Property(property: 'icone', type: 'string', nullable: true),
                new OA\Property(property: 'ordre', type: 'integer', default: 0),
            ]
        )),
        responses: [new OA\Response(response: 201, description: 'Catégorie créée avec succès')]
    )]
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['nom'] . '-' . uniqid());
        return new CategoryResource(Category::create($data));
    }

    #[OA\Get(path: '/api/v1/categories/{id}', summary: 'Détails d\'une catégorie', security: [['bearerAuth' => []]], tags: ['Categories'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 200, description: 'Détails de la catégorie'), new OA\Response(response: 404, description: 'Non trouvée')]
    )]
    public function show(string $id)
    {
        return new CategoryResource(Category::findOrFail($id));
    }

    #[OA\Put(path: '/api/v1/categories/{id}', summary: 'Mettre à jour une catégorie', security: [['bearerAuth' => []]], tags: ['Categories'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'nom', type: 'string'),
            new OA\Property(property: 'type', type: 'string'),
            new OA\Property(property: 'couleur', type: 'string'),
            new OA\Property(property: 'is_active', type: 'boolean'),
        ])),
        responses: [new OA\Response(response: 200, description: 'Catégorie mise à jour')]
    )]
    public function update(UpdateCategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());
        return new CategoryResource($category);
    }

    #[OA\Delete(path: '/api/v1/categories/{id}', summary: 'Supprimer une catégorie', security: [['bearerAuth' => []]], tags: ['Categories'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Supprimée avec succès')]
    )]
    public function destroy(string $id)
    {
        Category::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
