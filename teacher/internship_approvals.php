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
    $is_admin     = false;

    $sql  = "SELECT *, faculty_id as employee_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $is_admin     = ($teacher_data['status'] === 'admin');
        $is_counselor = ($teacher_data['status'] === 'counselor' || $is_admin);
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

    // Get filter parameters
    $search_filter     = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status_filter     = isset($_GET['status']) ? $_GET['status'] : '';
    $department_filter = isset($_GET['department']) ? $_GET['department'] : '';

    // Get internship submissions for assigned students
    if (! empty($student_regnos)) {
        $placeholders = implode(',', array_fill(0, count($student_regnos), '?'));

        // Build WHERE clause with filters
        $where_conditions = ["i.regno IN ($placeholders)"];
        $params           = $student_regnos;
        $types            = str_repeat('s', count($student_regnos));

        if (! empty($search_filter)) {
            $where_conditions[] = "(sr.name LIKE ? OR i.regno LIKE ? OR i.company_name LIKE ? OR i.role_title LIKE ?)";
            $search_param       = "%$search_filter%";
            $params[]           = $search_param;
            $params[]           = $search_param;
            $params[]           = $search_param;
            $params[]           = $search_param;
            $types .= 'ssss';
        }

        if (! empty($status_filter)) {
            $where_conditions[] = "i.approval_status = ?";
            $params[]           = $status_filter;
            $types .= 's';
        }

        if (! empty($department_filter)) {
            $where_conditions[] = "sr.department = ?";
            $params[]           = $department_filter;
            $types .= 's';
        }

        $where_clause = implode(' AND ', $where_conditions);

        $internship_sql = "SELECT i.*, sr.name as student_name, sr.department, sr.year_of_join
                          FROM internship_submissions i
                          JOIN student_register sr ON i.regno = sr.regno
                          WHERE $where_clause
                          ORDER BY i.submission_date DESC";
        $internship_stmt = $conn->prepare($internship_sql);
        $internship_stmt->bind_param($types, ...$params);
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
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Internship Approvals - Teacher Dashboard</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e4276;
            --secondary-color: #2d5aa0;
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
            color: white;
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
            grid-area: main;
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

        /* Filters Section */
        .filters-section {
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

            .header .header-logo {
                display: none;
            }

            .header-title p {
                font-size: 16px;
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
                z-index: 10000 !important;
                background: #ffffff !important;
                box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
                transition: transform 0.3s ease !important;
                padding: 20px 0 !important;
                overflow-y: auto !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 10001 !important;
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

            .close-sidebar {
                display: flex !important;
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
                position: fixed;
                width: 100%;
                height: 100%;
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
            z-index: 1002;
            left: 0;
            top: 80px;
            width: 100%;
            height: calc(100% - 80px);
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
            max-height: calc(100vh - 120px);
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
            }

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

            .modal {
                padding: 10px;
                top: 0;
                height: 100%;
            }

            .modal-content {
                padding: 15px;
                margin: 0;
                max-height: 95vh;
                width: 95%;
                max-width: 95%;
            }

            .modal-header {
                padding-bottom: 10px;
                margin-bottom: 15px;
            }

            .modal-header h2 {
                font-size: 18px;
            }

            /* Fix submission details in modal for mobile */
            .modal-content > div[style*="background: #f8f9fa"] {
                padding: 12px !important;
                margin-bottom: 15px !important;
            }

            .modal-content h3 {
                font-size: 16px !important;
                margin-bottom: 12px !important;
            }

            /* Fix detail rows in modal */
            .modal-content div[style*="display: flex"] {
                flex-direction: column !important;
                gap: 4px !important;
                padding-bottom: 8px !important;
            }

            .modal-content strong[style*="min-width"] {
                min-width: auto !important;
                font-size: 13px;
            }

            .modal-content span[style*="color: #212529"] {
                font-size: 14px;
                word-break: break-word;
            }

            .modal-content div[style*="display: grid"] {
                gap: 8px !important;
            }

            /* Fix report section */
            .modal-content div#modalBriefReport {
                font-size: 13px !important;
                padding: 12px !important;
                min-height: 80px !important;
                line-height: 1.5 !important;
            }

            .action-btns {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
                padding: 10px;
            }

            .form-group select,
            .form-group textarea {
                font-size: 14px;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
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
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="header-logo">
          <img
            class="logo"
            src="sona_logo.jpg"
            alt="Sona College Logo"
            height="60px"
            width="200"
          />
        </div>
            <div class="header-title">
                <p>Event Management Dashboard</p>
            </div>
            <div>
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
                <div class="student-name"><?php echo htmlspecialchars($teacher_name); ?></div>
                <div class="student-regno">ID:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo htmlspecialchars($teacher_data['employee_id']); ?> <?php if ($is_admin) {echo ' (Admin)';} elseif ($is_counselor) {echo ' (Counselor)';}?></div>
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
        </aside>

        <!-- Main Content -->
        <div class="main">
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

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="section-header">
                    <span class="material-symbols-outlined">filter_alt</span>
                    <h2>Filter Submissions</h2>
                </div>
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" class="filter-input"
                                   placeholder="Search by name, regno, company..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending"                                                                                                               <?php echo(isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved"                                                                                                                 <?php echo(isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected"                                                                                                                 <?php echo(isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="department">Department</label>
                            <select id="department" name="department" class="filter-select">
                                <option value="">All Departments</option>
                                <option value="Computer Science and Engineering"                                                                                                                                                                 <?php echo(isset($_GET['department']) && $_GET['department'] == 'Computer Science and Engineering') ? 'selected' : ''; ?>>CSE</option>
                                <option value="Information Technology"                                                                                                                                             <?php echo(isset($_GET['department']) && $_GET['department'] == 'Information Technology') ? 'selected' : ''; ?>>IT</option>
                                <option value="Electronics and Communication Engineering"                                                                                                                                                                                   <?php echo(isset($_GET['department']) && $_GET['department'] == 'Electronics and Communication Engineering') ? 'selected' : ''; ?>>ECE</option>
                                <option value="Electrical and Electronics Engineering"                                                                                                                                                                             <?php echo(isset($_GET['department']) && $_GET['department'] == 'Electrical and Electronics Engineering') ? 'selected' : ''; ?>>EEE</option>
                                <option value="Mechanical Engineering"                                                                                                                                             <?php echo(isset($_GET['department']) && $_GET['department'] == 'Mechanical Engineering') ? 'selected' : ''; ?>>MECH</option>
                                <option value="Civil Engineering"                                                                                                                                   <?php echo(isset($_GET['department']) && $_GET['department'] == 'Civil Engineering') ? 'selected' : ''; ?>>CIVIL</option>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">search</span>
                                Apply Filters
                            </button>
                            <a href="internship_approvals.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">refresh</span>
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
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
                                                    onclick='openModal(<?php echo json_encode($internship); ?>)'>
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
                                            onclick='openModal(<?php echo json_encode($internship); ?>)'>
                                        Review Submission
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Review Internship Submission</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <!-- Internship Details Section -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #0c3878; font-size: 18px; margin-bottom: 15px;">Submission Details</h3>

                <div style="display: grid; gap: 12px;">
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Student Name:</strong>
                        <span id="modalStudentName" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Reg No:</strong>
                        <span id="modalRegNo" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Department:</strong>
                        <span id="modalDepartment" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Company:</strong>
                        <span id="modalCompany" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Role/Title:</strong>
                        <span id="modalRole" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Domain:</strong>
                        <span id="modalDomain" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Duration:</strong>
                        <span id="modalDuration" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Submission Date:</strong>
                        <span id="modalSubmissionDate" style="color: #212529;"></span>
                    </div>
                    <div style="display: flex; padding-bottom: 8px;">
                        <strong style="min-width: 140px; color: #495057;">Certificate:</strong>
                        <span id="modalCertificate" style="color: #212529;"></span>
                    </div>
                </div>
            </div>

            <!-- Internship Report Section -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: #0c3878; font-size: 18px; margin-bottom: 15px;">Internship Report</h3>
                <div id="modalBriefReport" style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #0c3878; min-height: 120px; color: #212529; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word;"></div>
            </div>

            <!-- Approval Form -->
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
        // Mobile menu toggle function
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

        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu button functionality
            const sidebar = document.getElementById('sidebar');

            // Header menu icon functionality
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            // Close sidebar button functionality
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    (!headerMenuIcon || !headerMenuIcon.contains(event.target))) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });

        function openModal(internshipData) {
            // Set form data
            document.getElementById('internshipId').value = internshipData.id;

            // Populate details
            document.getElementById('modalStudentName').textContent = internshipData.student_name || 'N/A';
            document.getElementById('modalRegNo').textContent = internshipData.regno || 'N/A';
            document.getElementById('modalDepartment').textContent = internshipData.department || 'N/A';
            document.getElementById('modalCompany').textContent = internshipData.company_name || 'N/A';
            document.getElementById('modalRole').textContent = internshipData.role_title || 'N/A';
            document.getElementById('modalDomain').textContent = internshipData.domain || 'N/A';

            // Format duration
            if (internshipData.start_date && internshipData.end_date) {
                const startDate = new Date(internshipData.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const endDate = new Date(internshipData.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                document.getElementById('modalDuration').textContent = `${startDate} - ${endDate}`;
            } else {
                document.getElementById('modalDuration').textContent = 'N/A';
            }

            // Submission date
            if (internshipData.submission_date) {
                const subDate = new Date(internshipData.submission_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                document.getElementById('modalSubmissionDate').textContent = subDate;
            } else {
                document.getElementById('modalSubmissionDate').textContent = 'N/A';
            }

            // Certificate link
            const certElement = document.getElementById('modalCertificate');
            if (internshipData.internship_certificate) {
                certElement.innerHTML = `<a href="../uploads/${internshipData.internship_certificate}" target="_blank" class="certificate-link">View Certificate</a>`;
            } else {
                certElement.innerHTML = '<span style="color: #999;">Not Available</span>';
            }

            // Brief report
            const reportElement = document.getElementById('modalBriefReport');
            if (internshipData.brief_report) {
                reportElement.textContent = internshipData.brief_report;
            } else {
                reportElement.textContent = 'No report submitted';
                reportElement.style.color = '#999';
            }

            // Show modal
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
