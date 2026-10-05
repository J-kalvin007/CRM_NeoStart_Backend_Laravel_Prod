<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SampleStock;
use Illuminate\Http\Request;

class SampleStockController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Return only the authenticated delegate's stock
        $stocks = SampleStock::with('product')
            ->where('user_id', $user->id)
            ->where('quantity', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($stocks);
    }
}
