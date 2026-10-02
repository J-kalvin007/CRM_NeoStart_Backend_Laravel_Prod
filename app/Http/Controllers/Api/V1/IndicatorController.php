<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Indicator;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class IndicatorController extends Controller
{
    #[OA\Get(path: '/api/v1/indicators', summary: 'Lister les indicateurs de performance', security: [['bearerAuth' => []]], tags: ['Indicators'],
        responses: [new OA\Response(response: 200, description: 'Liste des indicateurs')]
    )]
    public function index(Request $request)
    {
        $indicators = Indicator::where('is_active', true)->get();
        return response()->json(['data' => $indicators]);
    }
}
