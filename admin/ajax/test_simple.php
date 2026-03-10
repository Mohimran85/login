<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Auth check
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);

echo json_encode([
    'test' => 'working',
    'year' => $year !== false && $year !== null ? $year : null,
]);
