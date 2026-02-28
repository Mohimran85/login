<?php
require_once "includes/OneSignalManager.php";

$envFile = __DIR__ . "/.env";
$lines   = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$envVars = [];

foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value)   = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$appId  = $envVars['ONESIGNAL_APP_ID'] ?? '';
$apiKey = $envVars['ONESIGNAL_REST_API_KEY'] ?? '';

// Check view_notification API to get subscriber count
$url = "https://onesignal.com/api/v1/players?app_id=" . $appId . "&limit=10";

$authHeader = (strpos($apiKey, 'os_v2_') === 0)
    ? "Authorization: Bearer " . $apiKey
    : "Authorization: Basic " . $apiKey;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    $authHeader,
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>Subscription Status</h2>";

if ($httpCode === 200) {
    $data       = json_decode($response, true);
    $players    = $data['players'] ?? [];
    $totalCount = $data['total_count'] ?? 0;

    echo "<p><strong>Total Subscribed Players:</strong> " . $totalCount . "</p>";

    if ($totalCount > 0) {
        echo "<p style='color: green;'>✅ You have subscribed users!</p>";
        echo "<h3>Recent Subscriptions:</h3>";
        echo "<ul>";
        foreach ($players as $player) {
            $identifier = $player['external_user_id'] ?? $player['id'] ?? 'Unknown';
            $lastActive = $player['last_active'] ?? 'Never';
            echo "<li>Player: " . htmlspecialchars($identifier) . " - Last Active: " . htmlspecialchars($lastActive) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ No subscribed users yet.</p>";
        echo "<p><strong>To get subscribed:</strong></p>";
        echo "<ol>";
        echo "<li>Open student dashboard: <a href='student/index.php'>student/index.php</a></li>";
        echo "<li>Log in with a student account</li>";
        echo "<li>Allow notifications when prompted</li>";
        echo "<li>Wait 10-15 seconds for subscription to complete</li>";
        echo "<li>Refresh this page to check again</li>";
        echo "</ol>";
    }
} else {
    echo "<p style='color: red;'>❌ Error checking subscriptions: HTTP " . $httpCode . "</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}
