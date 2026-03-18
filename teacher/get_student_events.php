<?php
session_start();

header('Content-Type: application/json');

if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Verify the user is a counselor
$username = $_SESSION['username'];
$sql      = "SELECT id, COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
$stmt     = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$teacher = $result->fetch_assoc();
$stmt->close();

if ($teacher['status'] !== 'counselor') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

$counselor_id = $teacher['id'];
$regno        = isset($_GET['regno']) ? trim($_GET['regno']) : '';

if (empty($regno)) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Verify student is assigned to this counselor
$verify_sql  = "SELECT 1 FROM counselor_assignments WHERE counselor_id = ? AND student_regno = ? AND status = 'active'";
$verify_stmt = $conn->prepare($verify_sql);
$verify_stmt->bind_param("is", $counselor_id, $regno);
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}
$verify_stmt->close();

// Fetch events
$filter     = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$events_sql = "SELECT event_name, organisation, start_date, event_type,
                      COALESCE(prize, '') as prize,
                      COALESCE(verification_status, 'Pending') as verification_status
               FROM student_event_register
               WHERE regno = ?";

if ($filter === 'prizes') {
    $events_sql .= " AND prize IN ('First', 'Second', 'Third')";
}

$events_sql .= " ORDER BY start_date DESC";

$events_stmt = $conn->prepare($events_sql);
$events_stmt->bind_param("s", $regno);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$events_stmt->close();
$conn->close();

echo json_encode(['events' => $events]);
