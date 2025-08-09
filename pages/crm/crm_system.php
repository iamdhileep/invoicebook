<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

include '../../db.php';
$page_title = 'CRM & Customer Communications';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'create_lead':
            $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
            $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $source = mysqli_real_escape_string($conn, $_POST['source']);
            $lead_value = floatval($_POST['lead_value'] ?? 0);
            $priority = mysqli_real_escape_string($conn, $_POST['priority']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes']);
            $assigned_to = intval($_POST['assigned_to']);
            
            $lead_id = 'LEAD-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO crm_leads (lead_id, company_name, contact_person, email, phone, 
                      source, lead_value, priority, notes, assigned_to, status, created_by, created_date) 
                      VALUES ('$lead_id', '$company_name', '$contact_person', '$email', '$phone', 
                      '$source', $lead_value, '$priority', '$notes', $assigned_to, 'new', '{$_SESSION['admin']}', NOW())";
            
            if (mysqli_query($conn, $query)) {
                $id = mysqli_insert_id($conn);
                
                // Log activity
                $activity_query = "INSERT INTO crm_activities (lead_id, customer_id, activity_type, description, 
                                  created_by, activity_date) VALUES ($id, NULL, 'lead_created', 
                                  'Lead \"$company_name\" created', '{$_SESSION['admin']}', NOW())";
                mysqli_query($conn, $activity_query);
                
                echo json_encode(['success' => true, 'message' => 'Lead created successfully!', 'lead_id' => $lead_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating lead: ' . $conn->error]);
            }
            exit;
            
        case 'update_lead_status':
            $lead_id = intval($_POST['lead_id']);
            $status = mysqli_real_escape_string($conn, $_POST['status']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $query = "UPDATE crm_leads SET status = '$status', last_updated = NOW(), updated_by = '{$_SESSION['admin']}'";
            
            if (!empty($notes)) {
                $query .= ", notes = CONCAT(IFNULL(notes, ''), '\n\n[" . date('Y-m-d H:i:s') . "] Status changed to $status: $notes')";
            }
            
            $query .= " WHERE id = $lead_id";
            
            if (mysqli_query($conn, $query)) {
                // Log activity
                $activity_query = "INSERT INTO crm_activities (lead_id, activity_type, description, 
                                  created_by, activity_date) VALUES ($lead_id, 'status_change', 
                                  'Lead status changed to $status', '{$_SESSION['admin']}', NOW())";
                mysqli_query($conn, $activity_query);
                
                echo json_encode(['success' => true, 'message' => 'Lead status updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating lead: ' . $conn->error]);
            }
            exit;
            
        case 'log_communication':
            $customer_id = intval($_POST['customer_id'] ?? 0) ?: null;
            $lead_id = intval($_POST['lead_id'] ?? 0) ?: null;
            $communication_type = mysqli_real_escape_string($conn, $_POST['communication_type']);
            $subject = mysqli_real_escape_string($conn, $_POST['subject']);
            $message = mysqli_real_escape_string($conn, $_POST['message']);
            $communication_date = mysqli_real_escape_string($conn, $_POST['communication_date']);
            $follow_up_date = mysqli_real_escape_string($conn, $_POST['follow_up_date'] ?? '');
            $outcome = mysqli_real_escape_string($conn, $_POST['outcome'] ?? '');
            
            $query = "INSERT INTO crm_communications (customer_id, lead_id, communication_type, subject, 
                      message, communication_date, follow_up_date, outcome, created_by, created_date) 
                      VALUES (" . ($customer_id ?: 'NULL') . ", " . ($lead_id ?: 'NULL') . ", 
                      '$communication_type', '$subject', '$message', '$communication_date', 
                      " . ($follow_up_date ? "'$follow_up_date'" : 'NULL') . ", '$outcome', 
                      '{$_SESSION['admin']}', NOW())";
            
            if (mysqli_query($conn, $query)) {
                $comm_id = mysqli_insert_id($conn);
                
                // Log as activity
                $entity_id = $customer_id ?: $lead_id;
                $entity_type = $customer_id ? 'communication_logged' : 'lead_communication';
                $description = "Communication logged: $subject ($communication_type)";
                
                $activity_query = "INSERT INTO crm_activities (customer_id, lead_id, activity_type, description, 
                                  created_by, activity_date) VALUES (" . ($customer_id ?: 'NULL') . ", 
                                  " . ($lead_id ?: 'NULL') . ", '$entity_type', '$description', 
                                  '{$_SESSION['admin']}', NOW())";
                mysqli_query($conn, $activity_query);
                
                echo json_encode(['success' => true, 'message' => 'Communication logged successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error logging communication: ' . $conn->error]);
            }
            exit;
            
        case 'create_opportunity':
            $customer_id = intval($_POST['customer_id'] ?? 0) ?: null;
            $lead_id = intval($_POST['lead_id'] ?? 0) ?: null;
            $opportunity_name = mysqli_real_escape_string($conn, $_POST['opportunity_name']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $value = floatval($_POST['value']);
            $stage = mysqli_real_escape_string($conn, $_POST['stage']);
            $probability = intval($_POST['probability']);
            $expected_close_date = mysqli_real_escape_string($conn, $_POST['expected_close_date']);
            $assigned_to = intval($_POST['assigned_to']);
            
            $opp_id = 'OPP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $query = "INSERT INTO crm_opportunities (opportunity_id, customer_id, lead_id, opportunity_name, 
                      description, value, stage, probability, expected_close_date, assigned_to, 
                      created_by, created_date) 
                      VALUES ('$opp_id', " . ($customer_id ?: 'NULL') . ", " . ($lead_id ?: 'NULL') . ", 
                      '$opportunity_name', '$description', $value, '$stage', $probability, 
                      '$expected_close_date', $assigned_to, '{$_SESSION['admin']}', NOW())";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Opportunity created successfully!', 'opportunity_id' => $opp_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating opportunity: ' . $conn->error]);
            }
            exit;
            
        case 'update_opportunity_stage':
            $opportunity_id = intval($_POST['opportunity_id']);
            $stage = mysqli_real_escape_string($conn, $_POST['stage']);
            $probability = intval($_POST['probability']);
            $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
            
            $query = "UPDATE crm_opportunities SET stage = '$stage', probability = $probability, 
                      last_updated = NOW(), updated_by = '{$_SESSION['admin']}'";
            
            if ($stage === 'won') {
                $query .= ", closed_date = NOW(), status = 'won'";
            } elseif ($stage === 'lost') {
                $query .= ", closed_date = NOW(), status = 'lost'";
            }
            
            $query .= " WHERE id = $opportunity_id";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Opportunity updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating opportunity: ' . $conn->error]);
            }
            exit;
            
        case 'schedule_follow_up':
            $customer_id = intval($_POST['customer_id'] ?? 0) ?: null;
            $lead_id = intval($_POST['lead_id'] ?? 0) ?: null;
            $task_title = mysqli_real_escape_string($conn, $_POST['task_title']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
            $priority = mysqli_real_escape_string($conn, $_POST['priority']);
            $assigned_to = intval($_POST['assigned_to']);
            
            $query = "INSERT INTO crm_tasks (customer_id, lead_id, task_title, description, due_date, 
                      priority, assigned_to, status, created_by, created_date) 
                      VALUES (" . ($customer_id ?: 'NULL') . ", " . ($lead_id ?: 'NULL') . ", 
                      '$task_title', '$description', '$due_date', '$priority', $assigned_to, 
                      'pending', '{$_SESSION['admin']}', NOW())";
            
            if (mysqli_query($conn, $query)) {
                echo json_encode(['success' => true, 'message' => 'Follow-up scheduled successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error scheduling follow-up: ' . $conn->error]);
            }
            exit;
            
        case 'convert_lead':
            $lead_id = intval($_POST['lead_id']);
            $create_customer = $_POST['create_customer'] === 'true';
            $create_opportunity = $_POST['create_opportunity'] === 'true';
            
            // Get lead details
            $lead_query = "SELECT * FROM crm_leads WHERE id = $lead_id";
            $lead_result = mysqli_query($conn, $lead_query);
            $lead = $lead_result->fetch_assoc();
            
            if (!$lead) {
                echo json_encode(['success' => false, 'message' => 'Lead not found']);
                exit;
            }
            
            $customer_id = null;
            
            if ($create_customer) {
                // Create customer from lead
                $customer_query = "INSERT INTO customers (customer_name, contact_person, email, phone, 
                                  address, customer_type, status, created_by, created_at) 
                                  VALUES ('{$lead['company_name']}', '{$lead['contact_person']}', 
                                  '{$lead['email']}', '{$lead['phone']}', '', 'business', 'active', 
                                  '{$_SESSION['admin']}', NOW())";
                
                if (mysqli_query($conn, $customer_query)) {
                    $customer_id = mysqli_insert_id($conn);
                }
            }
            
            if ($create_opportunity && $customer_id) {
                // Create opportunity
                $opp_id = 'OPP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $opp_query = "INSERT INTO crm_opportunities (opportunity_id, customer_id, lead_id, 
                             opportunity_name, value, stage, probability, assigned_to, created_by, created_date) 
                             VALUES ('$opp_id', $customer_id, $lead_id, '{$lead['company_name']} - Converted Lead', 
                             {$lead['lead_value']}, 'qualification', 25, {$lead['assigned_to']}, 
                             '{$_SESSION['admin']}', NOW())";
                mysqli_query($conn, $opp_query);
            }
            
            // Update lead status to converted
            $update_query = "UPDATE crm_leads SET status = 'converted', customer_id = " . ($customer_id ?: 'NULL') . ", 
                            last_updated = NOW(), updated_by = '{$_SESSION['admin']}' WHERE id = $lead_id";
            mysqli_query($conn, $update_query);
            
            echo json_encode(['success' => true, 'message' => 'Lead converted successfully!', 'customer_id' => $customer_id]);
            exit;
    }
}

