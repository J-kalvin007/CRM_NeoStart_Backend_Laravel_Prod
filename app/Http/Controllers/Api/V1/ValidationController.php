<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Contact;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Validations', description: 'API de modération des fiches en attente')]
class ValidationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/validations/pending',
        summary: 'Lister les contacts et établissements en attente de validation',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des entités en attente')
        ]
    )]
    public function indexPending()
    {
        $pendingContacts = Contact::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)
            ->with(['account', 'user'])
            ->whereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending'])
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
                    'created_by' => $contact->user ? ($contact->user->name ?: $contact->user->email) : 'Délégué Médical',
                    'created_at' => $contact->created_at,
                ];
            });

        $pendingAccounts = Account::withoutGlobalScope(\App\Models\Scopes\PerimeterScope::class)
            ->with('user')
            ->whereIn('statut', ['en_attente', 'en_attente_validation', 'inactif', 'pending'])
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
                    'created_by' => $account->user ? ($account->user->name ?: $account->user->email) : 'Délégué Médical',
                    'created_at' => $account->created_at,
                ];
            });

        $pending = $pendingContacts->concat($pendingAccounts)->sortByDesc('created_at')->values();

        return response()->json($pending);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/approve',
        summary: 'Valider une entité en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Entité validée')
        ]
    )]
    public function approve(string $type, string $id)
    {
        if ($type === 'contact') {
            $model = Contact::findOrFail($id);
        } elseif ($type === 'account') {
            $model = Account::findOrFail($id);
        } else {
            return response()->json(['message' => 'Type invalide'], 400);
        }

        $model->update(['statut' => 'actif']);

        // Notification au créateur (délégué)
        if ($model->user_id) {
            $delegate = \App\Models\User::find($model->user_id);
            if ($delegate) {
                NotificationController::sendNotification(
                    $delegate,
                    'Fiche Validée par le Manager',
                    "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " a été approuvée.",
                    'validation',
                    'delegate',
                    ['id' => (string) $model->id, 'type' => $type]
                );
            }
        }

        return response()->json(['message' => 'Validé avec succès', 'data' => $model]);
    }

    #[OA\Post(
        path: '/api/v1/validations/{type}/{id}/reject',
        summary: 'Rejeter une entité en attente',
        security: [['bearerAuth' => []]],
        tags: ['Validations'],
        parameters: [
            new OA\Parameter(name: 'type', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['contact', 'account'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Entité rejetée')
        ]
    )]
    public function reject(Request $request, string $type, string $id)
    {
        if ($type === 'contact') {
            $model = Contact::findOrFail($id);
        } elseif ($type === 'account') {
            $model = Account::findOrFail($id);
        } else {
            return response()->json(['message' => 'Type invalide'], 400);
        }

        $model->update(['statut' => 'rejete']);

        if ($model->user_id) {
            $delegate = \App\Models\User::find($model->user_id);
            if ($delegate) {
                NotificationController::sendNotification(
                    $delegate,
                    'Fiche Non Validée',
                    "La fiche " . ($type === 'contact' ? "du prescripteur {$model->prenom} {$model->nom}" : "de l'établissement {$model->nom}") . " n'a pas été validée par votre superviseur.",
                    'validation',
                    'delegate',
                    ['id' => (string) $model->id, 'type' => $type]
                );
            }
        }

        return response()->json(['message' => 'Rejeté avec succès', 'data' => $model]);
    }
}
