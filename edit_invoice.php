<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Edit Invoice';

// Get invoice ID from URL
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    header("Location: invoice_history.php");
    exit;
}

// Fetch invoice details
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$invoiceResult = $stmt->get_result();

if ($invoiceResult->num_rows === 0) {
    header("Location: invoice_history.php");
    exit;
}

$invoice = $invoiceResult->fetch_assoc();

// Parse items from JSON
$invoice_items = [];
if (!empty($invoice['items'])) {
    $decoded_items = json_decode($invoice['items'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_items)) {
        $invoice_items = $decoded_items;
    }
}

// If no items found, initialize with empty array for at least one row
if (empty($invoice_items)) {
    $invoice_items = [];
}

include 'layouts/header.php';
include 'layouts/sidebar.php';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="main-content">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Edit Invoice</h1>
            <p class="text-muted">Update invoice details - <?= htmlspecialchars($invoice['invoice_number']) ?></p>
        </div>
        <div>
            <a href="invoice_history.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to History
            </a>
            <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-outline-info">
                <i class="bi bi-eye"></i> View Invoice
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Invoice Details</h5>
                </div>
                <div class="card-body">
                    <form action="update_invoice.php" method="POST" id="editInvoiceForm">
                        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" name="invoice_number" class="form-control" 
                                       value="<?= htmlspecialchars($invoice['invoice_number']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Invoice Date *</label>
                                <input type="date" name="invoice_date" class="form-control" 
                                       value="<?= $invoice['invoice_date'] ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control" 
                                       value="<?= htmlspecialchars($invoice['customer_name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Contact *</label>
                                <input type="text" name="customer_contact" class="form-control" 
                                       value="<?= htmlspecialchars($invoice['customer_contact']) ?>" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Bill To Address *</label>
                                <textarea name="bill_address" class="form-control" rows="2" required><?= htmlspecialchars($invoice['bill_address']) ?></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Invoice Items</h6>
                                <button type="button" onclick="addRow()" class="btn btn-success btn-sm">
                                    <i class="bi bi-plus-circle"></i> Add Item
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th width="15%">Quantity</th>
                                            <th width="15%">Price</th>
                                            <th width="15%">Total</th>
                                            <th width="8%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemRows">
                                        <?php if (!empty($invoice_items)): ?>
                                            <?php foreach ($invoice_items as $item): ?>
                                                <tr class="item-row">
                                                    <td>
                                                        <select name="item_name[]" class="form-select item-select" required>
                                                            <option value="">-- Select Item --</option>
                                                            <?php 
                                                            mysqli_data_seek($itemOptions, 0); // Reset pointer
                                                            if ($itemOptions && mysqli_num_rows($itemOptions) > 0):
                                                                while ($dbItem = $itemOptions->fetch_assoc()): 
                                                                    $selected = ($dbItem['item_name'] === $item['name']) ? 'selected' : '';
                                                                ?>
                                                                <option value="<?= htmlspecialchars($dbItem['item_name']) ?>" 
                                                                        data-price="<?= $dbItem['item_price'] ?>" <?= $selected ?>>
                                                                    <?= htmlspecialchars($dbItem['item_name']) ?> (₹<?= number_format($dbItem['item_price'], 2) ?>)
                                                                </option>
                                                            <?php endwhile;
                                                            endif; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="item_qty[]" placeholder="Qty" 
                                                               class="form-control qty" min="1" 
                                                               value="<?= htmlspecialchars($item['qty']) ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="number" name="item_price[]" placeholder="Price" 
                                                               class="form-control price" step="0.01" min="0" 
                                                               value="<?= htmlspecialchars($item['price']) ?>" required>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="item_total[]" placeholder="Total" 
                                                               class="form-control total" 
                                                               value="<?= htmlspecialchars($item['total']) ?>" readonly>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm remove-item">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr class="item-row">
                                                <td>
                                                    <select name="item_name[]" class="form-select item-select" required>
                                                        <option value="">-- Select Item --</option>
                                                        <?php 
                                                        mysqli_data_seek($itemOptions, 0); // Reset pointer
                                                        if ($itemOptions && mysqli_num_rows($itemOptions) > 0):
                                                            while ($item = $itemOptions->fetch_assoc()): ?>
                                                            <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                                                                <?= htmlspecialchars($item['item_name']) ?> (₹<?= number_format($item['item_price'], 2) ?>)
                                                            </option>
                                                        <?php endwhile;
                                                        endif; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" min="1" value="1" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="item_price[]" placeholder="Price" class="form-control price" step="0.01" min="0" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm remove-item">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1">Subtotal:</p>
                                                <p class="mb-1">Tax (0%):</p>
                                                <h5 class="mb-0">Grand Total:</h5>
                                            </div>
                                            <div class="col-6 text-end">
                                                <p class="mb-1">₹ <span id="subtotal">0.00</span></p>
                                                <p class="mb-1">₹ <span id="tax">0.00</span></p>
                                                <h5 class="mb-0">₹ <span id="grandTotal">0.00</span></h5>
                                            </div>
                                        </div>
                                        <input type="hidden" name="grand_total" id="grandTotalInput">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <div>
                                <button type="button" class="btn btn-outline-warning" onclick="resetToOriginal()">
                                    <i class="bi bi-arrow-clockwise"></i> Reset Changes
                                </button>
                            </div>
                            <div>
                                <a href="invoice_history.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Update Invoice
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Invoice Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Invoice Status</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center">
                                <h6 class="text-muted">Created</h6>
                                <small><?= date('M j, Y', strtotime($invoice['created_at'])) ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h6 class="text-muted">Original Total</h6>
                                <small>₹<?= number_format($invoice['total_amount'], 2) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-outline-info">
                            <i class="bi bi-eye"></i> View Invoice
                        </a>
                        <a href="print_invoice.php?id=<?= $invoice_id ?>" class="btn btn-outline-success" target="_blank">
                            <i class="bi bi-printer"></i> Print Invoice
                        </a>
                        <a href="invoice_history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul"></i> All Invoices
                        </a>
                        <button type="button" class="btn btn-outline-warning" onclick="duplicateInvoice()">
                            <i class="bi bi-files"></i> Duplicate Invoice
                        </button>
                    </div>
                </div>
            </div>

            <!-- Edit Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Current Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="card bg-primary text-white">
                                <div class="card-body p-2">
                                    <h6 class="mb-0" id="itemCount">0</h6>
                                    <small>Items</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card bg-success text-white">
                                <div class="card-body p-2">
                                    <h6 class="mb-0" id="totalValue">₹0</h6>
                                    <small>Total Value</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Items -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Available Items</h6>
                </div>
                <div class="card-body">
                    <?php
                    $recentItems = $conn->query("SELECT DISTINCT item_name, item_price FROM items ORDER BY item_id DESC LIMIT 5");
                    if ($recentItems && $recentItems->num_rows > 0):
                        while ($item = $recentItems->fetch_assoc()):
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small><?= htmlspecialchars($item['item_name']) ?></small>
                            <span class="badge bg-light text-dark">₹<?= number_format($item['item_price'], 2) ?></span>
                        </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                        <p class="text-muted text-center">No items found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let itemCounter = 1;
