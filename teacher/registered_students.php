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
    $username       = $_SESSION['username'];
    $teacher_data   = null;
    $teacher_status = 'teacher'; // Default status
    $is_admin       = false;
    $is_counselor   = false;

    // Get teacher data from teacher_register table
    $sql  = "SELECT name, faculty_id as employee_id, COALESCE(status, 'teacher') as status FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
    $teacher_data   = $result->fetch_assoc();
    $teacher_status = $teacher_data['status'];
    $is_admin       = ($teacher_status === 'admin');
    $is_counselor   = ($teacher_status === 'counselor');
    } else {
    // Fallback: use student data structure for now
    $sql  = "SELECT name, regno as employee_id FROM student_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit();
    }
    }

    // Pagination settings
    $records_per_page = 10;
    $page             = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset           = ($page - 1) * $records_per_page;

    // Search and filter parameters
    $search            = isset($_GET['search']) ? trim($_GET['search']) : '';
    $event_type_filter = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $department_filter = isset($_GET['department']) ? $_GET['department'] : '';
    $prize_filter      = isset($_GET['prize']) ? $_GET['prize'] : '';
    $location_filter   = isset($_GET['location']) ? $_GET['location'] : '';

    // Build WHERE clause
    $where_conditions = [];
    $params           = [];
    $types            = '';

    if (! empty($search)) {
    $where_conditions[]  = "(sr.name LIKE ? OR sr.regno LIKE ? OR ser.event_name LIKE ?)";
    $search_param        = "%$search%";
    $params[]            = $search_param;
    $params[]            = $search_param;
    $params[]            = $search_param;
    $types              .= 'sss';
    }

    if (! empty($event_type_filter)) {
    $where_conditions[]  = "ser.event_type = ?";
    $params[]            = $event_type_filter;
    $types              .= 's';
    }

    if (! empty($department_filter)) {
    $where_conditions[]  = "sr.department = ?";
    $params[]            = $department_filter;
    $types              .= 's';
    }

    if (! empty($prize_filter)) {
    if ($prize_filter === 'winner') {
        $where_conditions[] = "ser.prize IN ('First', 'Second', 'Third')";
    } else {
        $where_conditions[]  = "ser.prize = ?";
        $params[]            = $prize_filter;
        $types              .= 's';
    }
    }

    if (! empty($location_filter)) {
    if ($location_filter === 'tamilnadu') {
        $where_conditions[] = "ser.state = 'Tamil Nadu'";
    } elseif ($location_filter === 'outside') {
        $where_conditions[] = "ser.state != 'Tamil Nadu'";
    }
    }

    $where_clause = ! empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total records for pagination
    $count_sql = "SELECT COUNT(*) as total
                  FROM student_register sr
                  JOIN student_event_register ser ON sr.regno = ser.regno
                  $where_clause";

    if (! empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
    } else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
    }

    $total_pages = ceil($total_records / $records_per_page);

    // Get registered students with pagination
    $students_sql = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join, sr.personal_email as email, sr.regno as phone,
                           ser.event_name, ser.event_type, ser.start_date, ser.end_date, ser.no_of_days, ser.prize,
                           ser.organisation as college, ser.state as position, ser.semester, ser.current_year, ser.id as event_id
                    FROM student_register sr
                    JOIN student_event_register ser ON sr.regno = ser.regno
                    $where_clause
                    ORDER BY ser.start_date DESC, ser.id DESC
                    LIMIT ? OFFSET ?";

    $params[] = $records_per_page;
    $params[] = $offset;
    $types    .= 'ii';

    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param($types, ...$params);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();

    // Store all results in an array to avoid duplicate data
    $students_data = [];
    while ($row = $students_result->fetch_assoc()) {
    $students_data[] = $row;
    }

    // Get filter options (optimize by caching results)
    $event_types_result = $conn->query("SELECT DISTINCT event_type FROM student_event_register ORDER BY event_type LIMIT 50");
    $departments_result = $conn->query("SELECT DISTINCT department FROM student_register ORDER BY department LIMIT 50");

    // Get statistics efficiently in a single query
    $stats_sql = "SELECT
                    COUNT(DISTINCT ser.regno) as unique_students,
                    COUNT(*) as total_registrations,
                    SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners
                  FROM student_event_register ser";
    $stats_result = $conn->query($stats_sql);
    $stats        = $stats_result->fetch_assoc();

    $stmt->close();
    $students_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Registered Students - Teacher Dashboard</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../assets/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        /* Global Reset */
        * {
            box-sizing: border-box;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        /* Fix for Grid Layout to prevent blowout */
        .main {
            min-width: 0;
            width: 100%;
            overflow-x: hidden; /* Ensure main container doesn't scroll */
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
            box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px,
                rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;
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
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .filter-group input, .filter-group select {
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: #2d5aa0;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #2d5aa0;
            color: white;
        }

        .btn-primary:hover {
            background: #1e3a6f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45, 90, 160, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: linear-gradient(135deg, #2d5aa0 0%, #1e3a6f 100%);
            font-weight: 600;
            color: white;
        }

        tr:hover {
            background: #f8f9fa;
            transition: background 0.2s ease;
        }

        .prize-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .prize-first { background: #ffd700; color: #b8860b; }
        .prize-second { background: #c0c0c0; color: #666; }
        .prize-third { background: #cd7f32; color: #fff; }
        .prize-participation { background: #e8f4fd; color: #3498db; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #2d5aa0;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pagination a, .pagination span {
            padding: 10px 16px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #f8f9fa;
            border-color: #2d5aa0;
        }

        .pagination .current {
            background: linear-gradient(135deg, #2d5aa0 0%, #1e3a6f 100%);
            color: white;
            border-color: #2d5aa0;
        }

        /* Excel-like Table Design */
        .desktop-table {
            display: block;
            width: 100%;
            overflow-x: auto; /* Enables horizontal scroll */
            -webkit-overflow-scrolling: touch;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            scrollbar-width: thin;
            scrollbar-color: #2d5aa0 #f1f3f4;
            margin-bottom: 20px;
        }

        .desktop-table::-webkit-scrollbar {
            height: 12px; /* Slightly thicker for better visibility */
        }

        .desktop-table::-webkit-scrollbar-track {
            background: #f1f3f4;
            border-radius: 6px;
        }

        .desktop-table::-webkit-scrollbar-thumb {
            background-color: #2d5aa0;
            border-radius: 6px;
            border: 2px solid #f1f3f4; /* Adds padding around thumb */
        }

        .mobile-card-table {
            display: none;
        }

        .excel-table {
            width: 100%;
            min-width: 1200px; /* Minimum width to force scroll on smaller screens */
            border-collapse: separate; /* Changed for border-radius */
            border-spacing: 0;
            font-size: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #fff;
        }

        .excel-table th, .excel-table td {
            border: 1px solid #d1d1d1;
            padding: 12px 15px; /* Increased padding */
            white-space: normal;
            vertical-align: top;
            word-wrap: break-word;
        }

        /* Prevent specific columns from wrapping (Year, Start Date, End Date) */
        .excel-table th:nth-child(5),
        .excel-table td:nth-child(5),
        .excel-table th:nth-child(8),
        .excel-table td:nth-child(8),
        .excel-table th:nth-child(9),
        .excel-table td:nth-child(9) {
            white-space: nowrap;
        }

        .excel-table th {
            background: linear-gradient(135deg, #2d5aa0 0%, #2d5aa0 100%);
            color: white;
            font-weight: 600;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .excel-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .excel-table tr:hover {
            background-color: #e6f7ff;
            cursor: default;
        }

        .excel-table td {
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-align: center;
        }

        /* Mobile Responsive Design */
        @media (max-width: 900px) {
            .desktop-table {
                display: none;
            }
            .mobile-card-table {
                display: block;
            }
        }


        .student-card h4 {
            margin: 0 0 15px 0;
            color: #1e4276;
            font-size: 18px;
            font-weight: 600;
            border-bottom: 2px solid #e1e5e9;
            padding-bottom: 10px;
        }

        .student-card p {
            margin: 10px 0;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            font-size: 15px;
            color: #2d3748;
            display: flex;
            align-items: center;
        }

        .student-card p:last-of-type {
            border-bottom: none;
            margin-bottom: 15px;
        }

        .student-card p strong {
            color: #4a5568;
            font-weight: 600;
            min-width: 180px;
        }

        /* Mobile Responsive Design */
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
                padding: 15px;
            }

            .page-title {
                font-size: 24px;
                margin-bottom: 15px;
                text-align: center;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 15px 10px;
            }

            .stat-number {
                font-size: 1.5em;
            }

            .stat-label {
                font-size: 12px;
            }

            .filters {
                padding: 15px;
                margin-bottom: 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .filter-actions {
                flex-direction: column;
                gap: 8px;
                margin-top: 10px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 12px;
                font-size: 16px;
            }

            .students-table {
                margin: 0;
                border-radius: 8px;
            }

            .table-header {
                padding: 15px;
            }

            .table-header h3 {
                font-size: 18px;
                margin-bottom: 5px;
            }

            /* Mobile table styling */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 600px; /* Minimum width to maintain readability */
                font-size: 14px;
            }

            th, td {
                padding: 8px 6px;
                white-space: nowrap;
            }

            th {
                font-size: 12px;
                position: sticky;
                top: 0;
                background: #f8f9fa;
                z-index: 10;
            }

            /* Hide less important columns on very small screens */
            .mobile-hide {
                display: none;
            }

            .prize-badge {
                font-size: 10px;
                padding: 2px 6px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 5px;
                margin: 15px 0;
            }

            .pagination a,
            .pagination span {
                padding: 6px 10px;
                font-size: 14px;
                min-width: 35px;
                text-align: center;
            }

            /* Card-style table for better mobile experience - controlled in smaller media query */
        }

        @media (max-width: 480px) {
            .main {
                padding: 70px 5px 15px 5px;
            }

            .page-title {
                font-size: 20px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 12px 8px;
                border-radius: 12px;
            }

            .filters-section {
                padding: 15px 10px;
                border-radius: 12px;
                margin-bottom: 15px;
            }

            .filter-group input,
            .filter-group select {
                padding: 8px;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            /* Mobile adjustments for cards */
            .student-card {
                padding: 16px;
                margin-bottom: 12px;
            }

            .student-card h4 {
                font-size: 16px;
                margin-bottom: 12px;
                padding-bottom: 8px;
            }

            .student-card p {
                margin: 8px 0;
                padding: 8px 0;
                font-size: 14px;
                flex-direction: column;
                align-items: flex-start;
            }

            .student-card p strong {
                min-width: auto;
                font-weight: 500;
            }

            .action-btn {
                padding: 8px 12px;
                font-size: 12px;
                margin: 4px 8px 4px 0;
            }

            .prize-first {
                background: #ffd700 !important;
                color: #b8860b !important;
            }

            .prize-second {
                background: #c0c0c0 !important;
                color: #666 !important;
            }

            .prize-third {
                background: #cd7f32 !important;
                color: #fff !important;
            }

            .prize-participation {
                background: #e8f4fd !important;
                color: #3498db !important;
            }

            /* Simple Table Header */
            .students-table {
                margin: 0;
                border-radius: 8px;
                overflow: hidden;
                background: transparent;
            }

            .table-header {
                padding: 16px 15px;
                background: #f8f9fa;
                border-bottom: 1px solid #e1e5e9;
            }

            .table-header h3 {
                font-size: 16px;
                margin: 0;
                color: #2d3748;
                font-weight: 600;
            }

            /* Simple Empty State */
            .empty-state {
                text-align: center;
                padding: 40px 20px;
                background: #fff;
                border-radius: 8px;
                border: 1px solid #e1e5e9;
                margin: 12px 0;
            }

            .empty-state .material-symbols-outlined {
                font-size: 48px;
                color: #cbd5e1;
                margin-bottom: 12px;
            }

            .empty-state h3 {
                color: #4a5568;
                font-size: 16px;
                margin-bottom: 6px;
                font-weight: 600;
            }

            .empty-state p {
                color: #718096;
                margin: 0;
                font-size: 14px;
            }
        }

        /* Ensure no horizontal overflow */
        * {
            max-width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
       <div class="header">
         <div class="menu-icon" onclick="openSidebar()">
          <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="header-logo">
          <img
            class="logo"
            src="sona_logo.jpg"
            alt="Sona College Logo"
            height="60"
            width="200"
          />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div >
          <!-- empty -->
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
                <div class="student-regno">ID:                                                                                                                                                                                                                                                                                                                                                                                 <?php echo htmlspecialchars($teacher_data['employee_id']); ?>
                    <?php
                        if ($is_admin) {
                            echo '(Admin)';
                        } elseif ($is_counselor) {
                            echo '(Counselor)';
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
                        <a href="registered_students.php" class="nav-link active">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
                    <?php
                        // Check if user is counselor or admin
                        $user_sql  = "SELECT status FROM teacher_register WHERE username = ?";
                        $user_stmt = $conn->prepare($user_sql);
                        $user_stmt->bind_param("s", $username);
                        $user_stmt->execute();
                        $user_result = $user_stmt->get_result();
                        $user_status = 'teacher'; // default
                        if ($user_result->num_rows > 0) {
                            $user_data   = $user_result->fetch_assoc();
                            $user_status = $user_data['status'];
                        }
                        $is_counselor = ($user_status === 'counselor' || $user_status === 'admin');
                        $is_admin     = ($user_status === 'admin');
                        $user_stmt->close();
                    ?>
                    <?php if ($is_counselor): ?>
                    <li class="nav-item">
                        <a href="assigned_students.php" class="nav-link">
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


            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['unique_students']; ?></div>
                    <div class="stat-label">Unique Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['prize_winners']; ?></div>
                    <div class="stat-label">Prize Winners</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Regno, or Event">
                        </div>

                        <div class="filter-group">
                            <label for="event_type">Event Type</label>
                            <select id="event_type" name="event_type">
                                <option value="">All Event Types</option>
                                <?php while ($type = $event_types_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['event_type']); ?>"
                                            <?php echo $event_type_filter === $type['event_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['event_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="department">Department</label>
                            <select id="department" name="department">
                                <option value="">All Departments</option>
                                <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="prize">Prize Filter</label>
                            <select id="prize" name="prize">
                                <option value="">All</option>
                                <option value="winner"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $prize_filter === 'winner' ? 'selected' : ''; ?>>Prize Winners</option>
                                <option value="first"                                                                                                                                                                <?php echo $prize_filter === 'first' ? 'selected' : ''; ?>>First Prize</option>
                                <option value="second"                                                                                                                                                                      <?php echo $prize_filter === 'second' ? 'selected' : ''; ?>>Second Prize</option>
                                <option value="third"                                                                                                                                                                <?php echo $prize_filter === 'third' ? 'selected' : ''; ?>>Third Prize</option>
                                <option value="Participation"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo $prize_filter === 'Participation' ? 'selected' : ''; ?>>Participation</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="location">Location</label>
                            <select id="location" name="location">
                                <option value="">All Locations</option>
                                <option value="tamilnadu"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $location_filter === 'tamilnadu' ? 'selected' : ''; ?>>Tamil Nadu</option>
                                <option value="outside"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo $location_filter === 'outside' ? 'selected' : ''; ?>>Outside Tamil Nadu</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">search</span>
                                Filter
                            </button>
                            <a href="registered_students.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">clear</span>
                                Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="students-table">
                <div class="table-header">
                    <h3>📋 Student Registrations (<?php echo $total_records; ?> total)</h3>
                </div>

                <div class="table-responsive desktop-table">
                    <table class="excel-table">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Reg. No</th>
                                <th>Dept</th>
                                <th>Year</th>
                                <th>Event Name</th>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Organization</th>
                                <th>State</th>
                                <th>Prize</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (! empty($students_data)): ?>
                                <?php
                                    $sno = $offset + 1;
                                    foreach ($students_data as $student):
                                ?>
                                    <tr>
                                        <td><?php echo $sno++; ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['regno']); ?></td>
                                        <td><?php echo htmlspecialchars($student['department']); ?></td>
                                        <td><?php echo htmlspecialchars($student['current_year']) . ' - ' . htmlspecialchars($student['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($student['event_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['event_type']); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($student['start_date'])); ?></td>
                                        <td><?php echo date('d-m-Y', strtotime($student['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($student['no_of_days']); ?></td>
                                        <td><?php echo htmlspecialchars($student['college']); ?></td>
                                        <td><?php echo htmlspecialchars($student['position']); ?></td>
                                        <td>
                                            <?php
                                                $prize       = $student['prize'];
                                                $badge_class = '';
                                                switch (strtolower($prize)) {
                                                    case 'first':$badge_class = 'prize-first';
                                                        break;
                                                    case 'second':$badge_class = 'prize-second';
                                                        break;
                                                    case 'third':$badge_class = 'prize-third';
                                                        break;
                                                    default: $badge_class = 'prize-participation';
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($prize); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="13" style="text-align: center; padding: 40px;">
                                        <span class="material-symbols-outlined" style="font-size: 48px; color: #ccc;">group_off</span>
                                        <p style="color: #666; margin: 10px 0;">No students found matching your criteria</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Layout -->
                <div class="mobile-card-table">
                    <?php if (! empty($students_data)): ?>
                        <?php foreach ($students_data as $student): ?>
                            <div class="student-card">
                                <h4><?php echo htmlspecialchars($student['event_name']); ?></h4>
                                <p><strong>• Student Name:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo htmlspecialchars($student['name']); ?></p>
                                <p><strong>• Register No:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo htmlspecialchars($student['regno']); ?></p>
                                <p><strong>• Event Type:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo htmlspecialchars($student['event_type']); ?></p>
                                <p><strong>• Date:</strong>
                                    <?php
                                        if ($student['start_date'] === $student['end_date']) {
                                            echo date('M d, Y', strtotime($student['start_date'])) . ' (' . $student['no_of_days'] . ' day)';
                                        } else {
                                            echo date('M d', strtotime($student['start_date'])) . ' - ' . date('M d, Y', strtotime($student['end_date'])) . ' (' . $student['no_of_days'] . ' days)';
                                        }
                                    ?>
                                </p>
                                <p><strong>• Organization:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo htmlspecialchars($student['college']); ?></p>
                                <p><strong>• Department:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo htmlspecialchars($student['department']); ?></p>
                                <p><strong>• Year & Semester:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo htmlspecialchars($student['current_year']); ?> -<?php echo htmlspecialchars($student['semester']); ?></p>
                                <p><strong>• Location:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo htmlspecialchars($student['position']); ?></p>
                                <p><strong>• Prize:</strong>
                                    <?php
                                        $prize       = $student['prize'];
                                        $badge_class = '';
                                        $prize_text  = strtoupper(htmlspecialchars($prize));
                                        if (strtolower($prize) == 'third') {
                                            $prize_text .= ' - ₹1000';
                                        }
                                        switch (strtolower($prize)) {
                                            case 'first':$badge_class = ' prize-first';
                                                break;
                                            case 'second':$badge_class = ' prize-second';
                                                break;
                                            case 'third':$badge_class = ' prize-third';
                                                break;
                                            default: $badge_class = ' prize-participation';
                                        }
                                    ?>
                                    <span class="prize-badge<?php echo $badge_class; ?>">🏆<?php echo $prize_text; ?></span>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined">group_off</span>
                            <h3>No Students Found</h3>
                            <p>No students found matching your search criteria</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>&location=<?php echo urlencode($location_filter); ?>">« Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>&location=<?php echo urlencode($location_filter); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>&location=<?php echo urlencode($location_filter); ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Optimized mobile menu functionality
        const sidebar = document.getElementById('sidebar');
        const headerMenuIcon = document.querySelector('.header .menu-icon');
        const closeSidebarBtn = document.querySelector('.close-sidebar');

        function toggleSidebar() {
            const body = document.body;
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-open');
            } else {
                sidebar.classList.add('active');
                body.classList.add('sidebar-open');
            }
        }

        // Use event delegation for better performance
        document.addEventListener('click', function(event) {
            if (headerMenuIcon && headerMenuIcon.contains(event.target)) {
                toggleSidebar();
            } else if (closeSidebarBtn && closeSidebarBtn.contains(event.target)) {
                toggleSidebar();
            } else if (window.innerWidth <= 768 &&
                       sidebar &&
                       sidebar.classList.contains('active') &&
                       !sidebar.contains(event.target)) {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>