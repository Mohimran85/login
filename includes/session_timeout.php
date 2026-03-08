<?php
/**
 * Session Timeout Handler
 *
 * Enforces inactivity-based session timeout for authenticated users.
 * Auto-executes when included — no manual call needed.
 *
 * Included automatically via db_config.php for near-universal coverage.
 */

/**
 * Check and enforce session inactivity timeout.
 *
 * @param int $timeout Inactivity timeout in seconds (default: 3600 = 1 hour)
 */
function checkSessionTimeout($timeout = 1800)
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    // Only apply to authenticated users
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return;
    }

    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired — destroy and redirect to login
            session_unset();
            session_destroy();

            // Determine login page URL based on current script location
            $scriptDir   = dirname($_SERVER['SCRIPT_FILENAME']);
            $includesDir = realpath(__DIR__);
            $rootDir     = realpath(__DIR__ . '/..');

            if ($scriptDir && $rootDir && realpath($scriptDir) === $rootDir) {
                $loginUrl = 'index.php?timeout=1';
            } else {
                $loginUrl = '../index.php?timeout=1';
            }

            header("Location: $loginUrl");
            exit();
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Auto-execute when session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    checkSessionTimeout();
}
