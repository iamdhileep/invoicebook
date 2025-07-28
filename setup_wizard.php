<?php
// Advanced Attendance System Setup Wizard
session_start();
include 'db.php';

$step = $_GET['step'] ?? 1;
$setup_complete = false;

// Handle setup actions
if ($_POST['action'] ?? false) {
    switch ($_POST['action']) {
        case 'configure_basic':
            handleBasicConfiguration();
            break;
        case 'setup_smart_features':
            handleSmartFeaturesSetup();
            break;
        case 'configure_integrations':
            handleIntegrationsSetup();
            break;
        case 'finalize_setup':
            handleFinalizeSetup();
            break;
    }
}

function handleBasicConfiguration() {
    global $conn;
    
    // Create basic configuration
    $company_name = $_POST['company_name'] ?? 'Your Company';
    $timezone = $_POST['timezone'] ?? 'Asia/Kolkata';
    $work_start = $_POST['work_start'] ?? '09:00';
    $work_end = $_POST['work_end'] ?? '18:00';
    
    // Save configuration to database
    $config_data = [
        'company_name' => $company_name,
        'timezone' => $timezone,
        'work_hours' => ['start' => $work_start, 'end' => $work_end]
    ];
    
    // In a real implementation, save to config table
    $_SESSION['setup_step_1'] = true;
    $_SESSION['config'] = $config_data;
}

function handleSmartFeaturesSetup() {
    global $conn;
    
    $features = $_POST['features'] ?? [];
    $_SESSION['enabled_features'] = $features;
    $_SESSION['setup_step_2'] = true;
}

function handleIntegrationsSetup() {
    global $conn;
    
    $integrations = $_POST['integrations'] ?? [];
    $_SESSION['enabled_integrations'] = $integrations;
    $_SESSION['setup_step_3'] = true;
}

