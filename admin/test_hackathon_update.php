<?php
    /**
 * Diagnostic Tool: Test Hackathon Update
 * This file helps verify that hackathon updates are working correctly
 */
    session_start();
    require_once __DIR__ . '/../includes/DatabaseManager.php';

    // Require admin authentication
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Forbidden: Admin access required');
    }

    // Get hackathon ID from URL
    $hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (! $hackathon_id) {
    echo "<h2>Usage: test_hackathon_update.php?id=[hackathon_id]</h2>";
    echo "<p>Provide a hackathon ID to test</p>";
    exit;
    }

    $db = DatabaseManager::getInstance();

    // Fetch the hackathon details
    try {
    $hackathon_sql = "SELECT * FROM hackathon_posts WHERE id = ? LIMIT 1";
    $hackathons    = $db->executeQuery($hackathon_sql, [$hackathon_id], 'i');

    if (empty($hackathons)) {
        echo "<h2>Error: Hackathon not found</h2>";
        echo "<p>Hackathon ID: {$hackathon_id}</p>";
        exit;
    }

    $hackathon = $hackathons[0];

    } catch (Exception $e) {
    echo "<h2>Database Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hackathon Update Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0c3878;
            border-bottom: 3px solid #0c3878;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #0c3878;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .timestamp {
            color: #666;
            font-size: 14px;
            margin: 10px 0;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-upcoming { background: #e3f2fd; color: #1976d2; }
        .status-ongoing { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #f3e5f5; color: #7b1fa2; }
        .status-draft { background: #fff3e0; color: #f57c00; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background: #0c3878;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
        }
        .btn-refresh {
            background: #4caf50;
        }
        .btn-refresh:hover {
            background: #45a049;
        }
    </style>
    <script>
        // Auto-refresh every 5 seconds
        let autoRefresh = false;
        let refreshInterval;

        function toggleAutoRefresh() {
            autoRefresh = !autoRefresh;
            const btn = document.getElementById('autoRefreshBtn');

            if (autoRefresh) {
                btn.textContent = '⏸️ Stop Auto-Refresh';
                btn.style.background = '#f44336';
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 5000);
            } else {
                btn.textContent = '▶️ Start Auto-Refresh (5s)';
                btn.style.background = '#4caf50';
                clearInterval(refreshInterval);
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>🔍 Hackathon Update Diagnostic Tool</h1>
        <p class="timestamp">
            <strong>Last Fetched:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
            <strong>Hackathon ID:</strong> <?php echo $hackathon_id; ?>
        </p>

        <div style="margin: 20px 0;">
            <a href="#" onclick="location.reload(); return false;" class="btn btn-refresh">🔄 Manual Refresh</a>
            <a href="#" onclick="toggleAutoRefresh(); return false;" class="btn btn-refresh" id="autoRefreshBtn">▶️ Start Auto-Refresh (5s)</a>
            <a href="edit_hackathon.php?id=<?php echo $hackathon_id; ?>" class="btn">✏️ Edit Hackathon</a>
            <a href="hackathons.php" class="btn">📋 View All Hackathons</a>
        </div>

        <h2>Hackathon Details</h2>
        <table>
            <tr>
                <th>Field</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>ID</strong></td>
                <td><?php echo htmlspecialchars($hackathon['id']); ?></td>
            </tr>
            <tr>
                <td><strong>Title</strong></td>
                <td><?php echo htmlspecialchars($hackathon['title']); ?></td>
            </tr>
            <tr>
                <td><strong>Description</strong></td>
                <td><?php echo nl2br(htmlspecialchars($hackathon['description'])); ?></td>
            </tr>
            <tr>
                <td><strong>Organizer</strong></td>
                <td><?php echo htmlspecialchars($hackathon['organizer']); ?></td>
            </tr>
            <tr>
                <td><strong>Theme</strong></td>
                <td><?php echo htmlspecialchars($hackathon['theme']); ?></td>
            </tr>
            <tr>
                <td><strong>Tags</strong></td>
                <td><?php echo htmlspecialchars($hackathon['tags']); ?></td>
            </tr>
            <tr>
                <td><strong>Status</strong></td>
                <td>
                    <span class="status status-<?php echo $hackathon['status']; ?>">
                        <?php echo strtoupper($hackathon['status']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Start Date</strong></td>
                <td><?php echo date('M d, Y H:i', strtotime($hackathon['start_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>End Date</strong></td>
                <td><?php echo date('M d, Y H:i', strtotime($hackathon['end_date'])); ?></td>
            </tr>
            <tr>
                <td><strong>Registration Deadline</strong></td>
                <td><?php echo date('M d, Y H:i', strtotime($hackathon['registration_deadline'])); ?></td>
            </tr>
            <tr>
                <td><strong>Max Participants</strong></td>
                <td><?php echo $hackathon['max_participants'] ?: 'Unlimited'; ?></td>
            </tr>
            <tr>
                <td><strong>Hackathon Link</strong></td>
                <td>
                    <?php if ($hackathon['hackathon_link']): ?>
                        <a href="<?php echo htmlspecialchars($hackathon['hackathon_link']); ?>" target="_blank">
                            <?php echo htmlspecialchars($hackathon['hackathon_link']); ?>
                        </a>
                    <?php else: ?>
                        <em>None</em>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Poster URL</strong></td>
                <td><?php echo htmlspecialchars($hackathon['poster_url']); ?></td>
            </tr>
            <tr>
                <td><strong>Rules PDF</strong></td>
                <td><?php echo htmlspecialchars($hackathon['rules_pdf']); ?></td>
            </tr>
            <tr>
                <td><strong>Created At</strong></td>
                <td><?php echo date('M d, Y H:i:s', strtotime($hackathon['created_at'])); ?></td>
            </tr>
            <tr>
                <td><strong>Updated At</strong></td>
                <td>
                    <strong style="color: #e91e63;">
                        <?php echo date('M d, Y H:i:s', strtotime($hackathon['updated_at'])); ?>
                    </strong>
                </td>
            </tr>
        </table>

        <h2>Database Connection Info</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td><strong>Database Status</strong></td>
                <td style="color: green;">✅ Connected</td>
            </tr>
            <tr>
                <td><strong>Query Cache</strong></td>
                <td>Disabled (no cache key used)</td>
            </tr>
            <tr>
                <td><strong>Page Load Time</strong></td>
                <td><?php echo date('Y-m-d H:i:s'); ?></td>
            </tr>
        </table>

        <h2>Testing Instructions</h2>
        <ol style="line-height: 1.8;">
            <li>Click "Edit Hackathon" above</li>
            <li>Make a change (e.g., change the title or description)</li>
            <li>Save the changes</li>
            <li>Come back to this page and click "Manual Refresh" or wait for auto-refresh</li>
            <li>Check if the "Updated At" timestamp changed</li>
            <li>Verify your changes are reflected in the table above</li>
        </ol>

        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #856404;">💡 Troubleshooting Tips</h3>
            <ul>
                <li><strong>If "Updated At" changes but values don't:</strong> There might be an issue with the UPDATE query parameters</li>
                <li><strong>If nothing changes:</strong> Check the Apache/PHP error logs for database errors</li>
                <li><strong>Student page not updating:</strong> Make sure students do a hard refresh (Ctrl+F5 or Cmd+Shift+R)</li>
                <li><strong>Check error logs:</strong> Look in your XAMPP error logs for "Edit Hackathon - Update Debug" messages</li>
            </ul>
        </div>
    </div>
</body>
</html>
