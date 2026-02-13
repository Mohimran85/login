<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
$servername  = "localhost";
$db_username = "root";
$db_password = "";
$dbname      = "event_management_system";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get parameters
$from_regno = isset($_GET['from_regno']) ? trim($_GET['from_regno']) : '';
$to_regno   = isset($_GET['to_regno']) ? trim($_GET['to_regno']) : '';

// Validate parameters
if (empty($from_regno) || empty($to_regno)) {
    echo json_encode(['success' => false, 'error' => 'Both registration numbers are required']);
    exit();
}

// Validate range
if ($from_regno > $to_regno) {
    echo json_encode(['success' => false, 'error' => 'From registration number must be less than or equal to To registration number']);
    exit();
}

// Fetch students in the registration number range with counselor assignment info
$sql = "SELECT s.regno, s.name, s.department, s.semester,
                   ca.counselor_id, ca.status as assignment_status,
                   t.name as counselor_name
            FROM student_register s
            LEFT JOIN counselor_assignments ca ON s.regno = ca.student_regno AND ca.status = 'active'
            LEFT JOIN teacher_register t ON ca.counselor_id = t.id
            WHERE s.regno BETWEEN ? AND ?
            ORDER BY s.regno";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $from_regno, $to_regno);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'regno'          => $row['regno'],
        'name'           => $row['name'] ?? 'N/A',
        'department'     => $row['department'] ?? 'N/A',
        'semester'       => $row['semester'] ?? 'N/A',
        'is_assigned'    => ! empty($row['counselor_id']),
        'counselor_name' => $row['counselor_name'] ?? null,
        'counselor_id'   => $row['counselor_id'] ?? null,
    ];
}

echo json_encode([
    'success'  => true,
    'students' => $students,
    'count'    => count($students),
]);

$stmt->close();
$conn->close();
