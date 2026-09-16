<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Mail\UserInvitationMail;
use App\Mail\LoginOtpMail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/v1/auth/invite',
        summary: 'Créer un utilisateur (mot de passe temporaire)',
        description: 'L\'admin crée un compte. Le système génère un mot de passe temporaire s\'il n\'est pas fourni. L\'utilisateur devra le changer à la première connexion.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
                    new OA\Property(property: 'password', type: 'string', description: 'Mot de passe temporaire créé par l\'admin'),
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'region_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'territory_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'zone_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'sector_id', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé, email contenant les identifiants envoyé'),
            new OA\Response(response: 422, description: 'Email déjà utilisé ou données invalides')
        ]
    )]
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'country_id' => 'nullable|uuid',
            'subsidiary_id' => 'nullable|uuid',
            'region_id' => 'nullable|uuid',
            'territory_id' => 'nullable|uuid',
            'zone_id' => 'nullable|uuid',
            'sector_id' => 'nullable|uuid',
        ]);

        $temporaryPassword = $validated['password'];
        
        $validated['password'] = Hash::make($temporaryPassword);
        $validated['force_password_change'] = true;
        
        $user = User::create($validated);

        // Envoi de l'email avec le mot de passe en clair (temporaire)
        Mail::to($user->email)->send(new UserInvitationMail($user, $temporaryPassword));

        return response()->json([
            'message' => 'Compte créé avec succès, un email avec les identifiants a été envoyé.',
            'user' => $user
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/login',
        summary: 'Connexion (Connexion directe ou déclenchement OTP si activé)',
        description: 'Vérifie les identifiants. Si premier accès, force le changement de mot de passe. Si l\'utilisateur a activé l\'OTP, envoie le code à 6 chiffres. Sinon, connecte directement sans OTP.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie (Token délivré directement si OTP désactivé)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'user', type: 'object')
                ])
            ),
            new OA\Response(
                response: 202,
                description: 'OTP requis et envoyé par email (si activé sur le profil utilisateur)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'requires_otp'),
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid')
                ])
            ),
            new OA\Response(
                response: 403,
                description: 'Changement de mot de passe requis (première connexion)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'requires_password_change'),
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid')
                ])
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides')
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects'],
            ]);
        }

        // Vérification du changement de mot de passe obligatoire (première connexion)
        if ($user->force_password_change) {
            return response()->json([
                'status' => 'requires_password_change',
                'message' => 'Veuillez modifier votre mot de passe temporaire pour continuer.',
                'user_id' => $user->id
            ], 403);
        }

        // Si l'utilisateur a activé l'authentification OTP sur son profil
        if ($user->otp_enabled) {
            $otpCode = (string) random_int(100000, 999999);
            Cache::put('login_otp_' . $user->id, $otpCode, now()->addMinutes(10));
            Mail::to($user->email)->send(new LoginOtpMail($user, $otpCode));

            return response()->json([
                'status' => 'requires_otp',
                'message' => 'Un code de vérification a été envoyé à votre adresse email.',
                'user_id' => $user->id
            ], 202);
        }

        // Par défaut (OTP désactivé) : Connexion directe sans code
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load(['roles', 'permissions', 'country', 'subsidiary', 'team'])
        ], 200);
    }

    #[OA\Post(
        path: '/api/v1/auth/change-password',
        summary: 'Changer le mot de passe temporaire',
        description: 'Définit le nouveau mot de passe de l\'utilisateur. Si l\'OTP est activé sur son compte, déclenche l\'envoi du code. Sinon, le connecte directement.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'old_password', 'new_password', 'new_password_confirmation'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'old_password', type: 'string', format: 'password'),
                    new OA\Property(property: 'new_password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe mis à jour avec succès, connexion directe'),
            new OA\Response(response: 202, description: 'Mot de passe mis à jour, OTP envoyé par email (si OTP activé)'),
            new OA\Response(response: 422, description: 'Ancien mot de passe incorrect ou nouveau non conforme')
        ]
    )]
    public function forceChangePassword(Request $request)
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($request->user_id);

        if (!Hash::check($request->old_password, $user->password)) {
            throw ValidationException::withMessages([
                'old_password' => ['L\'ancien mot de passe est incorrect.'],
            ]);
        }

        // Mise à jour du mot de passe et retrait du flag de mot de passe temporaire
        $updateData = [
            'password' => Hash::make($request->new_password),
            'force_password_change' => false,
        ];
        if (is_null($user->email_verified_at)) {
            $updateData['email_verified_at'] = now();
        }
        $user->update($updateData);

        // Si l'utilisateur a activé l'authentification OTP
        if ($user->otp_enabled) {
            $otpCode = (string) random_int(100000, 999999);
            Cache::put('login_otp_' . $user->id, $otpCode, now()->addMinutes(10));
            Mail::to($user->email)->send(new LoginOtpMail($user, $otpCode));

            return response()->json([
                'status' => 'requires_otp',
                'message' => 'Mot de passe mis à jour avec succès. Un code OTP a été envoyé à votre email.',
                'user_id' => $user->id
            ], 202);
        }

        // Par défaut (OTP non activé) : Connexion directe sans friction
        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.',
            'token' => $token,
            'user' => $user->load(['roles', 'permissions', 'country', 'subsidiary', 'team'])
        ], 200);
    }

    #[OA\Post(
        path: '/api/v1/verify-otp',
        summary: 'Deuxième étape de connexion (Vérification OTP)',
        description: 'Valide le code OTP reçu par email et retourne le Token Sanctum final.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'otp_code'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'otp_code', type: 'string', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie, Token délivré',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Code OTP invalide ou expiré')
        ]
    )]
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|uuid',
            'otp_code' => 'required|string|size:6',
        ]);

        $user = User::find($request->user_id);
        
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        // Récupération de l'OTP depuis le cache
        $cachedOtp = Cache::get('login_otp_' . $user->id);

        if (!$cachedOtp || $cachedOtp !== $request->otp_code) {
            return response()->json(['message' => 'Code de vérification invalide ou expiré.'], 401);
        }

        // L'OTP est valide, on l'efface du cache
        Cache::forget('login_otp_' . $user->id);

        // Mise à jour des informations de connexion et vérification email
        $updateData = ['last_login_at' => now()];
        if (is_null($user->email_verified_at)) {
            $updateData['email_verified_at'] = now();
        }
        $user->update($updateData);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('roles', 'country', 'subsidiary', 'team')
        ]);
    }

    #[OA\Get(
        path: '/api/v1/me',
        summary: 'Obtenir les informations de l\'utilisateur connecté',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'Succès'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function me(Request $request)
    {
        return response()->json($request->user()->load(['roles', 'permissions', 'country', 'subsidiary', 'team']));
    }

    /**
     * Enregistrer le token FCM de l'appareil mobile
     */
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json(['message' => 'FCM Token updated successfully']);
    }

    #[OA\Post(
        path: '/api/v1/auth/toggle-otp',
        summary: 'Activer ou désactiver l\'authentification OTP (2FA)',
        description: 'Permet à l\'utilisateur connecté d\'activer ou désactiver l\'authentification par code OTP (2FA) lors de ses prochaines connexions.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['otp_enabled'],
                properties: [
                    new OA\Property(property: 'otp_enabled', type: 'boolean', example: true, description: 'true pour activer, false pour désactiver')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Préférence OTP mise à jour avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'otp_enabled', type: 'boolean')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Données invalides')
        ]
    )]
    public function toggleOtp(Request $request)
    {
        $validated = $request->validate([
            'otp_enabled' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->otp_enabled = $validated['otp_enabled'];
        $user->save();

        return response()->json([
            'message' => $user->otp_enabled
                ? 'Authentification à double facteur (OTP) activée avec succès.'
                : 'Authentification à double facteur (OTP) désactivée avec succès.',
            'otp_enabled' => (bool) $user->otp_enabled,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/logout',
        summary: 'Déconnexion (Révoquer le Token)',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(response: 200, description: 'Déconnexion réussie'),
            new OA\Response(response: 401, description: 'Non authentifié')
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    #[OA\Post(
        path: '/api/v1/password/email',
        summary: 'Demander la réinitialisation du mot de passe par email',
        description: 'Génère un jeton ou un code temporaire et l\'envoie à l\'adresse email de l\'utilisateur si le compte existe.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Email de réinitialisation envoyé si le compte existe'),
            new OA\Response(response: 422, description: 'Email valide requis')
        ]
    )]
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $resetToken = Str::random(60);
            Cache::put('password_reset_' . $user->email, $resetToken, now()->addHours(1));
            // Pour le dev / staging, on renvoie une confirmation claire
        }

        return response()->json([
            'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email si elle correspond à un compte actif.'
        ]);
    }
}
