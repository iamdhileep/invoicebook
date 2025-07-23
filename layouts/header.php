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
    
    <!-- Professional UI CSS -->
    <style>
        :root {
            /* Layout Variables */
            --sidebar-width: 280px;
            --header-height: 80px;
            
            /* Professional Color System - Corporate Edition */
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary-color: #64748b;
            --accent-color: #0891b2;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0284c7;
            
            /* Professional Neutral Colors */
            --white: #ffffff;
            --gray-25: #fcfcfd;
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
            --gray-950: #020617;
            
            /* Professional Background Gradients */
            --bg-gradient-primary: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            --bg-gradient-secondary: linear-gradient(135deg, #64748b 0%, #475569 100%);
            --bg-gradient-success: linear-gradient(135deg, #059669 0%, #047857 100%);
            --bg-gradient-dark: linear-gradient(135deg, #374151 0%, #1f2937 100%);
            --bg-gradient-subtle: linear-gradient(135deg, var(--gray-25) 0%, var(--gray-50) 100%);
            --bg-gradient-card: linear-gradient(135deg, var(--white) 0%, var(--gray-25) 100%);
            
            /* Professional Shadow System */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-base: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            /* Border Radius */
            --radius-sm: 4px;
            --radius-base: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
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
            
            /* Professional Spacing Scale - Optimized */
            --space-0: 0;
            --space-1: 0.25rem;     /* 4px */
            --space-2: 0.5rem;      /* 8px */
            --space-3: 0.75rem;     /* 12px */
            --space-4: 1rem;        /* 16px */
            --space-5: 1.25rem;     /* 20px */
            --space-6: 1.5rem;      /* 24px */
            --space-8: 2rem;        /* 32px */
            --space-10: 2.5rem;     /* 40px */
            --space-12: 3rem;       /* 48px */
            --space-16: 4rem;       /* 64px */
            --space-20: 5rem;       /* 80px */
            --space-24: 6rem;       /* 96px */
            
            /* Content Spacing */
            --content-padding: var(--space-6);
            --section-padding: var(--space-8);
            --container-padding: var(--space-4);
            --form-spacing: var(--space-4);
            --card-padding: var(--space-6);
            --button-padding: var(--space-2) var(--space-4);  /* Reduced from var(--space-3) var(--space-6) */
            
            /* Optimized Sidebar Spacing */
            --sidebar-item-spacing: 0.0625rem;  /* 1px - Optimized for density */
            --sidebar-link-padding: 0.625rem 1rem; /* 10px 16px - Reduced height */
            --sidebar-section-spacing: 1rem;     /* 16px - Tighter sections */
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Professional Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
            transition: var(--transition-base);
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
        
        /* Professional Header Styles */
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
            padding: 0 var(--space-6);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-base);
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            font-size: var(--font-size-xl);
            color: var(--gray-600);
            margin-right: var(--space-4);
            padding: var(--space-3);
            border-radius: var(--radius-base);
            transition: var(--transition-base);
            cursor: pointer;
            user-select: none;
        }
        
        .sidebar-toggle:hover {
            background-color: var(--gray-100);
            color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .sidebar-toggle:active {
            transform: scale(0.95);
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
            gap: var(--space-2);
            transition: var(--transition-base);
        }
        
        .header-brand:hover {
            transform: scale(1.02);
        }
        
        .header-brand i {
            font-size: var(--font-size-2xl);
            color: var(--primary-color);
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-4);
        }
        
        .header-search {
            position: relative;
            width: 300px;
            margin-right: var(--space-4);
        }
        
        .header-search input {
            width: 100%;
            padding: var(--space-3) var(--space-4) var(--space-3) var(--space-10);
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
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
        }
        
        .header-search i {
            position: absolute;
            left: var(--space-3);
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
            user-select: none;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }
        
        .user-avatar:active {
            transform: scale(1.05);
        }
        
        /* Professional Notification Badge */
        .notification-badge {
            position: relative;
            cursor: pointer;
            padding: var(--space-3);
            border-radius: var(--radius-base);
            transition: var(--transition-base);
            user-select: none;
        }
        
        .notification-badge:hover {
            background: var(--gray-100);
            transform: scale(1.05);
        }
        
        .notification-badge:active {
            transform: scale(0.95);
        }
        
        .notification-badge .badge {
            position: absolute;
            top: var(--space-1);
            right: var(--space-1);
            background: var(--danger-color);
            color: var(--white);
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 50px;
            min-width: 18px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        /* Professional Sidebar Styles - Optimized Spacing */
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
            padding: var(--space-4) 0;
        }
        
        /* Optimized Navigation Items - Reduced Spacing */
        .nav-item {
            margin: var(--sidebar-item-spacing) 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: var(--sidebar-link-padding);
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            border-left: 3px solid transparent;
            position: relative;
            user-select: none;
        }
        
        .nav-link:hover {
            background: linear-gradient(90deg, var(--gray-25) 0%, transparent 100%);
            color: var(--primary-color);
            border-left-color: var(--primary-light);
            transform: translateX(2px);
        }
        
        .nav-link.active {
            background: linear-gradient(90deg, rgb(37 99 235 / 0.08) 0%, transparent 100%);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            right: var(--space-4);
            width: 6px;
            height: 6px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .nav-link i {
            width: 18px;
            margin-right: var(--space-3);
            font-size: 1rem;
            transition: var(--transition-base);
        }
        
        .nav-link:hover i {
            transform: scale(1.1);
        }
        
        /* Optimized Navigation Sections - Reduced Spacing */
        .nav-section {
            margin: var(--sidebar-section-spacing) 0 var(--space-2) 0;
        }
        
        .nav-section-title {
            font-size: var(--font-size-xs);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--gray-400);
            margin: 0 var(--space-4) var(--space-2) var(--space-4);
            padding-top: var(--space-2);
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
        
        /* Professional Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
            padding: var(--content-padding);
            transition: var(--transition-slow);
            background: var(--gray-50);
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Professional Cards */
        .card {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-base);
            transition: var(--transition-base);
            background: var(--bg-gradient-card);
            overflow: hidden;
            position: relative;
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--bg-gradient-primary);
            opacity: 0;
            transition: var(--transition-base);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            background: var(--bg-gradient-subtle);
            border-bottom: 1px solid var(--gray-200);
            padding: var(--space-4) var(--content-padding);
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--font-size-base);
        }
        
        .card-body {
            padding: var(--content-padding);
            background: var(--gray-25);
            color: var(--gray-800);
        }
        
        /* Professional Content Visibility */
        .card-body h1, .card-body h2, .card-body h3,
        .card-body h4, .card-body h5, .card-body h6 {
            color: var(--gray-900) !important;
            font-weight: 600;
        }
        
        .card-body p, .card-body span, .card-body div,
        .card-body td, .card-body th, .card-body li {
            color: var(--gray-800) !important;
        }
        
        .card-body .text-muted {
            color: var(--gray-600) !important;
        }
        
        .card-body .form-control {
            background: var(--white) !important;
            color: var(--gray-800) !important;
        }
        
        .card-body .form-select {
            background: var(--white) !important;
            color: var(--gray-800) !important;
        }
        
        /* Professional Buttons */
        .btn {
            border-radius: var(--radius-base);
            font-weight: 500;
            padding: var(--space-3) var(--space-6);
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            position: relative;
            overflow: hidden;
            user-select: none;
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
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .btn-primary {
            background: var(--bg-gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow-base);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-1px);
            border-color: var(--gray-400);
        }
        
        .btn-success {
            background: var(--bg-gradient-success);
            color: var(--white);
        }
        
        .btn-success:hover {
            background: var(--success-color);
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
        }
        
        /* Professional Button Sizes */
        .btn-sm {
            padding: var(--space-1) var(--space-3);
            font-size: var(--font-size-xs);
        }
        
        .btn-lg {
            padding: var(--space-3) var(--space-5);
            font-size: var(--font-size-base);
        }
        
        /* Professional Tables */
        .table-responsive {
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: var(--white);
            overflow: hidden;
        }
        
        .table thead th {
            background: var(--bg-gradient-dark);
            color: var(--white);
            font-weight: 600;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: var(--space-4);
            border: none;
        }
        
        .table tbody td {
            padding: var(--space-4);
            border-bottom: 1px solid var(--gray-200);
            font-size: var(--font-size-sm);
            vertical-align: middle;
        }
        
        .table tbody tr {
            transition: var(--transition-fast);
        }
        
        .table tbody tr:hover {
            background: var(--gray-25);
        }
        
        /* Professional Form Controls */
        .form-control {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-3) var(--space-4);
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
            outline: none;
        }
        
        /* Professional Form Validation */
        .form-control.is-invalid {
            border-color: var(--danger-color);
            background-image: none;
        }

        .form-control.is-invalid:focus {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgb(220 38 38 / 0.1);
        }

        .form-control.is-valid {
            border-color: var(--success-color);
            background-image: none;
        }

        .form-control.is-valid:focus {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgb(5 150 105 / 0.1);
        }
        
        /* Professional Form Select */
        .form-select {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-3) var(--space-4);
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            cursor: pointer;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
            outline: none;
        }
        
        .form-select:hover {
            border-color: var(--gray-400);
        }
        
        /* Professional Form Labels */
        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: var(--space-2);
            font-size: var(--font-size-sm);
        }
        
        /* Professional Alerts */
        .alert {
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--space-4) var(--space-6);
            font-size: var(--font-size-sm);
            box-shadow: var(--shadow-sm);
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgb(5 150 105 / 0.08) 0%, rgb(16 185 129 / 0.04) 100%);
            border-left: 4px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, rgb(220 38 38 / 0.08) 0%, rgb(239 68 68 / 0.04) 100%);
            border-left: 4px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .alert-warning {
            background: linear-gradient(135deg, rgb(217 119 6 / 0.08) 0%, rgb(245 158 11 / 0.04) 100%);
            border-left: 4px solid var(--warning-color);
            color: var(--warning-color);
        }
        
        .alert-info {
            background: linear-gradient(135deg, rgb(2 132 199 / 0.08) 0%, rgb(59 130 246 / 0.04) 100%);
            border-left: 4px solid var(--info-color);
            color: var(--info-color);
        }
        
        /* Professional Badge */
        .badge {
            font-size: var(--font-size-xs);
            padding: var(--space-1) var(--space-3);
            border-radius: 50px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Professional Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(37, 99, 235, 0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Professional Responsive Design */
        @media (max-width: 992px) {
            .header-search {
                width: 200px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .header-actions {
                gap: var(--space-2);
            }
            
            .header-search {
                display: none;
            }
            
            .main-header {
                padding: 0 var(--space-4);
            }
            
            .main-content {
                padding: var(--space-4);
            }
        }
        
        /* User Experience Enhancements */
        .interactive {
            cursor: pointer;
            transition: var(--transition-base);
            user-select: none;
        }
        
        .interactive:hover {
            transform: translateY(-1px);
        }
        
        .interactive:active {
            transform: translateY(0);
        }
        
        .focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            animation: slideInRight 0.3s ease forwards;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
    
    <!-- Custom CSS -->
    <link href="<?= $basePath ?? '' ?>assets/css/dashboard-modern.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
            <i class="bi bi-list"></i>
        </button>
        
        <a href="<?= $basePath ?? '' ?>pages/dashboard/dashboard.php" class="header-brand">
            <i class="bi bi-graph-up-arrow"></i>
            BillBook Pro
        </a>
        
        <div class="header-actions">
            <div class="header-search">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Search everything..." id="globalSearch">
            </div>
            
            <div class="notification-badge" onclick="showNotifications()">
                <i class="bi bi-bell"></i>
                <span class="badge">3</span>
            </div>
            
            <div class="user-menu">
                <div class="user-avatar" onclick="showUserMenu()">
                    A
                </div>
            </div>
        </div>
    </header>

    <!-- Enhanced Sidebar Toggle & Auto-hide Script -->
    <script>
        let sidebarTimeout;
        let isSidebarVisible = window.innerWidth > 992;
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && mainContent) {
                const isCollapsed = sidebar.classList.contains('collapsed');
                
                if (isCollapsed) {
                    sidebar.classList.remove('collapsed');
                    if (window.innerWidth > 992) {
                        mainContent.classList.remove('expanded');
                    }
                    isSidebarVisible = true;
                } else {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    isSidebarVisible = false;
                }
                
                // Save state
                localStorage.setItem('sidebarCollapsed', !isCollapsed);
            }
        }
        
        // Enhanced auto-hide functionality for mobile
        function setupAutoHide() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (!sidebar || window.innerWidth > 992) return;
            
            // Auto-hide on content click (mobile)
            if (mainContent) {
                mainContent.addEventListener('click', () => {
                    if (isSidebarVisible && window.innerWidth <= 992) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        isSidebarVisible = false;
                    }
                });
            }
            
            // Auto-hide on outside click
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && isSidebarVisible) {
                    if (!sidebar.contains(e.target) && !e.target.closest('.sidebar-toggle')) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        isSidebarVisible = false;
                    }
                }
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + B to toggle sidebar
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
            
            // Escape to hide sidebar on mobile
            if (e.key === 'Escape' && window.innerWidth <= 992 && isSidebarVisible) {
                toggleSidebar();
            }
        });
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // Restore sidebar state
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed && window.innerWidth > 992) {
                toggleSidebar();
            }
            
            // Setup auto-hide
            setupAutoHide();
            
            // Handle window resize
            window.addEventListener('resize', () => {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                
                if (window.innerWidth <= 992) {
                    // Mobile: hide sidebar by default
                    if (sidebar) sidebar.classList.add('collapsed');
                    if (mainContent) mainContent.classList.add('expanded');
                    isSidebarVisible = false;
                } else {
                    // Desktop: restore saved state
                    const savedState = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (sidebar && mainContent) {
                        if (savedState) {
                            sidebar.classList.add('collapsed');
                            mainContent.classList.add('expanded');
                            isSidebarVisible = false;
                        } else {
                            sidebar.classList.remove('collapsed');
                            mainContent.classList.remove('expanded');
                            isSidebarVisible = true;
                        }
                    }
                }
                
                setupAutoHide();
            });
        });
        
        // Professional Toast Notification System
        function showToast(message, type = 'info', duration = 3000) {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        // Enhanced Global Search
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.addEventListener('input', debounce(function(e) {
                    const query = e.target.value.trim();
                    if (query.length > 2) {
                        // Implement global search functionality
                        console.log('Searching for:', query);
                        // You can implement AJAX search here
                    }
                }, 300));
            }
        });
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        // Professional User Menu
        function showUserMenu() {
            showToast('User menu functionality coming soon!', 'info');
        }
        
        // Professional Notifications
        function showNotifications() {
            showToast('You have 3 new notifications!', 'info');
        }
    </script>