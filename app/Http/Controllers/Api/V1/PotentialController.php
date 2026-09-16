<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Potential;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Potentiels", description: "API Endpoints for Potentiels")]
class PotentialController extends Controller
{
    #[OA\Get(
        path: "/api/v1/potentials",
        summary: "Get list of Potentiels",
        tags: ["Potentiels"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function index()
    {
        return response()->json(Potential::all());
    }

    #[OA\Post(
        path: "/api/v1/potentials",
        summary: "Create a new Potential",
        tags: ["Potentiels"],
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
            'code' => 'required|string|max:100|unique:potentials',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = Potential::create($validated);
        return response()->json($item, 201);
    }

    #[OA\Get(
        path: "/api/v1/potentials/{id}",
        summary: "Get a specific Potential",
        tags: ["Potentiels"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function show($id)
    {
        $item = Potential::findOrFail($id);
        return response()->json($item);
    }

    #[OA\Put(
        path: "/api/v1/potentials/{id}",
        summary: "Update an existing Potential",
        tags: ["Potentiels"],
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
        $item = Potential::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:potentials,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/v1/potentials/{id}",
        summary: "Delete a Potential",
        tags: ["Potentiels"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted successfully")
        ]
    )]
    public function destroy($id)
    {
        $item = Potential::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
