<?php
include 'db.php';

if (isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM invoices WHERE id IN ($placeholders)");

    if ($stmt) {
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
    }
}

header("Location: invoice_history.php");
exit;
