<?php
    session_start();
    require_once "includes/DatabaseManager.php";
    require_once "includes/OneSignalManager.php";

    // Require admin authentication
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Forbidden: Admin access required');
    }

    $page_title = "Test OneSignal Notifications";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .test-section h2 {
            color: #667eea;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .test-result {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 10px 0;
            transition: background 0.3s;
        }
        .btn:hover { background: #764ba2; }
        .btn-secondary {
            background: #95a5a6;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 OneSignal Test Console</h1>
        <p class="subtitle">Test push notifications for SonaEms</p>

        <div class="test-section">
            <h2>Configuration Status</h2>
            <div class="test-result">
                <?php
                    $envFile = __DIR__ . "/.env";

                    // Check .env file exists
                    if (file_exists($envFile)) {
                        echo '<span class="success">✅ .env file found</span><br>';

                        // Read and parse .env file manually
                        $lines   = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $envVars = [];

                        foreach ($lines as $line) {
                            if (empty($line) || strpos($line, "#") === 0) {
                                continue;
                            }

                            if (strpos($line, "=") !== false) {
                                list($key, $value)   = explode("=", $line, 2);
                                $envVars[trim($key)] = trim($value);
                            }
                        }

                        // Check App ID
                        if (! empty($envVars['ONESIGNAL_APP_ID'])) {
                            echo '<span class="success">✅ App ID configured</span><br>';
                            echo '<small style="color: #666;">ID: ' . substr($envVars['ONESIGNAL_APP_ID'], 0, 10) . '...</small><br>';
                        } else {
                            echo '<span class="error">❌ App ID not found in .env</span><br>';
                        }

                        // Check API Key
                        if (! empty($envVars['ONESIGNAL_REST_API_KEY'])) {
                            echo '<span class="success">✅ REST API Key configured</span><br>';
                            echo '<small style="color: #666;">Key: ' . substr($envVars['ONESIGNAL_REST_API_KEY'], 0, 10) . '...</small><br>';
                        } else {
                            echo '<span class="error">❌ REST API Key not found in .env</span><br>';
                        }

                        // Test OneSignalManager loads correctly
                        $oneSignal = new OneSignalManager();
                        echo '<hr style="margin: 10px 0;">';
                        echo '<span class="info">✓ OneSignalManager instantiated successfully</span><br>';

                    } else {
                        echo '<span class="error">❌ .env file NOT found at: ' . $envFile . '</span><br>';
                    }
                ?>
            </div>
        </div>

        <div class="test-section">
            <h2>Send Test Notifications</h2>
            <form method="POST">
                <button type="submit" name="test_broadcast" class="btn">
                    🚀 Broadcast to All Students
                </button>
                <button type="submit" name="test_single" class="btn btn-secondary">
                    📢 Test Single Student
                </button>
                <button type="submit" name="check_db" class="btn btn-secondary">
                    📊 Check Database Logs
                </button>
            </form>
        </div>

        <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                echo '<div class="test-section"><h2>Test Results</h2><div class="test-result">';

                if (isset($_POST["test_broadcast"])) {
                    $oneSignal = new OneSignalManager();
                    $result    = $oneSignal->notifyNewHackathon(
                        999,
                        "Test Hackathon",
                        date("Y-m-d", strtotime("+7 days")),
                        "This is a test notification"
                    );

                    echo '<span class="info">Sending broadcast notification...</span><br>';
                    echo 'Status: <span class="' . ($result["status"] == 200 ? "success" : "error") . '">' . $result["status"] . '</span><br>';
                    echo '<pre>' . json_encode($result["response"], JSON_PRETTY_PRINT) . '</pre>';

                } elseif (isset($_POST["test_single"])) {
                    $db       = DatabaseManager::getInstance();
                    $students = $db->executeQuery("SELECT regno FROM student_register LIMIT 1");

                    if (! empty($students)) {
                        $studentRegno = $students[0]["regno"];
                        $oneSignal    = new OneSignalManager();
                        $result       = $oneSignal->notifyAppliedStudents(999, [$studentRegno], "Test Update");

                        echo '<span class="info">Sending to student: ' . $studentRegno . '</span><br>';
                        echo 'Status: <span class="' . ($result["status"] == 200 ? "success" : "error") . '">' . $result["status"] . '</span><br>';
                        echo '<pre>' . json_encode($result["response"], JSON_PRETTY_PRINT) . '</pre>';
                    } else {
                        echo '<span class="error">No students found in database</span>';
                    }

                } elseif (isset($_POST["check_db"])) {
                    $db     = DatabaseManager::getInstance();
                    $result = $db->executeQuery("SELECT COUNT(*) as count, notification_type, title FROM notifications GROUP BY notification_type, title LIMIT 10");

                    echo '<span class="info">Database Notification Logs</span><br><br>';
                    echo '<table style="width: 100%; border-collapse: collapse;">';
                    echo '<tr style="background: #f0f0f0;"><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Type</th><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Title</th><th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Count</th></tr>';

                    foreach ($result as $row) {
                        echo '<tr>';
                        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($row["notification_type"]) . '</td>';
                        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($row["title"]) . '</td>';
                        echo '<td style="border: 1px solid #ddd; padding: 8px; text-align: right;">' . $row["count"] . '</td>';
                        echo '</tr>';
                    }

                    echo '</table>';
                }

                echo '</div></div>';
            }
        ?>

        <div class="test-section">
            <h2>Documentation</h2>
            <p style="color: #666; line-height: 1.6; font-size: 13px;">
                <strong>How it works:</strong><br>
                • Click "Broadcast to All Students" to test OneSignal API connection<br>
                • Click "Test Single Student" to send to one student<br>
                • Click "Check Database" to see logged notifications<br><br>
                <strong>Notifications are automatically sent when:</strong><br>
                • New hackathon is created (status = upcoming)<br>
                • Hackathon goes live (draft → upcoming)<br>
                • Hackathon details are updated<br>
            </p>
        </div>
    </div>
</body>
</html>
