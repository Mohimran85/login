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

    // Get teacher data and check if they are a counselor
    $username     = $_SESSION['username'];
    $teacher_data = null;
    $is_counselor = false;

    $sql  = "SELECT * FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $is_counselor = ($teacher_data['status'] === 'counselor' || $teacher_data['status'] === 'admin');
        $teacher_name = $teacher_data['name'] ?? 'Teacher';
        $teacher_dept = $teacher_data['department'] ?? 'N/A';
    } else {
        header("Location: ../index.php");
        exit();
    }

    if (! $is_counselor) {
        $_SESSION['access_denied'] = 'Only counselors can access internship approvals. Your role is: ' . ucfirst($teacher_data['status']);
        header("Location: index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    // Handle internship approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_internship_status'])) {
        $internship_id     = intval($_POST['internship_id']);
        $new_status        = $_POST['new_status'];
        $counselor_remarks = trim($_POST['counselor_remarks']);

        // Validate status
        if (! in_array($new_status, ['pending', 'approved', 'rejected'])) {
            $message      = "Invalid status provided.";
            $message_type = 'error';
        } else {
            $update_sql  = "UPDATE internship_submissions SET approval_status = ?, counselor_remarks = ?, approved_by = ?, approval_date = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssii", $new_status, $counselor_remarks, $teacher_data['id'], $internship_id);

            if ($update_stmt->execute()) {
                $message      = "Internship " . ucfirst($new_status) . " successfully!";
                $message_type = 'success';
            } else {
                $message      = "Error updating internship: " . $conn->error;
                $message_type = 'error';
            }
            $update_stmt->close();
        }
    }

    // Get assigned students for this counselor
    $assigned_students_sql = "SELECT student_regno FROM counselor_assignments
                             WHERE counselor_id = ? AND status = 'active'";
    $assigned_students_stmt = $conn->prepare($assigned_students_sql);
    $assigned_students_stmt->bind_param("i", $teacher_data['id']);
    $assigned_students_stmt->execute();
    $assigned_students_result = $assigned_students_stmt->get_result();

    $student_regnos = [];
    while ($row = $assigned_students_result->fetch_assoc()) {
        $student_regnos[] = $row['student_regno'];
    }
    $assigned_students_stmt->close();

    // Get internship submissions for assigned students
    if (! empty($student_regnos)) {
        $placeholders   = implode(',', array_fill(0, count($student_regnos), '?'));
        $internship_sql = "SELECT i.*, sr.name as student_name, sr.department, sr.year_of_join
                          FROM internship_submissions i
                          JOIN student_register sr ON i.regno = sr.regno
                          WHERE i.regno IN ($placeholders)
                          ORDER BY i.submission_date DESC";
        $internship_stmt = $conn->prepare($internship_sql);
        $internship_stmt->bind_param(str_repeat('s', count($student_regnos)), ...$student_regnos);
        $internship_stmt->execute();
        $internship_result = $internship_stmt->get_result();

        // Fetch all results into an array for reuse
        $internship_array = [];
        while ($row = $internship_result->fetch_assoc()) {
            $internship_array[] = $row;
        }
        $internship_stmt->close();
    } else {
        $internship_array = [];
    }

    // Get statistics
    if (! empty($student_regnos)) {
        $stats_sql = "SELECT
                        COUNT(*) as total_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions
                      FROM internship_submissions
                      WHERE regno IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('s', count($student_regnos)), ...$student_regnos);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats        = $stats_result->fetch_assoc();
        $stats_stmt->close();
    } else {
        $stats = [
            'total_submissions'    => 0,
            'pending_submissions'  => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
        ];
    }

    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Internship Approvals - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0c3878;
            --secondary-color: #1e4276;
            --accent-color: #2d5aa0;
        }

        * {
            box-sizing: border-box;
            max-width: 100%;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        .grid-container {
            display: grid;
            grid-template-areas: "sidebar main";
            grid-template-columns: 280px 1fr;
            grid-template-rows: 1fr;
            min-height: 100vh;
            padding-top: 80px;
            transition: all 0.3s ease;
        }

        /* Header Styling */
        .header {
            grid-area: header;
            background-color: #fff;
            height: 80px;
            display: flex;
            font-size: 15px;
            font-weight: 100;
            align-items: center;
            justify-content: space-between;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px, rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;
            color: #1e4276;
            position: fixed;
            width: 100%;
            z-index: 1001;
            top: 0;
            left: 0;
        }

        .header .menu-icon {
            display: none;
            cursor: pointer;
        }

        .header .menu-icon .material-symbols-outlined {
            font-size: 28px;
            color: var(--primary-color);
        }

        .header .icon img {
            height: 60px;
            object-fit: contain;
        }

        .header-title {
            flex: 1;
            text-align: center;
        }

        .header-title p {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        /* Sidebar Styling */
        .sidebar {
            grid-area: sidebar;
            background: #ffffff;
            border-right: 1px solid #e9ecef;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            width: 280px;
            top: 80px;
            left: 0;
            height: calc(100vh - 80px);
            overflow-y: auto;
            transform: translateX(0);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff !important;
        }

        .sidebar-title {
            font-size: 20px;
            font-weight: 600;
            color: #1e4276;
            flex: 1;
        }

        .close-sidebar {
            display: none;
            cursor: pointer;
            color: #0c3878;
            font-size: 24px;
        }

        .student-info {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px;
            margin: 0 20px 25px;
            border-radius: 15px;
            text-align: center;
        }

        .student-info .student-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 8px;
            color: white;
        }

        .student-info .student-regno {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        .nav-menu {
            list-style: none;
            padding: 10px 0;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 8px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            color: #495057;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
            margin: 0 20px;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transform: translateX(5px);
        }

        .nav-link span {
            font-size: 20px;
            color: inherit;
        }

        .main {
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--primary-color);
            font-size: 32px;
            margin: 0 0 10px 0;
        }

        .page-header p {
            color: #6c757d;
            margin: 0;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }

            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "header"
                    "main";
                padding-top: 70px;
            }

            .header {
                padding: 0 15px;
                height: 70px;
            }

            .header .menu-icon {
                display: block;
            }

            .header .icon img {
                height: 50px;
            }

            .header-title p {
                font-size: 18px;
            }

            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 300px;
                height: 100vh;
                z-index: 10000;
                transition: left 0.3s ease;
            }

            .sidebar.active {
                left: 0;
            }

            .close-sidebar {
                display: block;
                position: absolute !important;
                top: 15px !important;
                right: 15px !important;
            }

            .sidebar-header {
                padding: 60px 20px 20px 20px !important;
            }

            .main {
                padding: 20px 15px;
            }

            body.sidebar-open {
                overflow: hidden;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 15px 10px;
            }
        }
        /* Prevent mobile zoom and overflow */
        * {
            box-sizing: border-box;
            max-width: 100%;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        /* Override default margins and paddings for wider content */
        .main {
            padding: 15px !important;
            margin: 0 !important;
        }

        .grid-container {
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Sidebar width optimization */
        .sidebar {
            width: 250px !important;
            min-width: 250px !important;
        }

        /* Statistics grid full width */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 0 5px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-width: 150px;
        }

        .stat-card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #0c3878;
        }

        /* Alert styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        /* Table styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #0c3878;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 20px;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            margin: 0;
            color: #0c3878;
            font-size: 22px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #0c3878;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background-color: #082553;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .certificate-link {
            color: #0c3878;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px dashed #0c3878;
        }

        .certificate-link:hover {
            text-decoration: underline;
        }

        /* Mobile Card View */
        .mobile-card-view {
            display: none;
        }

        @media (max-width: 768px) {
            .table-container table {
                display: none;
            }

            .mobile-card-view {
                display: block;
            }

            .internship-card {
                background: white;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                border-left: 4px solid var(--primary-color);
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 1px solid #e9ecef;
            }

            .card-student-name {
                font-weight: 600;
                color: var(--primary-color);
                font-size: 15px;
            }

            .card-row {
                display: flex;
                margin-bottom: 8px;
                font-size: 13px;
            }

            .card-label {
                font-weight: 600;
                color: #666;
                min-width: 100px;
                flex-shrink: 0;
            }

            .card-value {
                color: #333;
                word-break: break-word;
            }

            .card-actions {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid #e9ecef;
            }

            .modal-content {
                padding: 20px;
                margin: 10px;
            }

            .action-btns {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .internship-card {
                padding: 12px;
            }

            .card-student-name {
                font-size: 14px;
            }

            .card-row {
                font-size: 12px;
            }

            .card-label {
                min-width: 85px;
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon" onclick="toggleSidebar()">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="../asserts/images/sona_logo.jpg" alt="Logo">
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Teacher Portal</div>
                <div class="close-sidebar" onclick="toggleSidebar()">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_name); ?></div>
                <div class="student-regno">Teacher |                                                                                                                                                                                                                                                                     <?php echo htmlspecialchars($teacher_dept); ?></div>
            </div>

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
                <?php if ($is_counselor): ?>
                <li class="nav-item">
                    <a href="index.php#assigned-students" class="nav-link">
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
                    <a href="internship_approvals.php" class="nav-link active">
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
                <?php endif; ?>
                <?php if ($teacher_data['status'] === 'admin'): ?>
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
                    <a href="digital_signature.php" class="nav-link">
                        <span class="material-symbols-outlined">draw</span>
                        Digital Signature
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
        </div>

        <!-- Main Content -->
        <main class="main">
            <div class="page-header">
                <h1>Internship Approvals</h1>
                <p>Review and approve internship submissions from assigned students</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Submissions</h3>
                    <div class="number"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="number" style="color: #ff9800;"><?php echo $stats['pending_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Approved</h3>
                    <div class="number" style="color: #28a745;"><?php echo $stats['approved_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rejected</h3>
                    <div class="number" style="color: #dc3545;"><?php echo $stats['rejected_submissions'] ?? 0; ?></div>
                </div>
            </div>

            <!-- Internship Submissions Table -->
            <div class="table-container">
                <?php if (empty($internship_array)): ?>
                    <div style="padding: 40px; text-align: center; color: #666;">
                        <p>No internship submissions found for your assigned students.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Reg No</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Domain</th>
                                <th>Duration</th>
                                <th>Certificate</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internship_array as $internship): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($internship['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['regno']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['role_title']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['domain']); ?></td>
                                    <td>
                                        <?php
                                            $start = date('M d, Y', strtotime($internship['start_date']));
                                            $end   = date('M d, Y', strtotime($internship['end_date']));
                                            echo "$start - $end";
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($internship['internship_certificate']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($internship['internship_certificate']); ?>"
                                               target="_blank" class="certificate-link">View</a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $status = $internship['approval_status'] ?? 'pending'; ?>
                                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-sm btn-primary"
                                                    onclick="openModal(<?php echo $internship['id']; ?>, '<?php echo htmlspecialchars($internship['student_name']); ?>')">
                                                Review
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Mobile Card View -->
                    <div class="mobile-card-view">
                        <?php foreach ($internship_array as $internship): ?>
                            <div class="internship-card">
                                <div class="card-header">
                                    <span class="card-student-name"><?php echo htmlspecialchars($internship['student_name']); ?></span>
                                    <?php $status = $internship['approval_status'] ?? 'pending'; ?>
                                    <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Reg No:</span>
                                    <span class="card-value"><?php echo htmlspecialchars($internship['regno']); ?></span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Company:</span>
                                    <span class="card-value"><?php echo htmlspecialchars($internship['company_name']); ?></span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Role:</span>
                                    <span class="card-value"><?php echo htmlspecialchars($internship['role_title']); ?></span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Domain:</span>
                                    <span class="card-value"><?php echo htmlspecialchars($internship['domain']); ?></span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Duration:</span>
                                    <span class="card-value">
                                        <?php
                                            $start = date('M d, Y', strtotime($internship['start_date']));
                                            $end   = date('M d, Y', strtotime($internship['end_date']));
                                            echo "$start - $end";
                                        ?>
                                    </span>
                                </div>

                                <div class="card-row">
                                    <span class="card-label">Certificate:</span>
                                    <span class="card-value">
                                        <?php if ($internship['internship_certificate']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($internship['internship_certificate']); ?>"
                                               target="_blank" class="certificate-link">View Certificate</a>
                                        <?php else: ?>
                                            <span style="color: #999;">Not Available</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="card-actions">
                                    <button class="btn btn-sm btn-primary"
                                            onclick="openModal(<?php echo $internship['id']; ?>, '<?php echo htmlspecialchars($internship['student_name']); ?>')">
                                        Review Submission
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Review Internship Submission</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" id="internshipId" name="internship_id" value="">

                <div class="form-group">
                    <label for="statusSelect">Approval Status:</label>
                    <select id="statusSelect" name="new_status" required>
                        <option value="">-- Select Status --</option>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="remarksInput">Counselor Remarks (Optional):</label>
                    <textarea id="remarksInput" name="counselor_remarks" placeholder="Add any remarks or feedback..."></textarea>
                </div>

                <input type="hidden" name="update_internship_status" value="1">

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Decision</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const body = document.body;

            sidebar.classList.toggle('active');
            body.classList.toggle('sidebar-open');
        }

        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            const sidebar = document.querySelector('.sidebar');
            const menuIcon = document.querySelector('.menu-icon');

            if (sidebar.classList.contains('active') &&
                !sidebar.contains(e.target) &&
                !menuIcon.contains(e.target)) {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            }
        });

        function openModal(internshipId, studentName) {
            document.getElementById('internshipId').value = internshipId;
            document.querySelector('.modal-header h2').textContent = `Review Internship: ${studentName}`;
            document.getElementById('reviewModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('reviewModal').classList.remove('show');
            document.getElementById('statusSelect').value = '';
            document.getElementById('remarksInput').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
