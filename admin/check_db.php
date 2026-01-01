<?php
$conn   = new mysqli('localhost', 'root', '', 'event_management_system');
$result = $conn->query('SELECT id, regno, event_name, certificates FROM student_event_register WHERE certificates IS NOT NULL LIMIT 3');

echo "Checking database certificate storage:\n\n";

while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . "\n";
    echo "Regno: " . $row['regno'] . "\n";
    echo "Event: " . $row['event_name'] . "\n";
    echo "Certificates value: " . $row['certificates'] . "\n";
    echo "Length: " . strlen($row['certificates']) . " bytes\n";
    echo "Is it a path? " . (strlen($row['certificates']) < 200 ? "YES" : "NO") . "\n";
    echo "---\n\n";
}
