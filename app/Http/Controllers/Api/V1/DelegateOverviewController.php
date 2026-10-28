<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DelegateOverview;
use Illuminate\Http\Request;

class DelegateOverviewController extends Controller
{
    public function index(Request $request, DelegateOverview $overview)
    {
        return response()->json($overview->index($request));
    }

    public function show(Request $request, string $id, DelegateOverview $overview)
    {
        return response()->json($overview->show($request, $id));
    }
}
