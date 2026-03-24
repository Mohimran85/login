<?php
session_start();

// Clear all cache headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Clear session token from DB (single-device enforcement)
if (isset($_SESSION['username'], $_SESSION['role'])) {
    try {
        require_once __DIR__ . '/../includes/db_config.php';
        $logout_conn = get_db_connection();
        $tok_table   = ($_SESSION['role'] === 'student') ? 'student_register' : 'teacher_register';

        if ($_SESSION['role'] === 'student') {
            $tok_stmt = $logout_conn->prepare("UPDATE `$tok_table` SET session_token = NULL, onesignal_player_id = NULL WHERE username = ?");
        } else {
            $tok_stmt = $logout_conn->prepare("UPDATE `$tok_table` SET session_token = NULL WHERE username = ?");
        }

        if ($tok_stmt) {
            $tok_stmt->bind_param('s', $_SESSION['username']);
            $tok_stmt->execute();
            $tok_stmt->close();
        }
        $logout_conn->close();
    } catch (Exception $e) {
        // DB error — proceed with logout anyway
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: ../index.php?logout=success");
exit();
