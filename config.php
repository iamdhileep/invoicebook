<?php
// Simple configuration file for path management

// Get the root directory path
$rootDir = __DIR__;

// Database connection
$dbPath = $rootDir . '/db.php';

// Check if we're in a subdirectory and calculate relative path to root
function getRelativePathToRoot() {
    $currentPath = $_SERVER['SCRIPT_NAME'];
    $rootPath = '/';
    
    // Count directory levels from root
    $levels = substr_count($currentPath, '/') - 1;
    
    if (strpos($currentPath, '/pages/') !== false) {
        $levels = 2; // pages are 2 levels deep
    }
    
    $relativePath = '';
    for ($i = 0; $i < $levels; $i++) {
        $relativePath .= '../';
    }
    
    return $relativePath ?: './';
}

// Set the relative path
$relativePath = getRelativePathToRoot();

// Define commonly used paths
define('ROOT_RELATIVE', $relativePath);
define('DB_FILE', $relativePath . 'db.php');
define('LOGIN_FILE', $relativePath . 'login.php');
define('LOGOUT_FILE', $relativePath . 'logout.php');

// Layout files
define('HEADER_FILE', $relativePath . 'layouts/header.php');
define('SIDEBAR_FILE', $relativePath . 'layouts/sidebar.php');
define('FOOTER_FILE', $relativePath . 'layouts/footer.php');
?>