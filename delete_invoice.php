<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get ID from POST (AJAX) or GET (direct link)
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Get invoice details first
        $stmt = $conn->prepare("SELECT invoice_number, customer_name, total_amount FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $invoice = $result->fetch_assoc();
            $invoiceNumber = $invoice['invoice_number'] ?? "Invoice #$id";
            
            // Delete invoice items first (if any)
            $deleteItemsStmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $deleteItemsStmt->bind_param("i", $id);
            $deleteItemsStmt->execute();
            
            // Delete the invoice
            $deleteStmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => "Invoice '{$invoiceNumber}' deleted successfully"
                    ]);
                } else {
                    echo "<script>alert('Invoice deleted successfully'); window.location.href='invoice_history.php';</script>";
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to delete invoice: ' . $conn->error
                    ]);
                } else {
                    echo "Error deleting invoice: " . $conn->error;
                }
            }
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invoice not found']);
            } else {
                echo "Invoice not found.";
            }
        }
    } catch (Exception $e) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } else {
            echo "Error occurred while deleting invoice: " . $e->getMessage();
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid invoice ID is required']);
    } else {
        echo "Invalid request.";
    }
}

exit;
?>
