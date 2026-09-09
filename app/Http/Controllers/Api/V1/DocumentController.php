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

            return [
                'id' => $doc->id,
                'reference' => 'DOC-' . substr($doc->id, 0, 6),
                'titre' => $doc->title,
                'type' => $mappedType,
                'produit' => $doc->documentable_type === 'App\\Models\\Product' ? 'Produit Associé' : 'Général',
                'version' => 'v' . $doc->version,
                'taille' => '2.4 MB', // mock size
                'dateExpiration' => $doc->expiration_date ? \Carbon\Carbon::parse($doc->expiration_date)->format('Y-m-d') : 'N/A',
                'statut' => $status,
                'paysAutorises' => ['FR', 'CI', 'SN'], // mock for now
                'url' => $doc->file_path
            ];
        });
        
        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'documentable_id' => 'required|uuid',
            'documentable_type' => 'required|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:pdf,video,presentation,image,other',
            'file_path' => 'required|string',
            'version' => 'nullable|string|max:50',
            'expiration_date' => 'nullable|date|after:today',
        ]);

        $data['subsidiary_id'] = Auth::user()->subsidiary_id;

        $document = Document::create($data);

        return response()->json($document, 201);
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
        ]);

        $document->update($data);
        return response()->json($document);
    }

    public function destroy(Document $document)
    {
        $document->delete();
        return response()->json(null, 204);
    }
}
