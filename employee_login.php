<?php
session_start();
include 'db.php';

// Redirect if already logged in as employee
if (isset($_SESSION['employee_id'])) {
    header("Location: HRMS/employee_self_service.php");
    exit;
}

// Redirect if admin is logged in
if (isset($_SESSION['admin'])) {
    header("Location: HRMS/employee_self_service.php?emp_id=1"); // Demo with first employee
    exit;
}

$error = '';
$success_message = '';

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success_message = 'Registration successful! You can now login.';
}

// Check for logout success
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success_message = 'You have been successfully logged out.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $employee_id = trim($_POST['employee_id']);
    $password = $_POST['password'];

    if (empty($employee_id) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Create hr_employees table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS hr_employees (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_id VARCHAR(50) UNIQUE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            phone VARCHAR(20),
            position VARCHAR(100),
            department VARCHAR(100),
            department_id INT,
            date_of_joining DATE,
            salary DECIMAL(10,2),
            status ENUM('active', 'inactive') DEFAULT 'active',
            password VARCHAR(255),
            address TEXT,
            emergency_contact VARCHAR(100),
            emergency_phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Check if employee exists
        $stmt = $conn->prepare("SELECT id, employee_id, first_name, last_name, email, password, status FROM hr_employees WHERE employee_id = ? AND status = 'active'");
        if ($stmt) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $employee = $result->fetch_assoc();
                
                // Check password (if set) or allow default login for demo
                if (empty($employee['password'])) {
                    // First time login - set password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE hr_employees SET password = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $employee['id']);
                    $update_stmt->execute();
                    
                    // Login successful
                    $_SESSION['employee_id'] = $employee['id'];
                    $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                    $_SESSION['employee_email'] = $employee['email'];
                    
                    header("Location: HRMS/employee_self_service.php");
                    exit;
                } elseif (password_verify($password, $employee['password'])) {
                    // Login successful
                    $_SESSION['employee_id'] = $employee['id'];
                    $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                    $_SESSION['employee_email'] = $employee['email'];
                    
                    header("Location: HRMS/employee_self_service.php");
                    exit;
                } else {
                    $error = "Invalid employee ID or password.";
                }
            } else {
                $error = "Invalid employee ID or password.";
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again.";
        }
    }
}

// Handle demo data creation
if (isset($_POST['create_demo'])) {
    // Insert sample employees if none exist
    $check = $conn->query("SELECT COUNT(*) as count FROM hr_employees");
    if ($check && $check->fetch_assoc()['count'] == 0) {
        $conn->query("INSERT INTO hr_employees (employee_id, first_name, last_name, email, position, department, date_of_joining, salary, status) VALUES
            ('EMP001', 'John', 'Doe', 'john.doe@company.com', 'Software Developer', 'IT', '2024-01-15', 75000, 'active'),
            ('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', 'HR Manager', 'Human Resources', '2023-06-10', 68000, 'active'),
            ('EMP003', 'Mike', 'Johnson', 'mike.johnson@company.com', 'Sales Executive', 'Sales', '2024-03-01', 52000, 'active'),
            ('EMP004', 'Sarah', 'Wilson', 'sarah.wilson@company.com', 'Marketing Specialist', 'Marketing', '2023-09-15', 58000, 'active'),
            ('EMP005', 'David', 'Brown', 'david.brown@company.com', 'DevOps Engineer', 'IT', '2024-02-20', 78000, 'active')");
        $success_message = 'Demo employees created! Use Employee ID: EMP001, EMP002, EMP003, EMP004, or EMP005 with any password for first login.';
    } else {
        $success_message = 'Demo employees already exist. Use Employee ID: EMP001-EMP005 with any password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - HRMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-section i {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .form-control {
            border-radius: 15px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .input-group-text {
            border-radius: 15px 0 0 15px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: transparent;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 15px 15px 0;
        }

        .alert {
            border-radius: 15px;
            border: none;
        }

        .demo-section {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: center;
        }

        .admin-login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="login-container">
                    <div class="logo-section">
                        <i class="fas fa-users-cog"></i>
                        <h2 class="fw-bold text-dark">Employee Portal</h2>
                        <p class="text-muted">Access your self-service dashboard</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-id-badge text-primary"></i>
                                </span>
                                <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                       placeholder="Enter your Employee ID" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>" required>
                            </div>
                            <small class="form-text text-muted">e.g., EMP001, EMP002, etc.</small>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                            </div>
                            <small class="form-text text-muted">First time? Use any password to set up your account</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="login" class="btn btn-login btn-primary text-white">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Portal
                            </button>
                        </div>
                    </form>

                    <div class="demo-section">
                        <h6 class="text-primary">🚀 Demo Setup</h6>
                        <p class="small mb-3">Create sample employee accounts for testing</p>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="create_demo" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-magic me-1"></i>Create Demo Employees
                            </button>
                        </form>
                        <div class="mt-2">
                            <small class="text-muted">
                                <strong>Demo IDs:</strong> EMP001, EMP002, EMP003, EMP004, EMP005<br>
                                Use any password for first-time login
                            </small>
                        </div>
                    </div>

                    <div class="admin-login-link">
                        <p class="text-muted small">Administrator?</p>
                        <a href="login.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-user-shield me-1"></i>Admin Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto focus on employee ID field
        document.getElementById('employee_id').focus();

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const employeeId = document.getElementById('employee_id').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!employeeId || !password) {
                e.preventDefault();
                alert('Please fill in both Employee ID and Password');
                return false;
            }

            if (employeeId.length < 3) {
                e.preventDefault();
                alert('Employee ID must be at least 3 characters long');
                return false;
            }
        });
    </script>
</body>
</html>
