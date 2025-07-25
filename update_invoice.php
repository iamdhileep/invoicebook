<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = (int)$_POST['invoice_id'];
    $customer_name = $_POST['customer_name'];
    $customer_contact = $_POST['customer_contact'];
    $invoice_date = $_POST['invoice_date'];
    $bill_address = $_POST['bill_address'];
    $grand_total = $_POST['grand_total'];
    
    // Validate required fields
    if (empty($customer_name) || empty($customer_contact) || empty($invoice_date) || empty($bill_address)) {
        $_SESSION['error'] = "All required fields must be filled.";
        header("Location: edit_invoice.php?id=" . $invoice_id);
        exit;
    }
    
    // Process items
    $items = [];
    if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
        for ($i = 0; $i < count($_POST['item_name']); $i++) {
            if (!empty($_POST['item_name'][$i])) {
                $items[] = [
                    'name' => $_POST['item_name'][$i],
                    'qty' => $_POST['item_qty'][$i],
                    'price' => $_POST['item_price'][$i],
                    'total' => $_POST['item_total'][$i]
                ];
            }
        }
    }
    
    if (empty($items)) {
        $_SESSION['error'] = "At least one item is required.";
        header("Location: edit_invoice.php?id=" . $invoice_id);
        exit;
    }
    
    $items_json = json_encode($items);
    
    // Verify invoice exists and get current data for logging
    $checkStmt = $conn->prepare("SELECT invoice_number, customer_name, total_amount FROM invoices WHERE id = ?");
    $checkStmt->bind_param("i", $invoice_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $_SESSION['error'] = "Invoice not found.";
        header("Location: invoice_history.php");
        exit;
    }
    
    $originalInvoice = $checkResult->fetch_assoc();
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Update the invoice
        $updateStmt = $conn->prepare("UPDATE invoices SET customer_name = ?, customer_contact = ?, invoice_date = ?, bill_address = ?, items = ?, total_amount = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("sssssdi", $customer_name, $customer_contact, $invoice_date, $bill_address, $items_json, $grand_total, $invoice_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update invoice: " . $updateStmt->error);
        }
        
        // Log the update (optional - you can create an audit log table)
        $logMessage = "Invoice {$originalInvoice['invoice_number']} updated. Customer: {$originalInvoice['customer_name']} -> {$customer_name}, Total: ₹{$originalInvoice['total_amount']} -> ₹{$grand_total}";
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Invoice updated successfully!";
        header("Location: view_invoice.php?id=" . $invoice_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        
        $_SESSION['error'] = "Error updating invoice: " . $e->getMessage();
        header("Location: edit_invoice.php?id=" . $invoice_id);
        exit;
    }
    
} else {
    header("Location: invoice_history.php");
    exit;
}
?>
