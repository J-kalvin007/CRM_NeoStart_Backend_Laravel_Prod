<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportController extends Controller
{
    /**
     * Mapping colonnes Excel → champs internes.
     */
    private const COLUMN_MAP = [
        'nom du produit'               => 'nom',
        'nom'                          => 'nom',
        'produit'                      => 'nom',
        'code produit/reference'       => 'code',
        'code produit'                 => 'code',
        'reference'                    => 'code',
        'code'                         => 'code',
        'pght'                         => 'pght',
        'prix grossiste ht'            => 'pght',
        'prix de cession'              => 'prix_cession',
        'prix de cession fcfa'         => 'prix_cession',
        'cession'                      => 'prix_cession',
        'prix public de vente'         => 'ppv',
        'prix public fcfa'             => 'ppv',
        'ppv'                          => 'ppv',
        'prix public'                  => 'ppv',
        'devise'                       => 'devise',
        'description du produit'       => 'description',
        'description'                  => 'description',
        'dci'                          => 'dci',
        'conditionnement'              => 'conditionnement',
        'indications therapeutiques'   => 'indications',
        'indications'                  => 'indications',
    ];

    // ── Template ──────────────────────────────────────────────────────────

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Nom du produit', 'Code produit/reference', 'PGHT', 'Prix de cession', 
            'Prix Public de Vente', 'Devise', 'Description du produit', 
            'DCI', 'Conditionnement', 'Indications thérapeutiques',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');

        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle("A1:{$lastCol}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF002966']],
        ]);

        // Ligne exemple
        $example = [
            'CEDIN - 300', '1', '5495.80', '7250.00', '9425.00', 'XOF',
            'Cephalospirine 3eme generation', 'Cefdinir Capsules USP 300mg', 
            '1 X 10', 'Prise en charge des infections',
        ];
        
        foreach ($example as $col => $val) {
            $sheet->setCellValue(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '2',
                $val
            );
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            'template_import_produits.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ── Import ────────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'                   => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'mode'                   => 'in:create,update,upsert',
            'organizational_unit_id' => 'required|uuid|exists:organizational_units,id',
            'dry_run'                => 'boolean',
        ]);

        $mode         = $request->input('mode', 'upsert');
        $dryRun       = $request->boolean('dry_run', false);
        $unitId       = $request->input('organizational_unit_id');

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de lire le fichier : ' . $e->getMessage()], 422);
        }

        if (count($rows) < 2) {
            return response()->json(['success' => false, 'message' => 'Le fichier est vide.'], 422);
        }

        // Détecter les en-têtes
        $headerRow = array_map(fn($h) => $this->normalizeStr((string)($h ?? '')), $rows[0]);
        $colIdx    = $this->buildColumnIndex($headerRow);

        if (empty($colIdx)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune colonne reconnue. Vérifiez les en-têtes.',
            ], 422);
        }

        $results  = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        $dataRows = array_slice($rows, 1);

        DB::beginTransaction();
        try {
            foreach ($dataRows as $rowIdx => $row) {
                $lineNum = $rowIdx + 2;
                if ($this->isEmptyRow($row)) { $results['skipped']++; continue; }

                try {
                    $parsed = $this->parseRow($row, $colIdx, $unitId);

                    if (empty($parsed['nom'])) {
                        $results['errors'][] = "Ligne {$lineNum} : nom manquant.";
                        $results['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $results['preview'][] = array_merge(['_ligne' => $lineNum], $parsed);
                        continue;
                    }

                    $action = $this->upsertProduct($parsed, $mode, $unitId);
                    $results[$action]++;
                } catch (\Throwable $e) {
                    $results['errors'][] = "Ligne {$lineNum} : " . $e->getMessage();
                    $results['skipped']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
                return response()->json([
                    'success' => true, 'dry_run' => true,
                    'message' => count($results['preview']) . ' ligne(s) prêtes.',
                    'results' => $results,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Import products échoué', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Import terminé : {$results['created']} créés, {$results['updated']} mis à jour, {$results['skipped']} ignorés.",
            'results' => ['created' => $results['created'], 'updated' => $results['updated'], 'skipped' => $results['skipped'], 'errors' => $results['errors']],
        ]);
    }

    // ── Parsing ───────────────────────────────────────────────────────────

    private function normalizeStr(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/\s+/', ' ', $value);
        return str_replace(
            ['é','è','ê','ë','à','â','ä','ô','ö','ù','û','ü','î','ï','ç','œ','æ'],
            ['e','e','e','e','a','a','a','o','o','u','u','u','i','i','c','oe','ae'],
            $value
        );
    }

    private function buildColumnIndex(array $headers): array
    {
        $index = [];
        foreach ($headers as $colIdx => $header) {
            if ($header === '') continue;
            if (isset(self::COLUMN_MAP[$header]) && !isset($index[self::COLUMN_MAP[$header]])) {
                $index[self::COLUMN_MAP[$header]] = $colIdx;
            }
        }
        return $index;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string)$cell) !== '') return false;
        }
        return true;
    }

    private function getCell(array $row, array $colIdx, string $field): string
    {
        if (!isset($colIdx[$field])) return '';
        return trim((string)($row[$colIdx[$field]] ?? ''));
    }

    private function parseRow(array $row, array $colIdx, string $unitId): array 
    {
        $get  = fn(string $f) => $this->getCell($row, $colIdx, $f);
        $data = [];

        // Nom
        $nom = $get('nom');
        if ($nom !== '') {
            $data['nom'] = $nom;
        }

        // Champs strings
        foreach (['code', 'devise', 'description', 'dci', 'conditionnement', 'indications'] as $f) {
            $val = $get($f);
            if ($val !== '') $data[$f] = $val;
        }

        // Champs décimaux
        foreach (['pght', 'prix_cession', 'ppv'] as $f) {
            $val = $get($f);
            if ($val !== '') {
                // Enlever tous les espaces (y compris insécables) et lettres
                $val = preg_replace('/[^\d,\.]/', '', str_replace(["\xC2\xA0", ' '], '', $val));
                
                // Gérer les séparateurs de milliers/décimales
                if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
                    $lastComma = strrpos($val, ',');
                    $lastDot = strrpos($val, '.');
                    if ($lastComma > $lastDot) {
                        $val = str_replace('.', '', $val);
                        $val = str_replace(',', '.', $val);
                    } else {
                        $val = str_replace(',', '', $val);
                    }
                } elseif (strpos($val, ',') !== false) {
                    $val = str_replace(',', '.', $val);
                }

                if (is_numeric($val)) {
                    $data[$f] = (float)$val;
                }
            }
        }
        
        $data['statut'] = 'actif';
        $data['organizational_unit_id'] = $unitId;

        return $data;
    }

    private function upsertProduct(array $data, string $mode, string $unitId): string
    {
        $existing = null;

        // Chercher par code exact dans l'OU, ou par nom
        if (!empty($data['code'])) {
            $existing = Product::withoutGlobalScopes()
                ->where('organizational_unit_id', $unitId)
                ->where('code', $data['code'])
                ->first();
        }
        
        if (!$existing && !empty($data['nom'])) {
            $existing = Product::withoutGlobalScopes()
                ->where('organizational_unit_id', $unitId)
                ->where('nom', $data['nom'])
                ->first();
        }

        if ($existing) {
            if ($mode === 'create') return 'skipped';
            $existing->update($data);
            return 'updated';
        }

        if ($mode === 'update') return 'skipped';

        Product::create($data);
        return 'created';
    }
}
