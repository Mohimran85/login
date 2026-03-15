<?php
/**
 * Send Certificate Upload Reminder
 * Called via AJAX from verify_events.php when "Remind" button is clicked.
 * Inserts an in-app notification for the student.
 */
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$event_id   = intval($_POST['event_id'] ?? 0);
$regno      = trim($_POST['regno'] ?? '');
$event_name = trim($_POST['event_name'] ?? '');

if ($event_id <= 0 || empty($regno) || empty($event_name)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Database connection
require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

$faculty_name = $_SESSION['name'] ?? 'Your Counselor';

// Build notification content
$notif_title   = "📎 Certificate Upload Reminder";
$notif_message = "Your counselor ({$faculty_name}) is reminding you to upload the certificate for \"{$event_name}\". Please upload it as soon as possible.";
$notif_link    = "/event_management_system/login/student/index.php";

// Insert in-app notification into the notifications table
$stmt = $conn->prepare(
    "INSERT INTO notifications (student_regno, notification_type, title, message, link, sent_at)
     VALUES (?, 'event', ?, ?, ?, NOW())"
);
$stmt->bind_param("ssss", $regno, $notif_title, $notif_message, $notif_link);

if ($stmt->execute()) {
    $stmt->close();

    // Send push notification via OneSignal
    $push_status = 'not_sent';
    $push_debug  = null;
    try {
        require_once __DIR__ . '/../includes/OneSignalManager.php';
        $oneSignal = new OneSignalManager();

        // Look up any stored OneSignal player ID for this student (most reliable targeting)
        $playerIds  = [];
        $col_exists = $conn->query("SHOW COLUMNS FROM student_register LIKE 'onesignal_player_id'");
        if (! $col_exists || $col_exists->num_rows === 0) {
            // Column not yet created — create it now so future saves work
            $conn->query("ALTER TABLE student_register ADD COLUMN onesignal_player_id VARCHAR(255) NULL DEFAULT NULL");
        } else {
            $pid_stmt = $conn->prepare(
                "SELECT onesignal_player_id FROM student_register WHERE regno = ? AND onesignal_player_id IS NOT NULL AND onesignal_player_id != ''"
            );
            $pid_stmt->bind_param("s", $regno);
            $pid_stmt->execute();
            $pid_res = $pid_stmt->get_result();
            if ($pid_row = $pid_res->fetch_assoc()) {
                $playerIds[] = $pid_row['onesignal_player_id'];
            }
            $pid_stmt->close();
        }

        $push_result = $oneSignal->sendToStudent($regno, $notif_title, $notif_message, $notif_link, $playerIds);
        $push_debug  = $push_result;
        error_log('OneSignal push result for ' . $regno . ': ' . json_encode($push_result));
        // Check actual recipients count - HTTP 200 doesn't guarantee delivery
        $recipients = $push_result['response']['recipients'] ?? 0;
        if (isset($push_result['status']) && $push_result['status'] == 200 && $recipients > 0) {
            $push_status = 'sent';
        } else {
            $push_status = 'failed';
            if ($recipients == 0) {
                error_log('OneSignal: 0 recipients for ' . $regno . ' - student may not have push subscription (needs to open app once)');
            }
        }
    } catch (Exception $e) {
        error_log('Push notification error for reminder: ' . $e->getMessage());
        $push_debug = ['error' => $e->getMessage()];
    }

    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Reminder sent to student ' . $regno, 'push' => $push_status, 'push_debug' => $push_debug]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    error_log('Notification insert error: ' . $error);
    echo json_encode(['success' => false, 'message' => 'Failed to send reminder. Please try again.']);
}
