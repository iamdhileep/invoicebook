<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

include '../../db.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create_requisition':
        createRequisition($conn);
        break;
        
    case 'get_recent_requisitions':
        getRecentRequisitions($conn);
        break;
        
    case 'get_all_requisitions':
        getAllRequisitions($conn);
        break;
        
    case 'approve_requisition':
        approveRequisition($conn);
        break;
        
    case 'create_rfq':
        createRFQ($conn);
        break;
        
    case 'get_rfqs':
        getRFQs($conn);
        break;
        
    case 'submit_rfq_response':
        submitRFQResponse($conn);
        break;
        
    case 'evaluate_vendor':
        evaluateVendor($conn);
        break;
        
    case 'get_vendor_evaluations':
        getVendorEvaluations($conn);
        break;
        
    case 'create_contract':
        createContract($conn);
        break;
        
    case 'get_contracts':
        getContracts($conn);
        break;
        
    case 'update_contract_status':
        updateContractStatus($conn);
        break;
        
    case 'generate_spend_analysis':
        generateSpendAnalysis($conn);
        break;
        
    case 'get_spend_analytics':
        getSpendAnalytics($conn);
        break;
        
    case 'forecast_inventory':
        forecastInventory($conn);
        break;
        
    case 'get_procurement_categories':
        getProcurementCategories($conn);
        break;
        
    case 'get_approval_workflow':
        getApprovalWorkflow($conn);
        break;
        
    case 'process_approval':
        processApproval($conn);
        break;
        
    case 'get_supplier_performance':
        getSupplierPerformance($conn);
        break;
        
    case 'generate_procurement_reports':
        generateProcurementReports($conn);
        break;
        
    case 'get_contract_alerts':
        getContractAlerts($conn);
        break;
        
    case 'bulk_approve_requisitions':
        bulkApproveRequisitions($conn);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function createRequisition($conn) {
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $priority = mysqli_real_escape_string($conn, $_POST['priority']);
    $required_by_date = mysqli_real_escape_string($conn, $_POST['required_by_date']);
    $justification = mysqli_real_escape_string($conn, $_POST['justification']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Generate requisition number
    $requisition_number = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Calculate total amount from items
    $total_amount = 0;
    if (isset($_POST['quantity']) && isset($_POST['unit_price'])) {
        for ($i = 0; $i < count($_POST['quantity']); $i++) {
            $quantity = intval($_POST['quantity'][$i]);
            $unit_price = floatval($_POST['unit_price'][$i]);
            $total_amount += $quantity * $unit_price;
        }
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert requisition
        $requisition_query = "INSERT INTO purchase_requisitions 
                             (requisition_number, department, requested_by, priority, status, 
                              total_amount, required_by_date, justification) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($requisition_query);
        $stmt->bind_param("ssissdss", 
            $requisition_number, $department, $_SESSION['admin'], $priority, 
            $status, $total_amount, $required_by_date, $justification
        );
        $stmt->execute();
        
        $requisition_id = $conn->insert_id;
        
        // Insert requisition items
        if (isset($_POST['item_description'])) {
            $item_query = "INSERT INTO requisition_items 
                          (requisition_id, item_description, quantity, unit_price, total_price) 
                          VALUES (?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_query);
            
            for ($i = 0; $i < count($_POST['item_description']); $i++) {
                $description = mysqli_real_escape_string($conn, $_POST['item_description'][$i]);
                $quantity = intval($_POST['quantity'][$i]);
                $unit_price = floatval($_POST['unit_price'][$i]);
                $total_price = $quantity * $unit_price;
                
                $item_stmt->bind_param("isidh", $requisition_id, $description, $quantity, $unit_price, $total_price);
                $item_stmt->execute();
            }
        }
        
        // Create approval workflow if submitted
        if ($status === 'submitted') {
            createApprovalWorkflow($conn, 'requisition', $requisition_id, $total_amount);
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Requisition created successfully',
            'requisition_id' => $requisition_id,
            'requisition_number' => $requisition_number
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error creating requisition: ' . $e->getMessage()]);
    }
}

function getRecentRequisitions($conn) {
    $query = "SELECT pr.*, 
              CONCAT(COALESCE(e.name, 'System User')) as requested_by_name
              FROM purchase_requisitions pr
              LEFT JOIN employees e ON pr.requested_by = e.employee_id
              ORDER BY pr.created_at DESC 
              LIMIT 10";
    
    $result = mysqli_query($conn, $query);
    $requisitions = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $requisitions[] = $row;
    }
    
    echo json_encode(['success' => true, 'requisitions' => $requisitions]);
}

function getAllRequisitions($conn) {
    $status_filter = $_POST['status_filter'] ?? '';
    $priority_filter = $_POST['priority_filter'] ?? '';
    $search = $_POST['search'] ?? '';
    
    $where_conditions = ['1=1'];
    
    if ($status_filter) {
        $where_conditions[] = "pr.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
    }
    
    if ($priority_filter) {
        $where_conditions[] = "pr.priority = '" . mysqli_real_escape_string($conn, $priority_filter) . "'";
    }
    
    if ($search) {
        $search = mysqli_real_escape_string($conn, $search);
        $where_conditions[] = "(pr.requisition_number LIKE '%$search%' OR pr.department LIKE '%$search%' OR pr.justification LIKE '%$search%')";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT pr.*, 
              CONCAT(COALESCE(e.name, 'System User')) as requested_by_name
              FROM purchase_requisitions pr
              LEFT JOIN employees e ON pr.requested_by = e.employee_id
              WHERE $where_clause
              ORDER BY pr.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $requisitions = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $requisitions[] = $row;
    }
    
    echo json_encode(['success' => true, 'requisitions' => $requisitions]);
}

function approveRequisition($conn) {
    $requisition_id = intval($_POST['requisition_id']);
    $approval_notes = mysqli_real_escape_string($conn, $_POST['approval_notes'] ?? '');
    $action = $_POST['approval_action']; // 'approve' or 'reject'
    
    $new_status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $conn->begin_transaction();
    
    try {
        // Update requisition status
        $update_query = "UPDATE purchase_requisitions 
                        SET status = ?, approval_notes = ?, approved_by = ?, approved_at = NOW() 
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssii", $new_status, $approval_notes, $_SESSION['admin'], $requisition_id);
        $stmt->execute();
        
        // Update approval workflow
        $approval_query = "UPDATE procurement_approvals 
                          SET status = ?, comments = ?, approved_at = NOW() 
                          WHERE request_type = 'requisition' AND request_id = ? AND status = 'pending'";
        $approval_stmt = $conn->prepare($approval_query);
        $approval_status = ($action === 'approve') ? 'approved' : 'rejected';
        $approval_stmt->bind_param("ssi", $approval_status, $approval_notes, $requisition_id);
        $approval_stmt->execute();
        
        // If approved, can automatically create PO or move to next stage
        if ($action === 'approve') {
            // Logic for next steps (e.g., create PO, send to procurement team)
            logProcurementActivity($conn, 'requisition_approved', $requisition_id, "Requisition approved by admin");
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Requisition ' . $new_status . ' successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error processing approval: ' . $e->getMessage()]);
    }
}

function createRFQ($conn) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $deadline = mysqli_real_escape_string($conn, $_POST['deadline']);
    $supplier_ids = $_POST['supplier_ids'] ?? [];
    
    // Generate RFQ number
    $rfq_number = 'RFQ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $conn->begin_transaction();
    
    try {
        // Insert RFQ
        $rfq_query = "INSERT INTO rfq_requests (rfq_number, title, description, deadline, created_by) 
                     VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($rfq_query);
        $stmt->bind_param("ssssi", $rfq_number, $title, $description, $deadline, $_SESSION['admin']);
        $stmt->execute();
        
        $rfq_id = $conn->insert_id;
        
        // Add suppliers to RFQ
        if (!empty($supplier_ids)) {
            $supplier_query = "INSERT INTO rfq_suppliers (rfq_id, supplier_id) VALUES (?, ?)";
            $supplier_stmt = $conn->prepare($supplier_query);
            
            foreach ($supplier_ids as $supplier_id) {
                $supplier_stmt->bind_param("ii", $rfq_id, $supplier_id);
                $supplier_stmt->execute();
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'RFQ created successfully',
            'rfq_id' => $rfq_id,
            'rfq_number' => $rfq_number
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error creating RFQ: ' . $e->getMessage()]);
    }
}

function getRFQs($conn) {
    $query = "SELECT r.*, 
              COUNT(rs.id) as supplier_count,
              SUM(rs.response_received) as response_count
              FROM rfq_requests r
              LEFT JOIN rfq_suppliers rs ON r.id = rs.rfq_id
              GROUP BY r.id
              ORDER BY r.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $rfqs = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $rfqs[] = $row;
    }
    
    echo json_encode(['success' => true, 'rfqs' => $rfqs]);
}

function evaluateVendor($conn) {
    $supplier_id = intval($_POST['supplier_id']);
    $evaluation_period = mysqli_real_escape_string($conn, $_POST['evaluation_period']);
    $quality_score = intval($_POST['quality_score']);
    $delivery_score = intval($_POST['delivery_score']);
    $price_competitiveness = intval($_POST['price_competitiveness']);
    $communication_score = intval($_POST['communication_score']);
    $comments = mysqli_real_escape_string($conn, $_POST['comments']);
    
    // Calculate overall score
    $overall_score = ($quality_score + $delivery_score + $price_competitiveness + $communication_score) / 4;
    
    $query = "INSERT INTO vendor_evaluations 
             (supplier_id, evaluation_period, quality_score, delivery_score, 
              price_competitiveness, communication_score, overall_score, comments, evaluated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isiiiidsi", 
        $supplier_id, $evaluation_period, $quality_score, $delivery_score,
        $price_competitiveness, $communication_score, $overall_score, $comments, $_SESSION['admin']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vendor evaluation completed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving vendor evaluation']);
    }
}

function getVendorEvaluations($conn) {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    
    $where_clause = $supplier_id ? "WHERE ve.supplier_id = $supplier_id" : "";
    
    $query = "SELECT ve.*, s.supplier_name, s.company_name
              FROM vendor_evaluations ve
              JOIN suppliers s ON ve.supplier_id = s.id
              $where_clause
              ORDER BY ve.evaluation_date DESC";
    
    $result = mysqli_query($conn, $query);
    $evaluations = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $evaluations[] = $row;
    }
    
    echo json_encode(['success' => true, 'evaluations' => $evaluations]);
}

function createContract($conn) {
    $supplier_id = intval($_POST['supplier_id']);
    $contract_type = mysqli_real_escape_string($conn, $_POST['contract_type']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $value = floatval($_POST['value']);
    $currency = mysqli_real_escape_string($conn, $_POST['currency'] ?? 'INR');
    $auto_renewal = isset($_POST['auto_renewal']) ? 1 : 0;
    $renewal_notice_days = intval($_POST['renewal_notice_days'] ?? 30);
    $terms_conditions = mysqli_real_escape_string($conn, $_POST['terms_conditions']);
    
    // Generate contract number
    $contract_number = 'CTR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $query = "INSERT INTO contract_management 
             (contract_number, supplier_id, contract_type, title, start_date, end_date, 
              value, currency, auto_renewal, renewal_notice_days, terms_conditions, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sissssdsissi", 
        $contract_number, $supplier_id, $contract_type, $title, $start_date, $end_date,
        $value, $currency, $auto_renewal, $renewal_notice_days, $terms_conditions, $_SESSION['admin']
    );
    
    if ($stmt->execute()) {
        $contract_id = $conn->insert_id;
        echo json_encode([
            'success' => true, 
            'message' => 'Contract created successfully',
            'contract_id' => $contract_id,
            'contract_number' => $contract_number
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating contract']);
    }
}

function getContracts($conn) {
    $status_filter = $_POST['status_filter'] ?? '';
    
    $where_clause = $status_filter ? "WHERE cm.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'" : "";
    
    $query = "SELECT cm.*, s.supplier_name, s.company_name
              FROM contract_management cm
              JOIN suppliers s ON cm.supplier_id = s.id
              $where_clause
              ORDER BY cm.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    $contracts = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Check if contract is expiring soon
        $days_to_expiry = (strtotime($row['end_date']) - time()) / (60 * 60 * 24);
        $row['days_to_expiry'] = round($days_to_expiry);
        $row['is_expiring_soon'] = $days_to_expiry <= 30 && $days_to_expiry > 0;
        
        $contracts[] = $row;
    }
    
    echo json_encode(['success' => true, 'contracts' => $contracts]);
}

function generateSpendAnalysis($conn) {
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $department = $_POST['department'] ?? null;
    
    // Analyze spend from purchase orders
    $where_conditions = ["po.order_date BETWEEN '$start_date' AND '$end_date'"];
    
    if ($department) {
        $department = mysqli_real_escape_string($conn, $department);
        $where_conditions[] = "po.department = '$department'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $query = "SELECT 
                po.supplier_id,
                s.supplier_name,
                s.company_name,
                COUNT(po.id) as order_count,
                SUM(po.total_amount) as total_spend,
                AVG(po.total_amount) as avg_order_value,
                MIN(po.order_date) as first_order,
                MAX(po.order_date) as last_order
              FROM purchase_orders po
              JOIN suppliers s ON po.supplier_id = s.id
              WHERE $where_clause
              GROUP BY po.supplier_id
              ORDER BY total_spend DESC";
    
    $result = mysqli_query($conn, $query);
    $spend_analysis = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $spend_analysis[] = $row;
    }
    
    // Store analysis results for future reference
    foreach ($spend_analysis as $analysis) {
        $insert_query = "INSERT INTO spend_analysis 
                        (period_start, period_end, supplier_id, total_spend, transaction_count, average_order_value)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        total_spend = VALUES(total_spend),
                        transaction_count = VALUES(transaction_count),
                        average_order_value = VALUES(average_order_value)";
        
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssidid", 
            $start_date, $end_date, $analysis['supplier_id'], 
            $analysis['total_spend'], $analysis['order_count'], $analysis['avg_order_value']
        );
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'spend_analysis' => $spend_analysis]);
}

function getSupplierPerformance($conn) {
    $supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;
    
    $where_clause = $supplier_id ? "WHERE po.supplier_id = $supplier_id" : "";
    
    $query = "SELECT 
                s.supplier_name,
                s.company_name,
                COUNT(po.id) as total_orders,
                SUM(po.total_amount) as total_value,
                AVG(po.total_amount) as avg_order_value,
                SUM(CASE WHEN po.status = 'received' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN po.delivery_date <= po.expected_delivery_date THEN 1 ELSE 0 END) as on_time_deliveries,
                AVG(ve.overall_score) as avg_evaluation_score
              FROM purchase_orders po
              JOIN suppliers s ON po.supplier_id = s.id
              LEFT JOIN vendor_evaluations ve ON s.id = ve.supplier_id
              $where_clause
              GROUP BY po.supplier_id
              ORDER BY total_value DESC";
    
    $result = mysqli_query($conn, $query);
    $performance = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate performance metrics
        $row['delivery_rate'] = $row['total_orders'] > 0 ? 
            round(($row['delivered_orders'] / $row['total_orders']) * 100, 2) : 0;
        $row['on_time_rate'] = $row['delivered_orders'] > 0 ? 
            round(($row['on_time_deliveries'] / $row['delivered_orders']) * 100, 2) : 0;
        
        $performance[] = $row;
    }
    
    echo json_encode(['success' => true, 'performance' => $performance]);
}

