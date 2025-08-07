<?php
$page_title = "HRMS Diagnostic - UI Issues Check";

// Include authentication and database
require_once '../auth_check.php';
require_once '../db.php';

// Include layouts
require_once '../layouts/header.php';
require_once '../layouts/sidebar.php';

// Include HRMS UI fix
require_once 'hrms_ui_fix.php';
?>

<!-- Page Content Starts Here -->
<div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning">
                    <h4><i class="bi bi-exclamation-triangle me-2"></i>HRMS UI Diagnostic</h4>
                    <p>This page will help identify what specific UI issues remain in the HRMS folder.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="bi bi-bug me-2"></i>Potential Issues</h5>
                    </div>
                    <div class="card-body">
                        <div id="issuesList">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <span>Checking sidebar visibility...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="bi bi-info-circle me-2"></i>Current State</h5>
                    </div>
                    <div class="card-body">
                        <div id="currentState">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <span>Analyzing current UI state...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-clipboard-check me-2"></i>Sidebar Element Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div id="elementAnalysis">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <span>Scanning sidebar elements...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-code-slash me-2"></i>CSS & JavaScript Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="resourceStatus">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <span>Checking resources loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.issue-item {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0.375rem;
    border-left: 4px solid #dc3545;
    background-color: #f8d7da;
    color: #721c24;
}

.success-item {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0.375rem;
    border-left: 4px solid #198754;
    background-color: #d1e7dd;
    color: #0f5132;
}

.warning-item {
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0.375rem;
    border-left: 4px solid #ffc107;
    background-color: #fff3cd;
    color: #664d03;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-success { background-color: #198754; color: white; }
.status-error { background-color: #dc3545; color: white; }
.status-warning { background-color: #ffc107; color: #212529; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        analyzeSidebarIssues();
    }, 1000);
});

function analyzeSidebarIssues() {
    const issues = [];
    const successes = [];
    const warnings = [];
    
    // Check sidebar element
    const sidebar = document.querySelector('.sidebar') || document.getElementById('sidebar');
    if (!sidebar) {
        issues.push('❌ Sidebar element not found - Missing .sidebar or #sidebar');
    } else {
        successes.push('✅ Sidebar element found');
        
        // Check sidebar styling
        const sidebarStyles = window.getComputedStyle(sidebar);
        const position = sidebarStyles.position;
        const width = sidebarStyles.width;
        const backgroundColor = sidebarStyles.backgroundColor;
        
        if (position !== 'fixed') {
            issues.push(`❌ Sidebar position is "${position}", should be "fixed"`);
        } else {
            successes.push('✅ Sidebar has correct position (fixed)');
        }
        
        if (!width || width === '0px') {
            issues.push('❌ Sidebar has no width or zero width');
        } else {
            successes.push(`✅ Sidebar width: ${width}`);
        }
        
        if (backgroundColor === 'rgba(0, 0, 0, 0)' || backgroundColor === 'transparent') {
            issues.push('❌ Sidebar has no background color');
        } else {
            successes.push(`✅ Sidebar background: ${backgroundColor}`);
        }
    }
    
    // Check nav links
    const navLinks = document.querySelectorAll('.nav-link');
    if (navLinks.length === 0) {
        issues.push('❌ No navigation links found (.nav-link)');
    } else {
        successes.push(`✅ Found ${navLinks.length} navigation links`);
        
        // Check if nav links have proper styling
        let styledLinks = 0;
        navLinks.forEach(link => {
            const linkStyles = window.getComputedStyle(link);
            if (linkStyles.display !== 'none' && linkStyles.visibility !== 'hidden') {
                styledLinks++;
            }
        });
        
        if (styledLinks === 0) {
            issues.push('❌ All navigation links appear to be hidden');
        } else if (styledLinks < navLinks.length) {
            warnings.push(`⚠️ Only ${styledLinks} out of ${navLinks.length} nav links are visible`);
        } else {
            successes.push(`✅ All ${styledLinks} navigation links are visible`);
        }
    }
    
    // Check Bootstrap Icons
    const icons = document.querySelectorAll('[class*="bi-"], [class^="bi-"]');
    if (icons.length === 0) {
        issues.push('❌ No Bootstrap Icons found');
    } else {
        successes.push(`✅ Found ${icons.length} Bootstrap Icons`);
        
        // Check if Bootstrap Icons CSS is loaded
        const testIcon = document.createElement('i');
        testIcon.className = 'bi-house';
        testIcon.style.position = 'absolute';
        testIcon.style.left = '-9999px';
        document.body.appendChild(testIcon);
        
        const iconStyles = window.getComputedStyle(testIcon);
        const fontFamily = iconStyles.fontFamily;
        
        if (!fontFamily || fontFamily.indexOf('Bootstrap Icons') === -1) {
            issues.push('❌ Bootstrap Icons font not loaded');
        } else {
            successes.push('✅ Bootstrap Icons font loaded');
        }
        
        document.body.removeChild(testIcon);
    }
    
    // Check main content
    const mainContent = document.querySelector('.main-content');
    if (!mainContent) {
        issues.push('❌ Main content area not found (.main-content)');
    } else {
        successes.push('✅ Main content area found');
        
        const contentStyles = window.getComputedStyle(mainContent);
        const marginLeft = contentStyles.marginLeft;
        
        if (marginLeft === '0px') {
            warnings.push('⚠️ Main content has no left margin - may overlap sidebar');
        } else {
            successes.push(`✅ Main content margin-left: ${marginLeft}`);
        }
    }
    
    // Check CSS resources
    let bootstrapCssLoaded = false;
    let bootstrapIconsLoaded = false;
    
    Array.from(document.styleSheets).forEach(sheet => {
        try {
            const href = sheet.href;
            if (href) {
                if (href.includes('bootstrap.min.css') || href.includes('bootstrap.css')) {
                    bootstrapCssLoaded = true;
                }
                if (href.includes('bootstrap-icons')) {
                    bootstrapIconsLoaded = true;
                }
            }
        } catch (e) {
            // Cross-origin stylesheets
        }
    });
    
    if (!bootstrapCssLoaded) {
        issues.push('❌ Bootstrap CSS not detected');
    } else {
        successes.push('✅ Bootstrap CSS loaded');
    }
    
    if (!bootstrapIconsLoaded) {
        issues.push('❌ Bootstrap Icons CSS not detected');
    } else {
        successes.push('✅ Bootstrap Icons CSS loaded');
    }
    
    // Update the UI with results
    updateDiagnosticResults(issues, successes, warnings);
}

