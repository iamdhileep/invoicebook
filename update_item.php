<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $item_name = $_POST['item_name'];
    $item_price = $_POST['item_price'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("UPDATE items SET item_name=?, item_price=?, category=? WHERE id=?");
    $stmt->bind_param("sdsi", $item_name, $item_price, $category, $id);
    
    if ($stmt->execute()) {
        header("Location: index.php#items"); // Stay on Item tab
        exit;
    } else {
        echo "Update failed: " . $conn->error;
    }
}
?>
