<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add Employee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <a href="index.php" class="btn btn-sm btn-outline-secondary mb-3">&larr; Back to Dashboard</a>
        <h4>👨‍💼 Employee Manager</h4>

        <!-- Add Employee Form -->
        <form method="POST" action="employee-tabs.php" enctype="multipart/form-data" class="row g-3 mb-4">
            <input type="hidden" name="add" value="1">

            <div class="col-md-3"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
            <div class="col-md-2"><input type="text" name="code" class="form-control" placeholder="Code" required></div>
            <div class="col-md-2"><input type="text" name="position" class="form-control" placeholder="Position" required></div>
            <div class="col-md-2"><input type="number" step="0.01" name="monthly_salary" class="form-control" placeholder="Monthly Salary (₹)" required></div>
            <div class="col-md-3"><input type="text" name="phone" class="form-control" placeholder="Phone Number" required></div>
            <div class="col-md-6"><input type="text" name="address" class="form-control" placeholder="Address" required></div>
            <div class="col-md-6"><input type="file" name="photo" class="form-control" accept="image/*"></div>

            <div class="col-12 d-grid">
                <button type="submit" class="btn btn-success">➕ Add Employee</button>
            </div>
        </form>

        <!-- Employee List Table -->
        <table id="empTable" class="table table-bordered table-striped">
            <thead class="table-dark text-center">
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Position</th>
                    <th>Phone</th>
                    <th>Monthly Salary (₹)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $result = mysqli_query($conn, "SELECT * FROM employees ORDER BY employee_id DESC");
                while ($row = mysqli_fetch_assoc($result)) {
                    $photo = !empty($row['photo']) ? 'uploads/' . $row['photo'] : 'uploads/no-image.png';
                    echo "<tr>
                    <td><img src='$photo' width='50' height='50' style='border-radius: 50%; object-fit: cover;'></td>
                    <td>" . htmlspecialchars($row['name']) . "</td>
                    <td>" . htmlspecialchars($row['employee_code']) . "</td>
                    <td>" . htmlspecialchars($row['position']) . "</td>
                    <td>" . htmlspecialchars($row['phone']) . "</td>
                    <td>₹" . number_format($row['monthly_salary'], 2) . "</td>
                    <td class='text-center'>
                        <button class='btn btn-sm btn-info' data-bs-toggle='modal' data-bs-target='#viewModal{$row['employee_id']}'>🔍</button>
                        <a href='edit_employee.php?id={$row['employee_id']}' class='btn btn-sm btn-primary'>✏️</a>
                        <button class='btn btn-sm btn-danger delete-btn' data-id='{$row['employee_id']}'>🗑️</button>
                    </td>
                    </tr>";

                    // Modal Popup
                    echo "
                    <div class='modal fade' id='viewModal{$row['employee_id']}' tabindex='-1' aria-labelledby='viewModalLabel{$row['employee_id']}' aria-hidden='true'>
                    <div class='modal-dialog modal-dialog-centered'>
                        <div class='modal-content'>
                        <div class='modal-header'>
                            <h5 class='modal-title' id='viewModalLabel{$row['employee_id']}'>👤 Employee Details</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                        </div>
                        <div class='modal-body text-center'>
                            <img src='$photo' width='100' height='100' style='border-radius:50%; object-fit:cover;' class='mb-3'>
                            <p><strong>Name:</strong> " . htmlspecialchars($row['name']) . "</p>
                            <p><strong>Code:</strong> " . htmlspecialchars($row['employee_code']) . "</p>
                            <p><strong>Position:</strong> " . htmlspecialchars($row['position']) . "</p>
                            <p><strong>Phone:</strong> " . htmlspecialchars($row['phone']) . "</p>
                            <p><strong>Address:</strong> " . nl2br(htmlspecialchars($row['address'])) . "</p>
                            <p><strong>Monthly Salary:</strong> ₹" . number_format($row['monthly_salary'], 2) . "</p>
                        </div>
                        </div>
                    </div>
                    </div>
                    ";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>

</html>