function handleFinalizeSetup() {
    global $conn;
    
    // Create admin user if specified
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_password = $_POST['admin_password'] ?? '';
    
    if ($admin_email && $admin_password) {
        // In real implementation, create admin user
        $_SESSION['admin_created'] = true;
    }
    
    $_SESSION['setup_complete'] = true;
    header('Location: demo_system.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Attendance Setup Wizard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .setup-wizard { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .wizard-container { background: white; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .step-indicator { position: relative; }
        .step-indicator::before { content: ''; position: absolute; top: 50%; left: 0; right: 0; height: 2px; background: #e0e0e0; z-index: 1; }
        .step { position: relative; z-index: 2; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: white; border: 2px solid #e0e0e0; color: #999; font-weight: bold; }
        .step.active { background: #007bff; border-color: #007bff; color: white; }
        .step.completed { background: #28a745; border-color: #28a745; color: white; }
        .feature-card { border: 2px solid #e0e0e0; border-radius: 10px; transition: all 0.3s; cursor: pointer; }
        .feature-card:hover { border-color: #007bff; transform: translateY(-2px); }
        .feature-card.selected { border-color: #007bff; background: #f8f9ff; }
        .integration-card { background: linear-gradient(45deg, #f093fb 0%, #f5576c 100%); }
        .setup-complete { background: linear-gradient(45deg, #4facfe 0%, #00f2fe 100%); }
    </style>
</head>
<body class="setup-wizard">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="wizard-container p-5">
                    <!-- Header -->
                    <div class="text-center mb-5">
                        <h1 class="display-5 mb-3">🚀 Advanced Attendance System</h1>
                        <p class="lead text-muted">Setup Wizard - Configure your enterprise attendance solution</p>
                    </div>

                    <!-- Step Indicator -->
                    <div class="step-indicator d-flex justify-content-between mb-5">
                        <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>">1</div>
                        <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>">2</div>
                        <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>">3</div>
                        <div class="step <?= $step >= 4 ? 'active' : '' ?> <?= $step > 4 ? 'completed' : '' ?>">4</div>
                    </div>

                    <!-- Step Content -->
                    <?php if ($step == 1): ?>
                        <!-- Step 1: Basic Configuration -->
                        <div class="text-center mb-4">
                            <h3><i class="bi bi-gear text-primary me-2"></i>Basic Configuration</h3>
                            <p class="text-muted">Configure your company details and working hours</p>
                        </div>

                        <form method="POST" action="?step=2">
                            <input type="hidden" name="action" value="configure_basic">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" class="form-control" name="company_name" value="Your Company" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Timezone</label>
                                        <select class="form-select" name="timezone">
                                            <option value="Asia/Kolkata">Asia/Kolkata (IST)</option>
                                            <option value="America/New_York">America/New_York (EST)</option>
                                            <option value="Europe/London">Europe/London (GMT)</option>
                                            <option value="Asia/Tokyo">Asia/Tokyo (JST)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Work Start Time</label>
                                        <input type="time" class="form-control" name="work_start" value="09:00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Work End Time</label>
                                        <input type="time" class="form-control" name="work_end" value="18:00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <div></div>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Next Step <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>

                    <?php elseif ($step == 2): ?>
                        <!-- Step 2: Smart Features -->
                        <div class="text-center mb-4">
                            <h3><i class="bi bi-robot text-success me-2"></i>Smart Features</h3>
                            <p class="text-muted">Select the advanced features you want to enable</p>
                        </div>

                        <form method="POST" action="?step=3">
                            <input type="hidden" name="action" value="setup_smart_features">

                            <div class="row">
                                <?php
                                $smart_features = [
                                    ['id' => 'face_recognition', 'title' => 'Face Recognition', 'desc' => 'Biometric attendance using facial recognition', 'icon' => 'bi-person-check'],
                                    ['id' => 'qr_scanner', 'title' => 'QR Code Scanner', 'desc' => 'Dynamic QR codes for location-based check-in', 'icon' => 'bi-qr-code-scan'],
                                    ['id' => 'gps_tracking', 'title' => 'GPS Tracking', 'desc' => 'Location-based attendance verification', 'icon' => 'bi-geo-alt'],
                                    ['id' => 'ai_suggestions', 'title' => 'AI Leave Suggestions', 'desc' => 'Smart leave recommendations using AI', 'icon' => 'bi-lightbulb'],
                                    ['id' => 'mobile_integration', 'title' => 'Mobile Integration', 'desc' => 'Mobile app support with offline sync', 'icon' => 'bi-phone'],
                                    ['id' => 'smart_notifications', 'title' => 'Smart Notifications', 'desc' => 'Multi-channel notification system', 'icon' => 'bi-bell']
                                ];

                                foreach ($smart_features as $feature): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="feature-card p-3 h-100" onclick="toggleFeature('<?= $feature['id'] ?>')">
                                            <div class="text-center">
                                                <i class="<?= $feature['icon'] ?> display-6 text-primary mb-2"></i>
                                                <h6><?= $feature['title'] ?></h6>
                                                <p class="small text-muted"><?= $feature['desc'] ?></p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="features[]" value="<?= $feature['id'] ?>" id="<?= $feature['id'] ?>" checked>
                                                    <label class="form-check-label" for="<?= $feature['id'] ?>">Enable</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="?step=1" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i> Previous
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Next Step <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>

                    <?php elseif ($step == 3): ?>
                        <!-- Step 3: Integrations -->
                        <div class="text-center mb-4">
                            <h3><i class="bi bi-cloud text-info me-2"></i>System Integrations</h3>
                            <p class="text-muted">Configure external system integrations</p>
                        </div>

                        <form method="POST" action="?step=4">
                            <input type="hidden" name="action" value="configure_integrations">

                            <div class="row">
                                <?php
                                $integrations = [
                                    ['id' => 'hrms', 'title' => 'HRMS Integration', 'desc' => 'Connect with HR Management System', 'icon' => 'bi-people'],
                                    ['id' => 'payroll', 'title' => 'Payroll System', 'desc' => 'Automatic salary calculations', 'icon' => 'bi-currency-rupee'],
                                    ['id' => 'biometric', 'title' => 'Biometric Devices', 'desc' => 'Fingerprint and face recognition devices', 'icon' => 'bi-fingerprint'],
                                    ['id' => 'slack', 'title' => 'Slack Integration', 'desc' => 'Team notifications via Slack', 'icon' => 'bi-slack'],
                                    ['id' => 'email', 'title' => 'Email Notifications', 'desc' => 'SMTP email configuration', 'icon' => 'bi-envelope'],
                                    ['id' => 'sms', 'title' => 'SMS Alerts', 'desc' => 'SMS notification gateway', 'icon' => 'bi-chat-text']
                                ];

                                foreach ($integrations as $integration): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="integration-card feature-card p-3 h-100 text-white" onclick="toggleIntegration('<?= $integration['id'] ?>')">
                                            <div class="text-center">
                                                <i class="<?= $integration['icon'] ?> display-6 mb-2"></i>
                                                <h6><?= $integration['title'] ?></h6>
                                                <p class="small"><?= $integration['desc'] ?></p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="integrations[]" value="<?= $integration['id'] ?>" id="int_<?= $integration['id'] ?>">
                                                    <label class="form-check-label" for="int_<?= $integration['id'] ?>">Enable</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="?step=2" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i> Previous
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    Next Step <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </div>
                        </form>

                    <?php elseif ($step == 4): ?>
                        <!-- Step 4: Finalize Setup -->
                        <div class="text-center mb-4">
                            <h3><i class="bi bi-check-circle text-success me-2"></i>Finalize Setup</h3>
                            <p class="text-muted">Complete your Advanced Attendance System setup</p>
                        </div>

                        <div class="setup-complete p-4 rounded text-white mb-4">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4><i class="bi bi-trophy me-2"></i>Setup Almost Complete!</h4>
                                    <p class="mb-0">Your Advanced Attendance System is ready to go. Create an admin account to get started.</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <i class="bi bi-rocket display-3"></i>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="finalize_setup">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Email (Optional)</label>
                                        <input type="email" class="form-control" name="admin_email" placeholder="admin@yourcompany.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Password (Optional)</label>
                                        <input type="password" class="form-control" name="admin_password" placeholder="Create secure password">
                                    </div>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>What's Included:</h6>
                                <ul class="mb-0">
                                    <li>13+ Advanced attendance features</li>
                                    <li>60+ API endpoints for integration</li>
                                    <li>Real-time analytics and reporting</li>
                                    <li>Mobile app ready infrastructure</li>
                                    <li>Enterprise-grade security</li>
                                </ul>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="?step=3" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-arrow-left me-2"></i> Previous
                                </a>
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle me-2"></i> Complete Setup
                                </button>
                            </div>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFeature(featureId) {
            const card = event.currentTarget;
            const checkbox = card.querySelector('input[type="checkbox"]');
            
            card.classList.toggle('selected');
            checkbox.checked = !checkbox.checked;
        }

        function toggleIntegration(integrationId) {
            const card = event.currentTarget;
            const checkbox = card.querySelector('input[type="checkbox"]');
            
            card.classList.toggle('selected');
            checkbox.checked = !checkbox.checked;
        }

        // Auto-check recommended features
        document.addEventListener('DOMContentLoaded', function() {
            const recommendedFeatures = ['face_recognition', 'ai_suggestions', 'mobile_integration'];
            recommendedFeatures.forEach(feature => {
                const element = document.getElementById(feature);
                if (element) {
                    element.checked = true;
                    element.closest('.feature-card').classList.add('selected');
                }
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>
