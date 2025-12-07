<?php
// Start session
session_start();

// Check if user is logged in as teacher/counselor
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../index.php");
    exit();
}

$faculty_id = $_SESSION['faculty_id'] ?? $_SESSION['id'];
$faculty_name = $_SESSION['name'] ?? 'Counselor';

// Database connection
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filters from request
$event_category = isset($_GET['category']) ? $_GET['category'] : 'All';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'Pending';

// Handle Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $cert_id = intval($_POST['cert_id']);
    $update_stmt = $conn->prepare("UPDATE event_certificates SET status = 'Approved', verified_by = ?, verified_date = NOW() WHERE id = ?");
    $update_stmt->bind_param("ii", $faculty_id, $cert_id);
    if ($update_stmt->execute()) {
        $success_message = "✅ Certificate approved successfully!";
    } else {
        $error_message = "❌ Error approving certificate: " . $update_stmt->error;
    }
    $update_stmt->close();
}

// Handle Reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject') {
    $cert_id = intval($_POST['cert_id']);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if (empty($rejection_reason)) {
        $error_message = " Rejection reason is required.";
    } else {
        $update_stmt = $conn->prepare("UPDATE event_certificates SET status = 'Rejected', rejection_reason = ?, verified_by = ?, verified_date = NOW() WHERE id = ?");
        $update_stmt->bind_param("sii", $rejection_reason, $faculty_id, $cert_id);
        if ($update_stmt->execute()) {
            $success_message = " Certificate rejected successfully!";
        } else {
            $error_message = " Error rejecting certificate: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}

// Build SQL query based on filters
$query = "SELECT ec.id, s.name AS student_name, s.regno, e.event_name, e.organizer, e.event_date, 
                 ec.category, ec.achievement_level, ec.certificate_file, ec.status, ec.created_at
          FROM event_certificates ec
          JOIN student_register s ON ec.student_id = s.id
          JOIN events e ON ec.event_id = e.id
          WHERE 1=1";

// Apply filters
if ($event_category !== 'All') {
    $query .= " AND ec.category = '" . $conn->real_escape_string($event_category) . "'";
}
$query .= " AND ec.status = '" . $conn->real_escape_string($status_filter) . "'";

// Add ordering
$query .= " ORDER BY ec.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    $error_message = "Query Error: " . $conn->error;
    $result = null;
}

$total_records = $result ? $result->num_rows : 0;

// Category colors
$category_colors = [
    'Workshop' => '#3498db',
    'Symposium' => '#9b59b6',
    'Conference' => '#e74c3c',
    'Hackathon' => '#8e44ad',
    'Seminar' => '#2ecc71',
    'Paper Presentation' => '#f39c12'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Event Certificate Validation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
            grid-template-columns: 250px 1fr;
            grid-template-rows: 80px 1fr;
            grid-template-areas:
                "sidebar header"
                "sidebar main";
            min-height: 100vh;
            width: 100%;
        }

        /* Header */
        .header {
            grid-area: header;
            background: white;
            display: flex;
            align-items: center;
            padding: 0 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            gap: 20px;
        }

        .header-logo img {
            height: 60px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .header-title {
            flex: 1;
            text-align: center;
        }

        .header-title p {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
            color: var(--primary-color);
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
            background: white;
            border-right: 1px solid #e0e0e0;
            overflow-y: auto;
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

        .faculty-info {
            padding: 15px;
            margin: 15px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 12px;
            color: white;
        }

        .faculty-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 5px;
        }

        .faculty-id {
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
            gap: 12px;
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            border-left: 4px solid transparent;
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

        .nav-link i {
            font-size: 18px;
            width: 20px;
        }

        /* Main Content */
        .main {
            grid-area: main;
            padding: 30px;
            overflow-y: auto;
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
                grid-template-columns: 1fr;
                grid-template-areas:
                    "header"
                    "main";
            }

            .sidebar {
                display: none;
            }

            .header {
                padding: 0 15px;
            }

            .header-logo img {
                display: none;
            }

            .main {
                padding: 20px 15px;
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
                <img src="../asserts/images/Sona Logo.png" alt="Sona College Logo" />
            </div>
            <div class="header-title">
                <p>Event Management System</p>
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

            <div class="faculty-info">
                <div class="faculty-name"><?php echo htmlspecialchars($faculty_name); ?></div>
                <div class="faculty-id">ID: <?php echo htmlspecialchars($faculty_id); ?></div>
            </div>

            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="fas fa-chart-line"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            Registered Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="assigned_students.php" class="nav-link">
                            <i class="fas fa-user-check"></i>
                            My Assigned Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_approvals.php" class="nav-link">
                            <i class="fas fa-clipboard-check"></i>
                            OD Approvals
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="verify_events.php" class="nav-link">
                            <i class="fas fa-certificate"></i>
                            Event Certificate Validation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link">
                            <i class="fas fa-user-circle"></i>
                            Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/logout.php" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
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
                                <option value="All" <?php echo $event_category === 'All' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Workshop" <?php echo $event_category === 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                <option value="Symposium" <?php echo $event_category === 'Symposium' ? 'selected' : ''; ?>>Symposium</option>
                                <option value="Conference" <?php echo $event_category === 'Conference' ? 'selected' : ''; ?>>Conference</option>
                                <option value="Hackathon" <?php echo $event_category === 'Hackathon' ? 'selected' : ''; ?>>Hackathon</option>
                                <option value="Seminar" <?php echo $event_category === 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                                <option value="Paper Presentation" <?php echo $event_category === 'Paper Presentation' ? 'selected' : ''; ?>>Paper Presentation</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
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
                                        <div class="event-meta"><i class="fas fa-building"></i> <?php echo htmlspecialchars($row['organizer']); ?></div>
                                        <div class="event-meta"><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($row['event_date'])); ?></div>
                                    </td>

                                    <!-- Category Badge -->
                                    <td>
                                        <span class="badge" style="background: <?php echo $category_colors[$row['category']] ?? '#6c757d'; ?>;">
                                            <?php echo htmlspecialchars($row['category']); ?>
                                        </span>
                                    </td>

                                    <!-- Achievement Level -->
                                    <td>
                                        <?php if ($row['achievement_level'] === 'Prize Winner'): ?>
                                            <div class="achievement-prize">
                                                <i class="fas fa-trophy"></i>
                                                Prize Winner
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
                                        <a href="<?php echo htmlspecialchars($row['certificate_file']); ?>" target="_blank" class="btn btn-view">
                                            <i class="fas fa-eye"></i> View Cert
                                        </a>
                                    </td>

                                    <!-- Actions -->
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($status_filter === 'Pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="cert_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this certificate?')">
                                                        <i class="fas fa-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                <button class="btn btn-reject" onclick="openRejectModal(<?php echo $row['id']; ?>)">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            <?php else: ?>
                                                <span style="color:#6c757d; font-size: 12px;">
                                                    <i class="fas fa-lock"></i> <?php echo htmlspecialchars($status_filter); ?>
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
                Reject Certificate
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="cert_id" id="modalCertId" value="">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_reason" class="form-control" placeholder="Please provide a reason for rejecting this certificate..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-modal-confirm">Reject Certificate</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(certId) {
            document.getElementById('modalCertId').value = certId;
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
