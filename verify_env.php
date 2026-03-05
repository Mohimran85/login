<?php
// Verify environment variables are configured (without exposing values)
$envFile = __DIR__ . '/.env';
if (! file_exists($envFile) || ! is_readable($envFile)) {
    echo "ERROR: .env file not found or not readable at " . __DIR__ . "\n";
    exit(1);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (! is_array($lines)) {
    echo "ERROR: Could not read .env file\n";
    exit(1);
}

$envVars = [];
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value)   = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

echo "=== Environment Variables Check ===\n";
echo "ONESIGNAL_APP_ID: " . (! empty($envVars['ONESIGNAL_APP_ID']) ? 'FOUND' : 'NOT FOUND') . "\n";
echo "ONESIGNAL_REST_API_KEY: " . (! empty($envVars['ONESIGNAL_REST_API_KEY']) ? 'FOUND' : 'NOT FOUND') . "\n";
