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
 * @param int $timeout Inactivity timeout in seconds (default: 1800 = 30 minutes)
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

    // Determine login page base URL (root vs subdirectory)
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    $rootDir   = realpath(__DIR__ . '/..');
    $isAtRoot  = ($scriptDir && $rootDir && realpath($scriptDir) === $rootDir);

    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired — destroy and redirect to login
            session_unset();
            session_destroy();
            $loginUrl = $isAtRoot ? 'index.php?timeout=1' : '../index.php?timeout=1';
            header("Location: $loginUrl");
            exit();
        }
    }

    // Single-device enforcement: verify session token matches DB
    if (isset($_SESSION['session_token'], $_SESSION['username'], $_SESSION['role'])) {
        $role      = $_SESSION['role'];
        $tok_table = ($role === 'student') ? 'student_register' : 'teacher_register';

        try {
            $db       = get_db_connection();
            $tok_stmt = $db->prepare("SELECT session_token FROM `$tok_table` WHERE username = ? LIMIT 1");
            if ($tok_stmt) {
                $tok_stmt->bind_param('s', $_SESSION['username']);
                $tok_stmt->execute();
                $tok_result = $tok_stmt->get_result();
                if ($tok_result && ($tok_row = $tok_result->fetch_assoc())) {
                    if ($tok_row['session_token'] !== $_SESSION['session_token']) {
                        $tok_stmt->close();
                        $db->close();
                        session_unset();
                        session_destroy();
                        $loginUrl = $isAtRoot ? 'index.php?concurrent=1' : '../index.php?concurrent=1';
                        header("Location: $loginUrl");
                        exit();
                    }
                }
                $tok_stmt->close();
            }
            $db->close();
        } catch (Exception $e) {
            // DB error — skip concurrent check, do not block the request
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Auto-execute when session is active
if (session_status() === PHP_SESSION_ACTIVE) {
    checkSessionTimeout();
}
