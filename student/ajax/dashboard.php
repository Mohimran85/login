<?php
/**
 * AJAX Endpoints for Student Dashboard
 * Handles asynchronous loading of dashboard components
 */

session_start();
require_once '../../includes/DatabaseManager.php';
require_once '../../includes/CacheManager.php';

// Set JSON content type
header('Content-Type: application/json');

// Disable error display to prevent HTML in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Check authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Initialize managers
try {
    $db    = DatabaseManager::getInstance();
    $cache = CacheManager::getInstance();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Get request parameters
$action   = $_GET['action'] ?? '';
$username = $_SESSION['username'] ?? '';

// Get student regno from username
$studentQuery = "SELECT regno FROM student_register WHERE username = ? LIMIT 1";
try {
    $studentData = $db->executeQuery($studentQuery, [$username]);
    $regno       = $studentData[0]['regno'] ?? '';

    if (empty($regno)) {
        throw new Exception('Student not found');
    }
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit();
}

try {
    switch ($action) {
        case 'dashboard_stats':
            $data = getDashboardStats($db, $cache, $regno);
            break;

        case 'recent_activities':
            $data = getRecentActivities($db, $cache, $regno);
            break;

        case 'event_breakdown':
            $data = getEventBreakdown($db, $cache, $regno);
            break;

        case 'od_requests':
            $data = getODRequests($db, $cache, $regno);
            break;

        case 'full_dashboard':
            $data = getFullDashboard($db, $cache, $regno);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit();
    }

    echo json_encode([
        'success'   => true,
        'data'      => $data,
        'cached'    => true,
        'timestamp' => time(),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error',
        'message' => $e->getMessage(),
    ]);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats($db, $cache, $regno)
{
    $cacheKey = "ajax_stats_" . $regno;
    $data     = $cache->get($cacheKey);

    if (! $data) {
        try {
            // Get total events
            $totalEventsQuery = "SELECT COUNT(*) as total FROM student_event_register WHERE regno = ? AND verification_status = 'Approved'";
            $totalResult      = $db->executeQuery($totalEventsQuery, [$regno]);
            $totalEvents      = $totalResult[0]['total'] ?? 0;

            // Get events won
            $eventsWonQuery = "SELECT COUNT(*) as won FROM student_event_register
                             WHERE regno = ? AND verification_status = 'Approved' AND prize IN ('First', 'Second', 'Third')";
            $wonResult = $db->executeQuery($eventsWonQuery, [$regno]);
            $eventsWon = $wonResult[0]['won'] ?? 0;

            // Get OD stats
            $odStatsQuery = "SELECT
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                            FROM od_requests WHERE student_regno = ?";
            $odResult = $db->executeQuery($odStatsQuery, [$regno]);
            $odStats  = $odResult[0] ?? ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];

            $data = [
                'total_events' => $totalEvents,
                'events_won'   => $eventsWon,
                'success_rate' => $totalEvents > 0 ?
                round(($eventsWon / $totalEvents) * 100, 1) : 0,
                'od_stats'     => [
                    'total'    => $odStats['total'] ?? 0,
                    'pending'  => $odStats['pending'] ?? 0,
                    'approved' => $odStats['approved'] ?? 0,
                    'rejected' => $odStats['rejected'] ?? 0,
                ],
            ];

            $cache->set($cacheKey, $data, 300); // 5 minutes
        } catch (Exception $e) {
            // Return empty data if query fails
            $data = [
                'total_events' => 0,
                'events_won'   => 0,
                'success_rate' => 0,
                'od_stats'     => [
                    'total'    => 0,
                    'pending'  => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ],
            ];
        }
    }

    return $data;
}

/**
 * Get recent activities
 */
function getRecentActivities($db, $cache, $regno)
{
    $cacheKey = "ajax_activities_" . $regno;
    $data     = $cache->get($cacheKey);

    if (! $data) {
        try {
            $query = "SELECT event_name, event_type, start_date, end_date, no_of_days, prize
                     FROM student_event_register
                     WHERE regno = ? AND verification_status = 'Approved'
                     ORDER BY start_date DESC, id DESC
                     LIMIT 10";
            $activities = $db->executeQuery($query, [$regno]);

            $data = array_map(function ($activity) {
                $dateStr = $activity['start_date'] === $activity['end_date']
                    ? date('M d, Y', strtotime($activity['start_date']))
                    : date('M d', strtotime($activity['start_date'])) . ' - ' . date('M d, Y', strtotime($activity['end_date']));
                return [
                    'event_name'     => $activity['event_name'],
                    'event_type'     => $activity['event_type'],
                    'attended_date'  => $activity['start_date'],
                    'formatted_date' => $dateStr . ' (' . $activity['no_of_days'] . ' day' . ($activity['no_of_days'] > 1 ? 's' : '') . ')',
                    'prize'          => $activity['prize'],
                    'has_prize'      => ! empty($activity['prize']) && $activity['prize'] !== 'No Prize',
                ];
            }, $activities);

            $cache->set($cacheKey, $data, 180); // 3 minutes
        } catch (Exception $e) {
            $data = [];
        }
    }

    return $data;
}

/**
 * Get event type breakdown
 */
function getEventBreakdown($db, $cache, $regno)
{
    $cacheKey = "ajax_breakdown_" . $regno;
    $data     = $cache->get($cacheKey);

    if (! $data) {
        try {
            $query = "SELECT event_type, COUNT(*) as count
                     FROM student_event_register
                     WHERE regno = ?
                     GROUP BY event_type
                     ORDER BY count DESC
                     LIMIT 10";
            $types = $db->executeQuery($query, [$regno]);
            $total = array_sum(array_column($types, 'count'));

            $data = array_map(function ($type) use ($total) {
                return [
                    'event_type' => $type['event_type'],
                    'count'      => $type['count'],
                    'percentage' => $total > 0 ? round(($type['count'] / $total) * 100, 1) : 0,
                ];
            }, $types);

            $cache->set($cacheKey, $data, 300); // 5 minutes
        } catch (Exception $e) {
            $data = [];
        }
    }

    return $data;
}

/**
 * Get OD requests
 */
function getODRequests($db, $cache, $regno)
{
    $cacheKey = "ajax_od_" . $regno;
    $data     = $cache->get($cacheKey);

    if (! $data) {
        try {
            $query = "SELECT event_name, status, request_date, event_date
                     FROM od_requests
                     WHERE student_regno = ?
                     ORDER BY request_date DESC
                     LIMIT 10";
            $requests = $db->executeQuery($query, [$regno]);

            $data = array_map(function ($request) {
                return [
                    'event_name'           => $request['event_name'],
                    'status'               => $request['status'],
                    'request_date'         => $request['request_date'],
                    'event_date'           => $request['event_date'],
                    'formatted_event_date' => date('M d, Y', strtotime($request['event_date'])),
                    'status_class'         => strtolower($request['status']),
                ];
            }, $requests);

            $cache->set($cacheKey, $data, 120); // 2 minutes
        } catch (Exception $e) {
            $data = [];
        }
    }

    return $data;
}

/**
 * Get full dashboard data
 */
function getFullDashboard($db, $cache, $regno)
{
    return [
        'stats'       => getDashboardStats($db, $cache, $regno),
        'activities'  => getRecentActivities($db, $cache, $regno),
        'breakdown'   => getEventBreakdown($db, $cache, $regno),
        'od_requests' => getODRequests($db, $cache, $regno),
    ];
}
