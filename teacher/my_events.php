<?php
    session_start();

    // Check if user is logged in as a teacher
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;

    // Try to get teacher data from teacher_register table first
    $sql  = "SELECT name, faculty_id as employee_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        // Fallback: Check if username exists in student_register table
        $sql2  = "SELECT name, regno as employee_id FROM student_register WHERE username=?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows > 0) {
            $teacher_data = $result2->fetch_assoc();
        } else {
            // If no data found anywhere, create a default entry
            $teacher_data = [
                'name'        => ucfirst($username),                            // Use username as name
                'employee_id' => 'TEMP-' . strtoupper(substr($username, 0, 4)), // Generate temp ID
            ];
        }
        $stmt2->close();
    }

    // Handle search and filters
    $search            = isset($_GET['search']) ? trim($_GET['search']) : '';
    $event_type_filter = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $year_filter       = isset($_GET['year']) ? $_GET['year'] : '';
    $sort_by           = isset($_GET['sort']) ? $_GET['sort'] : 'event_date';
    $sort_order        = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    // Build query with filters
    $where_conditions = ["(staff_id = ? OR name = ?)"];
    $params           = [$teacher_data['employee_id'], $teacher_data['name']];
    $param_types      = "ss";

    if (! empty($search)) {
        $where_conditions[] = "(topic LIKE ? OR organisation LIKE ?)";
        $params[]           = "%$search%";
        $params[]           = "%$search%";
        $param_types .= "ss";
    }

    if (! empty($event_type_filter)) {
        $where_conditions[] = "event_type = ?";
        $params[]           = $event_type_filter;
        $param_types .= "s";
    }

    if (! empty($year_filter)) {
        $where_conditions[] = "academic_year = ?";
        $params[]           = $year_filter;
        $param_types .= "s";
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Validate sort columns
    $allowed_sorts = ['event_date', 'topic', 'event_type', 'organisation'];
    if (! in_array($sort_by, $allowed_sorts)) {
        $sort_by = 'event_date';
    }
    $sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

    // Get teacher's registered events
    $events_sql  = "SELECT * FROM staff_event_reg WHERE $where_clause ORDER BY $sort_by $sort_order";
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param($param_types, ...$params);
    $events_stmt->execute();
    $events_result = $events_stmt->get_result();

    // Get statistics
    $stats_sql = "SELECT
        COUNT(*) as total_events,
        COUNT(DISTINCT event_type) as event_types,
        COUNT(DISTINCT academic_year) as academic_years,
        SUM(no_of_dates) as total_days
        FROM staff_event_reg WHERE staff_id = ? OR name = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("ss", $teacher_data['employee_id'], $teacher_data['name']);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();

    // Get unique event types for filter
    $types_sql  = "SELECT DISTINCT event_type FROM staff_event_reg WHERE staff_id = ? OR name = ? ORDER BY event_type";
    $types_stmt = $conn->prepare($types_sql);
    $types_stmt->bind_param("ss", $teacher_data['employee_id'], $teacher_data['name']);
    $types_stmt->execute();
    $event_types = $types_stmt->get_result();

    // Get unique academic years for filter
    $years_sql  = "SELECT DISTINCT academic_year FROM staff_event_reg WHERE staff_id = ? OR name = ? ORDER BY academic_year DESC";
    $years_stmt = $conn->prepare($years_sql);
    $years_stmt->bind_param("ss", $teacher_data['employee_id'], $teacher_data['name']);
    $years_stmt->execute();
    $academic_years = $years_stmt->get_result();

    $stmt->close();
    $events_stmt->close();
    $stats_stmt->close();
    $types_stmt->close();
    $years_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Professional Development Events - Teacher Portal</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        /* Student Form Style - Modern Glassmorphism Design */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;

            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            grid-template-rows: 60px 1fr;
            grid-template-areas:
                "sidebar header"
                "sidebar main";
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        .header {
            grid-area: header;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar {
            grid-area: sidebar;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }

        .main {
            grid-area: main;
            overflow-x: hidden;
            padding: 30px;
            background: transparent;
        }

        :root {
            --primary-color: #2d5aa0;
            --secondary-color: #1e3a6f;
        }

        /* Modern glassmorphism containers */
        .participations-header,
        .filters-section,
        .participations-list {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .participations-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
        }

        .participations-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .participations-header > * {
            position: relative;
            z-index: 2;
        }

        .participations-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .participations-subtitle {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 25px;
            font-weight: 300;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }

        .filters-section {
            padding: 30px;
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .filter-input, .filter-select {
            padding: 12px 18px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            background: white;
            transition: all 0.3s ease;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(45, 90, 160, 0.1);
            transform: translateY(-2px);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(45, 90, 160, 0.3);
        }

        .filter-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(45, 90, 160, 0.4);
        }

        .participations-list {
            padding: 0;
            margin-bottom: 30px;
        }

        .participation-item {
            padding: 30px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            background: transparent;
        }

        .participation-item:hover {
            background: rgba(45, 90, 160, 0.05);
            transform: translateX(5px);
        }

        .participation-item:last-child {
            border-bottom: none;
        }

        .event-name {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #495057;
            background: rgba(45, 90, 160, 0.1);
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .meta-item .material-symbols-outlined {
            font-size: 18px;
            color: var(--primary-color);
        }

        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .detail-group {
            padding: 20px;
            background: rgba(45, 90, 160, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(45, 90, 160, 0.1);
            transition: all 0.3s ease;
        }

        .detail-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .detail-label {
            font-size: 12px;
            color: var(--primary-color);
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: #2c3e50;
            font-weight: 600;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
        }

        .actions-section {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-download {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(40, 167, 69, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            margin-bottom: 25px;
            opacity: 0.3;
            color: var(--primary-color);
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-weight: 700;
        }

        .empty-state p {
            margin-bottom: 25px;
            font-size: 16px;
            opacity: 0.8;
        }

        .empty-action {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(45, 90, 160, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .empty-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(45, 90, 160, 0.4);
        }        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }

            .grid-container {
                grid-template-columns: 1fr;
                grid-template-rows: 60px 1fr;
                grid-template-areas:
                    "header"
                    "main";
                min-height: 100vh;
                width: 100%;
                max-width: 100vw;
            }

            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 999;
                width: 100%;
            }

            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 280px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .main {
                width: 100% !important;
                max-width: 100vw;
                padding: 80px 10px 20px 10px; /* Top padding for fixed header */
                margin: 0 !important;
                grid-area: main;
                box-sizing: border-box;
                overflow-x: hidden;
            }

            .participations-header {
                padding: 20px 15px;
                margin-bottom: 20px;
                width: calc(100% - 20px);
                max-width: 100%;
                box-sizing: border-box;
            }

            .participations-title {
                font-size: 22px;
                margin-bottom: 8px;
                word-wrap: break-word;
            }

            .participations-subtitle {
                font-size: 14px;
                margin-bottom: 15px;
                word-wrap: break-word;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-top: 15px;
                width: 100%;
            }

            .stat-card {
                padding: 12px;
                min-width: 0;
            }

            .stat-number {
                font-size: 20px;
            }

            .stat-label {
                font-size: 11px;
            }

            .filters-section {
                padding: 15px;
                margin-bottom: 20px;
                width: calc(100% - 20px);
                max-width: 100%;
                box-sizing: border-box;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                width: 100%;
            }

            .filter-input, .filter-select {
                width: 100%;
                box-sizing: border-box;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .filter-btn {
                width: 100%;
                padding: 15px;
                font-size: 16px;
                margin-top: 10px;
            }

            .participations-list {
                margin: 0;
                width: calc(100% - 20px);
                max-width: 100%;
                box-sizing: border-box;
            }

            .participation-item {
                padding: 15px;
                margin-bottom: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .event-name {
                font-size: 16px;
                margin-bottom: 10px;
                line-height: 1.4;
                word-wrap: break-word;
            }

            .event-meta {
                flex-direction: column;
                gap: 8px;
                margin-bottom: 15px;
                width: 100%;
            }

            .meta-item {
                font-size: 13px;
                padding: 8px 12px;
                width: fit-content;
                min-width: 0;
            }

            .event-details {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-bottom: 15px;
                width: 100%;
            }

            .detail-group {
                padding: 12px;
                width: 100%;
                box-sizing: border-box;
            }

            .detail-label {
                font-size: 11px;
            }

            .detail-value {
                font-size: 13px;
            }

            .actions-section {
                flex-direction: column;
                gap: 8px;
            }

            .action-btn {
                text-align: center;
                justify-content: center;
                display: flex;
                align-items: center;
                padding: 12px 15px;
                font-size: 14px;
            }

            .empty-state {
                padding: 40px 20px;
            }

            .empty-state .material-symbols-outlined {
                font-size: 48px;
                margin-bottom: 15px;
            }

            .empty-state h3 {
                font-size: 18px;
                margin-bottom: 8px;
            }

            .empty-state p {
                font-size: 14px;
                margin-bottom: 15px;
            }

            /* Status badge responsive */
            .status-badge {
                font-size: 11px;
                padding: 4px 8px;
            }

            /* Ensure text doesn't overflow */
            .detail-value,
            .event-name,
            .meta-item {
                word-break: break-word;
                overflow-wrap: break-word;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 70px 5px 15px 5px; /* Reduced padding for very small screens */
            }

            .participations-header {
                padding: 15px 10px;
                width: calc(100% - 10px);
            }

            .participations-title {
                font-size: 20px;
                line-height: 1.2;
            }

            .participations-subtitle {
                font-size: 13px;
                line-height: 1.3;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .filters-section {
                padding: 12px;
                width: calc(100% - 10px);
            }

            .participations-list {
                width: calc(100% - 10px);
            }

            .participation-item {
                padding: 12px;
            }

            .event-name {
                font-size: 15px;
            }

            .meta-item {
                font-size: 12px;
                padding: 6px 10px;
            }

            .detail-group {
                padding: 10px;
            }

            /* Ensure no horizontal overflow */
            * {
                max-width: 100%;
                box-sizing: border-box;
            }

            /* Fix any potential overflow issues */
            .filter-input,
            .filter-select,
            .filter-btn {
                width: 100% !important;
                box-sizing: border-box !important;
            }
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
                <img src="../asserts/images/Sona Logo.png" alt="Sona College Logo">
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Teacher Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

        <div class="student-info">
            <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
            <div class="student-regno">ID:                                           <?php echo htmlspecialchars($teacher_data['employee_id']); ?></div>
        </div>            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <span class="material-symbols-outlined">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="staff_event_reg.php" class="nav-link">
                            <span class="material-symbols-outlined">event_note</span>
                            Event Registration
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_events.php" class="nav-link active">
                            <span class="material-symbols-outlined">calendar_month</span>
                            My Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
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
            <!-- Header Section -->
            <div class="participations-header">
                <div class="participations-title">My Professional Development Events</div>
                <div class="participations-subtitle">Track and manage all your completed professional development activities and certifications</div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_events']; ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['event_types']; ?></div>
                        <div class="stat-label">Event Types</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['academic_years']; ?></div>
                        <div class="stat-label">Academic Years</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_days'] ?? 0; ?></div>
                        <div class="stat-label">Total Days</div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search Events</label>
                            <input type="text" name="search" class="filter-input"
                                   placeholder="Search by event name or organization..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Event Type</label>
                            <select name="event_type" class="filter-select">
                                <option value="">All Types</option>
                                <?php while ($type = $event_types->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['event_type']); ?>"
                                            <?php echo($event_type_filter === $type['event_type']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['event_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Academic Year</label>
                            <select name="year" class="filter-select">
                                <option value="">All Years</option>
                                <?php while ($year = $academic_years->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($year['academic_year']); ?>"
                                            <?php echo($year_filter === $year['academic_year']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($year['academic_year']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort" class="filter-select">
                                <option value="event_date"                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($sort_by === 'event_date') ? 'selected' : ''; ?>>Date</option>
                                <option value="topic"                                                                                                                                                                                                                                                                                                                                                                                    <?php echo($sort_by === 'topic') ? 'selected' : ''; ?>>Event Name</option>
                                <option value="event_type"                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($sort_by === 'event_type') ? 'selected' : ''; ?>>Event Type</option>
                                <option value="organisation"                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($sort_by === 'organisation') ? 'selected' : ''; ?>>Organization</option>
                            </select>
                        </div>

                        <button type="submit" class="filter-btn">
                            <span class="material-symbols-outlined">search</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Events List -->
            <div class="participations-list">
                <?php if ($events_result->num_rows > 0): ?>
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <div class="participation-item">
                            <div class="event-name"><?php echo htmlspecialchars($event['topic']); ?></div>

                            <div class="event-meta">
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">category</span>
                                    <?php echo htmlspecialchars($event['event_type']); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">schedule</span>
                                    <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">business</span>
                                    <?php echo htmlspecialchars($event['organisation']); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">handshake</span>
                                    <?php echo htmlspecialchars($event['sponsors']); ?>
                                </div>
                                <div class="status-badge">
                                    ✅ Completed
                                </div>
                            </div>

                            <div class="event-details">
                                <div class="detail-group">
                                    <div class="detail-label">Department</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['department']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Academic Year</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['academic_year']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Duration</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($event['no_of_dates']) . ' Day' . ($event['no_of_dates'] > 1 ? 's' : ''); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Period</div>
                                    <div class="detail-value">
                                        <?php echo date('M d', strtotime($event['from_date'])) . ' - ' . date('M d, Y', strtotime($event['to_date'])); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="actions-section">
                                <?php if (! empty($event['certificate_path'])): ?>
                                    <a href="../../uploads/<?php echo htmlspecialchars($event['certificate_path']); ?>"
                                       class="action-btn btn-download" target="_blank">
                                        <span class="material-symbols-outlined">download</span>
                                        Certificate
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">event_busy</span>
                        <h3>No Professional Development Events Found</h3>
                        <p>You haven't added any completed events yet or no events match your search criteria.</p>
                        <a href="staff_event_reg.php" class="empty-action">Add Your First Event</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const body = document.body;

            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-open');
            } else {
                sidebar.classList.add('active');
                body.classList.add('sidebar-open');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            const closeSidebarBtn = document.querySelector('.close-sidebar');
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !headerMenuIcon.contains(event.target)) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>