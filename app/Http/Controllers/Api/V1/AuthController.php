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
        summary: 'Créer un utilisateur et envoyer un email d\'invitation',
        description: 'L\'admin crée un compte avec email uniquement. Un email est envoyé avec un token pour configurer le mot de passe.',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
                    new OA\Property(property: 'country_id', type: 'string', format: 'uuid', nullable: true),
                    new OA\Property(property: 'subsidiary_id', type: 'string', format: 'uuid', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Utilisateur créé, email d\'invitation envoyé'),
            new OA\Response(response: 422, description: 'Email déjà utilisé ou données invalides')
        ]
    )]
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'country_id' => 'nullable|uuid',
            'subsidiary_id' => 'nullable|uuid',
        ]);

        // Création de l'utilisateur avec un mot de passe temporaire aléatoire
        $validated['password'] = Hash::make(Str::random(32));
        $user = User::create($validated);

        // Génération du token d'invitation
        $token = Str::random(64);
        
        // Stockage du token dans le cache pour 48 heures (2880 minutes)
        Cache::put('invite_token_' . $token, $user->id, now()->addHours(48));

        // Envoi de l'email
        Mail::to($user->email)->send(new UserInvitationMail($user, $token));

        return response()->json([
            'message' => 'Compte créé avec succès, un email d\'invitation a été envoyé.',
            'user' => $user
        ], 201);
    }

    #[OA\Post(
        path: '/api/v1/auth/setup-password',
        summary: 'Définir le mot de passe via le lien d\'invitation',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Mot de passe défini avec succès'),
            new OA\Response(response: 400, description: 'Token invalide ou expiré'),
            new OA\Response(response: 422, description: 'Le mot de passe ne correspond pas ou est trop court')
        ]
    )]
    public function setupPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userId = Cache::get('invite_token_' . $request->token);

        if (!$userId) {
            return response()->json(['message' => 'Le lien d\'invitation est invalide ou a expiré.'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        // Mise à jour du mot de passe
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        // Invalidation du token
        Cache::forget('invite_token_' . $request->token);

        return response()->json(['message' => 'Mot de passe configuré avec succès. Vous pouvez maintenant vous connecter.']);
    }

    #[OA\Post(
        path: '/api/v1/login',
        summary: 'Première étape de connexion (Email/Mot de passe) -> Déclenche l\'OTP',
        description: 'Vérifie les identifiants et envoie un code OTP à 6 chiffres par email.',
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
                response: 202,
                description: 'Identifiants valides, OTP envoyé par email',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'requires_otp'),
                        new OA\Property(property: 'message', type: 'string', example: 'Un code de vérification a été envoyé à votre adresse email.'),
                        new OA\Property(property: 'user_id', type: 'string', format: 'uuid'),
                    ]
                )
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
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Génération de l'OTP à 6 chiffres
        $otpCode = (string) random_int(100000, 999999);

        // Sauvegarde dans le Cache pour 10 minutes
        Cache::put('login_otp_' . $user->id, $otpCode, now()->addMinutes(10));

        // Envoi de l'OTP par email
        Mail::to($user->email)->send(new LoginOtpMail($user, $otpCode));

        return response()->json([
            'status' => 'requires_otp',
            'message' => 'Un code de vérification a été envoyé à votre adresse email. Il expirera dans 10 minutes.',
            'user_id' => $user->id
        ], 202);
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

        // Connexion réussie
        $user->update(['last_login_at' => now()]);
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
        return response()->json($request->user()->load('roles', 'country', 'subsidiary', 'team'));
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
}
