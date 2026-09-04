<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    public function index()
    {
        return response()->json(Sector::with('teams')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|string',
            'code' => 'required|string|unique:sectors',
        ]);
        return response()->json(Sector::create($validated), 201);
    }

    public function show(Sector $sector)
    {
        return response()->json($sector->load('teams'));
    }

    public function update(Request $request, Sector $sector)
    {
        $sector->update($request->all());
        return response()->json($sector);
    }

    public function destroy(Sector $sector)
    {
        $sector->delete();
        return response()->json(null, 204);
    }
}
