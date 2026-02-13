<?php
session_start();
require_once 'config.php';

// Require teacher role
require_teacher_role();

// Get poster filename from request
$poster_file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($poster_file)) {
    die('No poster file specified.');
}

// Sanitize filename - prevent directory traversal
$poster_file = basename($poster_file);
$poster_file = str_replace(['../', '..\\', "\0", "\r", "\n"], '', $poster_file);

if (empty($poster_file)) {
    die('Invalid poster file.');
}

// Define allowed directories
$uploads_dir = dirname(__FILE__) . '/../uploads/posters';
$student_uploads_dir = dirname(__FILE__) . '/../student/uploads/posters';

$allowed_base_paths = [
    realpath($uploads_dir),
    realpath($student_uploads_dir)
];

// Remove false values
$allowed_base_paths = array_filter($allowed_base_paths);

// Search for poster file
$search_locations = [
    $uploads_dir . '/' . $poster_file,
    $student_uploads_dir . '/' . $poster_file,
];

$poster_path = null;

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
        $poster_path = $real_path;
        break;
    }
}

if ($poster_path === null) {
    die('Poster not found.');
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $poster_path);
finfo_close($finfo);

// Validate it's an image
if (strpos($mime_type, 'image/') !== 0) {
    die('Invalid file type.');
}

// Sanitize filename for header
$safe_filename = sanitize_filename($poster_file);

// Use RFC 5987 encoding for filename
$encoded_filename = rawurlencode($safe_filename);

// Set security headers
header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($poster_path));
header("Content-Disposition: inline; filename=\"" . $safe_filename . "\"; filename*=UTF-8''" . $encoded_filename);

// Output file
readfile($poster_path);
exit();
?>
