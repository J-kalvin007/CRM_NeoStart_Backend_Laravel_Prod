<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;

class LeaveController extends Controller {
    public function index() {
        return response()->json([
            "data" => [
                [
                    "id" => "1",
                    "employee_name" => "Alice Déléguée",
                    "type" => "Congé Annuel",
                    "start_date" => "2026-09-10",
                    "end_date" => "2026-09-24",
                    "status" => "En attente",
                    "reason" => "Vacances familiales"
                ]
            ]
        ]);
    }
}
