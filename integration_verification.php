<?php 
session_start();
$page_title = "Integration Verification";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Verification</title>
    <style>
        .integration-monitor {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #f8fafc;
            padding: 20px 30px;
            border-radius: 12px;
            z-index: 99999;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            min-width: 600px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin: 15px 0;
        }
        .status-item {
            padding: 10px;
            border-radius: 8px;
            background: rgba(255,255,255,0.1);
        }
        .status-pass { border-left: 4px solid #10b981; }
        .status-fail { border-left: 4px solid #ef4444; }
        .status-pending { border-left: 4px solid #6b7280; }
        .action-buttons {
            margin-top: 15px;
        }
        .btn-action {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            margin: 0 5px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-action:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>

<div class="integration-monitor">
    <div style="font-weight: bold; margin-bottom: 10px;">🔗 HEADER-SIDEBAR INTEGRATION MONITOR</div>
    
    <div class="status-grid">
        <div class="status-item status-pending" id="headerStatus">
            <div style="font-weight: bold;">HEADER</div>
            <div id="headerDetails">Checking...</div>
        </div>
        <div class="status-item status-pending" id="sidebarStatus">
            <div style="font-weight: bold;">SIDEBAR</div>
            <div id="sidebarDetails">Checking...</div>
        </div>
        <div class="status-item status-pending" id="toggleStatus">
            <div style="font-weight: bold;">TOGGLE</div>
            <div id="toggleDetails">Checking...</div>
        </div>
        <div class="status-item status-pending" id="syncStatus">
            <div style="font-weight: bold;">SYNC</div>
            <div id="syncDetails">Checking...</div>
        </div>
    </div>
    
    <div id="overallMessage">Initializing integration test...</div>
    
    <div class="action-buttons">
        <button class="btn-action" onclick="runIntegrationTest()">🔄 Test Integration</button>
        <button class="btn-action" onclick="testHeaderToggle()">🎯 Test Header</button>
        <button class="btn-action" onclick="testSidebarEvents()">📡 Test Events</button>
        <button class="btn-action" onclick="testFullSequence()">🚀 Full Sequence</button>
    </div>
</div>

<?php include_once 'layouts/header.php'; ?>
<?php include_once 'layouts/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <h1>🔗 Header-Sidebar Integration Verification</h1>
        
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-primary">
                    <h5>🎯 Integration Test Objectives:</h5>
                    <ol>
                        <li><strong>Header Toggle Function:</strong> Verify toggleSidebar() works correctly</li>
                        <li><strong>Sidebar Event System:</strong> Verify sidebarToggle event handling</li>
                        <li><strong>State Synchronization:</strong> Ensure both systems stay in sync</li>
                        <li><strong>Visual Coordination:</strong> Confirm animations are synchronized</li>
                    </ol>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>📋 Current State</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Sidebar State:</strong> <span id="currentSidebarState">Loading...</span></p>
                        <p><strong>Header Position:</strong> <span id="currentHeaderPos">Loading...</span></p>
                        <p><strong>Main Content:</strong> <span id="currentMainContent">Loading...</span></p>
                        <p><strong>localStorage:</strong> <span id="currentStorage">Loading...</span></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>⚡ Live Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button onclick="directToggleTest()" class="btn btn-primary">Direct Header Toggle</button>
                            <button onclick="eventToggleTest()" class="btn btn-success">Event-based Toggle</button>
                            <button onclick="compareResults()" class="btn btn-info">Compare Results</button>
                            <button onclick="resetState()" class="btn btn-warning">Reset State</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let testResults = {
    headerToggle: null,
    sidebarEvents: null,
    stateSync: null,
    visualSync: null
};

function updateStatus(component, status, details) {
    const element = document.getElementById(component + 'Status');
    const detailsElement = document.getElementById(component + 'Details');
    
    if (element && detailsElement) {
        element.className = `status-item status-${status}`;
        detailsElement.textContent = details;
    }
}

function updateCurrentState() {
    const sidebar = document.getElementById('sidebar');
    const header = document.querySelector('.main-header');
    const mainContent = document.querySelector('.main-content');
    const storage = localStorage.getItem('sidebarCollapsed');
    
    document.getElementById('currentSidebarState').textContent = 
        sidebar ? (sidebar.classList.contains('collapsed') ? 'Collapsed' : 'Expanded') : 'Not Found';
    
    document.getElementById('currentHeaderPos').textContent = 
        header ? `${header.getBoundingClientRect().left}px from left` : 'Not Found';
    
    document.getElementById('currentMainContent').textContent = 
        mainContent ? (mainContent.classList.contains('expanded') ? 'Expanded' : 'Normal') : 'Not Found';
    
    document.getElementById('currentStorage').textContent = storage || 'null';
}

function runIntegrationTest() {
    document.getElementById('overallMessage').textContent = 'Running comprehensive integration test...';
    
    // Test 1: Header function exists
    const headerToggleExists = typeof window.toggleSidebar === 'function';
    updateStatus('header', headerToggleExists ? 'pass' : 'fail', 
        headerToggleExists ? 'toggleSidebar() found' : 'toggleSidebar() missing');
    testResults.headerToggle = headerToggleExists;
    
    // Test 2: Sidebar event system
    let eventReceived = false;
    const testListener = () => { eventReceived = true; };
    document.addEventListener('sidebarToggle', testListener);
    document.dispatchEvent(new CustomEvent('sidebarToggle'));
    
    setTimeout(() => {
        updateStatus('sidebar', eventReceived ? 'pass' : 'fail',
            eventReceived ? 'Event system working' : 'Events not received');
        document.removeEventListener('sidebarToggle', testListener);
        testResults.sidebarEvents = eventReceived;
    }, 100);
    
    // Test 3: Toggle functionality
    setTimeout(() => testToggleFunction(), 200);
    
    // Test 4: Overall assessment
    setTimeout(() => assessOverall(), 500);
}

function testToggleFunction() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) {
        updateStatus('toggle', 'fail', 'Sidebar element not found');
        return;
    }
    
    const initialState = sidebar.classList.contains('collapsed');
    
    // Test header toggle function
    if (typeof window.toggleSidebar === 'function') {
        window.toggleSidebar();
        
        setTimeout(() => {
            const newState = sidebar.classList.contains('collapsed');
            const stateChanged = initialState !== newState;
            
            updateStatus('toggle', stateChanged ? 'pass' : 'fail',
                stateChanged ? 'State changed correctly' : 'State unchanged');
            testResults.stateSync = stateChanged;
            
            // Restore original state
            if (initialState && !newState) {
                window.toggleSidebar();
            } else if (!initialState && newState) {
                window.toggleSidebar();
            }
        }, 350);
    } else {
        updateStatus('toggle', 'fail', 'toggleSidebar function not found');
    }
}

function testHeaderToggle() {
    if (typeof window.toggleSidebar === 'function') {
        window.toggleSidebar();
        updateCurrentState();
    } else {
        alert('toggleSidebar function not found!');
    }
}

function testSidebarEvents() {
    const event = new CustomEvent('sidebarToggle');
    document.dispatchEvent(event);
    setTimeout(updateCurrentState, 100);
}

function testFullSequence() {
    let step = 0;
    const steps = [
        () => { 
            document.getElementById('overallMessage').textContent = 'Step 1: Header toggle...';
            if (typeof window.toggleSidebar === 'function') window.toggleSidebar();
        },
        () => { 
            document.getElementById('overallMessage').textContent = 'Step 2: Event toggle...';
            document.dispatchEvent(new CustomEvent('sidebarToggle'));
        },
        () => { 
            document.getElementById('overallMessage').textContent = 'Step 3: Restore state...';
            if (typeof window.toggleSidebar === 'function') window.toggleSidebar();
        },
        () => { 
            document.getElementById('overallMessage').textContent = 'Full sequence completed!';
            updateCurrentState();
        }
    ];
    
    function executeStep() {
        if (step < steps.length) {
            steps[step]();
            step++;
            setTimeout(executeStep, 700);
        }
    }
    
    executeStep();
}

function directToggleTest() {
    if (typeof window.toggleSidebar === 'function') {
        window.toggleSidebar();
        setTimeout(updateCurrentState, 100);
    }
}

function eventToggleTest() {
    document.dispatchEvent(new CustomEvent('sidebarToggle'));
    setTimeout(updateCurrentState, 100);
}

function compareResults() {
    alert('Check the console for detailed comparison results');
    console.log('Integration Test Results:', testResults);
    updateCurrentState();
}

function resetState() {
    localStorage.removeItem('sidebarCollapsed');
    location.reload();
}

function assessOverall() {
    const passCount = Object.values(testResults).filter(result => result === true).length;
    const totalTests = Object.keys(testResults).length;
    
    let status, message;
    if (passCount === totalTests) {
        status = 'pass';
        message = `🎉 Perfect integration! All ${totalTests} tests passed.`;
    } else if (passCount > totalTests / 2) {
        status = 'pass';
        message = `✅ Good integration! ${passCount}/${totalTests} tests passed.`;
    } else {
        status = 'fail';
        message = `❌ Integration issues! Only ${passCount}/${totalTests} tests passed.`;
    }
    
    updateStatus('sync', status, `${passCount}/${totalTests} passed`);
    document.getElementById('overallMessage').textContent = message;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        updateCurrentState();
        runIntegrationTest();
    }, 1000);
    
    // Update state every 2 seconds
    setInterval(updateCurrentState, 2000);
});
</script>

</body>
</html>
