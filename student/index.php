<?php
    session_start();

    // Include optimized systems
    require_once '../includes/DatabaseManager.php';
    require_once '../includes/CacheManager.php';

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

    // Initialize optimized managers
    $db    = DatabaseManager::getInstance();
    $cache = CacheManager::getInstance();

    // Get student data
    $username     = $_SESSION['username'];
    $student_data = null;

    // Try cache first for student data
    $student_cache_key = "student_data_" . $username;
    $student_data      = $cache->get($student_cache_key);

    if (! $student_data) {
    $sql    = "SELECT name, regno FROM student_register WHERE username=?";
    $result = $db->executeQuery($sql, [$username], 's');

    if (! empty($result)) {
        $student_data = $result[0];
        // Cache student data for 1 hour
        $cache->set($student_cache_key, $student_data, 3600);
    } else {
        header("Location: ../index.php");
        exit();
    }
    }

    $regno = $student_data['regno'];

    // Always fetch fresh dashboard data (avoids stale session cache issues)
    $dashboard_cache_key = "dashboard_" . $regno;
    $cache->delete($dashboard_cache_key);

    // Get all dashboard data
    $dashboard_stats = $db->getStudentDashboardData($regno);

    // Get additional data
    $recent_activities = $db->getRecentActivities($regno, 5);
    $event_types_data  = $db->getEventTypeBreakdown($regno, 8);
    $recent_od_data    = $db->getRecentODRequests($regno, 3);

    // Get internship count
    $internship_sql    = "SELECT COUNT(*) as internship_count FROM internship_submissions WHERE regno = ?";
    $internship_result = $db->executeQuery($internship_sql, [$regno], 's');
    $internship_count  = $internship_result[0]['internship_count'] ?? 0;

    // Combine all data
    $dashboard_data = [
    'stats'              => $dashboard_stats,
    'recent_events'      => $recent_activities,
    'event_types'        => $event_types_data,
    'recent_od_requests' => $recent_od_data,
    'internship_count'   => $internship_count,
    ];

    // Extract data for backward compatibility
    $total_events     = $dashboard_data['stats']['total_events'] ?? 0;
    $events_won       = $dashboard_data['stats']['events_won'] ?? 0;
    $internship_count = $dashboard_data['internship_count'] ?? 0;
    $od_stats         = [
    'total_od_requests' => $dashboard_data['stats']['total_od_requests'] ?? 0,
    'pending_od'        => $dashboard_data['stats']['pending_od'] ?? 0,
    'approved_od'       => $dashboard_data['stats']['approved_od'] ?? 0,
    'rejected_od'       => $dashboard_data['stats']['rejected_od'] ?? 0,
    ];

    // Convert arrays to objects for compatibility with existing code
    $recent_events       = (object) ['num_rows' => count($dashboard_data['recent_events'])];
    $recent_events->data = $dashboard_data['recent_events'];

    $event_types       = (object) ['num_rows' => count($dashboard_data['event_types'])];
    $event_types->data = $dashboard_data['event_types'];

    $recent_od_requests       = (object) ['num_rows' => count($dashboard_data['recent_od_requests'])];
    $recent_od_requests->data = $dashboard_data['recent_od_requests'];
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#1a408c">
    <meta name="color-scheme" content="light only">
    <title>Student Dashboard - Event Management System</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <!-- Web App Manifest for Push Notifications -->
    <link rel="manifest" href="../manifest.json">
    <!-- OneSignal Web Push Notifications -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js"></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
          appId: <?php echo json_encode(getenv('ONESIGNAL_APP_ID') ?: ''); ?>,
          allowLocalhostAsSecureOrigin: true,
        });

        // Set external user ID (student registration number)
        const studentRegno = <?php echo json_encode($regno); ?>;
        if (studentRegno) {
          OneSignal.login(studentRegno);
          console.log('OneSignal: Logged in as ' + studentRegno);
        }

        // Prompt for permission if not already granted
        OneSignal.Notifications.requestPermission();
      });
    </script>
    <!-- css link -->
    <link rel="stylesheet" href="student_dashboard.css" />
    <!-- google icons -->
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
      rel="stylesheet"
    />
    <!-- google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
      rel="stylesheet"
    />
    <style>
      /* Mobile Optimizations */
      @media (max-width: 768px) {
        body {
          overflow-x: hidden;
        }

        .grid-container {
          grid-template-columns: 1fr;
          /* grid-template-rows: 70px 1fr; */
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
          z-index: 1002;
          width: 100%;
          padding: 0 20px;
          height: 70px;
        }

        .header .icon img {
          height: 50px;
          width: auto;
        }

        .header-title {
          font-size: 20px;
          max-width: calc(100% - 120px);
          pointer-events: none;
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
          padding: 90px 20px 30px 20px;
          margin: 0 !important;
          grid-area: main;
          box-sizing: border-box;
          overflow-x: hidden;
        }

        .welcome-section {
          padding: 25px 0;
        }

        .welcome-section h1 {
          font-size: 28px;
          margin-bottom: 12px;
        }

        .welcome-section p {
          font-size: 16px;
        }

        .main-card {
          grid-template-columns: 1fr;
          gap: 20px;
          margin-bottom: 30px;
        }

        .card {
          padding: 25px 20px;
          min-height: auto;
          border-radius: 15px;
        }

        .card h1 {
          font-size: 32px;
        }

        .card h3 {
          font-size: 16px;
        }

        .card .material-symbols-outlined {
          font-size: 32px;
        }

        .od-stats {
          gap: 20px;
        }

        .od-stat-item {
          padding: 12px 16px;
          border-radius: 10px;
        }

        .od-count {
          font-size: 20px;
        }

        .od-label {
          font-size: 13px;
        }

        .quick-actions {
          gap: 12px;
        }

        .action-btn-card {
          padding: 14px 16px;
          font-size: 14px;
          border-radius: 10px;
        }

        .action-btn-card .material-symbols-outlined {
          font-size: 20px;
        }

        .content-grid {
          grid-template-columns: 1fr;
          gap: 25px;
        }

        .content-card {
          padding: 25px 20px;
          border-radius: 15px;
        }

        .card-header h3 {
          font-size: 18px;
        }

        .card-header .material-symbols-outlined {
          font-size: 24px;
        }

        .activity-item, .od-request-item {
          padding: 16px 0;
        }

        .activity-details h4, .od-request-details h4 {
          font-size: 16px;
          margin-bottom: 8px;
        }

        .activity-meta, .od-request-meta {
          font-size: 14px;
        }

        .category-item {
          padding: 15px 0;
        }

        .category-name {
          font-size: 15px;
        }

        .category-count {
          font-size: 16px;
        }

        .empty-state {
          padding: 40px 20px;
        }

        .empty-state .material-symbols-outlined {
          font-size: 48px;
        }

        .empty-state h3 {
          font-size: 18px;
        }

        .empty-state p {
          font-size: 15px;
        }

        .empty-action {
          padding: 12px 24px;
          font-size: 15px;
          border-radius: 10px;
        }

        .prize-badge {
          font-size: 13px;
          padding: 4px 8px;
          border-radius: 8px;
        }

        .view-all-link {
          font-size: 14px;
          padding: 8px 12px;
        }
      }

      @media (max-width: 480px) {
        .main {
          padding: 0px 15px 25px 15px;
        }

        .header {
          padding: 0 15px;
        }

        .header .icon img {
          height: 45px;
        }

        .welcome-section h1 {
          font-size: 24px;
        }

        .welcome-section p {
          font-size: 15px;
        }

        .card {
          padding: 20px 16px;
        }

        .card h1 {
          font-size: 28px;
        }

        .card h3 {
          font-size: 15px;
        }

        .card .material-symbols-outlined {
          font-size: 28px;
        }

        .content-card {
          padding: 20px 16px;
        }

        .card-header h3 {
          font-size: 17px;
        }

        .card-header .material-symbols-outlined {
          font-size: 22px;
        }

        .activity-details h4, .od-request-details h4 {
          font-size: 15px;
        }

        .activity-meta, .od-request-meta {
          font-size: 13px;
        }

        .od-stat-item {
          padding: 10px 14px;
        }

        .od-count {
          font-size: 18px;
        }

        .action-btn-card {
          padding: 12px 14px;
          font-size: 13px;
        }

        .action-btn-card .material-symbols-outlined {
          font-size: 18px;
        }

        .category-name {
          font-size: 14px;
        }

        .category-count {
          font-size: 15px;
        }

        .empty-state {
          padding: 35px 15px;
        }

        .empty-state .material-symbols-outlined {
          font-size: 44px;
        }

        .empty-action {
          padding: 11px 20px;
          font-size: 14px;
        }

        .prize-badge {
          font-size: 12px;
          padding: 3px 7px;
        }
      }

      /* Ensure no horizontal overflow */
      * {
        max-width: 100%;
        box-sizing: border-box;
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

      /* Notification Dropdown/Modal */
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
        pointer-events: auto;
        user-select: none;
        -webkit-user-select: none;
      }

      .notification-item:hover {
        background: #f9f9f9;
      }

      .notification-item:active {
        background: #e8eef5;
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
        pointer-events: none;
      }

      .notification-item-content {
        flex: 1;
        min-width: 0;
        pointer-events: none;
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

      /* Overlay */
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
          top: 12px;
          right: 15px;
          z-index: 1003;
          pointer-events: auto;
        }

        .notification-bell {
          width: 40px;
          height: 40px;
          margin: 0;
          pointer-events: auto;
          -webkit-tap-highlight-color: transparent;
          touch-action: manipulation;
        }

        .notification-bell .material-symbols-outlined {
          font-size: 20px;
          pointer-events: none;
        }

        .notification-dropdown {
          position: fixed;
          top: 70px;
          right: 0;
          left: 0;
          bottom: 0;
          width: calc(100% - 40px);
          max-height: 80vh;
          margin: 0 20px;
          border-radius: 15px 15px 0 0;
          box-shadow: 0 -8px 25px rgba(0,0,0,0.15);
          z-index: 1004;
        }
      }
    </style>
  </head>
  <body>
    <!-- <button class="mobile-menu-btn" onclick="toggleSidebar()">
      <span class="material-symbols-outlined">menu</span>
    </button> -->

    <div class="grid-container">
      <!-- header -->
      <div class="header">
        <div class="menu-icon">
          <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="icon">
          <img
            src="sona_logo.jpg"
            alt="Sona College Logo"
            height="60px"
            width="200"
          />
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
      <!-- sidebar -->
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
              <a href="#" class="nav-link active">
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
              <a href="hackathons.php" class="nav-link">
                <span class="material-symbols-outlined">emoji_events</span>
                Hackathons
              </a>
            </li>
            <li class="nav-item">
              <a href="od_request.php" class="nav-link">
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
      <!-- main container -->
      <div class="main">
        <!-- Welcome Section -->
        <div class="welcome-section">
          <h1>Welcome back,<?php echo htmlspecialchars(explode(' ', $student_data['name'])[0], ENT_QUOTES, 'UTF-8'); ?>!</h1>
          <p>Track your event participations and achievements</p>
        </div>

        <!-- cards  -->
        <div class="main-card">
          <div class="card">
            <div class="card-inner">
              <h3>Total Events Participated</h3>
              <span class="material-symbols-outlined">school</span>
            </div>
            <h1><?php echo $total_events; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Total Events Won</h3>
              <span class="material-symbols-outlined">emoji_events</span>
            </div>
            <h1><?php echo $events_won; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Success Rate</h3>
              <span class="material-symbols-outlined">trending_up</span>
            </div>
            <h1><?php echo $total_events > 0 ? round(($events_won / $total_events) * 100, 1) : 0; ?>%</h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Internships Completed</h3>
              <span class="material-symbols-outlined">work</span>
            </div>
            <h1><?php echo $internship_count; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>OD Requests</h3>
              <span class="material-symbols-outlined">request_page</span>
            </div>
            <div class="od-stats">
              <div class="od-stat-item">
                <span class="od-count"><?php echo $od_stats['total_od_requests'] ?? 0; ?></span>
                <span class="od-label">Total</span>
              </div>
              <div class="od-stat-item pending">
                <span class="od-count"><?php echo $od_stats['pending_od'] ?? 0; ?></span>
                <span class="od-label">Pending</span>
              </div>
              <div class="od-stat-item approved">
                <span class="od-count"><?php echo $od_stats['approved_od'] ?? 0; ?></span>
                <span class="od-label">Approved</span>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Quick Actions</h3>
              <span class="material-symbols-outlined">bolt</span>
            </div>
            <div class="quick-actions">
              <a href="student_register.php" class="action-btn-card">
                <span class="material-symbols-outlined">add</span>
                Register Event
              </a>
              <a href="od_request.php" class="action-btn-card">
                <span class="material-symbols-outlined">request_page</span>
                OD Request
              </a>
              <a href="student_participations.php" class="action-btn-card secondary">
                <span class="material-symbols-outlined">visibility</span>
                View All Events
              </a>
            </div>
          </div>
        </div>

        <!-- Recent Activities and Event Categories Section -->
        <div class="content-grid">
          <!-- Recent Activities -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">schedule</span>
              <h3>Recent Activities</h3>
              <a href="student_participations.php" class="view-all-link">View All</a>
            </div>

            <?php if ($recent_events->num_rows > 0): ?>
              <div class="activities-list">
                <?php foreach ($recent_events->data as $event): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <span class="material-symbols-outlined">event</span>
                    </div>
                    <div class="activity-details">
                      <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                      <p class="activity-meta">
                        <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                        <span class="event-date">
                          <?php
                              if ($event['start_date'] === $event['end_date']) {
                                  echo date('M d, Y', strtotime($event['start_date']));
                              } else {
                                  echo date('M d', strtotime($event['start_date'])) . ' - ' . date('M d, Y', strtotime($event['end_date']));
                              }
                          ?>
                          (<?php echo $event['no_of_days']; ?> day<?php echo $event['no_of_days'] > 1 ? 's' : ''; ?>)
                        </span>
                        <?php if (! empty($event['prize']) && $event['prize'] !== 'No Prize'): ?>
                          <span class="prize-badge">🏆<?php echo htmlspecialchars($event['prize']); ?></span>
                        <?php endif; ?>
                      </p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <p>No recent activities</p>
                <a href="student_register.php" class="empty-action">Register your first event</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Recent OD Requests -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">request_page</span>
              <h3>Recent OD Requests</h3>
              <a href="od_request.php" class="view-all-link">View All</a>
            </div>

            <?php if ($recent_od_requests->num_rows > 0): ?>
              <div class="od-requests-list">
                <?php foreach ($recent_od_requests->data as $od_request): ?>
                  <div class="od-request-item">
                    <div class="od-request-icon">
                      <span class="material-symbols-outlined">description</span>
                    </div>
                    <div class="od-request-details">
                      <h4><?php echo htmlspecialchars($od_request['event_name']); ?></h4>
                      <p class="od-request-meta">
                        <span class="od-status                                                                                                                                                                                                                                                                                                                                   <?php echo htmlspecialchars($od_request['status'], ENT_QUOTES, 'UTF-8'); ?>">
                          <?php echo ucfirst($od_request['status']); ?>
                        </span>
                        <span class="od-date"><?php echo date('M d, Y', strtotime($od_request['event_date'])); ?></span>
                      </p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">description</span>
                <p>No OD requests yet</p>
                <a href="od_request.php" class="empty-action">Submit your first OD request</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Event Categories -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">pie_chart</span>
              <h3>Event Categories</h3>
            </div>

            <?php if ($event_types->num_rows > 0): ?>
              <div class="categories-list">
                <?php foreach ($event_types->data as $type): ?>
                  <div class="category-item">
                    <div class="category-info">
                      <span class="category-name"><?php echo htmlspecialchars($type['event_type']); ?></span>
                      <div class="category-progress">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo $total_events > 0 ? ($type['count'] / $total_events) * 100 : 0; ?>%"></div>
                        </div>
                      </div>
                    </div>
                    <span class="category-count"><?php echo $type['count']; ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">category</span>
                <p>No event categories yet</p>
                <a href="student_register.php" class="empty-action">Start participating</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- charts -->
      </div>
    </div>

    <!-- Include optimized dashboard manager -->
    <script src="js/dashboard-manager.js"></script>

    <!-- Scripts -->
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
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const sidebar = document.getElementById('sidebar');

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', toggleSidebar);
            }

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
                    (!mobileMenuBtn || !mobileMenuBtn.contains(event.target)) &&
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

            // ============================================================================
            // NOTIFICATION SYSTEM
            // ============================================================================

            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationOverlay = document.createElement('div');
            notificationOverlay.className = 'notification-overlay';
            document.body.appendChild(notificationOverlay);
            let lastNotificationId = null;

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

                const newestNotification = notifications[0];
                if (newestNotification && newestNotification.id) {
                  if (lastNotificationId && newestNotification.id !== lastNotificationId) {
                    if (window.pushManager && window.pushManager.handleIncomingNotification) {
                      window.pushManager.handleIncomingNotification({
                        title: newestNotification.hackathon_title || 'New Notification',
                        body: newestNotification.message || 'You have a new update.',
                        url: newestNotification.link || 'hackathons.php'
                      });
                    }
                  }
                  lastNotificationId = newestNotification.id;
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

                    // Add click handler directly with proper delegation
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
                // Already a full absolute path
                if (link.startsWith('/event_management_system/')) return link;
                // Relative path starting with /student/
                if (link.startsWith('/student/')) return '/event_management_system/login' + link;
                // Relative path starting with student/
                if (link.startsWith('student/')) return '/event_management_system/login/' + link;
                // Relative filename like hackathons.php
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

                // Immediately clear UI
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
                // Immediately update UI for instant feedback
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
        });
    </script>
      <!-- Push Notifications Manager for Median.co -->
</body>
</html>

<?php
    // Clean up cache periodically (1% chance)
    if (rand(1, 100) === 1) {
    $cache->cleanup();
    }
?>
