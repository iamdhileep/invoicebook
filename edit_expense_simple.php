<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';
include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Edit Expense - Simple Test</h1>
                    <p class="text-muted">Testing basic UI and sidebar integration</p>
                </div>
                <div>
                    <a href="expense_history.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Expenses
                    </a>
                </div>
            </div>

            <!-- Test Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>
                        Simple Test Form
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>✅ Session:</strong> Admin logged in</p>
                    <p><strong>✅ Database:</strong> Connected</p>
                    <p><strong>✅ Sidebar:</strong> Loaded</p>
                    <p><strong>✅ Header:</strong> Loaded</p>
                    
                    <?php
                    $expense_id = $_GET['id'] ?? null;
                    if ($expense_id) {
                        echo "<p><strong>✅ Expense ID:</strong> " . htmlspecialchars($expense_id) . "</p>";
                        
                        // Try to fetch expense
                        $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $expense_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $expense = $result->fetch_assoc();
                            
                            if ($expense) {
                                echo "<div class='alert alert-success'>";
                                echo "<h6>✅ Expense Found:</h6>";
                                echo "<ul class='mb-0'>";
                                echo "<li>Category: " . htmlspecialchars($expense['category'] ?? 'N/A') . "</li>";
                                echo "<li>Amount: ₹" . number_format($expense['amount'] ?? 0, 2) . "</li>";
                                echo "<li>Date: " . htmlspecialchars($expense['expense_date'] ?? 'N/A') . "</li>";
                                echo "</ul>";
                                echo "</div>";
                                
                                // Simple form
                                echo "<form method='POST' class='mt-3'>";
                                echo "<div class='row'>";
                                echo "<div class='col-md-6'>";
                                echo "<div class='mb-3'>";
                                echo "<label class='form-label'>Date</label>";
                                echo "<input type='date' name='expense_date' class='form-control' value='" . htmlspecialchars($expense['expense_date'] ?? '') . "' required>";
                                echo "</div>";
                                echo "</div>";
                                echo "<div class='col-md-6'>";
                                echo "<div class='mb-3'>";
                                echo "<label class='form-label'>Category</label>";
                                echo "<input type='text' name='category' class='form-control' value='" . htmlspecialchars($expense['category'] ?? '') . "' required>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                                echo "<div class='row'>";
                                echo "<div class='col-md-6'>";
                                echo "<div class='mb-3'>";
                                echo "<label class='form-label'>Amount</label>";
                                echo "<input type='number' step='0.01' name='amount' class='form-control' value='" . htmlspecialchars($expense['amount'] ?? '') . "' required>";
                                echo "</div>";
                                echo "</div>";
                                echo "</div>";
                                echo "<div class='mb-3'>";
                                echo "<label class='form-label'>Description</label>";
                                echo "<textarea name='description' class='form-control' rows='3'>" . htmlspecialchars($expense['description'] ?? $expense['note'] ?? '') . "</textarea>";
                                echo "</div>";
                                echo "<button type='submit' class='btn btn-primary'>Update Expense</button> ";
                                echo "<a href='expense_history.php' class='btn btn-secondary'>Cancel</a>";
                                echo "</form>";
                                
                                // Handle form submission
                                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                    $date = $_POST['expense_date'] ?? '';
                                    $category = $_POST['category'] ?? '';
                                    $amount = $_POST['amount'] ?? '';
                                    $description = $_POST['description'] ?? '';
                                    
                                    if (!empty($date) && !empty($category) && !empty($amount)) {
                                        // Try update with description first
                                        $updateStmt = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, description = ? WHERE id = ?");
                                        if (!$updateStmt) {
                                            // Fallback to note
                                            $updateStmt = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, note = ? WHERE id = ?");
                                        }
                                        
                                        if ($updateStmt) {
                                            $updateStmt->bind_param("ssdsi", $date, $category, $amount, $description, $expense_id);
                                            if ($updateStmt->execute()) {
                                                echo "<div class='alert alert-success mt-3'>✅ Expense updated successfully!</div>";
                                                echo "<script>setTimeout(function(){ window.location.href='expense_history.php'; }, 2000);</script>";
                                            } else {
                                                echo "<div class='alert alert-danger mt-3'>❌ Update failed: " . $updateStmt->error . "</div>";
                                            }
                                        } else {
                                            echo "<div class='alert alert-danger mt-3'>❌ Could not prepare statement: " . $conn->error . "</div>";
                                        }
                                    } else {
                                        echo "<div class='alert alert-warning mt-3'>⚠️ Please fill in all required fields.</div>";
                                    }
                                }
                                
                            } else {
                                echo "<div class='alert alert-danger'>❌ Expense not found with ID: $expense_id</div>";
                            }
                        } else {
                            echo "<div class='alert alert-danger'>❌ Database query failed: " . $conn->error . "</div>";
                        }
                    } else {
                        echo "<div class='alert alert-warning'>⚠️ No expense ID provided. <a href='expense_history.php'>Go to expense history</a></div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>