<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ObjectifType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ObjectifTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $types = ObjectifType::orderBy('name')->get();
        return response()->json($types);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'unit' => 'required|string|max:191',
            'is_active' => 'boolean',
        ]);

        $type = ObjectifType::create($validated);
        return response()->json($type, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ObjectifType $objectifType)
    {
        return response()->json($objectifType);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ObjectifType $objectifType)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'unit' => 'sometimes|required|string|max:191',
            'is_active' => 'boolean',
        ]);

        $objectifType->update($validated);
        return response()->json($objectifType);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ObjectifType $objectifType)
    {
        $objectifType->delete();
        return response()->json(null, 204);
    }
}
