<?php
session_start();
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

include '../db.php';

$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get all payroll records for the specified month/year
$query = mysqli_query($conn, "
    SELECT p.id, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.employee_id as emp_id
    FROM hr_payroll p
    JOIN hr_employees e ON p.employee_id = e.id
    WHERE p.payroll_month = $month AND p.payroll_year = $year
    ORDER BY e.first_name, e.last_name
");

if (!$query || mysqli_num_rows($query) == 0) {
    die('<div class="alert alert-warning">No payroll records found for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . '</div>');
}

$period = date('F Y', mktime(0, 0, 0, $month, 1, $year));
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Payslips - <?= $period ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        .payslip-link { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-file-earmark-pdf text-primary"></i> Generate Payslips for <?= $period ?></h4>
            </div>
            <div class="card-body">
                <p class="text-muted">Click on individual employee names to generate and download their payslips:</p>
                
                <div class="row">
                    <?php while ($payroll = mysqli_fetch_assoc($query)): ?>
                        <div class="col-md-6 col-lg-4 payslip-link">
                            <a href="export_payslip.php?id=<?= $payroll['id'] ?>" 
                               target="_blank" 
                               class="btn btn-outline-primary w-100 text-start">
                                <i class="bi bi-person-circle me-2"></i>
                                <strong><?= htmlspecialchars($payroll['employee_name']) ?></strong>
                                <br>
                                <small class="text-muted"><?= htmlspecialchars($payroll['emp_id']) ?></small>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
                
                <hr>
                
                <div class="text-center">
                    <button class="btn btn-success me-2" onclick="downloadAllPayslips()">
                        <i class="bi bi-download"></i> Download All Payslips
                    </button>
                    <button class="btn btn-secondary" onclick="window.close()">
                        <i class="bi bi-x-circle"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function downloadAllPayslips() {
            const links = document.querySelectorAll('.payslip-link a');
            let delay = 0;
            
            links.forEach(link => {
                setTimeout(() => {
                    window.open(link.href, '_blank');
                }, delay);
                delay += 500; // 500ms delay between each download
            });
            
            alert('Opening payslips for all employees. Please check for popup blockers if some don\'t open.');
        }
    </script>
</body>
</html>
