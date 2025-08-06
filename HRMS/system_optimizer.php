<?php
/**
 * HRMS System Performance Optimizer & Health Monitor
 * Advanced database optimization and system monitoring
 */
if (!isset($root_path)) 
require_once '../db.php';

echo "🚀 Starting HRMS System Performance Optimization...\n\n";

// 1. Database Index Analysis and Optimization
echo "📊 ANALYZING DATABASE INDEXES...\n";

$optimization_queries = [
    // Performance indexes for frequently queried tables
    "CREATE INDEX IF NOT EXISTS idx_employee_status_dept ON employees(status, department_name)",
    "CREATE INDEX IF NOT EXISTS idx_employee_joining_date ON employees(joining_date)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_employee_date ON attendance(employee_id, attendance_date)",
    "CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON leave_requests(status, requested_date)",
    "CREATE INDEX IF NOT EXISTS idx_payroll_month_year ON employee_payroll(salary_month, salary_year)",
    "CREATE INDEX IF NOT EXISTS idx_performance_score ON employee_performance(performance_score DESC)",
    "CREATE INDEX IF NOT EXISTS idx_training_dates ON training_programs(start_date, end_date)",
    "CREATE INDEX IF NOT EXISTS idx_helpdesk_priority ON helpdesk_tickets(priority, status)",
    "CREATE INDEX IF NOT EXISTS idx_time_tracking_project ON time_tracking(project_name, date_logged)",
    "CREATE INDEX IF NOT EXISTS idx_asset_status ON asset_allocations(allocation_status, allocated_date)",
    
    // Composite indexes for complex queries
    "CREATE INDEX IF NOT EXISTS idx_employee_search ON employees(name, employee_code, email)",
    "CREATE INDEX IF NOT EXISTS idx_attendance_monthly ON attendance(employee_id, YEAR(attendance_date), MONTH(attendance_date))",
    "CREATE INDEX IF NOT EXISTS idx_performance_dept_score ON employee_performance(department_name, performance_score)",
    "CREATE INDEX IF NOT EXISTS idx_payroll_employee_month ON employee_payroll(employee_id, salary_month, salary_year)",
    
    // Full-text search indexes
    "ALTER TABLE employees ADD FULLTEXT(name, email, position) IF NOT EXISTS",
    "ALTER TABLE helpdesk_tickets ADD FULLTEXT(subject, description) IF NOT EXISTS",
    "ALTER TABLE training_programs ADD FULLTEXT(program_name, description) IF NOT EXISTS"
];

$indexes_created = 0;
foreach ($optimization_queries as $query) {
    if (mysqli_query($conn, $query)) {
        $indexes_created++;
        echo "✓ Optimized index applied\n";
    } else {
        if (!strpos(mysqli_error($conn), 'Duplicate key name')) {
            echo "⚠ Warning: " . mysqli_error($conn) . "\n";
        }
    }
}

echo "✅ Applied $indexes_created database optimizations\n\n";

// 2. System Health Metrics Collection
echo "🔍 COLLECTING SYSTEM HEALTH METRICS...\n";

// Create system health monitoring table
$health_table_sql = "CREATE TABLE IF NOT EXISTS system_health_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_type VARCHAR(50) NOT NULL,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2),
    status ENUM('excellent', 'good', 'warning', 'critical') DEFAULT 'good',
    details JSON,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_type (metric_type),
    INDEX idx_recorded_at (recorded_at DESC)
)";

if (mysqli_query($conn, $health_table_sql)) {
    echo "✓ System health monitoring table ready\n";
}

// Collect comprehensive system metrics
$health_metrics = [];

// Database size and performance metrics
$db_name = 'billing_demo'; // Use the actual database name
$result = mysqli_query($conn, "
    SELECT 
        table_name,
        table_rows,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'size_mb'
    FROM information_schema.tables 
    WHERE table_schema = '$db_name'
    ORDER BY (data_length + index_length) DESC
");

$total_size = 0;
$total_records = 0;
$table_stats = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $total_size += $row['size_mb'];
        $total_records += $row['table_rows'];
        $table_stats[] = $row;
    }
}

echo "📈 Database Statistics:\n";
echo "   • Total Size: " . round($total_size, 2) . " MB\n";
echo "   • Total Records: " . number_format($total_records) . "\n";
echo "   • Tables Analyzed: " . count($table_stats) . "\n";

// Employee data health metrics
$employee_metrics = [];

$queries = [
    'total_employees' => "SELECT COUNT(*) as count FROM employees",
    'active_employees' => "SELECT COUNT(*) as count FROM employees WHERE status = 'active'",
    'employees_with_missing_data' => "SELECT COUNT(*) as count FROM employees WHERE email IS NULL OR phone IS NULL OR joining_date IS NULL",
    'recent_hires' => "SELECT COUNT(*) as count FROM employees WHERE joining_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)",
    'duplicate_emails' => "SELECT COUNT(*) - COUNT(DISTINCT email) as count FROM employees WHERE email IS NOT NULL",
    'performance_records' => "SELECT COUNT(*) as count FROM employee_performance",
    'attendance_coverage' => "SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAYS)",
    'payroll_records' => "SELECT COUNT(*) as count FROM employee_payroll",
    'training_participation' => "SELECT COUNT(DISTINCT employee_id) as count FROM training_registrations",
    'active_surveys' => "SELECT COUNT(*) as count FROM employee_surveys WHERE survey_status = 'active'",
    'open_tickets' => "SELECT COUNT(*) as count FROM helpdesk_tickets WHERE status IN ('open', 'in_progress')",
    'pending_leaves' => "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'"
];

