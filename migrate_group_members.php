<?php
/**
 * Migration Script - Add group_members column
 *
 * This script will:
 * 1. Check if group_members column exists
 * 2. Add the column if it doesn't exist
 * 3. Display the current table structure
 * 4. Test the functionality
 *
 * Run this script by navigating to:
 * http://localhost/event_management_system/login/migrate_group_members.php
 */

echo "<h1>Group Members Migration Script</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #c3e6cb; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #f5c6cb; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; border: 1px solid #bee5eb; }
    .code { background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #dee2e6; font-family: monospace; margin: 10px 0; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; background: white; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #0c3878; color: white; }
</style>";

// Database connection
$conn = new mysqli("localhost", "root", "", "event_management_system");

if ($conn->connect_error) {
    echo "<div class='error'>❌ Connection failed: " . $conn->connect_error . "</div>";
    exit();
}

echo "<div class='success'>✅ Connected to database successfully</div>";

// Step 1: Check if column exists
echo "<h2>Step 1: Checking if group_members column exists...</h2>";
$check_result = $conn->query("SHOW COLUMNS FROM od_requests LIKE 'group_members'");

if ($check_result->num_rows > 0) {
    echo "<div class='success'>✅ Column 'group_members' already exists!</div>";
} else {
    echo "<div class='info'>ℹ️ Column 'group_members' does not exist. Adding it now...</div>";

    // Step 2: Add the column
    $alter_query = "ALTER TABLE od_requests ADD COLUMN group_members TEXT NULL COMMENT 'Comma-separated registration numbers for group OD requests' AFTER reason";

    if ($conn->query($alter_query)) {
        echo "<div class='success'>✅ Successfully added 'group_members' column to od_requests table!</div>";
    } else {
        echo "<div class='error'>❌ Error adding column: " . $conn->error . "</div>";
    }
}

// Step 3: Display table structure
echo "<h2>Step 2: Current Table Structure</h2>";
$structure = $conn->query("DESCRIBE od_requests");

if ($structure) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        $highlight = ($row['Field'] == 'group_members') ? 'style="background: #d4edda;"' : '';
        echo "<tr $highlight>";
        echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 4: Check recent OD requests
echo "<h2>Step 3: Recent OD Requests (with group_members data)</h2>";
$recent_ods = $conn->query("SELECT id, student_regno, event_name, group_members, status FROM od_requests ORDER BY id DESC LIMIT 10");

if ($recent_ods && $recent_ods->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Student Regno</th><th>Event Name</th><th>Group Members</th><th>Status</th></tr>";
    while ($row = $recent_ods->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['student_regno']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
        echo "<td>" . (empty($row['group_members']) ? '<em style="color: #999;">No group members</em>' : '<strong style="color: #0c3878;">' . htmlspecialchars($row['group_members']) . '</strong>') . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<div class='info'>ℹ️ No OD requests found in the database yet.</div>";
}

// Step 5: Test query for group member access
echo "<h2>Step 4: Testing Group Member Query</h2>";
echo "<div class='code'>";
echo "Testing SQL query to fetch OD requests where student is main requester OR group member...<br>";
echo "Query: SELECT * FROM od_requests WHERE student_regno = 'TEST123' OR FIND_IN_SET('TEST123', REPLACE(group_members, ',', ','))";
echo "</div>";

$test_query  = "SELECT COUNT(*) as total FROM od_requests WHERE group_members IS NOT NULL AND group_members != ''";
$test_result = $conn->query($test_query);
$test_data   = $test_result->fetch_assoc();

echo "<div class='info'>📊 Total OD requests with group members: <strong>" . $test_data['total'] . "</strong></div>";

// Instructions
echo "<h2>✅ Migration Complete!</h2>";
echo "<div class='success'>";
echo "<h3>What to do next:</h3>";
echo "<ol>";
echo "<li><strong>Test Group OD Creation:</strong> Go to student OD request page and create a new OD with group members</li>";
echo "<li><strong>Verify in Teacher View:</strong> Check teacher/od_approvals.php to see if group members appear</li>";
echo "<li><strong>Check OD Letter:</strong> Approve an OD and download the letter to see if all members are listed</li>";
echo "<li><strong>Delete this file:</strong> For security, delete this migration script after successful migration</li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>🔧 Troubleshooting:</h3>";
echo "<ul>";
echo "<li>If group members still don't show up, clear your browser cache and reload the page</li>";
echo "<li>Check that the form is submitting group_members[] array properly</li>";
echo "<li>Verify the INSERT query includes the group_members column</li>";
echo "<li>Check browser console (F12) for JavaScript errors when adding group members</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p style='text-align: center; color: #666; font-size: 12px;'>";
echo "Migration completed at: " . date('Y-m-d H:i:s') . " | ";
echo "<a href='student/od_request.php'>Go to OD Request Page</a> | ";
echo "<a href='teacher/od_approvals.php'>Go to Teacher Approvals</a>";
echo "</p>";

$conn->close();
