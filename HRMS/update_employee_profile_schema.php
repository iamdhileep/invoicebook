<?php
// Database schema updates for employee profile functionality
include '../db.php';

try {
    // Create employee_notes table
    $sql = "CREATE TABLE IF NOT EXISTS employee_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        note TEXT NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_employee (employee_id)
    )";
    
    if ($conn->query($sql)) {
        echo "Employee notes table created/verified.\n";
    } else {
        echo "Error creating employee_notes table: " . $conn->error . "\n";
    }
    
    // Add emergency contact columns to hr_employees
    $columns = [
        'emergency_contact_name' => 'VARCHAR(100)',
        'emergency_contact_relationship' => 'VARCHAR(50)',
        'emergency_contact_phone' => 'VARCHAR(20)',
        'address' => 'TEXT'
    ];
    
    foreach ($columns as $column => $type) {
        $check_sql = "SHOW COLUMNS FROM hr_employees LIKE '$column'";
        $result = $conn->query($check_sql);
        
        if ($result && $result->num_rows == 0) {
            $alter_sql = "ALTER TABLE hr_employees ADD COLUMN $column $type";
            if ($conn->query($alter_sql)) {
                echo "Added column $column to hr_employees table.\n";
            } else {
                echo "Error adding column $column: " . $conn->error . "\n";
            }
        } else {
            echo "Column $column already exists in hr_employees table.\n";
        }
    }
    
    echo "Database schema updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
