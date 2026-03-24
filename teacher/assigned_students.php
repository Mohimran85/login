<?php
    // Enable output compression
    if (! ob_get_level()) {
    ob_start("ob_gzhandler");
    }

                                              // Set caching headers
    header("Cache-Control: public, max-age=300"); // Cache for 5 minutes
    header("Expires: " . gmdate("D, d M Y H:i:s", time() + 300) . " GMT");

    session_start();

    // Check if user is logged in as a teacher
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
    }

    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;
    $is_counselor = false;
    $is_admin     = false;
    $counselor_id = null;

    // Get teacher data from teacher_register table
    $sql  = "SELECT name, faculty_id as employee_id, id, COALESCE(status, 'teacher') as status FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
    $teacher_data = $result->fetch_assoc();
    $is_counselor = ($teacher_data['status'] === 'counselor');
    $counselor_id = $teacher_data['id'];
    } else {
    header("Location: ../index.php");
    exit();
    }

    // Check if user is a counselor
    if (! $is_counselor) {
    header("Location: index.php");
    exit();
    }

    // Pagination settings
    $records_per_page = 15;
    $page             = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset           = ($page - 1) * $records_per_page;

    // Search and filter parameters
    $search                = isset($_GET['search']) ? trim($_GET['search']) : '';
    $semester_filter       = isset($_GET['semester']) ? $_GET['semester'] : '';
    $event_category_filter = isset($_GET['event_category']) ? $_GET['event_category'] : '';

    // Build WHERE clause
    $where_conditions = ["ca.counselor_id = ?", "ca.status = 'active'"];
    $params           = [$counselor_id];
    $types            = 'i';

    if (! empty($search)) {
    $where_conditions[]  = "(sr.name LIKE ? OR sr.regno LIKE ? OR sr.personal_email LIKE ?)";
    $search_param        = "%$search%";
    $params[]            = $search_param;
    $params[]            = $search_param;
    $params[]            = $search_param;
    $types              .= 'sss';
    }

    if (! empty($semester_filter)) {
    $where_conditions[]  = "GREATEST(LEAST(FLOOR(((YEAR(CURDATE()) - sr.year_of_join) * 12 + (MONTH(CURDATE()) - 7)) / 6) + 1, 8), 1) = ?";
    $params[]            = (int) $semester_filter;
    $types              .= 'i';
    }

    if (! empty($event_category_filter)) {
    $where_conditions[]  = "ser.event_type = ?";
    $params[]            = $event_category_filter;
    $types              .= 's';
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Export to Excel logic
    if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    if (ob_get_level()) {ob_end_clean();}

    $export_type = $_GET['type'] ?? 'students'; // 'students', 'events', 'prizes'

    if ($export_type === 'events' || $export_type === 'prizes') {
        $filename_prefix = $export_type === 'prizes' ? 'prizes_won_' : 'event_participations_';
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=" . $filename_prefix . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        $events_sql = "SELECT sr.name as student_name, sr.regno, sr.department,
                              ser.event_name, ser.organisation, ser.start_date, ser.event_type,
                              COALESCE(ser.prize, '') as prize,
                              COALESCE(ser.verification_status, 'Pending') as verification_status
                       FROM student_event_register ser
                       JOIN student_register sr ON ser.regno = sr.regno
                       JOIN counselor_assignments ca ON sr.regno = ca.student_regno
                       WHERE ca.counselor_id = ? AND ca.status = 'active'";

        if ($export_type === 'prizes') {
            $events_sql .= " AND ser.prize IN ('First', 'Second', 'Third')";
        }

        $events_sql .= " ORDER BY sr.name ASC, ser.start_date DESC";

        $exp_stmt = $conn->prepare($events_sql);
        $exp_stmt->bind_param("i", $counselor_id);
        $exp_stmt->execute();
        $exp_res = $exp_stmt->get_result();

        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>S.No</th>";
        echo "<th>Student Name</th>";
        echo "<th>Register Number</th>";
        echo "<th>Department</th>";
        echo "<th>Event Name</th>";
        echo "<th>Event Type</th>";
        echo "<th>Organisation</th>";
        echo "<th>Date</th>";
        echo "<th>Prize</th>";
        echo "<th>Status</th>";
        echo "</tr>";

        $sno = 1;
        while ($row = $exp_res->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $sno++ . "</td>";
            echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
            echo "<td>" . htmlspecialchars($row['department']) . "</td>";
            echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
            echo "<td>" . htmlspecialchars(date('M d, Y', strtotime($row['start_date']))) . "</td>";
            echo "<td>" . htmlspecialchars($row['prize'] ?: 'No Prize') . "</td>";
            echo "<td>" . htmlspecialchars($row['verification_status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit();
    } else {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=assigned_students_" . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        $export_sql = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join,
                                sr.personal_email as email, sr.dob, ca.assigned_date,
                                COUNT(DISTINCT ser.id) as total_events,
                                SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prizes_won
                        FROM counselor_assignments ca
                        JOIN student_register sr ON ca.student_regno = sr.regno
                        LEFT JOIN student_event_register ser ON sr.regno = ser.regno
                        $where_clause
                        GROUP BY sr.regno, sr.name, sr.department, sr.year_of_join,
                                sr.personal_email, sr.dob, ca.assigned_date
                        ORDER BY sr.name ASC";

        $exp_stmt = $conn->prepare($export_sql);
        if (! empty($types)) {
            $exp_stmt->bind_param($types, ...$params);
        }
        $exp_stmt->execute();
        $exp_res = $exp_stmt->get_result();

        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>S.No</th>";
        echo "<th>Student Name</th>";
        echo "<th>Register Number</th>";
        echo "<th>Department</th>";
        echo "<th>Semester</th>";
        echo "<th>Email</th>";
        echo "<th>Total Events</th>";
        echo "<th>Prizes Won</th>";
        echo "<th>Assigned Date</th>";
        echo "</tr>";

        $now_calc = new DateTime();
        $cur_yr   = (int) $now_calc->format('Y');
        $cur_mo   = (int) $now_calc->format('n');

        $sno = 1;
        while ($row = $exp_res->fetch_assoc()) {
            $join_yr      = (int) $row['year_of_join'];
            $months_since = ($cur_yr - $join_yr) * 12 + ($cur_mo - 7);
            $semester     = ($months_since < 0) ? 1 : min(max((int) floor($months_since / 6) + 1, 1), 8);

            echo "<tr>";
            echo "<td>" . $sno++ . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
            echo "<td>" . htmlspecialchars($row['department']) . "</td>";
            echo "<td>Sem " . $semester . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['total_events']) . "</td>";
            echo "<td>" . htmlspecialchars($row['prizes_won'] ?? 0) . "</td>";
            echo "<td>" . htmlspecialchars(date('M d, Y', strtotime($row['assigned_date']))) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        exit();
    }
    }

    // Get total records for pagination
    // Include LEFT JOIN if event category filter is applied
    if (! empty($event_category_filter)) {
    $count_sql = "SELECT COUNT(DISTINCT sr.regno) as total
                      FROM counselor_assignments ca
                      JOIN student_register sr ON ca.student_regno = sr.regno
                      LEFT JOIN student_event_register ser ON sr.regno = ser.regno
                      $where_clause";
    } else {
    $count_sql = "SELECT COUNT(*) as total
                      FROM counselor_assignments ca
                      JOIN student_register sr ON ca.student_regno = sr.regno
                      $where_clause";
    }

    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_records / $records_per_page);

    // Get assigned students with pagination
    $students_sql  = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join,
                           sr.personal_email as email, sr.dob, ca.assigned_date,
                           COUNT(DISTINCT ser.id) as total_events,
                           SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prizes_won
                    FROM counselor_assignments ca
                    JOIN student_register sr ON ca.student_regno = sr.regno
                    LEFT JOIN student_event_register ser ON sr.regno = ser.regno
                    $where_clause
                    GROUP BY sr.regno, sr.name, sr.department, sr.year_of_join,
                             sr.personal_email, sr.dob, ca.assigned_date
                    ORDER BY sr.name ASC
                    LIMIT ? OFFSET ?";

    $params[] = $records_per_page;
    $params[] = $offset;
    $types    .= 'ii';

    $students_stmt  = $conn->prepare($students_sql);
    $students_stmt->bind_param($types, ...$params);
    $students_stmt->execute();
    $students_result  = $students_stmt->get_result();

    // Store all results in an array
    $students_data = [];
    while ($row = $students_result->fetch_assoc()) {
    $students_data[] = $row;
    }

    // Auto-calculate semester from year_of_join for each student
    // Sem 1: Jul-Dec of join year, Sem 2: Jan-Jun next year, 6 months each, 8 total
    $now_calc = new DateTime();
    $cur_yr   = (int) $now_calc->format('Y');
    $cur_mo   = (int) $now_calc->format('n');
    foreach ($students_data as &$stu) {
    $join_yr         = (int) $stu['year_of_join'];
    $months_since    = ($cur_yr - $join_yr) * 12 + ($cur_mo - 7);
    $stu['semester'] = ($months_since < 0) ? 1 : min(max((int) floor($months_since / 6) + 1, 1), 8);
    }
    unset($stu);

    // Get available event categories for filter dropdown
    $event_categories_sql = "SELECT DISTINCT ser.event_type
                            FROM student_event_register ser
                            INNER JOIN counselor_assignments ca ON ser.regno = ca.student_regno
                            WHERE ca.counselor_id = ? AND ca.status = 'active'
                            AND ser.event_type IS NOT NULL AND ser.event_type != ''
                            ORDER BY ser.event_type ASC";
    $event_cat_stmt = $conn->prepare($event_categories_sql);
    $event_cat_stmt->bind_param("i", $counselor_id);
    $event_cat_stmt->execute();
    $event_categories_result = $event_cat_stmt->get_result();
    $event_categories        = [];
    while ($row = $event_categories_result->fetch_assoc()) {
    $event_categories[] = $row['event_type'];
    }
    $event_cat_stmt->close();

    // Get statistics
    $stats_sql = "SELECT
                    COUNT(DISTINCT sr.regno) as total_students,
                    COUNT(DISTINCT ser.id) as total_events,
                    SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners
                  FROM counselor_assignments ca
                  JOIN student_register sr ON ca.student_regno = sr.regno
                  LEFT JOIN student_event_register ser ON sr.regno = ser.regno
                  WHERE ca.counselor_id = ? AND ca.status = 'active'";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $counselor_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
    $stats_stmt->close();

    $stmt->close();
    $students_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Assigned Students - Counselor Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Prevent horizontal scroll */
        * {
            box-sizing: border-box;
        }

        html, body {
            max-width: 100%;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* Core Styles */
        .header {
            grid-area: header;
            background-color: #fff;
            height: 80px;
            display: flex;
            font-size: 15px;
            font-weight: 100;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            color: #1e4276;
            position: fixed;
            width: 100%;
            z-index: 1001;
            top: 0;
            left: 0;
        }

        .filters-section, .students-table {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .section-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .section-header .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Statistics Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #1e4276 0%, #2563eb 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(30, 66, 118, 0.2);
        }

        .stat-card.success {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Filters */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
        }

        .filter-input, .filter-select {
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 14px;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f1f3f5;
            font-size: 14px;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 25px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: var(--primary-color);
            color: white;
        }

        .pagination .active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert .material-symbols-outlined {
            font-size: 20px;
        }

        .update-btn {
            padding: 6px 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .update-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-1px);
        }

        .update-btn .material-symbols-outlined {
            font-size: 16px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
                padding-top: 80px;
            }

            .header .menu-icon {
                display: block;
            }

            .header .header-logo {
                display: none;
            }

            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                min-height: 100vh !important;
                max-height: 100vh !important;
                transform: translateX(-100%) !important;
                z-index: 1004 !important;
                background: #ffffff !important;
                box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
                transition: transform 0.3s ease !important;
                padding: 20px 0 !important;
                overflow-y: auto !important;
            }

            .close-sidebar {
                display: flex !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 1005 !important;
            }

            .sidebar.active::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                z-index: -1;
                backdrop-filter: blur(2px);
            }

            .main {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100vw;
                padding: 15px 10px;
                box-sizing: border-box;
            }

            .stats-container,
            .filters-section,
            .students-table {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
                width: 100%;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Mobile Cards View */
        .mobile-cards {
            display: none;
        }

        @media (max-width: 768px) {
            .table-container {
                display: none;
            }

            .mobile-cards {
                display: block;
                padding: 0;
            }

            .students-table {
                padding: 15px;
                background: transparent !important;
            }

            .student-card {
                background: white;
                border-radius: 12px;
                padding: 0;
                margin-bottom: 16px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                overflow: hidden;
                border: none;
            }

            .student-card-header {
                background: linear-gradient(135deg, #1e4276 0%, #2563eb 100%);
                color: white;
                padding: 16px;
                margin-bottom: 0;
                border-bottom: none;
            }

            .student-name {
                font-size: 18px;
                font-weight: 600;
                color: white;
                margin: 0 0 6px 0;
            }

            .student-regno {
                font-size: 13px;
                color: rgba(255,255,255,0.95);
                margin: 0;
            }

            .student-details {
                padding: 16px;
                background: white;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #f1f3f5;
                background: white;
            }

            .info-row:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }

            .info-label {
                font-size: 13px;
                font-weight: 500;
                color: #6c757d;
                flex: 0 0 auto;
            }

            .info-value {
                font-size: 14px;
                font-weight: 500;
                color: #212529;
                text-align: right;
                flex: 1;
                margin-left: 12px;
            }

            .info-value .badge {
                font-size: 11px;
                padding: 4px 8px;
            }

            /* Department badge in header */
            .student-card-header .badge {
                margin-top: 8px;
                background: rgba(255,255,255,0.2);
                color: white;
                border: 1px solid rgba(255,255,255,0.3);
            }

            .update-btn {
                width: 100%;
                max-width: 120px;
                justify-content: center;
                font-size: 12px;
                padding: 6px 10px;
            }

            /* Stats cards */
            .stats-container {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 20px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-number {
                font-size: 28px;
            }

            .stat-label {
                font-size: 13px;
            }

            /* Filters section */
            .filters-section {
                padding: 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .filter-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
            }

            /* Section headers */
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .section-header h2 {
                font-size: 18px;
            }

            .section-header .btn {
                width: 100%;
            }

            /* Alerts */
            .alert {
                padding: 12px 16px;
                font-size: 14px;
                margin: 0 15px 20px 15px;
            }

            /* Pagination */
            .pagination {
                flex-wrap: wrap;
                gap: 6px;
            }

            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 13px;
            }

            /* Hide email on very small screens */
            @media (max-width: 400px) {
                .info-row.email-row {
                    display: none;
                }

                .student-name {
                    font-size: 16px;
                }

                .stat-number {
                    font-size: 24px;
                }
            }
        }

        /* Clickable badge */
        .event-badge-clickable {
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.15s ease;
        }
        .event-badge-clickable:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        /* Event Modal */
        .event-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .event-modal-overlay.active {
            display: flex;
        }
        .event-modal {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 680px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
        }
        .event-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px 16px;
            border-bottom: 2px solid #e9ecef;
            flex-shrink: 0;
        }
        .event-modal-header h3 {
            margin: 0;
            color: #1e4276;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .event-modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #6c757d;
            display: flex;
            align-items: center;
            padding: 4px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .event-modal-close:hover {
            background: #f1f3f5;
            color: #212529;
        }
        .event-modal-body {
            overflow-y: auto;
            padding: 20px 24px;
            flex: 1;
        }
        .event-list-item {
            padding: 14px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .event-list-item:last-child {
            margin-bottom: 0;
        }
        .event-item-name {
            font-weight: 600;
            color: #212529;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .event-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            font-size: 12px;
            color: #6c757d;
        }
        .event-item-meta .meta-chip {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .event-item-meta .material-symbols-outlined {
            font-size: 14px;
        }
        .event-modal-loading, .event-modal-empty {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .event-modal-loading .material-symbols-outlined,
        .event-modal-empty .material-symbols-outlined {
            font-size: 48px;
            opacity: 0.4;
            display: block;
            margin-bottom: 10px;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
            </div>
            <div class="header-title">
                <p>Counselor Portal - My Assigned Students</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <?php
                        if ($is_admin) {
                            echo 'Admin Portal';
                        } elseif ($is_counselor) {
                            echo 'Counselor Portal';
                        } else {
                            echo 'Teacher Portal';
                        }
                    ?>
                </div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID:                                                                                             <?php echo htmlspecialchars($teacher_data['employee_id']); ?>
                    <?php
                        if ($is_admin) {
                            echo ' (Admin)';
                        } elseif ($is_counselor) {
                            echo ' (Counselor)';
                        }
                    ?>
                </div>
            </div>

            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <span class="material-symbols-outlined">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
                    <?php if ($is_counselor && ! $is_admin): ?>
                    <li class="nav-item">
                        <a href="assigned_students.php" class="nav-link active">
                            <span class="material-symbols-outlined">supervisor_account</span>
                            My Assigned Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_approvals.php" class="nav-link">
                            <span class="material-symbols-outlined">approval</span>
                            OD Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="internship_approvals.php" class="nav-link">
                            <span class="material-symbols-outlined">school</span>
                            Internship Validations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="verify_events.php" class="nav-link">
                            <span class="material-symbols-outlined">card_giftcard</span>
                            Event Certificate Validation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="reports.php" class="nav-link">
                            <span class="material-symbols-outlined">bar_chart</span>
                            Reports
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a href="../admin/index.php" class="nav-link">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            Admin Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/user_management.php" class="nav-link">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/manage_counselors.php" class="nav-link">
                            <span class="material-symbols-outlined">school</span>
                            Manage Counselors
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/participants.php" class="nav-link">
                            <span class="material-symbols-outlined">people</span>
                            Participants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/reports.php" class="nav-link">
                            <span class="material-symbols-outlined">bar_chart</span>
                            Reports
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <span class="material-symbols-outlined">person</span>
                            Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/logout.php" class="nav-link">
                            <span class="material-symbols-outlined">logout</span>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?php echo htmlspecialchars($_SESSION['success_message']);unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <?php echo htmlspecialchars($_SESSION['error_message']);unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_students'] ?? 0; ?></div>
                    <div class="stat-label">Assigned Students</div>
                </div>
                <div class="stat-card success" id="statTotalEvents" style="cursor:pointer;" title="Click to view all event participations">
                    <div class="stat-number"><?php echo $stats['total_events'] ?? 0; ?></div>
                    <div class="stat-label">Total Event Participations</div>
                </div>
                <div class="stat-card warning" id="statPrizesWon" style="cursor:pointer;" title="Click to view all prize winners">
                    <div class="stat-number"><?php echo $stats['prize_winners'] ?? 0; ?></div>
                    <div class="stat-label">Prizes Won</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="section-header" style="justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="material-symbols-outlined">filter_alt</span>
                        <h2>Filter Students</h2>
                    </div>
                    <?php
                        $export_params = $_GET;
                        if (isset($export_params['page'])) {
                            unset($export_params['page']);
                        }

                        $export_params['export'] = 'excel';
                        $export_url              = '?' . http_build_query($export_params);
                    ?>
                    <a href="<?php echo htmlspecialchars($export_url); ?>" class="btn" style="background-color: #28a745; color: white;">
                        <span class="material-symbols-outlined">download</span>
                        Export to Excel
                    </a>
                </div>

                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" class="filter-input"
                                   placeholder="Name, Regno, Email..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester" class="filter-select">
                                <option value="">All Semesters</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>"<?php echo $semester_filter == $i ? 'selected' : ''; ?>>
                                        Semester                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="event_category">Event Category</label>
                            <select id="event_category" name="event_category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($event_categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>"<?php echo $event_category_filter == $category ? ' selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">search</span>
                            Apply Filters
                        </button>
                        <a href="assigned_students.php" class="btn btn-secondary">
                            <span class="material-symbols-outlined">refresh</span>
                            Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="students-table">
                <div class="section-header">
                    <span class="material-symbols-outlined">group</span>
                    <h2>Assigned Students (<?php echo $total_records; ?> Total)</h2>
                </div>

                <?php if (count($students_data) > 0): ?>
                    <!-- Desktop Table View -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Registration No</th>
                                    <th>Department</th>
                                    <th>Semester</th>
                                    <th>Year of Join</th>
                                    <th>Events</th>
                                    <th>Prizes</th>
                                    <th>Assigned Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_data as $student): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['name']); ?></strong>
                                            <br>
                                            <small style="color: #6c757d;"><?php echo htmlspecialchars($student['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['regno']); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($student['department']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                Sem <?php echo htmlspecialchars($student['semester']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['year_of_join']); ?></td>
                                        <td>
                                            <span class="badge badge-info event-badge-clickable"
                                                  data-regno="<?php echo htmlspecialchars($student['regno']); ?>"
                                                  data-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                  data-filter="all"
                                                  title="Click to view events">
                                                <?php echo $student['total_events'] ?? 0; ?> Events
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($student['prizes_won'] ?? 0) > 0): ?>
                                                <span class="badge badge-success event-badge-clickable"
                                                      data-regno="<?php echo htmlspecialchars($student['regno']); ?>"
                                                      data-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                      data-filter="prizes"
                                                      title="Click to view prizes">
                                                    <?php echo $student['prizes_won']; ?> Prizes
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">No Prizes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $assigned_date = new DateTime($student['assigned_date']);
                                                echo $assigned_date->format('M d, Y');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards View -->
                    <div class="mobile-cards">
                        <?php foreach ($students_data as $student): ?>
                            <div class="student-card">
                                <div class="student-card-header">
                                    <div>
                                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                        <div class="student-regno"><?php echo htmlspecialchars($student['regno']); ?></div>
                                    </div>
                                    <span class="badge badge-info">
                                        <?php echo htmlspecialchars($student['department']); ?>
                                    </span>
                                </div>
                                <div class="student-details">
                                    <div class="info-row">
                                        <span class="info-label">Semester:</span>
                                        <span class="info-value">
                                                Sem <?php echo htmlspecialchars($student['semester']); ?>
                                            </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Year of Join:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($student['year_of_join']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Events:</span>
                                        <span class="info-value">
                                            <span class="badge badge-info event-badge-clickable"
                                                  data-regno="<?php echo htmlspecialchars($student['regno']); ?>"
                                                  data-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                  data-filter="all"
                                                  title="Click to view events">
                                                <?php echo $student['total_events'] ?? 0; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Prizes:</span>
                                        <span class="info-value">
                                            <?php if (($student['prizes_won'] ?? 0) > 0): ?>
                                                <span class="badge badge-success event-badge-clickable"
                                                      data-regno="<?php echo htmlspecialchars($student['regno']); ?>"
                                                      data-name="<?php echo htmlspecialchars($student['name']); ?>"
                                                      data-filter="prizes"
                                                      title="Click to view prizes">
                                                    <?php echo $student['prizes_won']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">None</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Assigned:</span>
                                        <span class="info-value">
                                            <?php
                                                $assigned_date = new DateTime($student['assigned_date']);
                                                echo $assigned_date->format('M d, Y');
                                            ?>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value" style="font-size: 11px;">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $semester_filter ? '&semester=' . urlencode($semester_filter) : ''; ?>">
                                    <span class="material-symbols-outlined">chevron_left</span>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $semester_filter ? '&semester=' . urlencode($semester_filter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $semester_filter ? '&semester=' . urlencode($semester_filter) : ''; ?>">
                                    <span class="material-symbols-outlined">chevron_right</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">group_off</span>
                        <h3>No Students Found</h3>
                        <p>
                            <?php if ($search || $department_filter || $semester_filter || $year_filter): ?>
                                No students match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You don't have any assigned students yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Events Modal -->
    <div class="event-modal-overlay" id="eventModalOverlay">
        <div class="event-modal">
            <div class="event-modal-header">
                <h3 id="eventModalTitle">
                    <span class="material-symbols-outlined">event</span>
                    Events Attended
                </h3>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <a href="#" id="eventModalExportBtn" class="btn" style="background-color: #28a745; color: white; padding: 6px 12px; font-size: 13px; display: none;">
                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span> Export
                    </a>
                    <button class="event-modal-close" id="eventModalClose">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>
            <div class="event-modal-body" id="eventModalBody">
                <div class="event-modal-loading">
                    <span class="material-symbols-outlined">hourglass_empty</span>
                    <p>Loading events...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const menuIcon = document.querySelector('.menu-icon');
        const sidebar = document.getElementById('sidebar');
        const closeSidebar = document.querySelector('.close-sidebar');

        menuIcon.addEventListener('click', () => {
            sidebar.classList.add('active');
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.remove('active');
        });

        // Close sidebar on outside click
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuIcon.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });

        // Event Modal
        const eventModalOverlay   = document.getElementById('eventModalOverlay');
        const eventModalClose     = document.getElementById('eventModalClose');
        const eventModalTitle     = document.getElementById('eventModalTitle');
        const eventModalBody      = document.getElementById('eventModalBody');
        const eventModalExportBtn = document.getElementById('eventModalExportBtn');

        function openEventModal(regno, studentName, filter) {
            eventModalExportBtn.style.display = 'none'; // Hide export for single student view
            const isPrizes = filter === 'prizes';
            eventModalTitle.innerHTML = '<span class="material-symbols-outlined">' + (isPrizes ? 'emoji_events' : 'event') + '</span>'
                + (isPrizes ? 'Prizes Won' : 'Events Attended') + ' &mdash; ' + studentName;
            eventModalBody.innerHTML = '<div class="event-modal-loading"><span class="material-symbols-outlined">hourglass_empty</span><p>Loading...</p></div>';
            eventModalOverlay.classList.add('active');

            fetch('get_student_events.php?regno=' + encodeURIComponent(regno) + '&filter=' + encodeURIComponent(filter))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) {
                        eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">error</span><p>' + data.error + '</p></div>';
                        return;
                    }
                    if (!data.events.length) {
                        eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">event_busy</span><p>No events found.</p></div>';
                        return;
                    }
                    var html = '';
                    data.events.forEach(function(ev) {
                        var prizeBadge = '';
                        if (ev.prize) {
                            var pc = ev.prize === 'First' ? 'badge-success' : (ev.prize === 'Second' ? 'badge-info' : 'badge-warning');
                            prizeBadge = '<span class="badge ' + pc + '">' + ev.prize + ' Prize</span>';
                        }
                        var sc = ev.verification_status === 'Verified' ? 'badge-success'
                               : (ev.verification_status === 'Rejected' ? 'badge-danger' : 'badge-warning');
                        html += '<div class="event-list-item">'
                            + '<div class="event-item-name">' + ev.event_name + '</div>'
                            + '<div class="event-item-meta">';
                        if (ev.organisation) {
                            html += '<span class="meta-chip"><span class="material-symbols-outlined">business</span>' + ev.organisation + '</span>';
                        }
                        if (ev.start_date) {
                            html += '<span class="meta-chip"><span class="material-symbols-outlined">calendar_today</span>' + ev.start_date + '</span>';
                        }
                        if (ev.event_type) {
                            html += '<span class="badge badge-info">' + ev.event_type + '</span>';
                        }
                        if (prizeBadge) html += prizeBadge;
                        html += '<span class="badge ' + sc + '">' + ev.verification_status + '</span>';
                        html += '</div></div>';
                    });
                    eventModalBody.innerHTML = html;
                })
                .catch(function() {
                    eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">error</span><p>Failed to load events.</p></div>';
                });
        }

        document.querySelectorAll('.event-badge-clickable').forEach(function(badge) {
            badge.addEventListener('click', function() {
                openEventModal(this.dataset.regno, this.dataset.name, this.dataset.filter || 'all');
            });
        });

        eventModalClose.addEventListener('click', function() {
            eventModalOverlay.classList.remove('active');
        });

        eventModalOverlay.addEventListener('click', function(e) {
            if (e.target === eventModalOverlay) {
                eventModalOverlay.classList.remove('active');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') eventModalOverlay.classList.remove('active');
        });

        function openAllEventsModal(filter) {
            const isPrizes = filter === 'prizes';

            // Set up export button
            eventModalExportBtn.style.display = 'inline-flex';
            eventModalExportBtn.href = '?export=excel&type=' + (isPrizes ? 'prizes' : 'events');

            eventModalTitle.innerHTML = '<span class="material-symbols-outlined">' + (isPrizes ? 'emoji_events' : 'event') + '</span>'
                + (isPrizes ? 'All Prizes Won' : 'All Event Participations');
            eventModalBody.innerHTML = '<div class="event-modal-loading"><span class="material-symbols-outlined">hourglass_empty</span><p>Loading...</p></div>';
            eventModalOverlay.classList.add('active');

            fetch('get_all_events.php?filter=' + encodeURIComponent(filter))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) {
                        eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">error</span><p>' + data.error + '</p></div>';
                        return;
                    }
                    if (!data.events.length) {
                        eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">event_busy</span><p>No events found.</p></div>';
                        return;
                    }
                    var html = '';
                    data.events.forEach(function(ev) {
                        var prizeBadge = '';
                        if (ev.prize) {
                            var pc = ev.prize === 'First' ? 'badge-success' : (ev.prize === 'Second' ? 'badge-info' : 'badge-warning');
                            prizeBadge = '<span class="badge ' + pc + '">' + ev.prize + ' Prize</span>';
                        }
                        var sc = ev.verification_status === 'Verified' ? 'badge-success'
                               : (ev.verification_status === 'Rejected' ? 'badge-danger' : 'badge-warning');
                        html += '<div class="event-list-item">'
                            + '<div class="event-item-name">' + ev.event_name + '</div>'
                            + '<div class="event-item-meta">';
                        html += '<span class="meta-chip"><span class="material-symbols-outlined">person</span>' + ev.student_name + ' (' + ev.regno + ')</span>';
                        if (ev.organisation) {
                            html += '<span class="meta-chip"><span class="material-symbols-outlined">business</span>' + ev.organisation + '</span>';
                        }
                        if (ev.start_date) {
                            html += '<span class="meta-chip"><span class="material-symbols-outlined">calendar_today</span>' + ev.start_date + '</span>';
                        }
                        if (ev.event_type) {
                            html += '<span class="badge badge-info">' + ev.event_type + '</span>';
                        }
                        if (prizeBadge) html += prizeBadge;
                        html += '<span class="badge ' + sc + '">' + ev.verification_status + '</span>';
                        html += '</div></div>';
                    });
                    eventModalBody.innerHTML = html;
                })
                .catch(function() {
                    eventModalBody.innerHTML = '<div class="event-modal-empty"><span class="material-symbols-outlined">error</span><p>Failed to load events.</p></div>';
                });
        }

        document.getElementById('statTotalEvents').addEventListener('click', function() {
            openAllEventsModal('all');
        });

        document.getElementById('statPrizesWon').addEventListener('click', function() {
            openAllEventsModal('prizes');
        });
    </script>
</body>
</html>


<?php
    $conn->close();
    if (ob_get_level()) {
    ob_end_flush();
    }
?>
