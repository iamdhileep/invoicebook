<?php
/**
 * Advanced Features Implementation
 * Dark Mode, PWA Support, and Real-time Updates
 */

$page_title = "Advanced Features";
include 'layouts/header.php';
include 'layouts/sidebar.php';
include 'db.php';
?>

<div class="main-content">
    <div class="container-fluid p-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm bg-gradient-advanced text-white">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="advanced-icon me-3">
                                <i class="fas fa-rocket fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-1">Advanced Features Control Center</h3>
                                <p class="mb-0 opacity-75">Dark Mode, PWA Support, and Real-time Updates</p>
                            </div>
                            <div class="ms-auto">
                                <div class="feature-status d-flex align-items-center gap-3">
                                    <!-- Dark Mode Toggle Button -->
                                    <button class="btn btn-outline-light btn-sm" onclick="toggleTheme()" id="themeToggleBtn" title="Toggle Dark Mode (Ctrl+Shift+D)">
                                        <span id="themeIcon">🌓</span>
                                    </button>
                                    <span class="badge bg-success">Phase 3 Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Control Panel -->
        <div class="row mb-4">
            <!-- Dark Mode Control -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm hover-lift h-100">
                    <div class="card-header bg-gradient-dark text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-moon me-2"></i>Dark Mode
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Toggle between light and dark themes for optimal viewing experience.</p>
                        
                        <div class="theme-controls mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="darkModeToggle">
                                <label class="form-check-label" for="darkModeToggle">
                                    Enable Dark Mode
                                </label>
                            </div>
                        </div>
                        
                        <div class="theme-preview">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="theme-card light-theme active" data-theme="light">
                                        <div class="theme-header"></div>
                                        <div class="theme-content">
                                            <div class="theme-line"></div>
                                            <div class="theme-line short"></div>
                                        </div>
                                        <small>Light</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="theme-card dark-theme" data-theme="dark">
                                        <div class="theme-header"></div>
                                        <div class="theme-content">
                                            <div class="theme-line"></div>
                                            <div class="theme-line short"></div>
                                        </div>
                                        <small>Dark</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm w-100" onclick="toggleSystemTheme()">
                                <i class="fas fa-desktop me-2"></i>Auto (System)
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PWA Control -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm hover-lift h-100">
                    <div class="card-header bg-gradient-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-mobile-alt me-2"></i>PWA Features
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Progressive Web App capabilities for offline access and mobile installation.</p>
                        
                        <div class="pwa-status mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Service Worker:</span>
                                <span class="badge bg-secondary" id="swStatus">Checking...</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Offline Ready:</span>
                                <span class="badge bg-secondary" id="offlineStatus">Checking...</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Installable:</span>
                                <span class="badge bg-secondary" id="installStatus">Checking...</span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-sm" onclick="installPWA()" id="installBtn" disabled>
                                <i class="fas fa-download me-2"></i>Install App
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="updateServiceWorker()">
                                <i class="fas fa-sync me-2"></i>Update Cache
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-time Updates -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm hover-lift h-100">
                    <div class="card-header bg-gradient-success text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-wifi me-2"></i>Real-time Updates
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Live data updates and real-time notifications for enhanced user experience.</p>
                        
                        <div class="realtime-status mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Connection:</span>
                                <span class="badge bg-secondary" id="connectionStatus">Disconnected</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Auto-refresh:</span>
                                <span class="badge bg-secondary" id="autoRefreshStatus">Disabled</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small">Notifications:</span>
                                <span class="badge bg-secondary" id="notificationStatus">Disabled</span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-sm" onclick="enableRealTime()" id="realtimeBtn">
                                <i class="fas fa-play me-2"></i>Enable Real-time
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="testNotification()">
                                <i class="fas fa-bell me-2"></i>Test Notification
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feature Demonstrations -->
        <div class="row mb-4">
            <!-- Dark Mode Showcase -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-palette me-2"></i>Theme Showcase
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="theme-showcase" id="themeShowcase">
                            <div class="showcase-header">
                                <h6>Sample Dashboard</h6>
                                <div class="showcase-actions">
                                    <span class="showcase-btn">⚙️</span>
                                    <span class="showcase-btn">🔔</span>
                                    <span class="showcase-btn">👤</span>
                                </div>
                            </div>
                            <div class="showcase-content">
                                <div class="showcase-stat">
                                    <div class="stat-icon">👥</div>
                                    <div class="stat-info">
                                        <div class="stat-number">24</div>
                                        <div class="stat-label">Employees</div>
                                    </div>
                                </div>
                                <div class="showcase-stat">
                                    <div class="stat-icon">✅</div>
                                    <div class="stat-info">
                                        <div class="stat-number">18</div>
                                        <div class="stat-label">Present</div>
                                    </div>
                                </div>
                                <div class="showcase-chart">
                                    <div class="chart-bar" style="height: 60%;"></div>
                                    <div class="chart-bar" style="height: 80%;"></div>
                                    <div class="chart-bar" style="height: 40%;"></div>
                                    <div class="chart-bar" style="height: 90%;"></div>
                                    <div class="chart-bar" style="height: 70%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PWA Installation Guide -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-mobile-alt me-2"></i>PWA Installation
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="installation-steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <strong>Enable PWA Features</strong>
                                    <p class="small text-muted mb-0">Click "Enable PWA" to activate service worker and offline capabilities.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <strong>Install on Device</strong>
                                    <p class="small text-muted mb-0">Use browser's "Add to Home Screen" or our install button.</p>
                                </div>
                            </div>
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <strong>Enjoy Offline Access</strong>
                                    <p class="small text-muted mb-0">App works offline and provides native-like experience.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="alert alert-info alert-sm">
                                <i class="fas fa-info-circle me-2"></i>
                                <small>PWA features require HTTPS in production environments.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Activity Feed -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-gradient-warning text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-stream me-2"></i>Real-time Activity Feed
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-feed" id="activityFeed">
                            <div class="activity-item">
                                <div class="activity-icon bg-success">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">System Initialized</div>
                                    <div class="activity-time"><?php echo date('H:i:s'); ?></div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon bg-info">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">Advanced Features Loaded</div>
                                    <div class="activity-time"><?php echo date('H:i:s'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Real-time updates will appear here when enabled
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Features Styles -->
<style>
/* Advanced Features Theme Variables */
:root {
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #dee2e6;
    --shadow-color: rgba(0,0,0,0.1);
    --accent-color: #007bff;
}

[data-theme="dark"] {
    --bg-primary: #1a1a1a;
    --bg-secondary: #2d2d2d;
    --text-primary: #ffffff;
    --text-secondary: #b3b3b3;
    --border-color: #404040;
    --shadow-color: rgba(0,0,0,0.3);
    --accent-color: #4dabf7;
}

/* Apply theme variables */
body {
    background-color: var(--bg-primary);
    color: var(--text-primary);
    transition: background-color 0.3s ease, color 0.3s ease;
}

.card {
    background-color: var(--bg-primary);
    border-color: var(--border-color);
    box-shadow: 0 2px 10px var(--shadow-color);
}

.text-muted {
    color: var(--text-secondary) !important;
}

/* Advanced Features Gradients */
.bg-gradient-advanced {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.advanced-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Theme Toggle Button */
#themeToggleBtn {
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}

#themeToggleBtn:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: scale(1.1);
}

