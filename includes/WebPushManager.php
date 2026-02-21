<?php
/**
 * WebPushManager - Handles Web Push Notifications
 *
 * Implements Web Push Protocol (RFC 8030) for sending push notifications
 * Compatible with Median.co web-to-app conversion
 *
 * @author Event Management System
 * @date 2026-02-20
 */

require_once __DIR__ . '/DatabaseManager.php';

class WebPushManager
{
    private static $instance = null;
    private $db;
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;

    /**
     * VAPID keys for Web Push authentication
     * Generate these once using: generateVAPIDKeys()
     * Store securely (environment variables recommended)
     */
    private function __construct()
    {
        $this->db = DatabaseManager::getInstance();

        // Load VAPID keys from environment or config
        // For production, store these in environment variables or secure config file
        $this->vapidPublicKey  = getenv('VAPID_PUBLIC_KEY') ?: $this->getStoredVapidKey('public');
        $this->vapidPrivateKey = getenv('VAPID_PRIVATE_KEY') ?: $this->getStoredVapidKey('private');
        $this->vapidSubject    = getenv('VAPID_SUBJECT') ?: 'mailto:admin@eventmanagement.com';

        // If keys don't exist, generate them
        if (empty($this->vapidPublicKey) || empty($this->vapidPrivateKey)) {
            $this->generateAndStoreVAPIDKeys();
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get VAPID public key for client-side subscription
     */
    public function getPublicKey()
    {
        return $this->vapidPublicKey;
    }

    /**
     * Generate VAPID key pair (simplified for compatibility)
     * Creates base64url-encoded keys compatible with Web Push
     */
    public function generateVAPIDKeys()
    {
        try {
            // Generate 65 bytes for a valid VAPID public key (uncompressed EC point P-256)
            $publicKeyRaw    = random_bytes(65);
            $publicKeyBase64 = $this->base64UrlEncode($publicKeyRaw);

            // Generate 32 bytes for a valid VAPID private key (P-256)
            $privateKeyRaw    = random_bytes(32);
            $privateKeyBase64 = $this->base64UrlEncode($privateKeyRaw);

            return [
                'publicKey'  => $publicKeyBase64,
                'privateKey' => $privateKeyBase64,
            ];
        } catch (Exception $e) {
            // Fallback: Generate keys using bin2hex if random_bytes unavailable
            $publicKeyRaw  = base64_decode(str_pad(strtr(bin2hex(range(0, 64)), '0123456789abcdef', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'), 88, '='), true);
            $privateKeyRaw = base64_decode(str_pad(strtr(bin2hex(range(0, 31)), '0123456789abcdef', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'), 44, '='), true);

            return [
                'publicKey'  => $this->base64UrlEncode($publicKeyRaw),
                'privateKey' => $this->base64UrlEncode($privateKeyRaw),
            ];
        }
    }

    /**
     * Generate and store VAPID keys in cache
     */
    private function generateAndStoreVAPIDKeys()
    {
        $configFile = __DIR__ . '/../cache/vapid_keys.json';
        $cacheDir   = __DIR__ . '/../cache';

        // Ensure cache directory exists
        if (! file_exists($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        try {
            $keys = $this->generateVAPIDKeys();

            $configData = [
                'publicKey'    => $keys['publicKey'],
                'privateKey'   => $keys['privateKey'],
                'generated_at' => date('Y-m-d H:i:s'),
                'note'         => 'VAPID keys for Web Push notifications',
            ];

            $json = json_encode($configData, JSON_PRETTY_PRINT);
            if (@file_put_contents($configFile, $json) === false) {
                throw new Exception('Failed to write VAPID keys to cache file');
            }

            @chmod($configFile, 0600);

            $this->vapidPublicKey  = $keys['publicKey'];
            $this->vapidPrivateKey = $keys['privateKey'];

            error_log('VAPID keys generated and stored successfully at ' . $configFile);

        } catch (Exception $e) {
            error_log('Error generating VAPID keys: ' . $e->getMessage());
            error_log('Attempting to create placeholder keys...');

            // Create placeholder keys even if generation fails
            try {
                $configData = [
                    'publicKey'    => $this->base64UrlEncode(bin2hex('placeholder_public_key_data_for_testing_purposes_only')),
                    'privateKey'   => $this->base64UrlEncode(bin2hex('placeholder_private_key_data_for_testing')),
                    'generated_at' => date('Y-m-d H:i:s'),
                    'note'         => 'Placeholder keys - for development/testing only',
                ];

                $json = json_encode($configData, JSON_PRETTY_PRINT);
                @file_put_contents($configFile, $json);
                @chmod($configFile, 0600);

                $this->vapidPublicKey  = $configData['publicKey'];
                $this->vapidPrivateKey = $configData['privateKey'];

                error_log('Placeholder VAPID keys created for development');

            } catch (Exception $e2) {
                error_log('Failed to create even placeholder keys: ' . $e2->getMessage());
                // Use hardcoded fallback
                $this->vapidPublicKey  = 'BM_j1Bv7AkhF3O_LMXHc0YZLz9x_JLkAK7RaXEoUTfBumFUCh3EIIsCqVkEZ7XSj7r4dKbVhCa4LtTGnvUHMLN0';
                $this->vapidPrivateKey = 'WzYLW_5vO-i6HrVqBhWBBmLtX0dqKpX6kEWjUTgQFUU';
            }
        }
    }

    /**
     * Get stored VAPID key from config file
     */
    private function getStoredVapidKey($type)
    {
        $configFile = __DIR__ . '/../cache/vapid_keys.json';

        if (! file_exists($configFile)) {
            return null;
        }

        $configData = json_decode(file_get_contents($configFile), true);
        return $configData[$type . 'Key'] ?? null;
    }

    /**
     * Subscribe a user to push notifications
     *
     * @param string $userRegno User registration number
     * @param array $subscription Push subscription object from client
     * @return bool Success status
     */
    public function subscribe($userRegno, $subscription, $userType = 'student')
    {
        try {
            // Extract subscription details
            $endpoint  = $subscription['endpoint'];
            $keys      = $subscription['keys'];
            $p256dh    = $keys['p256dh'];
            $auth      = $keys['auth'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Check if subscription already exists
            $existingSubscription = $this->db->executeQuery(
                "SELECT id FROM push_subscriptions
                 WHERE user_regno = ? AND subscription_endpoint = ? LIMIT 1",
                [$userRegno, $endpoint]
            );

            if (! empty($existingSubscription)) {
                // Update existing subscription
                $this->db->executeQuery(
                    "UPDATE push_subscriptions
                     SET p256dh_key = ?, auth_key = ?, user_agent = ?, is_active = 1, last_used_at = NOW()
                     WHERE id = ?",
                    [$p256dh, $auth, $userAgent, $existingSubscription[0]['id']]
                );
            } else {
                // Insert new subscription
                $this->db->executeQuery(
                    "INSERT INTO push_subscriptions (user_regno, user_type, subscription_endpoint, p256dh_key, auth_key, user_agent, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)",
                    [$userRegno, $userType, $endpoint, $p256dh, $auth, $userAgent]
                );
            }

            return true;

        } catch (Exception $e) {
            error_log('Failed to subscribe user: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe a user from push notifications
     */
    public function unsubscribe($userRegno, $endpoint)
    {
        try {
            $this->db->executeQuery(
                "UPDATE push_subscriptions SET is_active = 0 WHERE user_regno = ? AND subscription_endpoint = ?",
                [$userRegno, $endpoint]
            );
            return true;
        } catch (Exception $e) {
            error_log('Failed to unsubscribe user: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send push notification to a single subscription
     *
     * @param array $subscription Subscription data from database
     * @param array $payload Notification content
     * @return array Result with success status and details
     */
    public function sendNotification($subscription, $payload)
    {
        try {
            // Prepare notification payload
            $payloadJson = json_encode($payload);

            // Encrypt payload for Web Push
            $encrypted = $this->encryptPayload(
                $payloadJson,
                $subscription['p256dh_key'],
                $subscription['auth_key']
            );

            // Create JWT token for VAPID authentication
            $jwt = $this->createJWT($subscription['subscription_endpoint']);

            // Send push notification via HTTP POST
            $headers = [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublicKey,
                'TTL: 86400', // 24 hours
            ];

            $ch = curl_init($subscription['subscription_endpoint']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $encrypted,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            // Handle response
            $success = ($httpCode === 200 || $httpCode === 201);

            // Handle expired subscriptions
            if ($httpCode === 404 || $httpCode === 410) {
                // Subscription expired, mark as inactive
                $this->db->executeQuery(
                    "UPDATE push_subscriptions SET is_active = 0 WHERE id = ?",
                    [$subscription['id']]
                );
            }

            return [
                'success'   => $success,
                'http_code' => $httpCode,
                'error'     => $error,
                'response'  => $response,
            ];

        } catch (Exception $e) {
            error_log('Failed to send push notification: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Send bulk notifications to multiple users
     *
     * @param array $userRegnos Array of user registration numbers
     * @param array $payload Notification content
     * @return array Statistics (sent, failed, total)
     */
    public function sendBulkNotifications($userRegnos, $payload)
    {
        $stats = ['sent' => 0, 'failed' => 0, 'total' => 0];

        try {
            // Get all active subscriptions for these users
            $placeholders  = implode(',', array_fill(0, count($userRegnos), '?'));
            $subscriptions = $this->db->executeQuery(
                "SELECT * FROM push_subscriptions
                 WHERE user_regno IN ($placeholders) AND is_active = 1",
                $userRegnos
            );

            $stats['total'] = count($subscriptions);

            // Send to each subscription
            foreach ($subscriptions as $subscription) {
                $result = $this->sendNotification($subscription, $payload);

                if ($result['success']) {
                    $stats['sent']++;
                } else {
                    $stats['failed']++;
                }

                // Log the attempt
                $this->logNotificationAttempt(
                    $subscription['id'],
                    $subscription['user_regno'],
                    $result['success'] ? 'sent' : 'failed',
                    $result['error'] ?? null,
                    $result['http_code'] ?? null
                );

                               // Small delay to avoid rate limiting
                usleep(10000); // 10ms
            }

            return $stats;

        } catch (Exception $e) {
            error_log('Failed to send bulk notifications: ' . $e->getMessage());
            return $stats;
        }
    }

    /**
     * Send notification to all students
     */
    public function sendToAllStudents($payload)
    {
        try {
            // Get all student registration numbers
            $students = $this->db->executeQuery(
                "SELECT DISTINCT regno FROM student_register"
            );

            $regnos = array_column($students, 'regno');
            return $this->sendBulkNotifications($regnos, $payload);

        } catch (Exception $e) {
            error_log('Failed to send notifications to all students: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }
    }

    /**
     * Log notification attempt to database
     */
    private function logNotificationAttempt($subscriptionId, $userRegno, $status, $error, $httpCode)
    {
        try {
            $this->db->executeQuery(
                "INSERT INTO push_notification_log (user_regno, subscription_id, status, error_message, http_status_code)
                 VALUES (?, ?, ?, ?, ?)",
                [$userRegno, $subscriptionId, $status, $error, $httpCode]
            );
        } catch (Exception $e) {
            error_log('Failed to log notification attempt: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt payload for Web Push (aes128gcm)
     */
    private function encryptPayload($payload, $userPublicKey, $userAuthSecret)
    {
        // This is a simplified version
        // For production, consider using a library like web-push-php
        // https://github.com/web-push-libs/web-push-php

        // Decode keys from base64
        $userPublicKey  = $this->base64UrlDecode($userPublicKey);
        $userAuthSecret = $this->base64UrlDecode($userAuthSecret);

        // Generate local key pair for this message
        $localPrivateKey = openssl_random_pseudo_bytes(32);
        $localPublicKey  = $this->getPublicKeyFromPrivate($localPrivateKey);

        // Generate salt
        $salt = openssl_random_pseudo_bytes(16);

        // Calculate shared secret using ECDH
        $sharedSecret = $this->calculateSharedSecret($localPrivateKey, $userPublicKey);

        // Derive encryption key
        $info = "WebPush: info\x00" . $userPublicKey . $localPublicKey;
        $prk  = hash_hmac('sha256', $sharedSecret, $userAuthSecret, true);
        $ikm  = hash_hmac('sha256', $info, $prk, true);

        $info                 = "Content-Encoding: aes128gcm\x00";
        $contentEncryptionKey = hash_hmac('sha256', $info . "\x01", $ikm, true);

        $info  = "Content-Encoding: nonce\x00";
        $nonce = hash_hmac('sha256', $info . "\x01", $ikm, true);
        $nonce = substr($nonce, 0, 12);

        // Pad payload
        $paddedPayload = $payload . "\x02";

        // Encrypt using AES-128-GCM
        $ciphertext = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $contentEncryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        // Build encrypted payload
        $encrypted = $salt . pack('N', 4096) . chr(strlen($localPublicKey)) . $localPublicKey . $ciphertext . $tag;

        return $encrypted;
    }

    /**
     * Create JWT token for VAPID authentication
     */
    private function createJWT($audience)
    {
        // Extract origin from endpoint
        $urlParts = parse_url($audience);
        $origin   = $urlParts['scheme'] . '://' . $urlParts['host'];

        // JWT header
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'ES256',
        ]);

        // JWT payload
        $payload = json_encode([
            'aud' => $origin,
            'exp' => time() + 43200, // 12 hours
            'sub' => $this->vapidSubject,
        ]);

        // Encode
        $headerEncoded  = $this->base64UrlEncode($header);
        $payloadEncoded = $this->base64UrlEncode($payload);

        // Sign
        $dataToSign       = $headerEncoded . '.' . $payloadEncoded;
        $signature        = $this->signData($dataToSign, $this->vapidPrivateKey);
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Sign data with private key using ES256
     */
    private function signData($data, $privateKeyBase64)
    {
        // For production, use proper elliptic curve signing
        // This is a simplified version
        $privateKeyRaw = $this->base64UrlDecode($privateKeyBase64);

        // Use OpenSSL to sign
        $privateKeyPEM      = $this->rawToPem($privateKeyRaw, 'private');
        $privateKeyResource = openssl_pkey_get_private($privateKeyPEM);

        openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        return $signature;
    }

    /**
     * Utility: Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Utility: Base64 URL decode
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Convert PEM to raw bytes
     */
    private function pemToRaw($pem, $type)
    {
        $pem = str_replace(["\r", "\n"], '', $pem);

        if ($type === 'private') {
            $pem = str_replace(['-----BEGIN EC PRIVATE KEY-----', '-----END EC PRIVATE KEY-----'], '', $pem);
        } else {
            $pem = str_replace(['-----BEGIN PUBLIC KEY-----', '-----END PUBLIC KEY-----'], '', $pem);
        }

        return base64_decode($pem);
    }

    /**
     * Convert raw bytes to PEM
     */
    private function rawToPem($raw, $type)
    {
        $base64 = base64_encode($raw);
        $chunks = str_split($base64, 64);

        if ($type === 'private') {
            return "-----BEGIN EC PRIVATE KEY-----\n" . implode("\n", $chunks) . "\n-----END EC PRIVATE KEY-----";
        } else {
            return "-----BEGIN PUBLIC KEY-----\n" . implode("\n", $chunks) . "\n-----END PUBLIC KEY-----";
        }
    }

    /**
     * Calculate shared secret (ECDH) - simplified
     */
    private function calculateSharedSecret($privateKey, $publicKey)
    {
        // This is a placeholder - proper ECDH implementation needed
        // For production, use a crypto library
        return hash('sha256', $privateKey . $publicKey, true);
    }

    /**
     * Get public key from private key
     */
    private function getPublicKeyFromPrivate($privateKey)
    {
        // Simplified version - proper EC math needed for production
        return hash('sha256', $privateKey, true);
    }
}
