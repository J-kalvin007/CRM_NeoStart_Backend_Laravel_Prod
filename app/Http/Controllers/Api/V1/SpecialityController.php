<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Speciality;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Spécialités", description: "API Endpoints for Spécialités")]
class SpecialityController extends Controller
{
    #[OA\Get(
        path: "/api/v1/specialities",
        summary: "Get list of Spécialités",
        tags: ["Spécialités"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function index()
    {
        return response()->json(
            Speciality::where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    #[OA\Post(
        path: "/api/v1/specialities",
        summary: "Create a new Speciality",
        tags: ["Spécialités"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "code", type: "string"),
                    new OA\Property(property: "description", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Created successfully")
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:specialities',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = Speciality::create($validated);
        return response()->json($item, 201);
    }

    #[OA\Get(
        path: "/api/v1/specialities/{id}",
        summary: "Get a specific Speciality",
        tags: ["Spécialités"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function show($id)
    {
        $item = Speciality::findOrFail($id);
        return response()->json($item);
    }

    #[OA\Put(
        path: "/api/v1/specialities/{id}",
        summary: "Update an existing Speciality",
        tags: ["Spécialités"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "code", type: "string"),
                    new OA\Property(property: "description", type: "string"),
                    new OA\Property(property: "is_active", type: "boolean")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Updated successfully")
        ]
    )]
    public function update(Request $request, $id)
    {
        $item = Speciality::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:specialities,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/v1/specialities/{id}",
        summary: "Delete a Speciality",
        tags: ["Spécialités"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted successfully")
        ]
    )]
    public function destroy($id)
    {
        $item = Speciality::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
