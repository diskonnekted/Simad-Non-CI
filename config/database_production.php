<?php
/**
 * SIMAD - Sistem Informasi Manajemen Desa
 * Database Configuration for Production Environment
 * 
 * IMPORTANT: Update these values with your hosting database credentials
 */

// Production Database Configuration
// CHANGE THESE VALUES ACCORDING TO YOUR HOSTING PROVIDER
define('DB_HOST', 'localhost');           // Usually 'localhost' or your hosting DB server
define('DB_NAME', 'simad_database');      // Your database name from hosting panel
define('DB_USER', 'your_db_username');    // Database username from hosting panel
define('DB_PASS', 'your_db_password');    // Database password from hosting panel
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_URL', 'https://simad.sistemdata.id');  // Your domain URL
define('APP_NAME', 'SIMAD - Sistem Informasi Manajemen Desa');
define('APP_VERSION', '1.0.0');

// Security Configuration
define('SESSION_LIFETIME', 28800);        // Session timeout in seconds (8 hours)
define('CSRF_TOKEN_EXPIRE', 1800);        // CSRF token expiry in seconds (30 minutes)

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Error Reporting for Production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Database Connection Function
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error instead of displaying it
        error_log("Database connection failed: " . $e->getMessage());
        
        // Show generic error to user
        die("Database connection failed. Please contact administrator.");
    }
}

// Check if running in production environment
function isProduction() {
    return !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', 'localhost:8000']);
}

// Get current environment
function getEnvironment() {
    return isProduction() ? 'production' : 'development';
}

?>