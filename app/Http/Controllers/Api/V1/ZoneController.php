<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        return response()->json(Zone::with('sectors')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'territory_id' => 'required|exists:territories,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:zones',
        ]);
        return response()->json(Zone::create($validated), 201);
    }

    public function show(Zone $zone)
    {
        return response()->json($zone->load('sectors'));
    }

    public function update(Request $request, Zone $zone)
    {
        $zone->update($request->all());
        return response()->json($zone);
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();
        return response()->json(null, 204);
    }
}
