<?php
include 'db.php'; // Ensure database is connected
?>

<h5 class="mt-4">🧾 Recent Invoices</h5>
<table class="table table-striped table-bordered">
  <thead>
    <tr>
      <th>Invoice ID</th>
      <th>Customer</th>
      <th>Date</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
     <?php
    include 'db.php';

    $result = mysqli_query($conn, "SELECT * FROM invoices ORDER BY created_at DESC LIMIT 5");

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>
              <td>" . htmlspecialchars($row['id']) . "</td>
              <td>" . htmlspecialchars($row['customer_name']) . "</td>
              <td>" . date('d-m-Y', strtotime($row['created_at'])) . "</td>
              <td>₹ " . number_format($row['total'], 2) . "</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center'>No recent invoices found.</td></tr>";
    }
    ?>


  </tbody>
</table>
