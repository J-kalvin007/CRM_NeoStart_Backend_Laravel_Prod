<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Account;
use App\Models\Speciality;
use App\Models\Potential;
use App\Models\ContactType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactImportController extends Controller
{
    /**
     * Mapping colonnes Excel → champs internes.
     * Clé = en-tête normalisé (minuscules, trim, sans doubles espaces)
     * Valeur = champ interne ou clé spéciale préfixée "_"
     */
    private const COLUMN_MAP = [
        'id prescripteur'              => 'code_externe',
        'id_prescripteur'              => 'code_externe',
        'code'                         => 'code_externe',
        'délégué(e)'                   => '_delegue',
        'delegue(e)'                   => '_delegue',
        'delegue'                      => '_delegue',
        'nom et prénom'                => '_nom_prenom',
        'nom et prenom'                => '_nom_prenom',
        'nom'                          => 'nom',
        'prénom'                       => 'prenom',
        'prenom'                       => 'prenom',
        'spécialité'                   => '_specialite',
        'specialite'                   => '_specialite',
        'type de structure'            => '_type_structure',
        'nom de la structure'          => '_nom_structure',
        'ville'                        => 'ville',
        'zone / quartier'              => 'zone_quartier',
        'zone quartier'                => 'zone_quartier',
        'adresse'                      => 'adresse',
        'téléphone'                    => 'telephone',
        'telephone'                    => 'telephone',
        'whatsapp'                     => 'whatsapp',
        'e-mail'                       => 'email',
        'email'                        => 'email',
        'patients cibles / mois'       => 'nb_patients_cible_mois',
        'patients cibles'              => 'nb_patients_cible_mois',
        'potentiel (auto)'             => '_potentiel',
        'potentiel'                    => '_potentiel',
        'visites recommandées / mois'  => 'nb_visites_recommandees_mois',
        'visites recommandees / mois'  => 'nb_visites_recommandees_mois',
        'visites recommandées'         => 'nb_visites_recommandees_mois',
        'produit prioritaire'          => 'produit_prioritaire',
        'produit secondaire'           => 'produit_secondaire',
        "autre produit d'intérêt"      => 'autre_produit_interet',
        "autre produit d'interet"      => 'autre_produit_interet',
        'autre produit'                => 'autre_produit_interet',
        'canal préféré'                => 'canal_prefere',
        'canal prefere'                => 'canal_prefere',
        'statut de la relation'        => 'statut_relation',
        'statut relation'              => 'statut_relation',
        'statut'                       => 'statut_relation',
    ];

    // ── API : télécharger le template ─────────────────────────────────────

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'ID Prescripteur', 'Délégué(e)', 'Nom et prénom', 'Spécialité',
            'Type de structure', 'Nom de la structure', 'Ville', 'Zone / quartier',
            'Adresse', 'Téléphone', 'WhatsApp', 'E-mail',
            'Patients cibles / mois', 'Potentiel (auto)',
            'Visites recommandées / mois', 'Produit prioritaire',
            'Produit secondaire', "Autre produit d'intérêt",
            'Canal préféré', 'Statut de la relation',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Prescripteurs');

        foreach ($headers as $col => $header) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getColumnDimensionByColumn($col + 1)->setAutoSize(true);
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle("A1:{$lastCol}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF0066FF']],
        ]);

        $example = [
            'DIV-P-001', 'Jean Martin', 'KANTE Ibrahim', 'Pédiatre',
            'Hôpital privé', 'Clinique Exemple', 'Lomé', 'Bè',
            'Rue des Hôpitaux', '93022031', '93022031', 'kante@exemple.com',
            '20', 'Moyen', '2', 'CEDIN 125', 'CEDIN 250', 'SANAXONE T 1000',
            'Visite physique', 'Actif',
        ];
        foreach ($example as $col => $val) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . '2';
            $sheet->setCellValue($cell, $val);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        return response()->streamDownload(
            fn() => $writer->save('php://output'),
            'template_import_prescripteurs.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ── API : importer ────────────────────────────────────────────────────

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
        $countryId    = $request->input('country_id') ?? auth()->user()->country_id;

        // ── Lecture du fichier ─────────────────────────────────────────────
        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Impossible de lire le fichier : ' . $e->getMessage()], 422);
        }

        if (count($rows) < 2) {
            return response()->json(['success' => false, 'message' => 'Le fichier est vide ou sans données.'], 422);
        }

        // ── Détection des en-têtes ─────────────────────────────────────────
        $headerRow = array_map(fn($h) => $this->normalizeStr((string) ($h ?? '')), $rows[0]);
        $colIdx    = $this->buildColumnIndex($headerRow);

        if (empty($colIdx)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune colonne reconnue. Vérifiez que les en-têtes correspondent au template.',
            ], 422);
        }

        // ── Caches de lookup (peuplés / mis à jour au fil de l'import) ─────
        // Speciality: clé = name normalisé, valeur = id
        $specialities = $this->buildSpecialityCache();
        // Potential: clé = code (LOW/MED/HIGH), valeur = id
        $potentials   = $this->buildPotentialCache();
        // Account: clé = nom normalisé, valeur = id
        $accounts     = Account::withoutGlobalScopes()->pluck('id', 'nom')->toArray();
        // Users: clé = name normalisé, valeur = id
        $users        = User::pluck('id', 'name')->toArray();



        // ── Traitement ligne par ligne ─────────────────────────────────────
        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        $dataRows = array_slice($rows, 1);

        DB::beginTransaction();
        try {
            foreach ($dataRows as $rowIdx => $row) {
                $lineNum = $rowIdx + 2;
                if ($this->isEmptyRow($row)) { $results['skipped']++; continue; }

                try {
                    $parsed = $this->parseRow(
                        $row, $colIdx,
                        $specialities, $potentials, $accounts, $users,
                        $assignUserId, $countryId,
                        $dryRun // en dry_run on ne crée pas les entrées manquantes
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

                    $action = $this->upsertContact($parsed, $mode);
                    
                    if ($action === 'skipped_exists') {
                        $results['skipped']++;
                        $results['errors'][] = "Ligne {$lineNum} : ignorée (déjà existante, et mode création sélectionné).";
                    } elseif ($action === 'skipped_not_exists') {
                        $results['skipped']++;
                        $results['errors'][] = "Ligne {$lineNum} : ignorée (n'existe pas, et mode mise à jour sélectionné).";
                    } else {
                        $results[$action]++;
                    }

                } catch (\Throwable $e) {
                    $results['errors'][] = "Ligne {$lineNum} : " . $e->getMessage();
                    $results['skipped']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
                return response()->json([
                    'success'  => true,
                    'dry_run'  => true,
                    'message'  => count($results['preview']) . ' ligne(s) prêtes à importer.',
                    'results'  => $results,
                ]);
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Import contacts échoué', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Import terminé : {$results['created']} créés, {$results['updated']} mis à jour, {$results['skipped']} ignorés.",
            'results' => ['created' => $results['created'], 'updated' => $results['updated'], 'skipped' => $results['skipped'], 'errors' => $results['errors']],
        ]);
    }

    // ── Helpers : caches ──────────────────────────────────────────────────

    /**
     * Charge le cache des spécialités sous forme [name_normalisé => id].
     */
    private function buildSpecialityCache(): array
    {
        return Speciality::all()->mapWithKeys(fn($s) => [
            $this->normalizeStr($s->name) => $s->id
        ])->toArray();
    }

    /**
     * Charge ou crée les 3 potentiels standards (LOW / MED / HIGH).
     * Retourne [code => id].
     */
    private function buildPotentialCache(): array
    {
        $defaults = [
            'LOW' => ['name' => 'Faible',  'description' => 'Potentiel faible'],
            'MED' => ['name' => 'Moyen',   'description' => 'Potentiel moyen'],
            'HIGH'=> ['name' => 'Fort',    'description' => 'Potentiel fort'],
        ];

        $map = [];
        foreach ($defaults as $code => $attrs) {
            $pot = Potential::firstOrCreate(
                ['code' => $code],
                array_merge($attrs, ['is_active' => true])
            );
            $map[$code] = $pot->id;
        }
        return $map;
    }

    // ── Helpers : parsing ─────────────────────────────────────────────────

    private function normalizeStr(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        // Retirer les accents pour la comparaison
        $value = str_replace(
            ['é','è','ê','ë','à','â','ä','ô','ö','ù','û','ü','î','ï','ç','œ','æ'],
            ['e','e','e','e','a','a','a','o','o','u','u','u','i','i','c','oe','ae'],
            $value
        );
        return $value;
    }

    private function buildColumnIndex(array $normalizedHeaders): array
    {
        $index = [];
        foreach ($normalizedHeaders as $colIdx => $header) {
            if ($header === '') continue;
            if (isset(self::COLUMN_MAP[$header])) {
                $field = self::COLUMN_MAP[$header];
                if (!isset($index[$field])) { // première occurrence gagne
                    $index[$field] = $colIdx;
                }
            }
        }
        return $index;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') return false;
        }
        return true;
    }

    private function get(array $row, array $colIdx, string $field): string
    {
        if (!isset($colIdx[$field])) return '';
        $val = $row[$colIdx[$field]] ?? '';
        return trim((string) $val);
    }

    private function parseRow(
        array $row,
        array $colIdx,
        array &$specialities,
        array $potentials,
        array &$accounts,
        array $users,
        ?string $defaultUserId,
        ?string $countryId,
        bool $dryRun = false
    ): array {
        $data = [];
        $get  = fn(string $f) => $this->get($row, $colIdx, $f);

        // ── Champs directs ────────────────────────────────────────────────
        foreach ([
            'code_externe', 'ville', 'zone_quartier', 'adresse',
            'telephone', 'whatsapp', 'email',
            'produit_prioritaire', 'produit_secondaire', 'autre_produit_interet',
            'canal_prefere',
        ] as $field) {
            $val = $get($field);
            if ($val !== '') $data[$field] = $val;
        }

        // ── Statut relation ────────────────────────────────────────────────
        $statutRaw = $get('statut_relation');
        if ($statutRaw !== '') {
            $data['statut_relation'] = $statutRaw;
            $statutMap = [
                'actif'       => 'actif',
                'active'      => 'actif',
                'a contacter' => 'actif',   // "À contacter" = actif dans le CRM
                'inactif'     => 'inactif',
                'perdu'       => 'archive',
            ];
            $data['statut'] = $statutMap[$this->normalizeStr($statutRaw)] ?? 'actif';
        } else {
            // Par défaut lors d'un import → actif (pas en attente)
            $data['statut'] = 'actif';
        }

        // ── Entiers ────────────────────────────────────────────────────────
        $p = $get('nb_patients_cible_mois');
        if (is_numeric($p)) $data['nb_patients_cible_mois'] = (int) $p;

        $v = $get('nb_visites_recommandees_mois');
        if (is_numeric($v)) $data['nb_visites_recommandees_mois'] = (int) $v;

        // ── Nom + Prénom ───────────────────────────────────────────────────
        if (isset($colIdx['_nom_prenom'])) {
            $raw = $this->get($row, $colIdx, '_nom_prenom');
            [$data['prenom'], $data['nom']] = $this->splitNomPrenom($raw);
        } else {
            $n = $get('nom');   if ($n !== '') $data['nom']   = $n;
            $pr= $get('prenom');if ($pr!== '') $data['prenom'] = $pr;
        }

        if (empty($data['nom']) && !empty($data['prenom'])) {
            $data['nom']   = $data['prenom'];
            $data['prenom'] = '';
        }

        // ── Spécialité ─────────────────────────────────────────────────────
        $specRaw = isset($colIdx['_specialite'])
            ? trim((string) ($row[$colIdx['_specialite']] ?? ''))
            : '';

        if ($specRaw !== '') {
            $specNorm = $this->normalizeStr($specRaw);
            if (isset($specialities[$specNorm])) {
                $data['speciality_id'] = $specialities[$specNorm];
            } elseif (!$dryRun) {
                // Créer la spécialité si elle n'existe pas
                $spec = $this->findOrCreateSpeciality($specRaw);
                $specialities[$specNorm] = $spec->id;
                $data['speciality_id']   = $spec->id;
            } else {
                // En dry_run, stocker dans meta pour ne pas casser
                $data['meta']['specialite'] = $specRaw;
            }
            $data['titre'] = $this->inferTitre($specRaw);
        }

        // ── Potentiel ──────────────────────────────────────────────────────
        $potRaw = isset($colIdx['_potentiel'])
            ? trim((string) ($row[$colIdx['_potentiel']] ?? ''))
            : '';

        if ($potRaw !== '') {
            $potMap  = [
                'faible' => 'LOW', 'low'    => 'LOW',
                'moyen'  => 'MED', 'medium' => 'MED', 'med' => 'MED',
                'fort'   => 'HIGH','high'   => 'HIGH',
            ];
            $potCode = $potMap[$this->normalizeStr($potRaw)] ?? null;
            if ($potCode && isset($potentials[$potCode])) {
                $data['potential_id'] = $potentials[$potCode];
            }
        }

        // ── Établissement (Account) ────────────────────────────────────────
        $typeStructure = isset($colIdx['_type_structure'])
            ? trim((string) ($row[$colIdx['_type_structure']] ?? ''))
            : '';
        $nomStructure  = isset($colIdx['_nom_structure'])
            ? trim((string) ($row[$colIdx['_nom_structure']] ?? ''))
            : '';

        if ($nomStructure !== '' && !$dryRun) {
            $accountId = $this->findOrCreateAccount(
                $nomStructure, $typeStructure,
                $data['ville'] ?? null,
                $accounts,
                $countryId
            );
            if ($accountId) {
                $data['account_id'] = $accountId;
            }
        }

        // ── Délégué ────────────────────────────────────────────────────────
        $delegueRaw = isset($colIdx['_delegue'])
            ? trim((string) ($row[$colIdx['_delegue']] ?? ''))
            : '';

        $data['user_id'] = $delegueRaw !== ''
            ? ($this->findUserId($delegueRaw, $users) ?? $defaultUserId)
            : $defaultUserId;

        $data['statut_validation'] = 'valide';

        // ── Périmètre géographique ────────────────────────────────────────
        if ($countryId) {
            $data['country_id'] = $countryId;
        }

        return $data;
    }

    // ── Helpers : lookup / création ───────────────────────────────────────

    /**
     * Trouve ou crée une spécialité.
     * Compare en ignorant casse, accents et espaces multiples.
     */
    private function findOrCreateSpeciality(string $rawName): Speciality
    {
        $normalized = $this->normalizeStr($rawName);
        $capitalized = mb_strtolower(trim($rawName), 'UTF-8');
        $capitalized = mb_strtoupper(mb_substr($capitalized, 0, 1, 'UTF-8'), 'UTF-8')
                     . mb_substr($capitalized, 1, null, 'UTF-8');

        // Cherche toutes les spécialités actives et compare en PHP pour gérer les accents
        $existing = Speciality::all()->first(function ($s) use ($normalized) {
            return $this->normalizeStr($s->name) === $normalized;
        });

        if ($existing) return $existing;

        // Générer un code unique à partir du nom
        $words   = preg_split('/\s+/', strtoupper(trim($rawName)));
        $acronym = implode('_', array_map(fn($w) => preg_replace('/[^A-Z0-9]/', '', $w), $words));
        $code    = substr(preg_replace('/_+/', '_', trim($acronym, '_')), 0, 30) ?: 'SPEC';

        // S'assurer que le code est unique
        $baseCode = $code;
        $i = 1;
        while (Speciality::where('code', $code)->exists()) {
            $code = $baseCode . '_' . $i++;
        }

        return Speciality::create([
            'name'      => $capitalized,
            'code'      => $code,
            'is_active' => true,
        ]);
    }

    /**
     * Trouve ou crée un Account (établissement de santé).
     * Vérifie avec normalisation pour éviter les doublons.
     */
    private function findOrCreateAccount(string $nom, string $type, ?string $ville, array &$accounts, ?string $countryId = null): ?string
    {
        $normNom = $this->normalizeStr($nom);

        // Cherche dans le cache local d'abord
        foreach ($accounts as $cachedNom => $id) {
            if ($this->normalizeStr($cachedNom) === $normNom) return $id;
        }

        // Cherche en DB (comparaison large)
        $existing = Account::withoutGlobalScopes()
            ->whereRaw('LOWER(TRIM(nom)) = ?', [strtolower(trim($nom))])
            ->first();

        if (!$existing) {
            // Recherche plus souple (contient)
            $existing = Account::withoutGlobalScopes()
                ->where('nom', 'like', '%' . trim($nom) . '%')
                ->first();
        }

        if ($existing) {
            // Mettre à jour le périmètre si non défini et qu'on en a un
            if ($countryId && empty($existing->country_id)) {
                $existing->update(['country_id' => $countryId]);
            }
            $accounts[$nom] = $existing->id;
            return $existing->id;
        }

        // Créer un nouveau compte
        try {
            $nomMaj = mb_strtoupper(trim($nom), 'UTF-8'); // Nom en majuscules
            $account = Account::create([
                'nom'                    => $nomMaj,
                'type'                   => $type ?: 'Établissement de santé',
                'ville'                  => $ville,
                'statut'                 => 'actif',
                'user_id'                => auth()->id(),
                'statut_validation'      => 'valide',
                'country_id'             => $countryId,
            ]);
            $accounts[$nom]    = $account->id;
            $accounts[$nomMaj] = $account->id; // aussi dans le cache en majuscules
            return $account->id;
        } catch (\Throwable $e) {
            Log::warning("Impossible de créer l'account '{$nom}' : " . $e->getMessage());
            return null;
        }
    }

    private function findUserId(string $name, array $users): ?string
    {
        if (empty(trim($name))) return null;

        $norm = $this->normalizeStr($name);

        // Correspondance exacte d'abord
        foreach ($users as $userName => $id) {
            if ($this->normalizeStr($userName) === $norm) return $id;
        }

        // Correspondance partielle (contient)
        foreach ($users as $userName => $id) {
            if (str_contains($this->normalizeStr($userName), $norm)) return $id;
        }

        // Recherche par email ou partie du nom dans la DB
        $user = User::where('name', 'like', "%{$name}%")
            ->orWhere('email', 'like', "%{$name}%")
            ->first();

        return $user?->id;
    }

    private function splitNomPrenom(string $value): array
    {
        $value = trim($value);
        if (empty($value)) return ['', ''];
        $parts = preg_split('/\s+/', $value, 2);
        if (count($parts) === 1) return ['', $parts[0]];
        // Heuristique : premier mot en MAJUSCULES → c'est le NOM
        if ($parts[0] === strtoupper($parts[0]) && preg_match('/[A-Za-z]/', $parts[0])) {
            return [$parts[1], $parts[0]]; // [prenom, nom]
        }
        return [$parts[0], $parts[1]];
    }

    private function inferTitre(string $specialite): string
    {
        $spec = $this->normalizeStr($specialite);
        if (str_contains($spec, 'professeur') || str_starts_with($spec, 'pr')) return 'Pr.';
        if (str_contains($spec, 'infirmier') || str_contains($spec, 'infirmiere')
            || str_contains($spec, 'ide') || str_contains($spec, 'assistante')
            || str_contains($spec, 'assistant') || str_contains($spec, 'aep')
            || str_contains($spec, 'am ') || $spec === 'am') return '';
        return 'Dr.';
    }

    private function upsertContact(array $data, string $mode): string
    {
        $existing = null;

        if (!empty($data['code_externe'])) {
            $query = Contact::withoutGlobalScopes()->where('code_externe', $data['code_externe']);
            if (!empty($data['user_id'])) {
                $query->where('user_id', $data['user_id']);
            }
            $existing = $query->first();
        }

        if (!$existing && !empty($data['nom'])) {
            $q = Contact::withoutGlobalScopes()->where('nom', $data['nom']);
            if (!empty($data['account_id'])) $q->where('account_id', $data['account_id']);
            if (!empty($data['user_id']))    $q->where('user_id', $data['user_id']);
            $existing = $q->first();
        }

        if ($existing) {
            if ($mode === 'create') return 'skipped_exists';
            if (!empty($data['meta'])) {
                $data['meta'] = array_merge($existing->meta ?? [], $data['meta']);
            }
            $existing->update($data);
            return 'updated';
        }

        if ($mode === 'update') return 'skipped_not_exists';

        Contact::create($data);
        return 'created';
    }
}

