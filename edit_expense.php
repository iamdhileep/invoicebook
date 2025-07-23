<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

$expense_id = $_GET['id'] ?? null;
$error = '';
$success = false;

if (!$expense_id) {
    header('Location: expense_history.php?error=' . urlencode('Expense ID missing'));
    exit;
}

// Fetch current expense data
$stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
if (!$stmt) {
    die('Database error: ' . $conn->error);
}
$stmt->bind_param("i", $expense_id);
$stmt->execute();
$result = $stmt->get_result();
$expense = $result->fetch_assoc();

if (!$expense) {
    header('Location: expense_history.php?error=' . urlencode('Expense not found'));
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['expense_date'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    
    // Validation
    if (empty($date) || empty($category) || empty($amount)) {
        $error = 'Please fill in all required fields.';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount.';
    } else {
        // Handle file upload
        $receiptPath = $expense['receipt_path'] ?? $expense['bill_path'] ?? ''; // Keep existing path
        
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/expenses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = 'expense_' . $expense_id . '_' . time() . '.' . $fileExtension;
                $receiptPath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $receiptPath)) {
                    $error = 'Failed to upload receipt file.';
                    $receiptPath = $expense['receipt_path'] ?? $expense['bill_path'] ?? ''; // Revert to original
                } else {
                    // Delete old receipt if exists and is different
                    $oldReceipt = $expense['receipt_path'] ?? $expense['bill_path'] ?? '';
                    if (!empty($oldReceipt) && file_exists($oldReceipt) && $oldReceipt !== $receiptPath) {
                        unlink($oldReceipt);
                    }
                }
            } else {
                $error = 'Invalid file type. Please upload JPG, PNG, GIF, or PDF files only.';
            }
        }
        
        // Update expense if no errors
        if (empty($error)) {
            // Try modern schema first, then fallback to legacy
            $updateQuery = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, description = ?, payment_method = ?, receipt_path = ? WHERE id = ?");
            
            if (!$updateQuery) {
                // Fallback to legacy schema
                $updateQuery = $conn->prepare("UPDATE expenses SET expense_date = ?, category = ?, amount = ?, note = ?, bill_path = ? WHERE id = ?");
                if (!$updateQuery) {
                    $error = 'Failed to prepare update statement: ' . $conn->error;
                } else {
                    $updateQuery->bind_param("ssdssi", $date, $category, $amount, $description, $receiptPath, $expense_id);
                }
            } else {
                $updateQuery->bind_param("ssdsssi", $date, $category, $amount, $description, $payment_method, $receiptPath, $expense_id);
            }
            
            if (!empty($error)) {
                // Skip execution if there was an error
            } elseif ($updateQuery && $updateQuery->execute()) {
                $success = true;
                // Refresh expense data
                $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $expense_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $expense = $result->fetch_assoc();
                }
            } else {
                $error = 'Failed to update expense: ' . ($updateQuery ? $conn->error : 'Could not prepare statement');
            }
        }
    }
}

