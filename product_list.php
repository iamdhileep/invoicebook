<div class="container py-4">
                <h4 class="mb-3">All Items</h4>
                <div class="row">
                    <div class="col-6"><a href="item-stock.php" class="btn btn-outline-primary mb-3">📦 View Item Stock</a></div>
                    <div class="col-6 gap-2 d-md-flex justify-content-md-end"><a href="item-full-list.php" class="btn btn-outline-primary mb-3">View Full Item List</a></div>
                </div>
                
                <table id="itemTable" class="table table-bordered table-striped">
                    <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Price (₹)</th>
                        <th>Stock</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    include 'db.php';
                    $result = mysqli_query($conn, "SELECT * FROM items ORDER BY id DESC");
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>
                            <td>{$row['id']}</td>
                            <td>" . htmlspecialchars($row['item_name']) . "</td>
                            <td>₹ " . number_format($row['item_price'], 2) . "</td>
                            <td>" . (isset($row['stock']) ? htmlspecialchars($row['stock']) : '0') . "</td>
                        </tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>