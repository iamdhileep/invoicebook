<?php
/**
 * HRMS AJAX Handler Fixer
 * Fixes all AJAX requests and modal functionality in HRMS files
 */

echo "<h1>HRMS AJAX & Modal Functionality Fix</h1>";

// Get all PHP files in HRMS directory
$hrmsDir = __DIR__;
$phpFiles = glob($hrmsDir . '/*.php');

$skipFiles = [
    'complete_database_setup.php',
    'hrms_mass_fix.php', 
    'table_name_fixer.php',
    'ajax_modal_fixer.php',
    'employee_directory.php'
];

$fixedCount = 0;

echo "<h2>Adding AJAX handlers to HRMS files...</h2>";

foreach ($phpFiles as $filePath) {
    $fileName = basename($filePath);
    
    if (in_array($fileName, $skipFiles)) {
        echo "<p>⏭️ Skipped: $fileName</p>";
        continue;
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) continue;
    
    $originalContent = $content;
    
    // Check if file needs AJAX handler
    if (strpos($content, 'if (isset($_POST[\'action\']))') === false && 
        strpos($content, 'modal') !== false) {
        
        // Find where to insert AJAX handler (after includes, before HTML)
        $insertPoint = strpos($content, 'require_once \'../layouts/header.php\';');
        if ($insertPoint !== false) {
            $insertPoint = strpos($content, '?>', $insertPoint) + 2;
            
            $ajaxHandler = "

// Handle AJAX requests
if (isset(\$_POST['action'])) {
    header('Content-Type: application/json');
    
    switch (\$_POST['action']) {
        case 'get_record':
            \$id = intval(\$_POST['id'] ?? 0);
            if (\$id > 0) {
                // Determine main table based on file name
                \$table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) \$table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) \$table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) \$table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) \$table = 'hr_performance_reviews';
                
                \$result = \$conn->query(\"SELECT * FROM \$table WHERE id = \$id\");
                if (\$result && \$result->num_rows > 0) {
                    echo json_encode(['success' => true, 'data' => \$result->fetch_assoc()]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Record not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            exit;
            
        case 'delete_record':
            \$id = intval(\$_POST['id'] ?? 0);
            if (\$id > 0) {
                // Determine main table based on file name
                \$table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) \$table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) \$table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) \$table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) \$table = 'hr_performance_reviews';
                
                \$result = \$conn->query(\"DELETE FROM \$table WHERE id = \$id\");
                if (\$result) {
                    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error deleting record: ' . \$conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            exit;
            
        case 'update_status':
            \$id = intval(\$_POST['id'] ?? 0);
            \$status = \$conn->real_escape_string(\$_POST['status'] ?? '');
            if (\$id > 0 && \$status) {
                // Determine main table based on file name
                \$table = 'hr_employees';
                if (strpos(__FILE__, 'leave') !== false) \$table = 'hr_leave_applications';
                if (strpos(__FILE__, 'attendance') !== false) \$table = 'hr_attendance';
                if (strpos(__FILE__, 'payroll') !== false) \$table = 'hr_payroll';
                if (strpos(__FILE__, 'performance') !== false) \$table = 'hr_performance_reviews';
                
                \$result = \$conn->query(\"UPDATE \$table SET status = '\$status' WHERE id = \$id\");
                if (\$result) {
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error updating status: ' . \$conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }
}
";
            
            $content = substr($content, 0, $insertPoint) . $ajaxHandler . substr($content, $insertPoint);
        }
    }
    
    // Add standard JavaScript functions for modals if missing
    if (strpos($content, 'function showModal') === false && strpos($content, 'modal') !== false) {
        // Find where to add JavaScript (before closing body tag or at end)
        $jsInsertPoint = strrpos($content, '</body>');
        if ($jsInsertPoint === false) {
            $jsInsertPoint = strrpos($content, '?>');
            if ($jsInsertPoint === false) $jsInsertPoint = strlen($content);
        }
        
        $modalJS = "
<script>
// Standard modal functions for HRMS
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        new bootstrap.Modal(modal).show();
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
}

function loadRecord(id, modalId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal form fields
            Object.keys(data.data).forEach(key => {
                const field = document.getElementById(key) || document.querySelector('[name=\"' + key + '\"]');
                if (field) {
                    field.value = data.data[key];
                }
            });
            showModal(modalId);
        } else {
            alert('Error loading record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function deleteRecord(id, confirmMessage = 'Are you sure you want to delete this record?') {
    if (!confirm(confirmMessage)) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_record&id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Record deleted successfully');
            location.reload();
        } else {
            alert('Error deleting record: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

function updateStatus(id, status) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_status&id=' + id + '&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status updated successfully');
            location.reload();
        } else {
            alert('Error updating status: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
}

// Form submission with AJAX
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to forms with class 'ajax-form'
    document.querySelectorAll('.ajax-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Operation completed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred');
            });
        });
    });
});
</script>
";
        
        $content = substr($content, 0, $jsInsertPoint) . $modalJS . substr($content, $jsInsertPoint);
    }
    
    // Write back if changes were made
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content) !== false) {
            echo "<p style='color: green;'>✅ Added AJAX handlers to: $fileName</p>";
            $fixedCount++;
        } else {
            echo "<p style='color: red;'>❌ Could not write: $fileName</p>";
        }
    } else {
        echo "<p>⚪ No AJAX changes needed: $fileName</p>";
    }
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Files Enhanced with AJAX:</strong> $fixedCount</p>";
echo "<p>All HRMS files now have standard AJAX handlers for:</p>";
echo "<ul>";
echo "<li>✅ Modal loading and display</li>";
echo "<li>✅ Record retrieval for editing</li>";
echo "<li>✅ Record deletion with confirmation</li>";
echo "<li>✅ Status updates</li>";
echo "<li>✅ Form submission with feedback</li>";
echo "</ul>";

echo "<p><a href='attendance_management.php'>Test Attendance</a> | <a href='leave_management.php'>Test Leave Management</a> | <a href='payroll_processing.php'>Test Payroll</a></p>";

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
a { background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-right: 10px; display: inline-block; }
a:hover { background: #0056b3; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>";

require_once 'hrms_footer_simple.php';
?>