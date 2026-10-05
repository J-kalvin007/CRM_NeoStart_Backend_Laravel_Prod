<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * Gestion du branding dynamique de l'application.
 *
 * Permet à l'administrateur de personnaliser :
 *   - Le nom de l'application (app_name)
 *   - Le logo affiché dans l'interface (logo_path) — formats : jpeg, jpg, png, webp — max 2 Mo
 *   - L'icône de l'onglet navigateur / favicon (favicon_path) — formats : png, ico — max 512 Ko
 *
 * Les paramètres sont stockés dans la table `settings` avec une clé normalisée.
 * Les paramètres sont globaux (sans filiale).
 */
#[OA\Tag(
    name: 'App Branding',
    description: 'Personnalisation dynamique de l\'identité visuelle de l\'application (nom, logo, favicon).'
)]
class AppBrandingController extends Controller
{
    // ────────────────────────────────────────────────────────────────────────
    // Constantes de validation
    // ────────────────────────────────────────────────────────────────────────

    /** Formats autorisés pour le logo */
    private const LOGO_MIMES = ['jpeg', 'jpg', 'png', 'webp'];

    /** Taille maximale du logo en Ko (2 048 Ko = 2 Mo) */
    private const LOGO_MAX_KB = 2048;

    /** Formats autorisés pour la favicon */
    private const FAVICON_MIMES = ['png', 'ico'];

    /** Taille maximale de la favicon en Ko (512 Ko) */
    private const FAVICON_MAX_KB = 512;

