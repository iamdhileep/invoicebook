<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'Create Invoice';

include '../../layouts/header.php';
include '../../layouts/sidebar.php';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
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
                    <form action="../../save_invoice.php" method="POST">
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
                            <h6>Invoice Items</h6>
                            <div id="itemRows">
                                <div class="row g-2 mb-2 item-row">
                                    <div class="col-md-4">
                                        <select name="item_name[]" class="form-select" required>
                                            <option value="">-- Select Item --</option>
                                            <?php 
                                            if ($itemOptions && mysqli_num_rows($itemOptions) > 0):
                                                while ($item = $itemOptions->fetch_assoc()): ?>
                                                <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                                                    <?= htmlspecialchars($item['item_name']) ?> (₹<?= $item['item_price'] ?>)
                                                </option>
                                            <?php endwhile;
                                            endif; ?>
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
                                        <button type="button" class="btn btn-danger btn-sm remove-item">×</button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addRow()" class="btn btn-sm btn-success">Add Item</button>
                        </div>

                        <div class="row justify-content-end">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <strong>Grand Total: ₹ <span id="grandTotal">0.00</span></strong>
                                        <input type="hidden" name="grand_total" id="grandTotalInput">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Save Invoice</button>
                            <a href="../dashboard/dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="../../invoice_history.php" class="btn btn-outline-primary">View All Invoices</a>
                        <a href="../../add_item.php" class="btn btn-outline-success">Add New Product</a>
                        <a href="../products/products.php" class="btn btn-outline-warning">Manage Products</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
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
    
    document.getElementById("grandTotal").textContent = total.toFixed(2);
    document.getElementById("grandTotalInput").value = total.toFixed(2);
}

function addRow() {
    const container = document.getElementById("itemRows");
    const newRow = container.querySelector(".item-row").cloneNode(true);
    
    // Clear the values
    newRow.querySelectorAll("input, select").forEach(input => {
        if (input.type !== "button") input.value = "";
    });
    
    container.appendChild(newRow);
    updateListeners();
}

function updateListeners() {
    document.querySelectorAll(".qty, .price").forEach(input => {
        input.removeEventListener("input", calculateTotals);
        input.addEventListener("input", calculateTotals);
    });
    
    document.querySelectorAll(".remove-item").forEach(button => {
        button.onclick = function() {
            if (document.querySelectorAll(".item-row").length > 1) {
                this.closest(".item-row").remove();
                calculateTotals();
            }
        };
    });
    
    document.querySelectorAll("select[name='item_name[]']").forEach(select => {
        select.addEventListener("change", function() {
            const price = this.selectedOptions[0].getAttribute("data-price");
            const row = this.closest(".item-row");
            if (price) {
                row.querySelector(".price").value = price;
                row.querySelector(".qty").value = 1;
                calculateTotals();
            }
        });
    });
}

document.addEventListener("DOMContentLoaded", () => {
    updateListeners();
    calculateTotals();
});
</script>

<?php include '../../layouts/footer.php'; ?>