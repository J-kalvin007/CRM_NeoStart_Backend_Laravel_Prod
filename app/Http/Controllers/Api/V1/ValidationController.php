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
        $teamIds = $user && $user->team_id ? [$user->team_id] : [];

        $pendingContacts = Contact::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)
            ->with(['account', 'user', 'owner'])
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('statut_validation', ['en_attente', 'pending'])
                  ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']);
            })
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => (string) $contact->id,
                    'type' => 'contact',
                    'nom' => $contact->nom,
                    'prenom' => $contact->prenom,
                    'titre' => $contact->titre,
                    'specialite' => $contact->specialite ?? $contact->meta['specialite'] ?? 'Médecin',
                    'telephone' => $contact->telephone,
                    'email' => $contact->email,
                    'etablissement' => $contact->account ? $contact->account->nom : null,
                    'ville' => $contact->account ? $contact->account->ville : null,
                    'created_by' => $contact->owner?->name ?? ($contact->user ? ($contact->user->name ?: $contact->user->email) : 'Délégué Médical'),
                    'created_at' => $contact->created_at,
                ];
            });

        $pendingAccounts = Account::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)
            ->with(['user', 'owner'])
            ->where(function ($q) use ($teamIds) {
                $q->whereIn('statut_validation', ['en_attente', 'pending'])
                  ->orWhereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending']);
            })
            ->get()
            ->map(function ($account) {
                return [
                    'id' => (string) $account->id,
                    'type' => 'account',
                    'nom' => $account->nom,
                    'prenom' => null,
                    'titre' => null,
                    'specialite' => 'Établissement',
                    'telephone' => $account->telephone,
                    'email' => $account->email,
                    'etablissement' => $account->nom,
                    'ville' => $account->ville,
                    'created_by' => $account->owner?->name ?? ($account->user ? ($account->user->name ?: $account->user->email) : 'Délégué Médical'),
                    'created_at' => $account->created_at,
                ];
            });

        $pending = $pendingContacts->concat($pendingAccounts)->sortByDesc('created_at')->values();

        return response()->json([
            'data' => $pending
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
        $model->update([
            'statut_validation' => 'valide',
            'statut' => 'actif',
        ]);

        $recipient = $model->owner ?? ($model->user_id ? \App\Models\User::find($model->user_id) : null);
        if ($recipient) {
            try {
                app(\App\Services\NotificationService::class)->send(
                    $recipient,
                    ucfirst($type) . ' approuvé(e)',
                    "Votre demande de création de {$type} '{$model->nom}' a été validée par votre manager.",
                    'contact',
                    ['id' => $model->id, 'type' => $type]
                );
            } catch (\Throwable $e) {}

            NotificationController::sendNotification(
                $recipient,
                'Fiche Validée par le Manager',
                "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " a été approuvée.",
                'validation',
                'delegate',
                ['id' => (string) $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' approuvé avec succès', 'data' => $model]);
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
        $model->update([
            'statut_validation' => 'rejete',
            'statut' => 'rejete',
        ]);

        $recipient = $model->owner ?? ($model->user_id ? \App\Models\User::find($model->user_id) : null);
        if ($recipient) {
            try {
                app(\App\Services\NotificationService::class)->send(
                    $recipient,
                    ucfirst($type) . ' rejeté(e)',
                    "Votre demande de création de {$type} '{$model->nom}' a été rejetée par votre manager.",
                    'contact',
                    ['id' => $model->id, 'type' => $type]
                );
            } catch (\Throwable $e) {}

            NotificationController::sendNotification(
                $recipient,
                'Fiche Non Validée',
                "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " n'a pas été validée par votre superviseur.",
                'validation',
                'delegate',
                ['id' => (string) $model->id, 'type' => $type]
            );
        }

        return response()->json(['message' => ucfirst($type) . ' rejeté avec succès', 'data' => $model]);
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
