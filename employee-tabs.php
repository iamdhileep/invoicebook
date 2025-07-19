<?php
include 'db.php';
// ✅ AJAX Delete Handler
if (isset($_POST['ajax_delete']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    echo $result ? "success" : "error";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name     = $_POST['name'];
    $code     = $_POST['code'];
    $position = $_POST['position'];
    $salary   = $_POST['monthly_salary'];
    $phone    = $_POST['phone'];
    $address  = $_POST['address'];

    // Handle image upload
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $imageName = time() . '_' . basename($_FILES['photo']['name']);
        $target    = 'uploads/' . $imageName;
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
            $photo = $imageName;
        }
    }

    // Run INSERT query
    $stmt = $conn->prepare("INSERT INTO employees (name, employee_code, position, monthly_salary, phone, address, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $name, $code, $position, $salary, $phone, $address, $photoName);


    
    if (!$stmt) {
        die("SQL Prepare Error: " . $conn->error); // helpful debug
    }

    $stmt->bind_param("sssssss", $name, $code, $position, $salary, $phone, $photo, $address);
    $stmt->execute();

    header("Location: index.php#emp-manager");
    exit;
}


?>
