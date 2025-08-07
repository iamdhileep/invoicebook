        </div>
    </div>

    <!-- Bootstrap 5.3.2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom HRMS JavaScript -->
    <script>
        // Sidebar Toggle for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Dark Mode Toggle
        function toggleDarkMode() {
            const body = document.body;
            const icon = document.querySelector('.dark-mode-toggle i');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.className = 'bi bi-moon-stars-fill';
                localStorage.setItem('darkMode', 'false');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'bi bi-sun-fill';
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Initialize dark mode from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const darkMode = localStorage.getItem('darkMode');
            const icon = document.querySelector('.dark-mode-toggle i');
            
            if (darkMode === 'true') {
                document.body.setAttribute('data-theme', 'dark');
                if (icon) icon.className = 'bi bi-sun-fill';
            }

            // Auto-close sidebar on mobile when clicking outside
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.querySelector('.mobile-toggle');
                
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            });

            console.log('✅ HRMS Layout System Loaded Successfully');
        });

        // Page visibility handling for auto-refresh
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Page became visible - could trigger data refresh
                console.log('📱 Page visibility: visible');
            }
        });
    </script>
</body>
</html>