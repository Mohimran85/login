<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/DatabaseManager.php';

// Set JSON header
header('Content-Type: application/json');

// Require authentication
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if user is admin
$username = $_SESSION['username'];
require_once __DIR__ . '/../../includes/db_config.php';
$conn = get_db_connection();

$teacher_status_sql = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ? LIMIT 1";
$stmt               = $conn->prepare($teacher_status_sql);
if (! $stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0 || ($row = $result->fetch_assoc()) && $row['status'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}
$stmt->close();
$conn->close();

// Get hackathon ID
if (! isset($_GET['hackathon_id']) || ! is_numeric($_GET['hackathon_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid hackathon ID']);
    exit();
}

$hackathon_id = (int) $_GET['hackathon_id'];

try {
    $db = DatabaseManager::getInstance();

    // Get view details with student information
    $views_sql = "SELECT
        hv.student_regno,
        sr.name as student_name,
        sr.department,
        MIN(hv.viewed_at) as first_viewed_at,
        MAX(hv.viewed_at) as last_viewed_at,
        COUNT(*) as view_count
    FROM hackathon_views hv
    LEFT JOIN student_register sr ON hv.student_regno = sr.regno
    WHERE hv.hackathon_id = ?
    GROUP BY hv.student_regno, sr.name, sr.department
    ORDER BY last_viewed_at DESC";

    $views = $db->executeQuery($views_sql, [$hackathon_id]);

    // Calculate statistics
    $total_views    = 0;
    $unique_viewers = count($views);

    foreach ($views as &$view) {
        $total_views += $view['view_count'];
        // Format dates
        $view['first_viewed_at']  = date('M d, Y H:i', strtotime($view['first_viewed_at']));
        $view['last_viewed_at']   = date('M d, Y H:i', strtotime($view['last_viewed_at']));
    }

    $avg_views_per_user = $unique_viewers > 0 ? round($total_views / $unique_viewers, 1) : 0;

    echo json_encode([
        'success' => true,
        'data'    => [
            'views'              => $views,
            'total_views'        => $total_views,
            'unique_viewers'     => $unique_viewers,
            'avg_views_per_user' => $avg_views_per_user,
        ],
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => 'Error fetching view details: ' . $e->getMessage(),
    ]);
}
