<?php
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

include '../../db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'lead_details':
            $lead_id = intval($_GET['id']);
            
            // Get lead details with assigned user info
            $lead_query = "
                SELECT cl.*, 
                       e.name as assigned_name,
                       c.customer_name as customer_name
                FROM crm_leads cl
                LEFT JOIN employees e ON cl.assigned_to = e.employee_id
                LEFT JOIN customers c ON cl.customer_id = c.id
                WHERE cl.id = $lead_id
            ";
            
            $result = mysqli_query($conn, $lead_query);
            if (!$result || $result->num_rows === 0) {
                echo json_encode(['error' => 'Lead not found']);
                exit;
            }
            
            $lead = $result->fetch_assoc();
            
            // Get communications for this lead
            $comms_query = "
                SELECT cc.*, e.name as created_by_name
                FROM crm_communications cc
                LEFT JOIN employees e ON cc.created_by = e.name
                WHERE cc.lead_id = $lead_id
                ORDER BY cc.communication_date DESC
            ";
            $lead['communications'] = mysqli_fetch_all(mysqli_query($conn, $comms_query), MYSQLI_ASSOC);
            
            // Get tasks for this lead
            $tasks_query = "
                SELECT ct.*, e.name as assigned_name
                FROM crm_tasks ct
                LEFT JOIN employees e ON ct.assigned_to = e.employee_id
                WHERE ct.lead_id = $lead_id
                ORDER BY ct.due_date ASC
            ";
            $lead['tasks'] = mysqli_fetch_all(mysqli_query($conn, $tasks_query), MYSQLI_ASSOC);
            
            // Get activities for this lead
            $activities_query = "
                SELECT * FROM crm_activities 
                WHERE lead_id = $lead_id 
                ORDER BY activity_date DESC 
                LIMIT 10
            ";
            $lead['activities'] = mysqli_fetch_all(mysqli_query($conn, $activities_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'lead' => $lead]);
            break;
            
        case 'opportunity_details':
            $opp_id = intval($_GET['id']);
            
            $opp_query = "
                SELECT co.*, 
                       c.customer_name, c.email as customer_email,
                       e.name as assigned_name,
                       cl.company_name as lead_company
                FROM crm_opportunities co
                LEFT JOIN customers c ON co.customer_id = c.id
                LEFT JOIN employees e ON co.assigned_to = e.employee_id
                LEFT JOIN crm_leads cl ON co.lead_id = cl.id
                WHERE co.id = $opp_id
            ";
            
            $result = mysqli_query($conn, $opp_query);
            if (!$result || $result->num_rows === 0) {
                echo json_encode(['error' => 'Opportunity not found']);
                exit;
            }
            
            $opportunity = $result->fetch_assoc();
            
            // Get communications related to this opportunity
            $comms_query = "
                SELECT cc.*, e.name as created_by_name
                FROM crm_communications cc
                LEFT JOIN employees e ON cc.created_by = e.name
                WHERE (cc.customer_id = {$opportunity['customer_id']} OR cc.lead_id = {$opportunity['lead_id']})
                ORDER BY cc.communication_date DESC
                LIMIT 10
            ";
            $opportunity['communications'] = mysqli_fetch_all(mysqli_query($conn, $comms_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'opportunity' => $opportunity]);
            break;
            
        case 'crm_statistics':
            $date_range = $_GET['range'] ?? '30'; // days
            $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
            
            $stats = [];
            
            // Lead statistics
            $lead_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_leads,
                    SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified_leads,
                    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_leads,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_leads,
                    AVG(lead_value) as avg_lead_value,
                    SUM(lead_value) as total_lead_value
                FROM crm_leads 
                WHERE created_date >= '$start_date'
            ");
            $stats['leads'] = mysqli_fetch_assoc($lead_stats);
            
            // Opportunity statistics
            $opp_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_opportunities,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_opportunities,
                    SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_opportunities,
                    SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_opportunities,
                    SUM(CASE WHEN status = 'open' THEN value ELSE 0 END) as pipeline_value,
                    SUM(CASE WHEN status = 'won' THEN value ELSE 0 END) as won_value,
                    AVG(CASE WHEN status = 'open' THEN probability ELSE NULL END) as avg_probability
                FROM crm_opportunities 
                WHERE created_date >= '$start_date'
            ");
            $stats['opportunities'] = mysqli_fetch_assoc($opp_stats);
            
            // Communication statistics
            $comm_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_communications,
                    SUM(CASE WHEN communication_type = 'email' THEN 1 ELSE 0 END) as emails,
                    SUM(CASE WHEN communication_type = 'phone' THEN 1 ELSE 0 END) as phone_calls,
                    SUM(CASE WHEN communication_type = 'meeting' THEN 1 ELSE 0 END) as meetings,
                    SUM(CASE WHEN outcome = 'positive' THEN 1 ELSE 0 END) as positive_outcomes,
                    COUNT(DISTINCT CASE WHEN customer_id IS NOT NULL THEN customer_id WHEN lead_id IS NOT NULL THEN lead_id END) as unique_contacts
                FROM crm_communications 
                WHERE communication_date >= '$start_date'
            ");
            $stats['communications'] = mysqli_fetch_assoc($comm_stats);
            
            // Task statistics
            $task_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
                    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
                FROM crm_tasks 
                WHERE created_date >= '$start_date'
            ");
            $stats['tasks'] = mysqli_fetch_assoc($task_stats);
            
            // Performance by assigned user
            $user_performance = mysqli_query($conn, "
                SELECT 
                    e.name,
                    COUNT(DISTINCT cl.id) as leads_assigned,
                    COUNT(DISTINCT co.id) as opportunities_assigned,
                    SUM(CASE WHEN co.status = 'won' THEN co.value ELSE 0 END) as won_value,
                    AVG(co.probability) as avg_opportunity_probability
                FROM employees e
                LEFT JOIN crm_leads cl ON e.employee_id = cl.assigned_to AND cl.created_date >= '$start_date'
                LEFT JOIN crm_opportunities co ON e.employee_id = co.assigned_to AND co.created_date >= '$start_date'
                WHERE e.status = 'active'
                GROUP BY e.employee_id, e.name
                HAVING leads_assigned > 0 OR opportunities_assigned > 0
                ORDER BY won_value DESC, leads_assigned DESC
            ");
            $stats['user_performance'] = mysqli_fetch_all($user_performance, MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'sales_pipeline':
            // Get opportunities grouped by stage
            $pipeline_query = "
                SELECT 
                    stage,
                    COUNT(*) as opportunity_count,
                    SUM(value) as stage_value,
                    AVG(probability) as avg_probability
                FROM crm_opportunities 
                WHERE status = 'open'
                GROUP BY stage
                ORDER BY 
                    FIELD(stage, 'prospecting', 'qualification', 'needs_analysis', 'proposal', 'negotiation', 'closed_won', 'closed_lost')
            ";
            
            $pipeline_data = mysqli_fetch_all(mysqli_query($conn, $pipeline_query), MYSQLI_ASSOC);
            
            // Get opportunities by stage with details
            $detailed_pipeline = [];
            foreach ($pipeline_data as $stage) {
                $stage_opportunities = mysqli_query($conn, "
                    SELECT co.*, c.customer_name, e.name as assigned_name
                    FROM crm_opportunities co
                    LEFT JOIN customers c ON co.customer_id = c.id
                    LEFT JOIN employees e ON co.assigned_to = e.employee_id
                    WHERE co.stage = '{$stage['stage']}' AND co.status = 'open'
                    ORDER BY co.value DESC
                ");
                
                $detailed_pipeline[$stage['stage']] = [
                    'summary' => $stage,
                    'opportunities' => mysqli_fetch_all($stage_opportunities, MYSQLI_ASSOC)
                ];
            }
            
            echo json_encode(['success' => true, 'pipeline' => $detailed_pipeline]);
            break;
            
        case 'communication_history':
            $entity_type = $_GET['entity_type'] ?? 'all'; // 'customer', 'lead', or 'all'
            $entity_id = intval($_GET['entity_id'] ?? 0);
            $limit = intval($_GET['limit'] ?? 20);
            
            $where_clause = "WHERE 1=1";
            if ($entity_type === 'customer' && $entity_id > 0) {
                $where_clause .= " AND cc.customer_id = $entity_id";
            } elseif ($entity_type === 'lead' && $entity_id > 0) {
                $where_clause .= " AND cc.lead_id = $entity_id";
            }
            
            $comm_query = "
                SELECT cc.*, 
                       c.customer_name,
                       cl.company_name as lead_company,
                       e.name as created_by_name
                FROM crm_communications cc
                LEFT JOIN customers c ON cc.customer_id = c.id
                LEFT JOIN crm_leads cl ON cc.lead_id = cl.id
                LEFT JOIN employees e ON cc.created_by = e.name
                $where_clause
                ORDER BY cc.communication_date DESC
                LIMIT $limit
            ";
            
            $communications = mysqli_fetch_all(mysqli_query($conn, $comm_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'communications' => $communications]);
            break;
            
        case 'task_list':
            $assigned_to = intval($_GET['assigned_to'] ?? 0);
            $status = $_GET['status'] ?? 'all';
            $due_filter = $_GET['due_filter'] ?? 'all'; // 'overdue', 'today', 'this_week', 'all'
            
            $where_clause = "WHERE 1=1";
            
            if ($assigned_to > 0) {
                $where_clause .= " AND ct.assigned_to = $assigned_to";
            }
            
            if ($status !== 'all') {
                $where_clause .= " AND ct.status = '$status'";
            }
            
            switch ($due_filter) {
                case 'overdue':
                    $where_clause .= " AND ct.due_date < CURDATE() AND ct.status != 'completed'";
                    break;
                case 'today':
                    $where_clause .= " AND DATE(ct.due_date) = CURDATE()";
                    break;
                case 'this_week':
                    $where_clause .= " AND ct.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
            }
            
            $task_query = "
                SELECT ct.*, 
                       c.customer_name,
                       cl.company_name as lead_company,
                       e.name as assigned_name
                FROM crm_tasks ct
                LEFT JOIN customers c ON ct.customer_id = c.id
                LEFT JOIN crm_leads cl ON ct.lead_id = cl.id
                LEFT JOIN employees e ON ct.assigned_to = e.employee_id
                $where_clause
                ORDER BY ct.due_date ASC, ct.priority DESC
            ";
            
            $tasks = mysqli_fetch_all(mysqli_query($conn, $task_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'tasks' => $tasks]);
            break;
            
        case 'lead_conversion_funnel':
            // Get lead conversion data
            $funnel_query = "
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(lead_value) as total_value
                FROM crm_leads 
                GROUP BY status
                ORDER BY 
                    FIELD(status, 'new', 'contacted', 'qualified', 'proposal', 'negotiation', 'converted', 'lost')
            ";
            
            $funnel_data = mysqli_fetch_all(mysqli_query($conn, $funnel_query), MYSQLI_ASSOC);
            
            // Calculate conversion rates
            $total_leads = array_sum(array_column($funnel_data, 'count'));
            foreach ($funnel_data as &$stage) {
                $stage['conversion_rate'] = $total_leads > 0 ? 
                    round(($stage['count'] / $total_leads) * 100, 1) : 0;
            }
            
            echo json_encode(['success' => true, 'funnel' => $funnel_data, 'total_leads' => $total_leads]);
            break;
            
        case 'revenue_forecast':
            // Get opportunity-based revenue forecast
            $forecast_query = "
                SELECT 
                    DATE_FORMAT(expected_close_date, '%Y-%m') as month,
                    SUM(value * (probability / 100)) as forecasted_revenue,
                    SUM(value) as potential_revenue,
                    COUNT(*) as opportunity_count
                FROM crm_opportunities 
                WHERE status = 'open' 
                AND expected_close_date >= CURDATE()
                AND expected_close_date <= DATE_ADD(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(expected_close_date, '%Y-%m')
                ORDER BY month ASC
            ";
            
            $forecast_data = mysqli_fetch_all(mysqli_query($conn, $forecast_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'forecast' => $forecast_data]);
            break;
            
        case 'activity_timeline':
            $limit = intval($_GET['limit'] ?? 50);
            $entity_type = $_GET['entity_type'] ?? 'all';
            $entity_id = intval($_GET['entity_id'] ?? 0);
            
            $where_clause = "WHERE 1=1";
            if ($entity_type === 'customer' && $entity_id > 0) {
                $where_clause .= " AND ca.customer_id = $entity_id";
            } elseif ($entity_type === 'lead' && $entity_id > 0) {
                $where_clause .= " AND ca.lead_id = $entity_id";
            }
            
            $timeline_query = "
                SELECT ca.*, 
                       c.customer_name,
                       cl.company_name as lead_company
                FROM crm_activities ca
                LEFT JOIN customers c ON ca.customer_id = c.id
                LEFT JOIN crm_leads cl ON ca.lead_id = cl.id
                $where_clause
                ORDER BY ca.activity_date DESC
                LIMIT $limit
            ";
            
            $activities = mysqli_fetch_all(mysqli_query($conn, $timeline_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'activities' => $activities]);
            break;
            
        case 'search_entities':
            $query = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
            $type = $_GET['type'] ?? 'all'; // 'leads', 'customers', 'opportunities', 'all'
            $limit = intval($_GET['limit'] ?? 10);
            
            $results = [];
            
            if ($type === 'leads' || $type === 'all') {
                $lead_search = "
                    SELECT 'lead' as entity_type, id, lead_id as entity_id, company_name as name, 
                           contact_person, email, phone, status
                    FROM crm_leads
                    WHERE company_name LIKE '%$query%' 
                    OR contact_person LIKE '%$query%' 
                    OR email LIKE '%$query%'
                    OR lead_id LIKE '%$query%'
                    ORDER BY company_name ASC
                    LIMIT $limit
                ";
                $leads = mysqli_fetch_all(mysqli_query($conn, $lead_search), MYSQLI_ASSOC);
                $results = array_merge($results, $leads);
            }
            
            if ($type === 'customers' || $type === 'all') {
                $customer_search = "
                    SELECT 'customer' as entity_type, id, id as entity_id, customer_name as name, 
                           contact_person, email, phone, status
                    FROM customers
                    WHERE customer_name LIKE '%$query%' 
                    OR contact_person LIKE '%$query%' 
                    OR email LIKE '%$query%'
                    ORDER BY customer_name ASC
                    LIMIT $limit
                ";
                $customers = mysqli_fetch_all(mysqli_query($conn, $customer_search), MYSQLI_ASSOC);
                $results = array_merge($results, $customers);
            }
            
            if ($type === 'opportunities' || $type === 'all') {
                $opp_search = "
                    SELECT 'opportunity' as entity_type, co.id, co.opportunity_id as entity_id, 
                           co.opportunity_name as name, c.customer_name as contact_person, 
                           c.email, c.phone, co.stage as status
                    FROM crm_opportunities co
                    LEFT JOIN customers c ON co.customer_id = c.id
                    WHERE co.opportunity_name LIKE '%$query%' 
                    OR co.opportunity_id LIKE '%$query%'
                    OR c.customer_name LIKE '%$query%'
                    ORDER BY co.opportunity_name ASC
                    LIMIT $limit
                ";
                $opportunities = mysqli_fetch_all(mysqli_query($conn, $opp_search), MYSQLI_ASSOC);
                $results = array_merge($results, $opportunities);
            }
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'bulk_update':
            $entity_type = $_POST['entity_type'] ?? ''; // 'leads', 'opportunities'
            $entity_ids = $_POST['entity_ids'] ?? [];
            $update_field = $_POST['update_field'] ?? '';
            $update_value = $_POST['update_value'] ?? '';
            
            if (empty($entity_ids) || empty($update_field) || empty($entity_type)) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }
            
            $ids = implode(',', array_map('intval', $entity_ids));
            $update_value = mysqli_real_escape_string($conn, $update_value);
            
            if ($entity_type === 'leads') {
                $table = 'crm_leads';
                $allowed_fields = ['status', 'priority', 'assigned_to'];
            } elseif ($entity_type === 'opportunities') {
                $table = 'crm_opportunities';
                $allowed_fields = ['stage', 'assigned_to'];
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid entity type']);
                exit;
            }
            
            if (!in_array($update_field, $allowed_fields)) {
                echo json_encode(['success' => false, 'message' => 'Invalid update field']);
                exit;
            }
            
            $update_query = "UPDATE $table SET $update_field = '$update_value', 
                            updated_by = '{$_SESSION['admin']}', last_updated = NOW() 
                            WHERE id IN ($ids)";
            
            if (mysqli_query($conn, $update_query)) {
                $affected = mysqli_affected_rows($conn);
                echo json_encode(['success' => true, 'message' => "$affected records updated successfully"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
