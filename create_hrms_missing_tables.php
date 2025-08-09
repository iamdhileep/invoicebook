<?php
include 'db.php';

echo "Creating missing HRMS tables...\n";

// Create leave_requests table
$leave_table = "
CREATE TABLE IF NOT EXISTS hr_leave_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type ENUM('sick', 'vacation', 'personal', 'maternity', 'paternity', 'emergency') DEFAULT 'vacation',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_requested INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    comments TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id),
    FOREIGN KEY (approved_by) REFERENCES hr_employees(id)
)";

if (mysqli_query($conn, $leave_table)) {
    echo "✓ hr_leave_requests table created\n";
} else {
    echo "✗ Error creating hr_leave_requests: " . mysqli_error($conn) . "\n";
}

// Create positions table
$positions_table = "
CREATE TABLE IF NOT EXISTS hr_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) NOT NULL,
    department_id INT,
    description TEXT,
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    requirements TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES hr_departments(id)
)";

if (mysqli_query($conn, $positions_table)) {
    echo "✓ hr_positions table created\n";
} else {
    echo "✗ Error creating hr_positions: " . mysqli_error($conn) . "\n";
}

// Create training table
$training_table = "
CREATE TABLE IF NOT EXISTS hr_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    trainer VARCHAR(100),
    start_date DATE,
    end_date DATE,
    max_participants INT DEFAULT 50,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (mysqli_query($conn, $training_table)) {
    echo "✓ hr_training table created\n";
} else {
    echo "✗ Error creating hr_training: " . mysqli_error($conn) . "\n";
}

// Create training participants table
$training_participants_table = "
CREATE TABLE IF NOT EXISTS hr_training_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    training_id INT NOT NULL,
    employee_id INT NOT NULL,
    status ENUM('enrolled', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_date DATE NULL,
    score DECIMAL(5,2) NULL,
    certificate_issued BOOLEAN DEFAULT FALSE,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (training_id) REFERENCES hr_training(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES hr_employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (training_id, employee_id)
)";

if (mysqli_query($conn, $training_participants_table)) {
    echo "✓ hr_training_participants table created\n";
} else {
    echo "✗ Error creating hr_training_participants: " . mysqli_error($conn) . "\n";
}

// Create assets table
$assets_table = "
CREATE TABLE IF NOT EXISTS hr_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_name VARCHAR(255) NOT NULL,
    asset_type ENUM('laptop', 'desktop', 'phone', 'vehicle', 'equipment', 'software', 'other') DEFAULT 'other',
    asset_tag VARCHAR(50) UNIQUE,
    serial_number VARCHAR(100),
    brand VARCHAR(100),
    model VARCHAR(100),
    purchase_date DATE,
    purchase_cost DECIMAL(10,2),
    warranty_expiry DATE,
    assigned_to INT NULL,
    assigned_date DATE NULL,
    status ENUM('available', 'assigned', 'maintenance', 'retired') DEFAULT 'available',
    condition_status ENUM('excellent', 'good', 'fair', 'poor') DEFAULT 'good',
    location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES hr_employees(id)
)";

if (mysqli_query($conn, $assets_table)) {
    echo "✓ hr_assets table created\n";
} else {
    echo "✗ Error creating hr_assets: " . mysqli_error($conn) . "\n";
}

// Create announcements table
$announcements_table = "
CREATE TABLE IF NOT EXISTS hr_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('general', 'policy', 'event', 'urgent', 'celebration') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    target_audience ENUM('all', 'management', 'employees', 'department') DEFAULT 'all',
    department_id INT NULL,
    author_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    publish_date DATE,
    expiry_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES hr_departments(id),
    FOREIGN KEY (author_id) REFERENCES hr_employees(id)
)";

if (mysqli_query($conn, $announcements_table)) {
    echo "✓ hr_announcements table created\n";
} else {
    echo "✗ Error creating hr_announcements: " . mysqli_error($conn) . "\n";
}

// Insert sample data for positions
$sample_positions = [
    ['Software Engineer', 'IT', 'Develop and maintain software applications'],
    ['Project Manager', 'IT', 'Manage software development projects'],
    ['HR Manager', 'Human Resources', 'Oversee HR operations and policies'],
    ['Accountant', 'Finance', 'Handle financial records and transactions'],
    ['Sales Executive', 'Sales', 'Drive sales and customer relationships']
];

foreach ($sample_positions as $pos) {
    $dept_query = mysqli_query($conn, "SELECT id FROM hr_departments WHERE department_name = '{$pos[1]}' LIMIT 1");
    $dept_id = ($dept_query && $row = mysqli_fetch_assoc($dept_query)) ? $row['id'] : 'NULL';
    
    $insert_pos = "INSERT IGNORE INTO hr_positions (position_name, department_id, description) 
                   VALUES ('{$pos[0]}', $dept_id, '{$pos[2]}')";
    mysqli_query($conn, $insert_pos);
}

echo "\nAll missing HRMS tables created successfully!\n";
?>
