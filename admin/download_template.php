<?php
session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Check user permissions
$username = $_SESSION['username'];
$conn     = new mysqli("localhost", "root", "", "event_management_system");

$user_type      = "";
$teacher_status = 'teacher';
$tables         = ['student_register', 'teacher_register'];

foreach ($tables as $table) {
    $sql  = "SELECT name FROM $table WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user_type = $table === 'student_register' ? 'student' : 'teacher';
        break;
    }
    $stmt->close();
}

// Check teacher status
if ($user_type === 'teacher') {
    $teacher_status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
    $teacher_status_stmt = $conn->prepare($teacher_status_sql);
    $teacher_status_stmt->bind_param("s", $username);
    $teacher_status_stmt->execute();
    $teacher_status_result = $teacher_status_stmt->get_result();

    if ($teacher_status_result->num_rows > 0) {
        $status_data    = $teacher_status_result->fetch_assoc();
        $teacher_status = $status_data['status'];
    }
    $teacher_status_stmt->close();
}

$conn->close();

// Only allow admin-level teachers
if ($user_type === 'teacher' && $teacher_status !== 'admin') {
    http_response_code(403);
    exit("Access denied");
}

// Redirect students
if ($user_type === 'student') {
    http_response_code(403);
    exit("Access denied");
}

// Get template type
$type = isset($_GET['type']) ? $_GET['type'] : '';

if ($type === 'students') {
    // Student template
    $filename = 'student_import_template.csv';
    $headers  = [
        'name',
        'username',
        'personal_email',
        'regno',
        'department',
        'year_of_join',
        'degree',
        'dob',
    ];

    $sample_data = [
        [
            'John Doe',
            'john.doe',
            'john.doe@email.com',
            '617823241001',
            'Computer Science',
            '2024',
            'B.Tech',
            '2005-06-15',
        ],
        [
            'Jane Smith',
            'jane.smith',
            'jane.smith@email.com',
            '617823241002',
            'Information Technology',
            '2024',
            'B.Tech',
            '2005-08-22',
        ],
    ];

} elseif ($type === 'teachers') {
    // Teacher template
    $filename = 'teacher_import_template.csv';
    $headers  = [
        'name',
        'username',
        'email',
        'faculty_id',
        'department',
        'year_of_join',
    ];

    $sample_data = [
        [
            'Dr. John Professor',
            'john.professor',
            'john.professor@college.edu',
            'FAC001',
            'Computer Science',
            '2024',
        ],
        [
            'Dr. Jane Teacher',
            'jane.teacher',
            'jane.teacher@college.edu',
            'FAC002',
            'Information Technology',
            '2024',
        ],
    ];

} else {
    http_response_code(400);
    exit("Invalid template type");
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, $headers);

// Write sample data
foreach ($sample_data as $row) {
    fputcsv($output, $row);
}

// Close file pointer
fclose($output);
exit();