function createApprovalWorkflow($conn, $request_type, $request_id, $amount) {
    // Define approval levels based on amount
    $approval_levels = [];
    
    if ($amount <= 10000) {
        $approval_levels[] = ['level' => 1, 'approver_id' => 1]; // Department head
    } elseif ($amount <= 50000) {
        $approval_levels[] = ['level' => 1, 'approver_id' => 1]; // Department head
        $approval_levels[] = ['level' => 2, 'approver_id' => 2]; // Finance manager
    } else {
        $approval_levels[] = ['level' => 1, 'approver_id' => 1]; // Department head
        $approval_levels[] = ['level' => 2, 'approver_id' => 2]; // Finance manager
        $approval_levels[] = ['level' => 3, 'approver_id' => 3]; // CEO/Director
    }
    
    $stmt = $conn->prepare("INSERT INTO procurement_approvals 
                           (request_type, request_id, approval_level, approver_id) 
                           VALUES (?, ?, ?, ?)");
    
    foreach ($approval_levels as $level) {
        $stmt->bind_param("siii", $request_type, $request_id, $level['level'], $level['approver_id']);
        $stmt->execute();
    }
}

function logProcurementActivity($conn, $activity_type, $entity_id, $description) {
    // Implementation for activity logging
    $log_query = "INSERT INTO activity_logs (user_id, activity_type, entity_id, description, created_at) 
                  VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_query);
    $stmt->bind_param("isis", $_SESSION['admin'], $activity_type, $entity_id, $description);
    $stmt->execute();
}

