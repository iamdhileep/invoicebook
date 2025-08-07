<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$page_title = 'Digital Transformation';
include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-lightning-charge-fill text-warning me-2"></i>
                    Digital Transformation
                    <span class="badge bg-warning ms-2">AI</span>
                </h1>
                <p class="text-muted">AI-Driven Digital Innovation & Process Automation</p>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-gradient-warning text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning-fill me-2"></i>
                            Digital Innovation Hub
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Digital Transformation Center</h6>
                            <p class="mb-0">AI-powered digital transformation tools for process automation, workflow optimization, and digital innovation.</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-robot display-4 text-warning"></i>
                                        <h6>Process Automation</h6>
                                        <p class="small text-muted">AI workflow automation</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-diagram-3 display-4 text-primary"></i>
                                        <h6>Digital Workflows</h6>
                                        <p class="small text-muted">Smart process design</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-cloud-arrow-up display-4 text-info"></i>
                                        <h6>Cloud Integration</h6>
                                        <p class="small text-muted">Digital infrastructure</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-shield-check display-4 text-success"></i>
                                        <h6>Security & Compliance</h6>
                                        <p class="small text-muted">Digital governance</p>
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

<?php include 'layouts/footer.php'; ?>
