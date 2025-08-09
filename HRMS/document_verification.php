<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Use absolute paths to avoid any path issues
$base_dir = dirname(__DIR__);
require_once $base_dir . '/db.php';
require_once $base_dir . '/auth_check.php';

// Set compatibility variables for HRMS modules
if (isset($_SESSION['user']) && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? 1;
}

$current_user_id = $_SESSION['user_id'] ?? 1;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_verification':
            $employee_id = intval($_POST['employee_id']);
            $employee_name = mysqli_real_escape_string($conn, $_POST['employee_name']);
            $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
            $document_name = mysqli_real_escape_string($conn, $_POST['document_name']);
            $submitted_date = mysqli_real_escape_string($conn, $_POST['submitted_date']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $urgency = mysqli_real_escape_string($conn, $_POST['urgency']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            
            $query = "INSERT INTO document_verifications 
                     (employee_id, employee_name, document_type, document_name, submitted_date, status, urgency, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssssss", $employee_id, $employee_name, $document_type, $document_name, $submitted_date, $status, $urgency, $notes);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Document verification request added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add verification request: ' . $conn->error]);
            }
            exit;
            
        case 'update_verification':
            $id = intval($_POST['id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $verified_by_name = mysqli_real_escape_string($conn, $_POST['verified_by_name']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $verification_date = ($status === 'verified') ? date('Y-m-d') : null;
            
            $query = "UPDATE document_verifications 
                     SET status = ?, verified_by_name = ?, notes = ?, verification_date = ?
                     WHERE id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssi", $status, $verified_by_name, $notes, $verification_date, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Verification updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update verification: ' . $conn->error]);
            }
            exit;
            
        case 'delete_verification':
            $id = intval($_POST['id']);
            
            $query = "DELETE FROM document_verifications WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Verification request deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete verification request: ' . $conn->error]);
            }
            exit;
            
        case 'get_verification':
            $id = intval($_POST['id']);
            
            $query = "SELECT * FROM document_verifications WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Verification not found']);
            }
            exit;
            
        case 'bulk_approve':
            $ids = $_POST['ids'] ?? [];
            $verified_by = mysqli_real_escape_string($conn, $_POST['verified_by_name']);
            $verification_date = date('Y-m-d');
            
            if (!empty($ids)) {
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                $query = "UPDATE document_verifications 
                         SET status = 'verified', verified_by_name = ?, verification_date = ? 
                         WHERE id IN ($placeholders)";
                
                $stmt = $conn->prepare($query);
                $types = str_repeat('i', count($ids));
                $stmt->bind_param("ss" . $types, $verified_by, $verification_date, ...$ids);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Documents approved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to approve documents: ' . $conn->error]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No documents selected']);
            }
            exit;
            
        case 'upload_document':
            if (isset($_FILES['document'])) {
                $employee_id = intval($_POST['employee_id']);
                $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
                $document_title = mysqli_real_escape_string($conn, $_POST['document_title']);
                
                $upload_dir = $base_dir . '/uploads/documents/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = $_FILES['document']['name'];
                $file_tmp = $_FILES['document']['tmp_name'];
                $file_size = $_FILES['document']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                
                if (in_array($file_ext, $allowed_types)) {
                    $new_file_name = 'doc_' . time() . '_' . $employee_id . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $query = "INSERT INTO employee_documents 
                                 (employee_id, category_id, document_title, document_type, file_name, file_path, file_size, uploaded_by) 
                                 VALUES (?, 1, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("issssii", $employee_id, $document_title, $document_type, $new_file_name, $file_path, $file_size, $current_user_id);
                        
                        if ($stmt->execute()) {
                            echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to save document info: ' . $conn->error]);
                        }
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            }
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

// Fetch employees for dropdown
$employees_query = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM hr_employees ORDER BY first_name, last_name";
$employees_result = $conn->query($employees_query);

// Fetch document verifications with filters
$filter_status = $_GET['status'] ?? '';
$filter_urgency = $_GET['urgency'] ?? '';
$search_query = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($filter_status && $filter_status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_urgency && $filter_urgency !== 'all') {
    $where_conditions[] = "urgency = ?";
    $params[] = $filter_urgency;
    $types .= 's';
}

if ($search_query) {
    $where_conditions[] = "(employee_name LIKE ? OR document_type LIKE ? OR document_name LIKE ?)";
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
$verifications_query = "SELECT * FROM document_verifications $where_clause ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($verifications_query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $verifications_result = $stmt->get_result();
} else {
    $verifications_result = $conn->query($verifications_query);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN urgency = 'high' AND status = 'pending' THEN 1 ELSE 0 END) as urgent
    FROM document_verifications";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification - HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .status-verified { background-color: #d1edff; color: #0c5460; border: 1px solid #b6effb; }
        .status-rejected { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-under-review { background-color: #e2e3e5; color: #41464b; border: 1px solid #d6d8db; }
        
        .urgency-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .urgency-low { background-color: #d4edda; color: #155724; }
        .urgency-medium { background-color: #fff3cd; color: #856404; }
        .urgency-high { background-color: #f8d7da; color: #721c24; }
        
        .stats-card {
            transition: transform 0.2s;
            border: none !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            background: white;
            position: relative;
            z-index: 5;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        }
        
        .verification-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-top: 2px solid #007bff;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 5;
        }
        
        .card {
            position: relative;
            z-index: 5;
        }
        
        .document-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        
        .main-content-wrapper {
            margin-left: 280px; /* Match sidebar width */
            padding: 20px;
            background-color: #f4f6f9;
            min-height: 100vh;
            position: relative;
            z-index: 1;
            padding-top: 100px; /* Add top padding to avoid header overlap */
        }
        
        .header-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            position: relative;
            z-index: 100;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .header-text h1 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .header-text p {
            color: #718096;
            font-size: 15px;
            margin: 0;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }
        
        .button-group .btn {
            white-space: nowrap;
            min-width: auto;
            font-size: 14px;
            padding: 10px 16px;
            font-weight: 500;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }
        
        .button-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .button-group .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
                padding: 15px;
                padding-top: 120px; /* More padding on mobile for header */
            }
            
            .header-section {
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                gap: 15px;
                align-items: stretch !important;
            }
            
            .header-text {
                text-align: center;
                margin-bottom: 15px;
            }
            
            .button-group {
                justify-content: center;
                width: 100%;
            }
            
            .button-group .btn {
                flex: 1;
                min-width: 100px;
                max-width: 140px;
            }
        }
        
        @media (max-width: 576px) {
            .button-group {
                flex-direction: column;
                width: 100%;
            }
            
            .button-group .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <?php include $base_dir . '/layouts/header.php'; ?>
    <?php include $base_dir . '/layouts/sidebar.php'; ?>
    
    <div class="main-content-wrapper">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="header-section">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="header-text">
                        <h1 class="h3 mb-1">📄 Document Verification System</h1>
                        <p class="text-muted mb-0">Manage and verify employee documents efficiently</p>
                    </div>
                    <div class="button-group">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVerificationModal">
                            <i class="fas fa-plus"></i> <span class="d-none d-sm-inline">New Request</span>
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                            <i class="fas fa-upload"></i> <span class="d-none d-sm-inline">Upload</span>
                        </button>
                        <button class="btn btn-secondary" id="bulkApproveBtn" disabled>
                            <i class="fas fa-check-double"></i> <span class="d-none d-sm-inline">Bulk Approve</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-primary mb-2">
                                <i class="fas fa-file-alt fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-primary"><?= $stats['total'] ?></h3>
                            <p class="text-muted mb-0">Total Documents</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-warning mb-2">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-warning"><?= $stats['pending'] ?></h3>
                            <p class="text-muted mb-0">Pending Review</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-success mb-2">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-success"><?= $stats['verified'] ?></h3>
                            <p class="text-muted mb-0">Verified</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-danger mb-2">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <h3 class="mb-1 text-danger"><?= $stats['urgent'] ?></h3>
                            <p class="text-muted mb-0">Urgent</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="verified" <?= $filter_status === 'verified' ? 'selected' : '' ?>>Verified</option>
                                <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                <option value="under-review" <?= $filter_status === 'under-review' ? 'selected' : '' ?>>Under Review</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="urgency" class="form-label">Urgency</label>
                            <select name="urgency" id="urgency" class="form-select">
                                <option value="">All Urgency Levels</option>
                                <option value="low" <?= $filter_urgency === 'low' ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= $filter_urgency === 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="high" <?= $filter_urgency === 'high' ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   placeholder="Search by employee, document type..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="document_verification.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Documents Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Document Verification Requests</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover verification-table">
                            <thead>
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Employee</th>
                                    <th>Document</th>
                                    <th>Type</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Urgency</th>
                                    <th>Verified By</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($verifications_result && $verifications_result->num_rows > 0): ?>
                                    <?php while ($row = $verifications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input document-checkbox" value="<?= $row['id'] ?>">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 32px; height: 32px; font-size: 14px; color: white;">
                                                        <?= strtoupper(substr($row['employee_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <strong><?= htmlspecialchars($row['employee_name']) ?></strong>
                                                        <br><small class="text-muted">ID: <?= $row['employee_id'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="fas fa-file document-icon text-primary"></i>
                                                <strong><?= htmlspecialchars($row['document_name'] ?: 'N/A') ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= htmlspecialchars($row['document_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted">
                                                    <?= date('M j, Y', strtotime($row['submitted_date'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $row['status'] ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="urgency-badge urgency-<?= $row['urgency'] ?>">
                                                    <?= $row['urgency'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= $row['verified_by_name'] ? htmlspecialchars($row['verified_by_name']) : '-' ?>
                                                <?php if ($row['verification_date']): ?>
                                                    <br><small class="text-muted"><?= date('M j, Y', strtotime($row['verification_date'])) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewVerification(<?= $row['id'] ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#viewVerificationModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success" 
                                                            onclick="editVerification(<?= $row['id'] ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editVerificationModal">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteVerification(<?= $row['id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <h5>No Document Verification Requests</h5>
                                                <p>Start by adding a new verification request.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Verification Modal -->
    <div class="modal fade" id="addVerificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus text-primary"></i>
                        Add Verification Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addVerificationForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addEmployeeId" class="form-label">Employee *</label>
                                    <select id="addEmployeeId" name="employee_id" class="form-select" required>
                                        <option value="">Select Employee</option>
                                        <?php while ($emp = $employees_result->fetch_assoc()): ?>
                                            <option value="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['full_name']) ?>">
                                                <?= htmlspecialchars($emp['full_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addDocumentType" class="form-label">Document Type *</label>
                                    <select id="addDocumentType" name="document_type" class="form-select" required>
                                        <option value="">Select Document Type</option>
                                        <option value="Identity Proof">Identity Proof</option>
                                        <option value="Address Proof">Address Proof</option>
                                        <option value="Educational Certificate">Educational Certificate</option>
                                        <option value="Experience Letter">Experience Letter</option>
                                        <option value="Medical Certificate">Medical Certificate</option>
                                        <option value="Bank Details">Bank Details</option>
                                        <option value="PAN Card">PAN Card</option>
                                        <option value="Aadhar Card">Aadhar Card</option>
                                        <option value="Passport">Passport</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addDocumentName" class="form-label">Document Name</label>
                                    <input type="text" id="addDocumentName" name="document_name" class="form-control" 
                                           placeholder="e.g., John's Aadhar Card">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addSubmittedDate" class="form-label">Submitted Date *</label>
                                    <input type="date" id="addSubmittedDate" name="submitted_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addStatus" class="form-label">Status *</label>
                                    <select id="addStatus" name="status" class="form-select" required>
                                        <option value="pending">Pending</option>
                                        <option value="under-review">Under Review</option>
                                        <option value="verified">Verified</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="addUrgency" class="form-label">Urgency *</label>
                                    <select id="addUrgency" name="urgency" class="form-select" required>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="addNotes" class="form-label">Notes</label>
                            <textarea id="addNotes" name="notes" class="form-control" rows="3" 
                                      placeholder="Any additional notes or requirements..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Verification Modal -->
    <div class="modal fade" id="editVerificationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-success"></i>
                        Update Verification Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editVerificationForm">
                    <input type="hidden" id="editVerificationId" name="id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status *</label>
                            <select id="editStatus" name="status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="under-review">Under Review</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editVerifiedBy" class="form-label">Verified/Reviewed By</label>
                            <input type="text" id="editVerifiedBy" name="verified_by_name" class="form-control" 
                                   placeholder="Enter your name">
                        </div>
                        <div class="mb-3">
                            <label for="editNotes" class="form-label">Review Notes</label>
                            <textarea id="editNotes" name="notes" class="form-control" rows="4" 
                                      placeholder="Add any notes about the verification..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Verification Modal -->
    <div class="modal fade" id="viewVerificationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye text-info"></i>
                        Verification Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="verificationDetailsContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload text-success"></i>
                        Upload Document
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="uploadDocumentForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="uploadEmployeeId" class="form-label">Employee *</label>
                            <select id="uploadEmployeeId" name="employee_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php 
                                // Reset the result pointer
                                $employees_result->data_seek(0);
                                while ($emp = $employees_result->fetch_assoc()): 
                                ?>
                                    <option value="<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="uploadDocumentType" class="form-label">Document Type *</label>
                            <select id="uploadDocumentType" name="document_type" class="form-select" required>
                                <option value="">Select Document Type</option>
                                <option value="Identity Proof">Identity Proof</option>
                                <option value="Address Proof">Address Proof</option>
                                <option value="Educational Certificate">Educational Certificate</option>
                                <option value="Experience Letter">Experience Letter</option>
                                <option value="Medical Certificate">Medical Certificate</option>
                                <option value="Bank Details">Bank Details</option>
                                <option value="PAN Card">PAN Card</option>
                                <option value="Aadhar Card">Aadhar Card</option>
                                <option value="Passport">Passport</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="uploadDocumentTitle" class="form-label">Document Title *</label>
                            <input type="text" id="uploadDocumentTitle" name="document_title" class="form-control" 
                                   placeholder="e.g., PAN Card - John Doe" required>
                        </div>
                        <div class="mb-3">
                            <label for="uploadDocument" class="form-label">Select File *</label>
                            <input type="file" id="uploadDocument" name="document" class="form-control" 
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                            <div class="form-text">
                                Supported formats: PDF, DOC, DOCX, JPG, PNG (Max: 10MB)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS and other dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        // Employee name auto-fill when selecting employee
        $('#addEmployeeId').change(function() {
            const selectedOption = $(this).find('option:selected');
            const employeeName = selectedOption.data('name');
            $('input[name="employee_name"]').val(employeeName);
        });

        // Add verification form submission
        $('#addVerificationForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add_verification');
            
            // Get form data
            const employeeId = $('#addEmployeeId').val();
            const employeeName = $('#addEmployeeId option:selected').data('name') || $('#addEmployeeId option:selected').text();
            
            formData.append('employee_id', employeeId);
            formData.append('employee_name', employeeName);
            formData.append('document_type', $('#addDocumentType').val());
            formData.append('document_name', $('#addDocumentName').val());
            formData.append('submitted_date', $('#addSubmittedDate').val());
            formData.append('status', $('#addStatus').val());
            formData.append('urgency', $('#addUrgency').val());
            formData.append('notes', $('#addNotes').val());
            
            $.ajax({
                url: 'document_verification.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Verification request added successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error submitting form');
                }
            });
        });

        // Edit verification function
        function editVerification(id) {
            $.post('document_verification.php', {
                action: 'get_verification',
                id: id
            }, function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        $('#editVerificationId').val(data.id);
                        $('#editStatus').val(data.status);
                        $('#editVerifiedBy').val(data.verified_by_name || '');
                        $('#editNotes').val(data.notes || '');
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) {
                    alert('Error loading verification data');
                    console.error('Parse error:', e, response);
                }
            });
        }

        // Edit verification form submission
        $('#editVerificationForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_verification');
            formData.append('id', $('#editVerificationId').val());
            formData.append('status', $('#editStatus').val());
            formData.append('verified_by_name', $('#editVerifiedBy').val());
            formData.append('notes', $('#editNotes').val());
            
            $.ajax({
                url: 'document_verification.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Verification updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error updating verification');
                }
            });
        });

        // View verification function
        function viewVerification(id) {
            $.post('document_verification.php', {
                action: 'get_verification',
                id: id
            }, function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.success) {
                        const data = result.data;
                        const html = `
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Employee:</strong></td>
                                            <td>${data.employee_name} (ID: ${data.employee_id})</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Document Type:</strong></td>
                                            <td>${data.document_type}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Document Name:</strong></td>
                                            <td>${data.document_name || 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Submitted Date:</strong></td>
                                            <td>${new Date(data.submitted_date).toLocaleDateString()}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td><span class="status-badge status-${data.status}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Urgency:</strong></td>
                                            <td><span class="urgency-badge urgency-${data.urgency}">${data.urgency.toUpperCase()}</span></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Verified By:</strong></td>
                                            <td>${data.verified_by_name || 'Not verified yet'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Verification Date:</strong></td>
                                            <td>${data.verification_date ? new Date(data.verification_date).toLocaleDateString() : 'N/A'}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            ${data.notes ? `
                                <div class="mt-3">
                                    <h6><strong>Notes:</strong></h6>
                                    <div class="border rounded p-3 bg-light">
                                        ${data.notes.replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                            ` : ''}
                        `;
                        $('#verificationDetailsContent').html(html);
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) {
                    alert('Error loading verification details');
                    console.error('Parse error:', e, response);
                }
            });
        }

        // Delete verification function
        function deleteVerification(id) {
            if (confirm('Are you sure you want to delete this verification request?')) {
                $.post('document_verification.php', {
                    action: 'delete_verification',
                    id: id
                }, function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Verification request deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing request');
                        console.error('Parse error:', e, response);
                    }
                });
            }
        }

        // Upload document form submission
        $('#uploadDocumentForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'upload_document');
            formData.append('employee_id', $('#uploadEmployeeId').val());
            formData.append('document_type', $('#uploadDocumentType').val());
            formData.append('document_title', $('#uploadDocumentTitle').val());
            formData.append('document', $('#uploadDocument')[0].files[0]);
            
            $.ajax({
                url: 'document_verification.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert('Document uploaded successfully!');
                            $('#uploadDocumentModal').modal('hide');
                            $('#uploadDocumentForm')[0].reset();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing upload');
                        console.error('Parse error:', e, response);
                    }
                },
                error: function() {
                    alert('Error uploading document');
                }
            });
        });

        // Select All functionality
        $('#selectAll').change(function() {
            $('.document-checkbox').prop('checked', this.checked);
            updateBulkApproveButton();
        });

        $('.document-checkbox').change(function() {
            updateBulkApproveButton();
        });

        function updateBulkApproveButton() {
            const selectedCount = $('.document-checkbox:checked').length;
            $('#bulkApproveBtn').prop('disabled', selectedCount === 0);
        }

        // Bulk approve functionality
        $('#bulkApproveBtn').click(function() {
            const selectedIds = $('.document-checkbox:checked').map(function() {
                return this.value;
            }).get();
            
            if (selectedIds.length === 0) {
                alert('Please select at least one document to approve.');
                return;
            }
            
            const verifierName = prompt('Enter your name for verification:');
            if (!verifierName) {
                return;
            }
            
            if (confirm(`Are you sure you want to approve ${selectedIds.length} document(s)?`)) {
                $.post('document_verification.php', {
                    action: 'bulk_approve',
                    ids: selectedIds,
                    verified_by_name: verifierName
                }, function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.success) {
                            alert(result.message);
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    } catch (e) {
                        alert('Error processing bulk approval');
                        console.error('Parse error:', e, response);
                    }
                });
            }
        });

        // Auto-refresh page every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>

    <?php include $base_dir . '/layouts/footer.php'; ?>
</body>
</html>
