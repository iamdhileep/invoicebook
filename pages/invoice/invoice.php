<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Create Invoice';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY item_name ASC");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Create Invoice</h1>
            <p class="text-muted">Generate a new customer invoice</p>
        </div>
        <div>
            <a href="../../invoice_history.php" class="btn btn-outline-primary">
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
                    <form action="../../save_invoice.php" method="POST" id="invoiceForm">
                        <!-- Customer Information -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name *</label>
                                <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Customer Contact *</label>
                                <input type="text" name="customer_contact" class="form-control" placeholder="Phone number or email" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Bill To Address *</label>
                                <textarea name="bill_address" class="form-control" rows="2" placeholder="Customer billing address" required></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Invoice Date *</label>
                                <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <hr>

                        <!-- Items Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Invoice Items</h6>
                                <div>
                                    <button type="button" onclick="addRow()" class="btn btn-sm btn-success">
                                        <i class="bi bi-plus-circle"></i> Add Item
                                    </button>
                                    <button type="button" onclick="clearTable()" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> Clear All
                                    </button>
                                </div>
                            </div>

                            <div id="itemRows">
                                <div class="row g-2 mb-2 item-row">
                                    <div class="col-md-4">
                                        <label class="form-label small">Item</label>
                                        <select name="item_name[]" class="form-select item-select" required>
                                            <option value="">-- Select Item --</option>
                                            <?php while ($item = $itemOptions->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                                                    <?= htmlspecialchars($item['item_name']) ?> (₹<?= $item['item_price'] ?>)
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Qty</label>
                                        <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" min="1" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Price</label>
                                        <input type="number" name="item_price[]" placeholder="Price" class="form-control price" step="0.01" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">Total</label>
                                        <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label small">&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm d-block remove-item">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Total Section -->
                        <div class="row justify-content-end">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong>Grand Total:</strong>
                                            <div class="input-group">
                                                <span class="input-group-text">₹</span>
                                                <input type="text" name="grand_total" id="grandTotal" class="form-control text-end fw-bold" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Invoice
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../invoice_history.php" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-text"></i> View All Invoices
                        </a>
                        <a href="../../add_item.php" class="btn btn-outline-success">
                            <i class="bi bi-plus-circle"></i> Add New Product
                        </a>
                        <a href="../../item-stock.php" class="btn btn-outline-warning">
                            <i class="bi bi-boxes"></i> Check Stock
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Select items from dropdown to auto-fill prices</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Totals are calculated automatically</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>All fields marked with * are required</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i>You can add multiple items to one invoice</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = '
<script>
    function clearTable() {
        const container = document.getElementById("itemRows");
        container.innerHTML = `
            <div class="row g-2 mb-2 item-row">
                <div class="col-md-4">
                    <label class="form-label small">Item</label>
                    <select name="item_name[]" class="form-select item-select" required>
                        <option value="">-- Select Item --</option>';

// Re-fetch items for JavaScript
$itemsAgain = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
while ($item = $itemsAgain->fetch_assoc()) {
    $additional_scripts .= '<option value="' . htmlspecialchars($item['item_name']) . '" data-price="' . $item['item_price'] . '">' . htmlspecialchars($item['item_name']) . ' (₹' . $item['item_price'] . ')</option>';
}

$additional_scripts .= '
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Qty</label>
                    <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" min="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Price</label>
                    <input type="number" name="item_price[]" placeholder="Price" class="form-control price" step="0.01" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Total</label>
                    <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
                </div>
                <div class="col-md-1">
                    <label class="form-label small">&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm d-block remove-item">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        updateListeners();
        calculateTotals();
    }

    function calculateTotals() {
        let total = 0;
        document.querySelectorAll(".item-row").forEach(row => {
            const qty = parseFloat(row.querySelector(".qty")?.value) || 0;
            const price = parseFloat(row.querySelector(".price")?.value) || 0;
            const itemTotal = qty * price;
            if (row.querySelector(".total")) {
                row.querySelector(".total").value = itemTotal.toFixed(2);
            }
            total += itemTotal;
        });

        const grandTotalInput = document.getElementById("grandTotal");
        if (grandTotalInput) {
            grandTotalInput.value = total.toFixed(2);
        }
    }

    function updateListeners() {
        // Update price when item is selected
        document.querySelectorAll(".item-select").forEach(select => {
            select.addEventListener("change", function() {
                const price = this.selectedOptions[0].getAttribute("data-price");
                const row = this.closest(".item-row");
                row.querySelector(".price").value = price || 0;
                row.querySelector(".qty").value = 1;
                row.querySelector(".total").value = price || 0;
                calculateTotals();
            });
        });

        // Qty/Price changes
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
                    showAlert("At least one item is required", "warning");
                }
            };
        });
    }

    function addRow() {
        const row = document.createElement("div");
        row.className = "row g-2 mb-2 item-row";
        row.innerHTML = `
            <div class="col-md-4">
                <select name="item_name[]" class="form-select item-select" required>
                    <option value="">-- Select Item --</option>';

// Re-fetch items for add row function
$itemsForAdd = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
while ($item = $itemsForAdd->fetch_assoc()) {
    $additional_scripts .= '<option value="' . htmlspecialchars($item['item_name']) . '" data-price="' . $item['item_price'] . '">' . htmlspecialchars($item['item_name']) . ' (₹' . $item['item_price'] . ')</option>';
}

$additional_scripts .= '
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" min="1" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="item_price[]" placeholder="Price" class="form-control price" step="0.01" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-item">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;

        document.getElementById("itemRows").appendChild(row);
        updateListeners();
        calculateTotals();
    }

    document.addEventListener("DOMContentLoaded", () => {
        updateListeners();
        calculateTotals();
    });
</script>
';

include '../../layouts/footer.php';
?>