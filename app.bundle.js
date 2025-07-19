
// DataTables Initialization
$(document).ready(function() {
    $('#itemTable').DataTable({
        pageLength: 10,
        lengthMenu: [5, 10, 25, 50, 100],
        responsive: true
    });
    $('#empTable').DataTable({
        pageLength: 5,
        lengthMenu: [5, 10, 25],
        responsive: true
    });

    if (window.location.hash === "#emp-manager") {
        const trigger = new bootstrap.Tab(document.querySelector('#emp-manager-tab'));
        trigger.show();
    }

    $('.delete-btn').click(function() {
        const btn = $(this);
        const id = btn.data('id');

        if (confirm("Are you sure you want to delete this employee?")) {
            $.ajax({
                url: 'employee-tabs.php',
                method: 'POST',
                data: {
                    ajax_delete: 1,
                    id: id
                },
                success: function(res) {
                    if (res.trim() === 'success') {
                        btn.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert("Failed to delete employee.");
                    }
                },
                error: function() {
                    alert("AJAX error occurred.");
                }
            });
        }
    });

    updateListeners();
    calculateTotals();
});

// Item Calculation & Row Management
function clearTable() {
    const container = document.getElementById('itemRows');
    container.innerHTML = `
        <label>Items</label>
        <div class="row mb-2 item-row">
            <div class="col"><input type="text" name="item_name[]" placeholder="Item Name" class="form-control" required></div>
            <div class="col"><input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required></div>
            <div class="col"><input type="number" name="item_price[]" placeholder="Price" class="form-control price" required></div>
            <div class="col"><input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly></div>
            <div class="col-auto">
                <button type="button" class="btn btn-danger remove-item">X</button>
            </div>
        </div>
    `;
    updateListeners();
    calculateTotals();
}

function calculateTotals() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.price')?.value) || 0;
        const itemTotal = qty * price;
        if (row.querySelector('.total')) {
            row.querySelector('.total').value = itemTotal.toFixed(2);
        }
        total += itemTotal;
    });

    const grandTotalInput = document.getElementById('grandTotal');
    if (grandTotalInput) {
        grandTotalInput.value = total.toFixed(2);
    }
}

function updateListeners() {
    document.querySelectorAll('.item-select').forEach(select => {
        select.addEventListener('change', function() {
            const price = this.selectedOptions[0].getAttribute('data-price');
            const row = this.closest('.item-row');
            row.querySelector('.price').value = price || 0;
            row.querySelector('.qty').value = 1;
            row.querySelector('.total').value = price || 0;
            calculateTotals();
        });
    });

    document.querySelectorAll('.qty, .price').forEach(input => {
        input.removeEventListener('input', calculateTotals);
        input.addEventListener('input', calculateTotals);
    });

    document.querySelectorAll('.remove-item').forEach(button => {
        button.onclick = function() {
            this.closest('.item-row').remove();
            calculateTotals();
        };
    });
}

function addRow() {
    const row = document.createElement('div');
    row.className = 'row mb-2 item-row';
    row.innerHTML = `
        <div class="col">
            <select name="item_name[]" class="form-select item-select" required>
                <option value="">-- Select Item --</option>
            </select>
        </div>
        <div class="col"><input type="number" name="item_qty[]" placeholder="Qty" class="form-control qty" required></div>
        <div class="col"><input type="number" name="item_price[]" placeholder="Price" class="form-control price" required></div>
        <div class="col"><input type="text" name="item_total[]" placeholder="Total" class="form-control total" readonly></div>
        <div class="col-auto">
            <button type="button" class="btn btn-danger remove-item">X</button>
        </div>`;
    document.getElementById('itemRows').appendChild(row);
    updateListeners();
    calculateTotals();
}

// Search & Filter
const searchInput = document.getElementById('itemSearch');
const categoryFilter = document.getElementById('categoryFilter');
const selectAll = document.getElementById('selectAll');
const deleteSelected = document.getElementById('deleteSelected');

function filterItems() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCategory = categoryFilter.value;

    document.querySelectorAll('#itemTable tbody tr').forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        const category = row.getAttribute('data-category');
        const matchesName = name.includes(searchTerm);
        const matchesCategory = !selectedCategory || category === selectedCategory;

        row.style.display = matchesName && matchesCategory ? '' : 'none';
    });
}

if (searchInput && categoryFilter) {
    searchInput.addEventListener('input', filterItems);
    categoryFilter.addEventListener('change', filterItems);
}

if (selectAll) {
    selectAll.addEventListener('change', () => {
        document.querySelectorAll("#itemTable tbody input[type='checkbox']").forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    });
}

if (deleteSelected) {
    deleteSelected.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to delete selected items?")) {
            document.getElementById('bulkDeleteForm').submit();
        }
    });
}

// Loader
window.addEventListener('load', function() {
    document.getElementById('ajax-loader').style.display = 'none';
});

// Store last active tab
document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(button => {
    button.addEventListener('shown.bs.tab', function(e) {
        const activeTab = e.target.getAttribute('data-bs-target');
        localStorage.setItem('activeTab', activeTab);
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const lastTab = localStorage.getItem('activeTab');
    if (lastTab) {
        const tabTrigger = document.querySelector(`button[data-bs-target="${lastTab}"]`);
        if (tabTrigger) {
            new bootstrap.Tab(tabTrigger).show();
        }
    }
});
