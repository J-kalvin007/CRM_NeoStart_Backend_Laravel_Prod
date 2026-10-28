<?php
namespace App\Console\Commands;

use App\Services\ReportDocument;
use App\Support\ReportDependencies;
use Illuminate\Console\Command;

class CheckReportExports extends Command
{
    protected $signature = 'reports:check';
    protected $description = 'Vérifier les moteurs et générer réellement un fichier PDF et Excel sans modifier les données métier';

    public function handle(ReportDependencies $dependencies, ReportDocument $documents): int
    {
        foreach (['pdf', 'xlsx'] as $format) {
            if ($missing = $dependencies->missing($format)) {
                $this->error('Moteur '.$format.' absent : '.implode(', ', $missing));
                $this->line('Exécutez composer install --no-dev --prefer-dist --optimize-autoloader dans CRM-backend.');
                return self::FAILURE;
            }
        }
        $data = ['title'=>'Contrôle des exports', 'author'=>'Diagnostic serveur', 'generated_at'=>now()->toDateTimeString(),
            'country'=>'Sans données métier', 'filters'=>[], 'summary'=>[],
            'sections'=>[['title'=>'Vérification technique', 'columns'=>['État'], 'rows'=>[['Génération réelle du document']]]]];
        foreach (['pdf'=>'%PDF-', 'xlsx'=>"PK\x03\x04"] as $format=>$signature) {
            try {
                $file = $documents->generate(array_merge($data, ['format'=>$format]));
                if (!str_starts_with($file, $signature)) throw new \RuntimeException('Signature du document invalide.');
                $this->info(strtoupper($format).' : fichier généré et signature vérifiée ('.strlen($file).' octets).');
            } catch (\Throwable $exception) {
                $this->error(strtoupper($format).' : '.$exception->getMessage());
                return self::FAILURE;
            }
        }
        return self::SUCCESS;
    }
}
