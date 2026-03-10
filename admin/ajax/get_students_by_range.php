<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}
if (! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

// Database connection
require_once __DIR__ . '/../../includes/db_config.php';
$conn = get_db_connection();

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
if (! ctype_digit($from_regno) || ! ctype_digit($to_regno)) {
    echo json_encode(['success' => false, 'error' => 'Registration numbers must be numeric']);
    exit();
}
if ((int) $from_regno > (int) $to_regno) {
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
