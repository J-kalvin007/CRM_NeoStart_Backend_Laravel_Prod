<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentViewerService;
use Illuminate\Console\Command;

class PrepareDocumentViewers extends Command
{
    protected $signature = 'documents:prepare-viewers {--id= : Un seul document}';

    protected $description = 'Prépare la version affichable sur tablette des documents (PowerPoint → PDF, paquets HTML5 extraits)';

    public function handle(DocumentViewerService $service): int
    {
        $query = Document::withoutGlobalScopes();
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }

        foreach ($query->get() as $document) {
            $ok = $service->prepare($document);
            $this->line(($ok ? '✔ ' : '· ') . $document->title . ($ok ? ' → ' . $document->viewer_path : ''));
        }

        return self::SUCCESS;
    }
}
