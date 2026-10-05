<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\Potential;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AccountImportController extends Controller
{
    /**
     * Mapping colonnes Excel → champs internes.
     * Clé = en-tête normalisé. Valeur = champ ou clé spéciale "_xxx".
     */
    private const COLUMN_MAP = [
        // Identité
        'nom de la pharmacie'       => 'nom',
        'nom pharmacie'             => 'nom',
        'nom etablissement'         => 'nom',
        'nom'                       => 'nom',
        // Type
        'type'                      => '_type',
        "type d'etablissement"      => '_type',
        'type etablissement'        => '_type',
        // Responsable
        'responsable'               => '_responsable',
        'responsable prenom'        => 'responsable_prenom',
        'prenom responsable'        => 'responsable_prenom',
        'nom responsable'           => 'responsable_nom',
        // Délégué
        'delegue'                   => '_delegue',
        'delegue(e)'                => '_delegue',
        'nom du charge de commande' => '_delegue',
        // Contact
        'telephone'                 => 'telephone',
        'tel'                       => 'telephone',
        'whatsapp'                  => 'whatsapp',
        'e-mail'                    => 'email',
        'email'                     => 'email',
        // Localisation
        'ville'                     => 'ville',
        'zone / quartier'           => 'zone_quartier',
        'zone quartier'             => 'zone_quartier',
        'zone'                      => 'zone_quartier',
        'adresse'                   => 'adresse',
        'coordonnees gps'           => '_gps',
        'gps'                       => '_gps',
        // Commercial
        'potentiel commande / mois' => 'potentiel_commande_mois',
        'potentiel commande'        => 'potentiel_commande_mois',
        'potentiel'                 => 'potentiel_commande_mois',
        'grossiste principal'       => 'grossiste_principal',
        'grossiste'                 => 'grossiste_principal',
        // Statut
        'statut de la relation'     => 'statut_relation',
        'statut relation'           => 'statut_relation',
        'statut'                    => 'statut_relation',
        // Produits (colonnes booléennes Oui/Non)
        'cedin 125'                 => '_prod_CEDIN_125',
        'cedin 250'                 => '_prod_CEDIN_250',
        'cedin 300'                 => '_prod_CEDIN_300',
        'sanaxone t 1000'           => '_prod_SANAXONE_T_1000',
        'sanaxone 1000'             => '_prod_SANAXONE_T_1000',
        'sanpime'                   => '_prod_SANPIME',
        'sanix 200'                 => '_prod_SANIX_200',
        'sanix ds 100'              => '_prod_SANIX_DS_100',
    ];

    // ── Template ──────────────────────────────────────────────────────────

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Nom de la pharmacie', 'Type', 'Responsable', 'Téléphone', 'WhatsApp',
            'E-mail', 'Ville', 'Zone / quartier', 'Adresse', 'Coordonnées GPS',
            'Potentiel commande / mois', 'Grossiste principal',
            'CEDIN 125', 'CEDIN 250', 'CEDIN 300', 'SANAXONE T 1000',
            'SANPIME', 'SANIX 200', 'SANIX DS 100', 'Statut de la relation',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Établissements');

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
            'PHARMACIE SAINT ESPRIT', 'Officine indépendante', 'Sophie',
            '92281511', '92281511', 'saintesprit@pharma.tg',
            'Lomé', 'Agoe Koffi Panou', 'Rue des Pharmacies',
            '6.1375,1.2123', '8', 'UBIPHARM',
            'Oui', 'Oui', 'Oui', 'Oui', 'Oui', 'Oui', 'Oui', 'Actif',
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
            'template_import_etablissements.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ── Import ────────────────────────────────────────────────────────────

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'                   => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'mode'                   => 'in:create,update,upsert',
            'assign_to_user'         => 'nullable|uuid|exists:users,id',
            'country_id'             => 'nullable|uuid|exists:countries,id',
            'dry_run'                => 'boolean',
        ]);

        $mode         = $request->input('mode', 'upsert');
        $dryRun       = $request->boolean('dry_run', false);
        $assignUserId = $request->input('assign_to_user') ?? auth()->id();
        $countryId    = $request->input('country_id');

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
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

        // Caches
        $accountTypes = $this->buildAccountTypeCache();
        $potentials   = $this->buildPotentialCache();
        $users        = User::pluck('id', 'name')->toArray();

        $results  = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        $dataRows = array_slice($rows, 1);

        DB::beginTransaction();
        try {
            foreach ($dataRows as $rowIdx => $row) {
                $lineNum = $rowIdx + 2;
                if ($this->isEmptyRow($row)) { $results['skipped']++; continue; }

                try {
                    $parsed = $this->parseRow(
                        $row, $colIdx, $accountTypes, $potentials, $users,
                        $assignUserId, $countryId, $dryRun
                    );

                    if (empty($parsed['nom'])) {
                        $results['errors'][] = "Ligne {$lineNum} : nom manquant.";
                        $results['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $results['preview'][] = array_merge(['_ligne' => $lineNum], $parsed);
                        continue;
                    }

                    $action = $this->upsertAccount($parsed, $mode);
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
            Log::error('Import accounts échoué', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Import terminé : {$results['created']} créés, {$results['updated']} mis à jour, {$results['skipped']} ignorés.",
            'results' => ['created' => $results['created'], 'updated' => $results['updated'], 'skipped' => $results['skipped'], 'errors' => $results['errors']],
        ]);
    }

    // ── Caches ────────────────────────────────────────────────────────────

    private function buildAccountTypeCache(): array
    {
        return AccountType::all()->mapWithKeys(fn($t) => [
            $this->normalizeStr($t->libelle) => $t->id
        ])->toArray();
    }

    private function buildPotentialCache(): array
    {
        $defaults = [
            'LOW'  => ['name' => 'Faible'],
            'MED'  => ['name' => 'Moyen'],
            'HIGH' => ['name' => 'Fort'],
        ];
        $map = [];
        foreach ($defaults as $code => $attrs) {
            $pot = Potential::firstOrCreate(['code' => $code], array_merge($attrs, ['is_active' => true]));
            $map[$code] = $pot->id;
        }
        return $map;
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

    private function parseRow(
        array $row, array $colIdx,
        array &$accountTypes, array $potentials, array $users,
        ?string $defaultUserId, ?string $countryId,
        bool $dryRun
    ): array {
        $get  = fn(string $f) => $this->getCell($row, $colIdx, $f);
        $data = [];

        // ── Nom (en majuscules) ───────────────────────────────────────────
        $nom = $get('nom');
        if ($nom !== '') {
            $data['nom'] = mb_strtoupper(trim($nom), 'UTF-8');
        }

        // ── Champs directs ────────────────────────────────────────────────
        foreach (['ville', 'zone_quartier', 'adresse', 'telephone', 'whatsapp', 'email',
                  'grossiste_principal', 'responsable_prenom', 'responsable_nom'] as $f) {
            $val = $get($f);
            if ($val !== '') $data[$f] = $val;
        }

        // ── Responsable (colonne combinée) ────────────────────────────────
        if (isset($colIdx['_responsable'])) {
            $resp = $this->getCell($row, $colIdx, '_responsable');
            if ($resp !== '') {
                $data['responsable']        = $resp;
                $data['responsable_prenom'] = $resp; // fallback
            }
        }

        // ── Potentiel commande (entier) ───────────────────────────────────
        $potCom = $get('potentiel_commande_mois');
        if (is_numeric($potCom)) $data['potentiel_commande_mois'] = (int)$potCom;

        // ── Type d'établissement → account_type_id ────────────────────────
        if (isset($colIdx['_type'])) {
            $typeRaw = $this->getCell($row, $colIdx, '_type');
            if ($typeRaw !== '') {
                $typeNorm = $this->normalizeStr($typeRaw);
                if (isset($accountTypes[$typeNorm])) {
                    $data['account_type_id'] = $accountTypes[$typeNorm];
                } elseif (!$dryRun) {
                    $type = $this->findOrCreateAccountType($typeRaw);
                    $accountTypes[$typeNorm] = $type->id;
                    $data['account_type_id'] = $type->id;
                } else {
                    $data['meta']['type_label'] = $typeRaw;
                }
            }
        }

        // ── Produits disponibles (Oui/Non → JSON) ─────────────────────────
        $produits = [];
        foreach ($colIdx as $field => $colIdxVal) {
            if (str_starts_with($field, '_prod_')) {
                $productKey = substr($field, 6); // retire "_prod_"
                $val        = $this->getCell($row, $colIdx, $field);
                if ($val !== '') {
                    $produits[$productKey] = $this->parseBool($val);
                }
            }
        }
        if (!empty($produits)) $data['produits_disponibles'] = $produits;

        // ── GPS ────────────────────────────────────────────────────────────
        if (isset($colIdx['_gps'])) {
            $gps = $this->getCell($row, $colIdx, '_gps');
            if ($gps !== '') {
                $parts = explode(',', $gps);
                if (count($parts) === 2) {
                    $data['gps_lat'] = (float)trim($parts[0]);
                    $data['gps_lng'] = (float)trim($parts[1]);
                }
            }
        }

        // ── Statut relation ────────────────────────────────────────────────
        $statutRaw = $get('statut_relation');
        if ($statutRaw !== '') {
            $data['statut_relation'] = $statutRaw;
            $statutMap = [
                'actif'       => 'actif',
                'active'      => 'actif',
                'a contacter' => 'en_attente',
                'inactif'     => 'inactif',
            ];
            $data['statut'] = $statutMap[$this->normalizeStr($statutRaw)] ?? 'actif';
        } else {
            $data['statut'] = 'actif';
        }

        // ── Délégué → delegue_id ──────────────────────────────────────────
        $delegueRaw = isset($colIdx['_delegue'])
            ? $this->getCell($row, $colIdx, '_delegue')
            : '';

        // Le délégué lié est CELUI SÉLECTIONNÉ au moment de l'import
        // (assign_to_user), pas l'admin connecté
        $data['delegue_id'] = $this->findUserId($delegueRaw, $users) ?? $defaultUserId;
        $data['user_id']    = auth()->id(); // auteur de la création

        // ── Périmètre ─────────────────────────────────────────────────────
        if ($countryId) {
            $data['country_id'] = $countryId;
        }

        $data['statut_validation'] = 'valide';

        return $data;
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function findOrCreateAccountType(string $rawLabel): AccountType
    {
        $normalized = $this->normalizeStr($rawLabel);
        $capitalized = mb_strtolower(trim($rawLabel), 'UTF-8');
        $capitalized = mb_strtoupper(mb_substr($capitalized, 0, 1, 'UTF-8'), 'UTF-8')
                     . mb_substr($capitalized, 1, null, 'UTF-8');

        $existing = AccountType::all()->first(fn($t) =>
            $this->normalizeStr($t->libelle) === $normalized
        );
        if ($existing) return $existing;

        // Générer un code unique
        $words   = preg_split('/\s+/', strtoupper(trim($rawLabel)));
        $acronym = implode('_', array_map(fn($w) => preg_replace('/[^A-Z0-9]/', '', $w), $words));
        $code    = substr(preg_replace('/_+/', '_', trim($acronym, '_')), 0, 20) ?: 'ETAB';

        $baseCode = $code;
        $i = 1;
        while (AccountType::where('code', $code)->exists()) {
            $code = $baseCode . '_' . $i++;
        }

        return AccountType::create([
            'libelle'   => $capitalized,
            'code'      => $code,
            'is_active' => true,
            'statut'    => 'actif',
            'couleur'   => '#0066FF',
        ]);
    }

    private function findUserId(string $name, array $users): ?string
    {
        if (empty(trim($name))) return null;
        $norm = $this->normalizeStr($name);
        foreach ($users as $userName => $id) {
            if ($this->normalizeStr($userName) === $norm) return $id;
        }
        foreach ($users as $userName => $id) {
            if (str_contains($this->normalizeStr($userName), $norm)) return $id;
        }
        $user = User::where('name', 'like', "%{$name}%")
            ->orWhere('email', 'like', "%{$name}%")
            ->first();
        return $user?->id;
    }

    private function parseBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['oui', 'yes', '1', 'true', 'vrai', 'x'], true);
    }

    private function upsertAccount(array $data, string $mode): string
    {
        $existing = null;

        // Chercher par nom exact
        if (!empty($data['nom'])) {
            $q = Account::withoutGlobalScopes()->where('nom', $data['nom']);
            if (!empty($data['ville'])) $q->where('ville', $data['ville']);
            $existing = $q->first();
        }

        if ($existing) {
            if ($mode === 'create') return 'skipped';
            if (!empty($data['produits_disponibles'])) {
                $data['produits_disponibles'] = array_merge(
                    $existing->produits_disponibles ?? [],
                    $data['produits_disponibles']
                );
            }
            $existing->update($data);
            return 'updated';
        }

        if ($mode === 'update') return 'skipped';

        Account::create($data);
        return 'created';
    }
}
