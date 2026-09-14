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

/**
 * @OA\Tag(
 *     name="Users",
 *     description="Gestion des utilisateurs"
 * )
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['roles', 'team']);
        
        if ($request->has('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        
        return UserResource::collection($query->get());
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'active' ? 1 : 0;
        }

        // Générer un mot de passe temporaire lisible (pour l'envoyer par email)
        $temporaryPassword = Str::upper(Str::random(4)) . rand(10, 99) . '!' . Str::random(4);

        $data['password'] = Hash::make($temporaryPassword);
        $data['force_password_change'] = true;

        $user = User::create($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        // Envoyer l'email d'invitation avec le mot de passe temporaire en clair
        Mail::to($user->email)->send(new UserInvitationMail($user, $temporaryPassword));

        return response()->json(new UserResource($user->load(['roles', 'team', 'permissions'])), 201);
    }

    public function show(string $id)
    {
        $user = User::with(['roles', 'team', 'permissions'])->findOrFail($id);
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validated();

        // Convertir 'active'/'inactive' → boolean pour la colonne DB
        if (isset($data['status'])) {
            $data['status'] = $data['status'] === 'active' ? 1 : 0;
        }

        $user->update($data);

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return new UserResource($user->load(['roles', 'team', 'permissions']));
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        
        return response()->json(null, 204);
    }
}
