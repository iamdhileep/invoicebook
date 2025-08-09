<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit;
}

// Include database connection
include '../db.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Database-driven predictive analytics data
$predictions = [];
$query = "SELECT * FROM prediction_analytics ORDER BY prediction_type";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $prediction = [
            'type' => $row['prediction_type'],
            'title' => $row['title'],
            'description' => $row['description'],
            'accuracy' => $row['accuracy'],
            'trend' => $row['trend'],
            'last_updated' => date('Y-m-d H:i:s') // Current timestamp
        ];
        
        // Add specific fields based on prediction type
        if ($row['prediction_type'] == 'turnover_risk') {
            $prediction['high_risk_count'] = $row['high_risk_count'];
            $prediction['medium_risk_count'] = $row['medium_risk_count'];
            $prediction['low_risk_count'] = $row['low_risk_count'];
        } elseif ($row['prediction_type'] == 'hiring_demand') {
            $prediction['predicted_hires'] = $row['predicted_value'];
            $prediction['peak_months'] = $row['peak_months'] ? json_decode($row['peak_months']) : [];
        } elseif ($row['prediction_type'] == 'skill_gaps') {
            $prediction['critical_gaps'] = $row['critical_gaps'];
            $prediction['moderate_gaps'] = $row['moderate_gaps'];
            $prediction['emerging_skills'] = $row['emerging_skills'] ? json_decode($row['emerging_skills']) : [];
        } elseif ($row['prediction_type'] == 'performance_forecast') {
            $prediction['improving_teams'] = 2;
            $prediction['declining_teams'] = 1;
            $prediction['stable_teams'] = 1;
        }
        
        $predictions[] = $prediction;
    }
}

// Database-driven risk employees data
$risk_employees = [];
$query = "SELECT employee_name as name, department_name as department, risk_level, risk_score, risk_factors FROM risk_predictions ORDER BY risk_score DESC";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $factors = $row['risk_factors'] ? json_decode($row['risk_factors']) : [];
        $risk_employees[] = [
            'name' => $row['name'],
            'department' => $row['department'],
            'risk_level' => $row['risk_level'],
            'risk_score' => $row['risk_score'],
            'factors' => $factors
        ];
    }
}

// Database-driven forecast models data
$forecast_models = [];
$query = "SELECT model_name, accuracy, last_trained FROM prediction_models WHERE model_status = 'active' ORDER BY accuracy DESC";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $forecast_models[$row['model_name']] = [
            'accuracy' => $row['accuracy'],
            'last_trained' => $row['last_trained']
        ];
    }
}

$current_page = 'predictive_analytics';
$page_title = 'Predictive Analytics - HRMS';