function getContractAlerts($conn) {
    $query = "SELECT cm.*, s.supplier_name,
              DATEDIFF(cm.end_date, CURDATE()) as days_to_expiry
              FROM contract_management cm
              JOIN suppliers s ON cm.supplier_id = s.id
              WHERE cm.status = 'active' 
              AND cm.end_date <= DATE_ADD(CURDATE(), INTERVAL cm.renewal_notice_days DAY)
              ORDER BY days_to_expiry ASC";
    
    $result = mysqli_query($conn, $query);
    $alerts = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $alerts[] = $row;
    }
    
    echo json_encode(['success' => true, 'alerts' => $alerts]);
}

function forecastInventory($conn) {
    // Simple inventory forecasting based on historical data
    $forecast_days = intval($_POST['forecast_days'] ?? 30);
    
    // Get historical consumption data
    $query = "SELECT 
                i.id,
                i.item_name,
                i.stock as current_stock,
                AVG(ii.quantity) as avg_daily_usage,
                STDDEV(ii.quantity) as usage_variance
              FROM items i
              LEFT JOIN invoice_items ii ON i.id = ii.item_id
              LEFT JOIN invoices inv ON ii.invoice_id = inv.id
              WHERE inv.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
              GROUP BY i.id
              HAVING avg_daily_usage > 0
              ORDER BY avg_daily_usage DESC";
    
    $result = mysqli_query($conn, $query);
    $forecasts = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $daily_usage = floatval($row['avg_daily_usage']);
        $current_stock = intval($row['current_stock']);
        $forecasted_demand = round($daily_usage * $forecast_days);
        
        // Calculate reorder point (safety stock + lead time demand)
        $lead_time_days = 7; // Default lead time
        $safety_stock = round($daily_usage * 3); // 3 days safety stock
        $reorder_point = round(($daily_usage * $lead_time_days) + $safety_stock);
        
        // Optimal order quantity (simple EOQ approximation)
        $optimal_order_quantity = round(sqrt(2 * $forecasted_demand * 100 / 5)); // Simplified
        
        $forecast = [
            'item_id' => $row['id'],
            'item_name' => $row['item_name'],
            'current_stock' => $current_stock,
            'forecasted_demand' => $forecasted_demand,
            'reorder_point' => $reorder_point,
            'optimal_order_quantity' => $optimal_order_quantity,
            'stockout_risk' => $current_stock < $reorder_point ? 'High' : 'Low',
            'days_until_stockout' => $daily_usage > 0 ? round($current_stock / $daily_usage) : null
        ];
        
        $forecasts[] = $forecast;
        
        // Store forecast in database
        $insert_query = "INSERT INTO inventory_forecasting 
                        (item_id, item_name, current_stock, forecasted_demand, forecast_period,
                         reorder_point, optimal_order_quantity, lead_time_days, safety_stock)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        current_stock = VALUES(current_stock),
                        forecasted_demand = VALUES(forecasted_demand),
                        reorder_point = VALUES(reorder_point),
                        optimal_order_quantity = VALUES(optimal_order_quantity)";
        
        $stmt = $conn->prepare($insert_query);
        $forecast_period = $forecast_days . ' days';
        $stmt->bind_param("isisissii", 
            $row['id'], $row['item_name'], $current_stock, $forecasted_demand, 
            $forecast_period, $reorder_point, $optimal_order_quantity, 
            $lead_time_days, $safety_stock
        );
        $stmt->execute();
    }
    
    echo json_encode(['success' => true, 'forecasts' => $forecasts]);
}

