<?php
// Autoload vendor if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Start output buffering and session early to avoid "headers already sent" in tests
if (session_status() === PHP_SESSION_NONE) {
    ob_start();
    @session_start();
}

// Load application includes used by tests
require_once __DIR__ . '/../includes/TotpManager.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/FileCompressor.php';
require_once __DIR__ . '/../includes/CacheManager.php';
