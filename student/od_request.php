<?php
    session_start();

    // Include file compression utility
    require_once '../includes/FileCompressor.php';

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Migration: Ensure new columns exist for backward compatibility
    try {
        // Check if event_state column exists, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM od_requests LIKE 'event_state'");
        if ($check_column && $check_column->num_rows == 0) {
            $conn->query("ALTER TABLE od_requests ADD COLUMN event_state VARCHAR(100) DEFAULT ''");
        }

        // Check if event_district column exists, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM od_requests LIKE 'event_district'");
        if ($check_column && $check_column->num_rows == 0) {
            $conn->query("ALTER TABLE od_requests ADD COLUMN event_district VARCHAR(100) DEFAULT ''");
        }
    } catch (Exception $e) {
        // Silently continue if migration fails
    }

    // Get student data
    $username     = $_SESSION['username'];
    $student_data = null;

    $sql  = "SELECT * FROM student_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit();
    }

    // Check if student has an assigned counselor
    $counselor_info = null;
    $counselor_sql  = "SELECT tr.name as counselor_name, tr.email as counselor_email,
                            ca.assigned_date, tr.faculty_id as counselor_id, tr.id as teacher_id
                     FROM counselor_assignments ca
                     JOIN teacher_register tr ON ca.counselor_id = tr.id
                     WHERE ca.student_regno = ? AND ca.status = 'active'
                     ORDER BY ca.assigned_date DESC
                     LIMIT 1";
    $counselor_stmt = $conn->prepare($counselor_sql);
    $counselor_stmt->bind_param("s", $student_data['regno']);
    $counselor_stmt->execute();
    $counselor_result = $counselor_stmt->get_result();

    if ($counselor_result->num_rows > 0) {
        $counselor_info = $counselor_result->fetch_assoc();
    }
    $counselor_stmt->close();

    $student_state    = isset($student_data['state']) ? trim($student_data['state']) : '';
    $student_district = isset($student_data['district']) ? trim($student_data['district']) : '';
    $student_location = trim($student_state . ($student_state && $student_district ? ', ' : '') . $student_district);

    $message      = '';
    $message_type = '';

    // Check for success message from session
    if (isset($_SESSION['od_success']) && $_SESSION['od_success'] === true) {
        $message      = "OD request submitted successfully! Your request is now pending approval from your class counselor.";
        $message_type = 'success';
        unset($_SESSION['od_success']); // Remove the session variable so it doesn't show again
    }

    // Handle OD request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_od_request'])) {
        if (! $counselor_info) {
            $message      = "You don't have an assigned class counselor. Please contact the administration.";
            $message_type = 'error';
        } else {
            // Create OD requests table if not exists
            $create_table = "CREATE TABLE IF NOT EXISTS od_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_regno VARCHAR(50) NOT NULL,
                counselor_id INT NOT NULL,
                event_name VARCHAR(255) NOT NULL,
                event_description TEXT NOT NULL,
                event_state VARCHAR(100) NOT NULL,
                event_district VARCHAR(100) NOT NULL,
                event_date DATE NOT NULL,
                event_time TIME NOT NULL,
                event_days VARCHAR(20) NOT NULL,
                event_poster VARCHAR(255) NULL,
                reason TEXT NOT NULL,
                group_members TEXT NULL,
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                counselor_remarks TEXT,
                response_date TIMESTAMP NULL,
                FOREIGN KEY (counselor_id) REFERENCES teacher_register(id) ON DELETE CASCADE
            )";
            $conn->query($create_table);

            // Ensure group_members column exists (migration for existing tables)
            $check_column = $conn->query("SHOW COLUMNS FROM od_requests LIKE 'group_members'");
            if ($check_column && $check_column->num_rows == 0) {
                $conn->query("ALTER TABLE od_requests ADD COLUMN group_members TEXT NULL AFTER reason");
            }

            // Insert OD request
            $event_name        = trim($_POST['event_name']);
            $event_description = trim($_POST['event_description']);
            $event_state       = trim($_POST['event_state']);
            $event_district    = trim($_POST['event_district']);
            $event_date        = $_POST['event_date'];
            $event_time        = $_POST['event_time'];
            $event_days        = $_POST['event_days'];
            $reason            = trim($_POST['reason']);

            // Validate event description word count (50 words max)
            $word_count = str_word_count($event_description);
            if ($word_count > 50) {
                $error_message = "Event description must not exceed 50 words. Current: $word_count words.";
            }

            // Validate reason word count (50 words max)
            $reason_word_count = str_word_count($reason);
            if ($reason_word_count > 50) {
                $error_message = "Reason for OD must not exceed 50 words. Current: $reason_word_count words.";
            }

            // Validate event location fields
            if (empty($event_state) || empty($event_district)) {
                $message      = "Please provide both Event State and Event District.";
                $message_type = 'error';
            }

            // Handle file upload with compression
            $poster_filename = null;
            if (isset($_FILES['event_poster']) && $_FILES['event_poster']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/posters/';

                // Create directory if it doesn't exist
                if (! is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_info      = pathinfo($_FILES['event_poster']['name']);
                $file_extension = strtolower($file_info['extension']);

                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                if (in_array($file_extension, $allowed_types)) {
                    // Validate file size (5MB max)
                    if ($_FILES['event_poster']['size'] <= 5 * 1024 * 1024) {
                        $base_filename = $upload_dir . 'poster_' . $student_data['regno'] . '_' . time();

                        // Compress and save the file
                        $compression_result = FileCompressor::compressUploadedFile(
                            $_FILES['event_poster']['tmp_name'],
                            $base_filename,
                            $file_extension,
                            85// 85% quality
                        );

                        if ($compression_result['success']) {
                            $poster_filename = basename($compression_result['path']);
                            // Log compression savings
                            error_log(sprintf(
                                "OD Poster compressed: %s -> %s (%.2f%% saved)",
                                FileCompressor::formatSize($compression_result['original_size']),
                                FileCompressor::formatSize($compression_result['compressed_size']),
                                $compression_result['savings_percent']
                            ));
                        } else {
                            $message         = "Error uploading poster file.";
                            $message_type    = 'error';
                            $poster_filename = null;
                        }
                    } else {
                        $message      = "Poster file size must be less than 5MB.";
                        $message_type = 'error';
                    }
                } else {
                    $message      = "Please upload a valid image file (JPG, PNG) or PDF.";
                    $message_type = 'error';
                }
            }

            // Only proceed with database insert if no file upload errors
            if (empty($message)) {
                // Handle group members
                $group_members = '';
                if (isset($_POST['group_members']) && is_array($_POST['group_members'])) {
                    // Filter out empty values and trim whitespace
                    $members = array_filter(array_map('trim', $_POST['group_members']));
                    if (! empty($members)) {
                        $group_members = implode(',', $members);
                    }
                }

                $insert_sql  = "INSERT INTO od_requests (student_regno, counselor_id, event_name, event_description, event_state, event_district, event_date, event_time, event_days, event_poster, reason, group_members) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sissssssssss", $student_data['regno'], $counselor_info['teacher_id'], $event_name, $event_description, $event_state, $event_district, $event_date, $event_time, $event_days, $poster_filename, $reason, $group_members);

                if ($insert_stmt->execute()) {
                    $_SESSION['od_success'] = true;
                    $insert_stmt->close();
                    $conn->close();
                    header("Location: od_request.php");
                    exit();
                } else {
                    $message      = "Error submitting OD request: " . $conn->error;
                    $message_type = 'error';
                }
                $insert_stmt->close();
            }
        }
    }

    // Get student's OD requests (including those where they are a group member)
    $od_requests_sql = "SELECT * FROM od_requests
                         WHERE student_regno = ?
                         OR FIND_IN_SET(?, REPLACE(group_members, ',', ','))
                         ORDER BY request_date DESC";
    $od_requests_stmt = $conn->prepare($od_requests_sql);
    $od_requests_stmt->bind_param("ss", $student_data['regno'], $student_data['regno']);
    $od_requests_stmt->execute();
    $od_requests_result = $od_requests_stmt->get_result();
    $od_requests_stmt->close();

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
    <title>OD Request - Event Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="asserts/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        .od-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .od-form-card, .od-status-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-input[type="file"] {
            padding: 8px 12px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .form-input[type="file"]:hover {
            background: #e9ecef;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
            animation: slideInSuccess 0.5s ease-out;
        }

        @keyframes slideInSuccess {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .counselor-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #28a745;
            margin-bottom: 25px;
        }

        .counselor-info.no-counselor {
            background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
            border-left-color: #ffc107;
        }

        .counselor-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 10px;
        }

        .counselor-info.no-counselor .counselor-title {
            color: #856404;
        }

        .counselor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .od-request-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #0c3878;
        }

        .od-request-item.pending {
            border-left-color: #ffc107;
        }

        .od-request-item.approved {
            border-left-color: #28a745;
        }

        .od-request-item.rejected {
            border-left-color: #dc3545;
        }

        .od-request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .od-event-name {
            font-size: 16px;
            font-weight: 600;
            color: #0c3878;
        }

        .od-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .od-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .od-status.approved {
            background: #d4edda;
            color: #155724;
        }

        .od-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .od-details {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

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
                overflow-y: auto !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 10001 !important;
            }

            body.sidebar-open {
                overflow: hidden;
                position: fixed;
                width: 100%;
                height: 100%;
            }

            .main {
                width: 100% !important;
                max-width: 100vw;
                padding: 80px 15px 20px 15px;
                margin: 0 !important;
                grid-area: main;
                box-sizing: border-box;
                overflow-x: hidden;
            }

            .od-container {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .od-form-card, .od-status-card {
                padding: 20px 15px;
                border-radius: 12px;
                margin-bottom: 15px;
            }

            .card-title {
                font-size: 18px;
                margin-bottom: 20px;
            }

            .form-input, .form-select, .form-textarea {
                width: 100%;
                box-sizing: border-box;
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
                padding: 14px 20px;
            }

            .counselor-info {
                padding: 15px;
                margin-bottom: 20px;
            }

            .od-request-item {
                padding: 15px;
                margin-bottom: 12px;
            }

            .od-request-header {
                flex-direction: column;
                align-items: start;
                gap: 8px;
            }

            .od-event-name {
                font-size: 15px;
                margin-bottom: 5px;
            }

            .od-details {
                font-size: 13px;
                line-height: 1.5;
            }

            .od-status {
                align-self: flex-start;
            }

            .message {
                padding: 12px 15px;
                margin-bottom: 15px;
                font-size: 14px;
            }

            /* Fix button layout on mobile */
            .od-request-item .btn {
                width: auto;
                flex: 1;
                min-width: 120px;
                font-size: 11px;
                padding: 6px 10px;
            }

            .od-request-item [style*="display: flex"] {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 70px 10px 15px 10px;
            }

            .od-form-card, .od-status-card {
                padding: 15px 10px;
                border-radius: 10px;
            }

            .card-title {
                font-size: 16px;
                margin-bottom: 15px;
            }

            .form-input, .form-select, .form-textarea {
                padding: 10px 12px;
                font-size: 16px;
            }

            .btn {
                padding: 12px 16px;
                font-size: 14px;
            }

            .counselor-info {
                padding: 12px;
            }

            .od-request-item {
                padding: 12px;
            }

            .od-event-name {
                font-size: 14px;
            }

            .od-details {
                font-size: 12px;
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
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200px">
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Student Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                <div class="student-regno"><?php echo htmlspecialchars($student_data['regno']); ?></div>
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
                        <a href="student_register.php" class="nav-link">
                            <span class="material-symbols-outlined">add_circle</span>
                            Register Event
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="student_participations.php" class="nav-link">
                            <span class="material-symbols-outlined">event_note</span>
                            My Participations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="internship_submission.php" class="nav-link">
                            <span class="material-symbols-outlined">work</span>
                            Internship Submission
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_request.php" class="nav-link active">
                            <span class="material-symbols-outlined">person_raised_hand</span>
                            OD Request
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

            <div class="od-container">
                <!-- OD Request Form -->
                <div class="od-form-card">
                    <div class="card-title">
                        <span class="material-symbols-outlined">person_raised_hand</span>
                        Request OD for Event Participation
                    </div>

                    <!-- Counselor Information -->
                    <?php if ($counselor_info): ?>
                    <div class="counselor-info">
                        <div class="counselor-title">
                            <span class="material-symbols-outlined">supervisor_account</span>
                            Your Class Counselor
                        </div>
                        <div class="counselor-name"><?php echo htmlspecialchars($counselor_info['counselor_name']); ?></div>
                        <div style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                            ID:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo htmlspecialchars($counselor_info['counselor_id']); ?> |
                            <?php echo htmlspecialchars($counselor_info['counselor_email']); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="counselor-info no-counselor">
                        <div class="counselor-title">
                            <span class="material-symbols-outlined">warning</span>
                            No Class Counselor Assigned
                        </div>
                        <p style="margin: 0; color: #856404;">Please contact the administration to get a class counselor assigned.</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($counselor_info): ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Event Name *</label>
                                <input type="text" name="event_name" class="form-input" required
                                       placeholder="Enter event name">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event State *</label>
                                <select id="eventState" name="event_state" class="form-select" required>
                                    <option value="" disabled selected>Select State</option>
                                    <option value="Andhra Pradesh">Andhra Pradesh</option>
                                    <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                    <option value="Assam">Assam</option>
                                    <option value="Bihar">Bihar</option>
                                    <option value="Chhattisgarh">Chhattisgarh</option>
                                    <option value="Goa">Goa</option>
                                    <option value="Gujarat">Gujarat</option>
                                    <option value="Haryana">Haryana</option>
                                    <option value="Himachal Pradesh">Himachal Pradesh</option>
                                    <option value="Jharkhand">Jharkhand</option>
                                    <option value="Karnataka">Karnataka</option>
                                    <option value="Kerala">Kerala</option>
                                    <option value="Madhya Pradesh">Madhya Pradesh</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Manipur">Manipur</option>
                                    <option value="Meghalaya">Meghalaya</option>
                                    <option value="Mizoram">Mizoram</option>
                                    <option value="Nagaland">Nagaland</option>
                                    <option value="Odisha">Odisha</option>
                                    <option value="Punjab">Punjab</option>
                                    <option value="Rajasthan">Rajasthan</option>
                                    <option value="Sikkim">Sikkim</option>
                                    <option value="Tamil Nadu">Tamil Nadu</option>
                                    <option value="Telangana">Telangana</option>
                                    <option value="Tripura">Tripura</option>
                                    <option value="Uttar Pradesh">Uttar Pradesh</option>
                                    <option value="Uttarakhand">Uttarakhand</option>
                                    <option value="West Bengal">West Bengal</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event District *</label>
                                <select id="eventDistrict" name="event_district" class="form-select" required disabled>
                                    <option value="" disabled selected>Select District</option>
                                </select>
                                <small style="display: block; margin-top: 5px; color: #666; font-size: 0.85rem;">Please select a state first</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Date *</label>
                                <input type="date" name="event_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Time *</label>
                                <input type="time" name="event_time" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Number of Days *</label>
                                <select name="event_days" class="form-select" required>
                                    <option value="">Select number of days</option>
                                    <option value="1 hour">1 Hour</option>
                                    <option value="2 hours">2 Hours</option>
                                    <option value="3 hours">3 Hours</option>
                                    <option value="4 hours">4 Hours</option>
                                    <option value="5 hours">5 Hours</option>
                                    <option value="6 hours">6 Hours</option>
                                    <option value="7 hours">7 Hours</option>
                                    <option value="1">1 Day</option>
                                    <option value="2">2 Days</option>
                                    <option value="3">3 Days</option>
                                    <option value="4">4 Days</option>
                                    <option value="5">5 Days</option>
                                    <option value="6">6 Days</option>
                                    <option value="7">7 Days</option>
                                    <option value="other">More than 7 days</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">
                                    Event Description *
                                    <span style="font-size: 13px; color: #6b7280;">(Max 50 words)</span>
                                </label>
                                <textarea name="event_description" id="eventDescription" class="form-textarea" required
                                          placeholder="Describe what the event is about, activities involved, etc."
                                          oninput="countWords(this, 50, 'wordCount')"></textarea>
                                <div style="text-align: right; margin-top: 5px;">
                                    <span id="wordCount" style="font-size: 13px; color: #6b7280;">0 / 50 words</span>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">
                                    Group Members (Optional - For Group OD)
                                    <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; color: #17a2b8;">group</span>
                                </label>
                                <div id="groupMembersContainer" style="margin-bottom: 10px;">
                                    <!-- Group member inputs will be added here -->
                                </div>
                                <button type="button" onclick="addGroupMember()" class="btn" style="background: #17a2b8; width: auto; padding: 8px 16px; font-size: 14px; margin-bottom: 10px;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">add</span>
                                    Add Group Member
                                </button>
                                <small style="color: #6c757d; font-size: 12px; display: block;">
                                    If this is a group OD request, add registration numbers of other group members. They will also be able to view and download the OD letter.
                                </small>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Event Poster</label>
                                <input type="file" name="event_poster" class="form-input" accept="image/*,.pdf">
                                <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                    Upload event poster/flyer (JPG, PNG, PDF - Max 5MB). This helps your counselor understand the event better.
                                </small>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">
                                    Reason for OD *
                                    <span style="font-size: 13px; color: #6b7280;">(Max 50 words)</span>
                                </label>
                                <textarea name="reason" id="reasonForOD" class="form-textarea" required
                                          placeholder="Explain why you need OD for this event participation"
                                          oninput="countWords(this, 50, 'reasonWordCount')"></textarea>
                                <div style="text-align: right; margin-top: 5px;">
                                    <span id="reasonWordCount" style="font-size: 13px; color: #6b7280;">0 / 50 words</span>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" name="submit_od_request" class="btn btn-primary">
                                <span class="material-symbols-outlined">send</span>
                                Submit OD Request
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Dashboard
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- OD Request Status -->
                <div class="od-status-card">
                    <div class="card-title">
                        <span class="material-symbols-outlined">history</span>
                        My OD Requests
                    </div>

                    <?php if ($od_requests_result->num_rows > 0): ?>
                        <?php while ($request = $od_requests_result->fetch_assoc()): ?>
                        <div class="od-request-item<?php echo $request['status']; ?>">
                            <div class="od-request-header">
                                <div class="od-event-name"><?php echo htmlspecialchars($request['event_name']); ?></div>
                                <span class="od-status                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                            <div class="od-details">
                                <strong>Date:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo date('M d, Y', strtotime($request['event_date'])); ?> at<?php echo date('h:i A', strtotime($request['event_time'])); ?><br>
                                <strong>Duration:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo isset($request['event_days']) ? htmlspecialchars($request['event_days']) . ' day(s)' : 'Not specified'; ?><br>
                                <strong>Location:</strong>
                                <?php
                                    // Handle backward compatibility for old records
                                    if (! empty($request['event_state']) && ! empty($request['event_district'])) {
                                        echo htmlspecialchars($request['event_state']) . ', ' . htmlspecialchars($request['event_district']);
                                    } elseif (! empty($request['event_location'])) {
                                        echo htmlspecialchars($request['event_location']);
                                    } else {
                                        echo 'Location not specified';
                                }
                                ?><br>

                                <?php if (! empty($request['group_members'])): ?>
                                <?php
                                    $group_regnos    = array_filter(array_map('trim', explode(',', $request['group_members'])));
                                    $is_group_member = in_array($student_data['regno'], $group_regnos);
                                ?>
                                <div style="margin: 10px 0; padding: 10px; background: #e7f3ff; border-left: 3px solid #17a2b8; border-radius: 4px;">
                                    <strong style="color: #17a2b8;">
                                        <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle;">group</span>
                                        Group OD                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $is_group_member ? '(You are a member)' : ''; ?>
                                    </strong><br>
                                    <small style="color: #666; font-size: 12px;">
                                        <?php echo count($group_regnos); ?> additional member(s):
                                        <?php echo htmlspecialchars(implode(', ', array_slice($group_regnos, 0, 3))); ?>
                                        <?php if (count($group_regnos) > 3): ?>
                                        and<?php echo count($group_regnos) - 3; ?> more
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <?php endif; ?>

                                <?php if (! empty($request['event_poster'])): ?>
                                <div style="margin: 10px 0;">
                                    <strong>Event Poster:</strong><br>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 8px;">
                                        <?php
                                            $poster_path    = 'uploads/posters/' . $request['event_poster'];
                                            $file_extension = strtolower(pathinfo($request['event_poster'], PATHINFO_EXTENSION));
                                        ?>

                                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && file_exists($poster_path)): ?>
                                        <div style="flex-shrink: 0;">
                                            <img src="<?php echo htmlspecialchars($poster_path); ?>"
                                                 alt="Event Poster Thumbnail"
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef; cursor: pointer;"
                                                 onclick="window.open('view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>', '_blank')">
                                        </div>
                                        <?php endif; ?>

                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <a href="view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>"
                                               target="_blank"
                                               style="color: #0c3878; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 5px;">
                                                <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                                View Poster
                                            </a>
                                            <small style="color: #6c757d; font-size: 11px;">
                                                <?php echo htmlspecialchars($request['event_poster']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <strong>Requested:</strong><?php echo date('M d, Y h:i A', strtotime($request['request_date'])); ?><br>
                                <?php if ($request['status'] !== 'pending' && $request['counselor_remarks']): ?>
                                <strong>Counselor Remarks:</strong><?php echo htmlspecialchars($request['counselor_remarks']); ?><br>
                                <?php endif; ?>
                                <?php if ($request['status'] === 'approved'): ?>
                                <?php
                                    // Extract event type from event name or description (smart detection)
                                    $event_name_lower = strtolower($request['event_name']);
                                    $event_type       = 'Conference'; // default

                                    if (strpos($event_name_lower, 'workshop') !== false) {
                                        $event_type = 'Workshop';
                                    } elseif (strpos($event_name_lower, 'seminar') !== false) {
                                        $event_type = 'Seminar';
                                    } elseif (strpos($event_name_lower, 'webinar') !== false) {
                                        $event_type = 'Webinar';
                                    } elseif (strpos($event_name_lower, 'competition') !== false) {
                                        $event_type = 'Competition';
                                    } elseif (strpos($event_name_lower, 'hackathon') !== false) {
                                        $event_type = 'Hackathon';
                                    } elseif (strpos($event_name_lower, 'training') !== false) {
                                        $event_type = 'Training';
                                    } elseif (strpos($event_name_lower, 'symposium') !== false) {
                                        $event_type = 'Symposium';
                                    }

                                    // Prepare URL parameters for auto-fill (with backward compatibility)
                                    $url_params = [
                                        'event' => $request['event_name'],
                                        'type'  => $event_type,
                                        'date'  => $request['event_date'],
                                    ];

                                    // Add student's department to auto-fill
                                    if (isset($student_data['department']) && ! empty($student_data['department'])) {
                                        $url_params['dept'] = $student_data['department'];
                                    }

                                    // Handle state and district for new records, fallback to location for old records
                                    if (! empty($request['event_state']) && ! empty($request['event_district'])) {
                                        $url_params['org']      = $request['event_state'] . ' State';
                                        $url_params['state']    = $request['event_state'];
                                        $url_params['district'] = $request['event_district'];
                                    } elseif (! empty($request['event_location'])) {
                                        $url_params['org'] = $request['event_location'];
                                    } else {
                                        $url_params['org'] = 'Event Organization';
                                    }

                                    $register_url = 'student_register.php?' . http_build_query($url_params);
                                ?>
                                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="<?php echo htmlspecialchars($register_url); ?>" class="btn btn-primary" style="font-size: 12px; padding: 8px 15px;">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">add_circle</span>
                                        Register for Event
                                    </a>
                                    <a href="download_od_letter.php?od_id=<?php echo $request['id']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 15px;" target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                                        Download OD Letter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined">description</span>
                            <p>No OD requests submitted yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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

        // State-District mapping
        const stateDistricts = {
            'Andhra Pradesh': ['Anantapur', 'Chittoor', 'East Godavari', 'Guntur', 'Kadapa', 'Krishna', 'Kurnool', 'Nellore', 'Prakasam', 'Srikakulam', 'Visakhapatnam', 'Vizianagaram', 'West Godavari'],
            'Arunachal Pradesh': ['Anjaw', 'Changlang', 'Dibang Valley', 'East Kameng', 'East Siang', 'Kra Daadi', 'Kurung Kumey', 'Lohit', 'Longding', 'Lower Dibang Valley', 'Lower Siang', 'Lower Subansiri', 'Papum Pare', 'Siang', 'Upper Dibang Valley', 'Upper Siang', 'Upper Subansiri', 'West Kameng', 'West Siang'],
            'Assam': ['Baksa', 'Barpeta', 'Biswanath', 'Bongaigaon', 'Cachar', 'Charaideo', 'Chirang', 'Darrang', 'Dhemaji', 'Dima Hasao', 'Dibrugarh', 'Goalpara', 'Golaghat', 'Hailakandi', 'Hojai', 'Jorhat', 'Kamrup', 'Kamrup Metropolitan', 'Karbi Anglong', 'Karimganj', 'Kokrajhar', 'Lakhimpur', 'Majuli', 'Morigaon', 'Nagaon', 'Nalbari', 'Sonitpur', 'South Salmara-Mankachar', 'Sibsagar', 'Sualkuchi'],
            'Bihar': ['Araria', 'Arwal', 'Aurangabad', 'Banka', 'Begusarai', 'Bhagalpur', 'Bhojpur', 'Buxar', 'Darbhanga', 'East Champaran', 'Gaya', 'Gopalganj', 'Jamui', 'Jehanabad', 'Kaimur', 'Katihar', 'Khagaria', 'Kishanganj', 'Lakhisarai', 'Madhepura', 'Madhubani', 'Munger', 'Muzaffarpur', 'Nalanda', 'Nawada', 'Patna', 'Purnia', 'Rohtas', 'Saharsa', 'Samastipur', 'Saran', 'Sheikhpura', 'Sheohar', 'Sitamarhi', 'Siwan', 'Supaul', 'Vaishali', 'West Champaran'],
            'Chhattisgarh': ['Balod', 'Baloda Bazar', 'Balrampur', 'Bastar', 'Bemetara', 'Bijapur', 'Bilaspur', 'Dantewada', 'Dhamtari', 'Durg', 'Gariaband', 'Janjgir-Champa', 'Jashpur', 'Kabirdham', 'Kanker', 'Kondagaon', 'Korba', 'Koriya', 'Mahasamund', 'Mungeli', 'Narayanpur', 'Raigarh', 'Raipur', 'Rajnandgaon', 'Sukma', 'Surajpur', 'Surguja'],
            'Goa': ['North Goa', 'South Goa'],
            'Gujarat': ['Ahmedabad', 'Amreli', 'Anand', 'Aravalli', 'Banaskantha', 'Bharuch', 'Bhavnagar', 'Botad', 'Chhota Udaipur', 'Dahod', 'Dang', 'Devbhoomi Dwarka', 'Gandhinagar', 'Gir Somnath', 'Jamnagar', 'Junagadh', 'Kheda', 'Kutch', 'Mahisagar', 'Mehsana', 'Morbi', 'Narmada', 'Navsari', 'Panchmahal', 'Patan', 'Porbandar', 'Rajkot', 'Sabarkantha', 'Surat', 'Surendranagar', 'Tapi', 'Vadodara', 'Valsad'],
            'Haryana': ['Ambala', 'Bhiwani', 'Charkhi Dadri', 'Faridabad', 'Fatehabad', 'Gurgaon', 'Hisar', 'Jhajjar', 'Jind', 'Kaithal', 'Karnal', 'Kurukshetra', 'Mahendragarh', 'Nuh', 'Palwal', 'Panchkula', 'Panipat', 'Rewari', 'Rohtak', 'Sirsa', 'Sonipat', 'Yamunanagar'],
            'Himachal Pradesh': ['Bilaspur', 'Chamba', 'Hamirpur', 'Kangra', 'Kinnaur', 'Kullu', 'Lahaul Spiti', 'Mandi', 'Shimla', 'Sirmaur', 'Solan', 'Una'],
            'Jharkhand': ['Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa', 'Giridih', 'Godda', 'Gumla', 'Hazaribag', 'Jamtara', 'Khunti', 'Koderma', 'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahibganj', 'Seraikela Kharsawan', 'Simdega', 'West Singhbhum'],
            'Karnataka': ['Bagalkot', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru Urban', 'Bidar', 'Chamarajanagar', 'Chikballapur', 'Chikkamagaluru', 'Chitradurga', 'Dakshina Kannada', 'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri', 'Kalaburagi', 'Kodagu', 'Kolar', 'Koppal', 'Mandya', 'Mysuru', 'Raichur', 'Ramanagara', 'Shivamogga', 'Tumakuru', 'Udupi', 'Uttara Kannada', 'Vijayapura', 'Yadgir'],
            'Kerala': ['Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam', 'Kozhikode', 'Malappuram', 'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram', 'Thrissur', 'Wayanad'],
            'Madhya Pradesh': ['Agar Malwa', 'Alirajpur', 'Anuppur', 'Ashoknagar', 'Balaghat', 'Barwani', 'Betul', 'Bhind', 'Bhopal', 'Burhanpur', 'Chhatarpur', 'Chhindwara', 'Damoh', 'Datia', 'Dewas', 'Dhar', 'Dindori', 'Guna', 'Gwalior', 'Harda', 'Hoshangabad', 'Indore', 'Jabalpur', 'Jhabua', 'Katni', 'Khandwa', 'Khargone', 'Mandla', 'Mandsaur', 'Morena', 'Narsinghpur', 'Neemuch', 'Panna', 'Raisen', 'Rajgarh', 'Ratlam', 'Rewa', 'Sagar', 'Satna', 'Sehore', 'Seoni', 'Shahdol', 'Shajapur', 'Sheopur', 'Shivpuri', 'Sidhi', 'Singrauli', 'Tikamgarh', 'Ujjain', 'Umaria', 'Vidisha'],
            'Maharashtra': ['Ahmednagar', 'Akola', 'Amravati', 'Aurangabad', 'Beed', 'Bhandara', 'Buldhana', 'Chandrapur', 'Dhule', 'Gadchiroli', 'Gondia', 'Hingoli', 'Jalgaon', 'Jalna', 'Kolhapur', 'Latur', 'Mumbai City', 'Mumbai Suburban', 'Nagpur', 'Nanded', 'Nandurbar', 'Nashik', 'Osmanabad', 'Palghar', 'Parbhani', 'Pune', 'Raigad', 'Ratnagiri', 'Sangli', 'Satara', 'Sindhudurg', 'Solapur', 'Thane', 'Wardha', 'Washim', 'Yavatmal'],
            'Manipur': ['Bishnupur', 'Chandel', 'Churachandpur', 'Imphal East', 'Imphal West', 'Jiribam', 'Kakching', 'Kamjong', 'Kangpokpi', 'Noney', 'Pherzawl', 'Senapati', 'Tamenglong', 'Tengnoupal', 'Thoubal', 'Ukhrul'],
            'Meghalaya': ['East Garo Hills', 'East Jaintia Hills', 'East Khasi Hills', 'North Garo Hills', 'Ri Bhoi', 'South Garo Hills', 'South West Garo Hills', 'South West Khasi Hills', 'West Garo Hills', 'West Jaintia Hills', 'West Khasi Hills'],
            'Mizoram': ['Aizawl', 'Champhai', 'Kolasib', 'Lawngtlai', 'Lunglei', 'Mamit', 'Saiha', 'Serchhip'],
            'Nagaland': ['Chumoukedima', 'Dimapur', 'Kiphire', 'Kohima', 'Longleng', 'Mokokchung', 'Mon', 'Peren', 'Phek', 'Tuensang', 'Wokha', 'Zunheboto'],
            'Odisha': ['Angul', 'Balangir', 'Balasore', 'Bargarh', 'Bhadrak', 'Boudh', 'Cuttack', 'Deogarh', 'Dhenkanal', 'Gajapati', 'Ganjam', 'Jagatsinghpur', 'Jajpur', 'Jharsuguda', 'Kalahandi', 'Kandhamal', 'Kendrapara', 'Kendujhar', 'Khordha', 'Koraput', 'Malkangiri', 'Mayurbhanj', 'Nabarangpur', 'Nayagarh', 'Nuapada', 'Puri', 'Rayagada', 'Sambalpur', 'Subarnapur', 'Sundargarh'],
            'Punjab': ['Amritsar', 'Barnala', 'Bathinda', 'Faridkot', 'Fatehgarh Sahib', 'Fazilka', 'Firozpur', 'Gurdaspur', 'Hoshiarpur', 'Jalandhar', 'Kapurthala', 'Ludhiana', 'Mansa', 'Moga', 'Mohali', 'Muktsar', 'Pathankot', 'Patiala', 'Rupnagar', 'Sangrur', 'SBS Nagar', 'Shaheed Bhagat Singh Nagar', 'Tarn Taran'],
            'Rajasthan': ['Ajmer', 'Alwar', 'Banswara', 'Baran', 'Barmer', 'Bharatpur', 'Bhilwara', 'Bikaner', 'Bundi', 'Chittorgarh', 'Churu', 'Dausa', 'Dholpur', 'Dungarpur', 'Hanumangarh', 'Jaipur', 'Jaisalmer', 'Jalore', 'Jhalawar', 'Jhunjhunu', 'Jodhpur', 'Karauli', 'Kota', 'Nagaur', 'Pali', 'Pratapgarh', 'Rajsamand', 'Sawai Madhopur', 'Sikar', 'Sirohi', 'Sri Ganganagar', 'Tonk', 'Udaipur'],
            'Sikkim': ['East Sikkim', 'North Sikkim', 'South Sikkim', 'West Sikkim'],
            'Tamil Nadu': ['Ariyalur', 'Chengalpattu', 'Chennai', 'Coimbatore', 'Cuddalore', 'Dharmapuri', 'Dindigul', 'Erode', 'Kallakurichi', 'Kanchipuram', 'Kanyakumari', 'Karur', 'Krishnagiri', 'Madurai', 'Mayiladuthurai', 'Nagapattinam', 'Namakkal', 'Nilgiris', 'Perambalur', 'Pudukkottai', 'Ramanathapuram', 'Ranipet', 'Salem', 'Sivaganga', 'Tenkasi', 'Thanjavur', 'Theni', 'Thoothukudi', 'Tiruchirappalli', 'Tirunelveli', 'Tirupathur', 'Tiruppur', 'Tiruvallur', 'Tiruvannamalai', 'Tiruvarur', 'Vellore', 'Viluppuram', 'Virudhunagar'],
            'Telangana': ['Adilabad', 'Bhadradri Kothagudem', 'Hyderabad', 'Jagtial', 'Jangaon', 'Jayashankar', 'Jogulamba Gadwal', 'Kamareddy', 'Karimnagar', 'Khammam', 'Komaram Bheem', 'Mahabubabad', 'Mahbubnagar', 'Mancherial', 'Medak', 'Medchal', 'Mulugu', 'Nagarkurnool', 'Nalgonda', 'Narayanpet', 'Nirmal', 'Nizamabad', 'Peddapalli', 'Rajanna Sircilla', 'Ranga Reddy', 'Sangareddy', 'Siddipet', 'Suryapet', 'Vikarabad', 'Wanaparthy', 'Warangal Rural', 'Warangal Urban', 'Yadadri Bhuvanagiri'],
            'Tripura': ['Dhalai', 'Gomati', 'Khowai', 'North Tripura', 'Sepahijala', 'South Tripura', 'Unakoti', 'West Tripura'],
            'Uttar Pradesh': ['Agra', 'Aligarh', 'Ambedkar Nagar', 'Amethi', 'Amroha', 'Auraiya', 'Ayodhya', 'Azamgarh', 'Baghpat', 'Bahraich', 'Ballia', 'Balrampur', 'Banda', 'Barabanki', 'Bareilly', 'Basti', 'Bhadohi', 'Bijnor', 'Budaun', 'Bulandshahr', 'Chandauli', 'Chitrakoot', 'Deoria', 'Etah', 'Etawah', 'Farrukhabad', 'Fatehpur', 'Firozabad', 'Gautam Buddha Nagar', 'Ghaziabad', 'Ghazipur', 'Gonda', 'Gorakhpur', 'Hamirpur', 'Hapur', 'Hardoi', 'Hathras', 'Jalaun', 'Jaunpur', 'Jhansi', 'Kannauj', 'Kanpur Dehat', 'Kanpur Nagar', 'Kasganj', 'Kaushambi', 'Kheri', 'Kushinagar', 'Lalitpur', 'Lucknow', 'Maharajganj', 'Mahoba', 'Mainpuri', 'Mathura', 'Mau', 'Meerut', 'Mirzapur', 'Moradabad', 'Muzaffarnagar', 'Pilibhit', 'Pratapgarh', 'Prayagraj', 'Raebareli', 'Rampur', 'Saharanpur', 'Sambhal', 'Sant Kabir Nagar', 'Shahjahanpur', 'Shamli', 'Shravasti', 'Siddharthnagar', 'Sitapur', 'Sonbhadra', 'Sultanpur', 'Unnao', 'Varanasi'],
            'Uttarakhand': ['Almora', 'Bageshwar', 'Chamoli', 'Champawat', 'Dehradun', 'Haridwar', 'Nainital', 'Pauri Garhwal', 'Pithoragarh', 'Rudraprayag', 'Tehri Garhwal', 'Udham Singh Nagar', 'Uttarkashi'],
            'West Bengal': ['Alipurduar', 'Bankura', 'Birbhum', 'Cooch Behar', 'Dakshin Dinajpur', 'Darjeeling', 'Hooghly', 'Howrah', 'Jalpaiguri', 'Jhargram', 'Kalimpong', 'Kolkata', 'Malda', 'Murshidabad', 'Nadia', 'North 24 Parganas', 'Paschim Bardhaman', 'Paschim Medinipur', 'Purba Bardhaman', 'Purba Medinipur', 'Purulia', 'South 24 Parganas', 'Uttar Dinajpur']
        };

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            const sidebar = document.getElementById('sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // File size validation for event poster
            const posterInput = document.querySelector('input[name="event_poster"]');
            if (posterInput) {
                posterInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                        if (file.size > maxSize) {
                            alert('❌ File size exceeds 5MB limit!\n\nPlease select a file smaller than 5MB.\nCurrent file size: ' + (file.size / (1024 * 1024)).toFixed(2) + ' MB');
                            e.target.value = ''; // Clear the file input
                            return false;
                        }
                    }
                });
            }

            // State-District cascading dropdown
            const stateSelect = document.getElementById('eventState');
            const districtSelect = document.getElementById('eventDistrict');

            if (stateSelect && districtSelect) {
                stateSelect.addEventListener('change', function() {
                    const selectedState = this.value;
                    districtSelect.innerHTML = '<option value="" disabled selected>Select District</option>';

                    if (selectedState && stateDistricts[selectedState]) {
                        stateDistricts[selectedState].forEach(function(district) {
                            const option = document.createElement('option');
                            option.value = district;
                            option.textContent = district;
                            districtSelect.appendChild(option);
                        });
                        districtSelect.disabled = false;
                    } else {
                        districtSelect.disabled = true;
                    }
                });
            }

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                // Show popup for OD submission success
                    setTimeout(function() {
                        alert('✅ OD Request Submitted Successfully!\n\nYour On Duty (OD) request has been submitted and is now pending approval from your class counselor. You will be notified once your request is reviewed.');
                    }, 500);

                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });

        // Group Members Management
        let groupMemberCount = 0;

        function addGroupMember() {
            groupMemberCount++;
            const container = document.getElementById('groupMembersContainer');

            const memberDiv = document.createElement('div');
            memberDiv.className = 'group-member-input';
            memberDiv.id = `group-member-${groupMemberCount}`;
            memberDiv.style.cssText = 'display: flex; gap: 10px; align-items: center; margin-bottom: 10px; background: #f8f9fa; padding: 12px; border-radius: 8px; border: 1px solid #dee2e6;';

            memberDiv.innerHTML = `
                <span class="material-symbols-outlined" style="color: #17a2b8; font-size: 20px;">person</span>
                <input type="text"
                       name="group_members[]"
                       class="form-input"
                       placeholder="Enter registration number"
                       style="flex: 1; margin: 0; padding: 8px 12px;"
                       pattern="[A-Za-z0-9]+"
                       title="Registration number should contain only letters and numbers">
                <button type="button"
                        onclick="removeGroupMember(${groupMemberCount})"
                        style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: all 0.3s ease;"
                        onmouseover="this.style.background='#c82333'"
                        onmouseout="this.style.background='#dc3545'">
                    <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                    Remove
                </button>
            `;

            container.appendChild(memberDiv);
        }

        function removeGroupMember(id) {
            const element = document.getElementById(`group-member-${id}`);
            if (element) {
                element.style.opacity = '0';
                element.style.transform = 'translateX(-10px)';
                setTimeout(() => {
                    element.remove();
                }, 300);
            }
        }

        // Word counter function for event description and reason
        function countWords(textarea, maxWords, counterId = 'wordCount') {
            const text = textarea.value.trim();
            const words = text === '' ? 0 : text.split(/\s+/).length;
            const wordCountElement = document.getElementById(counterId);

            // Update counter display
            wordCountElement.textContent = `${words} / ${maxWords} words`;

            // Change color based on word count
            if (words > maxWords) {
                wordCountElement.style.color = '#ef4444'; // Red
                textarea.style.borderColor = '#ef4444';
            } else if (words > maxWords * 0.9) {
                wordCountElement.style.color = '#f59e0b'; // Orange warning
                textarea.style.borderColor = '#f59e0b';
            } else {
                wordCountElement.style.color = '#10b981'; // Green
                textarea.style.borderColor = '#d1d5db';
            }

            // Prevent further input if limit exceeded
            const warningId = `wordLimitWarning_${counterId}`;
            if (words > maxWords) {
                // Show warning
                if (!document.getElementById(warningId)) {
                    const warning = document.createElement('div');
                    warning.id = warningId;
                    warning.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 5px;';
                    warning.textContent = `⚠ Word limit exceeded! Please reduce to ${maxWords} words or less.`;
                    wordCountElement.parentNode.appendChild(warning);
                }
            } else {
                // Remove warning if exists
                const warning = document.getElementById(warningId);
                if (warning) {
                    warning.remove();
                }
            }
        }

        // Initialize word counter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const eventDescTextarea = document.getElementById('eventDescription');
            if (eventDescTextarea) {
                // Trigger count on load in case of pre-filled data
                countWords(eventDescTextarea, 50, 'wordCount');
            }

            const reasonTextarea = document.getElementById('reasonForOD');
            if (reasonTextarea) {
                // Trigger count on load in case of pre-filled data
                countWords(reasonTextarea, 50, 'reasonWordCount');
            }
        });
    </script>
</body>
</html>
