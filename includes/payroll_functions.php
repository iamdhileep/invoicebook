<?php
// Payroll Helper Functions

function calculateSalaryComponents($monthly_salary, $payroll_settings = null) {
    if (!$payroll_settings) {
        // Default percentages
        $basic_percentage = 60;
        $hra_percentage = 20;
        $da_percentage = 10;
        $allowances_percentage = 10;
        $pf_rate = 12;
        $esi_rate = 1.75;
    } else {
        $basic_percentage = $payroll_settings["basic_salary_percentage"] ?? 60;
        $hra_percentage = $payroll_settings["hra_percentage"] ?? 20;
        $da_percentage = $payroll_settings["da_percentage"] ?? 10;
        $allowances_percentage = $payroll_settings["allowances_percentage"] ?? 10;
        $pf_rate = $payroll_settings["pf_rate"] ?? 12;
        $esi_rate = $payroll_settings["esi_rate"] ?? 1.75;
    }
    
    $basic_salary = $monthly_salary * ($basic_percentage / 100);
    $hra = $monthly_salary * ($hra_percentage / 100);
    $da = $monthly_salary * ($da_percentage / 100);
    $allowances = $monthly_salary * ($allowances_percentage / 100);
    
    $gross_salary = $basic_salary + $hra + $da + $allowances;
    
    $pf_deduction = $basic_salary * ($pf_rate / 100);
    $esi_deduction = $gross_salary * ($esi_rate / 100);
    $professional_tax = $monthly_salary > 10000 ? 200 : 0;
    
    $total_deductions = $pf_deduction + $esi_deduction + $professional_tax;
    $net_salary = $gross_salary - $total_deductions;
    
    return [
        "basic_salary" => round($basic_salary, 2),
        "hra" => round($hra, 2),
        "da" => round($da, 2),
        "allowances" => round($allowances, 2),
        "gross_salary" => round($gross_salary, 2),
        "pf_deduction" => round($pf_deduction, 2),
        "esi_deduction" => round($esi_deduction, 2),
        "professional_tax" => round($professional_tax, 2),
        "total_deductions" => round($total_deductions, 2),
        "net_salary" => round($net_salary, 2)
    ];
}

function generatePayslip($conn, $employee_id, $month) {
    // Get employee details
    $emp_query = "SELECT * FROM employees WHERE employee_id = ? AND status = \"active\"";
    $stmt = $conn->prepare($emp_query);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();
    
    if (!$employee) {
        return ["success" => false, "message" => "Employee not found"];
    }
    
    // Check if payslip already exists
    $check_query = "SELECT payslip_id FROM payslips WHERE employee_id = ? AND pay_period = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("is", $employee_id, $month);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        return ["success" => false, "message" => "Payslip already exists for this period"];
    }
    
    // Calculate salary components
    $components = calculateSalaryComponents($employee["monthly_salary"]);
    
    // Insert payslip
    $insert_query = "INSERT INTO payslips 
        (employee_id, employee_code, employee_name, department, position, pay_period, pay_date,
         basic_salary, hra, da, allowances, gross_salary, pf_deduction, esi_deduction,
         professional_tax, total_deductions, net_salary, generated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_query);
    $pay_date = date("Y-m-t", strtotime($month . "-01"));
    $generated_by = $_SESSION["admin"] ?? $_SESSION["user_id"] ?? 1;
    
    $stmt->bind_param("issssssddddddddddi",
        $employee["employee_id"], $employee["employee_code"], $employee["name"],
        $employee["department_name"], $employee["position"], $month, $pay_date,
        $components["basic_salary"], $components["hra"], $components["da"], $components["allowances"],
        $components["gross_salary"], $components["pf_deduction"], $components["esi_deduction"],
        $components["professional_tax"], $components["total_deductions"], $components["net_salary"],
        $generated_by
    );
    
    if ($stmt->execute()) {
        return ["success" => true, "payslip_id" => $conn->insert_id, "message" => "Payslip generated successfully"];
    } else {
        return ["success" => false, "message" => "Error generating payslip: " . $conn->error];
    }
}
?>