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
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Adresse des fichiers joignable par le client (et non localhost de APP_URL)
        $host = $request->getSchemeAndHttpHost();

        $documents = $query->with('documentable')->get()->map(function ($doc) use ($host) {
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

            $relative = preg_replace('/^\/?storage\//', '', $doc->file_path ?? '');
            $sizeFormatted = null;
            if ($relative !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($relative)) {
                $bytes = \Illuminate\Support\Facades\Storage::disk('public')->size($relative);
                $sizeFormatted = round($bytes / (1024 * 1024), 1) . ' MB';
            }

            $url = null;
            if ($doc->file_path) {
                $url = preg_match('#^https?://#', $doc->file_path)
                    ? $doc->file_path
                    : $host . \Illuminate\Support\Facades\Storage::url($relative);
            }

            return [
                'id' => $doc->id,
                'reference' => 'DOC-' . substr($doc->id, 0, 6),
                'titre' => $doc->title,
                'title' => $doc->title,
                'description' => $doc->description,
                'type' => $mappedType,
                'raw_type' => $doc->type,
                'produit' => $doc->documentable_type === 'App\\Models\\Product' ? ($doc->documentable->nom ?? null) : 'Général',
                'version' => 'v' . ($doc->version ?? '1.0'),
                'taille' => $sizeFormatted,
                'dateExpiration' => $doc->expiration_date ? \Carbon\Carbon::parse($doc->expiration_date)->format('Y-m-d') : null,
                'expiration_date' => $doc->expiration_date ? \Carbon\Carbon::parse($doc->expiration_date)->format('Y-m-d') : null,
                'statut' => $status,
                'status' => $doc->status,
                'is_active' => (bool) $doc->is_active,
                'documentable_id' => $doc->documentable_id,
                'documentable_type' => $doc->documentable_type,
                'url' => $url,
                'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures')
            ];
        });

        return response()->json([
            'data' => $documents
        ]);
    }

    public function store(Request $request)
    {
        if (Auth::user() && Auth::user()->hasRole(['delegue', 'delegue_medical'])) {
            return response()->json(['message' => 'Action non autorisée pour votre rôle.'], 403);
        }
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

        $status = 'VALIDE';
        if (!$document->is_active) $status = 'ARCHIVE';
        if ($document->expiration_date && \Carbon\Carbon::parse($document->expiration_date)->isPast()) {
            $status = 'EXPIRE';
        }

        $sizeFormatted = '1.5 MB';
        if ($document->file_size) {
            $sizeFormatted = round($document->file_size / (1024 * 1024), 1) . ' MB';
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path ?? '')) {
            $bytes = \Illuminate\Support\Facades\Storage::disk('public')->size($document->file_path);
            $sizeFormatted = round($bytes / (1024 * 1024), 1) . ' MB';
        }

        $formatted = [
            'id' => $document->id,
            'reference' => 'DOC-' . substr($document->id, 0, 6),
            'titre' => $document->title,
            'type' => $mappedType,
            'produit' => $document->documentable_type === 'App\\Models\\Product' ? ($document->documentable->nom ?? 'Produit Associé') : 'Général',
            'version' => 'v' . ($document->version ?? '1.0'),
            'taille' => $sizeFormatted,
            'dateExpiration' => $document->expiration_date ? \Carbon\Carbon::parse($document->expiration_date)->format('Y-m-d') : 'N/A',
            'statut' => $status,
            'paysAutorises' => ['FR', 'CI', 'SN'],
            'documentable_id' => $document->documentable_id,
            'documentable_type' => $document->documentable_type,
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
        if (Auth::user() && Auth::user()->hasRole(['delegue', 'delegue_medical'])) {
            return response()->json(['message' => 'Action non autorisée pour votre rôle.'], 403);
        }
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

        $status = 'VALIDE';
        if (!$document->is_active) $status = 'ARCHIVE';
        if ($document->expiration_date && \Carbon\Carbon::parse($document->expiration_date)->isPast()) {
            $status = 'EXPIRE';
        }

        $sizeFormatted = '1.5 MB';
        if ($document->file_size) {
            $sizeFormatted = round($document->file_size / (1024 * 1024), 1) . ' MB';
        } elseif (\Illuminate\Support\Facades\Storage::disk('public')->exists($document->file_path ?? '')) {
            $bytes = \Illuminate\Support\Facades\Storage::disk('public')->size($document->file_path);
            $sizeFormatted = round($bytes / (1024 * 1024), 1) . ' MB';
        }

        $formatted = [
            'id' => $document->id,
            'reference' => 'DOC-' . substr($document->id, 0, 6),
            'titre' => $document->title,
            'type' => $mappedType,
            'produit' => $document->documentable_type === 'App\\Models\\Product' ? ($document->documentable->nom ?? 'Produit Associé') : 'Général',
            'version' => 'v' . ($document->version ?? '1.0'),
            'taille' => $sizeFormatted,
            'dateExpiration' => $document->expiration_date ? \Carbon\Carbon::parse($document->expiration_date)->format('Y-m-d') : 'N/A',
            'statut' => $status,
            'paysAutorises' => ['FR', 'CI', 'SN'],
            'documentable_id' => $document->documentable_id,
            'documentable_type' => $document->documentable_type,
            'url' => config('app.url') . \Illuminate\Support\Facades\Storage::url(preg_replace('/^\/?storage\//', '', $document->file_path)),
            'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures')
        ];

        return response()->json($formatted);
    }

    public function destroy(Document $document)
    {
        if (Auth::user() && Auth::user()->hasRole(['delegue', 'delegue_medical'])) {
            return response()->json(['message' => 'Action non autorisée pour votre rôle.'], 403);
        }
        $document->delete();
        return response()->json(null, 204);
    }
}
