<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Dashboard' ?> - Business Management System</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/billbook/icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/billbook/icons/favicon-16x16.png">
    <link rel="shortcut icon" href="/billbook/favicon.ico">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/billbook/HRMS/manifest.json">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="HRMS Enterprise">
    <link rel="apple-touch-icon" href="/billbook/HRMS/assets/icon-192x192.png">
    
    <!-- PWA iOS Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/billbook/HRMS/assets/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/billbook/HRMS/assets/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/billbook/HRMS/assets/icon-144x144.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Global Consistent Styles -->
    <link href="../../assets/css/global-styles.css" rel="stylesheet">
    <link href="../assets/css/global-styles.css" rel="stylesheet">
    <link href="assets/css/global-styles.css" rel="stylesheet">
    
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
            
            /* Typography - Compact Sizes */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.8125rem;    /* Reduced from 0.875rem */
            --font-size-base: 0.9375rem;  /* Reduced from 1rem */
            --font-size-lg: 1rem;         /* Reduced from 1.125rem */
            --font-size-xl: 1.125rem;     /* Reduced from 1.25rem */
            --font-size-2xl: 1.25rem;     /* Reduced from 1.5rem */
            --font-size-3xl: 1.5rem;      /* Reduced from 1.875rem */
            
            /* Transitions */
            --transition-fast: all 0.15s ease;
            --transition-base: all 0.2s ease;
            --transition-slow: all 0.3s ease;
            
            /* Professional Spacing Scale - Compact */
            --space-0: 0;
            --space-1: 0.25rem;     /* 4px */
            --space-2: 0.375rem;    /* 6px - Reduced from 8px */
            --space-3: 0.5rem;      /* 8px - Reduced from 12px */
            --space-4: 0.75rem;     /* 12px - Reduced from 16px */
            --space-5: 1rem;        /* 16px - Reduced from 20px */
            --space-6: 1.125rem;    /* 18px - Reduced from 24px */
            --space-8: 1.5rem;      /* 24px - Reduced from 32px */
            --space-10: 2rem;       /* 32px - Reduced from 40px */
            --space-12: 2.5rem;     /* 40px - Reduced from 48px */
            --space-16: 3rem;       /* 48px - Reduced from 64px */
            --space-20: 4rem;       /* 64px - Reduced from 80px */
            --space-24: 5rem;       /* 80px - Reduced from 96px */
            
            /* Content Spacing - Compact */
            --content-padding: var(--space-4);     /* Reduced from var(--space-6) */
            --section-padding: var(--space-5);     /* Reduced from var(--space-8) */
            --container-padding: var(--space-3);   /* Reduced from var(--space-4) */
            --form-spacing: var(--space-3);        /* Reduced from var(--space-4) */
            --card-padding: var(--space-4);        /* Reduced from var(--space-6) */
            --button-padding: var(--space-2) var(--space-3);  /* Further reduced */
            
            /* Optimized Sidebar Spacing - Ultra Compact */
            --sidebar-item-spacing: 0;                /* Removed spacing between items */
            --sidebar-link-padding: 0.5rem 0.75rem;   /* Further reduced padding */
            --sidebar-section-spacing: 0.75rem;       /* Reduced section spacing */
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
        
        /* Professional HRMS Dropdown Styles */
        .dropdown-toggle {
            position: relative;
            background: #f8fafc;
            border-radius: 6px;
            transition: all 0.2s ease;
            border-left: 2px solid transparent;
        }
        
        .dropdown-toggle:hover {
            background: #f1f5f9;
            color: #334155;
            transform: translateX(2px);
            border-left: 2px solid #3b82f6;
        }
        
        .dropdown-toggle:hover i {
            color: #3b82f6;
        }
        
        .dropdown-toggle::after {
            content: '';
            display: inline-block;
            margin-left: auto;
            vertical-align: middle;
            border-top: 0.3em solid #64748b;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            transition: all 0.2s ease;
        }
        
        .dropdown-toggle[aria-expanded="true"] {
            background: #eff6ff;
            color: #1e40af;
            border-left: 2px solid #2563eb;
        }
        
        .dropdown-toggle[aria-expanded="true"]::after {
            transform: rotate(-180deg);
            border-top-color: #2563eb;
        }
        
        .dropdown-toggle[aria-expanded="true"] i {
            color: #2563eb;
        }
        
        .dropdown-toggle:hover::after {
            border-top-color: #3b82f6;
        }
        
        /* Add smooth collapse animations */
        .collapse {
            transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Professional HRMS Submenu Styles - Refined */
        .nav-submenu {
            background: #fafbfc;
            border-left: 2px solid #e1e7ef;
            margin-left: 16px;
            border-radius: 0 8px 8px 0;
            overflow: hidden;
            padding: 4px 0;
        }
        
        .nav-submenu-inner {
            padding: 4px 0;
        }
        
        .nav-submenu .nav-link {
            padding: 10px 16px 10px 20px;
            font-size: 13px;
            color: #475569;
            border-left: none;
            position: relative;
            transition: all 0.2s ease;
            margin: 1px 8px;
            border-radius: 6px;
            font-weight: 400;
            display: flex;
            align-items: center;
        }
        
        .nav-submenu .nav-link i {
            width: 16px;
            text-align: center;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        /* Remove bullet points completely */
        .nav-submenu .nav-link::before {
            display: none;
        }
        
        /* Professional hover effects */
        .nav-submenu .nav-link:hover {
            background: #f1f5f9;
            color: #334155;
            transform: translateX(2px);
            border-left: 3px solid #3b82f6;
        }
        
        .nav-submenu .nav-link:hover i {
            color: #3b82f6;
        }
        
        /* Professional active state */
        .nav-submenu .nav-link.active {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 500;
            border-left: 3px solid #2563eb;
            position: relative;
        }
        
        .nav-submenu .nav-link.active::after {
            content: '';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 4px;
            background: #2563eb;
            border-radius: 50%;
        }
        
        .nav-submenu .nav-link.active i {
            color: #2563eb;
        }
        
        /* Child Submenu (Nested) Specific Styles */
        .nav-submenu-inner {
            background: #f8fafc;
            border-radius: 6px;
            margin: 4px 12px 4px 16px;
            border-left: 2px solid #cbd5e1;
            padding: 4px 0;
        }
        
        .nav-submenu-inner .nav-link {
            padding: 8px 12px 8px 16px;
            font-size: 12px;
            color: #64748b;
            margin: 1px 6px;
            border-radius: 4px;
            font-weight: 400;
            border-left: none;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .nav-submenu-inner .nav-link i {
            width: 14px;
            text-align: center;
            margin-right: 6px;
            flex-shrink: 0;
            font-size: 11px;
        }
        
        .nav-submenu-inner .nav-link::before {
            content: '•';
            position: absolute;
            left: 6px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 8px;
        }
        
        .nav-submenu-inner .nav-link:hover {
            background: #e2e8f0;
            color: #475569;
            transform: translateX(3px);
            border-left: 2px solid #3b82f6;
        }
        
        .nav-submenu-inner .nav-link:hover::before {
            color: #3b82f6;
        }
        
        .nav-submenu-inner .nav-link:hover i {
            color: #3b82f6;
        }
        
        .nav-submenu-inner .nav-link.active {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 500;
            border-left: 2px solid #2563eb;
        }
        
        .nav-submenu-inner .nav-link.active::before {
            color: #2563eb;
            content: '▪';
            font-size: 6px;
        }
        
        .nav-submenu-inner .nav-link.active::after {
            content: '';
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 3px;
            background: #2563eb;
            border-radius: 50%;
        }
        
        .nav-submenu-inner .nav-link.active i {
            color: #1d4ed8;
        }
        
        /* Visual Hierarchy Enhancements */
        .nav-submenu {
            position: relative;
        }
        
        .nav-submenu::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            bottom: 8px;
            width: 2px;
            background: linear-gradient(to bottom, #e1e7ef 0%, #3b82f6 50%, #e1e7ef 100%);
            opacity: 0.6;
        }
        
        .nav-submenu-inner {
            position: relative;
        }
        
        .nav-submenu-inner::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 4px;
            bottom: 4px;
            width: 1px;
            background: #cbd5e1;
            opacity: 0.8;
        }
        
        /* Icon size adjustments for hierarchy */
        .submenu-toggle i {
            font-size: 14px;
        }
        
        .nav-submenu-inner .nav-link i {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .nav-submenu-inner .nav-link:hover i,
        .nav-submenu-inner .nav-link.active i {
            opacity: 1;
        }
        
        /* Typography and spacing refinements */
        .nav-submenu .nav-link span {
            font-size: 13px;
            line-height: 1.4;
        }
        
        .nav-submenu-inner .nav-link span {
            font-size: 12px;
            line-height: 1.3;
            letter-spacing: 0.1px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .nav-submenu-inner .nav-link {
                padding: 6px var(--space-2) 6px var(--space-4);
                font-size: 11px;
            }
            
            .nav-submenu-inner .nav-link i {
                font-size: 11px;
            }
        }
        
        /* Submenu Toggle (Parent) Specific Styles */
        .submenu-toggle {
            background: #f1f5f9;
            font-weight: 500;
            color: #334155;
            border-left: 3px solid #cbd5e1;
            padding: 10px 16px 10px 20px;
            display: flex;
            align-items: center;
        }
        
        .submenu-toggle i {
            width: 16px;
            text-align: center;
            margin-right: 8px;
            flex-shrink: 0;
            font-size: 14px;
        }
        
        .submenu-toggle:hover {
            background: #e2e8f0;
            color: #1e293b;
            border-left: 3px solid #3b82f6;
        }
        
        .submenu-toggle[aria-expanded="true"] {
            background: #dbeafe;
            color: #1e40af;
            border-left: 3px solid #2563eb;
            font-weight: 600;
        }
        
        .submenu-toggle[aria-expanded="true"] i {
            color: #2563eb;
        }
        
        /* Professional Collapse Animation */
        .collapse {
            transition: var(--transition-slow);
        }
        
        .collapse:not(.show) {
            display: none;
        }
        
        .collapse.show {
            display: block;
        }
        
        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height var(--transition-slow) ease;
        }
        
        /* Badge Styles for Menu Items */
        .nav-link .badge {
            font-size: 0.6rem;
            padding: 0.2rem 0.4rem;
            border-radius: var(--radius-sm);
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
            padding: var(--space-3) var(--card-padding);  /* Reduced vertical padding */
            font-weight: 600;
            color: var(--gray-800);
            font-size: var(--font-size-sm);               /* Reduced from base */
        }
        
        .card-body {
            padding: var(--card-padding);
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
        
        /* Professional Dashboard Stat Cards - Compact */
        .stat-card {
            background: var(--bg-gradient-card);
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-base);
            transition: var(--transition-base);
            overflow: hidden;
            position: relative;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--bg-gradient-primary);
            opacity: 0;
            transition: var(--transition-base);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        /* Stat card variations */
        .stat-card.stat-revenue {
            border-left-color: #10b981;
        }
        
        .stat-card.stat-target {
            border-left-color: #3b82f6;
        }
        
        .stat-card.stat-expense {
            border-left-color: #f59e0b;
        }
        
        .stat-card.stat-employee {
            border-left-color: #8b5cf6;
        }
        
        /* Dashboard stat number styling - Compact */
        .stat-number {
            font-size: 1.75rem;        /* Reduced from 2.25rem */
            font-weight: 700;
            color: var(--gray-900) !important;
            line-height: 1.2;
            margin: 0;
        }
        
        .stat-label {
            font-size: var(--font-size-xs);
            color: var(--gray-600) !important;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: var(--space-1);  /* Reduced from var(--space-2) */
        }
        
        .stat-icon {
            width: 36px;               /* Reduced from 48px */
            height: 36px;              /* Reduced from 48px */
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;       /* Reduced from 12px */
            margin-bottom: var(--space-2);  /* Reduced from var(--space-3) */
        }
        
        /* Progress bars for dashboard */
        .progress-modern {
            height: 6px;
            border-radius: 3px;
            background: var(--gray-200);
            overflow: hidden;
        }
        
        .progress-modern .progress-bar {
            border-radius: 3px;
            transition: width 0.8s ease-in-out;
        }
        
        /* Activity feed styling - Compact */
        .activity-item {
            padding: var(--space-3);              /* Reduced from var(--space-4) */
            border-radius: var(--radius-base);
            background: var(--gray-25);
            border: 1px solid var(--gray-100);
            transition: var(--transition-base);
            margin-bottom: var(--space-2);        /* Reduced from var(--space-3) */
        }
        
        .activity-item:hover {
            background: var(--gray-50);
            border-color: var(--gray-200);
        }
        
        .activity-time {
            font-size: var(--font-size-xs);
            color: var(--gray-500) !important;
            font-weight: 500;
        }
        
        /* Quick action cards - Compact */
        .quick-action-card {
            background: var(--bg-gradient-card);
            border: 2px solid var(--gray-100);
            border-radius: var(--radius-lg);
            transition: var(--transition-base);
            text-decoration: none;
            color: inherit;
            display: block;
            padding: var(--space-4);              /* Reduced from var(--space-5) */
        }
        
        .quick-action-card:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action-icon {
            width: 32px;                          /* Reduced from 40px */
            height: 32px;                         /* Reduced from 40px */
            border-radius: 8px;                   /* Reduced from 10px */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-2);        /* Reduced from var(--space-3) */
        }
        
        /* Stat change indicators - Compact */
        .stat-change {
            font-size: var(--font-size-xs);
            font-weight: 500;
            display: flex;
            align-items: center;
            margin-top: var(--space-1);           /* Reduced from var(--space-2) */
        }
        
        .stat-change.positive {
            color: #10b981 !important;
        }
        
        .stat-change.negative {
            color: #ef4444 !important;
        }
        
        .stat-change i {
            margin-right: 3px;                    /* Reduced from 4px */
        }
        
        /* Dashboard animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        /* Professional badge styling - Compact */
        .badge {
            font-weight: 500;
            padding: 0.25rem 0.5rem;              /* Reduced padding */
            border-radius: var(--radius-base);
            font-size: var(--font-size-xs);      /* Reduced font size */
        }
        
        .badge-success {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f59e0b, #fbbf24);
            color: white;
        }
        
        .card-body .form-control {
            background: var(--white) !important;
            color: var(--gray-800) !important;
        }
        
        .card-body .form-select {
            background: var(--white) !important;
            color: var(--gray-800) !important;
        }
        
        /* Professional Buttons - Compact */
        .btn {
            border-radius: var(--radius-base);
            font-weight: 500;
            padding: var(--button-padding);       /* Using the compact button padding */
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);                  /* Reduced from var(--space-2) */
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
        
        /* Professional Button Sizes - Compact */
        .btn-sm {
            padding: var(--space-1) var(--space-2);  /* Further reduced */
            font-size: var(--font-size-xs);
        }
        
        .btn-lg {
            padding: var(--space-2) var(--space-4);  /* Reduced from var(--space-3) var(--space-5) */
            font-size: var(--font-size-sm);          /* Reduced from var(--font-size-base) */
        }
        
        /* Professional Tables */
        .table-responsive {
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: var(--white);
            overflow: hidden;
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            font-weight: 600;
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: var(--space-4);
            border: none;
            border-bottom: 3px solid var(--primary-color);
            position: relative;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--bg-gradient-primary);
        }
        
        .table thead {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
        }
        
        .table th {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            font-weight: 600 !important;
            font-size: var(--font-size-sm) !important;
            border-bottom: 3px solid var(--primary-color) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        .table tbody td {
            padding: var(--space-3);             /* Reduced from var(--space-4) */
            border-bottom: 1px solid var(--gray-200);
            font-size: var(--font-size-sm);
            vertical-align: middle;
            color: var(--gray-800) !important;
        }
        
        .table tbody tr {
            transition: var(--transition-fast);
        }
        
        .table tbody tr:hover {
            background: var(--gray-25);
        }
        
        /* DataTables Header Override */
        .dataTables_wrapper .table thead th,
        .dataTables_wrapper .table thead td {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            font-weight: 600 !important;
            border-bottom: 3px solid var(--primary-color) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Global Table Header Fix */
        table thead th,
        table thead td,
        .table-striped thead th,
        .table-hover thead th,
        .table-bordered thead th,
        .table > thead > tr > th,
        .table > thead > tr > td {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            font-weight: 600 !important;
            border-bottom: 3px solid var(--primary-color) !important;
            text-shadow: none !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Bootstrap Table Header Override */
        .table-dark thead th,
        .table-dark thead td,
        .bg-dark,
        .bg-primary,
        .bg-secondary {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            border-color: var(--primary-color) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Override any dark backgrounds on table headers */
        .table thead.table-dark th,
        .table thead.table-dark td,
        .table .table-dark th,
        .table .table-dark td {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Additional Table Header Fixes */
        .dataTable thead th,
        .dataTable thead td,
        .dt-head-center,
        .dt-head-left,
        .dt-head-right {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-100) 100%) !important;
            color: var(--gray-900) !important;
            font-weight: 600 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
        }
        
        /* Ensure text visibility in all table headers */
        thead th *,
        thead td *,
        .table thead th *,
        .table thead td *,
        .dataTables_wrapper table thead th *,
        .dataTables_wrapper table thead td * {
            color: var(--gray-900) !important;
        }
        
        /* DataTables Sorting Icons */
        .table thead th.sorting:before,
        .table thead th.sorting:after,
        .table thead th.sorting_asc:before,
        .table thead th.sorting_asc:after,
        .table thead th.sorting_desc:before,
        .table thead th.sorting_desc:after {
            color: var(--gray-700) !important;
        }
        
        /* Table Header Links */
        .table thead th a,
        .table thead td a,
        thead th a,
        thead td a {
            color: var(--gray-900) !important;
            text-decoration: none;
        }
        
        .table thead th a:hover,
        .table thead td a:hover,
        thead th a:hover,
        thead td a:hover {
            color: var(--primary-color) !important;
        }
        
        /* Enhanced DataTables Professional Styling */
        .dataTables_wrapper {
            padding: 0;
            margin: 0;
            font-family: var(--font-family);
        }
        
        .dataTables_wrapper .dataTables_length {
            float: left;
            margin-bottom: var(--space-3);       /* Reduced from var(--space-4) */
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-2) var(--space-3);
            font-size: var(--font-size-sm);
            background: var(--white);
            margin: 0 var(--space-2);
        }
        
        .dataTables_wrapper .dataTables_filter {
            float: right;
            margin-bottom: var(--space-3);       /* Reduced from var(--space-4) */
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-3) var(--space-4);
            font-size: var(--font-size-sm);
            margin-left: var(--space-2);
            transition: var(--transition-base);
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
        }
        
        .dataTables_wrapper .dataTables_info {
            float: left;
            padding-top: var(--space-3);         /* Reduced from var(--space-4) */
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .dataTables_wrapper .dataTables_paginate {
            float: right;
            padding-top: var(--space-2);         /* Reduced from var(--space-3) */
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            display: inline-block;
            padding: var(--space-2) var(--space-4);
            margin: 0 var(--space-1);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            background: var(--white);
            color: var(--gray-700);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
            cursor: pointer;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            font-weight: 600;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            background: var(--gray-100);
            color: var(--gray-400);
            border-color: var(--gray-200);
            cursor: not-allowed;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: var(--gray-100);
            color: var(--gray-400);
            border-color: var(--gray-200);
            transform: none;
        }
        
        /* Enhanced DataTables Sorting */
        .dataTables_wrapper table.dataTable thead th.sorting,
        .dataTables_wrapper table.dataTable thead th.sorting_asc,
        .dataTables_wrapper table.dataTable thead th.sorting_desc {
            cursor: pointer;
            position: relative;
            padding-right: var(--space-8);
        }
        
        .dataTables_wrapper table.dataTable thead th.sorting:before,
        .dataTables_wrapper table.dataTable thead th.sorting_asc:before,
        .dataTables_wrapper table.dataTable thead th.sorting_desc:before,
        .dataTables_wrapper table.dataTable thead th.sorting:after,
        .dataTables_wrapper table.dataTable thead th.sorting_asc:after,
        .dataTables_wrapper table.dataTable thead th.sorting_desc:after {
            position: absolute;
            right: var(--space-3);
            color: var(--gray-400);
            font-size: 0.8rem;
            line-height: 1;
        }
        
        .dataTables_wrapper table.dataTable thead th.sorting:before,
        .dataTables_wrapper table.dataTable thead th.sorting_asc:before,
        .dataTables_wrapper table.dataTable thead th.sorting_desc:before {
            content: "▲";
            top: 8px;
        }
        
        .dataTables_wrapper table.dataTable thead th.sorting:after,
        .dataTables_wrapper table.dataTable thead th.sorting_asc:after,
        .dataTables_wrapper table.dataTable thead th.sorting_desc:after {
            content: "▼";
            bottom: 8px;
        }
        
        .dataTables_wrapper table.dataTable thead th.sorting_asc:before {
            color: var(--primary-color);
        }
        
        .dataTables_wrapper table.dataTable thead th.sorting_desc:after {
            color: var(--primary-color);
        }
        
        /* DataTables Processing Indicator */
        .dataTables_wrapper .dataTables_processing {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 200px;
            margin-left: -100px;
            margin-top: -26px;
            text-align: center;
            padding: var(--space-4);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            font-size: var(--font-size-sm);
            color: var(--gray-700);
        }
        
        /* DataTables Row Styling */
        .dataTables_wrapper table.dataTable tbody tr {
            transition: var(--transition-fast);
        }
        
        .dataTables_wrapper table.dataTable tbody tr:hover {
            background: var(--gray-25) !important;
        }
        
        .dataTables_wrapper table.dataTable tbody tr.selected {
            background: rgba(37, 99, 235, 0.1) !important;
        }
        
        /* DataTables Responsive */
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control,
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control {
            position: relative;
            padding-left: var(--space-8);
            cursor: pointer;
        }
        
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            content: "+";
            position: absolute;
            left: var(--space-3);
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 1px solid var(--primary-color);
            border-radius: 50%;
            background: var(--primary-color);
            color: var(--white);
            font-size: 12px;
            text-align: center;
            line-height: 14px;
            font-weight: bold;
        }
        
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:before,
        .dataTables_wrapper table.dataTable.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control:before {
            content: "-";
            background: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        /* DataTables Action Buttons */
        .dt-action-buttons {
            display: flex;
            gap: var(--space-2);
            align-items: center;
        }
        
        .dt-btn {
            padding: var(--space-1) var(--space-3);
            border: none;
            border-radius: var(--radius-base);
            font-size: var(--font-size-xs);
            cursor: pointer;
            transition: var(--transition-base);
            display: inline-flex;
            align-items: center;
            gap: var(--space-1);
        }
        
        .dt-btn-edit {
            background: var(--info-color);
            color: var(--white);
        }
        
        .dt-btn-edit:hover {
            background: var(--primary-color);
            transform: translateY(-1px);
        }
        
        .dt-btn-delete {
            background: var(--danger-color);
            color: var(--white);
        }
        
        .dt-btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .dt-btn-view {
            background: var(--success-color);
            color: var(--white);
        }
        
        .dt-btn-view:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        
        /* DataTables Export Buttons - Compact */
        .dt-buttons {
            display: flex;
            gap: var(--space-1);                  /* Reduced from var(--space-2) */
            margin-bottom: var(--space-3);       /* Reduced from var(--space-4) */
        }
        
        .dt-button {
            padding: var(--space-2) var(--space-3);  /* Reduced from var(--space-2) var(--space-4) */
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            background: var(--white);
            color: var(--gray-700);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: var(--transition-base);
        }
        
        .dt-button:hover {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }
        
        /* DataTables Status Badges */
        .status-badge {
            padding: var(--space-1) var(--space-3);
            border-radius: 50px;
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.active {
            background: rgba(5, 150, 105, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        
        .status-badge.inactive {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        
        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        
        .status-badge.completed {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        /* Professional Form Controls - Compact */
        .form-control {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-2) var(--space-3);  /* Reduced from var(--space-3) var(--space-4) */
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
        
        /* Professional Form Select - Compact */
        .form-select {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-base);
            padding: var(--space-2) var(--space-3);  /* Reduced from var(--space-3) var(--space-4) */
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
        
        /* Professional Form Labels - Compact */
        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: var(--space-1);       /* Reduced from var(--space-2) */
            font-size: var(--font-size-sm);
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }
        
        .form-label.fw-semibold {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .form-label i {
            font-size: var(--font-size-sm);
            opacity: 0.8;
        }
        
        /* Enhanced Form Controls */
        .form-control-lg {
            padding: var(--space-4) var(--space-5);
            font-size: var(--font-size-base);
            border-radius: var(--radius-md);
            border-width: 2px;
        }
        
        .form-control-sm {
            padding: var(--space-2) var(--space-3);
            font-size: var(--font-size-xs);
            border-radius: var(--radius-sm);
        }
        
        .form-select-lg {
            padding: var(--space-4) var(--space-5);
            font-size: var(--font-size-base);
            border-radius: var(--radius-md);
            border-width: 2px;
        }
        
        .form-select-sm {
            padding: var(--space-2) var(--space-3);
            font-size: var(--font-size-xs);
            border-radius: var(--radius-sm);
        }
        
        /* Professional Input Groups */
        .input-group {
            border-radius: var(--radius-base);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-base);
        }
        
        .input-group:focus-within {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        .input-group-lg {
            border-radius: var(--radius-md);
        }
        
        .input-group-sm {
            border-radius: var(--radius-sm);
        }
        
        .input-group-text {
            background: var(--gray-100);
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
            font-weight: 500;
            font-size: var(--font-size-sm);
            transition: var(--transition-base);
        }
        
        .input-group:focus-within .input-group-text {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        
        /* Professional Form Text */
        .form-text {
            font-size: var(--font-size-xs);
            color: var(--gray-500);
            margin-top: var(--space-1);
            display: flex;
            align-items: center;
            gap: var(--space-1);
        }
        
        .form-text i {
            font-size: var(--font-size-xs);
            opacity: 0.8;
        }
        
        /* Professional Compact Typography */
        h1, .h1 {
            font-size: 1.5rem !important;        /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-3) !important;
            line-height: 1.3;
        }
        
        h2, .h2 {
            font-size: 1.25rem !important;       /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-2) !important;
            line-height: 1.3;
        }
        
        h3, .h3 {
            font-size: 1.125rem !important;      /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-2) !important;
            line-height: 1.3;
        }
        
        h4, .h4 {
            font-size: 1rem !important;          /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-2) !important;
            line-height: 1.3;
        }
        
        h5, .h5 {
            font-size: 0.9375rem !important;     /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-1) !important;
            line-height: 1.3;
        }
        
        h6, .h6 {
            font-size: 0.875rem !important;      /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-1) !important;
            line-height: 1.3;
        }
        
        /* Page Title Styling - Compact */
        .page-title {
            font-size: 1.375rem !important;      /* Compact page title */
            font-weight: 600;
            color: var(--gray-900) !important;
            margin-bottom: var(--space-3) !important;
            line-height: 1.2;
        }
        
        .page-subtitle {
            font-size: var(--font-size-sm) !important;
            color: var(--gray-600) !important;
            margin-bottom: var(--space-4) !important;
            line-height: 1.4;
        }
        
        /* Compact Section Headers */
        .section-header {
            font-size: 1rem !important;
            font-weight: 600;
            color: var(--gray-800) !important;
            margin-bottom: var(--space-2) !important;
            padding-bottom: var(--space-1);
            border-bottom: 2px solid var(--gray-200);
        }
        
        /* Compact Modal Headers */
        .modal-header {
            padding: var(--space-3) var(--space-4) !important;  /* Reduced padding */
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-title {
            font-size: 1.125rem !important;      /* Reduced from default */
            font-weight: 600;
            color: var(--gray-900) !important;
        }
        
        .modal-body {
            padding: var(--space-4) !important;  /* Reduced padding */
        }
        
        .modal-footer {
            padding: var(--space-3) var(--space-4) !important;  /* Reduced padding */
            border-top: 1px solid var(--gray-200);
        }
        
        /* Compact List Spacing */
        .list-group-item {
            padding: var(--space-3) var(--space-4) !important;  /* Reduced from default */
            font-size: var(--font-size-sm);
        }
        
        /* Compact Alert Spacing */
        .alert {
            padding: var(--space-3) var(--space-4) !important;  /* Reduced padding */
            margin-bottom: var(--space-3) !important;
            font-size: var(--font-size-sm);
        }
        
        /* Compact Navigation Tabs */
        .nav-tabs .nav-link {
            padding: var(--space-2) var(--space-3) !important;  /* Reduced padding */
            font-size: var(--font-size-sm);
        }
        
        .nav-pills .nav-link {
            padding: var(--space-2) var(--space-3) !important;  /* Reduced padding */
            font-size: var(--font-size-sm);
        }
        
        /* Compact Breadcrumb */
        .breadcrumb {
            padding: var(--space-2) var(--space-3) !important;  /* Reduced padding */
            margin-bottom: var(--space-3) !important;
            font-size: var(--font-size-sm);
        }
        
        .breadcrumb-item {
            font-size: var(--font-size-sm);
        }
        
        /* Compact Pagination */
        .pagination .page-link {
            padding: var(--space-2) var(--space-3) !important;  /* Reduced padding */
            font-size: var(--font-size-sm);
        }
        
        /* Global Paragraph Spacing */
        p {
            margin-bottom: var(--space-3) !important;
            font-size: var(--font-size-sm);
            line-height: 1.5;
        }
        
        /* Compact Input Group Spacing */
        .input-group {
            margin-bottom: var(--space-3) !important;
        }
        
        /* Compact Form Row Spacing */
        .row {
            margin-bottom: var(--space-3);
        }
        
        .mb-3 {
            margin-bottom: var(--space-3) !important;
        }
        
        .mb-4 {
            margin-bottom: var(--space-4) !important;
        }
        
        /* Compact Table Wrapper */
        .table-wrapper {
            margin-bottom: var(--space-4);
        }
        
        /* Professional Form Floating Labels */
        .form-floating {
            position: relative;
        }
        
        .form-floating > .form-control,
        .form-floating > .form-select {
            height: calc(3.5rem + 2px);
            line-height: 1.25;
            padding: 1rem 0.75rem;
        }
        
        .form-floating > label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem 0.75rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: opacity 0.1s ease-in-out, transform 0.1s ease-in-out;
            color: var(--gray-500);
            font-size: var(--font-size-sm);
        }
        
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label,
        .form-floating > .form-select ~ label {
            opacity: 0.65;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        /* Professional Form Check */
        .form-check {
            padding-left: 2rem;
            margin-bottom: var(--space-2);
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            margin-top: 0.125rem;
            margin-left: -2rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-sm);
            transition: var(--transition-base);
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-input:focus {
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
            border-color: var(--primary-color);
        }
        
        .form-check-label {
            color: var(--gray-700);
            font-size: var(--font-size-sm);
            font-weight: 500;
            cursor: pointer;
        }
        
        /* Professional File Input */
        .form-control[type="file"] {
            padding: var(--space-3);
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-md);
            background: var(--gray-25);
            transition: var(--transition-base);
            cursor: pointer;
        }
        
        .form-control[type="file"]:hover {
            border-color: var(--primary-color);
            background: var(--gray-50);
        }
        
        .form-control[type="file"]:focus {
            border-color: var(--primary-color);
            background: var(--white);
            box-shadow: 0 0 0 3px rgb(37 99 235 / 0.1);
        }
        
        /* Professional Textarea */
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        textarea.form-control:focus {
            resize: vertical;
        }
        
        /* Professional Form Validation Messages */
        .invalid-feedback {
            display: block;
            font-size: var(--font-size-xs);
            color: var(--danger-color);
            margin-top: var(--space-1);
            font-weight: 500;
        }
        
        .valid-feedback {
            display: block;
            font-size: var(--font-size-xs);
            color: var(--success-color);
            margin-top: var(--space-1);
            font-weight: 500;
        }
        
        /* Professional Form Actions */
        .form-actions {
            display: flex;
            gap: var(--space-3);
            justify-content: flex-end;
            align-items: center;
            padding-top: var(--space-4);
            border-top: 1px solid var(--gray-200);
            margin-top: var(--space-4);
        }
        
        .form-actions.justify-between {
            justify-content: space-between;
        }
        
        .form-actions.justify-center {
            justify-content: center;
        }
        
        /* Professional Form Loading States */
        .form-loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .form-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 2rem;
            height: 2rem;
            margin-top: -1rem;
            margin-left: -1rem;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s ease-in-out infinite;
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

    <!-- Bootstrap Native Collapse Enhancement -->
    <script>
        // Let Bootstrap handle the collapse natively, just add enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth accordion behavior for sidebar dropdowns
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.addEventListener('show.bs.collapse', function (e) {
                    // Close other dropdowns at the same level when opening a new one
                    const openingDropdown = e.target;
                    const parentLevel = openingDropdown.closest('.nav-item') || openingDropdown.closest('.nav-submenu');
                    
                    if (parentLevel) {
                        const siblingDropdowns = parentLevel.parentElement.querySelectorAll('.collapse.show');
                        siblingDropdowns.forEach(dropdown => {
                            if (dropdown !== openingDropdown) {
                                const bsCollapse = new bootstrap.Collapse(dropdown, { toggle: false });
                                bsCollapse.hide();
                            }
                        });
                    }
                });
            }
        });
    </script>

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
        
        // Enhanced DataTables Configuration
        function initDataTable(selector, options = {}) {
            const defaultOptions = {
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[0, 'desc']],
                language: {
                    search: "",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No entries available",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    },
                    processing: "Processing...",
                    emptyTable: "No data available in table"
                },
                dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-5"i><"col-sm-7"p>>',
                processing: true,
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                        searchable: false
                    }
                ],
                drawCallback: function(settings) {
                    // Add professional styling after each draw
                    this.api().rows().every(function() {
                        $(this.node()).find('.status-badge').each(function() {
                            const text = $(this).text().toLowerCase();
                            $(this).removeClass('active inactive pending completed');
                            if (text.includes('active') || text.includes('present')) {
                                $(this).addClass('active');
                            } else if (text.includes('inactive') || text.includes('absent')) {
                                $(this).addClass('inactive');
                            } else if (text.includes('pending')) {
                                $(this).addClass('pending');
                            } else if (text.includes('completed')) {
                                $(this).addClass('completed');
                            }
                        });
                    });
                }
            };
            
            const finalOptions = { ...defaultOptions, ...options };
            return $(selector).DataTable(finalOptions);
        }
        
        // Enhanced DataTables Export Functions
        function addExportButtons(table, filename = 'data') {
            const buttonsContainer = $('<div class="dt-buttons mb-3"></div>');
            
            // Excel Export
            const excelBtn = $('<button class="dt-button"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>');
            excelBtn.on('click', function() {
                exportToExcel(table, filename);
            });
            
            // PDF Export
            const pdfBtn = $('<button class="dt-button"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>');
            pdfBtn.on('click', function() {
                exportToPDF(table, filename);
            });
            
            // CSV Export
            const csvBtn = $('<button class="dt-button"><i class="bi bi-file-earmark-text me-1"></i>CSV</button>');
            csvBtn.on('click', function() {
                exportToCSV(table, filename);
            });
            
            // Print
            const printBtn = $('<button class="dt-button"><i class="bi bi-printer me-1"></i>Print</button>');
            printBtn.on('click', function() {
                printTable(table);
            });
            
            buttonsContainer.append(excelBtn, pdfBtn, csvBtn, printBtn);
            $(table.table().container()).find('.dataTables_length').parent().prepend(buttonsContainer);
        }
        
        function exportToExcel(table, filename) {
            const data = table.data().toArray();
            const headers = table.columns().header().toArray().map(th => $(th).text());
            
            let csvContent = headers.join(',') + '\\n';
            data.forEach(row => {
                const cleanRow = row.map(cell => {
                    // Remove HTML tags and escape quotes
                    return '"' + $(cell).text().replace(/"/g, '""') + '"';
                });
                csvContent += cleanRow.join(',') + '\\n';
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename + '.csv';
            link.click();
            
            showToast('Data exported to Excel successfully!', 'success');
        }
        
        function exportToCSV(table, filename) {
            exportToExcel(table, filename); // Same as Excel for now
        }
        
        function exportToPDF(table, filename) {
            showToast('PDF export functionality requires additional library setup!', 'info');
        }
        
        function printTable(table) {
            const printWindow = window.open('', '_blank');
            const tableHtml = $(table.table().container()).find('table').clone();
            
            // Remove action columns
            tableHtml.find('th:last-child, td:last-child').remove();
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Print Table</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; font-weight: bold; }
                        tr:nth-child(even) { background-color: #f9f9f9; }
                    </style>
                </head>
                <body>
                    <h2>Data Report</h2>
                    ${tableHtml[0].outerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
            
            showToast('Print dialog opened successfully!', 'success');
        }
        
        // Professional Action Button Helpers
        function createActionButtons(editUrl, deleteUrl, viewUrl, id) {
            let buttons = '<div class="dt-action-buttons">';
            
            if (viewUrl) {
                buttons += `<a href="${viewUrl}?id=${id}" class="dt-btn dt-btn-view" title="View">
                    <i class="bi bi-eye"></i>
                </a>`;
            }
            
            if (editUrl) {
                buttons += `<a href="${editUrl}?id=${id}" class="dt-btn dt-btn-edit" title="Edit">
                    <i class="bi bi-pencil"></i>
                </a>`;
            }
            
            if (deleteUrl) {
                buttons += `<button onclick="confirmDelete('${deleteUrl}', ${id})" class="dt-btn dt-btn-delete" title="Delete">
                    <i class="bi bi-trash"></i>
                </button>`;
            }
            
            buttons += '</div>';
            return buttons;
        }
        
        function confirmDelete(deleteUrl, id) {
            if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                // You can implement AJAX delete here or redirect
                window.location.href = deleteUrl + '?id=' + id;
            }
        }
        
        // Professional Status Badge Helper
        function createStatusBadge(status) {
            const statusText = status.toString().toLowerCase();
            let badgeClass = 'status-badge';
            
            if (statusText.includes('active') || statusText.includes('present') || statusText === '1') {
                badgeClass += ' active';
            } else if (statusText.includes('inactive') || statusText.includes('absent') || statusText === '0') {
                badgeClass += ' inactive';
            } else if (statusText.includes('pending')) {
                badgeClass += ' pending';
            } else if (statusText.includes('completed')) {
                badgeClass += ' completed';
            }
            
            return `<span class="${badgeClass}">${status}</span>`;
        }
        
        // ============ PROFESSIONAL FORM ENHANCEMENT SYSTEM ============
        
        // Initialize all form enhancements
        function initFormEnhancements() {
            // Auto-resize textareas
            autoResizeTextareas();
            
            // Enhanced file input preview
            initFilePreview();
            
            // Form validation enhancement
            initFormValidation();
            
            // Real-time validation
            initRealTimeValidation();
            
            // Number input formatting
            initNumberFormatting();
            
            // Auto-save functionality
            initAutoSave();
            
            // Professional tooltips
            initTooltips();
        }
        
        // Auto-resize textareas
        function autoResizeTextareas() {
            $('textarea').each(function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            }).on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
        
        // Enhanced file input preview
        function initFilePreview() {
            $('input[type="file"]').on('change', function(e) {
                const file = e.target.files[0];
                const $input = $(this);
                let $preview = $input.siblings('.file-preview');
                
                if (!$preview.length) {
                    $preview = $('<div class="file-preview mt-2"></div>');
                    $input.after($preview);
                }
                
                if (file) {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            $preview.html(`
                                <div class="preview-container">
                                    <img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                    <div class="file-info mt-2">
                                        <small class="text-muted">
                                            <i class="bi bi-file-image me-1"></i>
                                            ${file.name} (${(file.size / 1024).toFixed(1)} KB)
                                        </small>
                                    </div>
                                </div>
                            `);
                        };
                        reader.readAsDataURL(file);
                    } else {
                        $preview.html(`
                            <div class="file-info">
                                <i class="bi bi-file-earmark me-2"></i>
                                <span>${file.name}</span>
                                <small class="text-muted ms-2">(${(file.size / 1024).toFixed(1)} KB)</small>
                            </div>
                        `);
                    }
                } else {
                    $preview.empty();
                }
            });
        }
        
        // Form validation enhancement
        function initFormValidation() {
            $('.needs-validation').on('submit', function(e) {
                const form = this;
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Focus first invalid field
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Show toast notification
                        showToast('Please fill in all required fields correctly', 'warning');
                    }
                }
                form.classList.add('was-validated');
            });
        }
        
        // Real-time validation
        function initRealTimeValidation() {
            $('.form-control, .form-select').on('blur', function() {
                const $field = $(this);
                
                if (this.checkValidity()) {
                    $field.removeClass('is-invalid').addClass('is-valid');
                    $field.siblings('.invalid-feedback').hide();
                } else {
                    $field.removeClass('is-valid').addClass('is-invalid');
                    $field.siblings('.invalid-feedback').show();
                }
            });
            
            // Clear validation on input
            $('.form-control, .form-select').on('input', function() {
                if ($(this).hasClass('was-validated')) {
                    $(this).removeClass('is-valid is-invalid');
                }
            });
        }
        
        // Number input formatting
        function initNumberFormatting() {
            $('input[type="number"]').on('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value) && this.step && this.step.includes('.')) {
                    const decimals = this.step.split('.')[1].length;
                    this.value = value.toFixed(decimals);
                }
            });
            
            // Currency formatting
            $('input[data-type="currency"]').on('blur', function() {
                formatCurrency(this);
            });
            
            // Phone formatting
            $('input[data-type="phone"]').on('input', function() {
                formatPhone(this);
            });
        }
        
        // Auto-save functionality
        function initAutoSave() {
            $('form[data-autosave]').each(function() {
                const $form = $(this);
                const formId = $form.data('autosave');
                
                // Load saved data
                const savedData = localStorage.getItem(`autosave_${formId}`);
                if (savedData) {
                    try {
                        const data = JSON.parse(savedData);
                        Object.keys(data).forEach(key => {
                            const $field = $form.find(`[name="${key}"]`);
                            if ($field.length && $field.val() === '') {
                                $field.val(data[key]);
                            }
                        });
                        
                        if (Object.keys(data).length > 0) {
                            showToast('Form data restored from auto-save', 'info', 2000);
                        }
                    } catch (e) {
                        console.warn('Failed to restore auto-save data:', e);
                    }
                }
                
                // Save data on change
                $form.on('change input', 'input, select, textarea', function() {
                    const formData = $form.serializeArray();
                    const data = {};
                    formData.forEach(item => {
                        data[item.name] = item.value;
                    });
                    localStorage.setItem(`autosave_${formId}`, JSON.stringify(data));
                });
                
                // Clear on successful submit
                $form.on('submit', function() {
                    setTimeout(() => {
                        localStorage.removeItem(`autosave_${formId}`);
                    }, 1000);
                });
            });
        }
        
        // Initialize tooltips
        function initTooltips() {
            $('[data-bs-toggle="tooltip"]').tooltip();
            $('[title]').each(function() {
                if (!$(this).attr('data-bs-toggle')) {
                    $(this).attr('data-bs-toggle', 'tooltip');
                    $(this).tooltip();
                }
            });
        }
        
        // ============ FORM MODAL SYSTEM ============
        
        function showFormModal(title, content, onSave = null, size = 'lg') {
            const modalId = 'dynamicFormModal';
            let $modal = $(`#${modalId}`);
            
            if ($modal.length === 0) {
                $modal = $(`
                    <div class="modal fade" id="${modalId}" tabindex="-1">
                        <div class="modal-dialog modal-${size}">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body"></div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="modalSaveBtn">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                $('body').append($modal);
            }
            
            $modal.find('.modal-title').text(title);
            $modal.find('.modal-body').html(content);
            
            if (onSave) {
                $modal.find('#modalSaveBtn').off('click').on('click', onSave);
            } else {
                $modal.find('#modalSaveBtn').hide();
            }
            
            $modal.modal('show');
            
            // Initialize form enhancements in modal
            setTimeout(() => {
                initFormEnhancements();
            }, 100);
        }
        
        // ============ UTILITY FUNCTIONS ============
        
        function formatCurrency(input) {
            let value = parseFloat(input.value.replace(/[^\d.-]/g, ''));
            if (!isNaN(value)) {
                input.value = value.toFixed(2);
            }
        }
        
        function formatPhone(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
                if (value.length === 10) {
                    input.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                }
            }
        }
        
        function addFieldValidation(fieldSelector, validationFn, errorMessage) {
            $(fieldSelector).on('blur', function() {
                const isValid = validationFn(this.value);
                const $field = $(this);
                
                $field.removeClass('is-valid is-invalid');
                $field.siblings('.invalid-feedback').remove();
                
                if (isValid) {
                    $field.addClass('is-valid');
                } else {
                    $field.addClass('is-invalid');
                    $field.after(`<div class="invalid-feedback">${errorMessage}</div>`);
                }
            });
        }
        
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
        
        function validatePhone(phone) {
            return /^\(\d{3}\) \d{3}-\d{4}$/.test(phone) || /^\d{10}$/.test(phone.replace(/\D/g, ''));
        }
        
        function validateRequired(value) {
            return value && value.trim().length > 0;
        }
        
        // ============ ENHANCED TOAST SYSTEM ============
        
        function showFormToast(message, type = 'info', duration = 5000) {
            showToast(message, type, duration);
        }
        
        // ============ FORM SUBMISSION HELPERS ============
        
        function submitFormWithLoading(form, loadingText = 'Processing...') {
            const $form = $(form);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.html();
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $submitBtn.html(`<i class="bi bi-hourglass-split me-2"></i>${loadingText}`);
            
            // Add loading class to form
            $form.addClass('form-loading');
            
            // Reset after timeout (fallback)
            setTimeout(() => {
                $submitBtn.prop('disabled', false);
                $submitBtn.html(originalText);
                $form.removeClass('form-loading');
            }, 10000);
        }
        
        function resetFormLoading(form) {
            const $form = $(form);
            const $submitBtn = $form.find('button[type="submit"]');
            
            $submitBtn.prop('disabled', false);
            $form.removeClass('form-loading');
            
            // Restore original button text if available
            if ($submitBtn.data('original-text')) {
                $submitBtn.html($submitBtn.data('original-text'));
            }
        }
        
        // ============ INITIALIZE ON DOCUMENT READY ============
        
        $(document).ready(function() {
            // Initialize all form enhancements
            initFormEnhancements();
            
            // Auto-hide success alerts
            setTimeout(() => {
                $('.alert-success, .alert-info').fadeOut(400);
            }, 5000);
            
            // Initialize form submission handlers
            $('form').on('submit', function() {
                const $form = $(this);
                if (!$form.hasClass('no-loading')) {
                    submitFormWithLoading(this);
                }
            });
            
            console.log('✅ Professional Form Enhancement System Initialized');
        });
    </script>