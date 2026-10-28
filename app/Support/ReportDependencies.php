<?php
namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ReportDependencies
{
    public function missing(string $format): array
    {
        $classes = $format === 'pdf'
            ? [\Dompdf\Options::class, \Dompdf\Dompdf::class]
            : [\PhpOffice\PhpSpreadsheet\Spreadsheet::class, \PhpOffice\PhpSpreadsheet\Writer\Xlsx::class];
        return array_values(array_filter($classes, fn ($class) => !$this->available($class)));
    }

    protected function available(string $class): bool { return class_exists($class); }

    public function ensure(string $format): void
    {
        if ($this->missing($format)) {
            throw new HttpException(503, 'Le moteur d’export '.($format === 'pdf' ? 'PDF' : 'Excel').' manque sur ce serveur. L’administrateur doit installer les dépendances du backend avec Composer.');
        }
    }
}
