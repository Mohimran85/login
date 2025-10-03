<?php
$conn = new mysqli('localhost', 'root', '', 'event_management_system');
if ($conn->connect_error) {
    echo 'Connection failed: ' . $conn->connect_error;
    exit;
}

// Check if student_event_register table exists and has data
$result = $conn->query('SELECT COUNT(*) as count FROM student_event_register');
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo 'Total records in student_event_register: ' . $count . "\n";

    if ($count > 0) {
        $sample = $conn->query('SELECT event_name, event_type, attended_date FROM student_event_register LIMIT 5');
        echo "Sample data:\n";
        while ($row = $sample->fetch_assoc()) {
            echo '- Event: ' . $row['event_name'] . ', Type: ' . ($row['event_type'] ?? 'NULL') . ', Date: ' . ($row['attended_date'] ?? 'NULL') . "\n";
        }

        // Check event types
        $types = $conn->query('SELECT DISTINCT event_type FROM student_event_register WHERE event_type IS NOT NULL');
        echo "\nAvailable event types:\n";
        while ($row = $types->fetch_assoc()) {
            echo '- ' . $row['event_type'] . "\n";
        }
    } else {
        echo "No data found in student_event_register table.\n";
    }
} else {
    echo 'Error: ' . $conn->error . "\n";
}
$conn->close();
