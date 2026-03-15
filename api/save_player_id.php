<?php
/**
 * Save OneSignal Player ID
 * Called from the Median.co native app bridge via gonative_onesignal_info callback.
 * Stores the device's OneSignal player ID against the logged-in student's record,
 * enabling targeted push notifications without relying on external_id linking.
 */
session_start();
header('Content-Type: application/json');

// Must be a logged-in student session
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit();
}

$input     = json_decode(file_get_contents('php://input'), true);
$player_id = trim($input['player_id'] ?? '');

// OneSignal player IDs are UUIDs (8-4-4-4-12 hex format)
if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $player_id)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_format']);
    exit();
}

$username = $_SESSION['username'] ?? '';
if (empty($username)) {
    echo json_encode(['success' => false, 'reason' => 'no_session']);
    exit();
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Auto-migration: add column if it doesn't exist yet
$col_check = $conn->query("SHOW COLUMNS FROM student_register LIKE 'onesignal_player_id'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE student_register ADD COLUMN onesignal_player_id VARCHAR(255) NULL DEFAULT NULL");
}

$stmt = $conn->prepare("UPDATE student_register SET onesignal_player_id = ? WHERE username = ?");
$stmt->bind_param("ss", $player_id, $username);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

if ($affected > 0) {
    error_log("OneSignal: Stored player_id {$player_id} for user {$username}");
}

echo json_encode(['success' => true]);
