<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Dashboard' ?> - Business Management System</title>
    
    <!-- Preconnect to external domains for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Critical CSS - Load immediately -->
    <style>
        /* Critical above-the-fold styles */
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f8f9fa;
        }
        .loading { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #f8f9fa;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
    <!-- Load CSS asynchronously to prevent blocking -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"></noscript>
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
</head>
<body>
    <!-- Loading screen -->
    <div id="loadingScreen" class="loading">
        <div class="spinner"></div>
    </div>
    
    <!-- Main content will be shown after resources load -->
    <div id="mainContent" style="display: none;">
    
    <script>
        // Load non-critical CSS after page load
        window.addEventListener('load', function() {
            // Load DataTables CSS only when needed
            const datatablesCss = document.createElement('link');
            datatablesCss.rel = 'stylesheet';
            datatablesCss.href = 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css';
            document.head.appendChild(datatablesCss);
            
            // Load Google Fonts
            const googleFonts = document.createElement('link');
            googleFonts.rel = 'stylesheet';
            googleFonts.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap';
            document.head.appendChild(googleFonts);
            
            // Hide loading screen and show content
            setTimeout(function() {
                document.getElementById('loadingScreen').style.display = 'none';
                document.getElementById('mainContent').style.display = 'block';
            }, 500);
        });
        
        // Fallback: show content after 3 seconds even if resources haven't loaded
        setTimeout(function() {
            document.getElementById('loadingScreen').style.display = 'none';
            document.getElementById('mainContent').style.display = 'block';
        }, 3000);
    </script>
