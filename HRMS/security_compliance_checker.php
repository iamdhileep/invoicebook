<?php
/**
 * 🔐 HRMS Security & Compliance Audit System
 * Comprehensive analysis of security measures and regulatory compliance
 */

require_once '../db.php';

echo "🔐 HRMS SECURITY & COMPLIANCE AUDIT SYSTEM\n";
echo "==========================================\n\n";

$timestamp = date('Y-m-d H:i:s');
echo "📅 Starting security audit at: $timestamp\n\n";

// Initialize audit results
$audit_results = [
    'role_based_access' => ['score' => 0, 'issues' => []],
    'encryption' => ['score' => 0, 'issues' => []],
    'audit_logs' => ['score' => 0, 'issues' => []],
    'indian_compliance' => ['score' => 0, 'issues' => []],
    'gdpr_compliance' => ['score' => 0, 'issues' => []]
];

// 1. ROLE-BASED ACCESS CONTROL ANALYSIS
echo "🔐 1. ROLE-BASED ACCESS CONTROL ANALYSIS\n";
echo str_repeat("-", 50) . "\n";

// Check for role management tables
$rbac_tables = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions'];
$existing_rbac_tables = [];

foreach ($rbac_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        $existing_rbac_tables[] = $table;
        echo "   ✅ Table '$table': Exists\n";
    } else {
        echo "   ❌ Table '$table': Missing\n";
        $audit_results['role_based_access']['issues'][] = "Missing RBAC table: $table";
    }
}

// Check employee table for role column
$employee_structure = mysqli_query($conn, "DESCRIBE employees");
$has_role_column = false;
$has_department_column = false;

if ($employee_structure) {
    while ($row = mysqli_fetch_assoc($employee_structure)) {
        if (in_array($row['Field'], ['role', 'user_role', 'position', 'designation'])) {
            $has_role_column = true;
            echo "   ✅ Role field found: " . $row['Field'] . "\n";
        }
        if ($row['Field'] === 'department_id' || $row['Field'] === 'department') {
            $has_department_column = true;
            echo "   ✅ Department field found: " . $row['Field'] . "\n";
        }
    }
}

if (!$has_role_column) {
    echo "   ❌ No role/position field in employees table\n";
    $audit_results['role_based_access']['issues'][] = "Missing role/position field in employees table";
}

// Check for authentication files
$auth_files = ['authenticate.php', 'auth_check.php', 'auth_guard.php'];
$auth_files_found = [];

foreach ($auth_files as $file) {
    if (file_exists("../$file") || file_exists($file)) {
        $auth_files_found[] = $file;
        echo "   ✅ Authentication file: $file found\n";
    } else {
        echo "   ❌ Authentication file: $file missing\n";
        $audit_results['role_based_access']['issues'][] = "Missing authentication file: $file";
    }
}

// Scan PHP files for role-based access patterns
$hrms_files = glob("*.php");
$files_with_rbac = [];

foreach ($hrms_files as $file) {
    $content = file_get_contents($file);
    if (preg_match('/(role|permission|auth|access|admin|manager|employee)/i', $content)) {
        $files_with_rbac[] = $file;
    }
}

echo "   📁 Files with RBAC patterns: " . count($files_with_rbac) . "\n";

// Calculate RBAC score
$rbac_score = 0;
$rbac_score += count($existing_rbac_tables) * 15; // 15 points per RBAC table
$rbac_score += $has_role_column ? 10 : 0;
$rbac_score += $has_department_column ? 5 : 0;
$rbac_score += count($auth_files_found) * 5; // 5 points per auth file
$audit_results['role_based_access']['score'] = min($rbac_score, 100);

echo "   📊 RBAC Score: " . $audit_results['role_based_access']['score'] . "/100\n\n";

// 2. ENCRYPTION ANALYSIS
echo "🔒 2. ENCRYPTION & DATA PROTECTION ANALYSIS\n";
echo str_repeat("-", 50) . "\n";

