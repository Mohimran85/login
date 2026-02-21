<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';
    require_once __DIR__ . '/../includes/FileCompressor.php';
    require_once __DIR__ . '/../includes/WebPushManager.php';
    require_once __DIR__ . '/../includes/csrf.php';

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Require admin authentication
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

    $db   = DatabaseManager::getInstance();
    $push = WebPushManager::getInstance();

    // Get admin user ID
    $user_query = "SELECT id, name FROM teacher_register WHERE username = ? LIMIT 1";
    $user_data  = $db->executeQuery($user_query, [$username]);
    $admin_id   = $user_data[0]['id'];
    $admin_name = $user_data[0]['name'];

    $errors          = [];
    $success_message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (! validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    } else {
        // Validate inputs
        $title                 = trim($_POST['title'] ?? '');
        $description           = trim($_POST['description'] ?? '');
        $organizer             = trim($_POST['organizer'] ?? '');
        $theme                 = trim($_POST['theme'] ?? '');
        $tags                  = trim($_POST['tags'] ?? '');
        $hackathon_link        = trim($_POST['hackathon_link'] ?? '');
        $start_date            = $_POST['start_date'] ?? '';
        $end_date              = $_POST['end_date'] ?? '';
        $registration_deadline = $_POST['registration_deadline'] ?? '';
        $max_participants      = $_POST['max_participants'] ?? null;
        $status                = $_POST['status'] ?? 'upcoming';
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

        if (strtotime($end_date) < strtotime($start_date)) {
            $errors[] = "End date must be after start date";
        }

        if (strtotime($registration_deadline) > strtotime($start_date)) {
            $errors[] = "Registration deadline must be before start date";
        }

        if ($max_participants !== '' && (! is_numeric($max_participants) || $max_participants < 1)) {
            $errors[] = "Max participants must be a positive number";
        }

        // Handle file uploads
        $poster_url = null;
        $rules_pdf  = null;

        // Upload poster
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $poster_tmp = $_FILES['poster']['tmp_name'];
            $poster_ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));

            // Validate file type
            if (! in_array($poster_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $errors[] = "Poster must be an image file (JPG, PNG, GIF, WebP)";
            } else {
                // Compress and save
                $upload_dir = __DIR__ . '/../uploads/hackathon_posters/';
                if (! is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $compression_result = FileCompressor::compressUploadedFile(
                    $poster_tmp,
                    $upload_dir . uniqid('poster_') . '_' . time(),
                    $poster_ext,
                    85
                );

                if ($compression_result['success']) {
                    $poster_url = str_replace(__DIR__ . '/..', '', $compression_result['path']);
                } else {
                    $errors[] = "Failed to upload poster";
                }
            }
        }

        // Upload rules PDF
        if (isset($_FILES['rules_pdf']) && $_FILES['rules_pdf']['error'] === UPLOAD_ERR_OK) {
            $pdf_tmp = $_FILES['rules_pdf']['tmp_name'];
            $pdf_ext = strtolower(pathinfo($_FILES['rules_pdf']['name'], PATHINFO_EXTENSION));

            // Validate file type
            if ($pdf_ext !== 'pdf') {
                $errors[] = "Rules document must be a PDF file";
            } else {
                // Compress and save
                $upload_dir = __DIR__ . '/../uploads/hackathon_rules/';
                if (! is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $compression_result = FileCompressor::compressUploadedFile(
                    $pdf_tmp,
                    $upload_dir . uniqid('rules_') . '_' . time(),
                    $pdf_ext,
                    85
                );

                if ($compression_result['success']) {
                    $rules_pdf = str_replace(__DIR__ . '/..', '', $compression_result['path']);
                } else {
                    $errors[] = "Failed to upload rules PDF";
                }
            }
        }

        // Insert into database if no errors
        if (empty($errors)) {
            try {
                $insert_sql = "INSERT INTO hackathon_posts
                    (title, description, organizer, poster_url, rules_pdf, hackathon_link, theme, tags,
                     start_date, end_date, registration_deadline, max_participants, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $params = [
                    $title, $description, $organizer, $poster_url, $rules_pdf, $hackathon_link ?: null, $theme, $tags,
                    $start_date, $end_date, $registration_deadline,
                    $max_participants ?: null, $status, $admin_id,
                ];

                $result       = $db->executeQuery($insert_sql, $params);
                $hackathon_id = $db->getConnection()->insert_id;

                $success_message = "Hackathon created successfully!";

                // Send push notifications if requested and status is upcoming
                if ($send_notification && $status === 'upcoming') {
                    $notification_payload = [
                        'title' => '🚀 New Hackathon Posted!',
                        'body'  => $title . ' - Register now!',
                        'icon'  => '/asserts/images/logo.png',
                        'badge' => '/asserts/images/badge.png',
                        'url'   => '/student/hackathon_details.php?id=' . $hackathon_id,
                        'tag'   => 'hackathon-' . $hackathon_id,
                        'data'  => [
                            'hackathon_id' => $hackathon_id,
                            'type'         => 'new_hackathon',
                        ],
                    ];

                    // Send to all students
                    $push_stats = $push->sendToAllStudents($notification_payload);

                    // Save notification records
                    $students = $db->executeQuery("SELECT regno FROM student_register");
                    foreach ($students as $student) {
                        // Insert into notifications table
                        $db->executeQuery(
                            "INSERT INTO notifications (user_regno, hackathon_id, notification_type, title, message, link, sent_at)
                             VALUES (?, ?, 'hackathon', ?, ?, ?, NOW())",
                            [
                                $student['regno'],
                                $hackathon_id,
                                '🚀 New Hackathon Posted!',
                                $title . ' - Register now!',
                                '/student/hackathon_details.php?id=' . $hackathon_id,
                            ]
                        );
                    }

                    $success_message .= " Push notifications sent: {$push_stats['sent']} successful, {$push_stats['failed']} failed.";
                }

                // Redirect after 2 seconds
                header("refresh:2;url=hackathons.php");

            } catch (Exception $e) {
                $errors[] = "Error creating hackathon: " . $e->getMessage();
                error_log("Hackathon creation error: " . $e->getMessage());
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
    <title>Create Hackathon - Admin Dashboard</title>
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
        /* Page-specific styles */
        .page-header-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-title h1 {
            font-size: 28px;
            color: #0c3878;
        }

        .header-title .material-symbols-outlined {
            font-size: 36px;
            color: #0c3878;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
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

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .form-group label .required {
            color: #ea4335;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
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
            color: #999;
            font-size: 12px;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border: 2px dashed #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-input-label:hover {
            border-color: #0c3878;
            background: #f8f9fa;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 13px;
            display: none;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #e8f0f7;
            border-radius: 6px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            margin: 0;
            color: #0c3878;
            font-weight: 500;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        @media (max-width: 768px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
            }

            .page-header-section {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
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
                        <span class="profile-name"><?php echo htmlspecialchars($admin_name); ?></span>
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
                <div class="header-title">
                    <span class="material-symbols-outlined">add_circle</span>
                    <h1>Create New Hackathon</h1>
                </div>
                <div class="header-actions">
                    <a href="hackathon_applications.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">description</span>
                        View Applications
                    </a>
                    <a href="hackathons.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?php echo htmlspecialchars($success_message); ?>
                    <br><small>Redirecting to hackathons list...</small>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (! empty($errors)): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="hackathonForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-grid">
                    <!-- Title -->
                    <div class="form-group">
                        <label for="title">Hackathon Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required maxlength="255"
                               placeholder="e.g., AI Innovation Challenge 2026"
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required
                                  placeholder="Provide detailed information about the hackathon, objectives, rules, prizes, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <small>Describe what students can build, expectations, judging criteria, prizes, etc.</small>
                    </div>

                    <!-- 2-column layout -->
                    <div class="form-grid-2">
                        <!-- Organizer -->
                        <div class="form-group">
                            <label for="organizer">Organizer <span class="required">*</span></label>
                            <input type="text" id="organizer" name="organizer" required maxlength="255"
                                   placeholder="e.g., Department of Computer Science"
                                   value="<?php echo htmlspecialchars($_POST['organizer'] ?? ''); ?>">
                        </div>

                        <!-- Theme -->
                        <div class="form-group">
                            <label for="theme">Theme</label>
                            <input type="text" id="theme" name="theme" maxlength="100"
                                   placeholder="e.g., Artificial Intelligence"
                                   value="<?php echo htmlspecialchars($_POST['theme'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" maxlength="255"
                               placeholder="e.g., AI, Machine Learning, Innovation, Technology"
                               value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                        <small>Comma-separated tags for filtering and search</small>
                    </div>

                    <!-- Hackathon Link -->
                    <div class="form-group">
                        <label for="hackathon_link">External Link / Registration URL</label>
                        <input type="url" id="hackathon_link" name="hackathon_link" maxlength="500"
                               placeholder="e.g., https://hackathon-platform.com/register"
                               value="<?php echo htmlspecialchars($_POST['hackathon_link'] ?? ''); ?>">
                        <small>Optional: Add external registration URL or hackathon details page</small>
                    </div>

                    <!-- Dates -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="start_date">Start Date <span class="required">*</span></label>
                            <input type="date" id="start_date" name="start_date" required
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date <span class="required">*</span></label>
                            <input type="date" id="end_date" name="end_date" required
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Registration deadline and max participants -->
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="registration_deadline">Registration Deadline <span class="required">*</span></label>
                            <input type="datetime-local" id="registration_deadline" name="registration_deadline" required
                                   value="<?php echo htmlspecialchars($_POST['registration_deadline'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="max_participants">Max Participants</label>
                            <input type="number" id="max_participants" name="max_participants" min="1"
                                   placeholder="Leave empty for unlimited"
                                   value="<?php echo htmlspecialchars($_POST['max_participants'] ?? ''); ?>">
                            <small>Leave empty for unlimited registrations</small>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                        <label for="status">Status <span class="required">*</span></label>
                        <select id="status" name="status" required>
                            <option value="draft" <?php echo($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft (Not visible to students)</option>
                            <option value="upcoming" <?php echo($_POST['status'] ?? 'upcoming') === 'upcoming' ? 'selected' : ''; ?>>Upcoming (Visible & Open for registration)</option>
                            <option value="ongoing" <?php echo($_POST['status'] ?? '') === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        </select>
                    </div>

                    <!-- Poster Upload -->
                    <div class="form-group">
                        <label for="poster">Hackathon Poster</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="poster" name="poster" accept="image/*" onchange="previewFile(this, 'poster-preview')">
                            <label for="poster" class="file-input-label">
                                <span class="material-symbols-outlined">upload_file</span>
                                <span>Choose poster image (JPG, PNG, GIF, WebP)</span>
                            </label>
                        </div>
                        <div id="poster-preview" class="file-preview"></div>
                        <small>Recommended size: 1200x630px. Image will be compressed automatically.</small>
                    </div>

                    <!-- Rules PDF Upload -->
                    <div class="form-group">
                        <label for="rules_pdf">Rules & Guidelines (PDF)</label>
                        <div class="file-input-wrapper">
                            <input type="file" id="rules_pdf" name="rules_pdf" accept=".pdf" onchange="previewFile(this, 'pdf-preview')">
                            <label for="rules_pdf" class="file-input-label">
                                <span class="material-symbols-outlined">picture_as_pdf</span>
                                <span>Choose rules PDF</span>
                            </label>
                        </div>
                        <div id="pdf-preview" class="file-preview"></div>
                        <small>Upload detailed rules, judging criteria, and guidelines as PDF</small>
                    </div>

                    <!-- Send Notification -->
                    <div class="checkbox-group">
                        <input type="checkbox" id="send_notification" name="send_notification" checked>
                        <label for="send_notification">
                            <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 20px;">notifications_active</span>
                            Send push notification to all students
                        </label>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-outlined">save</span>
                        Create Hackathon
                    </button>
                    <a href="hackathons.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">cancel</span>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        </div>
    </div>

    <script>
        function previewFile(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];

            if (file) {
                preview.style.display = 'block';
                preview.innerHTML = `
                    <span class="material-symbols-outlined" style="vertical-align: middle; color: #0c3878;">check_circle</span>
                    <strong>Selected:</strong> ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
                `;
            } else {
                preview.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('hackathonForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            const deadline = new Date(document.getElementById('registration_deadline').value);

            if (endDate < startDate) {
                alert('End date must be after start date');
                e.preventDefault();
                return false;
            }

            if (deadline > startDate) {
                alert('Registration deadline must be before start date');
                e.preventDefault();
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span> Creating...';
        });

        // Set min date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;

        // Sidebar functionality
        function openSidebar() {
            document.getElementById('sidebar').classList.add('sidebar-responsive');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('sidebar-responsive');
        }

        // Navigation function for header profile
        function navigateToProfile() {
            window.location.href = 'profile.php';
        }

        // Prevent back button navigation
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>
