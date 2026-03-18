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
$filter       = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$events_sql = "SELECT sr.name as student_name, sr.regno,
                      ser.event_name, ser.organisation, ser.start_date, ser.event_type,
                      COALESCE(ser.prize, '') as prize,
                      COALESCE(ser.verification_status, 'Pending') as verification_status
               FROM student_event_register ser
               JOIN student_register sr ON ser.regno = sr.regno
               JOIN counselor_assignments ca ON sr.regno = ca.student_regno
               WHERE ca.counselor_id = ? AND ca.status = 'active'";

if ($filter === 'prizes') {
    $events_sql .= " AND ser.prize IN ('First', 'Second', 'Third')";
}

$events_sql .= " ORDER BY sr.name ASC, ser.start_date DESC";

$events_stmt = $conn->prepare($events_sql);
$events_stmt->bind_param("i", $counselor_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$events_stmt->close();
$conn->close();

echo json_encode(['events' => $events]);