// Check for password hashing
$password_security_score = 0;
$encryption_patterns = [
    'password_hash' => 'Modern PHP password hashing',
    'password_verify' => 'Secure password verification',
    'md5' => 'Weak MD5 hashing (SECURITY RISK)',
    'sha1' => 'Weak SHA1 hashing (SECURITY RISK)', 
    'bcrypt' => 'Strong bcrypt hashing',
    'ENCRYPTION' => 'Data encryption functions',
    'openssl_encrypt' => 'OpenSSL encryption',
    'mcrypt' => 'Deprecated mcrypt (SECURITY RISK)'
];

$security_findings = [];
foreach ($hrms_files as $file) {
    $content = file_get_contents($file);
    foreach ($encryption_patterns as $pattern => $description) {
        if (stripos($content, $pattern) !== false) {
            $security_findings[$pattern][] = $file;
        }
    }
}

foreach ($encryption_patterns as $pattern => $description) {
    if (isset($security_findings[$pattern])) {
        $count = count($security_findings[$pattern]);
        if (in_array($pattern, ['password_hash', 'password_verify', 'bcrypt', 'openssl_encrypt'])) {
            echo "   ✅ $description: Found in $count files\n";
            $password_security_score += $count * 10;
        } else {
            echo "   ⚠️ $description: Found in $count files\n";
            if (in_array($pattern, ['md5', 'sha1', 'mcrypt'])) {
                $audit_results['encryption']['issues'][] = "$description found in " . implode(', ', $security_findings[$pattern]);
            }
        }
    } else {
        if (in_array($pattern, ['password_hash', 'password_verify'])) {
            echo "   ❌ $description: Not found\n";
            $audit_results['encryption']['issues'][] = "Missing $description implementation";
        }
    }
}

// Check database for encrypted fields
$sensitive_tables = ['employees', 'employee_payroll', 'salary_grades'];
$encrypted_fields_score = 0;

foreach ($sensitive_tables as $table) {
    $result = mysqli_query($conn, "DESCRIBE $table");
    if ($result) {
        echo "   🔍 Checking $table for sensitive data protection:\n";
        while ($row = mysqli_fetch_assoc($result)) {
            $field = $row['Field'];
            if (in_array($field, ['salary', 'bank_account', 'pan_number', 'aadhar_number', 'password'])) {
                // Check if field might be encrypted (longer than expected values)
                $sample_query = mysqli_query($conn, "SELECT $field FROM $table LIMIT 1");
                if ($sample_query && mysqli_num_rows($sample_query) > 0) {
                    $sample_data = mysqli_fetch_assoc($sample_query);
                    $value = $sample_data[$field];
                    if (strlen($value) > 50 || preg_match('/^[a-f0-9]{32,}$/i', $value)) {
                        echo "      ✅ $field: Possibly encrypted\n";
                        $encrypted_fields_score += 10;
                    } else {
                        echo "      ⚠️ $field: Appears unencrypted\n";
                        $audit_results['encryption']['issues'][] = "Sensitive field '$field' in '$table' may be unencrypted";
                    }
                }
            }
        }
    }
}

$audit_results['encryption']['score'] = min($password_security_score + $encrypted_fields_score, 100);
echo "   📊 Encryption Score: " . $audit_results['encryption']['score'] . "/100\n\n";

// 3. AUDIT LOGS ANALYSIS
echo "📝 3. AUDIT LOGS & TRANSACTION TRACKING\n";
echo str_repeat("-", 50) . "\n";

// Check for audit/log tables
$audit_tables = ['audit_logs', 'user_logs', 'activity_logs', 'system_logs', 'transaction_logs'];
$existing_audit_tables = [];

foreach ($audit_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        $existing_audit_tables[] = $table;
        echo "   ✅ Audit table '$table': Exists\n";
        
        // Check for recent activity
        $count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
        if ($count_query) {
            $count_row = mysqli_fetch_assoc($count_query);
            echo "      📊 Records: " . $count_row['count'] . "\n";
        }
    } else {
        echo "   ❌ Audit table '$table': Missing\n";
    }
}

if (empty($existing_audit_tables)) {
    $audit_results['audit_logs']['issues'][] = "No audit logging tables found";
}

// Check for logging patterns in code
$logging_patterns = ['audit', 'log_activity', 'track_changes', 'history', 'log_user_action'];
$files_with_logging = [];