// Get expense categories for dropdown
$categoriesQuery = $conn->query("SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = [];
if ($categoriesQuery) {
    while ($row = $categoriesQuery->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

// Add some default categories if none exist
if (empty($categories)) {
    $categories = ['Office Supplies', 'Travel', 'Meals', 'Utilities', 'Marketing', 'Equipment', 'Software', 'Other'];
}

include 'layouts/header.php';
?>

<div class="main-content">
    <?php include 'layouts/sidebar.php'; ?>
    
    <div class="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Edit Expense</h1>
                    <p class="text-muted">Update expense details and receipt</p>
                </div>
                <div>
                    <a href="expense_history.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Expenses
                    </a>
                </div>
            </div>

            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    Expense updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Edit Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-pencil-square text-primary me-2"></i>
                                Expense Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="expense_date" class="form-label">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                Date <span class="text-danger">*</span>
                                            </label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="expense_date" 
                                                   name="expense_date" 
                                                   value="<?= htmlspecialchars($expense['expense_date'] ?? '') ?>" 
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="amount" class="form-label">
                                                <i class="bi bi-currency-dollar me-1"></i>
                                                Amount <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="number" 
                                                       class="form-control" 
                                                       id="amount" 
                                                       name="amount" 
                                                       step="0.01" 
                                                       min="0.01"
                                                       value="<?= htmlspecialchars($expense['amount'] ?? '') ?>" 
                                                       required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="category" class="form-label">
                                                <i class="bi bi-tags me-1"></i>
                                                Category <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="category" name="category" required>
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= htmlspecialchars($cat) ?>" 
                                                            <?= ($expense['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($cat) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="payment_method" class="form-label">
                                                <i class="bi bi-credit-card me-1"></i>
                                                Payment Method
                                            </label>
                                            <select class="form-select" id="payment_method" name="payment_method">
                                                <option value="">Select Payment Method</option>
                                                <?php 
                                                $payment_methods = ['Cash', 'Credit Card', 'Debit Card', 'Bank Transfer', 'UPI', 'Cheque', 'Other'];
                                                $current_payment = $expense['payment_method'] ?? '';
                                                foreach ($payment_methods as $method): 
                                                ?>
                                                    <option value="<?= htmlspecialchars($method) ?>" 
                                                            <?= $current_payment === $method ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($method) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">
                                        <i class="bi bi-file-text me-1"></i>
                                        Description
                                    </label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="3" 
                                              placeholder="Enter expense description..."><?= htmlspecialchars($expense['description'] ?? $expense['note'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="receipt" class="form-label">
                                        <i class="bi bi-paperclip me-1"></i>
                                        Receipt/Bill
                                    </label>
                                    <input type="file" 
                                           class="form-control" 
                                           id="receipt" 
                                           name="receipt" 
                                           accept="image/*,.pdf">
                                    <div class="form-text">
                                        Upload JPG, PNG, GIF, or PDF files only. Max size: 5MB
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>
                                        Update Expense
                                    </button>
                                    <a href="expense_history.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-1"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Current Receipt Preview -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-image text-info me-2"></i>
                                Current Receipt
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $currentReceipt = $expense['receipt_path'] ?? $expense['bill_path'] ?? '';
                            if (!empty($currentReceipt) && file_exists($currentReceipt)): 
                                $fileExtension = strtolower(pathinfo($currentReceipt, PATHINFO_EXTENSION));
                            ?>
                                <div class="text-center">
                                    <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <img src="<?= htmlspecialchars($currentReceipt) ?>" 
                                             class="img-fluid rounded mb-3" 
                                             alt="Current Receipt"
                                             style="max-height: 300px;">
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="bi bi-file-earmark-pdf fs-1 text-danger mb-2"></i>
                                            <p class="mb-2">PDF Receipt</p>
                                            <a href="<?= htmlspecialchars($currentReceipt) ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>
                                                View PDF
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            File: <?= basename($currentReceipt) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-image fs-1 text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No receipt uploaded</p>
                                    <small class="text-muted">Upload a new receipt using the form</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Expense Summary -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle text-success me-2"></i>
                                Expense Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-0">
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="fs-5 fw-bold text-primary">₹<?= number_format($expense['amount'] ?? 0, 2) ?></div>
                                        <small class="text-muted">Amount</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="fs-6 fw-bold text-info"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></div>
                                        <small class="text-muted">Category</small>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?= date('F j, Y', strtotime($expense['expense_date'] ?? 'now')) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-dismiss alerts
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
    
    // Custom category option
    $('#category').change(function() {
        if ($(this).val() === 'custom') {
            const customCategory = prompt('Enter custom category name:');
            if (customCategory) {
                $(this).append(`<option value="${customCategory}" selected>${customCategory}</option>`);
            } else {
                $(this).val('');
            }
        }
    });
    
    // File upload preview
    $('#receipt').change(function() {
        const file = this.files[0];
        if (file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            if (fileSize > 5) {
                alert('File size must be less than 5MB');
                $(this).val('');
                return;
            }
            
            const fileName = file.name;
            const fileType = file.type;
        }
    });
});
</script>

<?php include 'layouts/footer.php'; ?>
