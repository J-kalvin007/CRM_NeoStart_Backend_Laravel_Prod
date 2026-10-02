<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Notifications\MissingVisitReportNotification;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

class SupervisorReportController extends Controller
{
    #[OA\Get(
        path: '/api/v1/supervisor/missing-reports',
        summary: 'Lister les visites sans compte rendu',
        tags: ['Supervisor Reports'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Liste des activités passées sans compte rendu')
        ]
    )]
    public function index(Request $request)
    {
        // On récupère les visites passées qui n'ont pas de rapport,
        // uniquement pour les délégués supervisés par l'utilisateur connecté.
        $subordinateIds = $request->user()->subordinates()->pluck('id');
        
        $activities = Activity::with(['delegue', 'contact', 'account'])
            ->whereIn('user_id', $subordinateIds)
            ->where('date_activite', '<', Carbon::today())
            ->whereNotIn('statut', ['annulee', 'manquee'])
            ->whereNotIn('report_status', ['submitted', 'validated', 'modified'])
            ->orderBy('date_activite', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    #[OA\Post(
        path: '/api/v1/supervisor/remind-missing-reports',
        summary: 'Relancer manuellement les comptes rendus manquants',
        tags: ['Supervisor Reports'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['activity_ids'],
                properties: [
                    new OA\Property(
                        property: 'activity_ids',
                        description: 'Liste des identifiants (UUID) des activités à relancer',
                        type: 'array',
                        items: new OA\Items(type: 'string', format: 'uuid')
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Relances envoyées avec succès')
        ]
    )]
    public function remind(Request $request)
    {
        $request->validate([
            'activity_ids' => 'required|array',
            'activity_ids.*' => 'exists:activities,id'
        ]);

        $activities = Activity::whereIn('id', $request->activity_ids)->with('delegue')->get();
        $count = 0;

        foreach ($activities as $activity) {
            if ($activity->delegue && !in_array($activity->report_status, ['submitted', 'validated', 'modified'])) {
                $activity->delegue->notify(new MissingVisitReportNotification($activity));
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} relances ont été envoyées."
        ]);
    }
}
