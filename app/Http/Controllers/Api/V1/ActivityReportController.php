<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Activity Reports', description: 'Gestion des comptes-rendus de visites')]
class ActivityReportController extends Controller
{
    #[OA\Put(
        path: '/api/v1/activities/{activity}/report',
        summary: 'Soumettre ou modifier un compte-rendu',
        security: [['bearerAuth' => []]],
        tags: ['Activity Reports'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'report_content', type: 'string'),
                    new OA\Property(property: 'report_status', type: 'string', enum: ['draft', 'submitted'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu mis à jour')
        ]
    )]
    public function update(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'report_content' => 'nullable|string',
            'report_status' => 'required|in:draft,submitted'
        ]);

        $activity->update($validated);

        if ($validated['report_status'] === 'submitted' && $activity->user && $activity->user->manager_id) {
            $manager = $activity->user->manager;
            app(NotificationService::class)->send(
                $manager,
                'Compte-rendu soumis',
                "Le délégué {$activity->user->name} a soumis le compte-rendu de la visite {$activity->id} pour validation.",
                'report_submitted',
                ['activity_id' => $activity->id]
            );
        }

        return response()->json($activity);
    }

    #[OA\Post(
        path: '/api/v1/activities/{activity}/report/validate',
        summary: 'Valider ou rejeter un compte-rendu (Manager)',
        security: [['bearerAuth' => []]],
        tags: ['Activity Reports'],
        parameters: [
            new OA\Parameter(name: 'activity', in: 'path', required: true, schema: new OA\Schema(type: 'string'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['validated', 'rejected']),
                    new OA\Property(property: 'rejection_reason', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compte-rendu traité')
        ]
    )]
    public function validateReport(Request $request, Activity $activity)
    {
        $validated = $request->validate([
            'status' => 'required|in:validated,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string'
        ]);

        $activity->update([
            'report_status' => $validated['status'],
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'validated_by_id' => Auth::id(),
            'validation_date' => Carbon::now()
        ]);

        // Notification au délégué
        $title = $validated['status'] === 'validated' ? 'Compte-rendu validé' : 'Compte-rendu rejeté';
        $message = "Votre compte-rendu a été {$validated['status']}.";
        if ($validated['status'] === 'rejected') {
            $message .= " Motif : " . $validated['rejection_reason'];
        }

        app(NotificationService::class)->send(
            $activity->user,
            $title,
            $message,
            'report_validation',
            ['activity_id' => $activity->id]
        );

        return response()->json($activity);
    }
}
