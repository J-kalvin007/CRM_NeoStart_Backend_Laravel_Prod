<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KolProfile;
use Illuminate\Http\Request;

class KolProfileController extends Controller {
    public function index(Request $request) {
        $query = KolProfile::with('contact');

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('expertise', 'like', "%{$search}%")
                  ->orWhere('influence_institution', 'like', "%{$search}%")
                  ->orWhereHas('contact', function($cq) use ($search) {
                      $cq->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenom', 'like', "%{$search}%")
                         ->orWhere('specialite', 'like', "%{$search}%");
                  });
            });
        }

        if ($specialty = $request->input('specialty')) {
            if ($specialty !== 'Tous') {
                $query->where(function($q) use ($specialty) {
                    $q->where('expertise', 'like', "%{$specialty}%")
                      ->orWhereHas('contact', function($cq) use ($specialty) {
                          $cq->where('specialite', 'like', "%{$specialty}%");
                      });
                });
            }
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($request->has('page') || $request->has('per_page')) {
            $paginated = $query->paginate($perPage);
            $items = collect($paginated->items())->map(function($kol) {
                return [
                    "id" => (string)$kol->id,
                    "name" => $kol->contact ? $kol->contact->nom . ' ' . $kol->contact->prenom : 'Dr. Inconnu',
                    "specialty" => $kol->expertise ?? ($kol->contact ? $kol->contact->specialite : 'Non renseignée'),
                    "institution" => $kol->influence_institution ?? "Hôpital Principal",
                    "influence_level" => $kol->influence_institution ?? "Non spécifié",
                    "engagement_score" => $kol->engagement == 'élevé' ? 95 : 50
                ];
            });

            return response()->json([
                'data' => $items,
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ]);
        }

        $kols = $query->get()->map(function($kol) {
            return [
                "id" => (string)$kol->id,
                "name" => $kol->contact ? $kol->contact->nom . ' ' . $kol->contact->prenom : 'Dr. Inconnu',
                "specialty" => $kol->expertise ?? ($kol->contact ? $kol->contact->specialite : 'Non renseignée'),
                "institution" => $kol->influence_institution ?? "Hôpital Principal",
                "influence_level" => $kol->influence_institution ?? "Non spécifié",
                "engagement_score" => $kol->engagement == 'élevé' ? 95 : 50
            ];
        });

        return response()->json(["data" => $kols]);
    }
}
