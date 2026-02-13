<?php
// Secure database configuration using environment variables
// This file should not be committed with actual credentials

// Load database configuration from environment variables
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');

// Fallback to defaults for development (should be removed in production)
if ($db_host === false) $db_host = 'localhost';
if ($db_user === false) $db_user = 'root';
if ($db_pass === false) $db_pass = '';
if ($db_name === false) $db_name = 'event_management_system';

// Validate that required configuration exists
if (empty($db_host) || empty($db_name)) {
    error_log("Database configuration error: Missing required environment variables");
    die("Database configuration error. Please contact the administrator.");
}

// Create database connection
function get_db_connection() {
    global $db_host, $db_user, $db_pass, $db_name;
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Database connection error. Please contact the administrator.");
    }
    
    return $conn;
}

// CSRF Token Management
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Role-based access control
function require_teacher_role() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. Teacher role required.");
    }
}

function require_role($allowed_roles) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied. Insufficient permissions.");
    }
}

// Sanitize filename for safe header usage
function sanitize_filename($filename) {
    // Remove path separators and null bytes
    $filename = basename($filename);
    $filename = str_replace(["\0", "\r", "\n"], '', $filename);
    
    // If empty after sanitization, use default
    if (empty($filename)) {
        $filename = 'file';
    }
    
    return $filename;
}

// Validate path is within allowed directory
function validate_path($path, $allowed_base_paths) {
    $real_path = realpath($path);
    
    if ($real_path === false) {
        return false;
    }
    
    foreach ($allowed_base_paths as $base_path) {
        $real_base = realpath($base_path);
        if ($real_base !== false && strpos($real_path, $real_base) === 0) {
            return $real_path;
    }
    }
    
    return false;
}

// Debug mode check (should be disabled in production)
function is_debug_mode() {
    $debug = getenv('DEBUG_MODE');
    return $debug === '1' || $debug === 'true';
}
?>
