<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrainingController extends Controller {
    public function index() {
        return response()->json([
            "data" => [
                [
                    "id" => "1",
                    "title" => "Argumentaire Clinique CardioPulse 2026",
                    "category" => "Obligatoire",
                    "duration" => "25 min",
                    "min_score" => "80%",
                    "status" => "Terminé",
                    "progress" => 1.0
                ],
                [
                    "id" => "2",
                    "title" => "Conformité & Éthique",
                    "category" => "Réglementaire",
                    "duration" => "45 min",
                    "min_score" => "100%",
                    "status" => "En cours",
                    "progress" => 0.5
                ]
            ]
        ]);
    }
}
