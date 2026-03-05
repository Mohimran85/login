<?php
session_start();
require_once "includes/DatabaseManager.php";

// Require admin authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Forbidden: Admin access required');
}

$db           = DatabaseManager::getInstance();
$hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($hackathon_id) {
    echo "<h2>Debug Info for Hackathon ID: $hackathon_id</h2>";

    // Check hackathon status
    $hackathon = $db->executeQuery("SELECT * FROM hackathon_posts WHERE id = ?", [$hackathon_id], 'i');
    if (! empty($hackathon)) {
        echo "<h3>Hackathon Details:</h3>";
        echo "Title: " . htmlspecialchars($hackathon[0]['title']) . "<br>";
        echo "Status: " . htmlspecialchars($hackathon[0]['status']) . "<br><br>";
    }

    // Check applied students
    $applied = $db->executeQuery(
        "SELECT student_regno, created_at FROM hackathon_applications WHERE hackathon_id = ?",
        [$hackathon_id],
        'i'
    );

    echo "<h3>Applied Students: " . count($applied) . "</h3>";
    if (! empty($applied)) {
        echo "<ul>";
        foreach ($applied as $app) {
            echo "<li>" . htmlspecialchars($app['student_regno']) . " - Applied: " . $app['created_at'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ No students have applied yet. Notifications will only go to applied students.</p>";
    }

    // Check notifications sent
    $notifications = $db->executeQuery(
        "SELECT user_regno, title, message, sent_at FROM notifications WHERE link LIKE ? ORDER BY sent_at DESC LIMIT 10",
        ['%hackathons.php?id=' . $hackathon_id . '%'],
        's'
    );

    echo "<h3>Recent Notifications Sent: " . count($notifications) . "</h3>";
    if (! empty($notifications)) {
        echo "<ul>";
        foreach ($notifications as $notif) {
            echo "<li><strong>" . htmlspecialchars($notif['user_regno']) . "</strong>: " .
            htmlspecialchars($notif['title']) . " - " .
            htmlspecialchars($notif['sent_at']) . "</li>";
        }
        echo "</ul>";
    }
} else {
    echo "<h2>All Hackathons</h2>";
    $hackathons = $db->executeQuery("SELECT id, title, status FROM hackathon_posts ORDER BY id DESC");
    echo "<ul>";
    foreach ($hackathons as $h) {
        echo "<li><a href='?id=" . $h['id'] . "'>" . htmlspecialchars($h['title']) .
        " (" . htmlspecialchars($h['status']) . ")</a></li>";
    }
    echo "</ul>";
}

// Show error log (last 20 lines)
echo "<hr><h3>Recent Error Logs:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;'>";
$logFile = __DIR__ . "/../../../php_error_log";
if (file_exists($logFile)) {
    $lines  = file($logFile);
    $recent = array_slice($lines, -20);
    echo htmlspecialchars(implode('', $recent));
} else {
    // Try XAMPP log location
    $logFile = "C:/xampp/apache/logs/error.log";
    if (file_exists($logFile)) {
        $lines  = file($logFile);
        $recent = array_slice($lines, -20);
        echo htmlspecialchars(implode('', $recent));
    } else {
        echo "Log file not found";
    }
}
echo "</pre>";
