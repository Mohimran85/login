<?php
session_start();
require_once 'config.php';

// Require teacher role
require_teacher_role();

// Get database connection
$conn = get_db_connection();

// Get teacher ID from session
$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

// Get event_id from request
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
$column = isset($_GET['column']) ? $_GET['column'] : '';

if ($event_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// Whitelist allowed columns
$allowed_columns = ['event_poster', 'participation_certificate', 'internship_certificate'];
if (!in_array($column, $allowed_columns)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit();
}

// Verify authorization: Check if teacher is associated with this event
// This checks if the teacher owns/counsels students in the event
$auth_sql = "SELECT e.id FROM events e 
             LEFT JOIN student_event_register ser ON e.id = ser.event_id 
             LEFT JOIN student_register sr ON ser.regno = sr.regno 
             WHERE e.id = ? AND (e.created_by = ? OR sr.counselor_id = ?)
             LIMIT 1";
$auth_stmt = $conn->prepare($auth_sql);

if ($auth_stmt === false) {
    error_log("Prepare failed in get_image.php: " . $conn->error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit();
}

$auth_stmt->bind_param("iii", $event_id, $teacher_id, $teacher_id);
$auth_stmt->execute();
$auth_result = $auth_stmt->get_result();

if ($auth_result->num_rows === 0) {
    $auth_stmt->close();
    header("HTTP/1.1 403 Forbidden");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}
$auth_stmt->close();

// Build SQL with validated column name
$sql = "SELECT " . $column . " FROM events WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Prepare failed in get_image.php for column query: " . $conn->error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit();
}

$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Image not found.']);
    exit();
}

$row = $result->fetch_assoc();
$filename = $row[$column];
$stmt->close();
$conn->close();

if (empty($filename)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Image not found.']);
    exit();
}

// Sanitize filename - remove directory traversal attempts
$filename = basename($filename);
$filename = str_replace(['../', '..\\', "\0"], '', $filename);

// Define allowed base directories and resolve to real paths
$teacher_dir = dirname(__FILE__);
$uploads_dir = dirname(__FILE__) . '/../uploads';
$student_uploads_dir = dirname(__FILE__) . '/../student/uploads';

$allowed_base_paths = [
    realpath($teacher_dir),
    realpath($uploads_dir),
    realpath($student_uploads_dir)
];

// Remove any false values
$allowed_base_paths = array_filter($allowed_base_paths);

// Search for file in multiple locations
$search_locations = [
    $uploads_dir . '/' . $filename,
    $uploads_dir . '/posters/' . $filename,
    $uploads_dir . '/certificates/' . $filename,
    $student_uploads_dir . '/' . $filename,
    $student_uploads_dir . '/posters/' . $filename,
    $student_uploads_dir . '/certificates/' . $filename,
];

$found_file = null;

foreach ($search_locations as $candidate_path) {
    $real_path = realpath($candidate_path);
    
    if ($real_path === false) {
        continue;
    }
    
    // Validate path is within allowed directories
    $is_allowed = false;
    foreach ($allowed_base_paths as $allowed_base) {
        if (strpos($real_path, $allowed_base) === 0) {
            $is_allowed = true;
            break;
        }
    }
    
    if ($is_allowed && file_exists($real_path) && is_file($real_path)) {
        $found_file = $real_path;
        break;
    }
}

if ($found_file === null) {
    // Return generic error without exposing internals
    header('Content-Type: application/json');
    
    if (is_debug_mode()) {
        // Only include debug info in debug mode
        error_log("Image not found for event_id=$event_id, column=$column, filename=$filename");
        echo json_encode([
            'success' => false,
            'message' => 'Image not found.',
            'debug' => [
                'filename' => $filename,
                'searched_locations' => count($search_locations)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Image not found.']);
    }
    exit();
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $found_file);
finfo_close($finfo);

// Sanitize filename for Content-Disposition header
$safe_filename = sanitize_filename($filename);

// Use RFC 5987 encoding for filename
$encoded_filename = rawurlencode($safe_filename);

// Set security headers
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($found_file));
header("Content-Disposition: inline; filename=\"" . $safe_filename . "\"; filename*=UTF-8''" . $encoded_filename);

// Output file
readfile($found_file);
exit();
?>
