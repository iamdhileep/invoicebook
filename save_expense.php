<?php
include 'db.php';

// Fetch fields
$date = $_POST['expense_date'];
$category = $_POST['category'];
$amount = $_POST['amount'];
$note = $_POST['note'] ?? '';
$bill = '';

// Handle file upload
if (!empty($_FILES['bill_file']['name'])) {
    $fileName = time() . '_' . basename($_FILES['bill_file']['name']);
    $targetPath = 'uploads/' . $fileName;

    // Ensure the 'uploads' directory exists
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }

    if (move_uploaded_file($_FILES['bill_file']['tmp_name'], $targetPath)) {
        $bill = $fileName;
    }
}

// Update SQL to include bill_path
$stmt = $conn->prepare("INSERT INTO expenses (expense_date, category, amount, note, bill_path) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    die("Prepare Failed: " . $conn->error);
}

$stmt->bind_param("ssdss", $date, $category, $amount, $note, $bill);
$stmt->execute();
$stmt->close();

// Redirect back to the tab
header("Location: index.php#expense");
exit;
?>
