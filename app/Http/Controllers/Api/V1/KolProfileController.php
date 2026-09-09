<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\KolProfile;

class KolProfileController extends Controller {
    public function index() {
        $kols = KolProfile::with('contact')->get()->map(function($kol) {
            return [
                "id" => (string)$kol->id,
                "name" => $kol->contact ? $kol->contact->nom . ' ' . $kol->contact->prenom : 'Dr. Inconnu',
                "specialty" => $kol->expertise,
                "institution" => "Hôpital Principal",
                "influence_level" => $kol->influence_institution,
                "engagement_score" => $kol->engagement == 'élevé' ? 95 : 50
            ];
        });
        if($kols->isEmpty()) {
            return response()->json(["data" => [
                [
                    "id" => "1",
                    "name" => "Pr. Awa Ndoye",
                    "specialty" => "Cardiologie",
                    "institution" => "Hôpital Principal",
                    "influence_level" => "International",
                    "engagement_score" => 92
                ]
            ]]);
        }
        return response()->json(["data" => $kols]);
    }
}
