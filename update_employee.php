<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['employee_name'] ?? '');
    $code = trim($_POST['employee_code'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $monthly_salary = floatval($_POST['monthly_salary'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$id) {
        header('Location: pages/employees/employees.php?error=' . urlencode('Employee ID is required'));
        exit;
    }

    // Validation
    if (empty($name) || empty($code) || empty($position) || $monthly_salary <= 0 || empty($phone)) {
        header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Please fill in all required fields'));
        exit;
    }

    // Check if employee code is unique (excluding current employee)
    $checkQuery = $conn->prepare("SELECT employee_id FROM employees WHERE employee_code = ? AND employee_id != ?");
    $checkQuery->bind_param("si", $code, $id);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();
    
    if ($checkResult->num_rows > 0) {
        header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Employee code already exists'));
        exit;
    }

    // Get current employee data for photo handling
    $currentQuery = $conn->prepare("SELECT photo FROM employees WHERE employee_id = ?");
    $currentQuery->bind_param("i", $id);
    $currentQuery->execute();
    $currentResult = $currentQuery->get_result();
    $currentEmployee = $currentResult->fetch_assoc();
    
    $photoPath = $currentEmployee['photo'] ?? ''; // Keep existing photo by default
    
    // Handle photo upload
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/employees/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = 'employee_' . $id . '_' . time() . '.' . $fileExtension;
            $newPhotoPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $newPhotoPath)) {
                // Delete old photo if it exists
                if (!empty($photoPath) && file_exists($photoPath) && $photoPath !== $newPhotoPath) {
                    unlink($photoPath);
                }
                $photoPath = $newPhotoPath;
            } else {
                header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Failed to upload photo'));
                exit;
            }
        } else {
            header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Invalid photo format'));
            exit;
        }
    }

    // Update employee record
    $updateQuery = $conn->prepare("UPDATE employees SET employee_name = ?, employee_code = ?, position = ?, monthly_salary = ?, phone = ?, address = ?, email = ?, photo = ? WHERE employee_id = ?");
    
    if (!$updateQuery) {
        header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Database error: ' . $conn->error));
        exit;
    }

    $updateQuery->bind_param("sssdsssi", $name, $code, $position, $monthly_salary, $phone, $address, $email, $photoPath, $id);
    
    if ($updateQuery->execute()) {
        header('Location: edit_employee.php?id=' . $id . '&success=1');
    } else {
        header('Location: edit_employee.php?id=' . $id . '&error=' . urlencode('Failed to update employee: ' . $conn->error));
    }
    
    $updateQuery->close();
    exit;
} else {
    // If not POST request, redirect back
    header('Location: pages/employees/employees.php');
    exit;
}
?>
