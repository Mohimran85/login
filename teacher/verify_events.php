<?php
session_start();
require_once 'config.php';

// Set secure cache headers
header("Cache-Control: private, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Require teacher role
require_teacher_role();

// Get database connection
$conn = get_db_connection();

// Get teacher data and check role
$username = $_SESSION['username'];
$teacher_data = null;
$is_admin = false;
$is_counselor = false;

$sql = "SELECT id, name, employee_id, email, is_admin, is_counselor FROM teacher_register WHERE username=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $teacher_data = $result->fetch_assoc();
    $_SESSION['teacher_id'] = $teacher_data['id'];
    $is_admin = (bool)($teacher_data['is_admin'] ?? false);
    $is_counselor = (bool)($teacher_data['is_counselor'] ?? false);
} else {
    header("Location: ../index.php");
    exit();
}

$stmt->close();

// Check if user has admin or counselor role
if (!$is_admin && !$is_counselor) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied. Admin or Counselor role required.");
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Handle POST requests (approve/reject actions)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        header("HTTP/1.1 403 Forbidden");
        die("Invalid CSRF token.");
    }
    
    $action = $_POST['action'] ?? '';
    $registration_id = (int)($_POST['registration_id'] ?? 0);
    
    if ($registration_id > 0 && in_array($action, ['approve', 'reject'])) {
        if ($action === 'approve') {
            // Update registration status to verified
            $update_sql = "UPDATE student_event_register SET verification_status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ii", $teacher_data['id'], $registration_id);
            
            if ($update_stmt->execute()) {
                $message = "Event registration approved successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to approve registration.";
                $message_type = "error";
            }
            $update_stmt->close();
        } elseif ($action === 'reject') {
            // Update registration status to rejected
            $reject_reason = $_POST['reject_reason'] ?? '';
            $update_sql = "UPDATE student_event_register SET verification_status = 'rejected', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("isi", $teacher_data['id'], $reject_reason, $registration_id);
            
            if ($update_stmt->execute()) {
                $message = "Event registration rejected.";
                $message_type = "success";
            } else {
                $message = "Failed to reject registration.";
                $message_type = "error";
            }
            $update_stmt->close();
        }
    }
}

// Get pending event registrations
$registrations_query = "SELECT 
    ser.id,
    ser.regno,
    ser.event_name,
    ser.event_type,
    ser.event_date,
    ser.attended_date,
    ser.event_poster,
    ser.certificates,
    sr.name as student_name,
    sr.department,
    sr.email,
    ser.verification_status
FROM student_event_register ser
JOIN student_register sr ON ser.regno = sr.regno
WHERE ser.verification_status = 'pending' OR ser.verification_status IS NULL
ORDER BY ser.attended_date DESC, ser.id DESC";

$registrations_stmt = $conn->prepare($registrations_query);
$registrations_stmt->execute();
$registrations_result = $registrations_stmt->get_result();

