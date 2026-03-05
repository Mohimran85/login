<?php
/**
 * Shared Database Configuration
 *
 * Provides a centralized database connection using environment variables.
 * Reads from .env file if environment variables are not already set.
 *
 * Usage:
 *   require_once __DIR__ . '/db_config.php';       // from includes/
 *   require_once __DIR__ . '/../includes/db_config.php'; // from admin/, student/, teacher/
 *   require_once 'includes/db_config.php';          // from root
 *   $conn = get_db_connection();
 */

// Load .env file once if not already loaded
if (! getenv('DB_HOST')) {
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    putenv($line);
                }
            }
        }
    }
}

/**
 * Get a new mysqli connection using environment-based config.
 *
 * @return mysqli
 * @throws Exception if connection fails
 */
function get_db_connection(): mysqli
{
    $host     = getenv('DB_HOST') ?: 'localhost';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $database = getenv('DB_NAME') ?: 'event_management_system';

    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        error_log('Database connection failed: ' . $conn->connect_error);
        throw new Exception('Database connection error. Please contact the administrator.');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}
