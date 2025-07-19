<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datePrefix = date("Ymd");
    $check = $conn->query("SELECT COUNT(*) as count FROM invoices WHERE invoice_date = CURDATE()");
    $row = $check->fetch_assoc();
    $count = $row['count'] + 1;
    $invoice_number = "INV-" . $datePrefix . "-" . str_pad($count, 4, "0", STR_PAD_LEFT);

    $name = $_POST['customer_name'];
    $contact = $_POST['customer_contact'];
    $date = $_POST['invoice_date'];
    $bill_address = $_POST['bill_address'];
    $items = [];

    for ($i = 0; $i < count($_POST['item_name']); $i++) {
        $items[] = [
            'name' => $_POST['item_name'][$i],
            'qty' => $_POST['item_qty'][$i],
            'price' => $_POST['item_price'][$i],
            'total' => $_POST['item_total'][$i]
        ];
    }

    $items_json = json_encode($items);
    $grand_total = $_POST['grand_total'];

    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, customer_name, customer_contact, invoice_date, bill_address, items, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssd", $invoice_number, $name, $contact, $date, $bill_address, $items_json, $grand_total);

    if ($stmt->execute()) {
        header("Location: view_invoice.php?id=" . $stmt->insert_id);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>