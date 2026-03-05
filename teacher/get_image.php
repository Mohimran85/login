<?php
session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$type       = isset($_GET['type']) ? $_GET['type'] : '';
$regno      = isset($_GET['regno']) ? $_GET['regno'] : '';
$event_name = isset($_GET['event_name']) ? $_GET['event_name'] : '';
$event_id   = isset($_GET['event_id']) ? $_GET['event_id'] : '';

// Query database to get the actual file path
require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

$found_file = null;

if ($type === 'certificate') {
    $column = 'certificates';
} elseif ($type === 'poster') {
    $column = 'event_poster';
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid type',
    ]);
    exit;
}

// Query the database for the file path using event_id
$sql  = "SELECT $column FROM student_event_register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row       = $result->fetch_assoc();
    $file_path = $row[$column];

    if (! empty($file_path)) {
        // Check if file exists at the stored path
        if (file_exists($file_path)) {
            $found_file = $file_path;
        } else {
            // Try relative path from current directory
            $relative_path = '../student/' . $file_path;
            if (file_exists($relative_path)) {
                $found_file = $relative_path;
            } else {
                // Try with uploads prefix
                $uploads_path = '../student/uploads/' . basename($file_path);
                if (file_exists($uploads_path)) {
                    $found_file = $uploads_path;
                }
            }
        }
    }
}

$stmt->close();
$conn->close();

$search_paths = [$found_file];

// Check if we want to view the file directly or get JSON path
$view = isset($_GET['view']) ? $_GET['view'] : 'json';

if ($found_file && file_exists($found_file)) {
    if ($view === 'inline') {
        // Serve the file directly with inline disposition
        $file_extension = strtolower(pathinfo($found_file, PATHINFO_EXTENSION));

        // Set appropriate content type
        if ($file_extension === 'pdf') {
            header('Content-Type: application/pdf');
        } elseif (in_array($file_extension, ['jpg', 'jpeg'])) {
            header('Content-Type: image/jpeg');
        } elseif ($file_extension === 'png') {
            header('Content-Type: image/png');
        } elseif ($file_extension === 'gif') {
            header('Content-Type: image/gif');
        } else {
            header('Content-Type: application/octet-stream');
        }

        // Force inline display (not download)
        header('Content-Disposition: inline; filename="' . basename($found_file) . '"');
        header('Content-Length: ' . filesize($found_file));
        header('Cache-Control: public, max-age=86400'); // Cache for 1 day

        // Output file
        readfile($found_file);
        exit;
    } else {
        // Return the relative path as JSON
        echo json_encode(['success' => true, 'path' => $found_file]);
    }
} else {
    // No file found, return error with debug info
    echo json_encode([
        'success'    => false,
        'message'    => ucfirst($type) . ' not found',
        'searched'   => $search_paths,
        'event_name' => $event_name,
        'regno'      => $regno,
        'event_id'   => $event_id,
        'column'     => isset($column) ? $column : 'unknown',
    ]);
}
