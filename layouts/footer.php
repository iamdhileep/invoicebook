    </div> <!-- End main-content -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Hide loader after page loads
            $('#pageLoader').fadeOut(500);

            // Sidebar toggle functionality
            $('#sidebarToggle').click(function() {
                $('#sidebar').toggleClass('collapsed');
                $('.main-content').toggleClass('expanded');
                
                // Store sidebar state in localStorage
                const isCollapsed = $('#sidebar').hasClass('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            });

            // Restore sidebar state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                $('#sidebar').addClass('collapsed');
                $('.main-content').addClass('expanded');
            }

            // Mobile sidebar toggle
            $(window).resize(function() {
                if ($(window).width() <= 768) {
                    $('#sidebar').removeClass('collapsed').addClass('collapsed');
                    $('.main-content').addClass('expanded');
                } else {
                    if (!localStorage.getItem('sidebarCollapsed') || localStorage.getItem('sidebarCollapsed') === 'false') {
                        $('#sidebar').removeClass('collapsed');
                        $('.main-content').removeClass('expanded');
                    }
                }
            });

            // Initialize DataTables with modern styling
            if ($('.data-table').length) {
                $('.data-table').DataTable({
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    responsive: true,
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "No entries found",
                        infoFiltered: "(filtered from _MAX_ total entries)",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                });
            }

            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Form validation styling
            $('form').on('submit', function(e) {
                const form = this;
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                $(form).addClass('was-validated');
            });

            // Auto-hide alerts after 5 seconds
            $('.alert').delay(5000).fadeOut(300);

            // Confirm delete actions
            $('.btn-delete, .delete-btn').click(function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });

            // Number formatting
            $('.currency').each(function() {
                const value = parseFloat($(this).text());
                if (!isNaN(value)) {
                    $(this).text('₹ ' + value.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                }
            });

            // Auto-calculate totals in forms
            $(document).on('input', '.qty, .price', function() {
                calculateRowTotal($(this).closest('.item-row'));
                calculateGrandTotal();
            });

            function calculateRowTotal(row) {
                const qty = parseFloat(row.find('.qty').val()) || 0;
                const price = parseFloat(row.find('.price').val()) || 0;
                const total = qty * price;
                row.find('.total').val(total.toFixed(2));
            }

            function calculateGrandTotal() {
                let grandTotal = 0;
                $('.total').each(function() {
                    grandTotal += parseFloat($(this).val()) || 0;
                });
                $('#grandTotal, .grand-total').val(grandTotal.toFixed(2));
            }

            // Search functionality
            $('.search-input').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                const targetTable = $(this).data('target');
                
                $(targetTable + ' tbody tr').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(searchTerm) > -1);
                });
            });

            // Print functionality
            $('.btn-print').click(function() {
                window.print();
            });

            // Export functionality
            $('.btn-export').click(function() {
                const type = $(this).data('type');
                const url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank');
                }
            });
        });

        // Global functions for backward compatibility
        function showLoader() {
            $('#pageLoader').show();
        }

        function hideLoader() {
            $('#pageLoader').hide();
        }

        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('.main-content').prepend(alertHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.alert').first().fadeOut(300);
            }, 5000);
        }

        // AJAX helper function
        function makeAjaxRequest(url, data, successCallback, errorCallback) {
            showLoader();
            
            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    hideLoader();
                    if (successCallback) successCallback(response);
                },
                error: function(xhr, status, error) {
                    hideLoader();
                    if (errorCallback) {
                        errorCallback(xhr, status, error);
                    } else {
                        showAlert('An error occurred: ' + error, 'danger');
                    }
                }
            });
        }
    </script>

    <?php if (isset($additional_scripts)): ?>
        <?= $additional_scripts ?>
    <?php endif; ?>

</body>
</html>