<?php
/**
 * Journal Entries Management
 */
session_start();
require_once '../../db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_journal_entry':
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $entry_date = $_POST['entry_date'];
            $reference = mysqli_real_escape_string($conn, $_POST['reference']);
            $entries = json_decode($_POST['journal_entries'], true);
            
            // Validate that debits equal credits
            $total_debit = 0;
            $total_credit = 0;
            
            foreach ($entries as $entry) {
                $total_debit += floatval($entry['debit']);
                $total_credit += floatval($entry['credit']);
            }
            
            if (abs($total_debit - $total_credit) > 0.01) {
                echo json_encode(['success' => false, 'message' => 'Debits must equal credits']);
                exit;
            }
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert journal entry header
                $journal_query = "INSERT INTO journal_entries (description, entry_date, reference, total_amount) 
                                  VALUES ('$description', '$entry_date', '$reference', $total_debit)";
                
                if (!mysqli_query($conn, $journal_query)) {
                    throw new Exception('Error creating journal entry');
                }
                
                $journal_id = mysqli_insert_id($conn);
                
                // Insert journal entry lines
                foreach ($entries as $entry) {
                    $account_id = intval($entry['account_id']);
                    $debit = floatval($entry['debit']);
                    $credit = floatval($entry['credit']);
                    $line_description = mysqli_real_escape_string($conn, $entry['description']);
                    
                    $line_query = "INSERT INTO journal_entry_lines 
                                   (journal_entry_id, chart_account_id, debit_amount, credit_amount, description) 
                                   VALUES ($journal_id, $account_id, $debit, $credit, '$line_description')";
                    
                    if (!mysqli_query($conn, $line_query)) {
                        throw new Exception('Error creating journal entry line');
                    }
                    
                    // Update chart of accounts balance
                    $balance_change = $debit - $credit;
                    $update_balance = "UPDATE chart_of_accounts 
                                       SET current_balance = current_balance + $balance_change 
                                       WHERE id = $account_id";
                    
                    if (!mysqli_query($conn, $update_balance)) {
                        throw new Exception('Error updating account balance');
                    }
                }
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Journal entry created successfully']);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'delete_journal_entry':
            $entry_id = intval($_POST['entry_id']);
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Get journal entry lines to reverse balances
                $lines_query = "SELECT chart_account_id, debit_amount, credit_amount 
                                FROM journal_entry_lines WHERE journal_entry_id = $entry_id";
                $lines_result = mysqli_query($conn, $lines_query);
                
                while ($line = mysqli_fetch_assoc($lines_result)) {
                    $balance_change = $line['credit_amount'] - $line['debit_amount']; // Reverse the original change
                    $update_balance = "UPDATE chart_of_accounts 
                                       SET current_balance = current_balance + $balance_change 
                                       WHERE id = " . $line['chart_account_id'];
                    
                    if (!mysqli_query($conn, $update_balance)) {
                        throw new Exception('Error reversing account balance');
                    }
                }
                
                // Delete journal entry lines
                if (!mysqli_query($conn, "DELETE FROM journal_entry_lines WHERE journal_entry_id = $entry_id")) {
                    throw new Exception('Error deleting journal entry lines');
                }
                
                // Delete journal entry
                if (!mysqli_query($conn, "DELETE FROM journal_entries WHERE id = $entry_id")) {
                    throw new Exception('Error deleting journal entry');
                }
                
                mysqli_commit($conn);
                echo json_encode(['success' => true, 'message' => 'Journal entry deleted successfully']);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Get journal entries with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $where_conditions[] = "(je.description LIKE '%$search%' OR je.reference LIKE '%$search%')";
}
if (!empty($date_from)) {
    $where_conditions[] = "je.entry_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where_conditions[] = "je.entry_date <= '$date_to'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM journal_entries je $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_entries = mysqli_fetch_assoc($count_result)['total'];

// Get journal entries
$entries_query = "
    SELECT je.*, 
           (SELECT COUNT(*) FROM journal_entry_lines jel WHERE jel.journal_entry_id = je.id) as line_count
    FROM journal_entries je
    $where_clause
    ORDER BY je.entry_date DESC, je.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$entries_result = mysqli_query($conn, $entries_query);

// Get chart of accounts for the form
$accounts_query = "SELECT id, account_name, account_type FROM chart_of_accounts WHERE is_active = TRUE ORDER BY account_name";
$accounts_result = mysqli_query($conn, $accounts_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Entries - BillBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .journal-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .journal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .journal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        .debit-amount { color: #28a745; font-weight: 600; }
        .credit-amount { color: #dc3545; font-weight: 600; }
        .journal-entry-form {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .entry-line {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
        }
        .total-display {
            font-size: 1.2em;
            font-weight: 700;
        }
        .balanced { color: #28a745; }
        .unbalanced { color: #dc3545; }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../dashboard.php">
                <i class="fas fa-book me-2"></i>BillBook
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="accounts.php">
                    <i class="fas fa-chart-line me-1"></i>Accounts
                </a>
                <a class="nav-link" href="chart_of_accounts.php">
                    <i class="fas fa-list me-1"></i>Chart of Accounts
                </a>
                <a class="nav-link" href="bank_accounts.php">
                    <i class="fas fa-university me-1"></i>Bank Accounts
                </a>
                <a class="nav-link active" href="journal_entries.php">
                    <i class="fas fa-book-open me-1"></i>Journal Entries
                </a>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>Reports
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-book-open me-2"></i>Journal Entries</h2>
                <p class="text-muted">Manage double-entry bookkeeping transactions</p>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addJournalEntryModal">
                    <i class="fas fa-plus me-2"></i>New Journal Entry
                </button>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="card journal-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search description or reference..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Journal Entries List -->
        <?php if (mysqli_num_rows($entries_result) > 0): ?>
            <?php while ($entry = mysqli_fetch_assoc($entries_result)): ?>
                <div class="card journal-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($entry['description']); ?></h6>
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($entry['entry_date'])); ?>
                                <?php if (!empty($entry['reference'])): ?>
                                | <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($entry['reference']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo $entry['line_count']; ?> lines</span>
                            <strong class="total-display">₹<?php echo number_format($entry['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                    
                    <?php
                    // Get journal entry lines
                    $lines_query = "
                        SELECT jel.*, ca.account_name, ca.account_type
                        FROM journal_entry_lines jel
                        JOIN chart_of_accounts ca ON jel.chart_account_id = ca.id
                        WHERE jel.journal_entry_id = " . $entry['id'] . "
                        ORDER BY jel.id
                    ";
                    $lines_result = mysqli_query($conn, $lines_query);
                    ?>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Description</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($line = mysqli_fetch_assoc($lines_result)): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($line['account_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo $line['account_type']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($line['description']); ?></td>
                                        <td class="text-end">
                                            <?php if ($line['debit_amount'] > 0): ?>
                                                <span class="debit-amount">₹<?php echo number_format($line['debit_amount'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($line['credit_amount'] > 0): ?>
                                                <span class="credit-amount">₹<?php echo number_format($line['credit_amount'], 2); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Created: <?php echo date('M d, Y h:i A', strtotime($entry['created_at'])); ?>
                        </small>
                        <button class="btn btn-outline-danger btn-sm" onclick="deleteJournalEntry(<?php echo $entry['id']; ?>)">
                            <i class="fas fa-trash me-1"></i>Delete
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- Pagination -->
            <?php if ($total_entries > $per_page): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $total_pages = ceil($total_entries / $per_page);
                        $current_params = $_GET;
                        
                        for ($i = 1; $i <= $total_pages; $i++): 
                            $current_params['page'] = $i;
                            $page_url = '?' . http_build_query($current_params);
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $page_url; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="card journal-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h4>No Journal Entries Found</h4>
                    <p class="text-muted">Create your first journal entry to start double-entry bookkeeping.</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addJournalEntryModal">
                        <i class="fas fa-plus me-2"></i>New Journal Entry
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Journal Entry Modal -->
    <div class="modal fade" id="addJournalEntryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header journal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>New Journal Entry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addJournalEntryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_journal_entry">
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Entry Date *</label>
                                <input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" class="form-control" placeholder="JE-001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Description *</label>
                                <input type="text" name="description" class="form-control" required placeholder="Journal entry description">
                            </div>
                        </div>

                        <div class="journal-entry-form">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6><i class="fas fa-list me-2"></i>Journal Entry Lines</h6>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addJournalLine()">
                                    <i class="fas fa-plus me-1"></i>Add Line
                                </button>
                            </div>

                            <div id="journalLines">
                                <!-- Journal entry lines will be added here -->
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-8"></div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <strong>Total Debits:</strong><br>
                                        <span id="totalDebits" class="total-display debit-amount">₹0.00</span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-center">
                                        <strong>Total Credits:</strong><br>
                                        <span id="totalCredits" class="total-display credit-amount">₹0.00</span>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-2">
                                <span id="balanceStatus" class="badge fs-6">Balanced</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="submitJournalEntry" disabled>
                            <i class="fas fa-save me-2"></i>Create Journal Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let journalLineCounter = 0;
        const accounts = <?php echo json_encode(mysqli_fetch_all($accounts_result, MYSQLI_ASSOC)); ?>;

        function addJournalLine() {
            journalLineCounter++;
            const lineHtml = `
                <div class="entry-line" id="line_${journalLineCounter}">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Account *</label>
                            <select class="form-select journal-account" name="account_${journalLineCounter}" required>
                                <option value="">Select Account</option>
                                ${accounts.map(acc => `<option value="${acc.id}">${acc.account_name} (${acc.account_type})</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description_${journalLineCounter}" placeholder="Line description">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Debit</label>
                            <input type="number" class="form-control debit-input" name="debit_${journalLineCounter}" step="0.01" min="0" placeholder="0.00" onchange="calculateTotals()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Credit</label>
                            <input type="number" class="form-control credit-input" name="credit_${journalLineCounter}" step="0.01" min="0" placeholder="0.00" onchange="calculateTotals()">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-outline-danger btn-sm d-block" onclick="removeJournalLine(${journalLineCounter})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('journalLines').insertAdjacentHTML('beforeend', lineHtml);
            
            if (journalLineCounter === 1) {
                addJournalLine(); // Add second line automatically
            }
            
            calculateTotals();
        }

        function removeJournalLine(lineId) {
            document.getElementById(`line_${lineId}`).remove();
            calculateTotals();
        }

        function calculateTotals() {
            let totalDebits = 0;
            let totalCredits = 0;
            
            document.querySelectorAll('.debit-input').forEach(input => {
                totalDebits += parseFloat(input.value) || 0;
            });
            
            document.querySelectorAll('.credit-input').forEach(input => {
                totalCredits += parseFloat(input.value) || 0;
            });
            
            document.getElementById('totalDebits').textContent = '₹' + totalDebits.toFixed(2);
            document.getElementById('totalCredits').textContent = '₹' + totalCredits.toFixed(2);
            
            const isBalanced = Math.abs(totalDebits - totalCredits) < 0.01 && totalDebits > 0;
            const balanceStatus = document.getElementById('balanceStatus');
            const submitButton = document.getElementById('submitJournalEntry');
            
            if (isBalanced) {
                balanceStatus.textContent = 'Balanced ✓';
                balanceStatus.className = 'badge bg-success fs-6';
                submitButton.disabled = false;
            } else {
                balanceStatus.textContent = 'Not Balanced';
                balanceStatus.className = 'badge bg-danger fs-6';
                submitButton.disabled = true;
            }
        }

        // Initialize with two lines
        addJournalLine();

        // Handle form submission
        document.getElementById('addJournalEntryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Collect journal entry lines
            const journalEntries = [];
            const lines = document.querySelectorAll('.entry-line');
            
            lines.forEach((line, index) => {
                const accountSelect = line.querySelector('.journal-account');
                const descriptionInput = line.querySelector('input[type="text"]');
                const debitInput = line.querySelector('.debit-input');
                const creditInput = line.querySelector('.credit-input');
                
                if (accountSelect.value && (debitInput.value || creditInput.value)) {
                    journalEntries.push({
                        account_id: accountSelect.value,
                        description: descriptionInput.value,
                        debit: parseFloat(debitInput.value) || 0,
                        credit: parseFloat(creditInput.value) || 0
                    });
                }
            });
            
            if (journalEntries.length < 2) {
                alert('Journal entry must have at least 2 lines');
                return;
            }
            
            formData.append('journal_entries', JSON.stringify(journalEntries));
            
            fetch('journal_entries.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Journal entry created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the journal entry');
            });
        });

        function deleteJournalEntry(entryId) {
            if (confirm('Are you sure you want to delete this journal entry? This will reverse all account balance changes.')) {
                const formData = new FormData();
                formData.append('action', 'delete_journal_entry');
                formData.append('entry_id', entryId);
                
                fetch('journal_entries.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Journal entry deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the journal entry');
                });
            }
        }
    </script>
</body>
</html>
