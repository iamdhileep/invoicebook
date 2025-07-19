<h4 class="mb-4">Customer Invoice</h4>
<form action="save_invoice.php" method="POST">
    <div class="mb-3">
        <label>Customer Name</label>
        <input type="text" name="customer_name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Customer Contact</label>
        <input type="text" name="customer_contact" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Bill To Address</label>
        <textarea name="bill_address" class="form-control" required></textarea>
    </div>
    <div class="mb-3">
        <label>Invoice Date</label>
        <input type="date" name="invoice_date" class="form-control" required>
    </div>

    <div id="itemRows" class="mb-3">
        <label>Items</label>
        <div class="row mb-2 item-row">
            <div class="col">
                <select name="item_name[]" class="form-select item-select" required>
                    <option value="">-- Select Item --</option>
                    <?php while ($item = $itemOptions->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($item['item_name']) ?>" data-price="<?= $item['item_price'] ?>">
                            <?= htmlspecialchars($item['item_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col"><input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required></div>
            <div class="col"><input type="number" name="item_price[]" placeholder="Price" class="form-control price" required></div>
            <div class="col"><input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly></div>
            <div class="col-auto">
                <button type="button" class="btn btn-danger remove-item">X</button>
            </div>
        </div>
    </div>

    <button type="button" onclick="addRow()" class="btn btn-secondary mb-3">Add Item</button>
    <button type="button" onclick="clearTable()" class="btn btn-danger mb-3">Clear Items</button>

    <div class="mb-3">
        <label>Grand Total</label>
        <input type="text" name="grand_total" id="grandTotal" class="form-control" readonly>
    </div>

    <button type="submit" class="btn btn-primary">Save Invoice</button>
    <a href="invoice_history.php" class="btn btn-secondary">View Invoice History</a>
</form>