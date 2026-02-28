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
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

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
    $conn->close();
    echo json_encode(['success' => true, 'message' => 'Reminder sent to student ' . $regno]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Failed to insert notification: ' . $error]);
}
