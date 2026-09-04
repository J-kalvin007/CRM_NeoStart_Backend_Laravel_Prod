<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class ManagerDashboardController extends Controller {
    public function teamPerformance(Request $request) {
        return response()->json([
            "data" => [
                [
                    "id" => "1",
                    "name" => "Alice Déléguée",
                    "sector" => "Secteur 1",
                    "visit_count" => 58,
                    "objective_rate" => 96,
                    "trend_percentage" => 5,
                    "is_up" => true,
                    "rank" => 1
                ]
            ]
        ]);
    }
    public function performance() { return response()->json(["data" => []]); }
}
