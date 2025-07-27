<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

echo "<!-- Starting page load -->\n";
flush();

$page_title = 'Time Tracking - Fast Load';

echo "<!-- About to include header -->\n";
flush();

include '../../layouts/header.php';

echo "<!-- Header loaded -->\n";
flush();

include '../../layouts/sidebar.php';

echo "<!-- Sidebar loaded -->\n";
flush();
?>

<div class="main-content">
    <div class="container-fluid">
        <div class="alert alert-success">
            <h4>Fast Loading Time Tracking Page</h4>
            <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>If you can see this, the basic structure is working!</p>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Test</h5>
                    </div>
                    <div class="card-body">
                        <p>This is a simplified version to test loading speed.</p>
                        <button class="btn btn-primary">Test Button</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo "<!-- About to include footer -->\n";
flush();

include '../../layouts/footer.php';

echo "<!-- Footer loaded -->\n";
flush();
?>
