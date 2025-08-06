<?php
session_start();
// Check for either session variable for compatibility
if (!isset($_SESSION['admin']) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include config and database
if (!isset($root_path)) 
require_once '../config.php';
if (!isset($root_path)) 
include '../db.php';

$page_title = 'Document Verification - HRMS';

// Fetch pending documents from database
$pending_documents = [];
$result = mysqli_query($conn, "
    SELECT id, employee_name as employee, document_type, submitted_date, status, urgency
    FROM document_verifications 
    WHERE status IN ('pending', 'under_review', 'requires_clarification')
    ORDER BY urgency DESC, submitted_date ASC
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pending_documents[] = $row;
    }
}

// Fetch recent verifications from database
$recent_verifications = [];
$result = mysqli_query($conn, "
    SELECT id, employee_name as employee, document_type, verification_date as verified_date, 
           status, verified_by_name as verified_by
    FROM document_verifications 
    WHERE status IN ('approved', 'rejected') AND verification_date IS NOT NULL
    ORDER BY verification_date DESC
    LIMIT 10
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_verifications[] = $row;
    }
}

$current_page = 'document_verification';

include '../layouts/header.php';
if (!isset($root_path)) 
include '../layouts/sidebar.php';
?>

<div class="main-content animate-fade-in-up">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="gradient-text mb-2" style="font-size: 2.5rem; font-weight: 700;">
                    <i class="bi bi-file-earmark-check text-primary me-3"></i>Document Verification
                </h1>
                <p class="text-muted" style="font-size: 1.1rem;">Review and verify employee documents and certificates</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="generateReport()">
                    <i class="bi bi-download me-2"></i>Generate Report
                </button>
                <button class="btn btn-primary" onclick="showBulkUploadModal()">
                    <i class="bi bi-cloud-upload me-2"></i>Bulk Upload
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock-fill text-warning fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">4</h3>
                        <p class="text-muted mb-0">Pending Verification</p>
                        <small class="text-warning">Requires immediate attention</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle-fill text-success fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">12</h3>
                        <p class="text-muted mb-0">Verified This Week</p>
                        <small class="text-success">Documents approved</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-x-circle-fill text-danger fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">2</h3>
                        <p class="text-muted mb-0">Rejected Documents</p>
                        <small class="text-danger">Need resubmission</small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-file-earmark-text-fill text-primary fs-2"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">95%</h3>
                        <p class="text-muted mb-0">Verification Rate</p>
                        <small class="text-primary">This month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pending" role="tab">
                            <i class="bi bi-clock me-2"></i>Pending Verification
                            <span class="badge bg-warning ms-2">4</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#recent" role="tab">
                            <i class="bi bi-check-circle me-2"></i>Recent Verifications
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#upload" role="tab">
                            <i class="bi bi-cloud-upload me-2"></i>Document Upload
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#templates" role="tab">
                            <i class="bi bi-file-earmark-text me-2"></i>Document Templates
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content">
                    <!-- Pending Verification Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <div class="row g-4">
                            <?php foreach ($pending_documents as $doc): ?>
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($doc['document_type']) ?></h6>
                                                <div class="text-muted small">
                                                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($doc['employee']) ?>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    Submitted: <?= date('M j, Y', strtotime($doc['submitted_date'])) ?>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-column align-items-end gap-2">
                                                <?php 
                                                $urgencyClass = [
                                                    'high' => 'bg-danger text-white',
                                                    'medium' => 'bg-warning text-dark', 
                                                    'low' => 'bg-info text-white'
                                                ][$doc['urgency']];
                                                
                                                $statusClass = [
                                                    'pending' => 'bg-warning text-dark',
                                                    'under_review' => 'bg-primary text-white',
                                                    'requires_clarification' => 'bg-secondary text-white'
                                                ][$doc['status']];
                                                ?>
                                                <span class="badge <?= $urgencyClass ?> rounded-pill">
                                                    <?= ucfirst($doc['urgency']) ?>
                                                </span>
                                                <span class="badge <?= $statusClass ?> rounded-pill">
                                                    <?= ucwords(str_replace('_', ' ', $doc['status'])) ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary flex-fill" onclick="viewDocument(<?= $doc['id'] ?>)" title="View Document">
                                                <i class="bi bi-eye me-1"></i>View
                                            </button>
                                            <button class="btn btn-sm btn-outline-success" onclick="approveDocument(<?= $doc['id'] ?>)" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="rejectDocument(<?= $doc['id'] ?>)" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="requestClarification(<?= $doc['id'] ?>)" title="Request Clarification">
                                                <i class="bi bi-question-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Verifications Tab -->
                    <div class="tab-pane fade" id="recent" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Document Type</th>
                                        <th>Verification Date</th>
                                        <th>Status</th>
                                        <th>Verified By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_verifications as $verification): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 0.875rem;">
                                                    <?= strtoupper(substr($verification['employee'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($verification['employee']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($verification['document_type']) ?></td>
                                        <td><?= date('M j, Y', strtotime($verification['verified_date'])) ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = [
                                                'approved' => 'bg-success text-white',
                                                'rejected' => 'bg-danger text-white'
                                            ][$verification['status']];
                                            ?>
                                            <span class="badge <?= $statusClass ?> rounded-pill">
                                                <?= ucfirst($verification['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($verification['verified_by']) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(<?= $verification['id'] ?>)" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" onclick="downloadDocument(<?= $verification['id'] ?>)" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Document Upload Tab -->
                    <div class="tab-pane fade" id="upload" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card border-2 border-dashed border-primary bg-light">
                                    <div class="card-body text-center py-5">
                                        <i class="bi bi-cloud-upload display-1 text-primary opacity-75"></i>
                                        <h5 class="mt-3">Upload Documents</h5>
                                        <p class="text-muted">Drag and drop files here or click to browse</p>
                                        <button class="btn btn-primary" onclick="browseFiles()">
                                            <i class="bi bi-folder2-open me-2"></i>Browse Files
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card shadow-sm">
                                    <div class="card-header bg-gradient bg-primary text-white">
                                        <h6 class="mb-0">
                                            <i class="bi bi-info-circle me-2"></i>Upload Information
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Employee</label>
                                            <select class="form-select">
                                                <option>Select Employee</option>
                                                <option>John Doe</option>
                                                <option>Jane Smith</option>
                                                <option>Mike Johnson</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Document Type</label>
                                            <select class="form-select">
                                                <option>Select Type</option>
                                                <option>Identity Proof</option>
                                                <option>Address Proof</option>
                                                <option>Educational Certificate</option>
                                                <option>Experience Letter</option>
                                                <option>Medical Certificate</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Priority</label>
                                            <select class="form-select">
                                                <option>Normal</option>
                                                <option>High</option>
                                                <option>Urgent</option>
                                            </select>
                                        </div>
                                        <button class="btn btn-success w-100">
                                            <i class="bi bi-check-lg me-2"></i>Submit for Verification
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Templates Tab -->
                    <div class="tab-pane fade" id="templates" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-center mb-3">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                                <i class="bi bi-list-check text-primary fs-2"></i>
                                            </div>
                                        </div>
                                        <h6 class="fw-bold text-center mb-2">Document Checklist</h6>
                                        <p class="text-muted text-center small">Complete list of required documents for new employees</p>
                                        <div class="text-center">
                                            <button class="btn btn-outline-primary btn-sm" onclick="downloadTemplate('checklist')">
                                                <i class="bi bi-download me-1"></i>Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-center mb-3">
                                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                                <i class="bi bi-file-earmark-check text-success fs-2"></i>
                                            </div>
                                        </div>
                                        <h6 class="fw-bold text-center mb-2">Verification Form</h6>
                                        <p class="text-muted text-center small">Standard form for document verification process</p>
                                        <div class="text-center">
                                            <button class="btn btn-outline-success btn-sm" onclick="downloadTemplate('verification')">
                                                <i class="bi bi-download me-1"></i>Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-center mb-3">
                                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                                <i class="bi bi-file-earmark-text text-warning fs-2"></i>
                                            </div>
                                        </div>
                                        <h6 class="fw-bold text-center mb-2">Declaration Form</h6>
                                        <p class="text-muted text-center small">Employee declaration for document authenticity</p>
                                        <div class="text-center">
                                            <button class="btn btn-outline-warning btn-sm" onclick="downloadTemplate('declaration')">
                                                <i class="bi bi-download me-1"></i>Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDocument(id) {
            showAlert('Opening document viewer...', 'info');
        }

        function approveDocument(id) {
            if (confirm('Are you sure you want to approve this document?')) {
                showAlert('Document approved successfully!', 'success');
            }
        }

        function rejectDocument(id) {
            if (confirm('Are you sure you want to reject this document?')) {
                showAlert('Document rejected. Employee will be notified.', 'warning');
            }
        }

        function requestClarification(id) {
            showAlert('Clarification request sent to employee.', 'info');
        }

        function downloadDocument(id) {
            showAlert('Downloading document...', 'info');
        }

        function browseFiles() {
            showAlert('File browser will be implemented soon!', 'info');
        }

        function downloadTemplate(type) {
            showAlert(`Downloading ${type} template...`, 'info');
        }

        function generateReport() {
            showAlert('Generating verification report...', 'info');
        }

        function showBulkUploadModal() {
            showAlert('Bulk upload modal will be implemented soon!', 'info');
        }

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

<?php if (!isset($root_path))  include '../layouts/footer.php'; ?>
</body>
</html>
