<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Types d'Activité", description: "API Endpoints for Types d'Activité")]
class ActivityTypeController extends Controller
{
    #[OA\Get(
        path: "/api/v1/activity-types",
        summary: "Get list of Types d'Activité",
        tags: ["Types d'Activité"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function index()
    {
        return response()->json(ActivityType::all());
    }

    #[OA\Post(
        path: "/api/v1/activity-types",
        summary: "Create a new ActivityType",
        tags: ["Types d'Activité"],
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
            'code' => 'required|string|max:100|unique:activity_types',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = ActivityType::create($validated);
        return response()->json($item, 201);
    }

    #[OA\Get(
        path: "/api/v1/activity-types/{id}",
        summary: "Get a specific ActivityType",
        tags: ["Types d'Activité"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function show($id)
    {
        $item = ActivityType::findOrFail($id);
        return response()->json($item);
    }

    #[OA\Put(
        path: "/api/v1/activity-types/{id}",
        summary: "Update an existing ActivityType",
        tags: ["Types d'Activité"],
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
        $item = ActivityType::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:activity_types,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    #[OA\Delete(
        path: "/api/v1/activity-types/{id}",
        summary: "Delete a ActivityType",
        tags: ["Types d'Activité"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(response: 204, description: "Deleted successfully")
        ]
    )]
    public function destroy($id)
    {
        $item = ActivityType::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
