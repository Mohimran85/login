<?php
/**
 * ⚠️ WARNING: THIS IS A TEST/DEBUG FILE
 *
 * This file MUST be removed or secured before deploying to production!
 *
 * Security Issues:
 * - Forces authentication bypass ($_SESSION['logged_in'] = true)
 * - Hardcoded database credentials
 * - Exposes internal database structure
 *
 * To secure this file:
 * 1. DELETE this file completely, OR
 * 2. Move outside web root and run via CLI only, OR
 * 3. Add environment check to only allow in development
 */

// Only allow in development environment
if (! defined('APP_ENV') || getenv('APP_ENV') !== 'development') {
    http_response_code(404);
    die('Not found');
}

session_start();

// Proper authentication check (don't bypass in production!)
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    die('Unauthorized');
}

// Check admin role
if (! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

// Load credentials from environment
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'event_management_system';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("Test connection failed: " . $conn->connect_error);
    die("Connection failed");
}

echo "<h2>Database Test</h2>";

// Check if table exists
$tables = $conn->query("SHOW TABLES LIKE 'student_event_register'");
echo "<p>student_event_register table exists: " . ($tables->num_rows > 0 ? 'YES' : 'NO') . "</p>";

// Check total records
$total = $conn->query("SELECT COUNT(*) as cnt FROM student_event_register");
if ($total && $total_row = $total->fetch_assoc()) {
    $total_count = $total_row['cnt'];
    echo "<p>Total records in student_event_register: $total_count</p>";
}

// Check approved records
$approved = $conn->query("SELECT COUNT(*) as cnt FROM student_event_register WHERE verification_status = 'Approved'");
if ($approved && $approved_row = $approved->fetch_assoc()) {
    $approved_count = $approved_row['cnt'];
    echo "<p>Approved records: $approved_count</p>";
}

// Check prize values
echo "<h3>Prize values in database:</h3>";
$prize_check = $conn->query("SELECT DISTINCT prize, COUNT(*) as cnt FROM student_event_register WHERE prize IS NOT NULL AND prize != '' GROUP BY prize");
echo "<table border='1'><tr><th>Prize Value</th><th>Count</th></tr>";
while ($row = $prize_check->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['prize']) . "</td><td>" . $row['cnt'] . "</td></tr>";
}
echo "</table>";

// Check winners for 2026
echo "<h3>Winners for 2026:</h3>";
$year_winners = $conn->query("SELECT COUNT(*) as cnt FROM student_event_register
    WHERE YEAR(start_date) = 2026
    AND LOWER(TRIM(prize)) IN ('first', 'second', 'third')
    AND verification_status = 'Approved'");
$year_winners_count = $year_winners->fetch_assoc()['cnt'];
echo "<p>Winners in 2026: $year_winners_count</p>";

// Get actual winner records
echo "<h3>Winner Records:</h3>";
$winners = $conn->query("SELECT regno, event_name, prize, start_date, verification_status
    FROM student_event_register
    WHERE YEAR(start_date) = 2026
    AND LOWER(TRIM(prize)) IN ('first', 'second', 'third')
    AND verification_status = 'Approved'
    LIMIT 10");

echo "<table border='1'><tr><th>Regno</th><th>Event</th><th>Prize</th><th>Date</th><th>Status</th></tr>";
while ($row = $winners->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
    echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
    echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['verification_status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Test the JOIN query
echo "<h3>Test JOIN Query:</h3>";
$join_test = $conn->query("SELECT
    sr.regno,
    sr.name,
    ser.event_name,
    ser.prize
FROM student_event_register ser
INNER JOIN student_register sr ON ser.regno = sr.regno
WHERE YEAR(ser.start_date) = 2026
AND LOWER(TRIM(ser.prize)) IN ('first', 'second', 'third')
AND ser.verification_status = 'Approved'
LIMIT 5");

if ($join_test) {
    echo "<p>JOIN query successful. Rows: " . $join_test->num_rows . "</p>";
    echo "<table border='1'><tr><th>Regno</th><th>Name</th><th>Event</th><th>Prize</th></tr>";
    while ($row = $join_test->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>JOIN query failed: " . $conn->error . "</p>";
}

$conn->close();
