<?php
/**
 * Revert Changes Script
 * This script will help revert the changes made in today's chat session
 */

echo "<h2>🔄 Revert Last Chat Request Only</h2>\n";
echo "<p>Reverting only the changes made after 6 PM today (last chat request)...</p>\n";
echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #007bff;'>\n";
echo "<strong>📌 Important:</strong> This will preserve all the major work done earlier today including:<br>\n";
echo "• Integration Hub (33MB enterprise features)<br>\n";
echo "• Project Completion Celebration<br>\n"; 
echo "• System Status Dashboard<br>\n";
echo "• System Verification Tools<br>\n";
echo "• New Layout Files<br>\n";
echo "</div>\n";

// Files that were created in the LAST chat request (after 6 PM today)
$files_after_6pm = [
    'revert_today_changes.ps1',
    'revert_today_changes.bat', 
    'revert_changes.php',
    'employee_directory.php',
    'system_diagnostics.php',
    'hrms_footer_simple.php',
    'hrms_sidebar_simple.php', 
    'hrms_header_simple.php',
    'emergency_restore_check.php',
    'payroll_processing.php',
    'leave_management.php',
    'attendance_management.php'
];

// Keep the earlier files created today (before 6 PM) - these will be preserved
$files_to_preserve = [
    'integration_hub.php',
    'project_completion_celebration.html', 
    'system_status_dashboard.php',
    'system_verification.php',
    'layouts/footer_new.php',
    'layouts/header_new.php',
    'layouts/sidebar_new.php',
    'optimize_mobile_pwa.php'
];

$new_files_created = $files_after_6pm;

echo "<h3>📋 Files Created After 6 PM (Will be removed):</h3>\n";
echo "<ul>\n";
foreach ($new_files_created as $file) {
    $exists = file_exists($file) ? '✅ EXISTS' : '❌ NOT FOUND';
    $size = file_exists($file) ? ' (' . round(filesize($file) / 1024, 2) . ' KB)' : '';
    echo "<li><code>$file</code> - $exists$size</li>\n";
}
echo "</ul>\n";

echo "<h3>📌 Files Being Preserved (Created before 6 PM):</h3>\n";
echo "<ul style='color: #28a745;'>\n";
foreach ($files_to_preserve as $file) {
    $exists = file_exists($file) ? '✅ PRESERVED' : '❓ NOT FOUND';
    $size = file_exists($file) ? ' (' . round(filesize($file) / 1024, 2) . ' KB)' : '';
    echo "<li><code>$file</code> - $exists$size</li>\n";
}
echo "</ul>\n";

echo "<h3>🔧 Revert Options:</h3>\n";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>\n";

echo "<h4>Option 1: Delete Files After 6 PM Only (Recommended)</h4>\n";
echo "<p>This will remove only the files created in the last chat request while preserving all major work from earlier today.</p>\n";
echo "<button onclick='deleteNewFiles()' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🗑️ Delete Recent Files Only</button>\n";

echo "<h4>Option 2: Create Backup of Recent Files</h4>\n";
echo "<p>This will create backups of the files created after 6 PM before removing them.</p>\n";
echo "<button onclick='backupNewFiles()' style='background: #ffc107; color: black; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>💾 Backup Recent Files</button>\n";

echo "<h4>Option 3: Git Revert (If Git Repository)</h4>\n";
echo "<p>Use Git to revert to the state before today's changes.</p>\n";
echo "<button onclick='gitRevert()' style='background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>📦 Git Revert</button>\n";

echo "</div>\n";

echo "<div id='revertResults' style='margin-top: 20px;'></div>\n";

?>

<script>
function deleteNewFiles() {
    const files = <?php echo json_encode($new_files_created); ?>;
    
    if (!confirm('⚠️ WARNING: This will permanently delete ' + files.length + ' files created today. Continue?')) {
        return;
    }
    
    const resultsDiv = document.getElementById('revertResults');
    resultsDiv.innerHTML = '<h4>🗑️ Deleting Files...</h4>';
    
    // Make AJAX request to delete files
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=delete_files&files=' + encodeURIComponent(JSON.stringify(files))
    })
    .then(response => response.text())
    .then(data => {
        resultsDiv.innerHTML = data;
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div style="color: red;">Error: ' + error + '</div>';
    });
}

