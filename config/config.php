<?php
/**
 * CivicWatch – Application configuration
 *
 * Edit the database credentials below to match your local XAMPP setup.
 * The application is designed to live in a folder such as C:\xampp\htdocs\civicwatch.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
define('APP_NAME', 'CivicWatch');
define('APP_TAGLINE', 'Smart Community Issue Reporting and Tracking');
define('COMMUNITY_NAME', 'Greenfield City');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', true); // Set to false in production.

// Project developer credit shown on the website, dashboards and documentation.
define('DEVELOPER_NAME', 'Ketema Deresa');
define('DEVELOPER_TITLE', 'Software Developer');
define('DEVELOPER_EMAIL', 'ketemaderesa123@gmail.com');

// Public contact information shown on the homepage and in the footer.
define('CONTACT_EMAIL', DEVELOPER_EMAIL);
define('CONTACT_PHONE', '+251914976416');
define('CONTACT_ADDRESS', 'Oda Bultum University, Chiro, Oromia, Ethiopia');

// Default map centre used by the interactive location picker.
define('DEFAULT_MAP_LAT', 9.0300);
define('DEFAULT_MAP_LNG', 38.7400);
define('DEFAULT_MAP_ZOOM', 13);

// ---------------------------------------------------------------------------
// Database – XAMPP defaults
// ---------------------------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'civicwatch_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------------------------
// Paths and URLs
// ---------------------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_ROOT', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per image.
define('MAX_REPORT_IMAGES', 5);

/**
 * Work out the application base URL from the running script. This keeps the
 * app portable if the folder is renamed or placed directly in htdocs.
 */
if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base = preg_replace('#/(admin|user|staff)$#', '', rtrim($scriptDir, '/'));
    define('BASE_URL', in_array($base, ['.', '/', '\\'], true) ? '' : $base);
} else {
    define('BASE_URL', '');
}

// ---------------------------------------------------------------------------
// Error handling and timezone
// ---------------------------------------------------------------------------
date_default_timezone_set('UTC');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

// ---------------------------------------------------------------------------
// Secure session configuration (must happen before session_start)
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('civicwatch_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Basic hardening headers.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
}
