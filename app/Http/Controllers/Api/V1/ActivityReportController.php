<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Resources\ActivityResource;

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
            'report_status'  => 'required|in:draft,submitted'
        ]);

        $data = $validated;
        if ($validated['report_status'] === 'submitted') {
            $data['statut'] = 'realisee';
        }
        if ($request->has('notes') && !empty($request->input('notes'))) {
            $data['notes'] = $request->input('notes');
        }
        if ($request->has('signature') && !empty($request->input('signature'))) {
            $data['signature'] = $request->input('signature');
        }
        if ($request->has('prochain_rdv') && !empty($request->input('prochain_rdv'))) {
            $data['prochain_rdv'] = $request->input('prochain_rdv');
        }
        if ($request->has('heure_fin') && !empty($request->input('heure_fin'))) {
            $data['heure_fin'] = $request->input('heure_fin');
        }
        if ($request->has('duree_minutes') && !empty($request->input('duree_minutes'))) {
            $data['duree_minutes'] = $request->input('duree_minutes');
        }
        if ($request->has('gps_fin_lat') && !empty($request->input('gps_fin_lat'))) {
            $data['gps_fin_lat'] = $request->input('gps_fin_lat');
        }
        if ($request->has('gps_fin_lng') && !empty($request->input('gps_fin_lng'))) {
            $data['gps_fin_lng'] = $request->input('gps_fin_lng');
        }

        $activity->update($data);

        if ($activity->statut === 'realisee') {
            $activity->lock();
        }

        if ($validated['report_status'] === 'submitted') {
            $user = $activity->delegue ?? $activity->user ?? auth()->user();
            $manager = $user?->manager ?? \App\Models\User::whereHas('roles', fn($q) => $q->whereIn('name', ['superviseur', 'supervisor', 'manager', 'Manager', 'Superviseur', 'dir-commercial']))->first();
            if ($manager && $user) {
                NotificationController::sendNotification(
                    $manager,
                    'Nouveau compte-rendu de visite soumis',
                    "Le délégué {$user->name} a finalisé et soumis son compte-rendu de visite.",
                    'report',
                    'manager',
                    ['activity_id' => (string) $activity->id, 'sender_id' => (string) $user->id]
                );
            }
        }

        return response()->json(new ActivityResource($activity->load(['delegue', 'contact', 'account', 'products'])));
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
        $status = $request->input('status') ?? ($request->input('action') === 'validate' || $request->input('action') === 'validated' ? 'validated' : ($request->input('action') === 'reject' || $request->input('action') === 'rejected' ? 'rejected' : null));
        $request->merge(['status' => $status]);

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
