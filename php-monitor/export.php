<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/database.php';

$config = require __DIR__ . '/config.php';
$database = new Database($config['db']);
$rows = $database->fetchExportRows();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Multisig Report');

$headers = [
    'A1' => 'Transaction Hash',
    'B1' => 'Signer',
    'C1' => 'Signature Count',
    'D1' => 'Required Signatures',
    'E1' => 'Status',
    'F1' => 'Date',
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

$rowIndex = 2;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowIndex, $row['tx_hash']);
    $sheet->setCellValue('B' . $rowIndex, $row['signer_address']);
    $sheet->setCellValue('C' . $rowIndex, (int) $row['current_signatures']);
    $sheet->setCellValue('D' . $rowIndex, (int) $row['required_signatures']);
    $sheet->setCellValue('E' . $rowIndex, $row['status']);
    $sheet->setCellValue('F' . $rowIndex, $row['event_date']);
    $rowIndex++;
}

$sheet->getStyle('A1:F1')->getFont()->setBold(true);
foreach (['A' => 70, 'B' => 55, 'C' => 20, 'D' => 22, 'E' => 18, 'F' => 24] as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

$filename = 'multisig_report.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
