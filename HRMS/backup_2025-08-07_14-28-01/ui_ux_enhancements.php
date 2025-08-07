<?php
$page_title = "UI/UX Enhancement Test";
require_once 'hrms_header_simple.php';
require_once 'hrms_sidebar_simple.php';

// Include HRMS UI fix
?>

<!-- Enhanced UI/UX Test Page -->
<!-- Page Content Starts Here -->
    <div class="container-fluid p-4">
        <!-- Page Header with Loading Animation -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="loading-spinner me-3" id="pageLoadSpinner">
                                <div class="spinner-border spinner-border-sm text-light" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-1">
                                    <i class="fas fa-magic me-2"></i>
                                    UI/UX Enhancement Demo
                                </h3>
                                <p class="mb-0 opacity-75">Showcasing improved user experience features</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Showcase Grid -->
        <div class="row g-4">
            <!-- Loading Animations -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Loading Animations
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Professional loading indicators for better user experience</p>
                        
                        <!-- Different Loading Types -->
                        <div class="mb-3">
                            <button class="btn btn-primary btn-sm me-2" onclick="showLoadingDemo('spinner')">
                                Spinner Loading
                            </button>
                            <button class="btn btn-outline-primary btn-sm me-2" onclick="showLoadingDemo('skeleton')">
                                Skeleton Loading
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="showLoadingDemo('progress')">
                                Progress Bar
                            </button>
                        </div>
                        
                        <!-- Loading Demo Area -->
                        <div id="loadingDemoArea" class="p-3 bg-light rounded">
                            <p class="text-center text-muted mb-0">Click a button above to see loading animations</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Validation -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Enhanced Form Validation
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Real-time validation with beautiful feedback</p>
                        
                        <form id="enhancedForm" novalidate>
                            <div class="mb-3">
                                <label for="employeeName" class="form-label">Employee Name</label>
                                <input type="text" class="form-control enhanced-input" id="employeeName" required>
                                <div class="invalid-feedback">Please provide a valid name.</div>
                                <div class="valid-feedback">Looks good!</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="employeeEmail" class="form-label">Email Address</label>
                                <input type="email" class="form-control enhanced-input" id="employeeEmail" required>
                                <div class="invalid-feedback">Please provide a valid email.</div>
                                <div class="valid-feedback">Valid email format!</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="employeeDept" class="form-label">Department</label>
                                <select class="form-select enhanced-input" id="employeeDept" required>
                                    <option value="">Choose...</option>
                                    <option value="HR">Human Resources</option>
                                    <option value="IT">Information Technology</option>
                                    <option value="Finance">Finance</option>
                                </select>
                                <div class="invalid-feedback">Please select a department.</div>
                                <div class="valid-feedback">Good choice!</div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save me-2"></i>
                                Save Employee
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Interactive Widgets -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-warning text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>
                            Interactive Widgets
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Dynamic and interactive dashboard components</p>
                        
                        <!-- Progress Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="progress-card p-3 bg-primary bg-opacity-10 rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-primary fw-semibold">Attendance</span>
                                        <span class="badge bg-primary">95%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-primary" style="width: 95%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="progress-card p-3 bg-success bg-opacity-10 rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small text-success fw-semibold">Performance</span>
                                        <span class="badge bg-success">88%</span>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-success" style="width: 88%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="showToast('Feature coming soon!', 'info')">
                                <i class="fas fa-bell me-2"></i>
                                Show Notification
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="toggleDarkMode()">
                                <i class="fas fa-moon me-2"></i>
                                Toggle Dark Mode
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Responsive Design Demo -->
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm hover-lift">
                    <div class="card-header bg-gradient-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-mobile-alt me-2"></i>
                            Responsive Design
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Adaptive layout for all device sizes</p>
                        
                        <!-- Device Preview -->
                        <div class="device-preview mb-3">
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <input type="radio" class="btn-check" name="devicePreview" id="desktop" checked>
                                <label class="btn btn-outline-secondary" for="desktop">
                                    <i class="fas fa-desktop"></i> Desktop
                                </label>
                                
                                <input type="radio" class="btn-check" name="devicePreview" id="tablet">
                                <label class="btn btn-outline-secondary" for="tablet">
                                    <i class="fas fa-tablet-alt"></i> Tablet
                                </label>
                                
                                <input type="radio" class="btn-check" name="devicePreview" id="mobile">
                                <label class="btn btn-outline-secondary" for="mobile">
                                    <i class="fas fa-mobile-alt"></i> Mobile
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info alert-sm">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>This system automatically adapts to different screen sizes for optimal user experience.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced CSS for UI/UX Features -->
<style>
/* Loading Animations */
.loading-spinner {
    transition: opacity 0.3s ease;
}

/* Card Hover Effects */
.hover-lift {
    transition: all 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

/* Enhanced Form Styles */
.enhanced-input {
    transition: all 0.3s ease;
    border: 2px solid #e9ecef;
}

.enhanced-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    transform: scale(1.02);
}

