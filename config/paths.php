<?php
// Path configuration for the business management system

// Define the root directory
define('ROOT_PATH', dirname(dirname(__FILE__)));

// Define paths relative to root
define('DB_PATH', ROOT_PATH . '/db.php');
define('LOGIN_PATH', ROOT_PATH . '/login.php');
define('LOGOUT_PATH', ROOT_PATH . '/logout.php');

// Layout paths
define('LAYOUTS_PATH', ROOT_PATH . '/layouts');
define('HEADER_PATH', LAYOUTS_PATH . '/header.php');
define('SIDEBAR_PATH', LAYOUTS_PATH . '/sidebar.php');
define('FOOTER_PATH', LAYOUTS_PATH . '/footer.php');

// Pages paths
define('PAGES_PATH', ROOT_PATH . '/pages');
define('DASHBOARD_PATH', PAGES_PATH . '/dashboard');
define('INVOICE_PATH', PAGES_PATH . '/invoice');
define('PRODUCTS_PATH', PAGES_PATH . '/products');
define('EXPENSES_PATH', PAGES_PATH . '/expenses');
define('EMPLOYEES_PATH', PAGES_PATH . '/employees');
define('ATTENDANCE_PATH', PAGES_PATH . '/attendance');
define('PAYROLL_PATH', PAGES_PATH . '/payroll');

// Assets paths
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('IMG_PATH', ROOT_PATH . '/img');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// URL paths (for links and redirects)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = $protocol . $host . $scriptPath;

define('BASE_URL', $baseUrl);
define('PAGES_URL', BASE_URL . '/pages');
define('ASSETS_URL', BASE_URL . '/assets');
define('IMG_URL', BASE_URL . '/img');

// Function to get relative path from current directory to root
function getRelativePath($currentPath) {
    $rootPath = ROOT_PATH;
    $relativePath = '';
    
    // Count directory levels from root
    $currentDir = dirname($currentPath);
    $levels = substr_count(str_replace($rootPath, '', $currentDir), DIRECTORY_SEPARATOR);
    
    for ($i = 0; $i < $levels; $i++) {
        $relativePath .= '../';
    }
    
    return $relativePath ?: './';
}

// Get current page name for navigation
function getCurrentPage() {
    return basename($_SERVER['PHP_SELF'], '.php');
}
?>