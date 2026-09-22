<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\Account;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Validations', description: 'API de modération des fiches en attente')]
class ValidationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/validations/pending',
        summary: 'Lister les créations en attente de validation',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des éléments en attente'),
        ]
    )]
    public function index(Request $request)
    {
        $user = auth()->user();

        // Pour un superviseur, on récupère les membres de son équipe
        $teamIds = $user->team_id ? [$user->team_id] : [];

        $pendingContacts = Contact::where('statut_validation', 'en_attente')
            ->whereHas('owner', fn($q) => $q->whereIn('team_id', $teamIds))
            ->with(['owner:id,name', 'account:id,nom'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'type' => 'contact',
                'nom' => $c->nom,
                'prenom' => $c->prenom,
                'etablissement' => $c->account?->nom,
                'created_by' => $c->owner?->name,
                'created_at' => $c->created_at,
            ]);

        $pendingAccounts = Account::where('statut_validation', 'en_attente')
            ->whereHas('owner', fn($q) => $q->whereIn('team_id', $teamIds))
            ->with(['owner:id,name'])
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'type' => 'account',
                'nom' => $a->nom,
                'ville' => $a->ville,
                'created_by' => $a->owner?->name,
                'created_at' => $a->created_at,
            ]);

        return response()->json([
            'data' => $pendingContacts->concat($pendingAccounts)->sortByDesc('created_at')->values()
        ]);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/approve',
        summary: 'Approuver une création en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Création approuvée'),
        ]
    )]
    public function approve(string $type, string $id)
    {
        $model = $this->getModel($type, $id);
        $model->update(['statut_validation' => 'valide']);

        if ($model->owner) {
            app(\App\Services\NotificationService::class)->send(
                $model->owner,
                ucfirst($type) . ' approuvé(e)',
                "Votre demande de création de {$type} '{$model->nom}' a été validée par votre manager.",
                'contact',
                ['id' => $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' approuvé avec succès']);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/reject',
        summary: 'Rejeter une création en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Création rejetée'),
        ]
    )]
    public function reject(string $type, string $id)
    {
        $model = $this->getModel($type, $id);
        $model->update(['statut_validation' => 'rejete']);

        if ($model->owner) {
            app(\App\Services\NotificationService::class)->send(
                $model->owner,
                ucfirst($type) . ' rejeté(e)',
                "Votre demande de création de {$type} '{$model->nom}' a été rejetée par votre manager.",
                'contact',
                ['id' => $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' rejeté avec succès']);
    }

    private function getModel(string $type, string $id)
    {
        if ($type === 'contact') {
            return Contact::findOrFail($id);
        }
        if ($type === 'account') {
            return Account::findOrFail($id);
        }
        abort(400, "Type invalide");
    }
}
