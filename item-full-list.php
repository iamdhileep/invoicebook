<?php include 'db.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>📦 Item List - BillBook Pro</title>
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- DataTables + Export CSS with Professional Styling -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
  
  <!-- Professional CSS -->
  <link href="assets/css/dashboard-modern.css" rel="stylesheet">
  
  <style>
    body {
      font-family: var(--font-family);
      background: var(--gray-50);
      color: var(--gray-800);
    }
    
    .page-header {
      background: var(--gradient-card);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-sm);
      padding: var(--space-6);
      margin-bottom: var(--space-6);
      border: 1px solid var(--gray-200);
    }
    
    .page-title {
      color: var(--gray-900);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: var(--space-2);
      margin-bottom: var(--space-2);
    }
    
    .page-title i {
      color: var(--primary);
    }
    
    .item-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: var(--radius-lg);
      border: 2px solid var(--gray-200);
      transition: var(--transition-base);
    }
    
    .item-image:hover {
      transform: scale(1.1);
      border-color: var(--primary);
      box-shadow: var(--shadow-md);
    }
    
    .summary-card {
      background: var(--gradient-primary);
      color: var(--white);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-md);
      transition: var(--transition-base);
    }
    
    .summary-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }
  </style>
</head>
<body>

<div class="container-fluid mt-4">
  <!-- Professional Page Header -->
  <div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="page-title">
          <i class="bi bi-box-seam-fill"></i>
          Complete Item Inventory
        </h4>
        <p class="text-muted mb-0">Manage and export your complete product catalog with advanced filtering</p>
      </div>
      <div class="d-flex gap-2">
        <a href="add_item.php" class="btn btn-primary">
          <i class="bi bi-plus-circle me-2"></i>Add New Item
        </a>
        <a href="pages/dashboard/dashboard.php" class="btn btn-outline-primary">
          <i class="bi bi-house me-2"></i>Dashboard
        </a>
      </div>
    </div>
  </div>

  <!-- Professional Filter Section -->
  <div class="card mb-4">
    <div class="card-header">
      <h6 class="card-title mb-0">
        <i class="bi bi-funnel me-2"></i>Filters & Search
      </h6>
    </div>
    <div class="card-body">
      <!-- Category Filter -->
      <?php
      $catResult = mysqli_query($conn, "SELECT DISTINCT category FROM items WHERE category IS NOT NULL ORDER BY category");
      ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Filter by Category</label>
          <select id="categoryFilter" class="form-select">
            <option value="">🔍 All Categories</option>
            <?php while ($cat = mysqli_fetch_assoc($catResult)): ?>
              <option value="<?= htmlspecialchars($cat['category']) ?>">
                <?= htmlspecialchars($cat['category']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Stock Status</label>
          <select id="stockFilter" class="form-select">
            <option value="">All Items</option>
            <option value="in-stock">In Stock</option>
            <option value="low-stock">Low Stock (≤ 10)</option>
            <option value="out-of-stock">Out of Stock</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Price Range</label>
          <select id="priceFilter" class="form-select">
            <option value="">All Prices</option>
            <option value="0-100">₹0 - ₹100</option>
            <option value="100-500">₹100 - ₹500</option>
            <option value="500-1000">₹500 - ₹1,000</option>
            <option value="1000+">₹1,000+</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Professional Item Table -->
  <div class="table-container">
    <table id="itemTable" class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Image</th>
          <th>Item Name</th>
          <th>Category</th>
          <th>Price (₹)</th>
          <th>Stock</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $result = mysqli_query($conn, "SELECT * FROM items ORDER BY id DESC");
        while ($row = mysqli_fetch_assoc($result)) {
            $image = !empty($row['image_path']) ? $row['image_path'] : 'no-image.png';
            $stockClass = $row['stock'] <= 0 ? 'text-danger' : ($row['stock'] <= 10 ? 'text-warning' : 'text-success');
            $stockBadgeClass = $row['stock'] <= 0 ? 'badge-danger' : ($row['stock'] <= 10 ? 'badge-warning' : 'badge-success');
            
            echo "<tr>
              <td><span class='badge badge-secondary'>{$row['id']}</span></td>
              <td><img src='uploads/{$image}' class='item-image' alt='Item Image'></td>
              <td>
                <div class='fw-semibold'>" . htmlspecialchars($row['item_name']) . "</div>
              </td>
              <td>
                <span class='badge badge-info'>" . htmlspecialchars($row['category'] ?: 'Uncategorized') . "</span>
              </td>
              <td class='fw-bold text-primary'>₹ " . number_format($row['item_price'], 2) . "</td>
              <td>
                <span class='badge {$stockBadgeClass}'>" . htmlspecialchars($row['stock']) . "</span>
              </td>
              <td>
                <div class='btn-group btn-group-sm' role='group'>
                  <a href='edit_item.php?id={$row['id']}' class='btn btn-outline-primary' title='Edit Item'>
                    <i class='bi bi-pencil-square'></i>
                  </a>
                  <a href='delete_item.php?id={$row['id']}' class='btn btn-outline-danger' 
                     onclick='return confirm(\"Are you sure you want to delete this item?\")' title='Delete Item'>
                    <i class='bi bi-trash'></i>
                  </a>
                </div>
              </td>
            </tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

  <!-- Professional Summary Section -->
  <?php
  $totalRes = mysqli_query($conn, "SELECT 
    COUNT(*) as total_items,
    SUM(stock) as total_stock, 
    SUM(item_price * stock) as total_value,
    COUNT(CASE WHEN stock <= 0 THEN 1 END) as out_of_stock,
    COUNT(CASE WHEN stock <= 10 AND stock > 0 THEN 1 END) as low_stock
    FROM items");
  $totals = mysqli_fetch_assoc($totalRes);
  ?>
  
  <div class="row mt-4">
    <div class="col-md-12">
      <div class="summary-card card text-center">
        <div class="card-body">
          <div class="row">
            <div class="col-md-2">
              <h5 class="mb-0"><?= $totals['total_items'] ?? 0 ?></h5>
              <small>Total Items</small>
            </div>
            <div class="col-md-2">
              <h5 class="mb-0"><?= $totals['total_stock'] ?? 0 ?></h5>
              <small>Total Stock</small>
            </div>
            <div class="col-md-3">
              <h5 class="mb-0">₹ <?= number_format($totals['total_value'] ?? 0, 2) ?></h5>
              <small>Total Inventory Value</small>
            </div>
            <div class="col-md-2">
              <h5 class="mb-0 text-warning"><?= $totals['low_stock'] ?? 0 ?></h5>
              <small>Low Stock Items</small>
            </div>
            <div class="col-md-2">
              <h5 class="mb-0 text-danger"><?= $totals['out_of_stock'] ?? 0 ?></h5>
              <small>Out of Stock</small>
            </div>
            <div class="col-md-1">
              <a href="pages/dashboard/dashboard.php" class="btn btn-light btn-sm">
                <i class="bi bi-house"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Enhanced Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>

<script>
$(document).ready(function() {
    // Professional DataTable initialization
    var table = $('#itemTable').DataTable({
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-5"i><"col-sm-7"p>>' +
             '<"row"<"col-sm-12 text-center mt-3"B>>',
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        responsive: true,
        order: [[0, 'desc']], // Sort by ID descending
        columnDefs: [
            { orderable: false, targets: [1, -1] }, // Disable sorting for image and actions
            { className: "text-center", targets: [0, 1, 4, 5, -1] }
        ],
        buttons: [
            {
                extend: 'copy',
                text: '<i class="bi bi-clipboard me-1"></i>Copy',
                className: 'btn btn-outline-primary btn-sm'
            },
            {
                extend: 'excel',
                text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel',
                className: 'btn btn-outline-success btn-sm',
                title: 'Item Inventory Report'
            },
            {
                extend: 'pdf',
                text: '<i class="bi bi-file-earmark-pdf me-1"></i>PDF',
                className: 'btn btn-outline-danger btn-sm',
                title: 'Item Inventory Report'
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer me-1"></i>Print',
                className: 'btn btn-outline-secondary btn-sm'
            }
        ],
        language: {
            search: "Search items:",
            lengthMenu: "Show _MENU_ items per page",
            info: "Showing _START_ to _END_ of _TOTAL_ items",
            infoEmpty: "No items found",
            infoFiltered: "(filtered from _MAX_ total items)",
            emptyTable: "No items available in inventory",
            zeroRecords: "No matching items found"
        },
        drawCallback: function() {
            // Re-apply Bootstrap classes after table redraw
            $('.btn-group').each(function() {
                $(this).find('.btn').removeClass('btn-sm').addClass('btn-sm');
            });
        }
    });

    // Professional filter functionality
    $('#categoryFilter').on('change', function() {
        const value = this.value;
        if (value === '') {
            table.column(3).search('').draw();
        } else {
            table.column(3).search('^' + value + '$', true, false).draw();
        }
        showToast('Filter applied successfully!', 'success', 2000);
    });

    $('#stockFilter').on('change', function() {
        const value = this.value;
        table.column(5).search('').draw(); // Clear first
        
        if (value === 'in-stock') {
            table.column(5).search('^(?!0$).*', true, false).draw();
        } else if (value === 'low-stock') {
            table.column(5).search('^([1-9]|10)$', true, false).draw();
        } else if (value === 'out-of-stock') {
            table.column(5).search('^0$', true, false).draw();
        }
        showToast('Stock filter applied!', 'info', 2000);
    });

    $('#priceFilter').on('change', function() {
        const value = this.value;
        if (value === '') {
            table.column(4).search('').draw();
        } else {
            let min = 0, max = 999999;
            if (value === '0-100') { min = 0; max = 100; }
            else if (value === '100-500') { min = 100; max = 500; }
            else if (value === '500-1000') { min = 500; max = 1000; }
            else if (value === '1000+') { min = 1000; max = 999999; }
            
            // Custom search function for price range
            $.fn.dataTable.ext.search.pop(); // Remove previous search function if any
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'itemTable') return true;
                const price = parseFloat(data[4].replace(/[₹,\s]/g, '')) || 0;
                return price >= min && price <= max;
            });
            table.draw();
        }
        showToast('Price filter applied!', 'info', 2000);
    });

    // Professional toast notification function
    function showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            animation: slideInRight 0.3s ease forwards;
            max-width: 350px;
        `;
        
        const iconMap = {
            success: 'check-circle',
            info: 'info-circle',
            warning: 'exclamation-triangle',
            error: 'x-circle'
        };
        
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="bi bi-${iconMap[type]}" style="color: var(--${type === 'error' ? 'danger' : type});"></i>
                <span style="color: var(--gray-800); font-size: var(--text-sm);">${message}</span>
            </div>
        `;
        
        if (type === 'success') toast.style.borderLeft = '4px solid var(--success)';
        else if (type === 'error') toast.style.borderLeft = '4px solid var(--danger)';
        else if (type === 'warning') toast.style.borderLeft = '4px solid var(--warning)';
        else toast.style.borderLeft = '4px solid var(--info)';
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // Initialize tooltips
    $('[title]').each(function() {
        $(this).attr('data-bs-toggle', 'tooltip');
    });
    
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Show loading toast on page load
    showToast('Item inventory loaded successfully!', 'success', 2000);
});
</script>

</body>
</html>
