<?php
/**
 * CSRF Protection Utility
 *
 * Provides functions to generate and validate CSRF tokens
 * to protect against Cross-Site Request Forgery attacks
 */

/**
 * Generate a CSRF token and store it in the session
 *
 * @return string The generated token
 */
function generateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 *
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require a valid CSRF token or die
 *
 * @param array $data Data array to check (e.g., $_POST)
 * @param string $key Key name for the token (default: 'csrf_token')
 */
function requireCSRFToken($data = null, $key = 'csrf_token')
{
    if ($data === null) {
        $data = $_POST;
    }

    if (! isset($data[$key]) || ! validateCSRFToken($data[$key])) {
        error_log("CSRF validation failed from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']));
    }
}

/**
 * Output a hidden CSRF token field for forms
 *
 * @param string $name Field name (default: 'csrf_token')
 * @return string HTML input field
 */
function csrfField($name = 'csrf_token')
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Get the CSRF token value
 *
 * @return string The current CSRF token
 */
function getCSRFToken()
{
    return generateCSRFToken();
}

/**
 * Regenerate CSRF token (call after successful sensitive operations)
 */
function regenerateCSRFToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
