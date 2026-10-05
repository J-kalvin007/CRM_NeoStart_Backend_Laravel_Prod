<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClmPresentationAnalytic;
use App\Models\Document;
use Illuminate\Http\Request;
use App\Services\DocumentViewerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        $documents = $query->with('documentable')->get()->map(fn ($doc) => $this->format($doc, $request));

        return response()->json([
            'data' => $documents
        ]);
    }

    /**
     * Documents présentés à un prescripteur : nombre de présentations, date et heure de chacune.
     * Délégué : ses présentations ; hiérarchie : celles de son équipe ; administration : toutes.
     */
    public function presentedToContact(Request $request, string $contact)
    {
        $user = $request->user();
        $isGlobal = $user->can('scope:global') || $user->hasAnyRole(['superadmin', 'super-admin', 'admin']);

        $rows = ClmPresentationAnalytic::with(['document.documentable', 'user'])
            ->where('contact_id', $contact)
            ->whereHas('document')
            ->when(!$isGlobal, fn ($q) => $q->whereIn('user_id', array_merge([$user->id], $user->teamMemberIds())))
            ->get()
            ->sortByDesc(fn ($a) => $a->presented_at ?? $a->created_at);

        $data = $rows->groupBy('document_id')->map(function ($items) use ($request) {
            $first = $items->first();
            return [
                'document' => $this->format($first->document, $request),
                'presentations_count' => $items->count(),
                'last_presented_at' => ($first->presented_at ?? $first->created_at)?->toIso8601String(),
                'presentations' => $items->map(fn ($a) => [
                    'id' => $a->id,
                    'presented_at' => ($a->presented_at ?? $a->created_at)?->toIso8601String(),
                    'duration_seconds' => $a->total_duration_seconds,
                    'slides_count' => is_array($a->slides_data) ? count($a->slides_data) : 0,
                    'completed' => (bool) $a->completed,
                    'activity_id' => $a->activity_id,
                    'delegate_name' => $a->user?->name,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function store(Request $request, DocumentViewerService $viewers)
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
        $viewers->prepare($document);

        return response()->json($this->format($document->fresh('documentable'), $request), 201);
    }

    public function show(Request $request, Document $document)
    {
        return response()->json($this->format($document->load('documentable'), $request));
    }

    public function update(Request $request, Document $document, DocumentViewerService $viewers)
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
        if (isset($data['file_path'])) {
            $viewers->prepare($document);
        }

        return response()->json($this->format($document->fresh('documentable'), $request));
    }

    public function destroy(Document $document)
    {
        if (Auth::user() && Auth::user()->hasRole(['delegue', 'delegue_medical'])) {
            return response()->json(['message' => 'Action non autorisée pour votre rôle.'], 403);
        }
        $document->delete();
        return response()->json(null, 204);
    }

    /** Adresse publique d'un fichier du disque public, joignable par le client qui appelle (et non localhost de APP_URL). */
    private function publicUrl(?string $path, Request $request): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        $relative = DocumentViewerService::relativePath($path);
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));

        // Lien public/storage (php artisan storage:link), indépendant de APP_URL
        return $request->getSchemeAndHttpHost() . '/storage/' . $encoded;
    }

    /** Format renvoyé au web et au mobile : uniquement des valeurs réelles du document. */
    private function format(Document $doc, Request $request): array
    {
        $mappedType = 'NOTICE';
        if ($doc->type === 'presentation') $mappedType = 'E-DETAILING';
        if ($doc->type === 'video') $mappedType = 'VIDEO';
        if ($doc->type === 'pdf') $mappedType = 'BROCHURE';

        $status = 'VALIDE';
        if (!$doc->is_active) $status = 'ARCHIVE';
        if ($doc->expiration_date && \Carbon\Carbon::parse($doc->expiration_date)->isPast()) {
            $status = 'EXPIRE';
        }

        $relative = DocumentViewerService::relativePath($doc->file_path);
        $sizeFormatted = null;
        if ($relative !== '' && Storage::disk('public')->exists($relative)) {
            $sizeFormatted = round(Storage::disk('public')->size($relative) / (1024 * 1024), 1) . ' MB';
        }

        $viewerPath = $doc->viewer_path && Storage::disk('public')->exists($doc->viewer_path) ? $doc->viewer_path : null;
        $viewerExt = DocumentViewerService::extension($viewerPath);
        $expiration = $doc->expiration_date ? \Carbon\Carbon::parse($doc->expiration_date)->format('Y-m-d') : null;

        return [
            'id' => $doc->id,
            'reference' => 'DOC-' . substr($doc->id, 0, 6),
            'titre' => $doc->title,
            'title' => $doc->title,
            'description' => $doc->description,
            'type' => $mappedType,
            'raw_type' => $doc->type,
            // Extension du fichier d'origine : pdf, pptx, ppt, zip (HTML5), mp4, png…
            'format' => DocumentViewerService::extension($doc->file_path),
            'produit' => $doc->documentable_type === 'App\\Models\\Product' ? ($doc->documentable->nom ?? null) : 'Général',
            'version' => 'v' . ($doc->version ?? '1.0'),
            'taille' => $sizeFormatted,
            'dateExpiration' => $expiration,
            'expiration_date' => $expiration,
            'statut' => $status,
            'status' => $doc->status,
            'is_active' => (bool) $doc->is_active,
            'documentable_id' => $doc->documentable_id,
            'documentable_type' => $doc->documentable_type,
            'file_path' => $doc->file_path,
            'url' => $this->publicUrl($doc->file_path, $request),
            // Version affichable sur tablette : PDF converti (PowerPoint) ou page d'entrée HTML5 (zip)
            'viewer_url' => $this->publicUrl($viewerPath, $request),
            'viewer_format' => $viewerPath ? ($viewerExt === 'pdf' ? 'pdf' : 'html') : null,
            'folder' => $mappedType === 'E-DETAILING' ? 'E-Detailing' : ($mappedType === 'VIDEO' ? 'Vidéos' : 'Brochures'),
        ];
    }
}