#themeToggleBtn:active {
    transform: scale(0.95);
}

#themeIcon {
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

/* Theme Toggle Button Dark Mode Adjustments */
[data-theme="dark"] #themeToggleBtn {
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.15);
}

[data-theme="dark"] #themeToggleBtn:hover {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.6);
}

/* Theme Preview Cards */
.theme-card {
    border: 2px solid transparent;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
}

.theme-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.theme-card.active {
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.light-theme {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    color: #333;
}

.dark-theme {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: #fff;
}

.theme-header {
    height: 20px;
    background: var(--accent-color);
    border-radius: 4px;
    margin-bottom: 8px;
}

.theme-content {
    margin-bottom: 8px;
}

.theme-line {
    height: 4px;
    background: currentColor;
    opacity: 0.3;
    border-radius: 2px;
    margin-bottom: 4px;
}

.theme-line.short {
    width: 60%;
}

/* Theme Showcase */
.theme-showcase {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 16px;
    background: var(--bg-secondary);
    transition: all 0.3s ease;
}

.showcase-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border-color);
}

.showcase-actions {
    display: flex;
    gap: 8px;
}

.showcase-btn {
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.3s ease;
}

.showcase-btn:hover {
    opacity: 1;
}

.showcase-content {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.showcase-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: var(--bg-primary);
    border-radius: 6px;
    border: 1px solid var(--border-color);
}

