<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
$page_title = 'Create Invoice';

include 'layouts/header.php';
include 'layouts/sidebar.php';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create Invoice</h1>
                <p class="text-muted">Generate a new customer invoice</p>
            </div>
            <div>
                <a href="invoice_history.php" class="btn btn-outline-primary">
                    <i class="bi bi-clock-history"></i> Invoice History
                </a>
            </div>
        </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2"></i>Invoice Details</h5>
                </div>
                <div class="card-body">
                    <form action="save_invoice.php" method="POST" id="invoiceForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Contact *</label>
                                <input type="text" name="customer_contact" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Bill To Address *</label>
                                <textarea name="bill_address" class="form-control" rows="2" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Invoice Date *</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
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
                                        <tr class="item-row">
                                            <td>
                                                <select name="item_name[]" class="form-select item-select" required>
                                                    <option value="">-- Select Item --</option>
                                                    <?php 
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
                                <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                    <i class="bi bi-arrow-clockwise"></i> Clear Form
                                </button>
                            </div>
                            <div>
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Invoice
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="invoice_history.php" class="btn btn-outline-primary">
                            <i class="bi bi-list-ul"></i> View All Invoices
                        </a>
                        <a href="add_item.php" class="btn btn-outline-success">
                            <i class="bi bi-plus-circle"></i> Add New Product
                        </a>
                        <a href="products.php" class="btn btn-outline-warning">
                            <i class="bi bi-box-seam"></i> Manage Products
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="previewInvoice()">
                            <i class="bi bi-eye"></i> Preview Invoice
                        </button>
                    </div>
                </div>
            </div>

            <!-- Invoice Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2"></i>Invoice Summary</h6>
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
                    <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Items</h6>
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

<style>
/* Reduced box shadow for all cards on this page */
.card {
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.03), 0 1px 1px -1px rgb(0 0 0 / 0.04) !important;
}

.card:hover {
    box-shadow: 0 2px 4px -1px rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04) !important;
    transform: translateY(-1px);
}

/* Subtle shadow for summary cards in sidebar */
.card.bg-primary,
.card.bg-success {
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
}

/* Very light shadow for the total calculation card */
.card.bg-light {
    box-shadow: 0 1px 1px 0 rgb(0 0 0 / 0.02) !important;
}
</style>

<script>
let itemCounter = 1;

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

function clearForm() {
    if (confirm("Are you sure you want to clear the entire form?")) {
        document.getElementById("invoiceForm").reset();
        
        // Reset to single row
        const container = document.getElementById("itemRows");
        const rows = container.querySelectorAll(".item-row");
        for (let i = 1; i < rows.length; i++) {
            rows[i].remove();
        }
        
        // Reset date to today
        document.querySelector("input[name='invoice_date']").value = "<?= date('Y-m-d') ?>";
        
        calculateTotals();
    }
}

function previewInvoice() {
    const form = document.getElementById("invoiceForm");
    const formData = new FormData(form);
    
    // Basic validation
    if (!formData.get("customer_name")) {
        alert("Please enter customer name");
        return;
    }
    
    // Open preview in new window
    const preview = window.open("", "InvoicePreview", "width=800,height=600");
    preview.document.write(`
        <html>
        <head>
            <title>Invoice Preview</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="p-4">
            <div class="container">
                <h2>Invoice Preview</h2>
                <hr>
                <div class="row">
                    <div class="col-6">
                        <strong>Customer:</strong> ${formData.get("customer_name")}<br>
                        <strong>Contact:</strong> ${formData.get("customer_contact")}<br>
                        <strong>Address:</strong> ${formData.get("bill_address")}
                    </div>
                    <div class="col-6 text-end">
                        <strong>Date:</strong> ${formData.get("invoice_date")}<br>
                        <strong>Total:</strong> ₹${document.getElementById("grandTotal").textContent}
                    </div>
                </div>
                <p class="mt-3 text-muted">This is a preview. Close this window to continue editing.</p>
            </div>
        </body>
        </html>
    `);
}

// Form submission handling
document.getElementById("invoiceForm").addEventListener("submit", function(e) {
    const grandTotal = parseFloat(document.getElementById("grandTotalInput").value) || 0;
    if (grandTotal <= 0) {
        e.preventDefault();
        alert("Please add at least one item with valid quantity and price.");
        return false;
    }
});

// Initialize
document.addEventListener("DOMContentLoaded", () => {
    updateListeners();
    calculateTotals();
});
</script>

    </div>
</div>

<?php include 'layouts/footer.php'; ?>