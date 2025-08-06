<?php
/**
 * 🔒 HRMS Data Encryption & Security Module
 * Advanced encryption for passwords, salary, and sensitive information
 */

if (!isset($root_path)) 
require_once '../db.php';

class HRMSEncryption {
    private $encryption_key;
    private $cipher_method = 'AES-256-CBC';
    
    public function __construct() {
        // Generate or load encryption key
        $this->encryption_key = $this->getEncryptionKey();
    }
    
    /**
     * Get or generate encryption key
     */
    private function getEncryptionKey() {
        $key_file = '../config/encryption.key';
        
        if (file_exists($key_file)) {
            return file_get_contents($key_file);
        } else {
            // Generate new key
            $key = random_bytes(32); // 256-bit key
            
            // Create config directory if it doesn't exist
            if (!is_dir('../config')) {
                mkdir('../config', 0755, true);
            }
            
            file_put_contents($key_file, $key);
            chmod($key_file, 0600); // Restrict access
            
            return $key;
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher_method));
        $encrypted = openssl_encrypt($data, $this->cipher_method, $this->encryption_key, 0, $iv);
        
        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return $encrypted_data;
        }
        
        $data = base64_decode($encrypted_data);
        $iv_length = openssl_cipher_iv_length($this->cipher_method);
        
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $this->cipher_method, $this->encryption_key, 0, $iv);
    }
    
    /**
     * Hash password using PHP's password_hash (bcrypt)
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Encrypt salary data
     */
    public function encryptSalary($salary) {
        return $this->encrypt($salary);
    }
    
    /**
     * Decrypt salary data
     */
    public function decryptSalary($encrypted_salary) {
        return $this->decrypt($encrypted_salary);
    }
    
    /**
     * Encrypt personal identification numbers
     */
    public function encryptPII($data) {
        return $this->encrypt($data);
    }
    
    /**
     * Decrypt personal identification numbers
     */
    public function decryptPII($encrypted_data) {
        return $this->decrypt($encrypted_data);
    }
    
    /**
     * Generate secure token for password reset, etc.
     */
    public function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash sensitive data for searching (one-way)
     */
    public function hashForSearch($data) {
        return hash('sha256', $data . $this->encryption_key);
    }
}

