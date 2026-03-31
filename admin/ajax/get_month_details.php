<?php
require_once __DIR__ . '/../../includes/db_config.php';

header('Content-Type: application/json');

$year  = isset($_GET['year']) ? (int) $_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : date('m');

$conn = get_db_connection();
if (! $conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$sql = "SELECT event_type, event_name, prize, COUNT(regno) as participant_count
        FROM student_event_register
        WHERE YEAR(start_date) = ? AND MONTH(start_date) = ? AND verification_status = 'Approved'
        GROUP BY event_type, event_name, prize
        ORDER BY event_type, event_name";

$stmt = $conn->prepare($sql);
if (! $stmt) {
    echo json_encode(['success' => false, 'error' => $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param("ii", $year, $month);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'events' => $events]);
