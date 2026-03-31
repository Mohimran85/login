<?php
session_start();
header('Content-Type: application/json');

// Enable error logging (not display) for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    // Check if user is logged in and has admin access
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    // Check admin role
    if (! isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        exit();
    }

    // Database connection
    require_once __DIR__ . '/../../includes/db_config.php';
    $conn = get_db_connection();
    // Get year parameter
    $year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int) $_GET['year'] : date('Y');

    // Get category filter if provided
    $category = isset($_GET['category']) && ! empty($_GET['category']) ? $_GET['category'] : null;

    // Build query to get winner details
    $sql = "SELECT
        sr.regno,
        sr.name,
        sr.semester,
        ser.event_name,
        ser.event_type,
        ser.prize,
        ser.prize_amount,
        ser.start_date,
        ser.end_date
    FROM student_event_register ser
    INNER JOIN student_register sr ON ser.regno = sr.regno
    WHERE YEAR(ser.start_date) = ?
    AND LOWER(TRIM(ser.prize)) IN ('first', 'second', 'third')
    AND ser.verification_status = 'Approved'";

    $params = [$year];
    $types  = 'i';

    // Add category filter if provided
    if ($category) {
        $sql      .= " AND ser.event_type = ?";
        $params[]  = $category;
        $types    .= 's';
    }

    $sql .= " ORDER BY
        CASE LOWER(TRIM(ser.prize))
            WHEN 'first' THEN 1
            WHEN 'second' THEN 2
            WHEN 'third' THEN 3
            ELSE 4
        END,
        ser.start_date DESC,
        sr.name ASC";

    $stmt = $conn->prepare($sql);
    if (! $stmt) {
        throw new Exception('Query preparation failed: ' . $conn->error);
    }

    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);

    if (! $stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    $winners = [];
    while ($row = $result->fetch_assoc()) {
        // Standardize prize names
        $prize = ucfirst(strtolower(trim($row['prize'])));
        if ($prize === 'Second') {
            $prize = 'Second';
        }

        $winners[] = [
            'regno'        => $row['regno'],
            'name'         => $row['name'],
            'semester'     => $row['semester'],
            'event_name'   => $row['event_name'],
            'event_type'   => $row['event_type'],
            'prize'        => $prize,
            'prize_amount' => $row['prize_amount'],
            'start_date'   => $row['start_date'],
            'end_date'     => $row['end_date'],
        ];
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success'       => true,
        'year'          => $year,
        'category'      => $category,
        'total_winners' => count($winners),
        'winners'       => $winners,
    ]);

} catch (Exception $e) {
    // Log full error server-side
    error_log('get_winners.php error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error',
    ]);
}