function bulkApproveRequisitions($conn) {
    $requisition_ids = $_POST['requisition_ids'] ?? [];
    $approval_notes = mysqli_real_escape_string($conn, $_POST['approval_notes'] ?? 'Bulk approval');
    
    if (empty($requisition_ids)) {
        echo json_encode(['success' => false, 'message' => 'No requisitions selected']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $approved_count = 0;
        
        foreach ($requisition_ids as $req_id) {
            $req_id = intval($req_id);
            
            // Update requisition status
            $update_query = "UPDATE purchase_requisitions 
                            SET status = 'approved', approval_notes = ?, 
                                approved_by = ?, approved_at = NOW() 
                            WHERE id = ? AND status = 'submitted'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sii", $approval_notes, $_SESSION['admin'], $req_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $approved_count++;
                
                // Update approval workflow
                $approval_query = "UPDATE procurement_approvals 
                                  SET status = 'approved', comments = ?, approved_at = NOW() 
                                  WHERE request_type = 'requisition' AND request_id = ? AND status = 'pending'";
                $approval_stmt = $conn->prepare($approval_query);
                $approval_stmt->bind_param("si", $approval_notes, $req_id);
                $approval_stmt->execute();
                
                logProcurementActivity($conn, 'bulk_requisition_approved', $req_id, "Bulk approved requisition");
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Successfully approved $approved_count requisitions"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error in bulk approval: ' . $e->getMessage()]);
    }
}

function generateProcurementReports($conn) {
    $report_type = $_POST['report_type'] ?? 'summary';
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    
    $reports = [];
    
    switch ($report_type) {
        case 'summary':
            // Overall procurement summary
            $summary_query = "SELECT 
                COUNT(pr.id) as total_requisitions,
                SUM(pr.total_amount) as total_requisition_value,
                AVG(pr.total_amount) as avg_requisition_value,
                COUNT(CASE WHEN pr.status = 'approved' THEN 1 END) as approved_requisitions,
                COUNT(po.id) as total_purchase_orders,
                SUM(po.total_amount) as total_po_value
            FROM purchase_requisitions pr
            LEFT JOIN purchase_orders po ON DATE(po.order_date) BETWEEN '$start_date' AND '$end_date'
            WHERE DATE(pr.created_at) BETWEEN '$start_date' AND '$end_date'";
            
            $result = mysqli_query($conn, $summary_query);
            $reports['summary'] = mysqli_fetch_assoc($result);
            break;
            
        case 'supplier_performance':
            $supplier_query = "SELECT 
                s.supplier_name,
                COUNT(po.id) as order_count,
                SUM(po.total_amount) as total_value,
                AVG(ve.overall_score) as avg_rating
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.id = po.supplier_id 
                AND DATE(po.order_date) BETWEEN '$start_date' AND '$end_date'
            LEFT JOIN vendor_evaluations ve ON s.id = ve.supplier_id
            WHERE po.id IS NOT NULL
            GROUP BY s.id
            ORDER BY total_value DESC";
            
            $result = mysqli_query($conn, $supplier_query);
            $reports['supplier_performance'] = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $reports['supplier_performance'][] = $row;
            }
            break;
            
        case 'spend_analysis':
            $spend_query = "SELECT 
                DATE_FORMAT(po.order_date, '%Y-%m') as month,
                SUM(po.total_amount) as monthly_spend,
                COUNT(po.id) as order_count
            FROM purchase_orders po
            WHERE DATE(po.order_date) BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE_FORMAT(po.order_date, '%Y-%m')
            ORDER BY month";
            
            $result = mysqli_query($conn, $spend_query);
            $reports['spend_analysis'] = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $reports['spend_analysis'][] = $row;
            }
            break;
    }
    
    echo json_encode(['success' => true, 'reports' => $reports]);
}

