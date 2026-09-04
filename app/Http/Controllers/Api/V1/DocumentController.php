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

        $documents = $query->paginate(15);
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
