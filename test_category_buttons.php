<!DOCTYPE html>
<html>
<head>
    <title>Category Button Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="p-4">
    <div class="container">
        <h2>Category Functionality Test</h2>
        
        <div class="card">
            <div class="card-body">
                <h5>Test Quick Action Buttons</h5>
                
                <div class="input-group mb-3">
                    <select class="form-select" id="categorySelect">
                        <option value="">-- Loading Categories... --</option>
                    </select>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-success" id="quickAddBtn" title="Quick Add Category">
                            <i class="bi bi-plus-circle"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning" id="quickEditBtn" title="Edit Selected Category">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="quickDeleteBtn" title="Delete Selected Category">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <input type="text" 
                           class="form-control" 
                           placeholder="Enter new category name and press Enter" 
                           style="display: none;" 
                           id="newCategoryInput">
                </div>
                
                <div id="debugLog" class="mt-3">
                    <h6>Debug Log:</h6>
                    <div id="logContent" style="height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; font-family: monospace; font-size: 12px;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function log(message) {
        const timestamp = new Date().toLocaleTimeString();
        $('#logContent').append(`[${timestamp}] ${message}<br>`);
        $('#logContent').scrollTop($('#logContent')[0].scrollHeight);
        console.log(message);
    }
    
    function loadCategories() {
        log('Loading categories from server...');
        
        $.ajax({
            url: 'get_categories.php',
            method: 'GET',
            dataType: 'json',
            success: function(data) {
                log('Categories loaded: ' + JSON.stringify(data));
                
                const $select = $('#categorySelect');
                $select.empty();
                $select.append('<option value="">-- Select Category --</option>');
                
                if (data && data.length > 0) {
                    data.forEach(function(category) {
                        $select.append(`<option value="${category.name}">${category.name}</option>`);
                    });
                }
                
                $select.append('<option value="__new__" class="text-primary">+ Add New Category</option>');
                log('Dropdown populated with ' + data.length + ' categories');
            },
            error: function(xhr, status, error) {
                log('ERROR loading categories: ' + error + ' - ' + xhr.responseText);
            }
        });
    }
    
    function addCategory(name) {
        log('Adding category: ' + name);
        
        $.ajax({
            url: 'save_category.php',
            method: 'POST',
            data: { name: name },
            dataType: 'json',
            success: function(response) {
                log('Add category response: ' + JSON.stringify(response));
                if (response.success) {
                    log('Category added successfully, reloading dropdown');
                    loadCategories();
                    setTimeout(function() {
                        $('#categorySelect').val(name);
                        log('Selected new category: ' + name);
                    }, 100);
                } else {
                    log('ERROR: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                log('ERROR adding category: ' + error + ' - ' + xhr.responseText);
            }
        });
    }
    
    $(document).ready(function() {
        log('Page loaded, initializing...');
        
        // Load categories on start
        loadCategories();
        
        // Handle dropdown change
        $('#categorySelect').change(function() {
            const value = $(this).val();
            log('Dropdown changed to: ' + value);
            
            if (value === '__new__') {
                log('Showing new category input');
                $('#categorySelect').hide();
                $('#newCategoryInput').show().focus();
            } else {
                log('Showing category select');
                $('#categorySelect').show();
                $('#newCategoryInput').hide();
            }
        });
        
        // Quick Add button
        $('#quickAddBtn').click(function(e) {
            e.preventDefault();
            log('Quick Add button clicked');
            $('#categorySelect').val('__new__').trigger('change');
        });
        
        // Quick Edit button
        $('#quickEditBtn').click(function(e) {
            e.preventDefault();
            log('Quick Edit button clicked');
            
            const selectedValue = $('#categorySelect').val();
            log('Selected value for edit: ' + selectedValue);
            
            if (!selectedValue || selectedValue === '__new__') {
                alert('Please select a category to edit first.');
                return;
            }
            
            const newName = prompt('Enter new name for category "' + selectedValue + '":', selectedValue);
            if (newName && newName.trim() !== '' && newName !== selectedValue) {
                log('Editing category from "' + selectedValue + '" to "' + newName + '"');
                // Edit functionality would go here
            }
        });
        
        // Quick Delete button
        $('#quickDeleteBtn').click(function(e) {
            e.preventDefault();
            log('Quick Delete button clicked');
            
            const selectedValue = $('#categorySelect').val();
            log('Selected value for delete: ' + selectedValue);
            
            if (!selectedValue || selectedValue === '__new__') {
                alert('Please select a category to delete first.');
                return;
            }
            
            if (confirm('Delete category "' + selectedValue + '"?')) {
                log('Deleting category: ' + selectedValue);
                // Delete functionality would go here
            }
        });
        
        // Handle new category input
        $('#newCategoryInput').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                const categoryName = $(this).val().trim();
                log('Enter pressed in new category input: ' + categoryName);
                
                if (categoryName) {
                    addCategory(categoryName);
                    $(this).val('').hide();
                    $('#categorySelect').show();
                }
            }
        });
        
        // Handle escape key
        $('#newCategoryInput').on('keyup', function(e) {
            if (e.which === 27) { // Escape key
                log('Escape pressed, canceling new category');
                $(this).val('').hide();
                $('#categorySelect').show().val('');
            }
        });
        
        log('All event handlers attached');
    });
    </script>
</body>
</html>