function submitRFQResponse($conn) {
    $rfq_id = intval($_POST['rfq_id']);
    $supplier_id = intval($_POST['supplier_id']);
    $quoted_amount = floatval($_POST['quoted_amount']);
    $delivery_terms = mysqli_real_escape_string($conn, $_POST['delivery_terms']);
    $payment_terms = mysqli_real_escape_string($conn, $_POST['payment_terms']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes'] ?? '');
    
    $query = "UPDATE rfq_suppliers 
              SET response_received = 1, response_date = NOW(), 
                  quoted_amount = ?, delivery_terms = ?, payment_terms = ?, notes = ?
              WHERE rfq_id = ? AND supplier_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dssssii", $quoted_amount, $delivery_terms, $payment_terms, $notes, $rfq_id, $supplier_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'RFQ response submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error submitting RFQ response']);
    }
}

function updateContractStatus($conn) {
    $contract_id = intval($_POST['contract_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE contract_management SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $new_status, $contract_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Contract status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating contract status']);
    }
}

function getSpendAnalytics($conn) {
    $period = $_POST['period'] ?? '12'; // months
    
    // Monthly spend trend
    $trend_query = "SELECT 
        DATE_FORMAT(sa.period_start, '%Y-%m') as period,
        SUM(sa.total_spend) as total_spend,
        COUNT(DISTINCT sa.supplier_id) as active_suppliers
    FROM spend_analysis sa
    WHERE sa.period_start >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
    GROUP BY DATE_FORMAT(sa.period_start, '%Y-%m')
    ORDER BY period";
    
    $trend_result = mysqli_query($conn, $trend_query);
    $spend_trend = [];
    while ($row = mysqli_fetch_assoc($trend_result)) {
        $spend_trend[] = $row;
    }
    
    // Category breakdown
    $category_query = "SELECT 
        pc.name as category,
        SUM(sa.total_spend) as total_spend
    FROM spend_analysis sa
    LEFT JOIN procurement_categories pc ON sa.category_id = pc.id
    WHERE sa.period_start >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY sa.category_id
    ORDER BY total_spend DESC";
    
    $category_result = mysqli_query($conn, $category_query);
    $category_spend = [];
    while ($row = mysqli_fetch_assoc($category_result)) {
        $category_spend[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'spend_trend' => $spend_trend,
        'category_spend' => $category_spend
    ]);
}

