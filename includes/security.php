<?php
/**
 * Security Utilities
 *
 * Common security functions for input validation, authorization, etc.
 */

/**
 * Sanitize and validate file path to prevent directory traversal
 *
 * @param string $path The path to validate
 * @param string $baseDir The allowed base directory
 * @return string|false The safe path or false if invalid
 */
function validateFilePath($path, $baseDir)
{
    // Remove null bytes
    $path = str_replace("\0", '', $path);

    // Build full path
    $fullPath = $baseDir . '/' . $path;

    // Resolve to canonical path
    $realPath = realpath($fullPath);
    $realBase = realpath($baseDir);

    // Ensure path exists and is within base directory
    if ($realPath === false || $realBase === false) {
        return false;
    }

    if (strpos($realPath, $realBase) !== 0) {
        error_log("Path traversal attempt: $path");
        return false;
    }

    return $realPath;
}

/**
 * Sanitize filename for safe storage
 *
 * @param string $filename Original filename
 * @return string Safe filename
 */
function sanitizeFilename($filename)
{
    // Get extension
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);

    // Remove dangerous characters
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    $name = substr($name, 0, 100); // Limit length

    // Ensure we have a name
    if (empty($name)) {
        $name = 'file_' . time();
    }

    return $name . '.' . $ext;
}

/**
 * Validate uploaded file MIME type using server-side detection
 *
 * @param string $tmpPath Temporary file path
 * @param array $allowedTypes Array of allowed MIME types
 * @return bool True if valid
 */
function validateMimeType($tmpPath, $allowedTypes)
{
    if (! file_exists($tmpPath)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    return in_array($mime, $allowedTypes, true);
}

/**
 * Validate image file
 *
 * @param string $tmpPath Temporary file path
 * @return bool True if valid image
 */
function validateImageFile($tmpPath)
{
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

    if (! validateMimeType($tmpPath, $allowedMimes)) {
        return false;
    }

    // Additional check using getimagesize
    $imageInfo = @getimagesize($tmpPath);
    return $imageInfo !== false;
}

/**
 * Validate PDF file
 *
 * @param string $tmpPath Temporary file path
 * @return bool True if valid PDF
 */
function validatePDFFile($tmpPath)
{
    return validateMimeType($tmpPath, ['application/pdf']);
}

/**
 * Escape HTML output
 *
 * @param string $text Text to escape
 * @return string Escaped text
 */
function escapeHtml($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize for JavaScript context
 *
 * @param mixed $value Value to encode
 * @return string JSON-encoded value
 */
function escapeJs($value)
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Validate and sanitize integer input
 *
 * @param mixed $value Value to validate
 * @param int $default Default value if invalid
 * @return int Validated integer
 */
function validateInt($value, $default = 0)
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return $filtered !== false ? $filtered : $default;
}

/**
 * Validate email address
 *
 * @param string $email Email to validate
 * @return bool True if valid
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 *
 * @param string $password Password to validate
 * @param int $minLength Minimum length
 * @return array ['valid' => bool, 'errors' => array]
 */
function validatePasswordStrength($password, $minLength = 8)
{
    $errors = [];

    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least $minLength characters long";
    }

    if (! preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (! preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }

    if (! preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if (! preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Check if user is authenticated
 * Also verifies 2FA is complete when required.
 *
 * @return bool
 */
function isAuthenticated()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // If 2FA is pending but not verified, user is NOT authenticated yet
    if (isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] === true
        && (! isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true)) {
        return false;
    }

    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has a pending 2FA verification
 *
 * @return bool True if 2FA verification is still pending
 */
function is2faPending()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] === true
        && (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true);
}

/**
 * Require authentication or redirect
 *
 * @param string $redirectTo Redirect destination if not authenticated
 */
function requireAuth($redirectTo = '../index.php')
{
    if (! isAuthenticated()) {
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Check if user has a specific role
 *
 * @param string|array $roles Role(s) to check
 * @return bool
 */
function hasRole($roles)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (! isset($_SESSION['role'])) {
        return false;
    }

    if (is_array($roles)) {
        return in_array($_SESSION['role'], $roles, true);
    }

    return $_SESSION['role'] === $roles;
}

/**
 * Require a specific role or die
 *
 * @param string|array $roles Required role(s)
 * @param int $httpCode HTTP status code to return
 */
function requireRole($roles, $httpCode = 403)
{
    if (! hasRole($roles)) {
        http_response_code($httpCode);
        error_log("Unauthorized access attempt by user: " . ($_SESSION['user_id'] ?? 'unknown'));
        die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
    }
}

/**
 * Sanitize SQL LIKE pattern
 *
 * @param string $pattern Pattern to sanitize
 * @return string Sanitized pattern
 */
function sanitizeLikePattern($pattern)
{
    // Escape special characters
    $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pattern);
    return $pattern;
}

/**
 * Generate a secure random filename
 *
 * @param string $extension File extension
 * @param string $prefix Optional prefix
 * @return string Random filename
 */
function generateSecureFilename($extension, $prefix = '')
{
    $random    = bin2hex(random_bytes(16));
    $timestamp = time();
    return $prefix . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Sanitize header value to prevent header injection
 *
 * @param string $value Header value
 * @return string Sanitized value
 */
function sanitizeHeader($value)
{
    // Remove CR and LF
    return str_replace(["\r", "\n", "\0"], '', $value);
}

/**
 * Log security event
 *
 * @param string $event Event description
 * @param array $context Additional context
 */
function logSecurityEvent($event, $context = [])
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id'   => $_SESSION['user_id'] ?? 'anonymous',
        'event'     => $event,
        'context'   => $context,
    ];

    error_log("SECURITY: " . json_encode($logEntry));
}
