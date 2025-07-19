<?php
include 'db.php';

if (isset($_POST['delete_ids'])) {
    $ids = $_POST['delete_ids'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM items WHERE id IN ($placeholders)");

    if ($stmt) {
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
    }
}

header("Location: item-list.php");
exit;
