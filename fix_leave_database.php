<?php
// Database structure fix for leave management
include 'db.php';

try {
    echo "Checking and fixing database structure for leave management...\n\n";
    
    // Check if leaves table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'leaves'");
    
    if ($checkTable->num_rows == 0) {
        echo "Creating leaves table...\n";
        // Create leaves table
        $createLeavesTable = "
            CREATE TABLE leaves (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                leave_type ENUM('casual_leave', 'sick_leave', 'paid_leave', 'lop') NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                total_days INT NOT NULL,
                reason TEXT NOT NULL,
                contact_during_leave VARCHAR(20),
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                applied_by INT,
                approved_by INT,
                approved_date TIMESTAMP NULL,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee_id (employee_id),
                INDEX idx_status (status),
                INDEX idx_leave_type (leave_type),
                INDEX idx_applied_date (applied_date),
                FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
            )
        ";
        
        if ($conn->query($createLeavesTable)) {
            echo "✓ Leaves table created successfully\n";
        } else {
            echo "✗ Error creating leaves table: " . $conn->error . "\n";
        }
    } else {
        echo "Leaves table exists. Checking structure...\n";
        
        // Check if total_days column exists
        $checkColumn = $conn->query("SHOW COLUMNS FROM leaves LIKE 'total_days'");
        
        if ($checkColumn->num_rows == 0) {
            echo "Adding missing total_days column...\n";
            $addColumn = "ALTER TABLE leaves ADD COLUMN total_days INT NOT NULL AFTER end_date";
            if ($conn->query($addColumn)) {
                echo "✓ total_days column added successfully\n";
            } else {
                echo "✗ Error adding total_days column: " . $conn->error . "\n";
            }
        } else {
            echo "✓ total_days column already exists\n";
        }
        
        // Check and add other missing columns if needed
        $columnsToCheck = [
            'contact_during_leave' => "VARCHAR(20) AFTER reason",
            'applied_by' => "INT AFTER applied_date",
            'approved_by' => "INT AFTER applied_by",
            'approved_date' => "TIMESTAMP NULL AFTER approved_by",
            'rejection_reason' => "TEXT AFTER approved_date",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER rejection_reason",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
        ];
        
        foreach ($columnsToCheck as $column => $definition) {
            $checkCol = $conn->query("SHOW COLUMNS FROM leaves LIKE '$column'");
            if ($checkCol->num_rows == 0) {
                echo "Adding missing $column column...\n";
                $addCol = "ALTER TABLE leaves ADD COLUMN $column $definition";
                if ($conn->query($addCol)) {
                    echo "✓ $column column added successfully\n";
                } else {
                    echo "✗ Error adding $column column: " . $conn->error . "\n";
                }
            }
        }
    }
    
    // Check if permissions table exists
    $checkPermTable = $conn->query("SHOW TABLES LIKE 'permissions'");
    
    if ($checkPermTable->num_rows == 0) {
        echo "\nCreating permissions table...\n";
        // Create permissions table
        $createPermissionsTable = "
            CREATE TABLE permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                permission_date DATE NOT NULL,
                permission_type ENUM('early_departure', 'late_arrival', 'extended_lunch', 'personal_work') NOT NULL,
                from_time TIME NOT NULL,
                to_time TIME NOT NULL,
                reason TEXT NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                applied_by INT,
                approved_by INT,
                approved_date TIMESTAMP NULL,
                rejection_reason TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_employee_id (employee_id),
                INDEX idx_status (status),
                INDEX idx_permission_type (permission_type),
                INDEX idx_permission_date (permission_date),
                INDEX idx_applied_date (applied_date),
                FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
            )
        ";
        
        if ($conn->query($createPermissionsTable)) {
            echo "✓ Permissions table created successfully\n";
        } else {
            echo "✗ Error creating permissions table: " . $conn->error . "\n";
        }
    } else {
        echo "\n✓ Permissions table already exists\n";
    }
    
    // Show final table structures
    echo "\n=== FINAL TABLE STRUCTURES ===\n";
    
    echo "\nLeaves table structure:\n";
    $result = $conn->query("DESCRIBE leaves");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
    echo "\nPermissions table structure:\n";
    $result = $conn->query("DESCRIBE permissions");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
    echo "\n✓ Database structure check and fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
