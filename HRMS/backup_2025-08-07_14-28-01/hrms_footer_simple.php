    </div> <!-- End hrms-content -->
</div> <!-- End hrms-main-content -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleHrmsSidebar() {
    const sidebar = document.getElementById('hrmsSidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar on mobile when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('hrmsSidebar');
    const toggle = document.querySelector('.hrms-sidebar-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('hrmsSidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});

console.log('HRMS Simple Layout Loaded Successfully');
</script>

</body>
</html>