// Create CRM tables
$tables = [
    "CREATE TABLE IF NOT EXISTS crm_leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id VARCHAR(50) UNIQUE NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        source ENUM('website', 'referral', 'cold_call', 'email', 'social_media', 'event', 'advertisement', 'other') DEFAULT 'other',
        lead_value DECIMAL(12,2) DEFAULT 0,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('new', 'contacted', 'qualified', 'proposal', 'negotiation', 'converted', 'lost') DEFAULT 'new',
        notes TEXT,
        assigned_to INT NOT NULL,
        customer_id INT NULL,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100),
        INDEX idx_status (status),
        INDEX idx_assigned (assigned_to),
        INDEX idx_source (source)
    )",
    
    "CREATE TABLE IF NOT EXISTS crm_opportunities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        opportunity_id VARCHAR(50) UNIQUE NOT NULL,
        customer_id INT NULL,
        lead_id INT NULL,
        opportunity_name VARCHAR(255) NOT NULL,
        description TEXT,
        value DECIMAL(12,2) NOT NULL,
        stage ENUM('prospecting', 'qualification', 'needs_analysis', 'proposal', 'negotiation', 'closed_won', 'closed_lost') DEFAULT 'prospecting',
        probability INT DEFAULT 0,
        expected_close_date DATE,
        actual_close_date DATE NULL,
        status ENUM('open', 'won', 'lost') DEFAULT 'open',
        assigned_to INT NOT NULL,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100),
        closed_date TIMESTAMP NULL,
        INDEX idx_stage (stage),
        INDEX idx_status (status),
        INDEX idx_assigned (assigned_to),
        INDEX idx_customer (customer_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS crm_communications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        lead_id INT NULL,
        communication_type ENUM('email', 'phone', 'meeting', 'video_call', 'chat', 'letter', 'sms', 'other') NOT NULL,
        subject VARCHAR(255),
        message TEXT,
        communication_date DATETIME NOT NULL,
        duration_minutes INT DEFAULT 0,
        outcome ENUM('positive', 'neutral', 'negative', 'no_response') DEFAULT 'neutral',
        follow_up_required BOOLEAN DEFAULT FALSE,
        follow_up_date DATE NULL,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer (customer_id),
        INDEX idx_lead (lead_id),
        INDEX idx_type (communication_type),
        INDEX idx_date (communication_date)
    )",
    
    "CREATE TABLE IF NOT EXISTS crm_tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        lead_id INT NULL,
        task_title VARCHAR(255) NOT NULL,
        description TEXT,
        due_date DATE NOT NULL,
        completed_date DATE NULL,
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        assigned_to INT NOT NULL,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_due_date (due_date),
        INDEX idx_status (status),
        INDEX idx_assigned (assigned_to)
    )",
    
    "CREATE TABLE IF NOT EXISTS crm_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NULL,
        lead_id INT NULL,
        activity_type ENUM('lead_created', 'status_change', 'communication_logged', 'follow_up_scheduled', 
                          'opportunity_created', 'task_completed', 'meeting_scheduled', 'proposal_sent', 
                          'contract_signed', 'lead_communication', 'other') NOT NULL,
        description TEXT NOT NULL,
        activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(100),
        INDEX idx_customer (customer_id),
        INDEX idx_lead (lead_id),
        INDEX idx_type (activity_type),
        INDEX idx_date (activity_date)
    )",
    
    "CREATE TABLE IF NOT EXISTS crm_sales_pipeline (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_name VARCHAR(255) NOT NULL,
        stages JSON NOT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        created_by VARCHAR(100),
        created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
];

