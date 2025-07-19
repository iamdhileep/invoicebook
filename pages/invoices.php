<?php
session_start();
if (!isset($_SESSION['admin'])) {
  header("Location: ../login.php");
  exit;
}
include '../db.php';
include '../config.php';
include '../includes/header.php';

// Get items for dropdown
$itemOptions = $conn->query("SELECT * FROM items ORDER BY id DESC");
$itemsForJS = $conn->query("SELECT * FROM items ORDER BY id DESC");

?>

<div class="page-header">
  <h1 class="page-title">
    <i class="bi bi-receipt me-2"></i>
    Customer Invoice
  </h1>
  <p class="text-muted mb-0">Create and manage customer invoices</p>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-file-earmark-text me-2"></i>
          Create New Invoice
        </h5>
      </div>
      <div class="card-body">
        <form action="../save_invoice.php" method="POST">
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="customer_name" class="form-label">Customer Name</label>
              <input type="text" name="customer_name" id="customer_name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label for="customer_contact" class="form-label">Customer Contact</label>
              <input type="tel" name="customer_contact" id="customer_contact" class="form-control" required>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="invoice_date" class="form-label">Invoice Date</label>
              <input type="date" name="invoice_date" id="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
              <label for="due_date" class="form-label">Due Date</label>
              <input type="date" name="due_date" id="due_date" class="form-control">
            </div>
          </div>

          <div class="mb-4">
            <label for="bill_address" class="form-label">Bill To Address</label>
            <textarea name="bill_address" id="bill_address" class="form-control" rows="3" required></textarea>
          </div>

          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <label class="form-label mb-0">Items</label>
              <div>
                <button type="button" onclick="addRow()" class="btn btn-success btn-sm">
                  <i class="bi bi-plus-circle me-1"></i>
                  Add Item
                </button>
                <button type="button" onclick="clearTable()" class="btn btn-danger btn-sm">
                  <i class="bi bi-trash me-1"></i>
                  Clear All
                </button>
              </div>
            </div>

            <div id="itemRows">
              <div class="row g-2 mb-2 item-row">
                <div class="col-md-4">
                  <select name="item_name[]" class="form-select item-select" required>
                    <option value="">-- Select Item --</option>
                    <?php while ($item = $itemOptions->fetch_assoc()): ?>
                      <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                        <?= htmlspecialchars($item['item_name']) ?>
                      </option>
                    <?php endwhile; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required min="1">
                </div>
                <div class="col-md-2">
                  <input type="number" name="item_price[]" placeholder="Price" class="form-control price" required min="0" step="0.01">
                </div>
                <div class="col-md-2">
                  <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-danger btn-sm remove-item w-100">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="discount" class="form-label">Discount (%)</label>
              <input type="number" name="discount" id="discount" class="form-control" value="0" min="0" max="100" step="0.01">
            </div>
            <div class="col-md-6">
              <label for="tax" class="form-label">Tax (%)</label>
              <input type="number" name="tax" id="tax" class="form-control" value="0" min="0" max="100" step="0.01">
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="subtotal" class="form-label">Subtotal</label>
              <input type="text" name="subtotal" id="subtotal" class="form-control" readonly>
            </div>
            <div class="col-md-6">
              <label for="grandTotal" class="form-label">Grand Total</label>
              <input type="text" name="grand_total" id="grandTotal" class="form-control" readonly>
            </div>
          </div>

          <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save me-2"></i>
              Save Invoice
            </button>
            <a href="../invoice_history.php" class="btn btn-secondary">
              <i class="bi bi-clock-history me-2"></i>
              View History
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-info-circle me-2"></i>
          Invoice Summary
        </h5>
      </div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span>Subtotal:</span>
          <span id="displaySubtotal">₹ 0.00</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Discount:</span>
          <span id="displayDiscount">₹ 0.00</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>Tax:</span>
          <span id="displayTax">₹ 0.00</span>
        </div>
        <hr>
        <div class="d-flex justify-content-between">
          <strong>Total:</strong>
          <strong id="displayTotal">₹ 0.00</strong>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="bi bi-lightning me-2"></i>
          Quick Actions
        </h5>
      </div>
      <div class="card-body">
        <div class="d-grid gap-2">
          <a href="../invoice_history.php" class="btn btn-outline-primary">
            <i class="bi bi-list-ul me-2"></i>
            View All Invoices
          </a>
          <a href="<?php echo $base_url; ?>pages/products.php" class="btn btn-outline-success">
            <i class="bi bi-box-seam me-2"></i>
            Manage Products
          </a>
          <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-speedometer2 me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function clearTable() {
    const container = document.getElementById('itemRows');
    container.innerHTML = `
        <div class="row g-2 mb-2 item-row">
            <div class="col-md-4">
                <select name="item_name[]" class="form-select item-select" required>
                    <option value="">-- Select Item --</option>
                    <?php
                    mysqli_data_seek($itemsForJS, 0);
                    while ($item = $itemsForJS->fetch_assoc()) {
                        echo "<option value='{$item['item_name']}' data-price='{$item['item_price']}'>{$item['item_name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required min="1">
            </div>
            <div class="col-md-2">
                <input type="number" name="item_price[]" placeholder="Price" class="form-control price" required min="0" step="0.01">
            </div>
            <div class="col-md-2">
                <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm remove-item w-100">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
    `;
    updateListeners();
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.price')?.value) || 0;
        const itemTotal = qty * price;
        if (row.querySelector('.total')) {
            row.querySelector('.total').value = itemTotal.toFixed(2);
        }
        subtotal += itemTotal;
    });

    const discount = parseFloat(document.getElementById('discount')?.value) || 0;
    const tax = parseFloat(document.getElementById('tax')?.value) || 0;

    const discountAmount = (subtotal * discount) / 100;
    const taxAmount = ((subtotal - discountAmount) * tax) / 100;
    const grandTotal = subtotal - discountAmount + taxAmount;

    // Update form fields
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('grandTotal').value = grandTotal.toFixed(2);

    // Update display
    document.getElementById('displaySubtotal').textContent = '₹ ' + subtotal.toFixed(2);
    document.getElementById('displayDiscount').textContent = '₹ ' + discountAmount.toFixed(2);
    document.getElementById('displayTax').textContent = '₹ ' + taxAmount.toFixed(2);
    document.getElementById('displayTotal').textContent = '₹ ' + grandTotal.toFixed(2);
}