foreach ($hrms_files as $file) {
    $content = file_get_contents($file);
    foreach ($logging_patterns as $pattern) {
        if (stripos($content, $pattern) !== false) {
            $files_with_logging[$pattern][] = $file;
        }
    }
}

foreach ($logging_patterns as $pattern) {
    if (isset($files_with_logging[$pattern])) {
        $count = count($files_with_logging[$pattern]);
        echo "   ✅ $pattern: Found in $count files\n";
    } else {
        echo "   ❌ $pattern: Not implemented\n";
        $audit_results['audit_logs']['issues'][] = "Missing $pattern implementation";
    }
}

$audit_score = count($existing_audit_tables) * 25 + count($files_with_logging) * 10;
$audit_results['audit_logs']['score'] = min($audit_score, 100);
echo "   📊 Audit Logs Score: " . $audit_results['audit_logs']['score'] . "/100\n\n";

// 4. INDIAN LABOR LAW COMPLIANCE
echo "🇮🇳 4. INDIAN LABOR LAW COMPLIANCE ANALYSIS\n";
echo str_repeat("-", 50) . "\n";

// Check for Indian compliance fields and tables
$indian_compliance_elements = [
    // PF (Provident Fund)
    'pf' => ['pf_number', 'pf_amount', 'employee_pf', 'employer_pf'],
    // ESI (Employee State Insurance)
    'esi' => ['esi_number', 'esi_amount', 'employee_esi', 'employer_esi'],
    // Gratuity
    'gratuity' => ['gratuity_amount', 'gratuity_eligible', 'years_of_service'],
    // Professional Tax
    'professional_tax' => ['professional_tax', 'pt_amount'],
    // TDS (Tax Deducted at Source)
    'tds' => ['tds_amount', 'tax_deduction', 'income_tax'],
    // Leave as per Indian law
    'leave_compliance' => ['earned_leave', 'casual_leave', 'sick_leave', 'maternity_leave', 'paternity_leave']
];

$compliance_score = 0;
foreach ($indian_compliance_elements as $category => $fields) {
    echo "   🔍 Checking $category compliance:\n";
    $category_found = false;
    
    foreach ($sensitive_tables as $table) {
        $result = mysqli_query($conn, "DESCRIBE $table");
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                foreach ($fields as $field) {
                    if (stripos($row['Field'], $field) !== false) {
                        echo "      ✅ Found: " . $row['Field'] . " in $table\n";
                        $category_found = true;
                        $compliance_score += 5;
                    }
                }
            }
        }
    }
    
    if (!$category_found) {
        echo "      ❌ No $category fields found\n";
        $audit_results['indian_compliance']['issues'][] = "Missing $category compliance fields";
    }
}

// Check for Indian compliance calculation files
$compliance_files = ['payroll_processing.php', 'salary_calculation.php', 'tax_calculation.php'];
foreach ($compliance_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ Compliance file: $file exists\n";
        $compliance_score += 10;
    } else {
        echo "   ❌ Compliance file: $file missing\n";
        $audit_results['indian_compliance']['issues'][] = "Missing compliance file: $file";
    }
}

$audit_results['indian_compliance']['score'] = min($compliance_score, 100);
echo "   📊 Indian Compliance Score: " . $audit_results['indian_compliance']['score'] . "/100\n\n";

// 5. GDPR COMPLIANCE ANALYSIS
echo "🌍 5. GDPR DATA PRIVACY COMPLIANCE\n";
echo str_repeat("-", 50) . "\n";

// Check for GDPR compliance features
$gdpr_features = [
    'data_consent' => ['consent', 'data_processing_consent', 'privacy_policy'],
    'data_portability' => ['export_data', 'download_data', 'data_export'],
    'right_to_erasure' => ['delete_user', 'remove_data', 'data_deletion'],
    'data_protection' => ['data_protection', 'privacy_settings', 'data_retention'],
    'breach_notification' => ['security_breach', 'data_breach', 'incident_log']
];

