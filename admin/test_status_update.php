<?php
/**
 * Diagnostic: Test updating just the status field
 */
session_start();
require_once __DIR__ . '/../includes/DatabaseManager.php';

$hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$test_status  = isset($_GET['status']) ? $_GET['status'] : 'upcoming';

if (! $hackathon_id) {
    die("Usage: test_status_update.php?id=[hackathon_id]&status=[draft|upcoming|ongoing|completed|cancelled]");
}

$db = DatabaseManager::getInstance();

echo "<h2>Status Update Test</h2>";
echo "<pre>";

// Get current status
$current = $db->executeQuery("SELECT id, title, status FROM hackathon_posts WHERE id = ?", [$hackathon_id], 'i');
if (empty($current)) {
    die("Hackathon not found with ID: $hackathon_id");
}

echo "Current Hackathon:\n";
echo "ID: {$current[0]['id']}\n";
echo "Title: {$current[0]['title']}\n";
echo "Current Status: '{$current[0]['status']}'\n\n";

// Clean the test status
$clean_status   = strtolower(trim($test_status));
$valid_statuses = ['draft', 'upcoming', 'ongoing', 'completed', 'cancelled'];

echo "Test Status:\n";
echo "Raw: '{$test_status}'\n";
echo "Cleaned: '{$clean_status}'\n";
echo "Length: " . strlen($clean_status) . "\n";
echo "Hex: " . bin2hex($clean_status) . "\n";
echo "Valid: " . (in_array($clean_status, $valid_statuses) ? 'YES' : 'NO') . "\n\n";

if (! in_array($clean_status, $valid_statuses)) {
    die("ERROR: '{$clean_status}' is not a valid status value\n");
}

// Try the update
try {
    echo "Attempting update...\n";
    $result = $db->executeQuery(
        "UPDATE hackathon_posts SET status = ?, updated_at = NOW() WHERE id = ?",
        [$clean_status, $hackathon_id],
        'si'
    );

    echo "SUCCESS!\n";
    echo "Affected rows: " . ($result['affected_rows'] ?? 'unknown') . "\n\n";

    // Verify the update
    $updated = $db->executeQuery("SELECT status FROM hackathon_posts WHERE id = ?", [$hackathon_id], 'i');
    echo "New status: '{$updated[0]['status']}'\n";

} catch (Exception $e) {
    echo "FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<hr>";
echo "<h3>Test Different Values:</h3>";
echo "<ul>";
foreach ($valid_statuses as $status) {
    echo "<li><a href='?id={$hackathon_id}&status={$status}'>Test '{$status}'</a></li>";
}
echo "</ul>";
