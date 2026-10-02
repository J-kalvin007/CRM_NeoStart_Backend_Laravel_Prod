<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Prépare la version affichable d'un document e-detailing sur tablette :
 * - PowerPoint / Keynote / OpenDocument (ppt, pptx, pps, ppsx, odp, key) : conversion en PDF
 *   (LibreOffice headless, binaire configurable par LIBREOFFICE_BINARY) pour un affichage diapositive par diapositive ;
 * - paquet HTML5 (zip) : extraction et repérage de la page d'entrée (index.html).
 * Les autres formats (pdf, vidéo, image, html) s'affichent tels quels.
 */
class DocumentViewerService
{
    public const CONVERTIBLE = ['ppt', 'pptx', 'pps', 'ppsx', 'pot', 'potx', 'odp', 'key'];

    public static function relativePath(?string $path): string
    {
        return preg_replace('/^\/?storage\//', '', $path ?? '');
    }

    public static function extension(?string $path): string
    {
        return strtolower(pathinfo(parse_url($path ?? '', PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    }

    /** Calcule viewer_path ; renvoie true si une version affichable a été produite. */
    public function prepare(Document $document): bool
    {
        $relative = self::relativePath($document->file_path);
        $ext = self::extension($relative);
        $disk = Storage::disk('public');

        $viewer = null;
        if ($relative !== '' && !preg_match('#^https?://#', $relative) && $disk->exists($relative)) {
            if (in_array($ext, self::CONVERTIBLE, true)) {
                $viewer = $this->convertToPdf($document, $disk->path($relative));
            } elseif ($ext === 'zip') {
                $viewer = $this->extractHtmlPackage($document, $disk->path($relative));
            }
        }

        $document->forceFill(['viewer_path' => $viewer])->saveQuietly();

        return $viewer !== null;
    }

    private function convertToPdf(Document $document, string $source): ?string
    {
        $outDir = Storage::disk('public')->path('docs/viewer/' . $document->id);
        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            return null;
        }

        $binary = config('services.libreoffice.binary', 'soffice');
        // Profil utilisateur isolé : LibreOffice refuse de démarrer si un profil est déjà verrouillé
        $profile = 'file://' . sys_get_temp_dir() . '/lo_profile_' . $document->id;
        $process = new Process([$binary, '-env:UserInstallation=' . $profile, '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $source]);
        $process->setTimeout(180);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('Conversion PDF impossible (LibreOffice absent ?)', ['document' => $document->id, 'error' => $e->getMessage()]);
            return null;
        }

        $pdf = $outDir . '/' . pathinfo($source, PATHINFO_FILENAME) . '.pdf';
        if (!$process->isSuccessful() || !is_file($pdf)) {
            Log::warning('Conversion PDF échouée', ['document' => $document->id, 'output' => $process->getErrorOutput()]);
            return null;
        }

        return 'docs/viewer/' . $document->id . '/' . basename($pdf);
    }

    private function extractHtmlPackage(Document $document, string $source): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($source) !== true) {
            return null;
        }

        $base = 'docs/viewer/' . $document->id;
        $outDir = Storage::disk('public')->path($base);
        Storage::disk('public')->deleteDirectory($base);
        mkdir($outDir, 0775, true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Protection contre les chemins sortant du dossier d'extraction
            if ($name === false || str_contains($name, '..') || str_starts_with($name, '/') || str_starts_with($name, '__MACOSX')) {
                continue;
            }
            $entries[] = $name;
        }
        $zip->extractTo($outDir, $entries);
        $zip->close();

        // Page d'entrée : index.html le moins profond
        $candidates = array_filter($entries, fn ($n) => strtolower(basename($n)) === 'index.html' || strtolower(basename($n)) === 'index.htm');
        usort($candidates, fn ($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));
        $entry = $candidates[0] ?? null;

        return $entry ? $base . '/' . $entry : null;
    }
}
