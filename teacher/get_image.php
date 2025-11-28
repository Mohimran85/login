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

// Define search paths based on actual file structure
$search_paths = [];
$base_path    = '../student/uploads/';

if ($type === 'certificate') {
    // Get all cert_ files in uploads directory
    $pattern = $base_path . 'cert_*' . str_replace(' ', '_', strtolower($event_name)) . '*.pdf';
    $files   = glob($pattern);

    // Also try without lowercase
    if (empty($files)) {
        $pattern = $base_path . 'cert_*' . str_replace(' ', '_', $event_name) . '*.pdf';
        $files   = glob($pattern);
    }

    // Try additional patterns
    if (empty($files)) {
        $files = glob($base_path . 'cert_*' . $regno . '*.pdf');
    }

    if (! empty($files)) {
        $search_paths = $files;
    }
} elseif ($type === 'poster') {
    // Get all poster_ files in uploads directory
    $pattern = $base_path . 'poster_*' . str_replace(' ', '_', strtolower($event_name)) . '*.pdf';
    $files   = glob($pattern);

    // Also try without lowercase
    if (empty($files)) {
        $pattern = $base_path . 'poster_*' . str_replace(' ', '_', $event_name) . '*.pdf';
        $files   = glob($pattern);
    }

    if (! empty($files)) {
        $search_paths = $files;
    }
}

// Try to find the file
$found_file = null;

if (! empty($search_paths)) {
    // Use the first matching file
    $found_file = $search_paths[0];
}

if ($found_file && file_exists($found_file)) {
    // Return the relative path
    echo json_encode(['success' => true, 'path' => $found_file]);
} else {
    // No file found, return error with debug info
    echo json_encode([
        'success'    => false,
        'message'    => ucfirst($type) . ' not found',
        'searched'   => $search_paths,
        'event_name' => $event_name,
        'regno'      => $regno,
    ]);
}
