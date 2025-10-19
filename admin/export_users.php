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

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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

// Get export type
$type = isset($_GET['type']) ? $_GET['type'] : '';

if (! in_array($type, ['students', 'teachers', 'all'])) {
    http_response_code(400);
    exit("Invalid export type");
}

// Prepare filename and headers
$filename = 'users_export_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create file pointer connected to output stream
$output = fopen('php://output', 'w');

if ($type === 'students' || $type === 'all') {
    if ($type === 'all') {
        // Add a section header for students
        fputcsv($output, ['=== STUDENTS ===']);
        fputcsv($output, []);
    }

    // Student headers
    $student_headers = [
        'id',
        'name',
        'username',
        'personal_email',
        'regno',
        'department',
        'year_of_join',
        'degree',
        'dob',
        'status',
        'reg_time',
    ];
    fputcsv($output, $student_headers);

    // Get student data
    $student_sql    = "SELECT id, name, username, personal_email, regno, department, year_of_join, degree, dob, status, reg_time FROM student_register ORDER BY reg_time DESC, id DESC";
    $student_result = $conn->query($student_sql);

    if ($student_result->num_rows > 0) {
        while ($row = $student_result->fetch_assoc()) {
            // Format the data for CSV
            $student_row = [
                $row['id'],
                $row['name'],
                $row['username'],
                $row['personal_email'],
                $row['regno'],
                $row['department'],
                $row['year_of_join'],
                $row['degree'],
                $row['dob'],
                $row['status'],
                $row['reg_time'],
            ];
            fputcsv($output, $student_row);
        }
    }

    if ($type === 'all') {
        // Add spacing between sections
        fputcsv($output, []);
        fputcsv($output, []);
    }
}

if ($type === 'teachers' || $type === 'all') {
    if ($type === 'all') {
        // Add a section header for teachers
        fputcsv($output, ['=== TEACHERS ===']);
        fputcsv($output, []);
    }

    // Teacher headers
    $teacher_headers = [
        'id',
        'name',
        'username',
        'email',
        'faculty_id',
        'department',
        'year_of_join',
        'status',
        'created_at',
    ];
    fputcsv($output, $teacher_headers);

    // Get teacher data
    $teacher_sql    = "SELECT id, name, username, email, faculty_id, department, year_of_join, status, created_at FROM teacher_register ORDER BY created_at DESC, id DESC";
    $teacher_result = $conn->query($teacher_sql);

    if ($teacher_result->num_rows > 0) {
        while ($row = $teacher_result->fetch_assoc()) {
            // Format the data for CSV
            $teacher_row = [
                $row['id'],
                $row['name'],
                $row['username'],
                $row['email'],
                $row['faculty_id'],
                $row['department'],
                $row['year_of_join'],
                $row['status'],
                $row['created_at'],
            ];
            fputcsv($output, $teacher_row);
        }
    }
}

// Add export metadata at the end
fputcsv($output, []);
fputcsv($output, ['=== EXPORT METADATA ===']);
fputcsv($output, ['Export Type', $type]);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By', $username]);

// Close file pointer and database connection
fclose($output);
$conn->close();
exit();
