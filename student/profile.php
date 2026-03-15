<?php
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
    }

    // Block access if 2FA verification is still pending
    if (isset($_SESSION['2fa_pending']) && $_SESSION['2fa_pending'] === true
    && (! isset($_SESSION['2fa_verified']) || $_SESSION['2fa_verified'] !== true)) {
    header("Location: ../verify_2fa.php");
    exit();
    }

    require_once __DIR__ . '/../includes/db_config.php';
    require_once __DIR__ . '/../includes/TotpManager.php';
    $conn = get_db_connection();

    // Check 2FA status
    $totpMgr        = new TotpManager();
    $is_2fa_enabled = $totpMgr->isEnabled($conn, $_SESSION['username'], 'student_register');

    $username     = $_SESSION['username'];
    $student_data = null;
    $message      = '';
    $message_type = '';

    // Check for 2FA success message from setup
    if (isset($_GET['2fa']) && $_GET['2fa'] === 'enabled') {
    $message      = 'Two-factor authentication has been enabled successfully!';
    $message_type = 'success';
    } elseif (isset($_GET['2fa']) && $_GET['2fa'] === 'disabled') {
    $message      = 'Two-factor authentication has been disabled.';
    $message_type = 'success';
    }

    // Fetch complete student data
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

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        // Handle password update
        $current_password = $_POST['current_password'];
        $new_password     = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate password inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message      = "All password fields are required.";
            $message_type = "error";
        } elseif ($new_password !== $confirm_password) {
            $message      = "New password and confirm password do not match.";
            $message_type = "error";
        } elseif (strlen($new_password) < 6) {
            $message      = "New password must be at least 6 characters long.";
            $message_type = "error";
        } else {
            // Verify current password
            $current_password_sql = "SELECT password FROM student_register WHERE username=?";
            $current_stmt         = $conn->prepare($current_password_sql);
            $current_stmt->bind_param("s", $username);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();

            if ($current_result->num_rows > 0) {
                $current_data = $current_result->fetch_assoc();

                if (password_verify($current_password, $current_data['password'])) {
                    // Update password
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_update_sql = "UPDATE student_register SET password=? WHERE username=?";
                    $password_stmt       = $conn->prepare($password_update_sql);
                    $password_stmt->bind_param("ss", $hashed_new_password, $username);

                    if ($password_stmt->execute()) {
                        $message      = "Password updated successfully!";
                        $message_type = "success";
                    } else {
                        $message      = "Error updating password: " . htmlspecialchars($password_stmt->error);
                        $message_type = "error";
                    }
                    $password_stmt->close();
                } else {
                    $message      = "Current password is incorrect.";
                    $message_type = "error";
                }
            }
            $current_stmt->close();
        }
    }
    }

    // Get statistics for the profile
    $regno = $student_data['regno'];

    // Check if student is assigned to a class counselor
    $counselor_info = null;
    $counselor_sql  = "SELECT tr.name as counselor_name, tr.email as counselor_email,
                            ca.assigned_date, tr.faculty_id as counselor_id
                     FROM counselor_assignments ca
                     JOIN teacher_register tr ON ca.counselor_id = tr.id
                     WHERE ca.student_regno = ? AND ca.status = 'active'
                     ORDER BY ca.assigned_date DESC
                     LIMIT 1";
    $counselor_stmt = $conn->prepare($counselor_sql);
    $counselor_stmt->bind_param("s", $regno);
    $counselor_stmt->execute();
    $counselor_result = $counselor_stmt->get_result();

    if ($counselor_result->num_rows > 0) {
    $counselor_info = $counselor_result->fetch_assoc();
    }
    $counselor_stmt->close();

    // Total events participated (only approved events count)
    $total_events_sql = "SELECT COUNT(*) as total FROM student_event_register WHERE regno=? AND verification_status = 'Approved'";
    $total_stmt       = $conn->prepare($total_events_sql);
    $total_stmt->bind_param("s", $regno);
    $total_stmt->execute();
    $total_events = $total_stmt->get_result()->fetch_assoc()['total'];

    // Events won (only approved events count)
    $events_won_sql = "SELECT COUNT(*) as won FROM student_event_register WHERE regno=? AND verification_status = 'Approved' AND prize IN ('First', 'Second', 'Third')";
    $won_stmt       = $conn->prepare($events_won_sql);
    $won_stmt->bind_param("s", $regno);
    $won_stmt->execute();
    $events_won = $won_stmt->get_result()->fetch_assoc()['won'];

    $stmt->close();
    $total_stmt->close();
    $won_stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>My Profile - Event Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <!-- Web App Manifest for Push Notifications -->
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <!-- OneSignal Web Push Notifications -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js"></script>
    <script>
      const studentRegno = <?php echo json_encode($student_data['regno']); ?>;
      if (navigator.userAgent.indexOf('median') > -1 || navigator.userAgent.indexOf('gonative') > -1) {
        function _savePlayerId(pid) {
          if (!pid || !studentRegno) return;
          fetch('../api/save_player_id.php', {
            method: 'POST',
            credentials: 'include',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({player_id: pid})
          }).then(function(r){ return r.json(); })
            .then(function(d){ console.log('save_player_id:', d); })
            .catch(function(e){ console.warn('save_player_id error:', e); });
        }
        function _linkMedianId() {
          if (typeof median !== 'undefined' && median.onesignal) {
            try {
              median.onesignal.externalUserId.set({externalId: String(studentRegno)});
              median.onesignal.tags.setTags({tags: {regno: String(studentRegno)}});
              console.log('Median OneSignal: linked ' + studentRegno);
            } catch(e) { console.warn('Median bridge:', e); }
          }
        }
        _linkMedianId();
        document.addEventListener('DOMContentLoaded', _linkMedianId);
        window.addEventListener('load', _linkMedianId);
      } else {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async function(OneSignal) {
          await OneSignal.init({ appId: <?php echo json_encode(getenv('ONESIGNAL_APP_ID') ?: ''); ?>, allowLocalhostAsSecureOrigin: true });
          if (studentRegno) {
            OneSignal.login(studentRegno);
            OneSignal.User.addTags({"regno": studentRegno});
            console.log('OneSignal Web: Logged in as ' + studentRegno);
          }
          OneSignal.Notifications.requestPermission();
        });
      }
    </script>
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            height: fit-content;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(30, 66, 118, 0.3);
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .profile-regno {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .counselor-info {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            border-radius: 12px;
            border-left: 4px solid #28a745;
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
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .counselor-info.no-counselor .counselor-title {
            color: #856404;
        }

        .counselor-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .counselor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .counselor-id {
            font-size: 13px;
            color: #6c757d;
            font-weight: 500;
        }

        .counselor-email, .assigned-date {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #495057;
        }

        .counselor-email .material-symbols-outlined,
        .assigned-date .material-symbols-outlined {
            font-size: 16px;
            color: #6c757d;
        }

        .no-counselor-message {
            font-size: 14px;
            color: #856404;
            font-style: italic;
        }

        .counselor-display {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
            border: 2px solid #28a745 !important;
            min-height: 60px !important;
            display: flex;
            align-items: center;
        }

        .counselor-display:empty::before {
            content: "No counselor assigned";
            color: #6c757d;
            font-style: italic;
        }

        .profile-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .form-title {
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

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .readonly-field {
            background-color: #f8f9fa !important;
            cursor: not-allowed;
            color: #6c757d;
            border-color: #dee2e6 !important;
        }

        .readonly-field:focus {
            border-color: #dee2e6 !important;
            box-shadow: none;
        }

        .form-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
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
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .info-text {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }

        .profile-display {
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            color: #495057;
            min-height: 20px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-card, .profile-form {
                padding: 20px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .form-buttons {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .counselor-info {
                padding: 15px;
                margin-top: 20px;
            }

            .counselor-details {
                gap: 6px;
            }

            .counselor-email, .assigned-date {
                font-size: 12px;
            }

            .counselor-display {
                min-height: 50px !important;
                padding: 10px !important;
            }
        }
        /* Password field toggle styles */
        .password-field {
            position: relative;
            width: 100%;
        }

        .password-field .form-input {
            padding-right: 44px; /* space for the toggle button */
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 6px;
            color: var(--primary-color);
            z-index: 2;
            transition: all 0.2s ease;
        }

        .password-toggle:hover {
            transform: translateY(-50%) scale(1.1);
        }

        .password-toggle .material-symbols-outlined {
            font-size: 20px;
        }

        /* Ensure password toggle is visible on mobile */
        @media (max-width: 768px) {
            .password-field {
                position: relative;
            }

            .password-toggle {
                display: inline-flex !important;
                right: 10px;
                z-index: 10;
            }

            .password-toggle .material-symbols-outlined {
                font-size: 18px;
            }
        }

        /* Notification Bell Styles */
        .notification-bell-container {
            position: absolute;
            top: 12px;
            right: 20px;
            display: flex;
            align-items: center;
            z-index: 1001;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid #1a408c;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            transition: all 0.3s ease;
            margin: 0;
        }

        .notification-bell:hover {
            background: #f0f4f8;
            transform: scale(1.05);
        }

        .notification-bell .material-symbols-outlined {
            font-size: 24px;
            color: #1a408c;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
        }

        .notification-badge.hidden {
            display: none;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: 1px solid #eee;
            width: 350px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1a408c;
        }

        .notification-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .notification-header .mark-all,
        .notification-header .clear-all {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            text-decoration: underline;
            padding: 0;
            transition: all 0.3s ease;
        }

        .notification-header .mark-all {
            color: #1a408c;
        }

        .notification-header .mark-all:hover {
            color: #15306b;
        }

        .notification-header .clear-all {
            color: #dc3545;
        }

        .notification-header .clear-all:hover {
            color: #a71d2a;
        }

        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            gap: 12px;
        }

        .notification-item:hover {
            background: #f9f9f9;
        }

        .notification-item.unread {
            background: #f0f4f8;
        }

        .notification-item-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: #1a408c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .notification-item-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item-content h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .notification-item-content p {
            margin: 0 0 5px 0;
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        .notification-item-time {
            font-size: 12px;
            color: #999;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .notification-empty-icon {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }

        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: none;
            z-index: 999;
        }

        .notification-overlay.show {
            display: block;
        }

        @media (max-width: 768px) {
            .notification-bell-container {
                position: absolute;
                top: 8px;
                right: 10px;
            }

            .notification-dropdown {
                position: fixed;
                top: auto;
                right: 10px;
                left: 10px;
                bottom: 80px;
                width: auto;
                max-height: 300px;
            }

            .notification-bell {
                width: 40px;
                height: 40px;
                margin: 0;
            }

            .notification-bell .material-symbols-outlined {
                font-size: 20px;
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
                <img src="sona_logo.jpg"
                alt="Sona College Logo" height="60px" width="200" >
            </div>
            <div class="notification-bell-container">
                <div class="notification-bell" id="notificationBell">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <div class="notification-header-actions">
                            <button class="mark-all" onclick="markAllNotificationsAsRead()">Mark all as read</button>
                            <button class="clear-all" onclick="clearAllNotifications()">Clear all</button>
                        </div>
                    </div>
                    <ul class="notification-list" id="notificationList">
                        <li class="notification-empty">
                            <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                            <p>No notifications</p>
                        </li>
                    </ul>
                </div>
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
                        <a href="od_request.php" class="nav-link">
                            <span class="material-symbols-outlined">person_raised_hand</span>
                            OD Request
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="internship_submission.php" class="nav-link">
                            <span class="material-symbols-outlined">work</span>
                            Internship Submission
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="hackathons.php" class="nav-link">
                            <span class="material-symbols-outlined">emoji_events</span>
                            Hackathons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link active">
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

            <div class="profile-container">
                <!-- Profile Info Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($student_data['name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                        <div class="profile-regno">Registration No:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo htmlspecialchars($student_data['regno']); ?></div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $total_events; ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $events_won; ?></div>
                                <div class="stat-label">Prizes</div>
                            </div>
                        </div>

                        <!-- Class Counselor Information -->
                        <?php if ($counselor_info): ?>
                        <div class="counselor-info">
                            <div class="counselor-title">
                                <span class="material-symbols-outlined">supervisor_account</span>
                                Class Counselor
                            </div>
                            <div class="counselor-details">
                                <div class="counselor-name"><?php echo htmlspecialchars($counselor_info['counselor_name']); ?></div>
                                <div class="counselor-id">ID:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo htmlspecialchars($counselor_info['counselor_id']); ?></div>
                                <div class="counselor-email">
                                    <span class="material-symbols-outlined">email</span>
                                    <?php echo htmlspecialchars($counselor_info['counselor_email']); ?>
                                </div>
                                <div class="assigned-date">
                                    <span class="material-symbols-outlined">schedule</span>
                                    Assigned:                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo date('M d, Y', strtotime($counselor_info['assigned_date'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="counselor-info no-counselor">
                            <div class="counselor-title">
                                <span class="material-symbols-outlined">info</span>
                                Class Counselor
                            </div>
                            <div class="no-counselor-message">
                                No class counselor assigned yet
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="profile-form">
                    <div class="form-title">
                        <span class="material-symbols-outlined">person</span>
                        <span>Profile Information</span>
                    </div>

                    <!-- Profile Information Display -->
                    <div id="profileForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <div class="profile-display">
                                    <?php echo htmlspecialchars($student_data['name']); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Registration Number</label>
                                <div class="profile-display">
                                    <?php echo htmlspecialchars($student_data['regno']); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <div class="profile-display">
                                    <?php echo htmlspecialchars($student_data['username']); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Personal Email</label>
                                <div class="profile-display">
                                    <?php echo htmlspecialchars($student_data['personal_email']); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <div class="profile-display">
                                    <?php
                                        $dept_names = [
                                            'CSE'   => 'Computer Science and Engineering',
                                            'IT'    => 'Information Technology',
                                            'ECE'   => 'Electronics and Communication Engineering',
                                            'EEE'   => 'Electrical and Electronics Engineering',
                                            'MECH'  => 'Mechanical Engineering',
                                            'CIVIL' => 'Civil Engineering',
                                            'BME'   => 'Biomedical Engineering',
                                        ];
                                        $dept = $student_data['department'] ?? '';
                                        echo htmlspecialchars($dept_names[$dept] ?? ($dept ?: 'Not specified'));
                                    ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Semester</label>
                                <div class="profile-display">
                                    <?php echo htmlspecialchars($student_data['semester'] ?? 'Not specified'); ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Class Counselor</label>
                                <div class="profile-display counselor-display">
                                    <?php if ($counselor_info): ?>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <span style="font-weight: 600; color: #28a745;">
                                                <?php echo htmlspecialchars($counselor_info['counselor_name']); ?>
                                            </span>
                                            <span style="font-size: 12px; color: #6c757d;">
                                                ID:                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php echo htmlspecialchars($counselor_info['counselor_id']); ?> |
                                                <?php echo htmlspecialchars($counselor_info['counselor_email']); ?>
                                            </span>
                                            <span style="font-size: 11px; color: #856404;">
                                                Assigned:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo date('M d, Y', strtotime($counselor_info['assigned_date'])); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-style: italic;">
                                            No class counselor assigned
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>

                        <div class="form-buttons">
                            <a href="index.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Dashboard
                            </a>
                        </div>
                    </div>

                    <!-- Password Update Section -->
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e9ecef;">
                        <h3 class="form-title">
                            <span class="material-symbols-outlined">lock</span>
                            Change Password
                        </h3>

                        <form method="POST" action="">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Current Password *</label>
                                    <div class="password-field">
                                        <input type="password" name="current_password" class="form-input"
                                               placeholder="Enter current password" required>
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New Password *</label>
                                    <div class="password-field">
                                        <input type="password" name="new_password" class="form-input"
                                               placeholder="Enter new password (min 6 characters)" required>
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label">Confirm New Password *</label>
                                    <div class="password-field">
                                        <input type="password" name="confirm_password" class="form-input"
                                               placeholder="Confirm new password" required>
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility(this)">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-buttons">
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <span class="material-symbols-outlined">security</span>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Two-Factor Authentication Section -->
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e9ecef;">
                        <h3 class="form-title">
                            <span class="material-symbols-outlined">verified_user</span>
                            Two-Factor Authentication
                        </h3>

                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <?php if ($is_2fa_enabled): ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #e8f5e9; color: #2e7d32;">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">check_circle</span>
                                    Enabled
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #fff8e1; color: #e65100;">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">warning</span>
                                    Not Configured
                                </span>
                            <?php endif; ?>
                        </div>

                        <p style="color: #666; font-size: 13px; line-height: 1.6; margin-bottom: 16px;">
                            <?php if ($is_2fa_enabled): ?>
                                Your account is protected with two-factor authentication.
                                You'll be asked for a verification code each time you sign in.
                            <?php else: ?>
                                Add an extra layer of security by requiring a code from your
                                authenticator app when signing in.
                            <?php endif; ?>
                        </p>

                        <a href="../setup_2fa.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                            <span class="material-symbols-outlined">settings</span>
                            <?php echo $is_2fa_enabled ? 'Manage 2FA Settings' : 'Enable Two-Factor Authentication'; ?>
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePasswordVisibility(button) {
            const input = button.parentElement.querySelector('input');
            const icon = button.querySelector('.material-symbols-outlined');

            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }

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
            const sidebar = document.getElementById('sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !headerMenuIcon.contains(event.target)) {
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

            // ============================================================================
            // NOTIFICATION SYSTEM
            // ============================================================================

            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationOverlay = document.createElement('div');
            notificationOverlay.className = 'notification-overlay';
            document.body.appendChild(notificationOverlay);

            // Fetch notifications when page loads
            function loadNotifications() {
                fetch('ajax/get_notifications.php?action=get_notifications')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayNotifications(data.notifications, data.unread_count);
                        }
                    })
                    .catch(error => console.log('Error loading notifications:', error));
            }

            function displayNotifications(notifications, unreadCount) {
                const notificationList = document.getElementById('notificationList');
                const notificationBadge = document.getElementById('notificationBadge');

                // Update badge
                if (unreadCount > 0) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }

                // Clear list
                notificationList.innerHTML = '';

                if (notifications.length === 0) {
                    notificationList.innerHTML = `
                        <li class="notification-empty">
                            <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                            <p>No notifications</p>
                        </li>
                    `;
                    return;
                }

                // Add notifications
                notifications.forEach(notification => {
                    const date = new Date(notification.created_at);
                    const timeString = getTimeString(date);

                    const li = document.createElement('li');
                    li.className = `notification-item ${(notification.is_read == 0 || notification.is_read === null) ? 'unread' : ''}`;
                    li.innerHTML = `
                        <div class="notification-item-icon">
                            <span class="material-symbols-outlined">emoji_events</span>
                        </div>
                        <div class="notification-item-content">
                            <h4>${escapeHtml(notification.title || notification.hackathon_title)}</h4>
                            <p>${escapeHtml(notification.message)}</p>
                            <span class="notification-item-time">${timeString}</span>
                        </div>
                    `;
                    li.style.cursor = 'pointer';
                    li.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Notification clicked:', notification.id, notification.link);
                        handleNotificationClick(notification.id, notification.link);
                    };
                    notificationList.appendChild(li);
                });
            }

            function resolveNotificationLink(link) {
                if (!link) return '/event_management_system/login/student/hackathons.php';
                if (link.startsWith('/event_management_system/')) return link;
                if (link.startsWith('/student/')) return '/event_management_system/login' + link;
                if (link.startsWith('student/')) return '/event_management_system/login/' + link;
                if (!link.startsWith('/') && !link.startsWith('http')) return '/event_management_system/login/student/' + link;
                return link;
            }

            function handleNotificationClick(notificationId, link) {
                // Close dropdown immediately
                notificationDropdown.classList.remove('show');
                notificationOverlay.classList.remove('show');

                const fullLink = resolveNotificationLink(link);

                // Mark as read then redirect (always redirect regardless of mark_as_read result)
                fetch(`ajax/get_notifications.php?action=mark_as_read&id=${notificationId}`)
                    .finally(() => {
                        window.location.href = fullLink;
                    });
            }

            window.clearAllNotifications = function() {
                if (!confirm('Are you sure you want to clear all notifications?')) return;

                const notificationList = document.getElementById('notificationList');
                notificationList.innerHTML = `
                    <li class="notification-empty">
                        <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                        <p>No notifications</p>
                    </li>
                `;
                const notificationBadge = document.getElementById('notificationBadge');
                notificationBadge.classList.add('hidden');
                notificationBadge.textContent = '0';

                fetch('ajax/get_notifications.php?action=clear_all')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            loadNotifications();
                        }
                    })
                    .catch(error => console.log('Error clearing notifications:', error));
            };

            window.markAllNotificationsAsRead = function() {
                const notificationItems = document.querySelectorAll('#notificationList .notification-item.unread');
                notificationItems.forEach(item => item.classList.remove('unread'));
                const notificationBadge = document.getElementById('notificationBadge');
                notificationBadge.classList.add('hidden');
                notificationBadge.textContent = '0';

                fetch('ajax/get_notifications.php?action=mark_all_read')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            loadNotifications();
                        }
                    })
                    .catch(error => console.log('Error marking all notifications as read:', error));
            };

            function getTimeString(date) {
                const now = new Date();
                const diff = now - date;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);

                if (minutes < 1) return 'just now';
                if (minutes < 60) return `${minutes}m ago`;
                if (hours < 24) return `${hours}h ago`;
                if (days < 7) return `${days}d ago`;

                return date.toLocaleDateString();
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Toggle notification dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                notificationOverlay.classList.toggle('show');
            });

            // Close dropdown when clicking overlay
            notificationOverlay.addEventListener('click', function() {
                notificationDropdown.classList.remove('show');
                notificationOverlay.classList.remove('show');
            });

            // Close dropdown when clicking outside (except on bell)
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                    notificationOverlay.classList.remove('show');
                }
            });

            // Load notifications on page load
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 3000);
            }
        });


    </script>
    <!-- Push Notifications Manager for Median.co -->
</body>
</html>