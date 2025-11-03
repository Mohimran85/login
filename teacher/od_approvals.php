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
    } else {
        header("Location: ../index.php");
        exit();
    }

    if (! $is_counselor) {
        $_SESSION['access_denied'] = 'Only counselors can access OD approvals. Your role is: ' . ucfirst($teacher_data['status']);
        header("Location: index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    // Handle OD request approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_od_status'])) {
        $od_id             = $_POST['od_id'];
        $new_status        = $_POST['new_status'];
        $counselor_remarks = trim($_POST['counselor_remarks']);

        $update_sql  = "UPDATE od_requests SET status = ?, counselor_remarks = ?, response_date = CURRENT_TIMESTAMP WHERE id = ? AND counselor_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $new_status, $counselor_remarks, $od_id, $teacher_data['id']);

        if ($update_stmt->execute()) {
            $message      = "OD request " . ucfirst($new_status) . " successfully!";
            $message_type = 'success';
        } else {
            $message      = "Error updating OD request: " . $conn->error;
            $message_type = 'error';
        }
        $update_stmt->close();
    }

    // Get OD requests for this counselor
    $od_requests_sql = "SELECT od.*, sr.name as student_name, sr.department, sr.year_of_join
                        FROM od_requests od
                        JOIN student_register sr ON od.student_regno = sr.regno
                        WHERE od.counselor_id = ?
                        ORDER BY od.request_date DESC";
    $od_requests_stmt = $conn->prepare($od_requests_sql);
    $od_requests_stmt->bind_param("i", $teacher_data['id']);
    $od_requests_stmt->execute();
    $od_requests_result = $od_requests_stmt->get_result();

    // Get statistics
    $stats_sql = "SELECT
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
                  FROM od_requests WHERE counselor_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $teacher_data['id']);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats        = $stats_result->fetch_assoc();

    $stmt->close();
    $od_requests_stmt->close();
    $stats_stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Approvals - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            text-align: center;
        }

        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.approved { border-left-color: #28a745; }
        .stat-card.rejected { border-left-color: #dc3545; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .od-requests-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 0;
            width: 100%;
            max-width: none;
        }

        .section-card {
            width: 100%;
            margin-bottom: 25px;
            margin-top: 25px;
            border-radius: 12px;
            border: 2px solid #f0f0f0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .section-card:hover {
            border-color: #0c3878;
            box-shadow: 0 4px 15px rgba(12, 56, 120, 0.1);
        }

        .section-header {
            background: linear-gradient(135deg, #0c3878 0%, #2d5aa0 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .section-header h4 {
            margin: 0;
            font-size: 16px;
            letter-spacing: 0.5px;
        }

        .section-header .material-symbols-outlined {
            font-size: 20px;
        }

        .od-request-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #0c3878;
            display: flex;
            flex-direction: column;
            gap: 20px;
            width: 100%;
            max-width: none;
        }

        .od-request-item.pending {
            border-left-color: #ffc107;
            animation: subtle-pulse 3s ease-in-out infinite;
        }

        @keyframes subtle-pulse {
            0%, 100% { box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); }
            50% { box-shadow: 0 6px 25px rgba(255, 193, 7, 0.15); }
        }
        .od-request-item.approved { border-left-color: #28a745; }
        .od-request-item.rejected { border-left-color: #dc3545; }

        .od-request-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .student-info {
            text-align: center;
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: 1px solid #e9ecef;
        }

        .student-info h3 {
            color: #ffffffff;
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 600;
        }

        .student-details {
            font-size: 14px;
            color: #ffffffff;
            margin-bottom: 10px;
        }

        .od-status {
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            align-self: center;
            min-width: 120px;
            text-align: center;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .od-status.pending {
            background: linear-gradient(135deg, #ffc107 0%, #ffeb3b 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }
        .od-status.approved {
            background: linear-gradient(135deg, #28a745 0%, #4caf50 100%);
            color: white;
            border: 2px solid #28a745;
        }
        .od-status.rejected {
            background: linear-gradient(135deg, #dc3545 0%, #f44336 100%);
            color: white;
            border: 2px solid #dc3545;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 20px;
            background: white;
        }

        .event-detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .event-detail-item:last-child {
            border-bottom: none;
        }

        .event-detail-label {
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-detail-value {
            color: #333;
            font-size: 15px;
            font-weight: 500;
        }

        .description-box {
            background: white;
            padding: 20px;
            line-height: 1.6;
            font-size: 15px;
            color: #333;
        }

        .approval-form {
            background: white;
            padding: 20px;
        }

        .approval-form h4 {
            color: #0c3878;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-textarea {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            min-height: 80px;
            resize: vertical;
            transition: border-color 0.3s ease;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .btn {
            padding: 15px 24px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea080 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Poster Modal Styles */
        .poster-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            animation: fadeIn 0.3s ease;
        }

        .poster-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .poster-modal img {
            width: 100%;
            height: auto;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 10px;
        }

        .poster-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            background: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .poster-modal-close:hover {
            color: #000;
            background: #f0f0f0;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .main {
                padding: 10px !important;
            }

            .od-requests-container {
                padding: 12px;
                margin: 0 5px;
            }

            .section-card {
                margin-bottom: 15px;
            }

            .section-header {
                padding: 10px 12px;
                font-size: 14px;
            }

            .section-header h4 {
                font-size: 14px;
            }

            .od-request-header {
                padding: 12px;
            }

            .student-info h3 {
                font-size: 16px;
            }

            .student-details {
                font-size: 13px;
            }

            .event-details {
                padding: 12px;
            }

            .event-detail-item {
                padding: 8px;
                margin-bottom: 5px;
            }

            .description-box {
                padding: 12px;
                font-size: 14px;
            }

            .approval-form {
                padding: 12px;
            }

            .btn {
                padding: 16px 20px;
                font-size: 15px;
                font-weight: 700;
            }

            .form-textarea {
                min-height: 60px;
                font-size: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 0 5px;
            }
        }

        /* Tablet responsive */
        @media (min-width: 769px) and (max-width: 1024px) {
            .main {
                padding: 12px !important;
            }

            .od-requests-container {
                padding: 18px;
                margin: 0 8px;
            }

            .od-request-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .event-details {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .btn {
                padding: 14px 20px;
                font-size: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                padding: 0 8px;
            }
        }

        /* Large screens optimization */
        @media (min-width: 1200px) {
            .od-requests-container {
                padding: 25px;
                margin: 0 10px;
            }

            .od-request-item {
                padding: 25px;
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
                <div class="sidebar-title">Counselor Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info"  style="color: white;">
                <div class="student-name" style="color:white;"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID:                                                                                                                                           <?php echo htmlspecialchars($teacher_data['faculty_id']); ?> (Counselor)</div>
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
                        <a href="od_approvals.php" class="nav-link active">
                            <span class="material-symbols-outlined">approval</span>
                            OD Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            My Students
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
            <?php if ($message): ?>
                <div class="message<?php echo $message_type; ?>">
                    <span class="material-symbols-outlined">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                    <div class="stat-label">Approved Requests</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo $stats['rejected_requests'] ?? 0; ?></div>
                    <div class="stat-label">Rejected Requests</div>
                </div>
            </div>

            <!-- OD Requests -->
            <div class="od-requests-container">
                <h2 style="margin-bottom: 30px; color: var(--primary-color); display: flex; align-items: center; gap: 10px;">
                    <span class="material-symbols-outlined">approval</span>
                    Student OD Requests
                </h2>

                <?php if ($od_requests_result->num_rows > 0): ?>
                    <?php while ($request = $od_requests_result->fetch_assoc()): ?>
                    <div class="od-request-item<?php echo $request['status']; ?>">
                        <!-- Section 1: Student Information -->
                        <div class="section-card student-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">person</span>
                                <h4>Student Information</h4>
                            </div>
                            <div class="od-request-header">
                                <div class="student-info">
                                    <h3><?php echo htmlspecialchars($request['student_name']); ?></h3>
                                    <div class="student-details">
                                        Reg No:                                                                                                                                              <?php echo htmlspecialchars($request['student_regno']); ?> |
                                        <?php echo htmlspecialchars($request['department']); ?> |
                                        Year:                                                                                                                                        <?php echo htmlspecialchars($request['year_of_join']); ?>
                                    </div>
                                </div>
                                <span class="od-status                                                                                                                                                                   <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Section 2: Event Details -->
                        <div class="section-card event-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">event</span>
                                <h4>Event Details</h4>
                            </div>
                            <div class="event-details">
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Name:</span>
                                    <span class="event-detail-value"><?php echo htmlspecialchars($request['event_name']); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Location:</span>
                                    <span class="event-detail-value"><?php echo htmlspecialchars($request['event_location']); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Date:</span>
                                    <span class="event-detail-value"><?php echo date('M d, Y', strtotime($request['event_date'])); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Time:</span>
                                    <span class="event-detail-value"><?php echo date('h:i A', strtotime($request['event_time'])); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Duration:</span>
                                    <span class="event-detail-value"><?php echo isset($request['event_days']) ? htmlspecialchars($request['event_days']) . ' day(s)' : '1 day'; ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Requested:</span>
                                    <span class="event-detail-value"><?php echo date('M d, Y h:i A', strtotime($request['request_date'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Event Description -->
                        <div class="section-card description-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">description</span>
                                <h4>Event Description</h4>
                            </div>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($request['event_description'])); ?>
                            </div>
                        </div>

                        <!-- Section 4: Event Poster (if available) -->
                        <?php if (! empty($request['event_poster'])): ?>
                        <div class="section-card poster-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">image</span>
                                <h4>Event Poster</h4>
                            </div>
                            <div class="description-box" style="border-left-color: #17a2b8;">
                                <?php
                                    $poster_path    = '../student/uploads/posters/' . $request['event_poster'];
                                    $file_extension = strtolower(pathinfo($request['event_poster'], PATHINFO_EXTENSION));
                                ?>

                                <div style="display: flex; flex-direction: column; gap: 15px;">
                                    <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && file_exists($poster_path)): ?>
                                    <div style="text-align: center;">
                                        <img src="<?php echo htmlspecialchars($poster_path); ?>"
                                             alt="Event Poster"
                                             style="width: 100%; max-width: 300px; height: auto; border-radius: 12px; border: 3px solid #e9ecef; cursor: pointer; transition: transform 0.3s ease;"
                                             onclick="openPosterModal('<?php echo htmlspecialchars($poster_path); ?>')"
                                             onmouseover="this.style.transform='scale(1.02)'"
                                             onmouseout="this.style.transform='scale(1)'">
                                    </div>
                                    <?php endif; ?>

                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <a href="../student/view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>"
                                           target="_blank"
                                           style="color: white; background: #0c3878; text-decoration: none; font-weight: 500; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s ease;">
                                            <span class="material-symbols-outlined">visibility</span>
                                            View Full Poster
                                        </a>
                                        <a href="<?php echo htmlspecialchars($poster_path); ?>"
                                           download
                                           style="color: white; background: #28a745; text-decoration: none; font-weight: 500; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s ease;">
                                            <span class="material-symbols-outlined">download</span>
                                            Download Poster
                                        </a>
                                        <div style="text-align: center; font-size: 12px; color: #6c757d; padding: 8px; background: #f8f9fa; border-radius: 6px;">
                                            <strong>File:</strong>                                                                                                                                                                                                       <?php echo htmlspecialchars(basename($request['event_poster'])); ?><br>
                                            <strong>Type:</strong>                                                                                                                                                                                                       <?php echo strtoupper($file_extension); ?> •
                                            <strong>Size:</strong>                                                                                                                                                                                                       <?php echo file_exists($poster_path) ? round(filesize($poster_path) / 1024, 1) . ' KB' : 'Unknown'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 5: Reason for OD -->
                        <div class="section-card reason-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">help</span>
                                <h4>Reason for OD</h4>
                            </div>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                            </div>
                        </div>

                        <!-- Section 6: Previous Remarks (if any) -->
                        <?php if ($request['counselor_remarks']): ?>
                        <div class="section-card remarks-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">comment</span>
                                <h4>Your Previous Remarks</h4>
                            </div>
                            <div class="description-box" style="border-left-color: #28a745;">
                                <?php echo nl2br(htmlspecialchars($request['counselor_remarks'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 7: Review & Approval (if pending) -->
                        <?php if ($request['status'] === 'pending'): ?>
                        <div class="section-card approval-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">approval</span>
                                <h4>Review & Approval Decision</h4>
                            </div>
                            <div class="approval-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="od_id" value="<?php echo $request['id']; ?>">

                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Remarks (Optional)</label>
                                            <textarea name="counselor_remarks" class="form-textarea"
                                                      placeholder="Add your remarks or feedback..." rows="3"></textarea>
                                        </div>

                                        <div class="action-buttons">
                                            <button type="submit" name="update_od_status" value="approved"
                                                    onclick="this.form.new_status.value='approved'" class="btn btn-approve">
                                                <span class="material-symbols-outlined">check_circle</span>
                                                Approve
                                            </button>
                                            <button type="submit" name="update_od_status" value="rejected"
                                                    onclick="this.form.new_status.value='rejected'" class="btn btn-reject">
                                                <span class="material-symbols-outlined">cancel</span>
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="new_status" value="">
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">description</span>
                        <h3>No OD Requests</h3>
                        <p>No students have submitted OD requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Poster Modal -->
    <div id="posterModal" class="poster-modal">
        <div class="poster-modal-content">
            <span class="poster-modal-close" onclick="closePosterModal()">&times;</span>
            <img id="posterModalImage" src="" alt="Event Poster">
        </div>
    </div>

    <script>
        // Mobile sidebar functionality
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
            const closeSidebarBtn = document.querySelector('.close-sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });

        // Poster Modal Functions
        function openPosterModal(posterSrc) {
            const modal = document.getElementById('posterModal');
            const modalImage = document.getElementById('posterModalImage');
            modalImage.src = posterSrc;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closePosterModal() {
            const modal = document.getElementById('posterModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal when clicking outside the image
        document.getElementById('posterModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePosterModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePosterModal();
            }
        });
    </script>
</body>
</html>