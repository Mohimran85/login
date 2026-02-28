<?php
// Quick database status column checker
require_once __DIR__ . '/../includes/DatabaseManager.php';

$db   = DatabaseManager::getInstance();
$conn = new mysqli("localhost", "root", "", "event_management_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Status Column Definition Check</h2>";
echo "<pre>";

// Get column definition
$result = $conn->query("SHOW COLUMNS FROM hackathon_posts WHERE Field = 'status'");
if ($result) {
    $column = $result->fetch_assoc();
    echo "Column Definition:\n";
    print_r($column);
    echo "\n\n";
}

// Get all status values currently in database
echo "Current Status Values in Database:\n";
echo str_repeat("-", 80) . "\n";
$result = $conn->query("SELECT id, title, status, LENGTH(status) as status_length, HEX(status) as status_hex FROM hackathon_posts ORDER BY id");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo sprintf(
            "ID: %-4d | Title: %-30s | Status: %-12s | Length: %-2d | Hex: %s\n",
            $row['id'],
            substr($row['title'], 0, 30),
            "'" . $row['status'] . "'",
            $row['status_length'],
            $row['status_hex']
        );
    }
}

echo "\n" . str_repeat("-", 80) . "\n";

// Check for any invalid status values
echo "\nValidation Check:\n";
$valid_statuses = ['draft', 'upcoming', 'ongoing', 'completed', 'cancelled'];
$result         = $conn->query("SELECT id, title, status FROM hackathon_posts");

$invalid_count = 0;
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $clean_status = strtolower(trim($row['status']));
        if (! in_array($clean_status, $valid_statuses)) {
            echo "❌ INVALID - ID {$row['id']}: '{$row['status']}' (cleaned: '{$clean_status}')\n";
            $invalid_count++;
        } else {
            echo "✅ VALID - ID {$row['id']}: '{$row['status']}'\n";
        }
    }
}

if ($invalid_count === 0) {
    echo "\n✅ All status values are valid!\n";
} else {
    echo "\n❌ Found {$invalid_count} invalid status value(s)!\n";
}

// Test ENUM insertion
echo "\n\n" . str_repeat("=", 80) . "\n";
echo "ENUM Value Insertion Test:\n";
echo str_repeat("=", 80) . "\n";

$test_values = ['draft', 'upcoming', 'ongoing', 'completed', 'cancelled'];
foreach ($test_values as $test_val) {
    $stmt = $conn->prepare("SELECT ? as test_status");
    $stmt->bind_param("s", $test_val);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();

    echo "Testing '{$test_val}': ";
    if ($row['test_status'] === $test_val) {
        echo "✅ SUCCESS (length: " . strlen($test_val) . ", hex: " . bin2hex($test_val) . ")\n";
    } else {
        echo "❌ FAILED\n";
    }
}

echo "</pre>";

$conn->close();
