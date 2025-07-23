<?php
// Simple test page to debug bulk punch functionality
session_start();
if (!isset($_SESSION['admin'])) {
    die('Login required');
}

include 'db.php';

// Handle AJAX requests for testing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log('POST Input: ' . json_encode($input)); // Debug log
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input', 'raw_input' => file_get_contents('php://input')]);
        exit;
    }
    
    $action = $input['action'] ?? '';
    $current_time = date('Y-m-d H:i:s');
    $attendance_date = $input['attendance_date'] ?? date('Y-m-d');
    
    if ($action === 'bulk_punch_in') {
        $employee_ids = $input['employee_ids'] ?? [];
        
        error_log('Bulk punch in - Employee IDs: ' . json_encode($employee_ids)); // Debug log
        
        if (empty($employee_ids)) {
            echo json_encode(['success' => false, 'message' => 'No employees selected', 'received_ids' => $employee_ids]);
            exit;
        }
        
        $success_count = 0;
        $errors = [];
        
        foreach ($employee_ids as $employee_id) {
            $employee_id = intval($employee_id);
            
            if ($employee_id <= 0) {
                $errors[] = "Invalid employee ID: $employee_id";
                continue;
            }
            
            // Simple insertion for testing
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present') ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), status = VALUES(status)");
            
            if ($stmt->bind_param('iss', $employee_id, $attendance_date, $current_time) && $stmt->execute()) {
                $success_count++;
                error_log("Successfully punched in employee ID: $employee_id");
            } else {
                $errors[] = "Failed to process employee ID $employee_id: " . $conn->error;
                error_log("Failed to punch in employee ID: $employee_id - " . $conn->error);
            }
        }
        
        echo json_encode([
            'success' => $success_count > 0,
            'message' => "$success_count employees processed successfully" . (empty($errors) ? '' : '. Errors: ' . implode(', ', $errors)),
            'success_count' => $success_count,
            'error_count' => count($errors),
            'details' => [
                'action' => $action,
                'employee_ids' => $employee_ids,
                'attendance_date' => $attendance_date
            ]
        ]);
        exit;
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
        exit;
    }
}

// Get some employees for testing
$employees_query = "SELECT employee_id, name, employee_code FROM employees LIMIT 5";
$employees_result = $conn->query($employees_query);
$employees = $employees_result ? $employees_result->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Bulk Punch Functionality</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>Test Bulk Punch Functionality</h2>
        
        <div class="alert alert-info">
            <strong>Debug Test Page</strong><br>
            This page tests the bulk punch functionality in isolation to identify issues.
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Employee Selection</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" class="form-check-input">
                                Select All
                            </th>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       class="form-check-input employee-checkbox" 
                                       value="<?= $emp['employee_id'] ?>"
                                       data-name="<?= htmlspecialchars($emp['name']) ?>">
                            </td>
                            <td><?= $emp['employee_id'] ?></td>
                            <td><?= htmlspecialchars($emp['name']) ?></td>
                            <td><?= htmlspecialchars($emp['employee_code']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <button type="button" class="btn btn-success" onclick="testBulkPunchIn()">
                        <i class="bi bi-box-arrow-in-right"></i> Test Bulk Punch In
                    </button>
                    <button type="button" class="btn btn-info" onclick="debugSelection()">
                        <i class="bi bi-bug"></i> Debug Selection
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearLogs()">
                        <i class="bi bi-trash"></i> Clear Logs
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Debug Output</h5>
            </div>
            <div class="card-body">
                <div id="debugOutput" class="bg-dark text-light p-3" style="font-family: monospace; max-height: 400px; overflow-y: auto;">
                    <!-- Debug output will appear here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let debugOutput = document.getElementById('debugOutput');
        
        function log(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const colorClass = type === 'error' ? 'text-danger' : type === 'success' ? 'text-success' : type === 'warning' ? 'text-warning' : 'text-info';
            debugOutput.innerHTML += `<span class="${colorClass}">[${timestamp}] ${message}</span>\n`;
            debugOutput.scrollTop = debugOutput.scrollHeight;
            console.log(`[${timestamp}] ${message}`);
        }
        
        function clearLogs() {
            debugOutput.innerHTML = '';
            log('Debug logs cleared', 'info');
        }
        
        function debugSelection() {
            log('=== DEBUGGING SELECTION ===', 'warning');
            
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            log(`Total checkboxes found: ${checkboxes.length}`, 'info');
            
            const selectedCheckboxes = document.querySelectorAll('.employee-checkbox:checked');
            log(`Selected checkboxes: ${selectedCheckboxes.length}`, 'info');
            
            checkboxes.forEach((cb, index) => {
                log(`Checkbox ${index}: ID=${cb.value}, checked=${cb.checked}, name=${cb.dataset.name}`, 'info');
            });
            
            const selectedEmployees = Array.from(selectedCheckboxes).map(cb => cb.value);
            log(`Selected employee IDs: [${selectedEmployees.join(', ')}]`, 'info');
        }
        
        async function testBulkPunchIn() {
            log('=== STARTING BULK PUNCH IN TEST ===', 'warning');
            
            // Get selected employees
            const selectedEmployees = Array.from(document.querySelectorAll('.employee-checkbox:checked'))
                .map(cb => cb.value);
            
            log(`Selected employees: [${selectedEmployees.join(', ')}]`, 'info');
            
            if (selectedEmployees.length === 0) {
                log('ERROR: No employees selected', 'error');
                alert('Please select employees first');
                return;
            }
            
            if (!confirm(`Punch in ${selectedEmployees.length} selected employees?`)) {
                log('User cancelled operation', 'warning');
                return;
            }
            
            const requestData = {
                action: 'bulk_punch_in',
                employee_ids: selectedEmployees,
                attendance_date: '<?= date('Y-m-d') ?>'
            };
            
            log(`Request data: ${JSON.stringify(requestData)}`, 'info');
            
            try {
                log('Sending AJAX request...', 'info');
                
                const response = await fetch('test_bulk_punch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                log(`Response status: ${response.status} ${response.statusText}`, 'info');
                
                const responseText = await response.text();
                log(`Raw response: ${responseText}`, 'info');
                
                let result;
                try {
                    result = JSON.parse(responseText);
                    log(`Parsed response: ${JSON.stringify(result, null, 2)}`, 'info');
                } catch (e) {
                    log(`JSON parse error: ${e.message}`, 'error');
                    log(`Response was not valid JSON`, 'error');
                    return;
                }
                
                if (result.success) {
                    log(`SUCCESS: ${result.message}`, 'success');
                    alert('Success: ' + result.message);
                } else {
                    log(`FAILURE: ${result.message}`, 'error');
                    alert('Error: ' + result.message);
                }
                
            } catch (error) {
                log(`Network error: ${error.message}`, 'error');
                console.error('Full error:', error);
                alert('Network error: ' + error.message);
            }
        }
        
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.employee-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            log(`Select all toggled: ${this.checked}`, 'info');
        });
        
        // Initialize
        log('Test page loaded successfully', 'success');
        log('Ready for testing. Select employees and click "Test Bulk Punch In"', 'info');
    </script>
</body>
</html> 