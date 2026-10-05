<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class SettingController extends Controller
{
    #[OA\Get(
        path: '/api/v1/settings',
        summary: 'Lister les paramètres publics',
        tags: ['Settings'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des paramètres')
        ]
    )]
    public function index(Request $request)
    {
        $query = Setting::query();
        $settings = $query->get()->mapWithKeys(function ($item) {
            // Si c'est un lien de logo, on peut renvoyer l'URL complète
            if ($item->key === 'logo_path' && $item->value) {
                return [$item->key => asset('storage/' . $item->value)];
            }
            return [$item->key => $item->value];
        });

        return response()->json($settings);
    }

    #[OA\Post(
        path: '/api/v1/settings',
        summary: 'Mettre à jour un paramètre (ex: Logo)',
        security: [['bearerAuth' => []]],
        tags: ['Settings'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['key'],
                    properties: [
                        new OA\Property(property: 'key', type: 'string'),
                        new OA\Property(property: 'value', type: 'string'),
                        new OA\Property(property: 'file', type: 'string', format: 'binary', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Paramètre mis à jour')
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|max:100',
            'value' => 'nullable|string',
            'file' => 'nullable|file|image|mimes:jpeg,png,jpg,svg|max:2048',
        ]);

        $value = $request->input('value');

        // Si on upload un fichier (comme un logo)
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('logos', 'public');
            $value = $path; // On enregistre le chemin dans la table
        }

        $setting = Setting::updateOrCreate(
            ['key' => $request->key],
            ['value' => $value]
        );

        $responseValue = $setting->value;
        if ($request->hasFile('file')) {
            $responseValue = asset('storage/' . $responseValue);
        }

        return response()->json([
            'key' => $setting->key,
            'value' => $responseValue,
        ], 200);
    }
}
