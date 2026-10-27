<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\{Coordinate, DataType};
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Fill};
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportDocument
{
    public function download(\Illuminate\Http\Request $request, array $data)
    {
        $data['author'] = $request->user()->name;
        $data['generated_at'] = now()->format('d/m/Y H:i');
        $countryId = \App\Support\CountryFilter::resolveCountryId();
        $data['country'] = $countryId ? \App\Models\Country::find($countryId)?->name :
            (in_array(\App\Support\CountryFilter::selectedValue(), [null, '', 'group', 'default'], true) ? 'Tous les pays autorisés' : 'Pays non reconnu');
        $file = $this->generate($data);
        $filename = \Illuminate\Support\Str::slug($data['title']).'-'.now()->format('Y-m-d-His').'.'.$data['format'];
        return response($file, 200, [
            'Content-Type' => $data['format'] === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function generate(array $data): string
    {
        return $data['format'] === 'pdf' ? $this->pdf($data) : $this->xlsx($data);
    }

    private function xlsx(array $data): string
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator($data['author'])->setTitle($data['title']);
        $context = $book->getActiveSheet()->setTitle('Contexte');
        $contextRows = [[$data['title'], ''], ['Édité le', $data['generated_at']], ['Édité par', $data['author']],
            ['Pays actif', $data['country']], ['Source', 'Données de la vue filtrée au moment de l’export']];
        foreach (['filters' => 'Filtres', 'summary' => 'Synthèse'] as $key => $title) {
            $contextRows[] = [$title, ''];
            foreach ($data[$key] as $entry) $contextRows[] = [$entry['label'], $entry['value']];
        }
        $this->writeRows($context, $contextRows);
        $context->getColumnDimension('A')->setWidth(38);
        $context->getColumnDimension('B')->setWidth(90);
        $context->getStyle('A1:B1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF002966');
        foreach ($data['sections'] as $index => $section) {
            // Index prefix makes sheet names unique, even after Excel's 31 character limit.
            $name = ($index + 1).' '.preg_replace('/[\\\\\/?*\[\]:]/u', '-', $section['title']);
            $sheet = $book->createSheet()->setTitle(mb_substr($name, 0, 31));
            $this->writeRows($sheet, [$section['columns'], ...$section['rows']]);
            $lastCol = Coordinate::stringFromColumnIndex(count($section['columns']));
            $lastRow = count($section['rows']) + 1;
            $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$lastCol.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF002966');
            for ($column = 1; $column <= count($section['columns']); $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(30);
            }
            $sheet->setAutoFilter('A1:'.$lastCol.$lastRow)->freezePane('A2');
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }
        $book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'crm-report-');
        try {
            (new Xlsx($book))->save($path);
            return file_get_contents($path);
        } finally {
            $book->disconnectWorksheets();
            if (is_file($path)) unlink($path);
        }
    }

    private function writeRows($sheet, array $rows): void
    {
        foreach ($rows as $rowIndex => $row) foreach ($row as $columnIndex => $value) {
            $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 1);
            // All text is explicit text: no formulas, including =, +, -, @ and URL payloads.
            $type = is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING;
            $sheet->setCellValueExplicit($cell, is_bool($value) ? ($value ? 'Oui' : 'Non') : ($value ?? ''), $type);
        }
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }

    private function pdf(array $data): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('tempDir', sys_get_temp_dir());
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->loadHtml(view('reports.document', ['report' => $data])->render(), 'UTF-8');
        $dompdf->render();
        $dompdf->getCanvas()->page_text(740, 575, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.35, 0.4, 0.5]);
        return $dompdf->output();
    }
}
