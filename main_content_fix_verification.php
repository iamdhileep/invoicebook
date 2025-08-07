<?php
session_start();
include 'config.php';
$page_title = 'Main Content Fix Verification';

include 'layouts/header.php';
include 'layouts/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <h1>Main Content Toggle Fix Verification</h1>
        
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle"></i> Toggle Test Instructions</h5>
                    <p>Click the hamburger menu (☰) in the header to test the sidebar toggle functionality.</p>
                    <p><strong>Expected behavior:</strong></p>
                    <ul>
                        <li>Sidebar should slide out to the left</li>
                        <li>Header should expand to full width</li>
                        <li>This main content area should expand to fill the space</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Real-time Status Monitor</h5>
                    </div>
                    <div class="card-body">
                        <div id="statusMonitor">
                            <p>Monitoring toggle states...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <div id="debugInfo">
                            <p>Checking elements...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>CSS Classes Applied</h5>
                    </div>
                    <div class="card-body">
                        <pre id="cssClasses">Loading...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateMonitor() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const header = document.getElementById('topHeader');
    
    const statusMonitor = document.getElementById('statusMonitor');
    const debugInfo = document.getElementById('debugInfo');
    const cssClasses = document.getElementById('cssClasses');
    
    if (statusMonitor) {
        statusMonitor.innerHTML = `
            <strong>Current States:</strong><br>
            Sidebar Collapsed: ${sidebar?.classList.contains('collapsed') || false}<br>
            Main Content Expanded: ${mainContent?.classList.contains('expanded') || false}<br>
            Header Full Width: ${header?.classList.contains('sidebar-collapsed') || false}<br>
            Window Width: ${window.innerWidth}px
        `;
    }
    
    if (debugInfo) {
        debugInfo.innerHTML = `
            <strong>Elements Found:</strong><br>
            Sidebar: ${!!sidebar}<br>
            Main Content: ${!!mainContent}<br>
            Header: ${!!header}<br>
            Toggle Button: ${!!document.getElementById('sidebarToggle')}
        `;
    }
    
    if (cssClasses) {
        cssClasses.textContent = `
Sidebar classes: ${sidebar?.className || 'Not found'}
Main Content classes: ${mainContent?.className || 'Not found'}
Header classes: ${header?.className || 'Not found'}
        `;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Verification page loaded');
    
    // Initial update
    updateMonitor();
    
    // Update every 2 seconds
    setInterval(updateMonitor, 2000);
    
    // Listen for toggle events
    document.addEventListener('sidebarToggle', function() {
        console.log('sidebarToggle event detected');
        setTimeout(updateMonitor, 100);
    });
    
    document.addEventListener('sidebarStateChanged', function(e) {
        console.log('sidebarStateChanged event detected:', e.detail);
        setTimeout(updateMonitor, 100);
    });
});
</script>

<?php include 'layouts/footer.php'; ?>