.enhanced-input.is-valid {
    border-color: #28a745;
    background-color: #f8fff9;
}

.enhanced-input.is-invalid {
    border-color: #dc3545;
    background-color: #fff8f8;
}

/* Progress Cards */
.progress-card {
    transition: all 0.3s ease;
    cursor: pointer;
}

.progress-card:hover {
    transform: scale(1.05);
}

/* Gradient Backgrounds */
.bg-gradient-primary { background: linear-gradient(135deg, #007bff, #0056b3); }
.bg-gradient-success { background: linear-gradient(135deg, #28a745, #1e7e34); }
.bg-gradient-info { background: linear-gradient(135deg, #17a2b8, #117a8b); }
.bg-gradient-warning { background: linear-gradient(135deg, #ffc107, #e0a800); }
.bg-gradient-dark { background: linear-gradient(135deg, #343a40, #23272b); }

/* Page Load Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}



/* Skeleton Loading */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
}

@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Dark Mode Variables */
:root {
    --bg-primary: #ffffff;
    --text-primary: #333333;
    --border-color: #e9ecef;
}

[data-theme="dark"] {
    --bg-primary: #2d3748;
    --text-primary: #f7fafc;
    --border-color: #4a5568;
}

/* Responsive utilities */
@media (max-width: 768px) {
    .hover-lift:hover {
        transform: none;
    }
    
    
}
</style>

<!-- Enhanced JavaScript for UI/UX Features -->
<script>
// Remove page loading spinner after content loads
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const spinner = document.getElementById('pageLoadSpinner');
        if (spinner) {
            spinner.style.opacity = '0';
            setTimeout(() => spinner.remove(), 300);
        }
    }, 1000);
    
    // Initialize enhanced form validation
    initEnhancedValidation();
});

// Loading Animation Demos
function showLoadingDemo(type) {
    const demoArea = document.getElementById('loadingDemoArea');
    
    switch(type) {
        case 'spinner':
            demoArea.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading data...</p>
                </div>
            `;
            break;
            
        case 'skeleton':
            demoArea.innerHTML = `
                <div class="skeleton rounded mb-2" style="height: 20px;"></div>
                <div class="skeleton rounded mb-2" style="height: 20px; width: 80%;"></div>
                <div class="skeleton rounded" style="height: 20px; width: 60%;"></div>
            `;
            break;
            
        case 'progress':
            demoArea.innerHTML = `
                <div class="progress mb-2">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         style="width: 0%" id="demoProgress"></div>
                </div>
                <p class="text-center mb-0">Loading: <span id="progressText">0%</span></p>
            `;
            
            // Animate progress bar
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                const progressBar = document.getElementById('demoProgress');
                const progressText = document.getElementById('progressText');
                
                if (progressBar) progressBar.style.width = progress + '%';
                if (progressText) progressText.textContent = progress + '%';
                
                if (progress >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        demoArea.innerHTML = '<div class="alert alert-success mb-0"><i class="fas fa-check me-2"></i>Loading complete!</div>';
                    }, 500);
                }
            }, 200);
            break;
    }
    
    // Reset after 5 seconds
    setTimeout(() => {
        demoArea.innerHTML = '<p class="text-center text-muted mb-0">Click a button above to see loading animations</p>';
    }, 5000);
}

// Enhanced Form Validation
function initEnhancedValidation() {
    const form = document.getElementById('enhancedForm');
    if (!form) return;
    
    const inputs = form.querySelectorAll('.enhanced-input');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            validateInput(this);
        });
        
        input.addEventListener('blur', function() {
            validateInput(this);
        });
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        let isValid = true;
        inputs.forEach(input => {
            if (!validateInput(input)) {
                isValid = false;
            }
        });
        
        if (isValid) {
            showToast('Form submitted successfully!', 'success');
            form.reset();
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
            });
        } else {
            showToast('Please fix the errors in the form', 'error');
        }
    });
}

function validateInput(input) {
    const value = input.value.trim();
    let isValid = true;
    
    // Required validation
    if (input.hasAttribute('required') && !value) {
        isValid = false;
    }
    
    // Email validation
    if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
        }
    }
    
    // Select validation
    if (input.tagName === 'SELECT' && !value) {
        isValid = false;
    }
    
    // Update classes
    input.classList.remove('is-valid', 'is-invalid');
    if (value) {
        input.classList.add(isValid ? 'is-valid' : 'is-invalid');
    }
    
    return isValid;
}

// Dark Mode Toggle
function toggleDarkMode() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    showToast(`Switched to ${newTheme} mode`, 'info');
}

// Toast Notification System (enhanced from header.php)
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    toast.style.backgroundColor = colors[type] || colors.info;
    
    const icon = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    
    toast.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-${icon[type] || icon.info}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Animate out
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Initialize theme from localStorage
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    }
});
</script>

<?php 
<?php require_once 'hrms_footer_simple.php'; ?>