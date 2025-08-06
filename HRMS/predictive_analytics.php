<?php
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
require_once '../db.php';

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

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-2">
                            <i class="bi bi-graph-up-arrow me-3"></i>Predictive Analytics
                        </h1>
                        <p class="text-muted mb-0">Advanced machine learning models to forecast HR trends and outcomes</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2">
                            <i class="bi bi-download me-2"></i>Export Models
                        </button>
                        <button class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise me-2"></i>Retrain Models
                        </button>
                    </div>
                </div>
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
                                    <button class="btn btn-sm btn-outline-primary">
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

<script>
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
</script>

<?php if (!isset($root_path)) 
include '../layouts/footer.php'; ?>
