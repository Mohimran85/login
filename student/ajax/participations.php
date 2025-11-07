<?php
/**
 * AJAX Endpoint for Student Participations
 * Handles paginated and filtered participations data
 */

session_start();
require_once '../includes/DatabaseManager.php';
require_once '../includes/CacheManager.php';

header('Content-Type: application/json');

// Check authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db    = DatabaseManager::getInstance();
$cache = CacheManager::getInstance();

// Get parameters
$regno  = $_SESSION['regno'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = min(50, max(5, intval($_GET['limit'] ?? 10)));
$filter = $_GET['filter'] ?? 'all'; // all, won, recent
$search = $_GET['search'] ?? '';

try {
    $data = getParticipations($db, $cache, $regno, $page, $limit, $filter, $search);

    echo json_encode([
        'success'   => true,
        'data'      => $data,
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
 * Get participations with caching and pagination
 */
function getParticipations($db, $cache, $regno, $page, $limit, $filter, $search)
{
    $offset   = ($page - 1) * $limit;
    $cacheKey = "participations_{$regno}_{$page}_{$limit}_{$filter}_" . md5($search);

    $data = $cache->get($cacheKey);

    if (! $data) {
        // Build WHERE clause
        $whereConditions = ["regno = ?"];
        $params          = [$regno];
        $types           = 's';

        if ($filter === 'won') {
            $whereConditions[] = "prize IN ('First', 'Second', 'Third')";
        } elseif ($filter === 'recent') {
            $whereConditions[] = "attended_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        }

        if (! empty($search)) {
            $whereConditions[] = "(event_name LIKE ? OR event_type LIKE ?)";
            $searchParam       = "%{$search}%";
            $params[]          = $searchParam;
            $params[]          = $searchParam;
            $types .= 'ss';
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get total count
        $countSql     = "SELECT COUNT(*) as total FROM student_event_register WHERE {$whereClause}";
        $countResult  = $db->executeQuery($countSql, $params, $types);
        $totalRecords = $countResult[0]['total'];

        // Get paginated results
        $sql = "SELECT event_name, event_type, attended_date, prize, certificate_path, poster_path
                FROM student_event_register
                WHERE {$whereClause}
                ORDER BY attended_date DESC, id DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $participations = $db->executeQuery($sql, $params, $types);

        // Format data
        $formattedData = array_map(function ($participation) {
            return [
                'event_name'       => $participation['event_name'],
                'event_type'       => $participation['event_type'],
                'attended_date'    => $participation['attended_date'],
                'formatted_date'   => date('M d, Y', strtotime($participation['attended_date'])),
                'prize'            => $participation['prize'],
                'has_prize'        => ! empty($participation['prize']) && $participation['prize'] !== 'No Prize',
                'has_certificate'  => ! empty($participation['certificate_path']),
                'has_poster'       => ! empty($participation['poster_path']),
                'certificate_path' => $participation['certificate_path'],
                'poster_path'      => $participation['poster_path'],
            ];
        }, $participations);

        $data = [
            'participations' => $formattedData,
            'pagination'     => [
                'current_page'  => $page,
                'total_records' => $totalRecords,
                'total_pages'   => ceil($totalRecords / $limit),
                'per_page'      => $limit,
                'has_next'      => $page < ceil($totalRecords / $limit),
                'has_prev'      => $page > 1,
            ],
            'filters'        => [
                'current_filter' => $filter,
                'search_term'    => $search,
            ],
        ];

        // Cache for 2 minutes
        $cache->set($cacheKey, $data, 120);
    }

    return $data;
}
