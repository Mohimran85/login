<?php
session_start();
require_once "includes/OneSignalManager.php";

// Require admin authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Forbidden: Admin access required');
}

echo "=== OneSignal Debug Info ===\n\n";

// Read .env file
$envFile = __DIR__ . "/.env";
$lines   = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$envVars = [];

foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value)   = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

echo "From .env file:\n";
echo "- App ID Length: " . strlen($envVars['ONESIGNAL_APP_ID'] ?? '') . " chars\n";
echo "- App ID Format: " . substr($envVars['ONESIGNAL_APP_ID'] ?? '', 0, 15) . "...\n";
echo "- REST API Key Length: " . strlen($envVars['ONESIGNAL_REST_API_KEY'] ?? '') . " chars\n";
echo "- REST API Key Start: " . substr($envVars['ONESIGNAL_REST_API_KEY'] ?? '', 0, 15) . "...\n";
echo "- REST API Key Type: " . (strpos($envVars['ONESIGNAL_REST_API_KEY'] ?? '', 'os_v2_app_') === 0 ? 'User Auth Key (v2)' : 'Unknown') . "\n\n";

// Try to instantiate OneSignalManager
$oneSignal = new OneSignalManager();

// Test a simple API call to check authentication
$url  = "https://onesignal.com/api/v1/notifications";
$data = [
    "app_id"            => $envVars['ONESIGNAL_APP_ID'] ?? '',
    "included_segments" => ["Test"],
    "headings"          => ["en" => "Test"],
    "contents"          => ["en" => "Test"],
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json; charset=utf-8",
    "Authorization: Basic " . ($envVars['ONESIGNAL_REST_API_KEY'] ?? ''),
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Making test API call...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";
echo "Response: " . $response . "\n\n";

if ($httpCode === 403) {
    echo "❌ ERROR: 403 Forbidden\n";
    echo "This means the REST API Key is invalid or the wrong type.\n\n";
    echo "SOLUTION:\n";
    echo "1. Go to OneSignal Dashboard: https://app.onesignal.com/\n";
    echo "2. Select your app\n";
    echo "3. Go to Settings > Keys & IDs\n";
    echo "4. Look for 'REST API Key' (NOT User Auth Key)\n";
    echo "5. The REST API Key should look like: NGYwMGZmMjItY2NkNy0xMWUzLTk5ZDUtMDAwYzI5NDBlNjJj\n";
    echo "   (It's usually a shorter string, NOT starting with os_v2_)\n";
}
