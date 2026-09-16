<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Segmentation;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Segmentations", description: "API Endpoints for Segmentations")]
class SegmentationController extends Controller
{
    #[OA\Get(
        path: "/api/v1/segmentations",
        summary: "Get list of Segmentations",
        tags: ["Segmentations"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function index()
    {
        return response()->json(Segmentation::all());
    }

    #[OA\Post(
        path: "/api/v1/segmentations",
        summary: "Create a new Segmentation",
        tags: ["Segmentations"],
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
            'code' => 'required|string|max:100|unique:segmentations',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = Segmentation::create($validated);
        return response()->json($item, 201);
    }

    #[OA\Get(
        path: "/api/v1/segmentations/{id}",
        summary: "Get a specific Segmentation",
        tags: ["Segmentations"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function show($id)
    {
        $item = Segmentation::findOrFail($id);
        return response()->json($item);
    }

    #[OA\Put(
        path: "/api/v1/segmentations/{id}",
        summary: "Update an existing Segmentation",
        tags: ["Segmentations"],
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
        $item = Segmentation::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:segmentations,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/v1/segmentations/{id}",
        summary: "Delete a Segmentation",
        tags: ["Segmentations"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted successfully")
        ]
    )]
    public function destroy($id)
    {
        $item = Segmentation::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