foreach ($tables as $table) {
    mysqli_query($conn, $table);
}

// Insert default sales pipeline if not exists
$pipeline_check = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_sales_pipeline");
if ($pipeline_check->fetch_assoc()['count'] == 0) {
    $default_stages = json_encode([
        ['name' => 'Prospecting', 'probability' => 10],
        ['name' => 'Qualification', 'probability' => 25],
        ['name' => 'Needs Analysis', 'probability' => 50],
        ['name' => 'Proposal', 'probability' => 75],
        ['name' => 'Negotiation', 'probability' => 90],
        ['name' => 'Closed Won', 'probability' => 100],
        ['name' => 'Closed Lost', 'probability' => 0]
    ]);
    
    mysqli_query($conn, "INSERT INTO crm_sales_pipeline (pipeline_name, stages, is_default, created_by) 
                        VALUES ('Default Sales Pipeline', '$default_stages', TRUE, '{$_SESSION['admin']}')");
}

// Get CRM statistics
$stats = [
    'total_leads' => 0,
    'qualified_leads' => 0,
    'opportunities' => 0,
    'won_opportunities' => 0,
    'communications_today' => 0,
    'overdue_tasks' => 0,
    'pipeline_value' => 0,
    'conversion_rate' => 0
];

$lead_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN status IN ('qualified', 'proposal', 'negotiation') THEN 1 ELSE 0 END) as qualified_leads,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads
    FROM crm_leads
