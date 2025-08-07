<?php
// Prevent direct access
if (!defined('HRMS_ACCESS')) {
    define('HRMS_ACCESS', true);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'HRMS System' ?></title>
    
    <!-- Bootstrap 5.3.2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom HRMS Styles -->
    <style>
        :root {
            --hrms-primary: #2c3e50;
            --hrms-secondary: #34495e;
            --hrms-accent: #3498db;
            --hrms-success: #27ae60;
            --hrms-warning: #f39c12;
            --hrms-danger: #e74c3c;
            --hrms-light: #ecf0f1;
            --hrms-dark: #2c3e50;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .hrms-container {
            display: flex;
            min-height: 100vh;
        }

        .hrms-sidebar {
            width: 280px;
            min-width: 280px;
            background: linear-gradient(135deg, var(--hrms-primary) 0%, var(--hrms-secondary) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .hrms-main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .hrms-brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .hrms-brand h4 {
            margin: 0;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hrms-nav {
            padding: 20px 0;
        }

        .nav-section-title {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 20px 20px 10px 20px;
            padding-bottom: 5px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            background: none;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
            color: white;
            padding-left: 30px;
        }

        .nav-link.active {
            background-color: var(--hrms-accent);
            color: white;
            position: relative;
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: white;
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
            font-size: 16px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--hrms-accent), #5dade2);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, var(--hrms-accent));
            transform: translateY(-1px);
        }

        .badge {
            font-size: 10px;
            padding: 4px 8px;
            border-radius: 12px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hrms-sidebar {
                transform: translateX(-100%);
                width: 100%;
            }
            
            .hrms-sidebar.show {
                transform: translateX(0);
            }
            
            .hrms-main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .mobile-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--hrms-primary);
                color: white;
                border: none;
                padding: 10px;
                border-radius: 6px;
            }
        }

        @media (min-width: 769px) {
            .mobile-toggle {
                display: none;
            }
        }
    </style>
    
    <!-- Dark Mode Support -->
    <link rel="stylesheet" href="dark_mode.css">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle d-md-none" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    <div class="hrms-container">