function getProcurementCategories($conn) {
    $query = "SELECT * FROM procurement_categories WHERE is_active = 1 ORDER BY name";
    $result = mysqli_query($conn, $query);
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function getApprovalWorkflow($conn) {
    $request_type = mysqli_real_escape_string($conn, $_POST['request_type']);
    $request_id = intval($_POST['request_id']);
    
    $query = "SELECT pa.*, 
              CASE 
                WHEN e.name IS NOT NULL THEN e.name
                ELSE 'Admin User'
              END as approver_name
              FROM procurement_approvals pa
              LEFT JOIN employees e ON pa.approver_id = e.employee_id
              WHERE pa.request_type = ? AND pa.request_id = ?
              ORDER BY pa.approval_level";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $request_type, $request_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $workflow = [];
    while ($row = $result->fetch_assoc()) {
        $workflow[] = $row;
    }
    
    echo json_encode(['success' => true, 'workflow' => $workflow]);
}

function processApproval($conn) {
    $approval_id = intval($_POST['approval_id']);
    $action = mysqli_real_escape_string($conn, $_POST['action']); // 'approve' or 'reject'
    $comments = mysqli_real_escape_string($conn, $_POST['comments'] ?? '');
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    $query = "UPDATE procurement_approvals 
              SET status = ?, comments = ?, approved_at = NOW() 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $status, $comments, $approval_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Approval processed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error processing approval']);
    }
}
?>
