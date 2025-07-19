<?php
include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Fetch image path (optional)
    $stmt = $conn->prepare("SELECT image_path FROM items WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($imagePath);
    $stmt->fetch();
    $stmt->close();

    // Delete the image file if exists
    if (!empty($imagePath)) {
        $filePath = 'uploads/' . $imagePath;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Delete the item from the database
    $del = $conn->prepare("DELETE FROM items WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();

    // Redirect back with success
    header("Location: " . $_SERVER['HTTP_REFERER'] . "?deleted=1");
    exit;
}
?>
