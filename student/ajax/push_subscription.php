<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/WebPushManager.php';

header('Content-Type: application/json');

// Require authentication
requireAuth();

$push     = WebPushManager::getInstance();
$username = $_SESSION['username'];

// Get student regno
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$stmt = $conn->prepare("SELECT regno FROM student_register WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit();
}

$user_data  = $result->fetch_assoc();
$user_regno = $user_data['regno'];
$stmt->close();

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_public_key':
        // Return VAPID public key for client-side subscription
        try {
            $publicKey = $push->getPublicKey();
            echo json_encode([
                'success'   => true,
                'publicKey' => $publicKey,
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to get public key: ' . $e->getMessage(),
            ]);
        }
        break;

    case 'subscribe':
        // Subscribe user to push notifications
        $input = json_decode(file_get_contents('php://input'), true);

        if (! isset($input['subscription'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'Missing subscription data',
            ]);
            exit();
        }

        try {
            $subscription = $input['subscription'];
            $result       = $push->subscribe($user_regno, $subscription, 'student');

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Successfully subscribed to notifications' : 'Subscription failed',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to subscribe: ' . $e->getMessage(),
            ]);
        }
        break;

    case 'unsubscribe':
        // Unsubscribe user from push notifications
        $input = json_decode(file_get_contents('php://input'), true);

        if (! isset($input['endpoint'])) {
            echo json_encode([
                'success' => false,
                'error'   => 'Missing endpoint',
            ]);
            exit();
        }

        try {
            $endpoint = $input['endpoint'];
            $result   = $push->unsubscribe($user_regno, $endpoint);

            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Successfully unsubscribed' : 'Unsubscribe failed',
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to unsubscribe: ' . $e->getMessage(),
            ]);
        }
        break;

    case 'status':
        // Get subscription status for this user
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM push_subscriptions
                WHERE user_regno = ? AND is_active = 1
            ");
            $stmt->bind_param("s", $user_regno);
            $stmt->execute();
            $result = $stmt->get_result();
            $data   = $result->fetch_assoc();
            $stmt->close();

            echo json_encode([
                'success'            => true,
                'subscribed'         => $data['count'] > 0,
                'subscription_count' => $data['count'],
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to get status: ' . $e->getMessage(),
            ]);
        }
        break;

    case 'test':
        // Send test notification
        try {
            $test_payload = [
                'title' => 'Test Notification',
                'body'  => 'This is a test notification from Event Management System',
                'icon'  => '/asserts/images/logo.png',
                'badge' => '/asserts/images/badge.png',
                'url'   => '/student/index.php',
                'tag'   => 'test-' . time(),
            ];

            $stats = $push->sendBulkNotifications([$user_regno], $test_payload);

            echo json_encode([
                'success' => true,
                'message' => 'Test notification sent',
                'stats'   => $stats,
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to send test: ' . $e->getMessage(),
            ]);
        }
        break;

    default:
        echo json_encode([
            'success' => false,
            'error'   => 'Invalid action',
        ]);
        break;
}

$conn->close();
