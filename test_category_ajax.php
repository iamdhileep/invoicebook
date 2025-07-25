<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Category Test</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Category System Test</h2>
        
        <div class="card">
            <div class="card-body">
                <h5>Test Dynamic Category Loading</h5>
                <select id="testCategorySelect" class="form-select">
                    <option value="">Loading categories...</option>
                </select>
                <button id="refreshBtn" class="btn btn-primary mt-2">Refresh Categories</button>
                <div id="result" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
    function loadCategories() {
        $('#testCategorySelect').html('<option value="">Loading categories...</option>');
        $('#result').html('<div class="alert alert-info">Loading...</div>');
        
        $.ajax({
            url: 'get_categories.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                console.log('Categories loaded:', data);
                
                let options = '<option value="">-- Select Category --</option>';
                
                if (Array.isArray(data) && data.length > 0) {
                    data.forEach(function(category) {
                        options += `<option value="${category.name}">${category.name}</option>`;
                    });
                    $('#result').html(`<div class="alert alert-success">✅ Successfully loaded ${data.length} categories</div>`);
                } else {
                    options += '<option value="">No categories found</option>';
                    $('#result').html('<div class="alert alert-warning">⚠️ No categories found</div>');
                }
                
                $('#testCategorySelect').html(options);
            },
            error: function(xhr, status, error) {
                console.error('Error loading categories:', xhr.responseText);
                $('#testCategorySelect').html('<option value="">Error loading categories</option>');
                $('#result').html(`<div class="alert alert-danger">❌ Error: ${xhr.status} - ${xhr.responseText}</div>`);
            }
        });
    }
    
    // Load categories on page load
    $(document).ready(function() {
        loadCategories();
        
        $('#refreshBtn').click(function() {
            loadCategories();
        });
    });
    </script>
</body>
</html>
