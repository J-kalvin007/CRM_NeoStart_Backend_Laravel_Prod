<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>{{ $report['title'] }}</title>
<style>
@page { margin: 30px 28px 38px; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #243247; }
h1 { color: #002966; font-size: 19px; margin-bottom: 6px; } h2 { color: #002966; font-size: 13px; margin: 18px 0 8px; }
.meta { color: #58677b; font-size: 9px; } .brand { font-size: 10px; color: #0066ff; font-weight: bold; }
.context { border-top: 2px solid #0066ff; padding: 9px 0; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } thead { display: table-header-group; }
th { background: #002966; color: white; padding: 7px 5px; text-align: left; }
td { padding: 6px 5px; border-bottom: 1px solid #e2e8f0; vertical-align: top; word-wrap: break-word; }
tr:nth-child(even) td { background: #f4f7fb; } .record-title { padding: 6px; background: #edf3ff; color: #002966; page-break-after: avoid; font-weight: bold; }
p.field { margin: 0; padding: 4px 6px; white-space: pre-wrap; overflow-wrap: break-word; } .muted { color: #67758a; }
</style></head><body>
<div class="brand">CRM NEO MEDLINK — RAPPORT DÉTAILLÉ</div>
<h1>{{ $report['title'] }}</h1>
<div class="meta">Édité le {{ $report['generated_at'] }} par {{ $report['author'] }} · {{ $report['country'] }}</div>
<div class="context"><strong>Périmètre :</strong> données de la vue filtrée au moment de l’export.
@foreach ($report['filters'] as $filter)<div><strong>{{ $filter['label'] }} :</strong> {{ $filter['value'] ?? '—' }}</div>@endforeach
</div>
@if(count($report['summary']))<h2>Synthèse</h2>@foreach ($report['summary'] as $entry)<div><strong>{{ $entry['label'] }} :</strong> {{ $entry['value'] ?? '—' }}</div>@endforeach @endif
@foreach ($report['sections'] as $section)
<h2>{{ $section['title'] }} — {{ count($section['rows']) }} ligne(s)</h2>
@if(!count($section['rows']))<p class="muted">Aucune donnée pour les filtres sélectionnés.</p>
@else
@php($detailed = count($section['columns']) > 8 || collect($section['rows'])->contains(fn ($row) => collect($row)->contains(fn ($value) => mb_strlen((string)$value) > 400)))
@if($detailed)
@foreach ($section['rows'] as $row)
<div class="record-title">Fiche {{ $loop->iteration }}</div>
@foreach ($section['columns'] as $index => $column)<p class="field"><strong>{{ $column }} :</strong> {{ is_bool($row[$index]) ? ($row[$index] ? 'Oui' : 'Non') : ($row[$index] ?? '—') }}</p>@endforeach
@endforeach
@else
<table><thead><tr>@foreach ($section['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead><tbody>
@foreach ($section['rows'] as $row)<tr>@foreach ($row as $value)<td>{{ is_bool($value) ? ($value ? 'Oui' : 'Non') : ($value ?? '—') }}</td>@endforeach</tr>@endforeach
</tbody></table>
@endif
@endif
@endforeach
</body></html>