$gdpr_score = 0;
foreach ($gdpr_features as $feature => $patterns) {
    echo "   🔍 Checking $feature:\n";
    $feature_found = false;
    
    foreach ($hrms_files as $file) {
        $content = file_get_contents($file);
        foreach ($patterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                echo "      ✅ Found: $pattern in $file\n";
                $feature_found = true;
                $gdpr_score += 5;
                break;
            }
        }
    }
    
    if (!$feature_found) {
        echo "      ❌ No $feature implementation found\n";
        $audit_results['gdpr_compliance']['issues'][] = "Missing $feature implementation";
    }
}

// Check for privacy policy and terms
$privacy_files = ['privacy_policy.php', 'terms_conditions.php', 'data_policy.php'];
foreach ($privacy_files as $file) {
    if (file_exists($file) || file_exists("../$file")) {
        echo "   ✅ Privacy file: $file exists\n";
        $gdpr_score += 10;
    } else {
        echo "   ❌ Privacy file: $file missing\n";
        $audit_results['gdpr_compliance']['issues'][] = "Missing privacy file: $file";
    }
}

$audit_results['gdpr_compliance']['score'] = min($gdpr_score, 100);
echo "   📊 GDPR Compliance Score: " . $audit_results['gdpr_compliance']['score'] . "/100\n\n";

// COMPREHENSIVE SECURITY ASSESSMENT
echo "🎯 COMPREHENSIVE SECURITY & COMPLIANCE ASSESSMENT\n";
echo str_repeat("=", 60) . "\n";

$overall_score = (
    $audit_results['role_based_access']['score'] +
    $audit_results['encryption']['score'] +
    $audit_results['audit_logs']['score'] +
    $audit_results['indian_compliance']['score'] +
    $audit_results['gdpr_compliance']['score']
) / 5;

echo "📊 SECURITY SCORES:\n";
echo "   🔐 Role-Based Access Control: " . $audit_results['role_based_access']['score'] . "/100\n";
echo "   🔒 Encryption & Data Protection: " . $audit_results['encryption']['score'] . "/100\n";
echo "   📝 Audit Logs & Tracking: " . $audit_results['audit_logs']['score'] . "/100\n";
echo "   🇮🇳 Indian Labor Law Compliance: " . $audit_results['indian_compliance']['score'] . "/100\n";
echo "   🌍 GDPR Data Privacy: " . $audit_results['gdpr_compliance']['score'] . "/100\n";
echo "\n   🎯 OVERALL SECURITY SCORE: " . round($overall_score, 1) . "/100\n\n";

// Security Status Assessment
if ($overall_score >= 90) {
    echo "🏆 SECURITY STATUS: EXCELLENT\n";
    echo "✅ Your HRMS has robust security and compliance measures.\n";
} elseif ($overall_score >= 75) {
    echo "🎯 SECURITY STATUS: GOOD\n";
    echo "✅ Your HRMS has solid security with some areas for improvement.\n";
} elseif ($overall_score >= 60) {
    echo "⚖️ SECURITY STATUS: MODERATE\n";
    echo "⚠️ Your HRMS needs significant security enhancements.\n";
} elseif ($overall_score >= 40) {
    echo "⚠️ SECURITY STATUS: WEAK\n";
    echo "🔧 Your HRMS requires immediate security improvements.\n";
} else {
    echo "🚨 SECURITY STATUS: CRITICAL\n";
    echo "🔥 Your HRMS has serious security vulnerabilities requiring urgent attention.\n";
}

// DETAILED RECOMMENDATIONS
echo "\n💡 DETAILED SECURITY RECOMMENDATIONS:\n";
echo str_repeat("=", 60) . "\n";

// RBAC Recommendations
if ($audit_results['role_based_access']['score'] < 80) {
    echo "🔐 ROLE-BASED ACCESS CONTROL IMPROVEMENTS:\n";
    foreach ($audit_results['role_based_access']['issues'] as $issue) {
        echo "   • $issue\n";
    }
    echo "   📋 Recommended Actions:\n";
    echo "      - Implement user roles table (Admin, Manager, Employee)\n";
    echo "      - Create permission-based access system\n";
    echo "      - Add role-based menu restrictions\n";
    echo "      - Implement session-based authentication\n\n";
}

