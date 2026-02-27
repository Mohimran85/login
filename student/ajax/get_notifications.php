<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/DatabaseManager.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Verify student authentication
if (! isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$db = DatabaseManager::getInstance();

// Get student regno
$student_query = "SELECT regno FROM student_register WHERE username = ? LIMIT 1";
$result        = $db->executeQuery($student_query, [$_SESSION['username']]);

if (! $result || count($result) === 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Student not found']));
}

$student_regno = $result[0]['regno'];

// Determine action
$action = isset($_GET['action']) ? $_GET['action'] : 'get_notifications';

if ($action === 'get_notifications') {
    // Get all notifications for the student ordered by newest first
    $notifications_sql = "SELECT
        n.id,
        n.hackathon_id,
        n.title,
        n.message,
        n.notification_type,
        n.link,
        n.is_read,
        n.created_at,
        COALESCE(NULLIF(n.hackathon_title, ''), hp.title) as hackathon_title
    FROM notifications n
    LEFT JOIN hackathon_posts hp ON n.hackathon_id = hp.id
    WHERE n.student_regno = ?
    ORDER BY n.created_at DESC
    LIMIT 20";

    $notifications = $db->executeQuery($notifications_sql, [$student_regno]);

    // Count unread notifications
    $unread_count_sql = "SELECT COUNT(*) as count FROM notifications WHERE student_regno = ? AND (is_read = 0 OR is_read IS NULL)";
    $unread_result    = $db->executeQuery($unread_count_sql, [$student_regno]);
    $unread_count     = $unread_result[0]['count'] ?? 0;

    echo json_encode([
        'success'       => true,
        'notifications' => $notifications ?? [],
        'unread_count'  => $unread_count,
    ]);

} elseif ($action === 'mark_as_read') {
    $notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($notification_id <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Invalid notification ID']));
    }

    // Mark notification as read
    $update_sql = "UPDATE notifications
                   SET is_read = 1
                   WHERE id = ? AND student_regno = ?";

    $db->executeQuery($update_sql, [$notification_id, $student_regno]);

    echo json_encode([
        'success'         => true,
        'notification_id' => $notification_id,
    ]);

} elseif ($action === 'mark_all_read') {
    // Mark all notifications as read for the student
    $update_sql = "UPDATE notifications
                   SET is_read = 1
                   WHERE student_regno = ? AND (is_read = 0 OR is_read IS NULL)";

    $db->executeQuery($update_sql, [$student_regno]);

    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid action']));
}
