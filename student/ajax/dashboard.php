<?php
/**
 * AJAX Endpoints for Student Dashboard
 * Handles asynchronous loading of dashboard components
 */

session_start();
require_once '../includes/DatabaseManager.php';
require_once '../includes/CacheManager.php';

// Set JSON content type
header('Content-Type: application/json');

// Check authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Initialize managers
$db    = DatabaseManager::getInstance();
$cache = CacheManager::getInstance();

// Get request parameters
$action = $_GET['action'] ?? '';
$regno  = $_SESSION['regno'] ?? '';

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
            echo json_encode(['error' => 'Invalid action']);
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
        $stats = $db->getStudentDashboardData($regno);

        $data = [
            'total_events' => $stats['total_events'] ?? 0,
            'events_won'   => $stats['events_won'] ?? 0,
            'success_rate' => $stats['total_events'] > 0 ?
            round(($stats['events_won'] / $stats['total_events']) * 100, 1) : 0,
            'od_stats'     => [
                'total'    => $stats['total_od_requests'] ?? 0,
                'pending'  => $stats['pending_od'] ?? 0,
                'approved' => $stats['approved_od'] ?? 0,
                'rejected' => $stats['rejected_od'] ?? 0,
            ],
        ];

        $cache->set($cacheKey, $data, 300); // 5 minutes
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
        $activities = $db->getRecentActivities($regno, 10);

        $data = array_map(function ($activity) {
            return [
                'event_name'     => $activity['event_name'],
                'event_type'     => $activity['event_type'],
                'attended_date'  => $activity['attended_date'],
                'formatted_date' => date('M d, Y', strtotime($activity['attended_date'])),
                'prize'          => $activity['prize'],
                'has_prize'      => ! empty($activity['prize']) && $activity['prize'] !== 'No Prize',
            ];
        }, $activities);

        $cache->set($cacheKey, $data, 180); // 3 minutes
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
        $types = $db->getEventTypeBreakdown($regno, 10);
        $total = array_sum(array_column($types, 'count'));

        $data = array_map(function ($type) use ($total) {
            return [
                'event_type' => $type['event_type'],
                'count'      => $type['count'],
                'percentage' => $total > 0 ? round(($type['count'] / $total) * 100, 1) : 0,
            ];
        }, $types);

        $cache->set($cacheKey, $data, 300); // 5 minutes
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
        $requests = $db->getRecentODRequests($regno, 10);

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
