<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\UserInvitationMail;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Users', description: 'Gestion des utilisateurs')]
class UserController extends Controller
{
    #[OA\Get(
        path: '/api/v1/users',
        summary: 'Lister les utilisateurs',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'team_id', in: 'query', required: false, description: 'Filtrer par équipe', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs avec rôles et équipe'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        $query = User::with(['roles', 'team']);
        
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        
        return UserResource::collection($query->get());
    }

    #[OA\Post(
        path: '/api/v1/users',
        summary: 'Créer un utilisateur',
        description: 'Crée un compte utilisateur avec un mot de passe temporaire généré automatiquement. Un email d\'invitation contenant les identifiants est envoyé à l\'utilisateur. Il devra changer son mot de passe (min. 8 caractères) dès sa première connexion.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'status'],
                properties: [
                    new OA\Property(property: 'name',          type: 'string',  example: 'Jean Dupont'),
                    new OA\Property(property: 'email',         type: 'string',  format: 'email', example: 'jean.dupont@crm.local'),
                    new OA\Property(property: 'phone',         type: 'string',  nullable: true, example: '+22890000000'),
                    new OA\Property(property: 'status',        type: 'string',  enum: ['active', 'inactive'], example: 'active'),
                    new OA\Property(property: 'manager_id',    type: 'string',  format: 'uuid', nullable: true),
                    new OA\Property(property: 'scopes',        type: 'array',   items: new OA\Items(type: 'string', format: 'uuid'), description: 'IDs des périmètres (organizational units)'),
                    new OA\Property(property: 'roles',         type: 'array',   items: new OA\Items(type: 'string'), example: ['delegue']),
                    new OA\Property(property: 'otp_enabled',   type: 'boolean', example: false, description: 'Activer la double authentification OTP pour cet utilisateur (défaut: false)'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé, email d\'invitation envoyé automatiquement'),
            new OA\Response(response: 422, description: 'Validation échouée (email déjà utilisé, champs manquants...)'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

<<<<<<< HEAD
        if (array_key_exists('status', $data)) {
            $data['status'] = in_array($data['status'], ['active', true, 1, '1'], true) ? true : false;
        }
        
        $data['password'] = Hash::make(Str::random(12));
=======
        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'active' ? 1 : 0;
        }

        // Par défaut, l'OTP est désactivé
        $data['otp_enabled'] = $request->boolean('otp_enabled', false);

        // Générer un mot de passe temporaire lisible (pour l'envoyer par email)
        $temporaryPassword = Str::upper(Str::random(4)) . rand(10, 99) . '!' . Str::random(4);

        $data['password'] = Hash::make($temporaryPassword);
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a
        $data['force_password_change'] = true;

        $user = User::create($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }
        
        // Validation stricte du périmètre (1 seul périmètre max si permission `restriction:single_scope`)
        if (isset($data['scopes'])) {
            if ($user->hasPermissionTo('restriction:single_scope') && count($data['scopes']) > 1) {
                return response()->json(['message' => 'Ce rôle ne permet l\'affectation qu\'à un seul périmètre à la fois.'], 422);
            }
            $user->scopes()->sync($data['scopes']);
        }

        // Envoyer l'email d'invitation avec le mot de passe temporaire en clair
        Mail::to($user->email)->send(new UserInvitationMail($user, $temporaryPassword));

        return response()->json(new UserResource($user->load(['roles', 'team', 'permissions'])), 201);
    }

    #[OA\Get(
        path: '/api/v1/users/{id}',
        summary: 'Afficher un utilisateur',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détails de l\'utilisateur avec rôles, permissions et équipe'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function show(string $id)
    {
        $user = User::with(['roles', 'team', 'permissions'])->findOrFail($id);
        return new UserResource($user);
    }

    #[OA\Put(
        path: '/api/v1/users/{id}',
        summary: 'Mettre à jour un utilisateur',
        description: 'Met à jour les informations d\'un utilisateur. Le statut doit être "active" ou "inactive". Les rôles et permissions sont synchronisés si fournis.',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name',          type: 'string'),
                    new OA\Property(property: 'phone',         type: 'string', nullable: true),
                    new OA\Property(property: 'status',        type: 'string', enum: ['active', 'inactive']),
                    new OA\Property(property: 'manager_id',    type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'scopes',        type: 'array',  items: new OA\Items(type: 'string', format: 'uuid')),
                    new OA\Property(property: 'roles',         type: 'array',  items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'otp_enabled',   type: 'boolean', description: 'Activer ou désactiver l\'OTP'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateur mis à jour'),
            new OA\Response(response: 422, description: 'Validation échouée'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();

<<<<<<< HEAD
        if (array_key_exists('status', $data)) {
            $data['status'] = in_array($data['status'], ['active', true, 1, '1'], true) ? true : false;
=======
        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'active' ? 1 : 0;
>>>>>>> 3f45c15dfd167361f9f9e2f581bcc5692bacd07a
        }

        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }
        
        // Validation stricte du périmètre (1 seul périmètre max si permission `restriction:single_scope`)
        if (isset($data['scopes'])) {
            if ($user->hasPermissionTo('restriction:single_scope') && count($data['scopes']) > 1) {
                return response()->json(['message' => 'Ce rôle ne permet l\'affectation qu\'à un seul périmètre à la fois.'], 422);
            }
            $user->scopes()->sync($data['scopes']);
        }

        return new UserResource($user->load(['roles', 'team', 'permissions']));
    }

    #[OA\Delete(
        path: '/api/v1/users/{id}',
        summary: 'Supprimer un utilisateur',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Utilisateur supprimé'),
            new OA\Response(response: 404, description: 'Utilisateur introuvable'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        
        return response()->json(null, 204);
    }
}
