<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Render an authenticated user's current view; this endpoint never reads business records. */
class ReportDocumentController extends Controller
{
    public function __invoke(Request $request, ReportDocument $documents)
    {
        $data = $request->validate([
            'format' => 'required|in:xlsx,pdf', 'title' => 'required|string|max:160',
            'filters' => 'present|array|max:40', 'filters.*.label' => 'required|string|max:100',
            'filters.*.value' => 'present|nullable|string|max:1000',
            'summary' => 'present|array|max:30', 'summary.*.label' => 'required|string|max:100',
            'summary.*.value' => 'present|nullable|string|max:1000',
            'sections' => 'required|array|min:1|max:12', 'sections.*.title' => 'required|string|max:160',
            'sections.*.columns' => 'required|array|min:1|max:45',
            'sections.*.columns.*' => 'required|string|max:120',
            'sections.*.rows' => 'present|array|max:20000',
            'sections.*.rows.*' => 'array|max:45',
            'sections.*.rows.*.*' => ['nullable', function ($attribute, $value, $fail) {
                if ((!is_scalar($value) && $value !== null) || (is_string($value) && mb_strlen($value) > 32000)) {
                    $fail('Chaque cellule doit contenir une valeur simple de 32 000 caractères maximum.');
                }
            }],
        ]);
        $cells = 0;
        foreach ($data['sections'] as $section) {
            $cells += count($section['rows']) * count($section['columns']);
            foreach ($section['rows'] as $row) {
                if (count($row) !== count($section['columns']) || !array_is_list($row)) {
                    throw ValidationException::withMessages(['sections' => 'Les colonnes et les cellules du rapport doivent correspondre.']);
                }
            }
        }
        if ($cells > 100000) {
            throw ValidationException::withMessages(['sections' => 'Le rapport dépasse 100 000 cellules. Réduisez la période ou les filtres avant de réessayer.']);
        }
        return $documents->download($request, $data);
    }
}
