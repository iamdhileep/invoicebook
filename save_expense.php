<?php
include 'db.php';

// Fetch fields
$date = $_POST['expense_date'];
$category = $_POST['category'];
$amount = $_POST['amount'];
$description = $_POST['description'] ?? $_POST['note'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'Cash';
$bill = '';

// Handle file upload (check both possible field names)
$receiptPath = '';
if (!empty($_FILES['receipt']['name'])) {
    $fileName = time() . '_' . basename($_FILES['receipt']['name']);
    $targetPath = 'uploads/expenses/' . $fileName;

    // Ensure the 'uploads/expenses' directory exists
    if (!is_dir('uploads/expenses')) {
        mkdir('uploads/expenses', 0777, true);
    }

    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $targetPath)) {
        $receiptPath = $targetPath;
    }
} elseif (!empty($_FILES['bill_file']['name'])) {
    // Fallback for old field name
    $fileName = time() . '_' . basename($_FILES['bill_file']['name']);
    $targetPath = 'uploads/' . $fileName;

    // Ensure the 'uploads' directory exists
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    if (move_uploaded_file($_FILES['bill_file']['tmp_name'], $targetPath)) {
        $receiptPath = $targetPath;
    }
}

// Try to insert with both possible column combinations
$stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, amount, description, payment_method, receipt_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
if (!$stmt) {
    // Fallback to old column names if new ones don't exist
    $stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, amount, note, bill_path) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        die("Prepare Failed: " . $conn->error);
    }
    $stmt->bind_param("ssdss", $date, $category, $amount, $description, $receiptPath);
} else {
    $stmt->bind_param("ssdsss", $date, $category, $amount, $description, $payment_method, $receiptPath);
}
$stmt->execute();
$stmt->close();

// Redirect back to expenses page with success message
header("Location: pages/expenses/expenses.php?success=1");
exit;
?>