function backupNewFiles() {
    const files = <?php echo json_encode($new_files_created); ?>;
    
    const resultsDiv = document.getElementById('revertResults');
    resultsDiv.innerHTML = '<h4>💾 Creating Backups...</h4>';
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=backup_files&files=' + encodeURIComponent(JSON.stringify(files))
    })
    .then(response => response.text())
    .then(data => {
        resultsDiv.innerHTML = data;
    });
}

function gitRevert() {
    const resultsDiv = document.getElementById('revertResults');
    resultsDiv.innerHTML = '<h4>📦 Git Revert...</h4>';
    
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=git_revert'
    })
    .then(response => response.text())
    .then(data => {
        resultsDiv.innerHTML = data;
    });
}
</script>

<?php
// Handle POST requests for revert actions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: text/html');
    
    switch ($_POST['action']) {
        case 'delete_files':
            $files = json_decode($_POST['files'], true);
            $deleted = 0;
            $errors = [];
            
            echo "<h4>🗑️ Deletion Results:</h4>\n";
            echo "<ul>\n";
            
            foreach ($files as $file) {
                if (file_exists($file)) {
                    if (unlink($file)) {
                        echo "<li>✅ Deleted: <code>$file</code></li>\n";
                        $deleted++;
                    } else {
                        echo "<li>❌ Failed to delete: <code>$file</code></li>\n";
                        $errors[] = $file;
                    }
                } else {
                    echo "<li>ℹ️ Not found: <code>$file</code></li>\n";
                }
            }
            
            echo "</ul>\n";
            echo "<div style='background: " . (empty($errors) ? '#d4edda' : '#f8d7da') . "; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
            echo "<strong>Summary:</strong><br>\n";
            echo "• Files deleted: $deleted<br>\n";
            echo "• Errors: " . count($errors) . "<br>\n";
            if (empty($errors)) {
                echo "✅ All new files from today's session have been successfully removed!<br>\n";
                echo "Your system has been reverted to the state before today's changes.\n";
            } else {
                echo "⚠️ Some files could not be deleted. Check file permissions.\n";
            }
            echo "</div>\n";
            break;
            
        case 'backup_files':
            $files = json_decode($_POST['files'], true);
            $backup_dir = 'revert_backup_' . date('Y-m-d_H-i-s');
            
            echo "<h4>💾 Backup Results:</h4>\n";
            
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
                echo "<p>✅ Created backup directory: <code>$backup_dir</code></p>\n";
            }
            
            $backed_up = 0;
            echo "<ul>\n";
            
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $backup_file = $backup_dir . '/' . basename($file);
                    if (copy($file, $backup_file)) {
                        echo "<li>✅ Backed up: <code>$file</code> → <code>$backup_file</code></li>\n";
                        $backed_up++;
                    } else {
                        echo "<li>❌ Failed to backup: <code>$file</code></li>\n";
                    }
                } else {
                    echo "<li>ℹ️ Not found: <code>$file</code></li>\n";
                }
            }
            
            echo "</ul>\n";
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
            echo "<strong>Backup Complete!</strong><br>\n";
            echo "• Files backed up: $backed_up<br>\n";
            echo "• Backup location: <code>$backup_dir</code><br>\n";
            echo "💡 You can now safely delete the original files or keep them as needed.\n";
            echo "</div>\n";
            break;
            
        case 'git_revert':
            echo "<h4>📦 Git Revert:</h4>\n";
            
            if (!is_dir('.git')) {
                echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
                echo "❌ No Git repository found. This option requires a Git repository.\n";
                echo "</div>\n";
                break;
            }
            
            // Check git status
            $status = shell_exec('git status --porcelain 2>&1');
            
            echo "<p><strong>Git Status:</strong></p>\n";
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>$status</pre>\n";
            
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>\n";
            echo "📋 <strong>Manual Git Commands:</strong><br>\n";
            echo "To revert changes, run these commands in your terminal:<br><br>\n";
            echo "<code>git add -A</code> (stage all changes)<br>\n";
            echo "<code>git reset --hard HEAD</code> (revert to last commit)<br>\n";
            echo "or<br>\n";
            echo "<code>git stash</code> (stash changes for later)<br>\n";
            echo "</div>\n";
            break;
    }
    exit;
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

code {
    background: #f1f3f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

ul li {
    margin: 8px 0;
}

h2, h3, h4 {
    color: #333;
}
</style>
