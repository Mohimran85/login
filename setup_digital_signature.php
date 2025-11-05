<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Digital Signature System</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-weight: 500;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #99d6ff;
        }
        .sql-command {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Digital Signature Database Setup</h1>

        <?php
            $setup_complete = false;
            $messages       = [];

            if (isset($_POST['setup_database'])) {
                // Database connection
                $conn = new mysqli("localhost", "root", "", "event_management_system");

                if ($conn->connect_error) {
                    $messages[] = ['type' => 'error', 'message' => 'Connection failed: ' . $conn->connect_error];
                } else {
                    $messages[] = ['type' => 'success', 'message' => 'Connected to database successfully'];

                    // SQL commands to create tables
                    $sql_commands = [
                        "CREATE TABLE IF NOT EXISTS teacher_signatures (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        teacher_id INT NOT NULL,
                        signature_type ENUM('upload', 'drawn', 'text') NOT NULL,
                        signature_data TEXT NOT NULL,
                        signature_hash VARCHAR(255) NOT NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (teacher_id) REFERENCES teacher_register(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_active_signature (teacher_id, is_active)
                    )",

                        "ALTER TABLE od_requests
                     ADD COLUMN IF NOT EXISTS signature_verification_code VARCHAR(255) DEFAULT NULL,
                     ADD COLUMN IF NOT EXISTS signature_timestamp TIMESTAMP NULL DEFAULT NULL",

                        "CREATE INDEX IF NOT EXISTS idx_teacher_signatures_active ON teacher_signatures(teacher_id, is_active)",
                        "CREATE INDEX IF NOT EXISTS idx_teacher_signatures_hash ON teacher_signatures(signature_hash)",
                        "CREATE INDEX IF NOT EXISTS idx_od_requests_verification ON od_requests(signature_verification_code)",
                    ];

                    $success_count  = 0;
                    $total_commands = count($sql_commands);

                    foreach ($sql_commands as $index => $sql) {
                        $command_preview = substr(trim($sql), 0, 50) . '...';

                        // Try to execute the command
                        if ($conn->query($sql) === true) {
                            $messages[] = ['type' => 'success', 'message' => "✓ Command " . ($index + 1) . " executed successfully"];
                            $messages[] = ['type' => 'info', 'message' => "SQL: $command_preview", 'sql' => $sql];
                            $success_count++;
                        } else {
                            // Handle specific errors gracefully
                            $error = $conn->error;
                            if (strpos($error, 'Duplicate column') !== false) {
                                $messages[] = ['type' => 'info', 'message' => "ℹ Command " . ($index + 1) . " skipped (column already exists)"];
                                $success_count++;
                            } elseif (strpos($error, 'already exists') !== false) {
                                $messages[] = ['type' => 'info', 'message' => "ℹ Command " . ($index + 1) . " skipped (already exists)"];
                                $success_count++;
                            } else {
                                $messages[] = ['type' => 'error', 'message' => "✗ Error in command " . ($index + 1) . ": " . $error];
                            }
                            $messages[] = ['type' => 'info', 'message' => "SQL: $command_preview", 'sql' => $sql];
                        }
                    }

                    if ($success_count == $total_commands) {
                        $setup_complete = true;
                        $messages[]     = ['type' => 'success', 'message' => "🎉 Database setup completed successfully! All $total_commands commands executed."];
                    } else {
                        $messages[] = ['type' => 'info', 'message' => "Database setup completed with $success_count/$total_commands commands successful."];
                    }

                    $conn->close();
                }
            }

            // Display messages
            foreach ($messages as $msg) {
                echo "<div class='status {$msg['type']}'>{$msg['message']}</div>";
                if (isset($msg['sql'])) {
                    echo "<div class='sql-command'>{$msg['sql']}</div>";
                }
            }

            if (! $setup_complete && empty($messages)) {
                echo "<div class='status info'>Click the button below to set up the digital signature database tables.</div>";
            }
        ?>

        <?php if (! $setup_complete): ?>
        <form method="POST" style="text-align: center; margin-top: 30px;">
            <button type="submit" name="setup_database" class="btn">
                🚀 Setup Digital Signature Database
            </button>
        </form>
        <?php else: ?>
        <div style="text-align: center; margin-top: 30px;">
            <p><strong>✅ Setup Complete!</strong></p>
            <p>You can now:</p>
            <ul style="text-align: left; display: inline-block;">
                <li>Visit <a href="teacher/digital_signature.php">Digital Signature Management</a> to create signatures</li>
                <li>Generate OD letters with digital signatures</li>
                <li>Verify signature authenticity in generated documents</li>
            </ul>
            <a href="teacher/digital_signature.php" class="btn">Go to Signature Management</a>
        </div>
        <?php endif; ?>

        <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>About Digital Signature System</h3>
            <p><strong>Features:</strong></p>
            <ul>
                <li>🖊️ Multiple signature types: Upload, Draw, Text-based</li>
                <li>🔐 SHA-256 encryption for signature verification</li>
                <li>⏰ Timestamp validation for authenticity</li>
                <li>📄 Automatic integration with OD letter generation</li>
                <li>🚫 Single active signature per teacher (security)</li>
            </ul>
            <p><strong>Security:</strong> All signatures are hashed and verified to prevent tampering.</p>
        </div>
    </div>
</body>
</html>