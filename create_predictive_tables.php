<?php
// Create missing tables for predictive analytics functionality

$conn = new mysqli('localhost', 'root', '', 'billing_demo');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Creating missing tables for predictive analytics...\n\n";

// 1. ML Training Log Table
$sql = "CREATE TABLE IF NOT EXISTS ml_training_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trained_by VARCHAR(100) NOT NULL,
    training_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    models_updated INT DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✓ ml_training_log table created\n";
} else {
    echo "✗ Error creating ml_training_log: " . $conn->error . "\n";
}

// 2. Intervention Plans Table
$sql = "CREATE TABLE IF NOT EXISTS intervention_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id VARCHAR(50) UNIQUE NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    department VARCHAR(100),
    interventions JSON,
    created_by VARCHAR(100) NOT NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✓ intervention_plans table created\n";
} else {
    echo "✗ Error creating intervention_plans: " . $conn->error . "\n";
}

// 3. Intervention Log Table
$sql = "CREATE TABLE IF NOT EXISTS intervention_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_name VARCHAR(255) NOT NULL,
    intervention_type VARCHAR(100) NOT NULL,
    performed_by VARCHAR(100) NOT NULL,
    action_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT
)";

if ($conn->query($sql)) {
    echo "✓ intervention_log table created\n";
} else {
    echo "✗ Error creating intervention_log: " . $conn->error . "\n";
}

// 4. Add missing columns to existing tables if they don't exist
echo "\nUpdating existing tables...\n";

// Check if intervention_status column exists in risk_predictions
$result = $conn->query("SHOW COLUMNS FROM risk_predictions LIKE 'intervention_status'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE risk_predictions ADD COLUMN intervention_status VARCHAR(50) DEFAULT 'none', ADD COLUMN last_intervention TIMESTAMP NULL";
    if ($conn->query($sql)) {
        echo "✓ Added intervention columns to risk_predictions\n";
    } else {
        echo "✗ Error updating risk_predictions: " . $conn->error . "\n";
    }
} else {
    echo "✓ risk_predictions already has intervention columns\n";
}

// 5. Create exports directory
$exportDir = '../exports';
if (!is_dir($exportDir)) {
    if (mkdir($exportDir, 0755, true)) {
        echo "✓ Created exports directory\n";
    } else {
        echo "✗ Failed to create exports directory\n";
    }
} else {
    echo "✓ Exports directory already exists\n";
}

echo "\n=== Database setup completed! ===\n";
$conn->close();
?>
