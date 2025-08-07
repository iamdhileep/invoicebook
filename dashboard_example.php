<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BillBook HRMS</title>
</head>
<body>
    <!-- Include the sidebar component -->
    <?php include 'layouts/sidebar.php'; ?>
    
    <!-- Main content area -->
    <div class="main-content-wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <h1 class="card-title">Welcome to BillBook HRMS</h1>
                            <p class="card-text">This is your main dashboard. The sidebar has been properly reverted to be a reusable component.</p>
                            
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle-fill"></i> Sidebar Component Reverted</h5>
                                <p>✅ Clean PHP component without HTML document structure<br>
                                ✅ Can be included in any page with <code>&lt;?php include 'layouts/sidebar.php'; ?&gt;</code><br>
                                ✅ All CSS and JavaScript functionality preserved<br>
                                ✅ Professional design and responsive behavior maintained</p>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card border-primary">
                                        <div class="card-body text-center">
                                            <i class="bi bi-toggles2 text-primary" style="font-size: 2rem;"></i>
                                            <h5 class="card-title mt-2">Toggle Working</h5>
                                            <p class="card-text">Click the toggle button to test sidebar functionality</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-success">
                                        <div class="card-body text-center">
                                            <i class="bi bi-menu-down text-success" style="font-size: 2rem;"></i>
                                            <h5 class="card-title mt-2">Dropdowns Active</h5>
                                            <p class="card-text">All HRM module dropdowns working perfectly</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-info">
                                        <div class="card-body text-center">
                                            <i class="bi bi-phone text-info" style="font-size: 2rem;"></i>
                                            <h5 class="card-title mt-2">Mobile Ready</h5>
                                            <p class="card-text">Responsive design for all screen sizes</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
