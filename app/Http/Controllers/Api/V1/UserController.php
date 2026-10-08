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
        responses: [
            new OA\Response(response: 200, description: 'Liste des utilisateurs avec rôles et équipe'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function index(Request $request)
    {
        $actor = $request->user();
        $query = User::with(['roles', 'country', 'manager']);

        if (!$this->hasUserPermission($actor, 'users.view-all') && !$this->hasUserPermission($actor, 'users.view')) {
            $query->whereIn('id', array_values(array_unique(array_merge([$actor->id], $actor->teamMemberIds()))));
        }

        // ?team=1 ou ?role=delegate : uniquement les collaborateurs encadrés (sans soi-même)
        if ($request->boolean('team') || in_array($request->query('role'), ['delegate', 'delegue', 'delegue_medical'], true)) {
            $query->whereIn('id', $request->user()->teamMemberIds());
        }

        return UserResource::collection($query->with('country')->orderBy('name')->get());
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
        $this->authorizeUserPermission($request, 'users.create');
        $data = $request->validated();

        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'active' ? 1 : 0;
        }

        // Par défaut, l'OTP est désactivé
        $data['otp_enabled'] = $request->boolean('otp_enabled', false);

        // Si un mot de passe est fourni, l'utiliser directement
        // Sinon, générer un mot de passe temporaire lisible
        if (!empty($data['password'])) {
            $temporaryPassword = $data['password'];
            $data['password'] = Hash::make($data['password']);
            $data['force_password_change'] = false; // Pas forcer le changement si admin a défini le mot de passe
        } else {
            $temporaryPassword = Str::upper(Str::random(4)) . rand(10, 99) . '!' . Str::random(4);
            $data['password'] = Hash::make($temporaryPassword);
            $data['force_password_change'] = false;
        }

        $user = User::create($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }
        

        // Envoyer l'email d'invitation avec le mot de passe en clair
        Mail::to($user->email)->send(new UserInvitationMail($user, $temporaryPassword));

        // Envoyer une notification in-app
        $user->notify(new \App\Notifications\SystemNotification(
            'Bienvenue sur le CRM',
            "Votre compte a été créé avec succès. Identifiants de connexion : Email: {$user->email} | Mot de passe: {$temporaryPassword}",
            'info',
            ['user_id' => $user->id]
        ));

        return response()->json(new UserResource($user->load(['roles', 'permissions', 'country'])), 201);
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
        $actor = request()->user();
        $user = User::with([
            'roles', 
            'country', 
            'permissions',
            'manager',
            'contacts' => fn($q) => $q->with(['account', 'speciality'])->latest()->limit(50),
            'accounts' => fn($q) => $q->latest()->limit(50),
            'activities' => fn($q) => $q->with(['contact', 'account'])->latest()->limit(50)
        ])
        ->withCount(['contacts', 'accounts', 'activities'])
        ->findOrFail($id);

        abort_unless(
            $this->hasUserPermission($actor, 'users.view-profile')
                || $this->hasUserPermission($actor, 'users.view')
                || (string) $actor->id === (string) $user->id
                || $actor->manages($user->id),
            403,
            'Vous ne pouvez pas consulter ce profil utilisateur.'
        );

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
        $this->authorizeUserPermission($request, 'users.edit');
        $user = User::findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('roles', $data) || array_key_exists('permissions', $data)) {
            $this->authorizeUserPermission($request, 'users.manage-roles');
        }

        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = in_array($data['status'], ['active', 'true', '1', 1]) ? 1 : 0;
        }

        // Extraire les champs relations avant l'update
        $roles       = $data['roles']       ?? null;
        $permissions = $data['permissions'] ?? null;
        unset($data['roles'], $data['permissions']);

        // Vérifier si le mot de passe est modifié
        $passwordChanged = false;
        $newPassword = '';
        if (!empty($data['password'])) {
            $newPassword = $data['password'];
            $data['password'] = Hash::make($data['password']);
            $data['force_password_change'] = false;
            $passwordChanged = true;
        }

        // Mettre à jour les champs du modèle (inclut manager_id)
        $user->update($data);

        if ($roles !== null) {
            $user->syncRoles($roles);
        }

        if ($permissions !== null) {
            $user->syncPermissions($permissions);
        }

        // Si le mot de passe a été modifié, envoyer email et notification
        if ($passwordChanged) {
            // Envoyer l'email avec le nouveau mot de passe
            Mail::to($user->email)->send(new \App\Mail\PasswordChangedMail($user, $newPassword));

            // Envoyer une notification in-app
            $user->notify(new \App\Notifications\SystemNotification(
                'Mot de passe modifié',
                "Votre mot de passe a été modifié par l'administrateur. Veuillez utiliser votre nouveau mot de passe pour vous connecter.",
                'warning',
                ['user_id' => $user->id]
            ));
        }

        return new UserResource($user->load(['roles', 'permissions', 'country']));
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
        $this->authorizeUserPermission(request(), 'users.delete');
        $user = User::findOrFail($id);
        $user->delete();
        
        return response()->json(null, 204);
    }

    private function hasUserPermission(?User $user, string $permission): bool
    {
        if (!$user) return false;

        return $user->can('scope:global')
            || $user->hasAnyRole(['superadmin', 'super-admin', 'admin'])
            || $user->can($permission);
    }

    private function authorizeUserPermission(Request $request, string $permission): void
    {
        abort_unless(
            $this->hasUserPermission($request->user(), $permission),
            403,
            'Vous ne disposez pas des droits nécessaires pour gérer les utilisateurs.'
        );
    }

    #[OA\Post(
        path: '/api/v1/users/bulk-update-country',
        summary: 'Affecter un pays à plusieurs utilisateurs',
        security: [['bearerAuth' => []]],
        tags: ['Users'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_ids', 'country_id'],
                properties: [
                    new OA\Property(property: 'user_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Utilisateurs mis à jour'),
        ]
    )]
    public function bulkUpdateCountry(Request $request)
    {
        $this->authorizeUserPermission($request, 'users.edit');
        
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'uuid|exists:users,id',
            'country_id' => 'required|uuid|exists:countries,id',
        ]);

        User::whereIn('id', $request->user_ids)->update(['country_id' => $request->country_id]);

        return response()->json(['message' => 'Utilisateurs mis à jour avec succès']);
    }
}
