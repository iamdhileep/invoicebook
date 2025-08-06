    </div> <!-- End Main Content -->

    <!-- Modern Footer -->
    <footer class="main-footer" style="margin-left: var(--sidebar-width); transition: var(--transition-slow); background: var(--white); border-top: 1px solid var(--gray-200); padding: 1.5rem 2rem; margin-top: auto;">
        <div class="d-flex justify-content-between align-items-center text-sm">
            <div class="text-muted">
                © <?= date('Y') ?> Business Management System. All rights reserved.
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted">Powered by</span>
                <span class="gradient-text font-weight-bold">ModernUI</span>
                <span class="text-muted">|</span>
                <a href="#" class="text-decoration-none" style="color: var(--primary-color);" onclick="showHelp()">Support</a>
            </div>
        </div>
    </footer>

    <!-- Core JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables with Extensions -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

    <!-- Modern UI Enhancement Scripts -->
    <script>
        // Global UI Configuration
        const ModernUI = {
            config: {
                animationDuration: 300,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
                theme: localStorage.getItem('theme') || 'light'
            },
            
            // Initialize the UI system
            init() {
                this.setupSidebar();
                this.setupLoader();
                this.setupToasts();
                this.setupDataTables();
                this.setupForms();
                this.setupGlobalSearch();
                this.setupTheme();
                this.setupAnimations();
            },
            
            // Sidebar Management
            setupSidebar() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.main-content');
                const footer = document.querySelector('.main-footer');
                const sidebarToggle = document.getElementById('sidebarToggle');
                
                // Apply saved state
                if (this.config.sidebarCollapsed) {
                    sidebar?.classList.add('collapsed');
                    mainContent?.classList.add('expanded');
                    if (footer) footer.style.marginLeft = '0';
                }
                
                // Toggle functionality
                sidebarToggle?.addEventListener('click', () => {
                    const isCollapsed = sidebar?.classList.toggle('collapsed');
                    mainContent?.classList.toggle('expanded');
                    
                    if (footer) {
                        footer.style.marginLeft = isCollapsed ? '0' : 'var(--sidebar-width)';
                    }
                    
                    // Save state
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                    this.config.sidebarCollapsed = isCollapsed;
                });
                
                // Close sidebar on mobile when clicking outside
                document.addEventListener('click', (e) => {
                    if (window.innerWidth <= 768) {
                        if (!sidebar?.contains(e.target) && !sidebarToggle?.contains(e.target)) {
                            sidebar?.classList.remove('show');
                        }
                    }
                });
            },
            
            // Page Loader
            setupLoader() {
                window.addEventListener('load', () => {
                    const loader = document.getElementById('pageLoader');
                    if (loader) {
                        setTimeout(() => {
                            loader.style.opacity = '0';
                            setTimeout(() => {
                                loader.style.display = 'none';
                            }, 300);
                        }, 500);
                    }
                });
            },
            
            // Toast Notifications
            setupToasts() {
                // Create toast container if it doesn't exist
                if (!document.getElementById('toast-container')) {
                    const container = document.createElement('div');
                    container.id = 'toast-container';
                    container.className = 'position-fixed top-0 end-0 p-3';
                    container.style.zIndex = '1055';
                    document.body.appendChild(container);
                }
            },
            
            // Enhanced DataTables Setup
            setupDataTables() {
                // Global DataTables defaults
                $.extend(true, $.fn.dataTable.defaults, {
                    responsive: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    dom: '<"row"<"col-md-6"l><"col-md-6"f>>' +
                         '<"row"<"col-12"t>>' +
                         '<"row"<"col-md-5"i><"col-md-7"p>>',
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        },
                        emptyTable: "No data available",
                        zeroRecords: "No matching records found"
                    }
                });
            },
            
            // Form Enhancements
            setupForms() {
                // Add floating labels effect
                document.querySelectorAll('.form-control').forEach(input => {
                    input.addEventListener('focus', function() {
                        this.parentElement?.classList.add('focused');
                    });
                    
                    input.addEventListener('blur', function() {
                        if (!this.value) {
                            this.parentElement?.classList.remove('focused');
                        }
                    });
                });
                
                // Auto-save functionality for forms
                document.querySelectorAll('form[data-autosave]').forEach(form => {
                    const formId = form.dataset.autosave;
                    
                    // Load saved data
                    const savedData = localStorage.getItem(`form_${formId}`);
                    if (savedData) {
                        try {
                            const data = JSON.parse(savedData);
                            Object.keys(data).forEach(key => {
                                const field = form.querySelector(`[name="${key}"]`);
                                if (field) field.value = data[key];
                            });
                        } catch (e) {
                            // Failed to load saved form data
                        }
                    }
                    
                    // Save on change
                    form.addEventListener('input', () => {
                        const formData = new FormData(form);
                        const data = Object.fromEntries(formData.entries());
                        localStorage.setItem(`form_${formId}`, JSON.stringify(data));
                    });
                    
                    // Clear on submit
                    form.addEventListener('submit', () => {
                        localStorage.removeItem(`form_${formId}`);
                    });
                });
            },
            
            // Global Search Functionality
            setupGlobalSearch() {
                const searchInput = document.getElementById('globalSearch');
                if (searchInput) {
                    let searchTimeout;
                    
                    searchInput.addEventListener('input', function(e) {
                        clearTimeout(searchTimeout);
                        const query = e.target.value.toLowerCase().trim();
                        
                        if (query.length < 2) return;
                        
                        searchTimeout = setTimeout(() => {
                            // Highlight matching navigation items
                            document.querySelectorAll('.nav-link').forEach(link => {
                                const text = link.textContent.toLowerCase();
                                if (text.includes(query)) {
                                    link.style.background = 'rgba(99, 102, 241, 0.1)';
                                } else {
                                    link.style.background = '';
                                }
                            });
                        }, 300);
                    });
                    
                    // Clear highlights when search is cleared
                    searchInput.addEventListener('blur', () => {
                        setTimeout(() => {
                            document.querySelectorAll('.nav-link').forEach(link => {
                                link.style.background = '';
                            });
                        }, 200);
                    });
                }
            },
            
            // Theme Management
            setupTheme() {
                // Theme toggle functionality could be added here
                document.documentElement.setAttribute('data-theme', this.config.theme);
            },
            
            // Animation System
            setupAnimations() {
                // Intersection Observer for scroll animations
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-fade-in-up');
                        }
                    });
                }, observerOptions);
                
                // Observe cards and important elements
                document.querySelectorAll('.card, .alert, .table').forEach(el => {
                    observer.observe(el);
                });
            },
            
            // Utility Functions
            showToast(message, type = 'info', duration = 5000) {
                const toastContainer = document.getElementById('toast-container');
                const toastId = 'toast-' + Date.now();
                
                const toast = document.createElement('div');
                toast.id = toastId;
                toast.className = `toast align-items-center text-white bg-${type} border-0`;
                toast.setAttribute('role', 'alert');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                `;
                
                toastContainer.appendChild(toast);
                const bsToast = new bootstrap.Toast(toast, { delay: duration });
                bsToast.show();
                
                // Auto-remove after hiding
                toast.addEventListener('hidden.bs.toast', () => {
                    toast.remove();
                });
            },
            
            showConfirm(message, callback) {
                if (confirm(message)) {
                    callback();
                }
            },
            
            // AJAX Helper
            async apiRequest(url, options = {}) {
                try {
                    const response = await fetch(url, {
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        ...options
                    });
                    
                    return await response.json();
                } catch (error) {
                    this.showToast(`Request failed: ${error.message}`, 'danger');
                    throw error;
                }
            }
        };
        
        // Initialize Modern UI when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            ModernUI.init();
        });
        
        // Global helper functions for backward compatibility
        function showAlert(message, type = 'info') {
            ModernUI.showToast(message, type);
        }
        
        function showConfirm(message, callback) {
            ModernUI.showConfirm(message, callback);
        }
        
        // Responsive sidebar for mobile
        window.addEventListener('resize', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth <= 768) {
                sidebar?.classList.add('collapsed');
                mainContent?.classList.add('expanded');
            } else if (!ModernUI.config.sidebarCollapsed) {
                sidebar?.classList.remove('collapsed');
                mainContent?.classList.remove('expanded');
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K for global search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('globalSearch')?.focus();
            }
            
            // Ctrl/Cmd + B for sidebar toggle
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                document.getElementById('sidebarToggle')?.click();
            }
        });
        
        // Performance monitoring
        window.addEventListener('load', () => {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            // Page loaded in ${loadTime}ms
        });
        
        // PWA Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/billbook/HRMS/sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                        
                        // Check for updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content available, show update prompt
                                    showUpdateAvailableNotification();
                                }
                            });
                        });
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
        
        // Update notification
        function showUpdateAvailableNotification() {
            if (confirm('A new version of the app is available. Would you like to refresh to get the latest features?')) {
                window.location.reload();
            }
        }
        
        // Install PWA prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button if available
            const installBtn = document.createElement('div');
            installBtn.className = 'position-fixed bottom-0 end-0 m-3 p-3 bg-primary text-white rounded shadow';
            installBtn.style.cursor = 'pointer';
            installBtn.style.zIndex = '9999';
            installBtn.innerHTML = `
                <i class="fas fa-download me-2"></i>
                Install HRMS App
                <button class="btn btn-sm btn-light ms-2" onclick="this.parentElement.remove()">×</button>
            `;
            
            installBtn.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    deferredPrompt = null;
                    installBtn.remove();
                }
            });
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (document.body.contains(installBtn)) {
                    installBtn.remove();
                }
            }, 10000);
            
            document.body.appendChild(installBtn);
        });
    </script>
    
    <!-- Page-specific scripts can be added here -->
    <?php if (isset($page_scripts)): ?>
        <?= $page_scripts ?>
    <?php endif; ?>

</body>
</html>