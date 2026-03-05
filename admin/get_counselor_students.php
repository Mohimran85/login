<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Database connection
require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get counselor ID from query parameter
$counselor_id = isset($_GET['counselor_id']) ? intval($_GET['counselor_id']) : 0;

if ($counselor_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid counselor ID']);
    exit();
}

// Fetch students assigned to this counselor
$sql = "SELECT ca.id as assignment_id, ca.student_regno as regno, s.name
            FROM counselor_assignments ca
            LEFT JOIN student_register s ON ca.student_regno = s.regno
            WHERE ca.counselor_id = ? AND ca.status = 'active'
            ORDER BY ca.student_regno";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'assignment_id' => $row['assignment_id'],
        'regno'         => $row['regno'],
        'name'          => $row['name'],
    ];
}

echo json_encode([
    'success'  => true,
    'students' => $students,
]);

$stmt->close();
$conn->close();
