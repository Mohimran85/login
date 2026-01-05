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

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

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

    // Handle bulk semester update for all assigned students
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bulk_semester'])) {
        $bulk_semester = $_POST['bulk_semester'];

        if (! empty($bulk_semester)) {
            // Get all students assigned to this counselor
            $get_students_sql = "SELECT student_regno FROM counselor_assignments
                                WHERE counselor_id = ? AND status = 'active'";
            $get_students_stmt = $conn->prepare($get_students_sql);
            $get_students_stmt->bind_param("i", $counselor_id);
            $get_students_stmt->execute();
            $students_result = $get_students_stmt->get_result();

            $updated_count = 0;
            while ($student = $students_result->fetch_assoc()) {
                $update_sql  = "UPDATE student_register SET semester = ? WHERE regno = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $bulk_semester, $student['student_regno']);

                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $updated_count++;
                }
                $update_stmt->close();
            }
            $get_students_stmt->close();

            $_SESSION['success_message'] = "Successfully updated semester to $bulk_semester for $updated_count student(s)!";
        } else {
            $_SESSION['error_message'] = "Please select a semester.";
        }

        // Redirect to avoid form resubmission
        header("Location: assigned_students.php");
        exit();
    }

    // Handle individual semester updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_semesters'])) {
        $semesters     = $_POST['semester'] ?? [];
        $updated_count = 0;
        $failed_count  = 0;

        foreach ($semesters as $regno => $new_semester) {
            if (! empty($new_semester)) {
                $update_sql  = "UPDATE student_register SET semester = ? WHERE regno = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $new_semester, $regno);

                if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                    $updated_count++;
                } else {
                    $failed_count++;
                }
                $update_stmt->close();
            }
        }

        if ($updated_count > 0) {
            $_SESSION['success_message'] = "Successfully updated semester for $updated_count student(s)!";
        }
        if ($failed_count > 0) {
            $_SESSION['error_message'] = "Failed to update $failed_count student(s).";
        }

        // Redirect to avoid form resubmission
        header("Location: assigned_students.php");
        exit();
    }

    // Pagination settings
    $records_per_page = 15;
    $page             = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset           = ($page - 1) * $records_per_page;

    // Search and filter parameters
    $search          = isset($_GET['search']) ? trim($_GET['search']) : '';
    $semester_filter = isset($_GET['semester']) ? $_GET['semester'] : '';

    // Build WHERE clause
    $where_conditions = ["ca.counselor_id = ?", "ca.status = 'active'"];
    $params           = [$counselor_id];
    $types            = 'i';

    if (! empty($search)) {
        $where_conditions[] = "(sr.name LIKE ? OR sr.regno LIKE ? OR sr.personal_email LIKE ?)";
        $search_param       = "%$search%";
        $params[]           = $search_param;
        $params[]           = $search_param;
        $params[]           = $search_param;
        $types .= 'sss';
    }

    if (! empty($semester_filter)) {
        $where_conditions[] = "sr.semester = ?";
        $params[]           = $semester_filter;
        $types .= 's';
    }

    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    // Get total records for pagination
    $count_sql = "SELECT COUNT(*) as total
                  FROM counselor_assignments ca
                  JOIN student_register sr ON ca.student_regno = sr.regno
                  $where_clause";

    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $total_pages = ceil($total_records / $records_per_page);

    // Get assigned students with pagination
    $students_sql = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join, sr.semester,
                           sr.personal_email as email, sr.dob, ca.assigned_date,
                           COUNT(DISTINCT ser.id) as total_events,
                           SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prizes_won
                    FROM counselor_assignments ca
                    JOIN student_register sr ON ca.student_regno = sr.regno
                    LEFT JOIN student_event_register ser ON sr.regno = ser.regno
                    $where_clause
                    GROUP BY sr.regno, sr.name, sr.department, sr.year_of_join, sr.semester,
                             sr.personal_email, sr.dob, ca.assigned_date
                    ORDER BY sr.name ASC
                    LIMIT ? OFFSET ?";

    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= 'ii';

    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param($types, ...$params);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();

    // Store all results in an array
    $students_data = [];
    while ($row = $students_result->fetch_assoc()) {
        $students_data[] = $row;
    }

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assigned Students - Counselor Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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

        /* Semester Update Styles */
        .semester-update {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .semester-select {
            padding: 6px 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .semester-select:focus {
            outline: none;
            border-color: var(--primary-color);
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
            .stats-container {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: scroll;
            }

            table {
                min-width: 800px;
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
                background: white;
            }

            .students-table {
                background: white !important;
            }

            .student-card {
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }

            .student-card-header {
                display: flex;
                justify-content: space-between;
                align-items: start;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #e9ecef;
            }

            .student-name {
                font-size: 16px;
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 5px;
            }

            .student-regno {
                font-size: 13px;
                color: #6c757d;
            }

            .student-info {
                /* Keep the gradient background from parent styles */
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                font-size: 13px;
                background: white;
            }

            .info-label {
                color: #6c757d;
                font-weight: 500;
            }

            .info-value {
                color: #495057;
                font-weight: 500;
                text-align: right;
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
                <div class="student-regno">ID:                                                                                                                                                                                                                                       <?php echo htmlspecialchars($teacher_data['employee_id']); ?> (Counselor)</div>
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
                            Internship Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="verify_events.php" class="nav-link">
                            <span class="material-symbols-outlined">card_giftcard</span>
                            Event Certificate Validation
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
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $stats['total_events'] ?? 0; ?></div>
                    <div class="stat-label">Total Event Participations</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $stats['prize_winners'] ?? 0; ?></div>
                    <div class="stat-label">Prizes Won</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="section-header" style="justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="material-symbols-outlined">filter_alt</span>
                        <h2>Filter Students</h2>
                    </div>
                    <form method="POST" style="display: flex; gap: 10px; align-items: center;" onsubmit="return confirmBulkUpdate()">
                        <select id="bulkSemester" name="bulk_semester" class="filter-select" style="min-width: 150px;" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>">Semester<?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" name="update_bulk_semester" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Update All
                        </button>
                    </form>
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
                                        Semester                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
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
                    <form method="POST" id="semesterUpdateForm" onsubmit="return confirm('Are you sure you want to update all modified semesters?');">
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
                                            <select name="semester[<?php echo htmlspecialchars($student['regno']); ?>]" class="semester-select">
                                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                                    <option value="<?php echo $s; ?>"<?php echo($student['semester'] ?? '') == $s ? 'selected' : ''; ?>>
                                                        Semester                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $s; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['year_of_join']); ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo $student['total_events'] ?? 0; ?> Events
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($student['prizes_won'] ?? 0) > 0): ?>
                                                <span class="badge badge-success">
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
                                <div class="student-info">
                                    <div class="info-row">
                                        <span class="info-label">Semester:</span>
                                        <span class="info-value">
                                            <select name="semester[<?php echo htmlspecialchars($student['regno']); ?>]" class="semester-select" style="font-size: 12px; padding: 4px 8px;">
                                                <?php for ($s = 1; $s <= 8; $s++): ?>
                                                    <option value="<?php echo $s; ?>"<?php echo($student['semester'] ?? '') == $s ? 'selected' : ''; ?>>
                                                        <?php echo $s; ?>
                                                    </option>
                                                <?php endfor; ?>
                                            </select>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Year of Join:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($student['year_of_join']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Events:</span>
                                        <span class="info-value">
                                            <span class="badge badge-info"><?php echo $student['total_events'] ?? 0; ?></span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Prizes:</span>
                                        <span class="info-value">
                                            <?php if (($student['prizes_won'] ?? 0) > 0): ?>
                                                <span class="badge badge-success"><?php echo $student['prizes_won']; ?></span>
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

                    <div style="margin-top: 20px; text-align: center;">
                        <button type="submit" name="update_all_semesters" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Update Semesters
                        </button>
                    </div>
                    </form>

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

        // Confirm bulk semester update
        function confirmBulkUpdate() {
            const selectedSemester = document.getElementById('bulkSemester').value;
            return confirm('Are you sure you want to update ALL assigned students to Semester ' + selectedSemester + '?');
        }
    </script>
</body>
</html>

<?php
    $conn->close();
    if (ob_get_level()) {
        ob_end_flush();
    }
?>
