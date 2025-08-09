<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Check if parameters are provided
$employee_id = $_GET['employee_id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');

if (!$employee_id) {
    die(json_encode(['error' => 'Employee ID is required']));
}

// Generate PDF using print-friendly version
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$currentPath = dirname($_SERVER['REQUEST_URI']);
$pdfUrl = $baseUrl . $currentPath . '/generate_payslip.php?employee_id=' . $employee_id . '&month=' . $month . '&print=1';

// Set headers for PDF download
header('Content-Type: application/json');

// Return the PDF URL for client-side handling
echo json_encode([
    'success' => true,
    'pdf_url' => $pdfUrl,
    'message' => 'PDF generated successfully'
]);
?>
