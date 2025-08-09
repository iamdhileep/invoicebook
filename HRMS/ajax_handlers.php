<?php
// Include authentication and database connection
$base_dir = dirname(__DIR__);
require $base_dir . '/config.php';
require $base_dir . '/auth_check.php';

header('Content-Type: application/json');

// Get the posted data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        
        case 'add_fnf_settlement':
            if (!$_POST['employee_id'] || !$_POST['last_working_day']) {
                throw new Exception('Employee ID and last working day are required');
            }

            // Calculate notice period details
            $last_working_day = $_POST['last_working_day'];
            $resignation_date = $_POST['resignation_date'] ?: $last_working_day;
            $notice_period_days = $_POST['notice_period_days'] ?: 30;

            $stmt = $conn->prepare("
                INSERT INTO fnf_settlements (
                    employee_id, employee_name, employee_code, department_name,
                    basic_salary, resignation_date, last_working_day, notice_period_days,
                    status, initiated_by, initiated_date, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'initiated', ?, NOW(), ?)
            ");
            
            $stmt->bind_param('isssdssiis',
                $_POST['employee_id'],
                $_POST['employee_name'],
                $_POST['employee_code'],
                $_POST['department_name'],
                $_POST['basic_salary'],
                $resignation_date,
                $last_working_day,
                $notice_period_days,
                $_SESSION['user_id'],
                $_POST['remarks']
            );

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'FNF settlement initiated successfully']);
            } else {
                throw new Exception('Failed to initiate FNF settlement');
            }
            break;

        case 'calculate_settlement':
            if (!$_POST['id']) {
                throw new Exception('Settlement ID is required');
            }

            $id = $_POST['id'];
            $pending_salary = $_POST['pending_salary'] ?: 0;
            $leave_encashment = $_POST['leave_encashment'] ?: 0;
            $bonus_amount = $_POST['bonus_amount'] ?: 0;
            $gratuity_amount = $_POST['gratuity_amount'] ?: 0;
            $other_benefits = $_POST['other_benefits'] ?: 0;
            $loan_deductions = $_POST['loan_deductions'] ?: 0;
            $advance_deductions = $_POST['advance_deductions'] ?: 0;
            $other_deductions = $_POST['other_deductions'] ?: 0;

            // Calculate totals
            $gross_settlement = $pending_salary + $leave_encashment + $bonus_amount + $gratuity_amount + $other_benefits;
            $total_deductions = $loan_deductions + $advance_deductions + $other_deductions;
            $net_settlement = $gross_settlement - $total_deductions;

            $stmt = $conn->prepare("
                UPDATE fnf_settlements SET
                    pending_salary = ?, leave_encashment = ?, bonus_amount = ?, 
                    gratuity_amount = ?, other_benefits = ?, loan_deductions = ?,
                    advance_deductions = ?, other_deductions = ?, 
                    gross_settlement = ?, net_settlement = ?, status = 'calculated'
                WHERE id = ?
            ");

            $stmt->bind_param('ddddddddddi',
                $pending_salary, $leave_encashment, $bonus_amount,
                $gratuity_amount, $other_benefits, $loan_deductions,
                $advance_deductions, $other_deductions,
                $gross_settlement, $net_settlement, $id
            );

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Settlement calculated successfully']);
            } else {
                throw new Exception('Failed to calculate settlement');
            }
            break;

        case 'approve_settlement':
            if (!$_POST['id'] || !$_POST['status']) {
                throw new Exception('Settlement ID and status are required');
            }

            $stmt = $conn->prepare("
                UPDATE fnf_settlements SET
                    status = ?, approved_by = ?, approved_date = NOW(), remarks = ?
                WHERE id = ?
            ");

            $stmt->bind_param('sisi',
                $_POST['status'],
                $_SESSION['user_id'],
                $_POST['remarks'],
                $_POST['id']
            );

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Settlement status updated successfully']);
            } else {
                throw new Exception('Failed to update settlement status');
            }
            break;

        case 'mark_payment':
            if (!$_POST['id'] || !$_POST['payment_date']) {
                throw new Exception('Settlement ID and payment date are required');
            }

            $stmt = $conn->prepare("
                UPDATE fnf_settlements SET
                    payment_date = ?, payment_remarks = ?, status = 'completed'
                WHERE id = ?
            ");

            $stmt->bind_param('ssi',
                $_POST['payment_date'],
                $_POST['payment_remarks'],
                $_POST['id']
            );

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Payment marked successfully']);
            } else {
                throw new Exception('Failed to mark payment');
            }
            break;

        case 'get_settlement':
            if (!$_POST['id']) {
                throw new Exception('Settlement ID is required');
            }

            $stmt = $conn->prepare("SELECT * FROM fnf_settlements WHERE id = ?");
            $stmt->bind_param('i', $_POST['id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($settlement = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'data' => $settlement]);
            } else {
                throw new Exception('Settlement not found');
            }
            break;

        case 'schedule_exit_interview':
            if (!$_POST['settlement_id'] || !$_POST['interview_date'] || !$_POST['interviewer_name']) {
                throw new Exception('Settlement ID, interview date, and interviewer name are required');
            }

            $stmt = $conn->prepare("
                INSERT INTO exit_interviews (
                    settlement_id, interview_date, interviewer_name, status, created_date
                ) VALUES (?, ?, ?, 'scheduled', NOW())
            ");

            $stmt->bind_param('iss',
                $_POST['settlement_id'],
                $_POST['interview_date'],
                $_POST['interviewer_name']
            );

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Exit interview scheduled successfully']);
            } else {
                throw new Exception('Failed to schedule exit interview');
            }
            break;

        case 'create_clearance_checklist':
            if (!$_POST['settlement_id']) {
                throw new Exception('Settlement ID is required');
            }

            $settlement_id = $_POST['settlement_id'];

            // Get settlement details
            $stmt = $conn->prepare("SELECT employee_name, department_name FROM fnf_settlements WHERE id = ?");
            $stmt->bind_param('i', $settlement_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $settlement = $result->fetch_assoc();

            if (!$settlement) {
                throw new Exception('Settlement not found');
            }

            // Predefined clearance steps
            $clearance_steps = [
                'HR - Return ID card and office keys',
                'HR - Complete exit interview',
                'IT - Return laptop and accessories',
                'IT - Account deactivation',
                'Finance - Clear pending dues',
                'Finance - Return company credit card',
                'Admin - Return office supplies',
                'Admin - Update employee records',
                'Manager - Project handover',
                'Manager - Final approval'
            ];

            foreach ($clearance_steps as $step_description) {
                $stmt = $conn->prepare("
                    INSERT INTO clearance_steps (
                        settlement_id, employee_name, department_name, 
                        step_description, status, created_date
                    ) VALUES (?, ?, ?, ?, 'pending', NOW())
                ");

                $stmt->bind_param('isss',
                    $settlement_id,
                    $settlement['employee_name'],
                    $settlement['department_name'],
                    $step_description
                );

                $stmt->execute();
            }

            echo json_encode(['success' => true, 'message' => 'Clearance checklist created successfully']);
            break;

        case 'delete_settlement':
            if (!$_POST['id']) {
                throw new Exception('Settlement ID is required');
            }

            // Delete related records first
            $conn->query("DELETE FROM clearance_steps WHERE settlement_id = " . intval($_POST['id']));
            $conn->query("DELETE FROM exit_interviews WHERE settlement_id = " . intval($_POST['id']));

            $stmt = $conn->prepare("DELETE FROM fnf_settlements WHERE id = ?");
            $stmt->bind_param('i', $_POST['id']);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'FNF settlement deleted successfully']);
            } else {
                throw new Exception('Failed to delete settlement');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
