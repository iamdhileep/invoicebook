<?php
session_start();
if (!isset($_SESSION['admin'])) {
    echo "Please login first";
    exit;
}

include 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Expense Delete</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h3>Test Expense Delete Functionality</h3>
        
        <!-- Test 1: Check if delete_expense.php is accessible -->
        <div class="card mb-3">
            <div class="card-header">Test 1: File Accessibility</div>
            <div class="card-body">
                <p><strong>delete_expense.php exists:</strong> <?= file_exists('delete_expense.php') ? '✅ YES' : '❌ NO' ?></p>
                <p><strong>File readable:</strong> <?= is_readable('delete_expense.php') ? '✅ YES' : '❌ NO' ?></p>
            </div>
        </div>

        <!-- Test 2: Check expenses in database -->
        <div class="card mb-3">
            <div class="card-header">Test 2: Sample Expenses</div>
            <div class="card-body">
                <?php
                $expenses = $conn->query("SELECT * FROM expenses ORDER BY id DESC LIMIT 5");
                if ($expenses && $expenses->num_rows > 0):
                ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($expense = $expenses->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $expense['id'] ?></td>
                                    <td><?= $expense['expense_date'] ?></td>
                                    <td><?= htmlspecialchars($expense['category']) ?></td>
                                    <td>₹<?= number_format($expense['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($expense['description'] ?? $expense['note'] ?? '') ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger test-delete" 
                                                data-id="<?= $expense['id'] ?>">
                                            Test Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No expenses found. <a href="pages/expenses/expenses.php">Add some expenses first</a>.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test 3: AJAX Test -->
        <div class="card mb-3">
            <div class="card-header">Test 3: AJAX Test</div>
            <div class="card-body">
                <button id="testAjax" class="btn btn-primary">Test AJAX Call</button>
                <div id="ajaxResult" class="mt-3"></div>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>Instructions:</strong>
            <ol>
                <li>Open browser console (F12) to see debug messages</li>
                <li>Try clicking "Test Delete" on any expense</li>
                <li>Check console for any JavaScript errors</li>
                <li>Try the AJAX test button</li>
            </ol>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        console.log('Test page loaded, jQuery version:', $.fn.jquery);
        
        // Test delete functionality
        $(document).on('click', '.test-delete', function() {
            const expenseId = $(this).data('id');
            console.log('Testing delete for expense ID:', expenseId);
            
            if (confirm('Test delete expense ID ' + expenseId + '?')) {
                $.post('delete_expense.php', {id: expenseId}, function(response) {
                    console.log('Delete response:', response);
                    if (response.success) {
                        alert('✅ Delete successful: ' + response.message);
                        location.reload();
                    } else {
                        alert('❌ Delete failed: ' + response.message);
                    }
                }, 'json').fail(function(xhr, status, error) {
                    console.error('AJAX failed:', xhr.responseText);
                    alert('❌ AJAX Error: ' + error + '\nStatus: ' + status + '\nResponse: ' + xhr.responseText);
                });
            }
        });
        
        // Test AJAX connectivity
        $('#testAjax').click(function() {
            console.log('Testing AJAX connectivity...');
            $('#ajaxResult').html('<div class="spinner-border spinner-border-sm"></div> Testing...');
            
            $.post('delete_expense.php', {id: 99999}, function(response) {
                console.log('AJAX test response:', response);
                $('#ajaxResult').html('<div class="alert alert-success">✅ AJAX works! Response: ' + JSON.stringify(response) + '</div>');
            }, 'json').fail(function(xhr, status, error) {
                console.error('AJAX test failed:', xhr.responseText);
                $('#ajaxResult').html('<div class="alert alert-danger">❌ AJAX failed: ' + error + '<br>Status: ' + status + '<br>Response: ' + xhr.responseText + '</div>');
            });
        });
    });
    </script>
</body>
</html>