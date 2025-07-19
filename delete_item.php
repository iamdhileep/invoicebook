<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Optional: delete associated image if used
    $stmt = $conn->prepare("SELECT image_path FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imagePath);
    $stmt->fetch();
    $stmt->close();

    if (!empty($imagePath)) {
        $file = 'uploads/' . $imagePath;
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Delete item from DB
    $delete = $conn->prepare("DELETE FROM items WHERE id = ?");
    $delete->bind_param("i", $id);
    $delete->execute();

    // Redirect back
    header("Location: item-stock.php?deleted=1");
    exit;
}
?>
