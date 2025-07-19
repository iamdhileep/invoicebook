<?php
require 'vendor/autoload.php';
include 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$sql = "SELECT expense_date, category, SUM(amount) as total 
        FROM expenses 
        WHERE expense_date BETWEEN '$from' AND '$to'
        GROUP BY expense_date, category
        ORDER BY expense_date DESC";

$result = $conn->query($sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Expense Summary");

$sheet->setCellValue('A1', 'Date');
$sheet->setCellValue('B1', 'Category');
$sheet->setCellValue('C1', 'Total Amount');

$rowNum = 2;
$grandTotal = 0;

while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $row['expense_date']);
    $sheet->setCellValue("B$rowNum", $row['category']);
    $sheet->setCellValue("C$rowNum", $row['total']);
    $grandTotal += $row['total'];
    $rowNum++;
}

$sheet->setCellValue("B$rowNum", "Grand Total");
$sheet->setCellValue("C$rowNum", $grandTotal);

// Download file
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Expense_Summary.xlsx"');
$writer->save("php://output");
exit;
