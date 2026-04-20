<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

class ReportExportService
{
    public function download(array $dataset, string $format, array $context = []): never
    {
        $baseName = pathinfo((string) ($dataset['filename'] ?? ('report-' . date('Ymd-His') . '.csv')), PATHINFO_FILENAME);

        match ($format) {
            'csv' => $this->streamCsv($dataset, $baseName),
            'xlsx' => $this->streamSpreadsheet($dataset, $baseName),
            'pdf' => $this->streamPdf($dataset, $baseName, $context),
            default => throw new HttpException(404, 'Unsupported export format requested.'),
        };

        exit;
    }

    private function streamCsv(array $dataset, string $baseName): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            throw new HttpException(500, 'Could not open CSV export stream.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $dataset['headers'] ?? []);
        foreach (($dataset['rows'] ?? []) as $row) {
            fputcsv($stream, $row);
        }
        fclose($stream);
    }

    private function streamSpreadsheet(array $dataset, string $baseName): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) || !class_exists(\PhpOffice\PhpSpreadsheet\Writer\Xlsx::class)) {
            throw new HttpException(500, 'Excel export requires Composer dependencies. Run composer install first.');
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr((string) ($dataset['title'] ?? 'Report'), 0, 31));
        $sheet->fromArray($dataset['headers'] ?? [], null, 'A1');
        $sheet->fromArray($dataset['rows'] ?? [], null, 'A2');

        $headerCount = count($dataset['headers'] ?? []);
        $rowCount = count($dataset['rows'] ?? []) + 1;
        for ($index = 1; $index <= $headerCount; $index++) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($headerCount > 0) {
            $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerCount) . '1';
            $fullRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerCount) . $rowCount;
            $sheet->freezePane('A2');
            $sheet->setAutoFilter($fullRange);
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '246BDB'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'D7E5F6'],
                    ],
                ],
            ]);
        }

        if ($headerCount > 0 && $rowCount > 1) {
            $bodyRange = 'A2:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headerCount) . $rowCount;
            $sheet->getStyle($bodyRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'E6EEF8'],
                    ],
                ],
            ]);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function streamPdf(array $dataset, string $baseName, array $context): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new HttpException(500, 'PDF export requires Composer dependencies. Run composer install first.');
        }

        $dompdf = new \Dompdf\Dompdf([
            'defaultFont' => 'DejaVu Sans',
            'isRemoteEnabled' => false,
        ]);
        $dompdf->loadHtml($this->buildPdfHtml($dataset, $context));
        $orientation = count($dataset['headers'] ?? []) > 6 ? 'landscape' : 'portrait';
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $baseName . '.pdf"');
        echo $dompdf->output();
    }

    private function buildPdfHtml(array $dataset, array $context): string
    {
        $title = $this->escape((string) ($context['title'] ?? ($dataset['title'] ?? 'Report Export')));
        $filters = $context['filters'] ?? [];
        $dateRange = (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '')
            ? $this->escape(((string) ($filters['date_from'] ?? '')) . ' to ' . ((string) ($filters['date_to'] ?? '')))
            : '';
        $branchName = $this->escape((string) ($context['branch_name'] ?? 'Main Branch'));
        $cashierName = $this->escape((string) ($context['cashier_name'] ?? 'All cashiers'));
        $generatedBy = $this->escape((string) ($context['generated_by'] ?? 'System User'));
        $rowCount = count($dataset['rows'] ?? []);

        $thead = '';
        foreach (($dataset['headers'] ?? []) as $header) {
            $thead .= '<th>' . $this->escape((string) $header) . '</th>';
        }

        $tbody = '';
        foreach (($dataset['rows'] ?? []) as $row) {
            $tbody .= '<tr>';
            foreach ($row as $cell) {
                $tbody .= '<td>' . $this->escape((string) $cell) . '</td>';
            }
            $tbody .= '</tr>';
        }

        if ($tbody === '') {
            $colspan = max(1, count($dataset['headers'] ?? []));
            $tbody = '<tr><td colspan="' . $colspan . '">No rows matched the selected filters.</td></tr>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>' . $title . '</title>
<style>
body{font-family:DejaVu Sans,sans-serif;color:#18324d;font-size:11px;margin:24px;background:#f5f8fc;}
.hero{padding:20px 22px;border:1px solid #d7e3f3;border-radius:18px;background:linear-gradient(180deg,#ffffff,#eef5fd);margin-bottom:18px;}
.eyebrow{font-size:10px;letter-spacing:0.18em;text-transform:uppercase;color:#5d7594;margin:0 0 8px;font-weight:bold;}
h1{font-size:24px;margin:0 0 8px;color:#123b7a;}
.summary{margin:0;color:#52667f;line-height:1.5;}
.meta-grid{margin-top:14px;}
.meta-chip{display:inline-block;margin:0 8px 8px 0;padding:7px 11px;border-radius:999px;border:1px solid #d7e3f3;background:#f8fbff;color:#23415f;font-size:10px;}
table{width:100%;border-collapse:collapse;background:#ffffff;border:1px solid #d7e3f3;border-radius:16px;overflow:hidden;}
th,td{border:1px solid #dfe8f4;padding:8px 10px;text-align:left;vertical-align:top;}
th{background:linear-gradient(180deg,#246bdb,#2b83ea);color:#ffffff;font-weight:bold;}
tr:nth-child(even) td{background:#f7fbff;}
.footer{margin-top:14px;font-size:10px;color:#6b7f97;}
</style>
</head>
<body>
<section class="hero">
    <p class="eyebrow">NovaPOS Report Export</p>
    <h1>' . $title . '</h1>
    <p class="summary">Prepared for operational review with the active branch, cashier, and date filters applied at export time.</p>
    <div class="meta-grid">
        <span class="meta-chip">Generated: ' . $this->escape(date('Y-m-d H:i:s')) . '</span>
        <span class="meta-chip">Branch: ' . $branchName . '</span>
        <span class="meta-chip">Cashier: ' . $cashierName . '</span>' .
        ($dateRange !== '' ? '<span class="meta-chip">Range: ' . $dateRange . '</span>' : '') . '
        <span class="meta-chip">Rows: ' . $this->escape((string) $rowCount) . '</span>
        <span class="meta-chip">Generated By: ' . $generatedBy . '</span>
    </div>
</section>
<table>
<thead><tr>' . $thead . '</tr></thead>
<tbody>' . $tbody . '</tbody>
</table>
<div class="footer">Generated by NovaPOS reporting services.</div>
</body>
</html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
