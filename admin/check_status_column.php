<?php
/**
 * Diagnostic: Check the status column definition in hackathon_posts table
 */

$conn = new mysqli("localhost", "root", "", "event_management_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
        echo "ID: {$row['id']}, Title: {$row['title']}, Status: '{$row['status']}' (length: {$row['status_length']})\n";
    }
}

echo "</pre>";

$conn->close();
