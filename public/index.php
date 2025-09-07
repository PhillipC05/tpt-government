<?php
/**
 * TPT Government Platform - Main Entry Point
 *
 * This is the main entry point for the government platform.
 * All requests are routed through this file for security and consistency.
 */

// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define application constants
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);
define('SRC_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('SESSIONS_PATH', ROOT_PATH . '/sessions');

// Include autoloader
require_once SRC_PATH . '/php/core/Autoloader.php';

// Initialize autoloader
$autoloader = new Core\Autoloader();
$autoloader->register();

// Start session with secure settings
Core\Session::start([
    'name' => 'TPT_GOV_SESSION',
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'gc_maxlifetime' => 3600, // 1 hour
    'save_path' => SESSIONS_PATH
]);

try {
    // Initialize application
    $app = new Core\Application();

    // Handle the request
    $app->run();

} catch (Exception $e) {
    // Log the error
    error_log('[' . date('Y-m-d H:i:s') . '] Application Error: ' . $e->getMessage() .
              ' in ' . $e->getFile() . ':' . $e->getLine());

    // Show user-friendly error page
    http_response_code(500);
    if (file_exists(PUBLIC_PATH . '/500.html')) {
        include PUBLIC_PATH . '/500.html';
    } else {
        echo '<h1>500 Internal Server Error</h1>';
        echo '<p>Something went wrong. Please try again later.</p>';
    }
}
?>
