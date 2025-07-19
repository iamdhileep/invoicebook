<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_ids'])) {
    $ids = $_POST['item_ids'];
    $idList = implode(',', array_map('intval', $ids));
    $sql = "DELETE FROM items WHERE id IN ($idList)";
    mysqli_query($conn, $sql);
}

header("Location: item-list.php");
exit;
?>
