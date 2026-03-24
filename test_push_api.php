<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/OneSignalManager.php';

echo "Testing OneSignal API...\n";

$manager = new OneSignalManager();

// Use Reflection to access the private method 'sendToStudents'
$reflection = new ReflectionClass($manager);
// Test Broadcast Notification (Sent to 'Subscribed Users')
echo "Testing Broadcast Notification...\n";
try {
    $result = $manager->notifyNewHackathon(
        999,                     // Fake hackathon ID
        "Test Global Broadcast", // Title
        "2026-12-31",            // Deadline
        "This is a test global broadcast."
    );
    echo "Broadcast Result:\n";
    print_r($result);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