const originalData = {
    items: <?= json_encode($invoice_items) ?>,
    total: <?= $invoice['total_amount'] ?>
};

function calculateTotals() {
    let subtotal = 0;
    let itemCount = 0;
    
    document.querySelectorAll(".item-row").forEach(row => {
        const qty = parseFloat(row.querySelector(".qty")?.value) || 0;
        const price = parseFloat(row.querySelector(".price")?.value) || 0;
        const itemTotal = qty * price;
        
        if (row.querySelector(".total")) {
            row.querySelector(".total").value = itemTotal.toFixed(2);
        }
        
        if (qty > 0 && price > 0) {
            subtotal += itemTotal;
            itemCount++;
        }
    });
    
    const tax = subtotal * 0; // 0% tax for now
    const grandTotal = subtotal + tax;
    
    // Update displays
    document.getElementById("subtotal").textContent = subtotal.toFixed(2);
    document.getElementById("tax").textContent = tax.toFixed(2);
    document.getElementById("grandTotal").textContent = grandTotal.toFixed(2);
    document.getElementById("grandTotalInput").value = grandTotal.toFixed(2);
    
    // Update summary cards
    document.getElementById("itemCount").textContent = itemCount;
    document.getElementById("totalValue").textContent = "₹" + grandTotal.toFixed(2);
}

