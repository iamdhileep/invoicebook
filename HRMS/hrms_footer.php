        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light text-center py-3 mt-5">
        <div class="container">
            <small class="text-muted">
                &copy; <?= date('Y') ?> BillBook HRMS. All rights reserved. 
                <span class="mx-2">|</span>
                <a href="#" class="text-decoration-none">Privacy Policy</a>
                <span class="mx-2">|</span>
                <a href="#" class="text-decoration-none">Terms of Service</a>
            </small>
        </div>
    </footer>

    <!-- Bootstrap 5.3.2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom HRMS Scripts -->
    <script>
        // Global HRMS functions
        
        // Show loading spinner
        function showLoading(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            if (element) {
                element.innerHTML = '<span class="loading"></span> Loading...';
                element.disabled = true;
            }
        }
        
        // Hide loading spinner
        function hideLoading(element, originalText = 'Submit') {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            if (element) {
                element.innerHTML = originalText;
                element.disabled = false;
            }
        }
        
        // Show toast notification
        function showToast(message, type = 'info', duration = 3000) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });
            
            const iconMap = {
                'success': 'success',
                'error': 'error',
                'warning': 'warning',
                'info': 'info',
                'danger': 'error'
            };
            
            Toast.fire({
                icon: iconMap[type] || 'info',
                title: message
            });
        }
        
        // Confirm dialog
        function confirmAction(message, callback, title = 'Are you sure?') {
            Swal.fire({
                title: title,
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && typeof callback === 'function') {
                    callback();
                }
            });
        }
        
        // Format currency
        function formatCurrency(amount, currency = '₹') {
            return currency + parseFloat(amount).toLocaleString('en-IN', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }
        
        // Format date
        function formatDate(dateString, format = 'short') {
            const date = new Date(dateString);
            const options = format === 'long' ? 
                { year: 'numeric', month: 'long', day: 'numeric' } :
                { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-IN', options);
        }
        
        // AJAX helper
        function hrmsAjax(url, data, callback, method = 'POST') {
            fetch(url, {
                method: method,
                headers: method === 'POST' ? {
                    'Content-Type': 'application/x-www-form-urlencoded'
                } : {},
                body: method === 'POST' ? (data instanceof FormData ? data : new URLSearchParams(data)) : null
            })
            .then(response => response.json())
            .then(callback)
            .catch(error => {
                console.error('AJAX Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }
        
        // Form validation helper
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return false;
            
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                field.classList.remove('is-invalid');
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });
            
            return isValid;
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-dismissible')) {
                        const closeBtn = alert.querySelector('.btn-close');
                        if (closeBtn) closeBtn.click();
                    }
                });
            }, 5000);
        });
        
        // Add animation classes on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('slide-up');
                }
            });
        }, observerOptions);
        
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => observer.observe(card));
        });
    </script>
</body>
</html>
