<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Dashboard' ?> - Business Management System</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Modern UI CSS -->
    <style>
        :root {
            /* Layout Variables */
            --sidebar-width: 280px;
            --header-height: 80px;
            
            /* Color System - Modern Palette */
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #a5b4fc;
            --secondary-color: #64748b;
            --accent-color: #06b6d4;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            
            /* Neutral Colors */
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            
            /* Background Gradients */
            --bg-gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bg-gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --bg-gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --bg-gradient-dark: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            
            /* Shadow System */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-base: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            /* Border Radius */
            --radius-sm: 6px;
            --radius-base: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            
            /* Typography */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            
            /* Transitions */
            --transition-fast: all 0.15s ease;
            --transition-base: all 0.2s ease;
            --transition-slow: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-family);
            background: var(--gray-50);
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: var(--gray-800);
            overflow-x: hidden;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
        
        /* Header Styles */
        .main-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: var(--white);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--gray-200);
            z-index: 1030;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-base);
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-600);
            margin-right: 1rem;
            padding: 0.75rem;
            border-radius: var(--radius-base);
            transition: var(--transition-fast);
            cursor: pointer;
        }
        
        .sidebar-toggle:hover {
            background-color: var(--gray-100);
            color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .header-brand {
            font-weight: 700;
            font-size: var(--font-size-xl);
            background: var(--bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            margin-right: auto;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition-base);
        }
        
        .header-brand:hover {
            transform: scale(1.05);
        }
        
        .header-brand i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-search {
            position: relative;
            width: 300px;
            margin-right: 1rem;
        }
        
        .header-search input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            background: var(--gray-50);
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
        }
        
        .header-search input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: var(--white);
            box-shadow: 0 0 0 3px rgb(99 102 241 / 0.1);
        }
        
        .header-search i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: var(--font-size-sm);
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: var(--bg-gradient-primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            border: 3px solid var(--white);
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }
        
        /* Notification Badge */
        .notification-badge {
            position: relative;
            cursor: pointer;
            padding: 0.75rem;
            border-radius: var(--radius-base);
            transition: var(--transition-base);
        }
        
        .notification-badge:hover {
            background: var(--gray-100);
        }
        
        .notification-badge .badge {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: var(--danger-color);
            color: var(--white);
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 50px;
            min-width: 18px;
            text-align: center;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            background: var(--white);
            border-right: 1px solid var(--gray-200);
            overflow-y: auto;
            z-index: 1020;
            transition: var(--transition-slow);
            box-shadow: var(--shadow-sm);
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        
        .sidebar-nav {
            padding: 1.5rem 0;
        }
        
        .nav-item {
            margin: 0.25rem 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .nav-link:hover {
            background: linear-gradient(90deg, var(--gray-50) 0%, transparent 100%);
            color: var(--primary-color);
            border-left-color: var(--primary-light);
            transform: translateX(4px);
        }
        
        .nav-link.active {
            background: linear-gradient(90deg, rgb(99 102 241 / 0.1) 0%, transparent 100%);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            right: 1rem;
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
        }
        
        .nav-link i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
            transition: var(--transition-base);
        }
        
        .nav-link:hover i {
            transform: scale(1.1);
        }
        
        /* Navigation sections */
        .nav-section {
            margin: 2rem 0 0.75rem 0;
        }
        
        .nav-section-title {
            font-size: var(--font-size-xs);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--gray-400);
            margin: 0 1.5rem 0.75rem 1.5rem;
            padding-top: 0.75rem;
            position: relative;
        }
        
        .nav-section-title::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, var(--gray-200) 0%, transparent 100%);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
            padding: 2rem;
            transition: var(--transition-slow);
            background: var(--gray-50);
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-base);
            transition: var(--transition-base);
            background: var(--white);
            overflow: hidden;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--white) 100%);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.5rem 2rem;
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--font-size-lg);
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Buttons */
        .btn {
            border-radius: var(--radius-base);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition-base);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: var(--bg-gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow-base);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--bg-gradient-success);
            color: var(--white);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
        }
        
        /* Forms */
        .form-control {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: 0.75rem 1rem;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            background: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgb(99 102 241 / 0.1);
            background: var(--white);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: var(--font-size-sm);
        }
        
        /* Tables */
        .table {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-base);
            background: var(--white);
        }
        
        .table thead th {
            background: var(--bg-gradient-dark);
            color: var(--white);
            border: none;
            font-weight: 600;
            padding: 1.25rem;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
            font-size: var(--font-size-sm);
        }
        
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            font-weight: 500;
            box-shadow: var(--shadow-base);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgb(16 185 129 / 0.1) 0%, rgb(34 197 94 / 0.1) 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgb(239 68 68 / 0.1) 0%, rgb(220 38 38 / 0.1) 100%);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .header-search {
                display: none;
            }
            
            .main-header {
                padding: 0 1rem;
            }
        }
        
        /* Loading Animation */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(248, 250, 252, 0.95);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: var(--transition-slow);
        }
        
        .loader-spinner {
            width: 3rem;
            height: 3rem;
            border: 3px solid var(--gray-200);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Custom Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease forwards;
        }
        
        .animate-fade-in-down {
            animation: fadeInDown 0.6s ease forwards;
        }
        
        /* Utility Classes */
        .gradient-text {
            background: var(--bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <!-- Page Loader -->
    <div id="pageLoader" class="page-loader">
        <div class="loader-spinner"></div>
    </div>

    <!-- Header -->
    <header class="main-header animate-fade-in-down">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <?php
        // Simple and reliable base path calculation (same as sidebar)
        $basePath = '';
        if (strpos(dirname($_SERVER['SCRIPT_NAME']), '/pages/') !== false) {
            // We're in pages subdirectory, go back to root
            $basePath = '../../';
        } else {
            // We're in root directory
            $basePath = './';
        }
        ?>
        <a href="<?= $basePath ?>pages/dashboard/dashboard.php" class="header-brand">
            <i class="bi bi-building"></i>
            Business Manager
        </a>
        
        <!-- Global Search -->
        <div class="header-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search across all modules..." id="globalSearch">
        </div>
        
        <div class="header-actions">
            <!-- Notifications -->
            <div class="notification-badge">
                <i class="bi bi-bell" style="font-size: 1.2rem; color: var(--gray-600);"></i>
                <span class="badge">3</span>
            </div>
            
            <!-- User Menu -->
            <div class="dropdown user-menu">
                <div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: var(--radius-lg); padding: 0.5rem;">
                    <li><a class="dropdown-item" href="#" style="border-radius: var(--radius-base); padding: 0.75rem 1rem;"><i class="bi bi-person me-2"></i>Profile Settings</a></li>
                    <li><a class="dropdown-item" href="#" style="border-radius: var(--radius-base); padding: 0.75rem 1rem;"><i class="bi bi-gear me-2"></i>Preferences</a></li>
                    <li><a class="dropdown-item" href="#" style="border-radius: var(--radius-base); padding: 0.75rem 1rem;"><i class="bi bi-question-circle me-2"></i>Help & Support</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $basePath ?>logout.php" style="border-radius: var(--radius-base); padding: 0.75rem 1rem;"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </header>