// Encryption Recommendations  
if ($audit_results['encryption']['score'] < 80) {
    echo "🔒 ENCRYPTION & DATA PROTECTION IMPROVEMENTS:\n";
    foreach ($audit_results['encryption']['issues'] as $issue) {
        echo "   • $issue\n";
    }
    echo "   📋 Recommended Actions:\n";
    echo "      - Implement password_hash() for all passwords\n";
    echo "      - Encrypt sensitive salary and personal data\n";
    echo "      - Use HTTPS for all data transmission\n";
    echo "      - Implement database field-level encryption\n\n";
}

// Audit Logs Recommendations
if ($audit_results['audit_logs']['score'] < 80) {
    echo "📝 AUDIT LOGS & TRACKING IMPROVEMENTS:\n";
    foreach ($audit_results['audit_logs']['issues'] as $issue) {
        echo "   • $issue\n";
    }
    echo "   📋 Recommended Actions:\n";
    echo "      - Create comprehensive audit_logs table\n";
    echo "      - Log all CRUD operations on sensitive data\n";
    echo "      - Track user login/logout activities\n";
    echo "      - Implement change tracking for payroll\n\n";
}

// Indian Compliance Recommendations
if ($audit_results['indian_compliance']['score'] < 80) {
    echo "🇮🇳 INDIAN LABOR LAW COMPLIANCE IMPROVEMENTS:\n";
    foreach ($audit_results['indian_compliance']['issues'] as $issue) {
        echo "   • $issue\n";
    }
    echo "   📋 Recommended Actions:\n";
    echo "      - Add PF calculation and tracking\n";
    echo "      - Implement ESI compliance features\n";
    echo "      - Include gratuity calculation\n";
    echo "      - Add professional tax and TDS handling\n";
    echo "      - Implement Indian leave policies\n\n";
}

// GDPR Recommendations
if ($audit_results['gdpr_compliance']['score'] < 80) {
    echo "🌍 GDPR DATA PRIVACY IMPROVEMENTS:\n";
    foreach ($audit_results['gdpr_compliance']['issues'] as $issue) {
        echo "   • $issue\n";
    }
    echo "   📋 Recommended Actions:\n";
    echo "      - Implement data consent mechanisms\n";
    echo "      - Add data export/portability features\n";
    echo "      - Create data deletion/erasure options\n";
    echo "      - Implement privacy policy and terms\n";
    echo "      - Add data breach notification system\n\n";
}

// PRIORITY ACTION ITEMS
echo "🎯 PRIORITY ACTION ITEMS:\n";
echo str_repeat("=", 60) . "\n";

$all_issues = array_merge(
    $audit_results['role_based_access']['issues'],
    $audit_results['encryption']['issues'],
    $audit_results['audit_logs']['issues'],
    $audit_results['indian_compliance']['issues'],
    $audit_results['gdpr_compliance']['issues']
);

if (count($all_issues) > 0) {
    echo "🔥 CRITICAL SECURITY ISSUES TO ADDRESS:\n";
    $priority = 1;
    foreach ($all_issues as $issue) {
        echo "   $priority. $issue\n";
        $priority++;
        if ($priority > 10) break; // Show top 10 priority issues
    }
} else {
    echo "✅ No critical security issues found!\n";
    echo "🎉 Your HRMS system has excellent security compliance.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔐 SECURITY & COMPLIANCE AUDIT COMPLETE!\n";
echo "📊 Generated comprehensive security assessment report.\n";
echo str_repeat("=", 60) . "\n";

// Generate JSON report for programmatic use
$json_report = [
    'audit_timestamp' => $timestamp,
    'overall_score' => round($overall_score, 1),
    'category_scores' => $audit_results,
    'total_issues' => count($all_issues),
    'security_status' => $overall_score >= 90 ? 'EXCELLENT' : 
                        ($overall_score >= 75 ? 'GOOD' : 
                        ($overall_score >= 60 ? 'MODERATE' : 
                        ($overall_score >= 40 ? 'WEAK' : 'CRITICAL')))
];

file_put_contents('security_audit_report.json', json_encode($json_report, JSON_PRETTY_PRINT));
echo "\n📄 Detailed JSON report saved to: security_audit_report.json\n";

?>