.stat-icon {
    font-size: 1.5rem;
}

.stat-number {
    font-size: 1.25rem;
    font-weight: bold;
    color: var(--accent-color);
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.showcase-chart {
    display: flex;
    align-items: end;
    gap: 4px;
    height: 60px;
    flex: 1;
    padding: 8px;
}

.chart-bar {
    background: var(--accent-color);
    width: 20px;
    border-radius: 2px;
    transition: all 0.3s ease;
}

/* Installation Steps */
.installation-steps {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.step-number {
    background: var(--accent-color);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: bold;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

/* Activity Feed */
.activity-feed {
    max-height: 300px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 500;
    margin-bottom: 2px;
}

.activity-time {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Hover Effects */
.hover-lift {
    transition: all 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px var(--shadow-color);
}

/* Responsive Design */
@media (max-width: 768px) {
    .showcase-content {
        flex-direction: column;
    }
    
    .showcase-chart {
        justify-content: center;
    }
    
    .hover-lift:hover {
        transform: none;
    }
}

/* PWA Status Indicators */
.badge.bg-success { background-color: #28a745 !important; }
.badge.bg-danger { background-color: #dc3545 !important; }
.badge.bg-warning { background-color: #ffc107 !important; color: #000; }

/* Dark mode specific adjustments */
[data-theme="dark"] .card {
    background-color: var(--bg-secondary);
}

[data-theme="dark"] .theme-showcase {
    background-color: var(--bg-primary);
}

[data-theme="dark"] .showcase-stat {
    background-color: var(--bg-secondary);
}

/* Animation for theme transitions */
* {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
}
</style>

<!-- Advanced Features JavaScript -->
<script>
// Advanced Features Management
class AdvancedFeatures {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.pwaDeferredPrompt = null;
        this.serviceWorker = null;
        this.realtimeConnection = null;
        
        this.init();
    }
    
    init() {
        this.initTheme();
        this.initPWA();
        this.initRealtime();
        this.bindEvents();
        this.updateStatus();
    }
    
    // Theme Management
    initTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
        
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = this.theme === 'dark';
        }
        
        // Set initial theme button icon
        const themeIcon = document.getElementById('themeIcon');
        if (themeIcon) {
            themeIcon.textContent = this.theme === 'dark' ? '☀️' : '🌙';
        }
        
        this.updateThemeCards();
    }
    
    setTheme(theme) {
        this.theme = theme;
        localStorage.setItem('theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        
        const toggle = document.getElementById('darkModeToggle');
        if (toggle) {
            toggle.checked = theme === 'dark';
        }
        
        // Update theme button icon
        const themeIcon = document.getElementById('themeIcon');
        if (themeIcon) {
            themeIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
        
        this.updateThemeCards();
        this.addActivity(`Theme changed to ${theme} mode`, 'palette');
    }
    
    updateThemeCards() {
        document.querySelectorAll('.theme-card').forEach(card => {
            card.classList.remove('active');
            if (card.dataset.theme === this.theme) {
                card.classList.add('active');
            }
        });
    }
    
    toggleTheme() {
        const newTheme = this.theme === 'light' ? 'dark' : 'light';
        this.setTheme(newTheme);
    }
    
    // PWA Management
    async initPWA() {
        // Check if service worker is supported
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/billbook/sw.js');
                this.serviceWorker = registration;
                this.updatePWAStatus('swStatus', 'Active', 'success');
                
                // Check for updates
                registration.addEventListener('updatefound', () => {
                    this.addActivity('App update available', 'download');
                });
                
            } catch (error) {
                this.updatePWAStatus('swStatus', 'Failed', 'danger');
                console.error('SW registration failed:', error);
            }
        } else {
            this.updatePWAStatus('swStatus', 'Not Supported', 'secondary');
        }
        
        // Check if app is installable
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.pwaDeferredPrompt = e;
            this.updatePWAStatus('installStatus', 'Available', 'success');
            document.getElementById('installBtn').disabled = false;
        });
        
        // Check if app is already installed
        window.addEventListener('appinstalled', () => {
            this.updatePWAStatus('installStatus', 'Installed', 'success');
            this.addActivity('App installed successfully', 'mobile-alt');
        });
        
        // Check offline capability
        this.checkOfflineCapability();
    }
    
    async installPWA() {
        if (this.pwaDeferredPrompt) {
            this.pwaDeferredPrompt.prompt();
            const { outcome } = await this.pwaDeferredPrompt.userChoice;
            
            if (outcome === 'accepted') {
                this.addActivity('App installation accepted', 'check-circle');
            } else {
                this.addActivity('App installation declined', 'times-circle');
            }
            
            this.pwaDeferredPrompt = null;
            document.getElementById('installBtn').disabled = true;
        }
    }
    
    checkOfflineCapability() {
        if (navigator.onLine) {
            this.updatePWAStatus('offlineStatus', 'Ready', 'success');
        } else {
            this.updatePWAStatus('offlineStatus', 'Offline', 'warning');
        }
        
        window.addEventListener('online', () => {
            this.updatePWAStatus('offlineStatus', 'Back Online', 'success');
            this.addActivity('Connection restored', 'wifi');
        });
        
        window.addEventListener('offline', () => {
            this.updatePWAStatus('offlineStatus', 'Offline Mode', 'warning');
            this.addActivity('Working offline', 'wifi');
        });
    }
    
    // Real-time Features
    initRealtime() {
        // Simulate WebSocket connection (in production, use actual WebSocket)
        this.updateRealtimeStatus('connectionStatus', 'Ready', 'secondary');
        this.updateRealtimeStatus('autoRefreshStatus', 'Ready', 'secondary');
        
        // Check notification permission
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                this.updateRealtimeStatus('notificationStatus', 'Enabled', 'success');
            } else if (Notification.permission === 'denied') {
                this.updateRealtimeStatus('notificationStatus', 'Blocked', 'danger');
            } else {
                this.updateRealtimeStatus('notificationStatus', 'Permission Needed', 'warning');
            }
        }
    }
    
    async enableRealtime() {
        // Request notification permission
        if ('Notification' in window && Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.updateRealtimeStatus('notificationStatus', 'Enabled', 'success');
            }
        }
        
        // Start real-time connection simulation
        this.updateRealtimeStatus('connectionStatus', 'Connected', 'success');
        this.updateRealtimeStatus('autoRefreshStatus', 'Active', 'success');
        
        document.getElementById('realtimeBtn').innerHTML = '<i class="fas fa-stop me-2"></i>Disable Real-time';
        document.getElementById('realtimeBtn').onclick = () => this.disableRealtime();
        
        this.addActivity('Real-time updates enabled', 'wifi');
        
        // Simulate periodic updates
        this.startRealtimeUpdates();
    }
    
    disableRealtime() {
        this.updateRealtimeStatus('connectionStatus', 'Disconnected', 'secondary');
        this.updateRealtimeStatus('autoRefreshStatus', 'Disabled', 'secondary');
        
        document.getElementById('realtimeBtn').innerHTML = '<i class="fas fa-play me-2"></i>Enable Real-time';
        document.getElementById('realtimeBtn').onclick = () => this.enableRealtime();
        
        this.addActivity('Real-time updates disabled', 'pause');
        
        if (this.realtimeInterval) {
            clearInterval(this.realtimeInterval);
        }
    }
    
    startRealtimeUpdates() {
        // Simulate real-time data updates every 30 seconds
        this.realtimeInterval = setInterval(() => {
            const updates = [
                'New employee checked in',
                'Leave request submitted',
                'Attendance report generated',
                'System performance optimized',
                'Database backup completed'
            ];
            
            const randomUpdate = updates[Math.floor(Math.random() * updates.length)];
            this.addActivity(randomUpdate, 'bell');
        }, 30000);
    }
    
    async testNotification() {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('HRMS Test Notification', {
                body: 'Real-time notifications are working correctly!',
                icon: '/icon-192x192.png',
                badge: '/icon-192x192.png'
            });
            
            this.addActivity('Test notification sent', 'bell');
        } else {
            alert('Notifications not available or permission not granted');
        }
    }
    
    // Utility Methods
    updatePWAStatus(elementId, text, status) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
            element.className = `badge bg-${status}`;
        }
    }
    
    updateRealtimeStatus(elementId, text, status) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
            element.className = `badge bg-${status}`;
        }
    }
    
    addActivity(message, icon = 'info-circle') {
        const feed = document.getElementById('activityFeed');
        if (!feed) return;
        
        const time = new Date().toLocaleTimeString();
        const statusColors = ['success', 'info', 'warning', 'primary'];
        const randomColor = statusColors[Math.floor(Math.random() * statusColors.length)];
        
        const activityItem = document.createElement('div');
        activityItem.className = 'activity-item';
        activityItem.innerHTML = `
            <div class="activity-icon bg-${randomColor}">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="activity-content">
                <div class="activity-title">${message}</div>
                <div class="activity-time">${time}</div>
            </div>
        `;
        
        feed.insertBefore(activityItem, feed.firstChild);
        
        // Keep only last 10 activities
        while (feed.children.length > 10) {
            feed.removeChild(feed.lastChild);
        }
        
        // Animate new item
        activityItem.style.opacity = '0';
        activityItem.style.transform = 'translateY(-20px)';
        
        setTimeout(() => {
            activityItem.style.transition = 'all 0.3s ease';
            activityItem.style.opacity = '1';
            activityItem.style.transform = 'translateY(0)';
        }, 100);
    }
    
    updateStatus() {
        // Update all status indicators
        this.addActivity('Advanced features initialized', 'rocket');
    }
    
    bindEvents() {
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        if (darkModeToggle) {
            darkModeToggle.addEventListener('change', () => this.toggleTheme());
        }
        
        // Theme card clicks
        document.querySelectorAll('.theme-card').forEach(card => {
            card.addEventListener('click', () => {
                this.setTheme(card.dataset.theme);
            });
        });
        
        // Install PWA button
        const installBtn = document.getElementById('installBtn');
        if (installBtn) {
            installBtn.addEventListener('click', () => this.installPWA());
        }
    }
}

// Global functions for button events
function toggleTheme() {
    if (advancedFeatures) {
        advancedFeatures.toggleTheme();
    }
}

function toggleSystemTheme() {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    advancedFeatures.setTheme(prefersDark ? 'dark' : 'light');
}

function installPWA() {
    advancedFeatures.installPWA();
}

function updateServiceWorker() {
    if (advancedFeatures.serviceWorker) {
        advancedFeatures.serviceWorker.update();
        advancedFeatures.addActivity('Service worker updated', 'sync');
    }
}

function enableRealTime() {
    advancedFeatures.enableRealtime();
}

function testNotification() {
    advancedFeatures.testNotification();
}

// Initialize when DOM is loaded
let advancedFeatures;
document.addEventListener('DOMContentLoaded', function() {
    advancedFeatures = new AdvancedFeatures();
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Shift+D for dark mode toggle
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            advancedFeatures.toggleTheme();
        }
        
        // Ctrl+Shift+N for test notification
        if (e.ctrlKey && e.shiftKey && e.key === 'N') {
            e.preventDefault();
            advancedFeatures.testNotification();
        }
    });
});
</script>

<?php include 'layouts/footer.php'; ?>
