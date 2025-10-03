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

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;

    // Get teacher data from teacher_register table
    $sql  = "SELECT name, faculty_id as employee_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        // Fallback: use student data structure for now
        $sql  = "SELECT name, regno as employee_id FROM student_register WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $teacher_data = $result->fetch_assoc();
        } else {
            header("Location: ../index.php");
            exit();
        }
    }

    // Get comprehensive statistics for teacher
    $teacher_id = $teacher_data['employee_id'];

    // Total events registered by this teacher in staff_event_reg
    $total_events_sql = "SELECT COUNT(*) as total FROM staff_event_reg WHERE staff_id=?";
    $total_stmt       = $conn->prepare($total_events_sql);
    if ($total_stmt) {
        $total_stmt->bind_param("s", $teacher_id);
        $total_stmt->execute();
        $total_events = $total_stmt->get_result()->fetch_assoc()['total'];
        $total_stmt->close();
    } else {
        $total_events = 0;
    }

    // Total students participated in events (from student_event_register)
    $total_participants_sql = "SELECT COUNT(DISTINCT regno) as participants FROM student_event_register";
    $participants_result    = $conn->query($total_participants_sql);
    if ($participants_result) {
        $total_participants = $participants_result->fetch_assoc()['participants'];
    } else {
        $total_participants = 0;
    }

    // Recent events registered by this teacher (last 5)
    $recent_events_sql = "SELECT topic as event_name, event_type, event_date as start_date,
                         CASE WHEN event_date < CURDATE() THEN 'completed'
                              WHEN event_date = CURDATE() THEN 'ongoing'
                              ELSE 'upcoming' END as status,
                         organisation, sponsors
                         FROM staff_event_reg
                         WHERE staff_id=?
                         ORDER BY event_date DESC, id DESC LIMIT 5";
    $recent_stmt = $conn->prepare($recent_events_sql);
    if ($recent_stmt) {
        $recent_stmt->bind_param("s", $teacher_id);
        $recent_stmt->execute();
        $recent_events = $recent_stmt->get_result();
    } else {
        // Fallback: create empty result
        $recent_events = null;
    }

    // Event type breakdown for teacher's events
    $event_types_sql = "SELECT event_type, COUNT(*) as count FROM staff_event_reg
                       WHERE staff_id=?
                       GROUP BY event_type ORDER BY count DESC LIMIT 8";
    $types_stmt = $conn->prepare($event_types_sql);
    if ($types_stmt) {
        $types_stmt->bind_param("s", $teacher_id);
        $types_stmt->execute();
        $event_types = $types_stmt->get_result();
    } else {
        // Fallback: use student event data for chart
        $event_types_sql = "SELECT event_type, COUNT(*) as count FROM student_event_register
                           GROUP BY event_type ORDER BY count DESC LIMIT 8";
        $types_stmt = $conn->prepare($event_types_sql);
        $types_stmt->execute();
        $event_types = $types_stmt->get_result();
    }

    $stmt->close();
    if (isset($recent_stmt)) {
        $recent_stmt->close();
    }

    if (isset($types_stmt)) {
        $types_stmt->close();
    }

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Dashboard - Event Management System</title>
    <!-- css link -->
    <link rel="stylesheet" href="../student/student_dashboard.css" />
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
          <div class="student-regno">ID:                                                                                                                                                                 <?php echo htmlspecialchars($teacher_data['employee_id']); ?></div>
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
              <a href="staff_event_reg.php" class="nav-link">
                <span class="material-symbols-outlined">event_note</span>
                Event Registration
              </a>
            </li>
            <li class="nav-item">
              <a href="my_events.php" class="nav-link">
                <span class="material-symbols-outlined">calendar_month</span>
                My Events
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
          <h1>Welcome back,                            <?php echo explode(' ', $teacher_data['name'])[0]; ?>!</h1>
          <p>Register for professional development events and track your participation</p>
        </div>

        <!-- cards  -->
        <div class="main-card">
          <div class="card">
            <div class="card-inner">
              <h3>Events Registered</h3>
              <span class="material-symbols-outlined">event</span>
            </div>
            <h1><?php echo $total_events; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Total Students</h3>
              <span class="material-symbols-outlined">group</span>
            </div>
            <h1><?php echo $total_participants; ?></h1>
          </div>

          <div class="card">
            <div class="card-inner">
              <h3>Quick Actions</h3>
              <span class="material-symbols-outlined">bolt</span>
            </div>
            <div class="quick-actions">
              <a href="staff_event_reg.php" class="action-btn-card">
                <span class="material-symbols-outlined">add</span>
                Register Event
              </a>
              <a href="my_events.php" class="action-btn-card secondary">
                <span class="material-symbols-outlined">visibility</span>
                View My Events
              </a>
            </div>
          </div>
        </div>

        <!-- Recent Activities and Event Categories Section -->
        <div class="content-grid">
          <!-- Recent Events -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">schedule</span>
              <h3>My Recent Events</h3>
              <a href="my_events.php" class="view-all-link">View All</a>
            </div>

            <?php if ($recent_events->num_rows > 0): ?>
              <div class="activities-list">
                <?php while ($event = $recent_events->fetch_assoc()): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <span class="material-symbols-outlined">event</span>
                    </div>
                    <div class="activity-details">
                      <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                      <p class="activity-meta">
                        <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                        <span class="event-date"><?php echo date('M d, Y', strtotime($event['start_date'])); ?></span>
                        <span class="prize-badge">
                          <?php
                              $status     = $event['status'] ?? 'completed';
                              $statusIcon = $status === 'active' ? '🟢' : ($status === 'upcoming' ? '🟡' : '✅');
                              echo $statusIcon . ucfirst($status);
                          ?>
                        </span>
                      </p>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <p>No events registered yet</p>
                <a href="staff_event_reg.php" class="empty-action">Register for your first event</a>
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
                <?php while ($type = $event_types->fetch_assoc()): ?>
                  <div class="category-item">
                    <div class="category-info">
                      <span class="category-name"><?php echo htmlspecialchars($type['event_type']); ?></span>
                      <div class="category-progress">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                         <?php echo $total_events > 0 ? ($type['count'] / $total_events) * 100 : 0; ?>%"></div>
                        </div>
                      </div>
                    </div>
                    <span class="category-count"><?php echo $type['count']; ?></span>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">category</span>
                <p>No event categories yet</p>
                <a href="staff_event_reg.php" class="empty-action">Start registering for events</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Additional Teacher-specific sections -->
        <div class="content-grid" style="margin-top: 30px;">
          <!-- Upcoming Events -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">upcoming</span>
              <h3>Upcoming Events</h3>
              <a href="my_events.php" class="view-all-link">View All</a>
            </div>
            <div class="empty-state">
              <span class="material-symbols-outlined">event_upcoming</span>
              <p>No upcoming events</p>
              <a href="staff_event_reg.php" class="empty-action">Register for events</a>
            </div>
          </div>

          <!-- Quick Stats -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">insights</span>
              <h3>My Statistics</h3>
            </div>
            <div class="categories-list">
              <div class="category-item">
                <div class="category-info">
                  <span class="category-name">This Month's Events</span>
                </div>
                <span class="category-count">0</span>
              </div>
              <div class="category-item">
                <div class="category-info">
                  <span class="category-name">Completed Events</span>
                </div>
                <span class="category-count">0</span>
              </div>
              <div class="category-item">
                <div class="category-info">
                  <span class="category-name">Total Registrations</span>
                </div>
                <span class="category-count"><?php echo $total_events; ?></span>
              </div>
            </div>
          </div>
        </div>
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