class HRMSSecurityManager {
    private $conn;
    private $encryption;
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->encryption = new HRMSEncryption();
    }
    
    /**
     * Secure employee data insertion
     */
    public function insertSecureEmployee($employee_data) {
        // Encrypt sensitive fields
        $encrypted_data = $employee_data;
        
        if (isset($encrypted_data['salary'])) {
            $encrypted_data['salary'] = $this->encryption->encryptSalary($encrypted_data['salary']);
        }
        
        if (isset($encrypted_data['bank_account'])) {
            $encrypted_data['bank_account'] = $this->encryption->encryptPII($encrypted_data['bank_account']);
        }
        
        if (isset($encrypted_data['aadhar_number'])) {
            $encrypted_data['aadhar_number'] = $this->encryption->encryptPII($encrypted_data['aadhar_number']);
        }
        
        if (isset($encrypted_data['pan_number'])) {
            $encrypted_data['pan_number'] = $this->encryption->encryptPII($encrypted_data['pan_number']);
        }
        
        if (isset($encrypted_data['password'])) {
            $encrypted_data['password'] = $this->encryption->hashPassword($encrypted_data['password']);
        }
        
        // Insert into database
        $fields = implode(',', array_keys($encrypted_data));
        $placeholders = str_repeat('?,', count($encrypted_data) - 1) . '?';
        
        $query = "INSERT INTO employees ($fields) VALUES ($placeholders)";
        $stmt = mysqli_prepare($this->conn, $query);
        
        $values = array_values($encrypted_data);
        $types = str_repeat('s', count($values));
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Retrieve and decrypt employee data
     */
    public function getSecureEmployee($employee_id) {
        $query = "SELECT * FROM employees WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Decrypt sensitive fields
            if (!empty($row['salary'])) {
                $row['salary'] = $this->encryption->decryptSalary($row['salary']);
            }
            
            if (!empty($row['bank_account'])) {
                $row['bank_account'] = $this->encryption->decryptPII($row['bank_account']);
            }
            
            if (!empty($row['aadhar_number'])) {
                $row['aadhar_number'] = $this->encryption->decryptPII($row['aadhar_number']);
            }
            
            if (!empty($row['pan_number'])) {
                $row['pan_number'] = $this->encryption->decryptPII($row['pan_number']);
            }
            
            // Never return password hash
            unset($row['password']);
            
            return $row;
        }
        
        return null;
    }
    
    /**
     * Update employee with encryption
     */
    public function updateSecureEmployee($employee_id, $update_data) {
        // Encrypt sensitive fields
        $encrypted_data = $update_data;
        
        if (isset($encrypted_data['salary'])) {
            $encrypted_data['salary'] = $this->encryption->encryptSalary($encrypted_data['salary']);
        }
        
        if (isset($encrypted_data['bank_account'])) {
            $encrypted_data['bank_account'] = $this->encryption->encryptPII($encrypted_data['bank_account']);
        }
        
        if (isset($encrypted_data['aadhar_number'])) {
            $encrypted_data['aadhar_number'] = $this->encryption->encryptPII($encrypted_data['aadhar_number']);
        }
        
        if (isset($encrypted_data['pan_number'])) {
            $encrypted_data['pan_number'] = $this->encryption->encryptPII($encrypted_data['pan_number']);
        }
        
        if (isset($encrypted_data['password'])) {
            $encrypted_data['password'] = $this->encryption->hashPassword($encrypted_data['password']);
        }
        
        // Build update query
        $set_clauses = [];
        $values = [];
        $types = '';
        
        foreach ($encrypted_data as $field => $value) {
            $set_clauses[] = "$field = ?";
            $values[] = $value;
            $types .= 's';
        }
        
        $values[] = $employee_id;
        $types .= 'i';
        
        $query = "UPDATE employees SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Secure payroll data handling
     */
    public function insertSecurePayroll($payroll_data) {
        $encrypted_data = $payroll_data;
        
        // Encrypt all monetary values
        $monetary_fields = ['basic_salary', 'hra', 'da', 'bonus', 'overtime', 'deductions', 'net_salary'];
        
        foreach ($monetary_fields as $field) {
            if (isset($encrypted_data[$field])) {
                $encrypted_data[$field] = $this->encryption->encryptSalary($encrypted_data[$field]);
            }
        }
        
        // Insert encrypted payroll data
        $fields = implode(',', array_keys($encrypted_data));
        $placeholders = str_repeat('?,', count($encrypted_data) - 1) . '?';
        
        $query = "INSERT INTO employee_payroll ($fields) VALUES ($placeholders)";
        $stmt = mysqli_prepare($this->conn, $query);
        
        $values = array_values($encrypted_data);
        $types = str_repeat('s', count($values));
        
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get decrypted payroll data
     */
    public function getSecurePayroll($employee_id, $period = null) {
        $query = "SELECT * FROM employee_payroll WHERE employee_id = ?";
        $params = [$employee_id];
        $types = "i";
        
        if ($period) {
            $query .= " AND pay_period = ?";
            $params[] = $period;
            $types .= "s";
        }
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        $payroll_data = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Decrypt monetary values
            $monetary_fields = ['basic_salary', 'hra', 'da', 'bonus', 'overtime', 'deductions', 'net_salary'];
            
            foreach ($monetary_fields as $field) {
                if (!empty($row[$field])) {
                    $row[$field] = $this->encryption->decryptSalary($row[$field]);
                }
            }
            
            $payroll_data[] = $row;
        }
        
        return $payroll_data;
    }
    
    /**
     * Authenticate user with secure password verification
     */
    public function authenticateUser($username, $password) {
        $query = "SELECT id, password, role FROM users WHERE username = ? OR email = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
        mysqli_stmt_execute($stmt);
        
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            if ($this->encryption->verifyPassword($password, $row['password'])) {
                // Log successful login
                $this->logSecurityEvent('LOGIN_SUCCESS', $row['id'], 'User authenticated successfully');
                
                return [
                    'success' => true,
                    'user_id' => $row['id'],
                    'role' => $row['role']
                ];
            } else {
                // Log failed login
                $this->logSecurityEvent('LOGIN_FAILED', null, "Failed login attempt for: $username");
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event_type, $user_id, $description) {
        $query = "INSERT INTO security_logs (event_type, user_id, description, ip_address, timestamp) 
                  VALUES (?, ?, ?, ?, NOW())";
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "siss", $event_type, $user_id, $description, $ip_address);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken($user_id) {
        $token = $this->encryption->generateSecureToken();
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE token = ?, expires_at = ?";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $token, $expires, $token, $expires);
        mysqli_stmt_execute($stmt);
        
        return $token;
    }
}

// Initialize security manager
$security = new HRMSSecurityManager($conn);

echo "🔒 Security & Encryption System Initialized!\n";
echo "✅ Advanced encryption ready for sensitive data protection.\n";

?>
