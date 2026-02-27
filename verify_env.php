<?php
$lines   = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$envVars = [];
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value)   = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

echo "=== Environment Variables Found ===\n";
echo "✓ App ID: " . (isset($envVars['ONESIGNAL_APP_ID']) && ! empty($envVars['ONESIGNAL_APP_ID']) ? substr($envVars['ONESIGNAL_APP_ID'], 0, 20) . '...' : 'NOT FOUND') . "\n";
echo "✓ REST API Key: " . (isset($envVars['ONESIGNAL_REST_API_KEY']) && ! empty($envVars['ONESIGNAL_REST_API_KEY']) ? substr($envVars['ONESIGNAL_REST_API_KEY'], 0, 20) . '...' : 'NOT FOUND') . "\n";
