<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DuoActivityType;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Types d'Activité Duo", description: "API Endpoints for Types d'Activité Duo")]
class DuoActivityTypeController extends Controller
{
    public function index()
    {
        return response()->json(DuoActivityType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:duo_activity_types',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item = DuoActivityType::create($validated);
        return response()->json($item, 201);
    }

    public function show($id)
    {
        $item = DuoActivityType::findOrFail($id);
        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        $item = DuoActivityType::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100|unique:duo_activity_types,code,' . $item->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        $item->update($validated);
        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = DuoActivityType::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }
}