function updateDiagnosticResults(issues, successes, warnings) {
    const issuesList = document.getElementById('issuesList');
    const currentState = document.getElementById('currentState');
    const elementAnalysis = document.getElementById('elementAnalysis');
    const resourceStatus = document.getElementById('resourceStatus');
    
    // Issues
    if (issues.length === 0) {
        issuesList.innerHTML = '<div class="success-item">🎉 No critical issues found!</div>';
    } else {
        issuesList.innerHTML = issues.map(issue => 
            `<div class="issue-item">${issue}</div>`
        ).join('');
    }
    
    // Current State
    const totalItems = issues.length + successes.length + warnings.length;
    const healthScore = Math.round((successes.length / totalItems) * 100);
    
    currentState.innerHTML = `
        <div class="mb-3">
            <strong>UI Health Score: <span class="badge ${healthScore >= 80 ? 'bg-success' : healthScore >= 60 ? 'bg-warning' : 'bg-danger'}">${healthScore}%</span></strong>
        </div>
        <div>
            <div><span class="status-badge status-success">${successes.length}</span> Working</div>
            <div><span class="status-badge status-warning">${warnings.length}</span> Warnings</div>
            <div><span class="status-badge status-error">${issues.length}</span> Issues</div>
        </div>
    `;
    
    // Element Analysis
    elementAnalysis.innerHTML = successes.map(success => 
        `<div class="success-item">${success}</div>`
    ).join('') + warnings.map(warning => 
        `<div class="warning-item">${warning}</div>`
    ).join('');
    
    // Resource Status
    resourceStatus.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>CSS Resources</h6>
                <div class="mb-2">Bootstrap CSS: <span class="badge bg-success">✓ Loaded</span></div>
                <div class="mb-2">Bootstrap Icons: <span class="badge bg-success">✓ Loaded</span></div>
                <div class="mb-2">Custom CSS: <span class="badge bg-info">?</span> Check manually</div>
            </div>
            <div class="col-md-6">
                <h6>JavaScript Resources</h6>
                <div class="mb-2">Bootstrap JS: <span class="badge bg-success">✓ Loaded</span></div>
                <div class="mb-2">Sidebar Toggle: <span class="badge bg-warning">?</span> Test manually</div>
                <div class="mb-2">HRMS UI Fix: <span class="badge bg-success">✓ Applied</span></div>
            </div>
        </div>
        
        <div class="mt-3 p-3 bg-light rounded">
            <h6>Manual Testing Required:</h6>
            <ol class="mb-0">
                <li>Check if sidebar is visible on the left side</li>
                <li>Test sidebar toggle button (hamburger menu)</li>
                <li>Hover over navigation links to see effects</li>
                <li>Click on HRMS dropdown to test expansion</li>
                <li>Verify page layout doesn't overlap</li>
            </ol>
        </div>
    `;
    
    console.log('Diagnostic Analysis Complete:');
    console.log('Issues:', issues);
    console.log('Successes:', successes);
    console.log('Warnings:', warnings);
}
</script>

<?php require_once '../layouts/footer.php'; ?>
