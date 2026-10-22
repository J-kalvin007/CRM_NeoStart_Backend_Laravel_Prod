<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PlanningOptions;
use Illuminate\Http\Request;

class PlanningOptionsController extends Controller
{
    public function __invoke(Request $request, PlanningOptions $options)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        return response()->json(['data' => $options->forUser((string) $request->user()->id, $data['date'] ?? now()->toDateString())]);
    }
}
