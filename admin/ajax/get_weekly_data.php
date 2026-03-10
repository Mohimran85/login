<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
if (! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Database connection
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME') ?: 'event_management_system';

if (! $db_user || ! $db_pass) {
    error_log('Database credentials missing in environment');
    echo json_encode(['success' => false, 'error' => 'Configuration error']);
    exit();
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get parameters
$year        = isset($_GET['year']) && is_numeric($_GET['year']) ? (int) $_GET['year'] : date('Y');
$start_month = isset($_GET['start_month']) && is_numeric($_GET['start_month']) ? (int) $_GET['start_month'] : 1;
$end_month   = isset($_GET['end_month']) && is_numeric($_GET['end_month']) ? (int) $_GET['end_month'] : 12;

// Ensure valid month range (1-12)
$start_month = max(1, min(12, $start_month));
$end_month   = max(1, min(12, $end_month));

// If end month is before start month, swap them
if ($end_month < $start_month) {
    $temp        = $start_month;
    $start_month = $end_month;
    $end_month   = $temp;
}

// Use months directly (already 1-based)
$start_month_sql = $start_month;
$end_month_sql   = $end_month;

// Get week data
$weekly_events = [];
$weekly_wins   = [];
$weeks         = [];

// Calculate start and end dates
$start_date = date('Y-m-d', strtotime("$year-$start_month_sql-01"));
$end_date   = date('Y-m-t', strtotime("$year-$end_month_sql-01"));

// Get all weeks in the range
$current_date   = new DateTime($start_date);
$end_date_obj   = new DateTime($end_date);
$start_date_obj = new DateTime($start_date);
$week_num       = 1;

while ($current_date <= $end_date_obj) {
    // Get week start (Monday) and end (Sunday)
    $week_start = clone $current_date;
    $week_start->modify('monday this week');

    $week_end = clone $week_start;
    $week_end->modify('+6 days');

    // Clamp to requested range
    if ($week_start < $start_date_obj) {
        $week_start = clone $start_date_obj;
    }
    if ($week_end > $end_date_obj) {
        $week_end = clone $end_date_obj;
    }

    $week_start_str = $week_start->format('Y-m-d');
    $week_end_str   = $week_end->format('Y-m-d');

    // Count events for this week (unique event types)
    $events_sql = "SELECT COUNT(DISTINCT event_type) as count
                   FROM student_event_register
                   WHERE start_date BETWEEN ? AND ?
                   AND event_type IS NOT NULL AND event_type != ''
                   AND verification_status = 'Approved'";

    $events_stmt  = $conn->prepare($events_sql);
    $events_count = 0;
    if ($events_stmt) {
        $events_stmt->bind_param('ss', $week_start_str, $week_end_str);
        $events_stmt->execute();
        $events_result = $events_stmt->get_result();
        $events_count  = $events_result ? (int) $events_result->fetch_assoc()['count'] : 0;
        $events_stmt->close();
    }

    // Count prize winners for this week
    $wins_sql = "SELECT COUNT(*) as count
                 FROM student_event_register
                 WHERE start_date BETWEEN ? AND ?
                 AND LOWER(TRIM(prize)) IN ('first', 'second', 'third')
                 AND verification_status = 'Approved'";

    $wins_stmt  = $conn->prepare($wins_sql);
    $wins_count = 0;
    if ($wins_stmt) {
        $wins_stmt->bind_param('ss', $week_start_str, $week_end_str);
        $wins_stmt->execute();
        $wins_result = $wins_stmt->get_result();
        $wins_count  = $wins_result ? (int) $wins_result->fetch_assoc()['count'] : 0;
        $wins_stmt->close();
    }

    // Format week label
    $week_label = 'Week ' . $week_num . ' (' . $week_start->format('M d') . '-' . $week_end->format('M d') . ')';

    $weeks[]         = $week_label;
    $weekly_events[] = $events_count;
    $weekly_wins[]   = $wins_count;

    // Move to next week
    $current_date->modify('+7 days');
    $week_num++;

    // Safety limit: max 52 weeks (full year)
    if ($week_num > 52) {
        break;
    }

}

$month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$date_range  = $month_names[$start_month - 1] . ' - ' . $month_names[$end_month - 1] . ' ' . $year;

$conn->close();

echo json_encode([
    'success'       => true,
    'weeks'         => $weeks,
    'weekly_events' => $weekly_events,
    'weekly_wins'   => $weekly_wins,
    'date_range'    => $date_range,
    'start_month'   => $start_month_sql,
    'end_month'     => $end_month_sql,
]);
