<?php
include 'db.php';

$search = $_POST['search'] ?? '';
$sql = "SELECT * FROM items WHERE item_name LIKE '%$search%' OR category LIKE '%$search%' ORDER BY item_name ASC";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $lowStock = ($row['stock'] < 5) ? 'low-stock' : '';
    echo "<tr class='$lowStock text-center'>
        <td>{$row['id']}</td>
        <td>" . htmlspecialchars($row['item_name']) . "</td>
        <td>" . htmlspecialchars($row['category']) . "</td>
        <td>₹ " . number_format($row['item_price'], 2) . "</td>
        <td>{$row['stock']}</td>
        <td>
          <a href='edit-item.php?id={$row['id']}' class='btn btn-sm btn-primary'>Edit</a>
          <a href='delete-item.php?id={$row['id']}' onclick=\"return confirm('Delete this item?')\" class='btn btn-sm btn-danger'>Delete</a>
        </td>
      </tr>";
}
?>
