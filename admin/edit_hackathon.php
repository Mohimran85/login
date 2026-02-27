<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';
    require_once __DIR__ . '/../includes/FileCompressor.php';
    require_once __DIR__ . '/../includes/csrf.php';
    require_once __DIR__ . '/../includes/OneSignalManager.php';

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Require authentication
    requireAuth('../index.php');

    // Check if user is admin
    $username = $_SESSION['username'];
    $conn     = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
    }

    $teacher_status_sql = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ? LIMIT 1";
    $stmt               = $conn->prepare($teacher_status_sql);
    if (! $stmt) {
    die(json_encode(['success' => false, 'error' => 'Database error']));
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0 || ($row = $result->fetch_assoc()) && $row['status'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
    }
    $stmt->close();
    $conn->close();

    // Initialize database manager
    $db = DatabaseManager::getInstance();

    // Get user name for header
    $user_query = "SELECT name FROM teacher_register WHERE username = ? LIMIT 1";
    $user_data  = $db->executeQuery($user_query, [$username]);
    $user_name  = $user_data[0]['name'] ?? 'Admin';

    // Get hackathon ID from URL
    $hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($hackathon_id <= 0) {
    $_SESSION['error_message'] = "Invalid hackathon ID.";
    header("Location: hackathons.php");
    exit();
    }

    $hackathon_sql = "SELECT * FROM hackathon_posts WHERE id = ? LIMIT 1";
    $hackathons    = $db->executeQuery($hackathon_sql, [$hackathon_id]);

    if (empty($hackathons)) {
    $_SESSION['error_message'] = "Hackathon not found.";
    header("Location: hackathons.php");
    exit();
    }

    $hackathon = $hackathons[0];

    $errors  = [];
    $success = false;

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (! validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Get form data
        $title                 = trim($_POST['title'] ?? '');
        $description           = trim($_POST['description'] ?? '');
        $organizer             = trim($_POST['organizer'] ?? '');
        $theme                 = trim($_POST['theme'] ?? '');
        $tags                  = trim($_POST['tags'] ?? '');
        $hackathon_link        = trim($_POST['hackathon_link'] ?? '');
        $start_date            = $_POST['start_date'] ?? '';
        $end_date              = $_POST['end_date'] ?? '';
        $registration_deadline = $_POST['registration_deadline'] ?? '';
        $max_participants      = (int) ($_POST['max_participants'] ?? 0);
        $status                = $_POST['status'] ?? 'draft';
        $send_notification     = isset($_POST['send_notification']);

        // Validation
        if (empty($title)) {
            $errors[] = "Title is required";
        }

        if (empty($description)) {
            $errors[] = "Description is required";
        }

        if (empty($organizer)) {
            $errors[] = "Organizer is required";
        }

        if (empty($start_date)) {
            $errors[] = "Start date is required";
        }

        if (empty($end_date)) {
            $errors[] = "End date is required";
        }

        if (empty($registration_deadline)) {
            $errors[] = "Registration deadline is required";
        }

        if (! in_array($status, ['draft', 'upcoming', 'ongoing', 'completed', 'cancelled'])) {
            $errors[] = "Invalid status";
        }

        // Date validation
        if (strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End date must be after start date";
        }
        if (strtotime($registration_deadline) > strtotime($start_date)) {
            $errors[] = "Registration deadline must be before start date";
        }

        $poster_url = $hackathon['poster_url'];
        $rules_pdf  = $hackathon['rules_pdf'];

        // Handle poster upload (optional update)
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type     = $_FILES['poster']['type'];

            if (! in_array($file_type, $allowed_types)) {
                $errors[] = "Invalid poster file type. Only JPEG, PNG, GIF, and WebP are allowed.";
            } else {
                try {
                    // Delete old poster if exists
                    if ($poster_url && file_exists(__DIR__ . '/../' . $poster_url)) {
                        unlink(__DIR__ . '/../' . $poster_url);
                    }

                    $upload_dir = __DIR__ . '/../uploads/hackathon_posters/';
                    if (! file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $compressor      = new FileCompressor();
                    $compressed_file = $compressor->compressImage(
                        $_FILES['poster']['tmp_name'],
                        $upload_dir,
                        'hackathon_' . time()
                    );

                    if ($compressed_file) {
                        $poster_url = 'uploads/hackathon_posters/' . basename($compressed_file);
                    } else {
                        $errors[] = "Failed to compress poster image";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error uploading poster: " . $e->getMessage();
                }
            }
        }

        // Handle PDF upload (optional update)
        if (isset($_FILES['rules_pdf']) && $_FILES['rules_pdf']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['rules_pdf']['type'] !== 'application/pdf') {
                $errors[] = "Rules file must be a PDF";
            } else {
                try {
                    // Delete old PDF if exists
                    if ($rules_pdf && file_exists(__DIR__ . '/../' . $rules_pdf)) {
                        unlink(__DIR__ . '/../' . $rules_pdf);
                    }

                    $upload_dir = __DIR__ . '/../uploads/hackathon_rules/';
                    if (! file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $filename    = 'rules_' . time() . '_' . basename($_FILES['rules_pdf']['name']);
                    $target_file = $upload_dir . $filename;

                    if (move_uploaded_file($_FILES['rules_pdf']['tmp_name'], $target_file)) {
                        $rules_pdf = 'uploads/hackathon_rules/' . $filename;
                    } else {
                        $errors[] = "Failed to upload rules PDF";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error uploading PDF: " . $e->getMessage();
                }
            }
        }

        // Update database if no errors
        if (empty($errors)) {
            try {
                $old_status = $hackathon['status'];

                $update_sql = "UPDATE hackathon_posts SET
                    title = ?,
                    description = ?,
                    organizer = ?,
                    theme = ?,
                    tags = ?,
                    hackathon_link = ?,
                    start_date = ?,
                    end_date = ?,
                    registration_deadline = ?,
                    max_participants = ?,
                    status = ?,
                    poster_url = ?,
                    rules_pdf = ?,
                    updated_at = NOW()
                    WHERE id = ?";

                $db->executeQuery($update_sql, [
                    $title,
                    $description,
                    $organizer,
                    $theme,
                    $tags,
                    $hackathon_link ?: null,
                    $start_date,
                    $end_date,
                    $registration_deadline,
                    $max_participants,
                    $status,
                    $poster_url,
                    $rules_pdf,
                    $hackathon_id,
                ]);

                // Auto-send notifications when hackathon status/details change
                if ($old_status === 'draft' && $status === 'upcoming') {
                    // Going LIVE - notify ALL students
                    $oneSignal = new OneSignalManager();
                    $oneSignal->notifyNewHackathon($hackathon_id, $title, $registration_deadline, $description);

                    // Log for all students
                    $students = $db->executeQuery("SELECT regno FROM student_register");
                    foreach ($students as $student) {
                        $db->executeQuery(
                            "INSERT INTO notifications (user_regno, notification_type, title, message, link, sent_at)
                             VALUES (?, 'hackathon', ?, ?, ?, NOW())",
                            [
                                $student['regno'],
                                '🚀 ' . $title,
                                'Register before ' . date('M d, Y', strtotime($registration_deadline)),
                                '/event_management_system/login/student/hackathons.php?id=' . $hackathon_id,
                            ],
                            'ssss'
                        );
                    }
                    $_SESSION['success_message'] = "✅ Hackathon is now LIVE! All students notified automatically.";

                } elseif ($status === 'upcoming' && ($title !== $old_title || $description !== $old_description)) {
                    // Details/description changed - notify ONLY applied students
                    $applied_students = $db->executeQuery(
                        "SELECT DISTINCT student_regno FROM hackathon_applications WHERE hackathon_id = ?",
                        [$hackathon_id],
                        'i'
                    );

                    if (! empty($applied_students)) {
                        $regnos = array_column($applied_students, 'student_regno');

                        $oneSignal = new OneSignalManager();
                        $oneSignal->notifyAppliedStudents($hackathon_id, $regnos, $title);

                        foreach ($applied_students as $student) {
                            $db->executeQuery(
                                "INSERT INTO notifications (user_regno, notification_type, title, message, link, sent_at)
                                 VALUES (?, 'hackathon', ?, ?, ?, NOW())",
                                [
                                    $student['student_regno'],
                                    '📢 ' . $title . ' Updated',
                                    'Check the latest details',
                                    '/event_management_system/login/student/hackathons.php?id=' . $hackathon_id,
                                ],
                                'ssss'
                            );
                        }
                    }
                    $_SESSION['success_message'] = "✅ Hackathon updated! Applied students notified automatically.";
                } else {
                    $_SESSION['success_message'] = "Hackathon updated successfully!";
                }
                header("Location: hackathons.php");
                exit();
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    }

    // Generate CSRF token
    $csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Edit Hackathon - Admin Dashboard</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <!-- CSS -->
    <link rel="stylesheet" href="./CSS/styles.css">
    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        /* Universal box-sizing */
        * {
            box-sizing: border-box;
        }

        /* Edit Hackathon Specific Styles */
        .page-header-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 0;
        }

        .page-header-title span {
            font-size: 32px;
            color: #0c3878;
        }

        .page-header-title h1 {
            margin: 0;
            color: #0c3878;
            font-size: 28px;
        }

        .form-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px 30px;
            margin-bottom: 40px;
        }

        .form-group {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #0c3878;
            margin-bottom: 10px;
        }

        .form-group label .required {
            color: #e74c3c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0c3878;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }

        .file-upload-box {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 35px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
            margin-top: 12px;
            min-height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .file-upload-box:hover {
            border-color: #0c3878;
            background: #f0f4f9;
        }

        .file-upload-box input[type="file"] {
            display: none;
        }

        .file-upload-box span {
            color: #0c3878;
        }

        .current-file {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
        }

        .current-file img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .checkbox-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .checkbox-group:hover {
            border-color: #0c3878;
            background: #f0f4f8;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: normal;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #0c3878;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            flex: 1;
            justify-content: center;
        }

        .btn-primary {
            background: #0c3878;
            color: white;
        }

        .btn-primary:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(12, 56, 120, 0.3);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #666;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .alert span {
            flex-shrink: 0;
            font-size: 20px;
        }

        .alert ul {
            margin: 5px 0 0 0;
            padding-left: 20px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 25px;
                margin-bottom: 30px;
            }

            .form-group {
                margin-bottom: 0;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 12px 12px;
            }

            .btn-group {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                padding: 12px 20px;
                font-size: 13px;
            }

            .page-header-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .checkbox-group {
                margin-top: 20px;
                margin-bottom: 20px;
                padding: 15px;
            }

            .file-upload-box {
                padding: 25px 15px;
                min-height: 100px;
            }

            .form-container {
                padding: 20px;
            }
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
                <img class="logo" src="../sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
            </div>
            <div class="header-title">
                <p>Event Management Dashboard</p>
            </div>
            <div class="header-profile">
                <div class="profile-info" onclick="navigateToProfile()">
                    <span class="material-symbols-outlined">account_circle</span>
                    <div class="profile-details">
                        <span class="profile-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="profile-role">Admin</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-title">
                <div class="sidebar-band">
                    <h2 style="color: white; padding: 10px">Admin Panel</h2>
                    <span class="material-symbols-outlined" onclick="closeSidebar()">close</span>
                </div>
                <ul class="sidebar-list">
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">dashboard</span>
                        <a href="index.php">Home</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">people</span>
                        <a href="participants.php">Participants</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">manage_accounts</span>
                        <a href="user_management.php">User Management</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">school</span>
                        <a href="manage_counselors.php">Manage Counselors</a>
                    </li>
                    <li class="sidebar-list-item active">
                        <span class="material-symbols-outlined">emoji_events</span>
                        <a href="hackathons.php">Hackathons</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">bar_chart</span>
                        <a href="reports.php">Reports</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">account_circle</span>
                        <a href="profile.php">Profile</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">logout</span>
                        <a href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main">
            <!-- Page Header Section -->
            <div class="page-header-section">
                <div class="page-header-title">
                    <span class="material-symbols-outlined">edit</span>
                    <div>
                        <h1>Edit Hackathon</h1>
                        <p style="color: #666; font-size: 14px; margin-top: 5px;">Update hackathon details and information</p>
                    </div>
                </div>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <?php if (! empty($errors)): ?>
                    <div class="alert alert-error">
                        <span class="material-symbols-outlined">error</span>
                        <div>
                            <strong>Please fix the following errors:</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-grid">
                        <!-- Title -->
                        <div class="form-group full-width">
                            <label for="title">Hackathon Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" required
                                   value="<?php echo htmlspecialchars($hackathon['title']); ?>">
                        </div>

                        <!-- Organizer -->
                        <div class="form-group">
                            <label for="organizer">Organized By <span class="required">*</span></label>
                            <input type="text" id="organizer" name="organizer" required
                                   value="<?php echo htmlspecialchars($hackathon['organizer']); ?>">
                        </div>

                        <!-- Theme -->
                        <div class="form-group">
                            <label for="theme">Theme / Category</label>
                            <input type="text" id="theme" name="theme"
                                   value="<?php echo htmlspecialchars($hackathon['theme']); ?>">
                            <small>e.g., Web Development, AI/ML, IoT</small>
                        </div>

                        <!-- Start Date -->
                        <div class="form-group">
                            <label for="start_date">Start Date <span class="required">*</span></label>
                            <input type="datetime-local" id="start_date" name="start_date" required
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($hackathon['start_date'])); ?>">
                        </div>

                        <!-- End Date -->
                        <div class="form-group">
                            <label for="end_date">End Date <span class="required">*</span></label>
                            <input type="datetime-local" id="end_date" name="end_date" required
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($hackathon['end_date'])); ?>">
                        </div>

                        <!-- Registration Deadline -->
                        <div class="form-group">
                            <label for="registration_deadline">Registration Deadline <span class="required">*</span></label>
                            <input type="datetime-local" id="registration_deadline" name="registration_deadline" required
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($hackathon['registration_deadline'])); ?>">
                        </div>

                        <!-- Max Participants -->
                        <div class="form-group">
                            <label for="max_participants">Max Participants (0 = Unlimited)</label>
                            <input type="number" id="max_participants" name="max_participants" min="0"
                                   value="<?php echo $hackathon['max_participants']; ?>">
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label for="status">Status <span class="required">*</span></label>
                            <select id="status" name="status" required>
                                <option value="draft" <?php echo $hackathon['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="upcoming" <?php echo $hackathon['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $hackathon['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $hackathon['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $hackathon['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <!-- Tags -->
                        <div class="form-group full-width">
                            <label for="tags">Tags (comma-separated)</label>
                            <input type="text" id="tags" name="tags"
                                   value="<?php echo htmlspecialchars($hackathon['tags']); ?>">
                            <small>e.g., coding, innovation, prizes</small>
                        </div>

                        <!-- Hackathon Link -->
                        <div class="form-group full-width">
                            <label for="hackathon_link">External Link / Registration URL</label>
                            <input type="url" id="hackathon_link" name="hackathon_link" maxlength="500"
                                   value="<?php echo htmlspecialchars($hackathon['hackathon_link'] ?? ''); ?>"
                                   placeholder="e.g., https://hackathon-platform.com/register">
                            <small>Optional: Add external registration URL or hackathon details page</small>
                        </div>

                        <!-- Description -->
                        <div class="form-group full-width">
                            <label for="description">Description <span class="required">*</span></label>
                            <textarea id="description" name="description" required><?php echo htmlspecialchars($hackathon['description']); ?></textarea>
                        </div>

                        <!-- Poster Upload -->
                        <div class="form-group">
                            <label>Hackathon Poster</label>
                            <div class="file-upload-box" onclick="document.getElementById('poster').click()">
                                <span class="material-symbols-outlined" style="font-size: 48px;">image</span>
                                <p style="color: #666; margin-top: 10px;">Click to upload new poster (optional)</p>
                                <input type="file" id="poster" name="poster" accept="image/*">
                            </div>
                            <?php if ($hackathon['poster_url']): ?>
                                <div class="current-file">
                                    <strong>Current Poster:</strong><br>
                                    <img src="<?php echo htmlspecialchars('../' . $hackathon['poster_url']); ?>" alt="Current Poster">
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- PDF Upload -->
                        <div class="form-group">
                            <label>Rules & Guidelines PDF</label>
                            <div class="file-upload-box" onclick="document.getElementById('rules_pdf').click()">
                                <span class="material-symbols-outlined" style="font-size: 48px;">picture_as_pdf</span>
                                <p style="color: #666; margin-top: 10px;">Click to upload new PDF (optional)</p>
                                <input type="file" id="rules_pdf" name="rules_pdf" accept="application/pdf">
                            </div>
                            <?php if ($hackathon['rules_pdf']): ?>
                                <div class="current-file">
                                    <strong>Current PDF:</strong>
                                    <a href="<?php echo htmlspecialchars('../' . $hackathon['rules_pdf']); ?>" target="_blank">
                                        View Current PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>



                    <div class="btn-group">
                        <a href="hackathons.php" class="btn btn-secondary">
                            <span class="material-symbols-outlined">close</span>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span class="material-symbols-outlined">save</span>
                            Update Hackathon
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openSidebar() {
            document.getElementById('sidebar').classList.add('active');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('active');
        }

        function navigateToProfile() {
            window.location.href = 'profile.php';
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuIcon = document.querySelector('.menu-icon');
            if (!sidebar.contains(event.target) && !menuIcon.contains(event.target)) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>