");

if ($lead_stats && $row = $lead_stats->fetch_assoc()) {
    $stats['total_leads'] = $row['total_leads'];
    $stats['qualified_leads'] = $row['qualified_leads'];
    $stats['conversion_rate'] = $row['total_leads'] > 0 ? 
        round(($row['converted_leads'] / $row['total_leads']) * 100, 1) : 0;
}

$opp_stats = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_opportunities,
        SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_opportunities,
        SUM(CASE WHEN status = 'open' THEN value ELSE 0 END) as pipeline_value,
        SUM(CASE WHEN status = 'won' THEN value ELSE 0 END) as won_value
    FROM crm_opportunities
");

if ($opp_stats && $row = $opp_stats->fetch_assoc()) {
    $stats['opportunities'] = $row['total_opportunities'];
    $stats['won_opportunities'] = $row['won_opportunities'];
    $stats['pipeline_value'] = $row['pipeline_value'];
}

$comm_stats = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_communications WHERE DATE(communication_date) = CURDATE()");
if ($comm_stats && $row = $comm_stats->fetch_assoc()) {
    $stats['communications_today'] = $row['count'];
}

$task_stats = mysqli_query($conn, "SELECT COUNT(*) as count FROM crm_tasks WHERE due_date < CURDATE() AND status NOT IN ('completed', 'cancelled')");
if ($task_stats && $row = $task_stats->fetch_assoc()) {
    $stats['overdue_tasks'] = $row['count'];
}

