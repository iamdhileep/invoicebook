<?php
// Test the relative path from pages/employees/ to delete_employee.php
echo "<h3>Delete Path Test from pages/employees/</h3>";

$relativePath = '../../delete_employee.php';
$absolutePath = realpath($relativePath);

echo "<p><strong>Relative path:</strong> $relativePath</p>";
echo "<p><strong>Resolved to:</strong> " . ($absolutePath ?: "❌ Path not found") . "</p>";
echo "<p><strong>File exists:</strong> " . (file_exists($relativePath) ? "✅ YES" : "❌ NO") . "</p>";

if (file_exists($relativePath)) {
    echo "<p><strong>File is readable:</strong> " . (is_readable($relativePath) ? "✅ YES" : "❌ NO") . "</p>";
    echo "<p><strong>File size:</strong> " . filesize($relativePath) . " bytes</p>";
}

echo "<hr>";
echo "<p><strong>Current working directory:</strong> " . getcwd() . "</p>";
echo "<p><strong>Script location:</strong> " . __FILE__ . "</p>";
echo "<p><strong>Script directory:</strong> " . __DIR__ . "</p>";

// Test AJAX call simulation
echo "<h4>AJAX Call Simulation:</h4>";
echo '<button onclick="testAjaxCall()">Test AJAX Delete Call</button>';
echo '<div id="ajaxResult"></div>';

echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
echo '<script>
function testAjaxCall() {
    console.log("Testing AJAX call to:", "../../delete_employee.php");
    
    $.post("../../delete_employee.php", {
        id: 999 // Non-existent ID for testing
    }, function(response) {
        console.log("AJAX Success:", response);
        $("#ajaxResult").html("<p style=\"color: green;\">✅ AJAX call successful: " + JSON.stringify(response) + "</p>");
    }, "json").fail(function(xhr, status, error) {
        console.error("AJAX Failed:", xhr.responseText);
        $("#ajaxResult").html("<p style=\"color: red;\">❌ AJAX call failed: " + error + "<br>Status: " + status + "<br>Response: " + xhr.responseText + "</p>");
    });
}
</script>';
?>