function addRow() {
    const container = document.getElementById("itemRows");
    const newRow = container.querySelector(".item-row").cloneNode(true);
    
    // Clear the values
    newRow.querySelectorAll("input, select").forEach(input => {
        if (input.type !== "button") {
            if (input.classList.contains("qty")) {
                input.value = "1";
            } else {
                input.value = "";
            }
        }
    });
    
    container.appendChild(newRow);
    updateListeners();
    calculateTotals();
}

function updateListeners() {
    // Qty/Price calculation
    document.querySelectorAll(".qty, .price").forEach(input => {
        input.removeEventListener("input", calculateTotals);
        input.addEventListener("input", calculateTotals);
    });
    
    // Remove row
    document.querySelectorAll(".remove-item").forEach(button => {
        button.onclick = function() {
            if (document.querySelectorAll(".item-row").length > 1) {
                this.closest(".item-row").remove();
                calculateTotals();
            } else {
                alert("At least one item row is required.");
            }
        };
    });
    
    // Item dropdown auto-fill price
    document.querySelectorAll(".item-select").forEach(select => {
        select.addEventListener("change", function() {
            const price = this.selectedOptions[0]?.getAttribute("data-price");
            const row = this.closest(".item-row");
            if (price) {
                row.querySelector(".price").value = parseFloat(price).toFixed(2);
                calculateTotals();
            }
        });
    });
}

function resetToOriginal() {
    if (confirm("Are you sure you want to reset all changes to the original invoice data?")) {
        location.reload();
    }
}

function duplicateInvoice() {
    if (confirm("Create a new invoice with the same details?")) {
        // Show loading state
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
        
        // Get current form data
        const form = document.getElementById("editInvoiceForm");
        const formData = new FormData(form);
        
        // Create a form to submit to invoice_form.php
        const newForm = document.createElement('form');
        newForm.method = 'GET';
        newForm.action = 'invoice_form.php';
        newForm.style.display = 'none';
        
        // Add a flag to indicate this is a duplicate
        const duplicateFlag = document.createElement('input');
        duplicateFlag.type = 'hidden';
        duplicateFlag.name = 'duplicate_from';
        duplicateFlag.value = '<?= $invoice_id ?>';
        newForm.appendChild(duplicateFlag);
        
        document.body.appendChild(newForm);
        
        // Reset button after short delay
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            newForm.submit();
        }, 1000);
    }
}

// Form submission handling
document.getElementById("editInvoiceForm").addEventListener("submit", function(e) {
    const grandTotal = parseFloat(document.getElementById("grandTotalInput").value) || 0;
    const customerName = document.querySelector("input[name='customer_name']").value.trim();
    const customerContact = document.querySelector("input[name='customer_contact']").value.trim();
    const billAddress = document.querySelector("textarea[name='bill_address']").value.trim();
    
    // Validate required fields
    if (!customerName) {
        e.preventDefault();
        alert("Customer name is required.");
        document.querySelector("input[name='customer_name']").focus();
        return false;
    }
    
    if (!customerContact) {
        e.preventDefault();
        alert("Customer contact is required.");
        document.querySelector("input[name='customer_contact']").focus();
        return false;
    }
    
    if (!billAddress) {
        e.preventDefault();
        alert("Bill address is required.");
        document.querySelector("textarea[name='bill_address']").focus();
        return false;
    }
    
    if (grandTotal <= 0) {
        e.preventDefault();
        alert("Please add at least one item with valid quantity and price.");
        return false;
    }
    
    // Check if anything has changed
    const hasChanges = confirm("Are you sure you want to update this invoice with the current changes?");
    if (!hasChanges) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector("button[type='submit']");
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
});

// Initialize
document.addEventListener("DOMContentLoaded", () => {
    updateListeners();
    calculateTotals();
});
</script>

<?php include 'layouts/footer.php'; ?>