// Include global header and sidebar
include '../layouts/header.php';
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">📊 Predictive Analytics</h1>
                <p class="text-muted">Advanced machine learning models to forecast HR trends and outcomes</p>
            </div>
            <div>
                <button class="btn btn-outline-primary me-2" onclick="exportModels()">
                    <i class="bi bi-download me-2"></i>Export Models
                </button>
                <button class="btn btn-primary" onclick="retrainModels()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Retrain Models
                </button>
            </div>
        </div>

        <!-- Prediction Cards -->
        <div class="row mb-4">
            <?php foreach ($predictions as $prediction): ?>
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title"><?= htmlspecialchars($prediction['title']) ?></h5>
                            <span class="badge bg-success"><?= $prediction['accuracy'] ?>% Accuracy</span>
                        </div>
                        <p class="card-text text-muted"><?= htmlspecialchars($prediction['description']) ?></p>
                        
                        <div class="row text-center">
                            <?php if ($prediction['type'] == 'turnover_risk'): ?>
                                <div class="col-4">
                                    <div class="text-danger fw-bold"><?= $prediction['high_risk_count'] ?></div>
                                    <small class="text-muted">High Risk</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning fw-bold"><?= $prediction['medium_risk_count'] ?></div>
                                    <small class="text-muted">Medium Risk</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-success fw-bold"><?= $prediction['low_risk_count'] ?></div>
                                    <small class="text-muted">Low Risk</small>
                                </div>
                            <?php elseif ($prediction['type'] == 'hiring_demand'): ?>
                                <div class="col-6">
                                    <div class="text-primary fw-bold"><?= $prediction['predicted_hires'] ?></div>
                                    <small class="text-muted">Predicted Hires</small>
                                </div>
                                <div class="col-6">
                                    <div class="text-info fw-bold"><?= count($prediction['peak_months']) ?></div>
                                    <small class="text-muted">Peak Months</small>
                                </div>
                            <?php elseif ($prediction['type'] == 'performance_forecast'): ?>
                                <div class="col-4">
                                    <div class="text-success fw-bold"><?= $prediction['improving_teams'] ?></div>
                                    <small class="text-muted">Improving</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-secondary fw-bold"><?= $prediction['stable_teams'] ?></div>
                                    <small class="text-muted">Stable</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-danger fw-bold"><?= $prediction['declining_teams'] ?></div>
                                    <small class="text-muted">Declining</small>
                                </div>
                            <?php elseif ($prediction['type'] == 'skill_gaps'): ?>
                                <div class="col-6">
                                    <div class="text-danger fw-bold"><?= $prediction['critical_gaps'] ?></div>
                                    <small class="text-muted">Critical Gaps</small>
                                </div>
                                <div class="col-6">
                                    <div class="text-warning fw-bold"><?= $prediction['moderate_gaps'] ?></div>
                                    <small class="text-muted">Moderate Gaps</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">Last Updated: <?= date('M j, Y H:i', strtotime($prediction['last_updated'])) ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- High Risk Employees -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                    High Risk Employees - Turnover Prediction
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Risk Level</th>
                                <th>Risk Score</th>
                                <th>Key Factors</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($risk_employees as $employee): ?>
                            <tr>
                                <td><?= htmlspecialchars($employee['name']) ?></td>
                                <td><?= htmlspecialchars($employee['department']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $employee['risk_level'] == 'High' ? 'danger' : 'warning' ?>">
                                        <?= $employee['risk_level'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-<?= $employee['risk_level'] == 'High' ? 'danger' : 'warning' ?>" 
                                             style="width: <?= ($employee['risk_score'] * 100) ?>%"></div>
                                    </div>
                                    <small><?= number_format($employee['risk_score'] * 100, 1) ?>%</small>
                                </td>
                                <td>
                                    <?php foreach ($employee['factors'] as $factor): ?>
                                        <span class="badge bg-light text-dark me-1"><?= htmlspecialchars($factor) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="interveneEmployee('<?= htmlspecialchars($employee['name']) ?>', '<?= htmlspecialchars($employee['department']) ?>')">
                                        <i class="bi bi-person-check"></i> Intervene
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ML Models Performance -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-cpu text-info me-2"></i>
                            Machine Learning Models Performance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($forecast_models as $model => $data): ?>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= htmlspecialchars($model) ?></h6>
                                        <div class="h4 text-primary"><?= $data['accuracy'] ?>%</div>
                                        <small class="text-muted">Accuracy</small>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                Trained: <?= date('M j', strtotime($data['last_trained'])) ?>
                                            </small>
                                        </div>
                                        <div class="progress mt-2" style="height: 4px;">
                                            <div class="progress-bar" style="width: <?= $data['accuracy'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
</div>

<script>
    // Show alert function
    function showAlert(message, type = 'info') {
        const alertDiv = `
            <div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertDiv);
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.textContent.includes(message)) {
                    alert.remove();
                }
            });
        }, 5000);
    }

    // Export models function
    function exportModels() {
        showAlert('Preparing models export...', 'info');
        
        fetch('predictive_analytics_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'export_models'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create download link
                const link = document.createElement('a');
                link.href = data.download_url;
                link.download = data.filename;
                link.click();
                showAlert('Models exported successfully!', 'success');
            } else {
                showAlert('Export failed: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Export failed. Please try again.', 'danger');
        });
    }

    // Retrain models function
    function retrainModels() {
        if (confirm('Are you sure you want to retrain all models? This may take several minutes.')) {
            showAlert('Retraining models... This may take a while.', 'warning');
            
            fetch('predictive_analytics_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'retrain_models'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Models retrained successfully! Accuracy updated.', 'success');
                    // Refresh page after 3 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    showAlert('Retraining failed: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Retraining failed. Please try again.', 'danger');
            });
        }
    }

    // Intervene employee function
    function interveneEmployee(employeeName, department) {
        // Open intervention modal
        const modalHtml = `
            <div class="modal fade" id="interventionModal" tabindex="-1" aria-labelledby="interventionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="interventionModalLabel">
                                <i class="bi bi-person-check me-2"></i>Employee Intervention Plan
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <strong>High Risk Employee Detected:</strong> ${employeeName} (${department})
                            </div>
                            
                            <h6>Recommended Actions:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">1-on-1 Meeting</h6>
                                            <p class="card-text small">Schedule immediate meeting to discuss concerns and career goals.</p>
                                            <button class="btn btn-sm btn-primary" onclick="scheduleOneOnOne('${employeeName}')">Schedule</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Development Plan</h6>
                                            <p class="card-text small">Create personalized development and training plan.</p>
                                            <button class="btn btn-sm btn-success" onclick="createDevPlan('${employeeName}')">Create Plan</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Compensation Review</h6>
                                            <p class="card-text small">Review salary and benefits package.</p>
                                            <button class="btn btn-sm btn-info" onclick="reviewCompensation('${employeeName}')">Review</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">Team Transfer</h6>
                                            <p class="card-text small">Consider transfer to better-fit team or role.</p>
                                            <button class="btn btn-sm btn-warning" onclick="considerTransfer('${employeeName}')">Explore</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" onclick="createInterventionPlan('${employeeName}', '${department}')">Create Full Plan</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('interventionModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to DOM and show
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('interventionModal'));
        modal.show();
    }

    // Intervention action functions
    function scheduleOneOnOne(employeeName) {
        showAlert(`One-on-one meeting scheduled for ${employeeName}`, 'success');
        logInterventionAction(employeeName, 'one_on_one_scheduled');
    }

    function createDevPlan(employeeName) {
        showAlert(`Development plan created for ${employeeName}`, 'success');
        logInterventionAction(employeeName, 'development_plan_created');
    }

    function reviewCompensation(employeeName) {
        showAlert(`Compensation review initiated for ${employeeName}`, 'info');
        logInterventionAction(employeeName, 'compensation_review');
    }

    function considerTransfer(employeeName) {
        showAlert(`Transfer options being explored for ${employeeName}`, 'warning');
        logInterventionAction(employeeName, 'transfer_consideration');
    }

    function createInterventionPlan(employeeName, department) {
        fetch('predictive_analytics_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create_intervention_plan',
                employee_name: employeeName,
                department: department
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert(`Comprehensive intervention plan created for ${employeeName}`, 'success');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('interventionModal'));
                modal.hide();
            } else {
                showAlert('Failed to create intervention plan: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('Failed to create intervention plan. Please try again.', 'danger');
        });
    }

    function logInterventionAction(employeeName, action) {
        fetch('predictive_analytics_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'log_intervention',
                employee_name: employeeName,
                intervention_type: action
            })
        })
        .catch(error => console.error('Logging error:', error));
    }
</script>

<?php
// Include global footer
include '../layouts/footer.php';
?>