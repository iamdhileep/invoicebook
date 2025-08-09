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
        case 'project_details':
            $project_id = intval($_GET['id']);
            
            // Get project details
            $project_query = "
                SELECT p.*, 
                       CONCAT(e.name) as manager_name,
                       c.customer_name as client_name,
                       (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id) as total_tasks,
                       (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'completed') as completed_tasks,
                       (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND status = 'in_progress') as in_progress_tasks,
                       (SELECT COUNT(*) FROM project_tasks WHERE project_id = p.id AND due_date < CURDATE() AND status NOT IN ('completed')) as overdue_tasks,
                       (SELECT SUM(hours) FROM time_logs tl 
                        INNER JOIN project_tasks pt ON tl.task_id = pt.id 
                        WHERE pt.project_id = p.id) as total_hours_logged,
                       (SELECT SUM(estimated_hours) FROM project_tasks WHERE project_id = p.id) as total_estimated_hours
                FROM projects p
                LEFT JOIN employees e ON p.project_manager_id = e.employee_id
                LEFT JOIN customers c ON p.client_id = c.id
                WHERE p.id = $project_id
            ";
            
            $result = mysqli_query($conn, $project_query);
            if (!$result || $result->num_rows === 0) {
                echo json_encode(['error' => 'Project not found']);
                exit;
            }
            
            $project = $result->fetch_assoc();
            
            // Get team members
            $team_query = "
                SELECT pt.*, e.name, e.email, e.department
                FROM project_team pt
                INNER JOIN employees e ON pt.employee_id = e.employee_id
                WHERE pt.project_id = $project_id AND pt.is_active = 1
                ORDER BY pt.role, e.name
            ";
            $project['team_members'] = mysqli_fetch_all(mysqli_query($conn, $team_query), MYSQLI_ASSOC);
            
            // Get recent tasks
            $tasks_query = "
                SELECT pt.*, e.name as assignee_name
                FROM project_tasks pt
                LEFT JOIN employees e ON pt.assigned_to = e.employee_id
                WHERE pt.project_id = $project_id
                ORDER BY pt.created_date DESC
                LIMIT 20
            ";
            $project['tasks'] = mysqli_fetch_all(mysqli_query($conn, $tasks_query), MYSQLI_ASSOC);
            
            // Get recent activities
            $activities_query = "
                SELECT * FROM project_activities 
                WHERE project_id = $project_id 
                ORDER BY activity_date DESC 
                LIMIT 10
            ";
            $project['activities'] = mysqli_fetch_all(mysqli_query($conn, $activities_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'project' => $project]);
            break;
            
        case 'task_details':
            $task_id = intval($_GET['id']);
            
            $task_query = "
                SELECT pt.*, p.name as project_name, p.project_code,
                       e.name as assignee_name, e.email as assignee_email,
                       creator.name as creator_name
                FROM project_tasks pt
                INNER JOIN projects p ON pt.project_id = p.id
                LEFT JOIN employees e ON pt.assigned_to = e.employee_id
                LEFT JOIN employees creator ON pt.created_by = creator.employee_id
                WHERE pt.id = $task_id
            ";
            
            $result = mysqli_query($conn, $task_query);
            if (!$result || $result->num_rows === 0) {
                echo json_encode(['error' => 'Task not found']);
                exit;
            }
            
            $task = $result->fetch_assoc();
            
            // Get dependencies
            $deps_query = "
                SELECT td.*, pt.title as depends_on_title, pt.status as depends_on_status
                FROM task_dependencies td
                INNER JOIN project_tasks pt ON td.depends_on_task_id = pt.id
                WHERE td.task_id = $task_id
            ";
            $task['dependencies'] = mysqli_fetch_all(mysqli_query($conn, $deps_query), MYSQLI_ASSOC);
            
            // Get time logs
            $time_query = "
                SELECT tl.*, e.name as employee_name
                FROM time_logs tl
                LEFT JOIN employees e ON tl.employee_id = e.employee_id
                WHERE tl.task_id = $task_id
                ORDER BY tl.date_logged DESC
            ";
            $task['time_logs'] = mysqli_fetch_all(mysqli_query($conn, $time_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'task' => $task]);
            break;
            
        case 'project_stats':
            $date_range = $_GET['range'] ?? '30'; // days
            $start_date = date('Y-m-d', strtotime("-{$date_range} days"));
            
            // Overall statistics
            $stats = [];
            
            // Project statistics
            $project_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_projects,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_projects,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
                    SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_projects,
                    SUM(CASE WHEN end_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_projects,
                    AVG(budget) as avg_budget,
                    SUM(budget) as total_budget
                FROM projects 
                WHERE status != 'archived' AND created_date >= '$start_date'
            ");
            $stats['projects'] = mysqli_fetch_assoc($project_stats);
            
            // Task statistics
            $task_stats = mysqli_query($conn, "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_tasks,
                    SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('completed') THEN 1 ELSE 0 END) as overdue_tasks,
                    AVG(progress) as avg_progress,
                    SUM(estimated_hours) as total_estimated_hours,
                    SUM(actual_hours) as total_actual_hours
                FROM project_tasks pt
                INNER JOIN projects p ON pt.project_id = p.id
                WHERE p.status != 'archived' AND pt.created_date >= '$start_date'
            ");
            $stats['tasks'] = mysqli_fetch_assoc($task_stats);
            
            // Time tracking statistics
            $time_stats = mysqli_query($conn, "
                SELECT 
                    SUM(hours) as total_hours_logged,
                    COUNT(DISTINCT employee_id) as active_team_members,
                    COUNT(*) as total_time_entries,
                    AVG(hours) as avg_hours_per_entry
                FROM time_logs tl
                INNER JOIN project_tasks pt ON tl.task_id = pt.id
                INNER JOIN projects p ON pt.project_id = p.id
                WHERE p.status != 'archived' AND tl.date_logged >= '$start_date'
            ");
            $stats['time_tracking'] = mysqli_fetch_assoc($time_stats);
            
            // Top performers
            $top_performers = mysqli_query($conn, "
                SELECT e.name, e.employee_id,
                       COUNT(DISTINCT pt.id) as tasks_completed,
                       SUM(tl.hours) as hours_logged,
                       AVG(pt.progress) as avg_progress
                FROM employees e
                INNER JOIN project_tasks pt ON e.employee_id = pt.assigned_to
                LEFT JOIN time_logs tl ON pt.id = tl.task_id
                INNER JOIN projects p ON pt.project_id = p.id
                WHERE p.status != 'archived' AND pt.created_date >= '$start_date'
                GROUP BY e.employee_id, e.name
                ORDER BY tasks_completed DESC, hours_logged DESC
                LIMIT 10
            ");
            $stats['top_performers'] = mysqli_fetch_all($top_performers, MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'project_timeline':
            $project_id = intval($_GET['project_id']);
            
            // Get project tasks with dates for timeline
            $timeline_query = "
                SELECT 
                    id,
                    title,
                    status,
                    priority,
                    created_date as start_date,
                    due_date as end_date,
                    progress,
                    estimated_hours,
                    actual_hours,
                    assigned_to,
                    (SELECT name FROM employees WHERE employee_id = pt.assigned_to) as assignee_name
                FROM project_tasks pt
                WHERE project_id = $project_id
                ORDER BY created_date ASC
            ";
            
            $timeline = mysqli_fetch_all(mysqli_query($conn, $timeline_query), MYSQLI_ASSOC);
            
            // Get dependencies for timeline
            foreach ($timeline as &$task) {
                $deps_query = "
                    SELECT depends_on_task_id, 
                           (SELECT title FROM project_tasks WHERE id = td.depends_on_task_id) as depends_on_title
                    FROM task_dependencies td
                    WHERE task_id = {$task['id']}
                ";
                $task['dependencies'] = mysqli_fetch_all(mysqli_query($conn, $deps_query), MYSQLI_ASSOC);
            }
            
            echo json_encode(['success' => true, 'timeline' => $timeline]);
            break;
            
        case 'team_workload':
            // Get team workload data
            $workload_query = "
                SELECT 
                    e.employee_id,
                    e.name,
                    e.department,
                    COUNT(pt.id) as active_tasks,
                    SUM(CASE WHEN pt.priority = 'urgent' THEN 1 ELSE 0 END) as urgent_tasks,
                    SUM(CASE WHEN pt.due_date < CURDATE() AND pt.status NOT IN ('completed') THEN 1 ELSE 0 END) as overdue_tasks,
                    AVG(pt.progress) as avg_progress,
                    SUM(pt.estimated_hours) as total_estimated_hours,
                    (SELECT SUM(hours) FROM time_logs tl 
                     INNER JOIN project_tasks task_logs ON tl.task_id = task_logs.id 
                     WHERE task_logs.assigned_to = e.employee_id 
                     AND tl.date_logged >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as hours_last_30_days
                FROM employees e
                LEFT JOIN project_tasks pt ON e.employee_id = pt.assigned_to 
                    AND pt.status NOT IN ('completed', 'cancelled')
                LEFT JOIN projects p ON pt.project_id = p.id
                WHERE e.status = 'active' AND (p.status IS NULL OR p.status != 'archived')
                GROUP BY e.employee_id, e.name, e.department
                ORDER BY active_tasks DESC, urgent_tasks DESC
            ";
            
            $workload = mysqli_fetch_all(mysqli_query($conn, $workload_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'workload' => $workload]);
            break;
            
        case 'dashboard_charts':
            // Data for various dashboard charts
            $charts = [];
            
            // Project status distribution
            $status_query = "
                SELECT status, COUNT(*) as count
                FROM projects 
                WHERE status != 'archived'
                GROUP BY status
            ";
            $charts['project_status'] = mysqli_fetch_all(mysqli_query($conn, $status_query), MYSQLI_ASSOC);
            
            // Task priority distribution
            $priority_query = "
                SELECT priority, COUNT(*) as count
                FROM project_tasks pt
                INNER JOIN projects p ON pt.project_id = p.id
                WHERE p.status != 'archived' AND pt.status != 'completed'
                GROUP BY priority
            ";
            $charts['task_priority'] = mysqli_fetch_all(mysqli_query($conn, $priority_query), MYSQLI_ASSOC);
            
            // Monthly project completion trend (last 12 months)
            $completion_query = "
                SELECT 
                    DATE_FORMAT(completed_date, '%Y-%m') as month,
                    COUNT(*) as completed_projects
                FROM projects 
                WHERE status = 'completed' 
                AND completed_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(completed_date, '%Y-%m')
                ORDER BY month ASC
            ";
            $charts['completion_trend'] = mysqli_fetch_all(mysqli_query($conn, $completion_query), MYSQLI_ASSOC);
            
            // Team productivity (tasks completed per month)
            $productivity_query = "
                SELECT 
                    e.name,
                    DATE_FORMAT(pt.completed_date, '%Y-%m') as month,
                    COUNT(*) as tasks_completed
                FROM employees e
                INNER JOIN project_tasks pt ON e.employee_id = pt.assigned_to
                INNER JOIN projects p ON pt.project_id = p.id
                WHERE pt.status = 'completed' 
                AND pt.completed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                AND p.status != 'archived'
                GROUP BY e.employee_id, e.name, DATE_FORMAT(pt.completed_date, '%Y-%m')
                ORDER BY month ASC, tasks_completed DESC
            ";
            $charts['productivity'] = mysqli_fetch_all(mysqli_query($conn, $productivity_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'charts' => $charts]);
            break;
            
        case 'search_projects':
            $search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
            $limit = intval($_GET['limit'] ?? 10);
            
            $search_query = "
                SELECT p.id, p.project_code, p.name, p.status, 
                       CONCAT(e.name) as manager_name
                FROM projects p
                LEFT JOIN employees e ON p.project_manager_id = e.employee_id
                WHERE p.status != 'archived' 
                AND (p.name LIKE '%$search%' OR p.project_code LIKE '%$search%' OR p.description LIKE '%$search%')
                ORDER BY p.name ASC
                LIMIT $limit
            ";
            
            $results = mysqli_fetch_all(mysqli_query($conn, $search_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'search_tasks':
            $search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
            $limit = intval($_GET['limit'] ?? 10);
            
            $search_query = "
                SELECT pt.id, pt.task_code, pt.title, pt.status, pt.priority,
                       p.name as project_name, p.project_code,
                       e.name as assignee_name
                FROM project_tasks pt
                INNER JOIN projects p ON pt.project_id = p.id
                LEFT JOIN employees e ON pt.assigned_to = e.employee_id
                WHERE p.status != 'archived' 
                AND (pt.title LIKE '%$search%' OR pt.task_code LIKE '%$search%' OR pt.description LIKE '%$search%')
                ORDER BY pt.title ASC
                LIMIT $limit
            ";
            
            $results = mysqli_fetch_all(mysqli_query($conn, $search_query), MYSQLI_ASSOC);
            
            echo json_encode(['success' => true, 'results' => $results]);
            break;
            
        case 'update_project_progress':
            $project_id = intval($_POST['project_id']);
            
            // Calculate project progress based on task completion
            $progress_query = "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                FROM project_tasks 
                WHERE project_id = $project_id
            ";
            
            $result = mysqli_query($conn, $progress_query);
            $data = mysqli_fetch_assoc($result);
            
            $progress = $data['total_tasks'] > 0 ? 
                round(($data['completed_tasks'] / $data['total_tasks']) * 100, 2) : 0;
            
            // Update project progress
            $update_query = "UPDATE projects SET progress = $progress WHERE id = $project_id";
            mysqli_query($conn, $update_query);
            
            echo json_encode(['success' => true, 'progress' => $progress]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
