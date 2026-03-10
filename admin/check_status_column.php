<?php
/**
 * Diagnostic: Check the status column definition in hackathon_posts table
 */
session_start();
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Forbidden: Admin access required');
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

if ($conn->connect_error) {
    error_log('check_status_column: Connection failed: ' . $conn->connect_error);
    die("Connection failed");
}

echo "<h2>Status Column Definition Check</h2>";
echo "<pre>";

// Get column definition
$result = $conn->query("SHOW COLUMNS FROM hackathon_posts WHERE Field = 'status'");

if ($result && $row = $result->fetch_assoc()) {
    echo "Column: " . $row['Field'] . "\n";
    echo "Type: " . $row['Type'] . "\n";
    echo "Null: " . $row['Null'] . "\n";
    echo "Default: " . $row['Default'] . "\n";
    echo "Extra: " . $row['Extra'] . "\n\n";

    // Extract ENUM values
    if (preg_match("/^enum\((.+)\)$/i", $row['Type'], $matches)) {
        $enum_values = str_getcsv($matches[1], ',', "'");
        echo "Valid ENUM values:\n";
        foreach ($enum_values as $value) {
            echo "  - '" . $value . "' (length: " . strlen($value) . ")\n";
        }
    }
} else {
    echo "ERROR: Could not find status column\n";
}

echo "\n--- Current Data Check ---\n";
$result = $conn->query("SELECT id, title, status, LENGTH(status) as status_length FROM hackathon_posts ORDER BY id DESC LIMIT 10");
if ($result) {
    echo "Recent hackathons:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Title: " . htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') . ", Status: '{$row['status']}' (length: {$row['status_length']})\n";
    }
}

echo "</pre>";

$conn->close();