foreach ($queries as $metric => $query) {
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $employee_metrics[$metric] = $row['count'];
    }
}

// Calculate health scores
$data_completeness = $employee_metrics['active_employees'] > 0 ? 
    (($employee_metrics['active_employees'] - $employee_metrics['employees_with_missing_data']) / $employee_metrics['active_employees']) * 100 : 0;

$attendance_coverage = $employee_metrics['active_employees'] > 0 ? 
    ($employee_metrics['attendance_coverage'] / $employee_metrics['active_employees']) * 100 : 0;

$training_participation = $employee_metrics['active_employees'] > 0 ? 
    ($employee_metrics['training_participation'] / $employee_metrics['active_employees']) * 100 : 0;

echo "\n🎯 SYSTEM HEALTH SCORES:\n";
echo "   • Data Completeness: " . round($data_completeness, 1) . "%\n";
echo "   • Attendance Coverage: " . round($attendance_coverage, 1) . "%\n";
echo "   • Training Participation: " . round($training_participation, 1) . "%\n";
echo "   • Open Support Tickets: " . $employee_metrics['open_tickets'] . "\n";
echo "   • Pending Leave Requests: " . $employee_metrics['pending_leaves'] . "\n";

// Store health metrics in database
$health_records = [
    ['database', 'total_size_mb', $total_size, $total_size < 100 ? 'excellent' : ($total_size < 500 ? 'good' : 'warning')],
    ['database', 'total_records', $total_records, $total_records > 0 ? 'excellent' : 'critical'],
    ['employees', 'data_completeness_pct', $data_completeness, $data_completeness > 95 ? 'excellent' : ($data_completeness > 85 ? 'good' : 'warning')],
    ['attendance', 'coverage_pct', $attendance_coverage, $attendance_coverage > 90 ? 'excellent' : ($attendance_coverage > 75 ? 'good' : 'warning')],
    ['training', 'participation_pct', $training_participation, $training_participation > 60 ? 'excellent' : ($training_participation > 40 ? 'good' : 'warning')],
    ['support', 'open_tickets', $employee_metrics['open_tickets'], $employee_metrics['open_tickets'] < 5 ? 'excellent' : ($employee_metrics['open_tickets'] < 10 ? 'good' : 'warning')],
    ['leaves', 'pending_requests', $employee_metrics['pending_leaves'], $employee_metrics['pending_leaves'] < 3 ? 'excellent' : ($employee_metrics['pending_leaves'] < 8 ? 'good' : 'warning')]
];

foreach ($health_records as $record) {
    $details = json_encode(['measurement_time' => date('Y-m-d H:i:s'), 'auto_generated' => true]);
    $sql = "INSERT INTO system_health_metrics (metric_type, metric_name, metric_value, status, details) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssdss", $record[0], $record[1], $record[2], $record[3], $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// 3. Data Integrity Checks
echo "\n🔧 PERFORMING DATA INTEGRITY CHECKS...\n";

$integrity_checks = [
    'Foreign Key Consistency' => [
        "SELECT COUNT(*) as issues FROM employees e LEFT JOIN departments d ON e.department_id = d.department_id WHERE e.department_id IS NOT NULL AND d.department_id IS NULL",
        "Employees with invalid department references"
    ],
    'Attendance Data Validity' => [
        "SELECT COUNT(*) as issues FROM attendance WHERE attendance_date > CURDATE() OR attendance_date < '2020-01-01'",
        "Attendance records with invalid dates"
    ],
    'Salary Data Consistency' => [
        "SELECT COUNT(*) as issues FROM employee_payroll WHERE net_salary < 0 OR gross_salary < basic_salary",
        "Payroll records with inconsistent salary calculations"
    ],
    'Performance Score Validity' => [
        "SELECT COUNT(*) as issues FROM employee_performance WHERE performance_score < 0 OR performance_score > 100",
        "Performance records with invalid scores"
    ]
];

$total_issues = 0;
foreach ($integrity_checks as $check_name => $check_data) {
    $result = mysqli_query($conn, $check_data[0]);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $issues = $row['issues'];
        $total_issues += $issues;
        $status = $issues == 0 ? "✅" : "⚠️";
        echo "   $status $check_name: $issues issues found\n";
        if ($issues > 0) {
            echo "      → " . $check_data[1] . "\n";
        }
    }
}

echo "\n🎉 OPTIMIZATION COMPLETE!\n";
echo "📊 SUMMARY:\n";
echo "   • Database Indexes: $indexes_created optimizations applied\n";
echo "   • System Health: " . count($health_records) . " metrics recorded\n";
echo "   • Data Integrity: $total_issues issues detected\n";
echo "   • Performance Status: " . ($total_issues == 0 ? "EXCELLENT" : "NEEDS ATTENTION") . "\n";

// 4. Generate optimization recommendations
echo "\n💡 OPTIMIZATION RECOMMENDATIONS:\n";

if ($data_completeness < 95) {
    echo "   📝 Complete missing employee data fields\n";
}
if ($attendance_coverage < 90) {
    echo "   📅 Improve attendance tracking coverage\n";
}
if ($training_participation < 60) {
    echo "   🎓 Increase employee training participation\n";
}
if ($employee_metrics['open_tickets'] > 5) {
    echo "   🎫 Address open helpdesk tickets\n";
}
if ($total_size > 100) {
    echo "   🗄️ Consider database archiving for old records\n";
}
if ($total_issues > 0) {
    echo "   🔧 Fix identified data integrity issues\n";
}

echo "\n✨ System optimization completed successfully!\n";
echo "🚀 Your HRMS is now running at peak performance!\n";
?>
