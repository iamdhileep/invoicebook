<?php
include 'db.php';
$itemQuery = $conn->query("SELECT * FROM items ORDER BY item_name ASC");
?>

<div class="row mb-2 item-row">
    <div class="col">
        <select name="item_name[]" class="form-select item-select" required>
            <option value="">-- Select Item --</option>
            <?php while($item = $itemQuery->fetch_assoc()): ?>
                <option value="<?= $item['item_name'] ?>" data-price="<?= $item['item_price'] ?>">
                    <?= $item['item_name'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col">
        <input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required>
    </div>
    <div class="col">
        <input type="number" name="item_price[]" placeholder="Price" class="form-control price" required>
    </div>
    <div class="col">
        <input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly>
    </div>
    <div class="col-auto">
        <button type="button" class="btn btn-danger remove-item">X</button>
    </div>
</div>
<script>
function updateListeners() {
    // Qty/Price calculation
    document.querySelectorAll('.qty, .price').forEach(input => {
        input.removeEventListener('input', calculateTotals);
        input.addEventListener('input', calculateTotals);
    });

    // Remove row
    document.querySelectorAll('.remove-item').forEach(button => {
        button.onclick = function () {
            this.closest('.item-row').remove();
            calculateTotals();
        };
    });

    // Item dropdown auto-fill price
    document.querySelectorAll('.item-select').forEach(select => {
        select.onchange = function () {
            const selected = this.options[this.selectedIndex];
            const price = selected.getAttribute('data-price');
            const row = this.closest('.item-row');
            row.querySelector('.price').value = price;
            calculateTotals();
        };
    });
}


</script>