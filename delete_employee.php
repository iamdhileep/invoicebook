
<?php
session_start();
if (!isset($_SESSION['admin'])) {
    // For AJAX requests, return JSON error
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}

include 'db.php';

// Get ID from POST (AJAX) or GET (direct link)
$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);

if ($id > 0) {
    try {
        // Get employee details for photo deletion
        $stmt = $conn->prepare("SELECT name, photo FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $employee = $result->fetch_assoc();
            $employeeName = $employee['name'];
            
            // Delete employee record
            $deleteStmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                // Delete photo file if exists
                if (!empty($employee['photo']) && file_exists($employee['photo'])) {
                    unlink($employee['photo']);
                }
                
                // For AJAX requests (POST), return JSON
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'message' => "Employee '{$employeeName}' deleted successfully"
                    ]);
                } else {
                    // For direct access (GET), redirect
                    header('Location: pages/employees/employees.php?success=' . urlencode('Employee deleted successfully'));
                }
            } else {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Failed to delete employee: ' . $conn->error
                    ]);
                } else {
                    header('Location: pages/employees/employees.php?error=' . urlencode('Failed to delete employee'));
                }
            }
            
            $deleteStmt->close();
        } else {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
            } else {
                header('Location: pages/employees/employees.php?error=' . urlencode('Employee not found'));
            }
        }
        
        $stmt->close();
    } catch (Exception $e) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } else {
            header('Location: pages/employees/employees.php?error=' . urlencode('Error occurred while deleting employee'));
        }
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid employee ID is required']);
    } else {
        header('Location: pages/employees/employees.php?error=' . urlencode('Employee ID is required'));
    }
}

exit;
?>