// Get recent leads
$recent_leads = mysqli_query($conn, "
    SELECT cl.*, e.name as assigned_name
    FROM crm_leads cl
    LEFT JOIN employees e ON cl.assigned_to = e.employee_id
    ORDER BY cl.created_date DESC
    LIMIT 20
");

// Get opportunities
$opportunities = mysqli_query($conn, "
    SELECT co.*, c.customer_name, e.name as assigned_name
    FROM crm_opportunities co
    LEFT JOIN customers c ON co.customer_id = c.id
    LEFT JOIN employees e ON co.assigned_to = e.employee_id
    WHERE co.status = 'open'
    ORDER BY co.expected_close_date ASC
    LIMIT 20
");

// Get employees for dropdowns
$employees = mysqli_query($conn, "SELECT employee_id, name FROM employees WHERE status = 'active' ORDER BY name");

// Get customers for dropdowns
$customers = mysqli_query($conn, "SELECT id, customer_name FROM customers ORDER BY customer_name");

include '../../layouts/header.php';
include '../../layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">🤝 CRM & Customer Communications</h1>
                <p class="text-muted">Comprehensive customer relationship and sales management system</p>
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createLeadModal">
                    <i class="bi bi-person-plus me-1"></i>New Lead
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#logCommunicationModal">
                    <i class="bi bi-chat-dots me-1"></i>Log Communication
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots me-1"></i>More
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showPipelineView()"><i class="bi bi-diagram-3 me-2"></i>Pipeline View</a></li>
                        <li><a class="dropdown-item" href="#" onclick="showReports()"><i class="bi bi-graph-up me-2"></i>Reports</a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportCRMData()"><i class="bi bi-download me-2"></i>Export Data</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- CRM Statistics Dashboard -->
        <div class="row g-3 mb-4">
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-person-plus fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['total_leads'] ?></h4>
                        <small class="opacity-75">Total Leads</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['qualified_leads'] ?></h4>
                        <small class="opacity-75">Qualified</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-bullseye fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['opportunities'] ?></h4>
                        <small class="opacity-75">Opportunities</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-currency-rupee fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold">₹<?= number_format($stats['pipeline_value']/100000, 1) ?>L</h4>
                        <small class="opacity-75">Pipeline Value</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-chat-dots fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['communications_today'] ?></h4>
                        <small class="opacity-75">Today's Comms</small>
                    </div>
                </div>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg, #ff6b6b 0%, #ffa500 100%);">
                    <div class="card-body text-white text-center">
                        <div class="mb-2">
                            <i class="bi bi-percent fs-2"></i>
                        </div>
                        <h4 class="mb-1 fw-bold"><?= $stats['conversion_rate'] ?>%</h4>
                        <small class="opacity-75">Conversion Rate</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="crmTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#leads" type="button">
                                    <i class="bi bi-person-plus me-2"></i>Leads
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#opportunities" type="button">
                                    <i class="bi bi-bullseye me-2"></i>Opportunities
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#communications" type="button">
                                    <i class="bi bi-chat-dots me-2"></i>Communications
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tasks" type="button">
                                    <i class="bi bi-list-check me-2"></i>Tasks
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#pipeline" type="button">
                                    <i class="bi bi-diagram-3 me-2"></i>Pipeline
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Leads Tab -->
                            <div class="tab-pane fade show active" id="leads" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="leadsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Lead ID</th>
                                                <th>Company</th>
                                                <th>Contact Person</th>
                                                <th>Source</th>
                                                <th>Value</th>
                                                <th>Status</th>
                                                <th>Priority</th>
                                                <th>Assigned To</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($lead = $recent_leads->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-bold"><?= htmlspecialchars($lead['lead_id']) ?></span>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($lead['company_name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($lead['email']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <div><?= htmlspecialchars($lead['contact_person']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($lead['phone']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= ucfirst($lead['source']) ?></span>
                                                </td>
                                                <td>₹<?= number_format($lead['lead_value'], 0) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $lead['status'] === 'converted' ? 'success' : 
                                                        ($lead['status'] === 'qualified' ? 'primary' : 
                                                        ($lead['status'] === 'lost' ? 'danger' : 'secondary'))
                                                    ?>">
                                                        <?= ucfirst($lead['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $lead['priority'] === 'urgent' ? 'danger' : 
                                                        ($lead['priority'] === 'high' ? 'warning' : 
                                                        ($lead['priority'] === 'medium' ? 'info' : 'secondary'))
                                                    ?>">
                                                        <?= ucfirst($lead['priority']) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($lead['assigned_name'] ?? 'Unassigned') ?></td>
                                                <td>
                                                    <small><?= date('M j, Y', strtotime($lead['created_date'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-lead" 
                                                                data-id="<?= $lead['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning update-lead-status" 
                                                                data-id="<?= $lead['id'] ?>">
                                                            <i class="bi bi-arrow-up-circle"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-success convert-lead" 
                                                                data-id="<?= $lead['id'] ?>"
                                                                data-company="<?= htmlspecialchars($lead['company_name']) ?>">
                                                            <i class="bi bi-arrow-right-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Opportunities Tab -->
                            <div class="tab-pane fade" id="opportunities" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="opportunitiesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Opportunity</th>
                                                <th>Customer</th>
                                                <th>Value</th>
                                                <th>Stage</th>
                                                <th>Probability</th>
                                                <th>Close Date</th>
                                                <th>Assigned To</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($opp = $opportunities->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($opp['opportunity_name']) ?></div>
                                                        <small class="text-muted"><?= $opp['opportunity_id'] ?></small>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($opp['customer_name'] ?? 'Prospect') ?></td>
                                                <td>₹<?= number_format($opp['value'], 0) ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?= str_replace('_', ' ', ucwords($opp['stage'])) ?></span>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                            <div class="progress-bar" style="width: <?= $opp['probability'] ?>%"></div>
                                                        </div>
                                                        <small><?= $opp['probability'] ?>%</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($opp['expected_close_date']): ?>
                                                        <span class="<?= strtotime($opp['expected_close_date']) < time() ? 'text-danger' : '' ?>">
                                                            <?= date('M j, Y', strtotime($opp['expected_close_date'])) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($opp['assigned_name'] ?? 'Unassigned') ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button class="btn btn-sm btn-outline-primary view-opportunity" 
                                                                data-id="<?= $opp['id'] ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning update-opportunity" 
                                                                data-id="<?= $opp['id'] ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Communications Tab -->
                            <div class="tab-pane fade" id="communications" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-chat-dots fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">Communication History</h5>
                                    <p class="text-muted">Track all customer and lead communications</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#logCommunicationModal">
                                        <i class="bi bi-plus-circle me-1"></i>Log Communication
                                    </button>
                                </div>
                            </div>

                            <!-- Tasks Tab -->
                            <div class="tab-pane fade" id="tasks" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-list-check fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">CRM Tasks & Follow-ups</h5>
                                    <p class="text-muted">Manage follow-up tasks and customer interactions</p>
                                    <button class="btn btn-primary" onclick="scheduleFollowUp()">
                                        <i class="bi bi-plus-circle me-1"></i>Schedule Follow-up
                                    </button>
                                </div>
                            </div>

                            <!-- Pipeline Tab -->
                            <div class="tab-pane fade" id="pipeline" role="tabpanel">
                                <div class="text-center py-5">
                                    <i class="bi bi-diagram-3 fs-1 text-muted"></i>
                                    <h5 class="text-muted mt-3">Sales Pipeline Visualization</h5>
                                    <p class="text-muted">Interactive pipeline view with drag-and-drop functionality</p>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Visual pipeline board will show opportunities by stage with value tracking
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

<!-- Create Lead Modal -->
<div class="modal fade" id="createLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createLeadForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Source</label>
                            <select name="source" class="form-select">
                                <option value="website">Website</option>
                                <option value="referral">Referral</option>
                                <option value="cold_call">Cold Call</option>
                                <option value="email">Email Campaign</option>
                                <option value="social_media">Social Media</option>
                                <option value="event">Event</option>
                                <option value="advertisement">Advertisement</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lead Value</label>
                            <input type="number" name="lead_value" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Assigned To</label>
                            <select name="assigned_to" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php 
                                $employees->data_seek(0);
                                while ($emp = $employees->fetch_assoc()): 
                                ?>
                                <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Log Communication Modal -->
<div class="modal fade" id="logCommunicationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Communication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="logCommunicationForm">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Entity Type</label>
                            <select name="entity_type" class="form-select" id="entityTypeSelect" onchange="updateEntityOptions()">
                                <option value="customer">Customer</option>
                                <option value="lead">Lead</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Select Entity</label>
                            <select name="entity_id" class="form-select" id="entitySelect">
                                <option value="">Select...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Communication Type</label>
                            <select name="communication_type" class="form-select">
                                <option value="email">Email</option>
                                <option value="phone">Phone Call</option>
                                <option value="meeting">Meeting</option>
                                <option value="video_call">Video Call</option>
                                <option value="chat">Chat</option>
                                <option value="sms">SMS</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Communication Date</label>
                            <input type="datetime-local" name="communication_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" placeholder="Brief subject or topic">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message/Notes</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Detailed communication notes"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Outcome</label>
                            <select name="outcome" class="form-select">
                                <option value="positive">Positive</option>
                                <option value="neutral" selected>Neutral</option>
                                <option value="negative">Negative</option>
                                <option value="no_response">No Response</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Communication</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#leadsTable, #opportunitiesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[8, "desc"]]
    });

    // Create Lead Form
    $('#createLeadForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'create_lead');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(`${response.message} Lead ID: ${response.lead_id}`, 'success');
                    $('#createLeadModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Log Communication Form
    $('#logCommunicationForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'log_communication');
        
        const entityType = $('#entityTypeSelect').val();
        const entityId = $('#entitySelect').val();
        
        if (entityType === 'customer') {
            formData.append('customer_id', entityId);
        } else {
            formData.append('lead_id', entityId);
        }
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    $('#logCommunicationModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    });

    // Initialize entity options
    updateEntityOptions();
});

// Update entity options based on type
function updateEntityOptions() {
    const entityType = $('#entityTypeSelect').val();
    const entitySelect = $('#entitySelect');
    
    entitySelect.empty().append('<option value="">Loading...</option>');
    
    if (entityType === 'customer') {
        entitySelect.empty().append('<option value="">Select Customer</option>');
        <?php 
        $customers->data_seek(0);
        while ($customer = $customers->fetch_assoc()): 
        ?>
        entitySelect.append('<option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['customer_name']) ?></option>');
        <?php endwhile; ?>
    } else {
        entitySelect.empty().append('<option value="">Select Lead</option>');
        <?php 
        $recent_leads->data_seek(0);
        while ($lead = $recent_leads->fetch_assoc()): 
        ?>
        entitySelect.append('<option value="<?= $lead['id'] ?>"><?= htmlspecialchars($lead['company_name']) ?></option>');
        <?php endwhile; ?>
    }
}

// View Lead
$(document).on('click', '.view-lead', function() {
    const id = $(this).data('id');
    showAlert('Detailed lead view will be implemented next!', 'info');
});

// Update Lead Status
$(document).on('click', '.update-lead-status', function() {
    const id = $(this).data('id');
    showAlert('Lead status update modal will be implemented next!', 'info');
});

// Convert Lead
$(document).on('click', '.convert-lead', function() {
    const id = $(this).data('id');
    const company = $(this).data('company');
    
    if (confirm(`Convert lead "${company}" to customer and create opportunity?`)) {
        const formData = new FormData();
        formData.append('action', 'convert_lead');
        formData.append('lead_id', id);
        formData.append('create_customer', 'true');
        formData.append('create_opportunity', 'true');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(response.message, 'error');
                }
            }
        });
    }
});

// Additional functions
function showPipelineView() {
    showAlert('Visual pipeline board coming soon with drag-and-drop functionality!', 'info');
}

function showReports() {
    showAlert('CRM reports and analytics coming soon!', 'info');
}

function exportCRMData() {
    showAlert('CRM data export functionality will be available soon!', 'info');
}

function scheduleFollowUp() {
    showAlert('Follow-up scheduling modal will be implemented next!', 'info');
}

function showAlert(message, type) {
    const alertTypes = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    };
    
    const alertHtml = `
        <div class="alert ${alertTypes[type]} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    setTimeout(() => $('.alert').alert('close'), 5000);
}
</script>

<?php include '../../layouts/footer.php'; ?>
