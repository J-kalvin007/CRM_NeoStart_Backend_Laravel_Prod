<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PharmacovigilanceReport;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;

class PharmacovigilanceReportController extends Controller
{
    #[OA\Get(
        path: '/api/v1/pharmacovigilance-reports',
        summary: 'Lister les déclarations de pharmacovigilance',
        security: [['bearerAuth' => []]],
        tags: ['Pharmacovigilance'],
        responses: [
            new OA\Response(response: 200, description: 'Liste récupérée avec succès'),
        ]
    )]
    public function index()
    {
        return response()->json(PharmacovigilanceReport::paginate(25));
    }

    #[OA\Post(
        path: '/api/v1/pharmacovigilance-reports',
        summary: 'Soumettre une déclaration de pharmacovigilance',
        security: [['bearerAuth' => []]],
        tags: ['Pharmacovigilance'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['contact_id', 'product_id', 'patient_initials', 'adverse_event_description', 'report_date'],
                properties: [
                    new OA\Property(property: 'contact_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'patient_initials', type: 'string', example: 'J.D.'),
                    new OA\Property(property: 'patient_age', type: 'integer', example: 45),
                    new OA\Property(property: 'patient_gender', type: 'string', enum: ['M', 'F', 'Autre']),
                    new OA\Property(property: 'adverse_event_description', type: 'string'),
                    new OA\Property(property: 'severity', type: 'string', enum: ['non_grave', 'moderee', 'grave', 'deces']),
                    new OA\Property(property: 'report_date', type: 'string', format: 'date'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Déclaration créée avec succès'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'product_id' => 'required|exists:products,id',
            'patient_initials' => 'required|string',
            'patient_age' => 'nullable|integer',
            'patient_gender' => 'nullable|in:M,F,Autre',
            'adverse_event_description' => 'required|string',
            'severity' => 'required|string|in:non_grave,moderee,grave,deces',
            'report_date' => 'required|date',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['status'] = 'soumis';

        $report = PharmacovigilanceReport::create($validated);

        // Déclencher une alerte immédiate vers le superviseur (et/ou direction médicale)
        $manager = User::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)->where('id', auth()->user()->team->manager_id ?? null)->first() 
            ?? User::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)->where('id', '!=', auth()->id())->first();
        if ($manager) {
            NotificationController::sendNotification(
                $manager,
                'Alerte Pharmacovigilance',
                "Un signalement de pharmacovigilance (gravité : {$report->severity}) a été soumis par " . auth()->user()->name,
                'pharmacovigilance',
                'superviseur',
                ['report_id' => $report->id]
            );
        }

        // Si gravité grave ou décès, envoyer un email
        // Gravité grave ou décès : email à la direction pharmacovigilance (adresse configurée)
        if (in_array($report->severity, ['grave', 'deces'])) {
            $recipient = config('services.pharmacovigilance.alert_email');
            if ($recipient) {
                Mail::raw("Alerte PV Sévère soumise par " . auth()->user()->name . "\nDescription: " . $report->adverse_event_description, function ($msg) use ($recipient) {
                    $msg->to($recipient)->subject('ALERTE PHARMACOVIGILANCE URGENTE');
                });
            } else {
                \Log::warning('Alerte pharmacovigilance grave non envoyée par email : PV_ALERT_EMAIL non configuré.', ['report_id' => $report->id]);
            }
        }

        return response()->json($report, 201);
    }

    #[OA\Get(
        path: '/api/v1/pharmacovigilance-reports/{id}',
        summary: 'Afficher les détails d\'une déclaration',
        security: [['bearerAuth' => []]],
        tags: ['Pharmacovigilance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de la déclaration'),
        ]
    )]
    public function show(PharmacovigilanceReport $pharmacovigilanceReport)
    {
        return response()->json($pharmacovigilanceReport);
    }

    #[OA\Put(
        path: '/api/v1/pharmacovigilance-reports/{id}',
        summary: 'Mettre à jour une déclaration',
        security: [['bearerAuth' => []]],
        tags: ['Pharmacovigilance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Déclaration mise à jour'),
        ]
    )]
    public function update(Request $request, PharmacovigilanceReport $pharmacovigilanceReport)
    {
        $pharmacovigilanceReport->update($request->all());
        return response()->json($pharmacovigilanceReport);
    }

    #[OA\Delete(
        path: '/api/v1/pharmacovigilance-reports/{id}',
        summary: 'Supprimer une déclaration',
        security: [['bearerAuth' => []]],
        tags: ['Pharmacovigilance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Déclaration supprimée'),
        ]
    )]
    public function destroy(PharmacovigilanceReport $pharmacovigilanceReport)
    {
        $pharmacovigilanceReport->delete();
        return response()->json(null, 204);
    }
}
