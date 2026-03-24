<?php
/**
 * Lightweight .env loader
 *
 * Reads the .env file and calls putenv() for every KEY=VALUE line.
 * Include this file early in any page that needs environment variables
 * (e.g. ONESIGNAL_APP_ID).
 *
 * Safe to include multiple times — guarded by define().
 */

if (! defined('ENV_LOADED')) {
    define('ENV_LOADED', true);

    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                // Trim all whitespace including \r from Windows line endings
                $line = trim($line, " \t\n\r\0\x0B");
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key   = trim($key);
                    $value = trim($value);  
                    if ($key !== '') {
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
}
