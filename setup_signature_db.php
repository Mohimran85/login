<?php
// Execute signature table creation script
$conn = new mysqli("localhost", "root", "", "event_management_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read and execute SQL file
$sql_file = file_get_contents('create_signature_table.sql');

// Split SQL commands by semicolon and execute each
$sql_commands = explode(';', $sql_file);

foreach ($sql_commands as $sql) {
    $sql = trim($sql);
    if (! empty($sql)) {
        echo "Executing: " . substr($sql, 0, 50) . "...\n";
        if ($conn->query($sql) === true) {
            echo "✓ Success\n";
        } else {
            echo "✗ Error: " . $conn->error . "\n";
        }
    }
}

echo "\nDatabase setup completed!\n";
$conn->close();
