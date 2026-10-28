<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Medias', description: 'Gestion des fichiers et médias (Upload/Download)')]
class MediaController extends Controller
{
    #[OA\Post(
        path: '/api/v1/media/upload',
        summary: 'Uploader un fichier',
        security: [['bearerAuth' => []]],
        tags: ['Medias'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                'multipart/form-data' => new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        properties: [
                            new OA\Property(property: 'file', type: 'string', format: 'binary'),
                            new OA\Property(property: 'folder', type: 'string', description: 'Dossier de destination (ex: signatures, avatars)')
                        ]
                    )
                )
            ]
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fichier uploadé avec succès')
        ]
    )]
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,mp4,webm,ppt,pptx,doc,docx,xls,xlsx|max:10240', // 10MB max
            'folder' => 'nullable|string|in:signatures,avatars,documents,reports'
        ]);

        $folder = $request->input('folder', 'general');
        $file = $request->file('file');
        
        $filename = Str::uuid() . '.' . $file->extension();
        $path = $file->storeAs("uploads/{$folder}", $filename, 'public');

        return response()->json([
            'url' => url(Storage::url($path)),
            'path' => $path,
            'filename' => $filename
        ]);
    }
}
