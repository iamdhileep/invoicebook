<?php
session_start();
include 'db.php';

echo "<h2>🔧 Advanced Attendance Diagnostics</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";

echo "<div class='info'><h3>1. Authentication Check</h3>";
echo "Session admin: " . (isset($_SESSION['admin']) ? $_SESSION['admin'] : 'NOT SET') . "<br>";
echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";

// Set admin session for testing
if (!isset($_SESSION['admin'])) {
    $_SESSION['admin'] = 'admin';
    echo "<div class='warning'>⚠️ Set admin session for testing</div>";
}
echo "</div>";

echo "<div class='info'><h3>2. Database Tables Check</h3>";

// Check if attendance table exists
$tables_check = $conn->query("SHOW TABLES LIKE 'attendance'");
if ($tables_check->num_rows > 0) {
    echo "<div class='success'>✅ Attendance table exists</div>";
    
    // Check table structure
    $structure = $conn->query("DESCRIBE attendance");
    echo "<strong>Table Structure:</strong><br>";
    while ($row = $structure->fetch_assoc()) {
        echo "- {$row['Field']} ({$row['Type']}) " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "<br>";
    }
} else {
    echo "<div class='error'>❌ Attendance table missing</div>";
    
    // Create attendance table
    $create_attendance = "CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        attendance_date DATE NOT NULL,
        time_in TIME NULL,
        time_out TIME NULL,
        status ENUM('Present', 'Absent', 'Late', 'Half Day') DEFAULT 'Absent',
        work_duration TIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_employee_date (employee_id, attendance_date),
        FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_attendance)) {
        echo "<div class='success'>✅ Created attendance table</div>";
    } else {
        echo "<div class='error'>❌ Failed to create attendance table: " . $conn->error . "</div>";
    }
}
echo "</div>";

echo "<div class='info'><h3>3. Employees Table Check</h3>";
$employees_check = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($employees_check) {
    $emp_count = $employees_check->fetch_assoc()['count'];
    echo "<div class='success'>✅ Employees table has $emp_count records</div>";
    
    if ($emp_count == 0) {
        // Add sample employees
        $sample_employees = [
            ['John Doe', 'EMP001', 'Manager', '9876543210', 50000],
            ['Jane Smith', 'EMP002', 'Developer', '9876543211', 45000],
            ['Mike Johnson', 'EMP003', 'Designer', '9876543212', 40000]
        ];
        
        foreach ($sample_employees as $emp) {
            $stmt = $conn->prepare("INSERT INTO employees (name, employee_code, position, phone, monthly_salary) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssi', $emp[0], $emp[1], $emp[2], $emp[3], $emp[4]);
            $stmt->execute();
        }
        echo "<div class='success'>✅ Added 3 sample employees</div>";
    }
} else {
    echo "<div class='error'>❌ Cannot access employees table: " . $conn->error . "</div>";
}
echo "</div>";

echo "<div class='info'><h3>4. Test Punch Operations</h3>";
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Get first employee for testing
$emp_query = $conn->query("SELECT employee_id, name FROM employees LIMIT 1");
if ($emp_query && $emp_query->num_rows > 0) {
    $employee = $emp_query->fetch_assoc();
    $emp_id = $employee['employee_id'];
    $emp_name = $employee['name'];
    
    echo "<strong>Testing with Employee:</strong> $emp_name (ID: $emp_id)<br>";
    
    // Test punch in
    $punch_in_query = "INSERT INTO attendance (employee_id, attendance_date, time_in, status) VALUES (?, ?, ?, 'Present') 
                       ON DUPLICATE KEY UPDATE time_in = VALUES(time_in), status = VALUES(status)";
    $stmt = $conn->prepare($punch_in_query);
    $stmt->bind_param('iss', $emp_id, $current_date, $current_time);
    
    if ($stmt->execute()) {
        echo "<div class='success'>✅ Punch In test successful</div>";
        
        // Test punch out
        $punch_out_query = "UPDATE attendance SET time_out = ? WHERE employee_id = ? AND attendance_date = ?";
        $stmt2 = $conn->prepare($punch_out_query);
        $stmt2->bind_param('sis', $current_time, $emp_id, $current_date);
        
        if ($stmt2->execute()) {
            echo "<div class='success'>✅ Punch Out test successful</div>";
        } else {
            echo "<div class='error'>❌ Punch Out test failed: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='error'>❌ Punch In test failed: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='error'>❌ No employees found for testing</div>";
}
echo "</div>";

echo "<div class='info'><h3>5. Advanced Attendance Page Access</h3>";
if (file_exists('advanced_attendance.php')) {
    echo "<div class='success'>✅ advanced_attendance.php file exists</div>";
    echo "<a href='advanced_attendance.php' style='background:#2563eb;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;'>🕐 Test Advanced Attendance</a>";
} else {
    echo "<div class='error'>❌ advanced_attendance.php file missing</div>";
}
echo "</div>";

echo "<br><h3>🚀 Solutions:</h3>";
echo "<div style='background:#f0f9ff;padding:15px;border-radius:8px;border-left:4px solid #2563eb;'>";
echo "<strong>1. Authentication Fix:</strong> Update advanced_attendance.php to use \$_SESSION['admin']<br>";
echo "<strong>2. Database Fix:</strong> Ensure attendance table has correct structure<br>";
echo "<strong>3. Employee Data:</strong> Ensure employees exist for testing<br>";
echo "</div>";
?>
