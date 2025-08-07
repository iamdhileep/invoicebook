<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'HRMS Dashboard' ?> - BillBook Pro</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
        }
        
        /* HRMS SIDEBAR STYLES - SIMPLIFIED */
        .hrms-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }
        
        .hrms-sidebar-header {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
        }
        
        .hrms-nav-item {
            margin: 0;
        }
        
        .hrms-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .hrms-nav-link:hover {
            background-color: #f1f5f9;
            color: #2563eb;
            border-left-color: #3b82f6;
            text-decoration: none;
        }
        
        .hrms-nav-link.active {
            background-color: #eff6ff;
            color: #1e40af;
            border-left-color: #2563eb;
            font-weight: 600;
        }
        
        .hrms-nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            text-align: center;
            font-size: 1rem;
        }
        
        .hrms-nav-section {
            margin-top: 1.5rem;
        }
        
        .hrms-nav-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9ca3af;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
        }
        
        /* MAIN CONTENT AREA */
        .hrms-main-content {
            margin-left: 280px;
            padding: 0;
            min-height: 100vh;
        }
        
        .hrms-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .hrms-content {
            padding: 2rem;
        }
        
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hrms-sidebar {
                transform: translateX(-100%);
            }
            
            .hrms-sidebar.show {
                transform: translateX(0);
            }
            
            .hrms-main-content {
                margin-left: 0;
            }
            
            .hrms-sidebar-toggle {
                display: block !important;
            }
        }
        
        .hrms-sidebar-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: #2563eb;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