    // ────────────────────────────────────────────────────────────────────────
    // GET /api/v1/branding  (public — pas besoin d'être connecté)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/v1/branding',
        summary: 'Récupérer la configuration de branding de l\'application',
        description: <<<'DESC'
Retourne le nom, le logo et la favicon de l'application.
- Les URLs de logo et favicon sont des URLs absolues prêtes à l'emploi.
- **Accessible sans authentification** pour permettre l'affichage avant la connexion.
DESC,
        tags: ['App Branding'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Configuration de branding',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'app_name', type: 'string', example: 'MyCRM', nullable: true),
                        new OA\Property(property: 'logo_url', type: 'string', format: 'uri', example: 'https://example.com/storage/branding/logo.png', nullable: true),
                        new OA\Property(property: 'favicon_url', type: 'string', format: 'uri', example: 'https://example.com/storage/branding/favicon.ico', nullable: true),
                    ]
                )
            )
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        // Récupérer les settings globaux
        $globalSettings = Setting::whereIn('key', ['app_name', 'logo_path', 'favicon_path'])
            ->pluck('value', 'key')
            ->toArray();

        return response()->json([
            'app_name'    => $globalSettings['app_name']    ?? null,
            'logo_url'    => isset($globalSettings['logo_path'])    ? asset('storage/' . $globalSettings['logo_path'])    : null,
            'favicon_url' => isset($globalSettings['favicon_path']) ? asset('storage/' . $globalSettings['favicon_path']) : null,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PUT /api/v1/branding/name  (admin)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Put(
        path: '/api/v1/branding/name',
        summary: 'Mettre à jour le nom de l\'application',
        description: 'Met à jour la clé `app_name` dans la table settings.',
        security: [['bearerAuth' => []]],
        tags: ['App Branding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['app_name'],
                properties: [
                    new OA\Property(property: 'app_name', type: 'string', maxLength: 100, example: 'MyCRM Pro'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Nom mis à jour avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'key', type: 'string', example: 'app_name'),
                        new OA\Property(property: 'value', type: 'string', example: 'MyCRM Pro'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ]
    )]
    public function updateName(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_name' => 'required|string|max:100',
        ]);

        $setting = Setting::updateOrCreate(
            ['key' => 'app_name'],
            ['value' => $data['app_name']]
        );

        return response()->json([
            'key'   => $setting->key,
            'value' => $setting->value,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /api/v1/branding/logo  (admin — multipart/form-data)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/v1/branding/logo',
        summary: 'Uploader / remplacer le logo de l\'application',
        description: <<<'DESC'
Upload du logo affiché dans l'interface (header, page de connexion, etc.).

**Contraintes fichier :**
- Formats acceptés : `jpeg`, `jpg`, `png`, `webp`
- Taille maximale : **2 Mo**
- L'ancien fichier est automatiquement supprimé du stockage.

L'URL du logo est retournée dans le champ `logo_url`.
DESC,
        security: [['bearerAuth' => []]],
        tags: ['App Branding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['logo'],
                    properties: [
                        new OA\Property(
                            property: 'logo',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier logo (jpeg, jpg, png, webp — max 2 Mo)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logo uploadé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'logo_url', type: 'string', format: 'uri'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Fichier invalide (format ou taille refusé)'),
        ]
    )]
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:' . implode(',', self::LOGO_MIMES),
                'max:' . self::LOGO_MAX_KB,
            ],
        ]);

        // Supprimer l'ancien fichier s'il existe
        $this->deleteOldFile('logo_path', null);

        // Stocker le nouveau logo
        $path = $request->file('logo')->storeAs(
            'branding',
            'logo_' . Str::random(12) . '.' . $request->file('logo')->getClientOriginalExtension(),
            'public'
        );

        Setting::updateOrCreate(
            ['key' => 'logo_path'],
            ['value' => $path]
        );

        return response()->json([
            'logo_url' => asset('storage/' . $path),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /api/v1/branding/favicon  (admin — multipart/form-data)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Post(
        path: '/api/v1/branding/favicon',
        summary: 'Uploader / remplacer la favicon (icône de l\'onglet)',
        description: <<<'DESC'
Upload de la favicon affichée dans les onglets du navigateur.

**Contraintes fichier :**
- Formats acceptés : `png`, `ico`
- Taille maximale : **512 Ko**
- L'ancien fichier est automatiquement supprimé.

> **Recommandation** : utiliser une image carrée de **32×32 px** ou **64×64 px** en PNG pour une compatibilité maximale.
DESC,
        security: [['bearerAuth' => []]],
        tags: ['App Branding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['favicon'],
                    properties: [
                        new OA\Property(
                            property: 'favicon',
                            type: 'string',
                            format: 'binary',
                            description: 'Fichier favicon (png ou ico — max 512 Ko, recommandé 32×32 ou 64×64 px)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Favicon uploadée avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'favicon_url', type: 'string', format: 'uri'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 422, description: 'Fichier invalide (format ou taille refusé)'),
        ]
    )]
    public function uploadFavicon(Request $request): JsonResponse
    {
        $request->validate([
            'favicon' => [
                'required',
                'file',
                'mimes:' . implode(',', self::FAVICON_MIMES),
                'max:' . self::FAVICON_MAX_KB,
            ],
        ]);

        // Supprimer l'ancien fichier s'il existe
        $this->deleteOldFile('favicon_path', null);

        // Stocker la nouvelle favicon
        $path = $request->file('favicon')->storeAs(
            'branding',
            'favicon_' . Str::random(12) . '.' . $request->file('favicon')->getClientOriginalExtension(),
            'public'
        );

        Setting::updateOrCreate(
            ['key' => 'favicon_path'],
            ['value' => $path]
        );

        return response()->json([
            'favicon_url' => asset('storage/' . $path),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/branding/logo  (admin)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Delete(
        path: '/api/v1/branding/logo',
        summary: 'Supprimer le logo de l\'application',
        description: 'Supprime le fichier logo du stockage et retire l\'entrée de la table settings.',
        security: [['bearerAuth' => []]],
        tags: ['App Branding'],
        responses: [
            new OA\Response(response: 204, description: 'Logo supprimé avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function deleteLogo(Request $request): JsonResponse
    {
        $this->deleteOldFile('logo_path');
        Setting::where('key', 'logo_path')->delete();
        return response()->json(null, 204);
    }

    // ────────────────────────────────────────────────────────────────────────
    // DELETE /api/v1/branding/favicon  (admin)
    // ────────────────────────────────────────────────────────────────────────

    #[OA\Delete(
        path: '/api/v1/branding/favicon',
        summary: 'Supprimer la favicon de l\'application',
        description: 'Supprime le fichier favicon du stockage et retire l\'entrée de la table settings.',
        security: [['bearerAuth' => []]],
        tags: ['App Branding'],
        responses: [
            new OA\Response(response: 204, description: 'Favicon supprimée avec succès'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ]
    )]
    public function deleteFavicon(Request $request): JsonResponse
    {
        $this->deleteOldFile('favicon_path');
        Setting::where('key', 'favicon_path')->delete();
        return response()->json(null, 204);
    }

    // ────────────────────────────────────────────────────────────────────────
    // Méthode privée utilitaire
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Supprime l'ancien fichier physique lié à une clé de setting.
     *
     * @param string $key Clé du setting (logo_path / favicon_path)
     */
    private function deleteOldFile(string $key): void
    {
        $setting = Setting::where('key', $key)->first();

        if ($setting && $setting->value && Storage::disk('public')->exists($setting->value)) {
            Storage::disk('public')->delete($setting->value);
        }
    }
}
