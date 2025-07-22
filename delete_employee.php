
<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($id > 0) {
        // Get employee details for photo deletion
        $stmt = $conn->prepare("SELECT photo FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $employee = $result->fetch_assoc();
            
            // Delete employee record
            $deleteStmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
            $deleteStmt->bind_param("i", $id);
            
            if ($deleteStmt->execute()) {
                // Delete photo file if exists
                if (!empty($employee['photo']) && file_exists($employee['photo'])) {
                    unlink($employee['photo']);
                }
                
                header('Location: pages/employees/employees.php?success=' . urlencode('Employee deleted successfully'));
            } else {
                header('Location: pages/employees/employees.php?error=' . urlencode('Failed to delete employee'));
            }
            
            $deleteStmt->close();
        } else {
            header('Location: pages/employees/employees.php?error=' . urlencode('Employee not found'));
        }
        
        $stmt->close();
    } else {
        header('Location: pages/employees/employees.php?error=' . urlencode('Invalid employee ID'));
    }
} else {
    header('Location: pages/employees/employees.php?error=' . urlencode('Employee ID is required'));
}

exit;
?>
