<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['expense_date'], $_POST['category'])) {
    $date = $_POST['expense_date'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_date = ? AND category = ?");
    $stmt->bind_param("ss", $date, $category);

    if ($stmt->execute()) {
        header("Location: expense_summary.php?deleted=1");
        exit;
    } else {
        echo "Delete failed: " . $stmt->error;
    }
}
?>
