<?php
    session_start();
    require_once 'config.php';

    // Require teacher role
    require_teacher_role();

    // Get database connection
    $conn = get_db_connection();

    // Get teacher data
    $username = $_SESSION['username'];
    $teacher_data = null;

    $sql = "SELECT id, name, employee_id, email FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $_SESSION['teacher_id'] = $teacher_data['id'];
    } else {
        header("Location: ../index.php");
        exit();
    }
    // Get teacher statistics
    $teacher_id = $teacher_data['id'];

    // Get count of assigned students
    $students_sql = "SELECT COUNT(DISTINCT id) as total FROM student_register WHERE counselor_id=?";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("i", $teacher_id);
    $students_stmt->execute();
    $total_students = $students_stmt->get_result()->fetch_assoc()['total'];

    // Get count of events created/managed
    $events_sql = "SELECT COUNT(*) as total FROM events WHERE created_by=?";
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param("i", $teacher_id);
    $events_stmt->execute();
    $total_events = $events_stmt->get_result()->fetch_assoc()['total'];

    // Recent activities
    $recent_sql = "SELECT e.event_name, e.event_date, COUNT(ser.id) as participants 
                   FROM events e 
                   LEFT JOIN student_event_register ser ON e.id = ser.event_id 
                   WHERE e.created_by=? 
                   GROUP BY e.id 
                   ORDER BY e.event_date DESC 
                   LIMIT 5";
    $recent_stmt = $conn->prepare($recent_sql);
    $recent_stmt->bind_param("i", $teacher_id);
    $recent_stmt->execute();
    $recent_activities = $recent_stmt->get_result();

    $stmt->close();
    $students_stmt->close();
    $events_stmt->close();
    $recent_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Dashboard - Event Management System</title>
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
            src="../asserts/images/Sona Logo.png"
            alt="Sona College Logo"
          />
        </div>
        <div class="header-title">
          <p>Event Management System</p>
        </div>
      </div>
      <!-- sidebar -->
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
              <a href="#" class="nav-link active">
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
          <h1>Welcome back, <?php 
            $name_parts = explode(' ', $teacher_data['name']);
            echo htmlspecialchars($name_parts[0] ?? 'Teacher'); 
          ?>!</h1>
          <p>Manage students and events</p>
        </div>

        <!-- cards  -->
        <div class="main-card">
          <div class="card">
            <div class="card-inner">
              <h3>Assigned Students</h3>
              <span class="material-symbols-outlined">school</span>
            </div>
            <h1><?php echo $total_students; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Events Managed</h3>
              <span class="material-symbols-outlined">emoji_events</span>
            </div>
            <h1><?php echo $total_events; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Quick Actions</h3>
              <span class="material-symbols-outlined">bolt</span>
            </div>
            <div class="quick-actions">
              <a href="digital_signature.php" class="action-btn-card">
                <span class="material-symbols-outlined">draw</span>
                Digital Signature
              </a>
              <a href="registered_students.php" class="action-btn-card secondary">
                <span class="material-symbols-outlined">group</span>
                View Students
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
              <a href="registered_students.php" class="view-all-link">View All Students</a>
            </div>

            <?php if ($recent_activities->num_rows > 0): ?>
              <div class="activities-list">
                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <span class="material-symbols-outlined">event</span>
                    </div>
                    <div class="activity-details">
                      <h4><?php echo htmlspecialchars($activity['event_name']); ?></h4>
                      <p class="activity-meta">
                        <span class="event-date"><?php echo date('M d, Y', strtotime($activity['event_date'])); ?></span>
                        <span class="participants"><?php echo $activity['participants']; ?> participants</span>
                      </p>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <p>No recent activities</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Event Categories -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">pie_chart</span>
              <h3>Quick Links</h3>
            </div>
            <div class="categories-list">
              <div class="category-item">
                <a href="digital_signature.php" class="category-link">
                  <span class="material-symbols-outlined">draw</span>
                  <span>Digital Signature</span>
                </a>
              </div>
              <div class="category-item">
                <a href="registered_students.php" class="category-link">
                  <span class="material-symbols-outlined">group</span>
                  <span>View Students</span>
                </a>
              </div>
              <div class="category-item">
                <a href="profile.php" class="category-link">
                  <span class="material-symbols-outlined">person</span>
                  <span>My Profile</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- charts -->
      </div>
    </div>

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
    $conn->close();
?>
