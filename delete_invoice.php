<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Delete the invoice
    $sql = "DELETE FROM invoices WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Invoice deleted successfully'); window.location.href='invoice_history.php';</script>";
    } else {
        echo "Error deleting invoice: " . $conn->error;
    }
} else {
    echo "Invalid request.";
}
?>
