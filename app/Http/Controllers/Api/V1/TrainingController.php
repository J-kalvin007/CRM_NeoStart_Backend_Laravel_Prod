<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Training;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TrainingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/trainings',
        summary: 'Lister les formations e-learning avec filtrage et pagination',
        security: [['bearerAuth' => []]],
        tags: ['Trainings'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des formations')
        ]
    )]
    public function index(Request $request)
    {
        $query = Training::query();

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            if ($category !== 'Toutes' && $category !== 'Tous') {
                $query->where('category', $category);
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'Tous') {
                $query->where('status', $status);
            }
        }

        $perPage = (int) $request->input('per_page', 20);

        if ($request->has('page') || $request->has('per_page') || $request->has('search') || $request->has('category')) {
            $paginated = $query->paginate($perPage);
            return response()->json([
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
            ]);
        }

        $trainings = $query->get();
        return response()->json($trainings);
    }
}