function updateListeners() {
    // Update price when item is selected
    document.querySelectorAll('.item-select').forEach(select => {
        select.addEventListener('change', function() {
            const price = this.selectedOptions[0].getAttribute('data-price');
            const row = this.closest('.item-row');
            row.querySelector('.price').value = price || 0;
            row.querySelector('.qty').value = 1;
            calculateTotals();
        });
    });

    // Qty/Price/Discount/Tax changes
    document.querySelectorAll('.qty, .price, #discount, #tax').forEach(input => {
        input.removeEventListener('input', calculateTotals);
        input.addEventListener('input', calculateTotals);
    });

    // Remove row
    document.querySelectorAll('.remove-item').forEach(button => {
        button.onclick = function() {
            if (document.querySelectorAll('.item-row').length > 1) {
                this.closest('.item-row').remove();
                calculateTotals();
            } else {
                alert('At least one item is required');
            }
        };
    });
}

function addRow() {
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 item-row';
    row.innerHTML = `
        <div class="col-md-4">
            <select name="item_name[]" class="form-select item-select" required>
                <option value="">-- Select Item --</option>
                <?php
                mysqli_data_seek($itemsForJS, 0);
                while ($item = $itemsForJS->fetch_assoc()) {
                    echo "<option value='{$item['item_name']}' data-price='{$item['item_price']}'>{$item['item_name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-2">
            <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required min="1">
        </div>
        <div class="col-md-2">
            <input type="number" name="item_price[]" placeholder="Price" class="form-control price" required min="0" step="0.01">
        </div>
        <div class="col-md-2">
            <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger btn-sm remove-item w-100">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `;

    document.getElementById('itemRows').appendChild(row);
    updateListeners();
    calculateTotals();
}

document.addEventListener('DOMContentLoaded', () => {
    updateListeners();
    calculateTotals();
});
</script>

<?php include '../includes/footer.php'; ?>