<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = Document::query();

        if ($request->has('documentable_type') && $request->has('documentable_id')) {
            $query->where('documentable_type', $request->documentable_type)
                  ->where('documentable_id', $request->documentable_id);
        }

        \Illuminate\Support\Facades\Log::info('Document Query:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $documents = $query->get()->map(function ($doc) {
            // Map the internal types to frontend display types
            $mappedType = 'NOTICE';
            if ($doc->type === 'presentation') $mappedType = 'E-DETAILING';
            if ($doc->type === 'video') $mappedType = 'VIDEO';
            if ($doc->type === 'pdf') $mappedType = 'BROCHURE';

            $status = 'VALIDE';
            if (!$doc->is_active) $status = 'ARCHIVE';
            if ($doc->expiration_date && \Carbon\Carbon::parse($doc->expiration_date)->isPast()) {
                $status = 'EXPIRE';
            }

            $sizeFormatted = '1.5 MB';
            if ($doc->file_size) {
                $sizeFormatted = round($doc->file_size / (1024 * 1024), 1) . ' MB';
            } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($doc->file_path ?? '')) {
                $bytes = \Illuminate\Support\Facades\Storage::disk('public')->size($doc->file_path);
                $sizeFormatted = round($bytes / (1024 * 1024), 1) . ' MB';
            }

            return [
                'id' => $doc->id,
                'reference' => 'DOC-' . substr($doc->id, 0, 6),
                'titre' => $doc->title,
                'type' => $mappedType,
                'produit' => $doc->documentable_type === 'App\\Models\\Product' ? ($doc->documentable->nom ?? 'Produit Associé') : 'Général',
                'version' => 'v' . ($doc->version ?? '1.0'),
                'taille' => $sizeFormatted,
                'dateExpiration' => $doc->expiration_date ? \Carbon\Carbon::parse($doc->expiration_date)->format('Y-m-d') : 'N/A',
                'statut' => $status,
                'paysAutorises' => ['FR', 'CI', 'SN'],
                'documentable_id' => $doc->documentable_id,
                'documentable_type' => $doc->documentable_type,
                'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url(preg_replace('/^\/?storage\//', '', $doc->file_path)),
                'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures')
            ];
        });
        
        return response()->json([
            'data' => $documents
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'documentable_id' => 'required|uuid',
            'documentable_type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:pdf,video,presentation,image,other',
            'file' => 'nullable|file', // Actual file upload
            'file_path' => 'nullable|string', // Fallback
            'version' => 'nullable|string|max:50',
            'expiration_date' => 'nullable|date|after:today',
            'speciality_id' => 'nullable|uuid',
            'role_id' => 'nullable|integer',
            'status' => 'nullable|in:draft,review,approved,expired',
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('docs', 'public');
        } elseif (!$request->filled('file_path')) {
            return response()->json(['error' => 'A file or file_path is required'], 422);
        }

        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $document = Document::create($data);

        // Format for frontend
        $mappedType = 'NOTICE';
        if ($document->type === 'presentation') $mappedType = 'E-DETAILING';
        if ($document->type === 'video') $mappedType = 'VIDEO';
        if ($document->type === 'pdf') $mappedType = 'BROCHURE';

        $formatted = [
            'id' => $document->id,
            'reference' => 'DOC-' . substr($document->id, 0, 6),
            'titre' => $document->title,
            'type' => $mappedType,
            'produit' => $document->documentable_type === 'App\\Models\\Product' ? ($document->documentable->nom ?? 'Produit') : 'Général',
            'version' => 'v' . $document->version,
            'taille' => '2.4 MB',
            'dateExpiration' => $document->expiration_date ? \Carbon\Carbon::parse($document->expiration_date)->format('Y-m-d') : 'N/A',
            'statut' => 'VALIDE',
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url(preg_replace('/^\/?storage\//', '', $document->file_path)),
            'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures')
        ];

        return response()->json($formatted, 201);
    }

    public function show(Document $document)
    {
        return response()->json($document);
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'version' => 'sometimes|string|max:50',
            'expiration_date' => 'sometimes|nullable|date',
            'title' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:pdf,video,presentation,image,other',
            'documentable_id' => 'sometimes|uuid',
            'documentable_type' => 'sometimes|string',
            'file' => 'sometimes|nullable|file',
            'speciality_id' => 'sometimes|nullable|uuid',
            'role_id' => 'sometimes|nullable|integer',
            'status' => 'sometimes|nullable|in:draft,review,approved,expired',
        ]);

        if ($request->hasFile('file')) {
            // Delete old file if necessary (optional depending on requirements)
            // Storage::disk('public')->delete($document->file_path);
            $data['file_path'] = $request->file('file')->store('docs', 'public');
        }
        unset($data['file']);

        $document->update($data);
        
        $document->refresh();

        // Format for frontend
        $mappedType = 'NOTICE';
        if ($document->type === 'presentation') $mappedType = 'E-DETAILING';
        if ($document->type === 'video') $mappedType = 'VIDEO';
        if ($document->type === 'pdf') $mappedType = 'BROCHURE';

        $formatted = [
            'id' => $document->id,
            'reference' => 'DOC-' . substr($document->id, 0, 6),
            'titre' => $document->title,
            'type' => $mappedType,
            'produit' => $document->documentable_type === 'App\\Models\\Product' ? ($document->documentable->nom ?? 'Produit') : 'Général',
            'version' => 'v' . $document->version,
            'taille' => '2.4 MB',
            'dateExpiration' => $document->expiration_date ? \Carbon\Carbon::parse($document->expiration_date)->format('Y-m-d') : 'N/A',
            'statut' => $document->is_active ? 'VALIDE' : 'ARCHIVE',
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url(preg_replace('/^\/?storage\//', '', $document->file_path)),
            'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures'),
            'documentable_id' => $document->documentable_id,
            'documentable_type' => $document->documentable_type,
        ];

        return response()->json($formatted);
    }

    public function destroy(Document $document)
    {
        $document->delete();
        return response()->json(null, 204);
    }
}
