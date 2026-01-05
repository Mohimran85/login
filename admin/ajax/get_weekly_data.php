<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Database connection
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get parameters
$year        = isset($_GET['year']) && is_numeric($_GET['year']) ? (int) $_GET['year'] : date('Y');
$start_month = isset($_GET['start_month']) && is_numeric($_GET['start_month']) ? (int) $_GET['start_month'] : 1;
$end_month   = isset($_GET['end_month']) && is_numeric($_GET['end_month']) ? (int) $_GET['end_month'] : 12;

// Ensure valid month range
$start_month = max(0, min(11, $start_month));
$end_month   = max(0, min(11, $end_month));

// If end month is before start month, swap them
if ($end_month < $start_month) {
    $temp        = $start_month;
    $start_month = $end_month;
    $end_month   = $temp;
}

// Convert to 1-based months for SQL
$start_month_sql = $start_month + 1;
$end_month_sql   = $end_month + 1;

// Get week data
$weekly_events = [];
$weekly_wins   = [];
$weeks         = [];

// Calculate start and end dates
$start_date = date('Y-m-d', strtotime("$year-$start_month_sql-01"));
$end_date   = date('Y-m-t', strtotime("$year-$end_month_sql-01"));

// Get all weeks in the range
$current_date = new DateTime($start_date);
$end_date_obj = new DateTime($end_date);
$week_num     = 1;

while ($current_date <= $end_date_obj) {
    // Get week start (Monday) and end (Sunday)
    $week_start = clone $current_date;
    $week_start->modify('monday this week');

    $week_end = clone $week_start;
    $week_end->modify('+6 days');

    // Ensure we don't go beyond end date
    if ($week_end > $end_date_obj) {
        $week_end = $end_date_obj;
    }

    $week_start_str = $week_start->format('Y-m-d');
    $week_end_str   = $week_end->format('Y-m-d');

    // Count events for this week (unique event types)
    $events_sql = "SELECT COUNT(DISTINCT event_type) as count
                   FROM student_event_register
                   WHERE start_date BETWEEN '$week_start_str' AND '$week_end_str'
                   AND event_type IS NOT NULL AND event_type != ''
                   AND verification_status = 'Approved'";

    $events_result = $conn->query($events_sql);
    $events_count  = $events_result ? (int) $events_result->fetch_assoc()['count'] : 0;

    // Count prize winners for this week
    $wins_sql = "SELECT COUNT(*) as count
                 FROM student_event_register
                 WHERE start_date BETWEEN '$week_start_str' AND '$week_end_str'
                 AND LOWER(TRIM(prize)) IN ('first', 'secound', 'third')
                 AND verification_status = 'Approved'";

    $wins_result = $conn->query($wins_sql);
    $wins_count  = $wins_result ? (int) $wins_result->fetch_assoc()['count'] : 0;

    // Format week label
    $week_label = 'Week ' . $week_num . ' (' . $week_start->format('M d') . '-' . $week_end->format('M d') . ')';

    $weeks[]         = $week_label;
    $weekly_events[] = $events_count;
    $weekly_wins[]   = $wins_count;

    // Move to next week
    $current_date->modify('+7 days');
    $week_num++;

    // Safety limit: max 20 weeks
    if ($week_num > 20) {
        break;
    }

}

$month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$date_range  = $month_names[$start_month] . ' - ' . $month_names[$end_month] . ' ' . $year;

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