$registrations_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Event Registrations - Teacher Dashboard</title>
    <!-- google icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #1e4276;
            --secondary-color: #2d5aa0;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --font-family: "Poppins", sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: #ffffff;
            min-height: 100vh;
            line-height: 1.6;
            color: #333;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* Grid Layout */
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
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1002;
        }

        .header .menu-icon {
            display: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .header .menu-icon:hover {
            background-color: var(--light-color);
        }

        .header .icon img {
            height: 75px;
            width: 200px;
            border-radius: 10px;
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

        /* Sidebar */
        .sidebar {
            grid-area: sidebar;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 25px 0;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            width: 280px;
            overflow-y: auto;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            z-index: 1001;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--white);
        }

        .close-sidebar {
            display: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .close-sidebar:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .student-info {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .student-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .student-regno {
            font-size: 14px;
            opacity: 0.9;
        }

        .nav-menu {
            list-style: none;
            padding: 20px 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 30px;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--white);
        }

        .nav-link .material-symbols-outlined {
            font-size: 24px;
        }

        /* Main Content */
        .main {
            grid-area: main;
            padding: 30px;
            background: #f8f9fa;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            background: var(--white);
            padding: 25px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 16px;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Registrations Container */
        .registrations-container {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .registrations-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .registration-card {
            background: var(--white);
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .registration-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .registration-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .registration-info h3 {
            color: var(--primary-color);
            font-size: 20px;
            margin-bottom: 5px;
        }

        .registration-info p {
            color: #6c757d;
            font-size: 14px;
        }

        .registration-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-item .material-symbols-outlined {
            color: var(--primary-color);
            font-size: 20px;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .detail-value {
            color: #6c757d;
            font-size: 14px;
        }

        .attachments {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .attachment-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--info-color);
            color: var(--white);
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .attachment-btn:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .attachment-btn .material-symbols-outlined {
            font-size: 20px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .action-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            font-size: 14px;
        }

        .btn-approve {
            background: var(--success-color);
            color: var(--white);
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-reject {
            background: var(--danger-color);
            color: var(--white);
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 10px;
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
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background-color: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: var(--primary-color);
            font-size: 24px;
        }

        .modal-close {
            cursor: pointer;
            font-size: 28px;
            color: #adb5bd;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--danger-color);
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .modal-body textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-family: var(--font-family);
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .modal-body textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn-cancel {
            background: #6c757d;
            color: var(--white);
        }

        .modal-btn-cancel:hover {
            background: #5a6268;
        }

        .modal-btn-submit {
            background: var(--danger-color);
            color: var(--white);
        }

        .modal-btn-submit:hover {
            background: #c82333;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas: "main";
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .header .menu-icon {
                display: block;
            }

            .close-sidebar {
                display: block;
            }

            .main {
                padding: 20px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .registrations-container {
                padding: 20px;
            }

            .registration-details {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }

        @media screen and (max-width: 480px) {
            .header-title p {
                font-size: 16px;
            }

            .attachments {
                flex-direction: column;
            }

            .attachment-btn {
                width: 100%;
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
                <div class="student-regno">ID: <?php echo htmlspecialchars($teacher_data['employee_id']); ?></div>
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
                        <a href="digital_signature.php" class="nav-link">
                            <span class="material-symbols-outlined">draw</span>
                            Digital Signature
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="verify_events.php" class="nav-link active">
                            <span class="material-symbols-outlined">verified</span>
                            Verify Events
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>Verify Event Registrations</h1>
                <p>Review and approve pending student event registrations</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <span class="material-symbols-outlined">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Registrations Container -->
            <div class="registrations-container">
                <h2>Pending Registrations</h2>
                
                <?php if ($registrations_result->num_rows > 0): ?>
                    <div class="registrations-grid">
                        <?php while ($reg = $registrations_result->fetch_assoc()): ?>
                            <div class="registration-card">
                                <div class="registration-header">
                                    <div class="registration-info">
                                        <h3><?php echo htmlspecialchars($reg['student_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($reg['regno']); ?> - <?php echo htmlspecialchars($reg['department']); ?></p>
                                    </div>
                                </div>

                                <div class="registration-details">
                                    <div class="detail-item">
                                        <span class="material-symbols-outlined">event</span>
                                        <div>
                                            <div class="detail-label">Event Name</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($reg['event_name']); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="material-symbols-outlined">category</span>
                                        <div>
                                            <div class="detail-label">Event Type</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($reg['event_type']); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="material-symbols-outlined">calendar_today</span>
                                        <div>
                                            <div class="detail-label">Event Date</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($reg['event_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="material-symbols-outlined">schedule</span>
                                        <div>
                                            <div class="detail-label">Attended Date</div>
                                            <div class="detail-value"><?php echo date('M d, Y', strtotime($reg['attended_date'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <span class="material-symbols-outlined">email</span>
                                        <div>
                                            <div class="detail-label">Email</div>
                                            <div class="detail-value"><?php echo htmlspecialchars($reg['email']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="attachments">
                                    <?php if (!empty($reg['event_poster'])): ?>
                                        <a href="view_poster.php?id=<?php echo $reg['id']; ?>&type=poster" 
                                           target="_blank" 
                                           class="attachment-btn">
                                            <span class="material-symbols-outlined">image</span>
                                            View Poster
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($reg['certificates'])): ?>
                                        <a href="view_poster.php?id=<?php echo $reg['id']; ?>&type=certificate" 
                                           target="_blank" 
                                           class="attachment-btn">
                                            <span class="material-symbols-outlined">workspace_premium</span>
                                            View Certificate
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="actions">
                                    <button class="action-btn btn-approve" 
                                            onclick="<?php 
                                                $data = [
                                                    'id' => $reg['id'],
                                                    'action' => 'approve'
                                                ];
                                                echo htmlspecialchars(
                                                    'approveRegistration(' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>">
                                        <span class="material-symbols-outlined">check_circle</span>
                                        Approve
                                    </button>
                                    <button class="action-btn btn-reject" 
                                            onclick="<?php 
                                                $data = [
                                                    'id' => $reg['id'],
                                                    'name' => $reg['student_name']
                                                ];
                                                echo htmlspecialchars(
                                                    'showRejectModal(' . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                );
                                            ?>">
                                        <span class="material-symbols-outlined">cancel</span>
                                        Reject
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">verified</span>
                        <p>No pending registrations</p>
                        <p style="font-size: 14px; color: #adb5bd;">All event registrations have been verified.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Registration</h2>
                <span class="modal-close" onclick="closeRejectModal()">&times;</span>
            </div>
            <form id="rejectForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="registration_id" id="reject_registration_id">
                
                <div class="modal-body">
                    <p id="reject_student_name" style="margin-bottom: 15px; color: #6c757d;"></p>
                    <label for="reject_reason">Reason for Rejection (Optional):</label>
                    <textarea name="reject_reason" id="reject_reason" placeholder="Enter reason for rejection..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-submit">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Approve registration
        function approveRegistration(data) {
            if (confirm('Are you sure you want to approve this registration?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="registration_id" value="${data.id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Show reject modal
        function showRejectModal(data) {
            const modal = document.getElementById('rejectModal');
            document.getElementById('reject_registration_id').value = data.id;
            document.getElementById('reject_student_name').textContent = `Rejecting registration for: ${data.name}`;
            document.getElementById('reject_reason').value = '';
            modal.classList.add('show');
        }

        // Close reject modal
        function closeRejectModal() {
            const modal = document.getElementById('rejectModal');
            modal.classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                closeRejectModal();
            }
        }

        // Toggle sidebar function
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

        // Wait for DOM to load - Single event listener
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');

            // Header menu icon functionality
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            // Close sidebar button functionality
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
    </script>
</body>
</html>

<?php
$conn->close();
?>
