<?php
// Script to add event_photo column to student_event_register table
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "event_management_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE student_event_register ADD COLUMN event_photo VARCHAR(255) NULL AFTER certificates";

if ($conn->query($sql) === true) {
    echo "✓ Column 'event_photo' added successfully to student_event_register table!\n";
} else {
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "✓ Column 'event_photo' already exists in the table.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

$conn->close();
