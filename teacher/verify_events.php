<?php
    // Start session
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $faculty_id   = $_SESSION['faculty_id'] ?? $_SESSION['id'] ?? 0;
    $faculty_name = $_SESSION['name'] ?? 'Counselor';
    $username     = $_SESSION['username'] ?? '';

    // Database connection
    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get filters from request
    $event_category = isset($_GET['category']) ? $_GET['category'] : 'All';
    $status_filter  = isset($_GET['status']) ? $_GET['status'] : 'Pending';

    // Handle Approve action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
        $event_id    = intval($_POST['event_id']);
        $update_stmt = $conn->prepare("UPDATE student_event_register SET verification_status = 'Approved', verified_by = ?, verified_date = NOW() WHERE id = ?");
        $update_stmt->bind_param("ii", $faculty_id, $event_id);
        if ($update_stmt->execute()) {
            $update_stmt->close();
            $conn->close();
            // Redirect back to Pending page to continue approving more events
            header("Location: verify_events.php?success=approved&category=" . urlencode($event_category) . "&status=Pending");
            exit();
        } else {
            $error_message = "❌ Error approving registration: " . $update_stmt->error;
            $update_stmt->close();
        }
    }

    // Handle Reject action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
        $event_id         = intval($_POST['event_id']);
        $rejection_reason = trim($_POST['rejection_reason'] ?? '');

        if (empty($rejection_reason)) {
            $error_message = "❌ Rejection reason is required.";
        } else {
            $update_stmt = $conn->prepare("UPDATE student_event_register SET verification_status = 'Rejected', rejection_reason = ?, verified_by = ?, verified_date = NOW() WHERE id = ?");
            $update_stmt->bind_param("sii", $rejection_reason, $faculty_id, $event_id);
            if ($update_stmt->execute()) {
                $update_stmt->close();
                $conn->close();
                // Redirect back to Pending page to continue processing more events
                header("Location: verify_events.php?success=rejected&category=" . urlencode($event_category) . "&status=Pending");
                exit();
            } else {
                $error_message = "❌ Error rejecting registration: " . $update_stmt->error;
                $update_stmt->close();
            }
        }
    }

    // Check for success messages from redirects
    if (isset($_GET['success'])) {
        if ($_GET['success'] === 'approved') {
            $success_message = "✅ Event registration approved successfully!";
        } elseif ($_GET['success'] === 'rejected') {
            $success_message = "✅ Event registration rejected successfully!";
        }
    }

    // Build SQL query based on filters
    $query = "SELECT
                 ser.id,
                 sr.name AS student_name,
                 ser.regno,
                 ser.event_name,
                 ser.organisation as organizer,
                 ser.start_date as event_date,
                 ser.event_type as category,
                 ser.prize,
                 COALESCE(ser.verification_status, 'Pending') as status,
                 ser.start_date as created_at
          FROM student_event_register ser
          JOIN student_register sr ON ser.regno = sr.regno
          WHERE 1=1";

    // Apply filters
    if ($event_category !== 'All') {
        $query .= " AND ser.event_type = '" . $conn->real_escape_string($event_category) . "'";
    }
    $query .= " AND COALESCE(ser.verification_status, 'Pending') = '" . $conn->real_escape_string($status_filter) . "'";

    // Add ordering
    $query .= " ORDER BY ser.start_date DESC";

    $result = $conn->query($query);
    if (! $result) {
        $error_message = "Query Error: " . $conn->error;
        $result        = null;
    }

    $total_records = $result ? $result->num_rows : 0;

    // Category colors
    $category_colors = [
        'Workshop'           => '#3498db',
        'Symposium'          => '#9b59b6',
        'Conference'         => '#e74c3c',
        'Hackathon'          => '#8e44ad',
        'Seminar'            => '#2ecc71',
        'Paper Presentation' => '#f39c12',
    ];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Event Certificate Validation</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #0c3878;
            --secondary-color: #1e4276;
            --accent-color: #2d5aa0;
            --light-bg: #f8f9fa;
            --border-color: #e1e8ed;
            --text-primary: #0c3878;
            --text-secondary: #495057;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            min-height: 100vh;
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

        /* Header */
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
            padding: 0 30px;
        }

        .header-logo img {
            height: 60px;
            width: 200px;
            object-fit: contain;
            flex-shrink: 0;
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

        .header-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-left: 15px;
            border-left: 1px solid #e0e0e0;
        }

        .profile-info {
            text-align: right;
        }

        .profile-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary-color);
            display: block;
        }

        .profile-role {
            font-size: 12px;
            color: #6c757d;
            display: block;
        }

        /* Sidebar */
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
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .close-sidebar {
            display: none;
            cursor: pointer;
            color: var(--primary-color);
            font-size: 24px;
        }

        .student-info {
            padding: 15px;
            margin: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 12px;
            color: white;
        }

        .student-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .student-regno {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }

        .nav-menu {
            list-style: none;
            padding: 10px 0;
            margin: 0;
        }

        .nav-item {
            margin: 0;
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

        .nav-link:hover {
            background: #f5f5f5;
            color: var(--primary-color);
        }

        .nav-item.active .nav-link {
            background: #f0f4f8;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }

        .nav-link .material-symbols-outlined {
            font-size: 20px;
            width: 20px;
        }

        /* Main Content */
        .main {
            grid-area: main;
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        /* Filter Bar */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .filter-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            color: #495057;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(12, 56, 120, 0.3);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .stats-bar {
            padding: 15px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #6c757d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
            color: #495057;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        /* Student Info */
        .student-info-cell {
            font-weight: 600;
            color: var(--primary-color);
        }

        .student-regno {
            font-size: 12px;
            color: #6c757d;
            margin-top: 3px;
        }

        /* Event Details */
        .event-name {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .event-meta {
            font-size: 12px;
            color: #6c757d;
            margin: 3px 0;
        }

        /* Category Badge */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            text-align: center;
        }

        /* Achievement Level */
        .achievement-prize {
            background: #fff3cd;
            color: #856404;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .achievement-participant {
            color: #6c757d;
            font-size: 12px;
        }

        .warning-text {
            color: #ff6b6b;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-left: 5px;
        }

        /* Actions */
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel {
            background: #e9ecef;
            color: #495057;
        }

        .btn-modal-cancel:hover {
            background: #dee2e6;
        }

        .btn-modal-confirm {
            background: #dc3545;
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #c82333;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-icon {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }

        .empty-text {
            font-size: 16px;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
                padding-top: 80px;
            }

            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
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

            .main {
                padding: 20px 15px;
            }

            .header {
                padding: 0 15px;
            }

            .header-logo img {
                display: none;
            }

            .filter-row {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px 12px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="header-logo">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200" />
            </div>
            <div class="header-title">
                <p>Event Management Dashboard</p>
            </div>
            <div class="header-profile">
                <div class="profile-info">
                    <span class="profile-name"><?php echo htmlspecialchars($faculty_name); ?></span>
                    <span class="profile-role">Counselor</span>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Counselor Portal</div>
                <div class="close-sidebar">
                    <i class="fas fa-times"></i>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($faculty_name); ?></div>
                <div class="student-regno">ID:                                                                                                                                                                                                                                                                                     <?php echo htmlspecialchars($faculty_id); ?> (Counselor)</div>
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
                    <li class="nav-item active">
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
        <main class="main">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"> Event Certificate Validation</h1>
                <p class="page-subtitle">Review and verify event participation certificates from students</p>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">Event Category</label>
                            <select name="category" class="filter-select">
                                <option value="All"                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $event_category === 'All' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Workshop"                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $event_category === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Symposium"                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo $event_category === 'Symposium' ? 'selected' : ''; ?>>Symposium</option>
                                <option value="Conference"                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo $event_category === 'Conference' ? 'selected' : ''; ?>>Conference</option>
                                <option value="Hackathon"                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo $event_category === 'Hackathon' ? 'selected' : ''; ?>>Hackathon</option>
                                <option value="Seminar"                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $event_category === 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="Paper Presentation"                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo $event_category === 'Paper Presentation' ? 'selected' : ''; ?>>Paper Presentation</option>
                                <option value="Webinar"                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo $event_category === 'Webinar' ? 'selected' : ''; ?>>Webinar</option>
                                <option value="Competition"                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo $event_category === 'Competition' ? 'selected' : ''; ?>>Competition</option>
                                <option value="Cultural"                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $event_category === 'Cultural' ? 'selected' : ''; ?>>Cultural</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="Pending"                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved"                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected"                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="filter-btn">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="stats-bar">
                    <span><strong><?php echo $total_records; ?></strong> certificate(s) found</span>
                    <span>Status: <strong><?php echo htmlspecialchars($status_filter); ?></strong></span>
                </div>

                <?php if ($total_records > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Info</th>
                                <th>Event Details</th>
                                <th>Category</th>
                                <th>Achievement</th>
                                <th>Certificate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <!-- Student Info -->
                                    <td>
                                        <div class="student-info-cell"><?php echo htmlspecialchars($row['student_name']); ?></div>
                                        <div class="student-regno"><?php echo htmlspecialchars($row['regno']); ?></div>
                                    </td>

                                    <!-- Event Details -->
                                    <td>
                                        <div class="event-name"><?php echo htmlspecialchars($row['event_name']); ?></div>
                                        <div class="event-meta"><i class="fas fa-building"></i>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo htmlspecialchars($row['organizer']); ?></div>
                                        <div class="event-meta"><i class="fas fa-calendar"></i>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo date('M d, Y', strtotime($row['event_date'])); ?></div>
                                    </td>

                                    <!-- Category Badge -->
                                    <td>
                                        <span class="badge" style="background:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $category_colors[$row['category']] ?? '#6c757d'; ?>;">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>

                                    <!-- Achievement Level -->
                                    <td>
                                        <?php if (! empty($row['prize']) && in_array($row['prize'], ['First', 'Second', 'Third'])): ?>
                                            <div class="achievement-prize">
                                                <i class="fas fa-trophy"></i>
                                                <?php echo htmlspecialchars($row['prize']); ?> Prize
                                                <?php if ($status_filter === 'Pending'): ?>
                                                    <span class="warning-text">⚠ Verify Carefully</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="achievement-participant">Participant</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Certificate View -->
                                    <td>
                                        <?php if (! empty($row['certificate_file'])): ?>
                                            <a href="../student/<?php echo htmlspecialchars($row['certificate_file']); ?>" target="_blank" class="btn btn-view">
                                                <i class="fas fa-eye"></i> View Cert
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#6c757d; font-size: 12px;">No file</span>
                                        <?php endif; ?>
                                        <?php if (! empty($row['event_photo'])): ?>
                                            <a href="../student/<?php echo htmlspecialchars($row['event_photo']); ?>" target="_blank" class="btn btn-view" style="margin-top: 5px;">
                                                <i class="fas fa-image"></i> Photo
                                            </a>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($status_filter === 'Pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="event_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this event registration?')">
                                                        <i class="fas fa-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <button class="btn btn-reject" onclick="openRejectModal(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span style="color:#6c757d; font-size: 12px;">
                                                    <i class="fas fa-lock"></i>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo htmlspecialchars($status_filter); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="empty-text">No certificates found</div>
                        <p style="font-size: 12px;">Try adjusting your filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-times-circle" style="color: #dc3545;"></i>
                Reject Event Registration
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="modalEventId" value="">
                    <input type="hidden" name="action" value="reject">

                    <div class="form-group">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_reason" class="form-control" placeholder="Please provide a reason for rejecting this event registration..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-confirm">Reject Registration</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(eventId) {
            document.getElementById('modalEventId').value = eventId;
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('active');
            document.getElementById('rejectForm').reset();
        }

        // Close modal when clicking outside
        window.addEventListener('click', function (event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                closeRejectModal();
            }
        });

        // Close alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html>

<?php
    $conn->close();
?>
