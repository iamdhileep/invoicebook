<?php
require_once '../db.php';

echo "Adding missing columns to backup tables...\n";

// Add missing columns to backup_schedule
$alterSchedule = [
    "ALTER TABLE backup_schedule ADD COLUMN backup_type ENUM('full', 'selective', 'incremental') DEFAULT 'full' AFTER schedule_time",
    "ALTER TABLE backup_schedule ADD COLUMN tables_to_backup TEXT AFTER backup_type",
    "ALTER TABLE backup_schedule ADD COLUMN compression_enabled BOOLEAN DEFAULT TRUE AFTER tables_to_backup",
    "ALTER TABLE backup_schedule ADD COLUMN retention_days INT DEFAULT 30 AFTER compression_enabled"
];

foreach ($alterSchedule as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Schedule table column added successfully\n";
    } else {
        echo "⚠️ Column may already exist or error: " . $conn->error . "\n";
    }
}

// Add missing columns to backup_history
$alterHistory = [
    "ALTER TABLE backup_history ADD COLUMN filename VARCHAR(255) AFTER backup_name",
    "ALTER TABLE backup_history ADD COLUMN file_path VARCHAR(500) AFTER filename",
    "ALTER TABLE backup_history ADD COLUMN file_size BIGINT AFTER file_path",
    "ALTER TABLE backup_history CHANGE backup_type backup_type ENUM('full', 'selective', 'incremental', 'manual', 'scheduled', 'auto') DEFAULT 'full'",
    "ALTER TABLE backup_history ADD COLUMN compression_used BOOLEAN DEFAULT FALSE AFTER backup_type",
    "ALTER TABLE backup_history ADD COLUMN tables_backed_up TEXT AFTER compression_used"
];

foreach ($alterHistory as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ History table column added successfully\n";
    } else {
        echo "⚠️ Column may already exist or error: " . $conn->error . "\n";
    }
}

// Now insert the sample schedule
$sampleSchedule = "
INSERT IGNORE INTO backup_schedule 
(schedule_name, schedule_type, schedule_time, backup_type, compression_enabled, retention_days, created_by) 
VALUES 
('Daily Full Backup', 'daily', '02:00:00', 'full', TRUE, 30, 1)
";

if ($conn->query($sampleSchedule) === TRUE) {
    echo "✅ Sample backup schedule created\n";
} else {
    echo "❌ Error creating sample schedule: " . $conn->error . "\n";
}

echo "\n🚀 Backup system database structure updated!\n";

$conn->close();
?>
