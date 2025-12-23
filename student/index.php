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

    // Try to get dashboard data from cache
    $dashboard_cache_key = "dashboard_" . $regno;
    $dashboard_data      = $cache->get($dashboard_cache_key);

    if (! $dashboard_data) {
        // Get all dashboard data in optimized single query
        $dashboard_stats = $db->getStudentDashboardData($regno);

        // Get additional data with caching
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

        // Cache dashboard data for 5 minutes
        $cache->set($dashboard_cache_key, $dashboard_data, 300);
    }

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
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Student Dashboard - Event Management System</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="asserts/images/favicon_io/site.webmanifest">
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
          z-index: 999;
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
              <a href="od_request.php" class="nav-link">
                <span class="material-symbols-outlined">description</span>
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
          <h1>Welcome back,<?php echo explode(' ', $student_data['name'])[0]; ?>!</h1>
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
                        <span class="od-status                                                                                                                                                                                                                                                                                     <?php echo $od_request['status']; ?>">
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
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $total_events > 0 ? ($type['count'] / $total_events) * 100 : 0; ?>%"></div>
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
        });
    </script>
  </body>
</html>

<?php
    // Clean up cache periodically (1% chance)
    if (rand(1, 100) === 1) {
        $cache->cleanup();
    }
?>
