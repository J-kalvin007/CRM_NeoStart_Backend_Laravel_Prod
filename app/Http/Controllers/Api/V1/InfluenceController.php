<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Influence;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Influences", description: "API Endpoints for Influences")]
class InfluenceController extends Controller
{
    #[OA\Get(
        path: "/api/v1/influences",
        summary: "Get list of Influences",
        tags: ["Influences"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function index()
    {
        return response()->json(Influence::all());
    }

    #[OA\Post(
        path: "/api/v1/influences",
        summary: "Create a new Influence",
        tags: ["Influences"],
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
            'code' => 'required|string|max:100|unique:influences',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = Influence::create($validated);
        return response()->json($item, 201);
    }

    #[OA\Get(
        path: "/api/v1/influences/{id}",
        summary: "Get a specific Influence",
        tags: ["Influences"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function show($id)
    {
        $item = Influence::findOrFail($id);
        return response()->json($item);
    }

    #[OA\Put(
        path: "/api/v1/influences/{id}",
        summary: "Update an existing Influence",
        tags: ["Influences"],
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
        $item = Influence::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:influences,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/v1/influences/{id}",
        summary: "Delete a Influence",
        tags: ["Influences"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted successfully")
        ]
    )]
    public function destroy($id)
    {
        $item = Influence::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
