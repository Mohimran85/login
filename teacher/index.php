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
    $username       = $_SESSION['username'];
    $teacher_data   = null;
    $teacher_status = 'teacher'; // Default status
    $is_admin       = false;

    // Try to get teacher data from teacher_register table first
    $sql  = "SELECT name, faculty_id as employee_id, COALESCE(status, 'teacher') as status FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data   = $result->fetch_assoc();
        $teacher_status = $teacher_data['status'];
        $is_admin       = ($teacher_status === 'admin');
    } else {
        // Fallback: Check if username exists in student_register table
        $sql2  = "SELECT name, regno as employee_id FROM student_register WHERE username=?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows > 0) {
            $teacher_data = $result2->fetch_assoc();
        } else {
            // If no data found anywhere, create a default entry
            $teacher_data = [
                'name'        => ucfirst($username),                            // Use username as name
                'employee_id' => 'TEMP-' . strtoupper(substr($username, 0, 4)), // Generate temp ID
            ];
        }
        if (isset($stmt2)) {
            $stmt2->close();
        }

    }

    // Get comprehensive statistics for teacher
    $teacher_id = $teacher_data['employee_id'];

    // Total events registered by this teacher in staff_event_reg
    $total_events_sql = "SELECT COUNT(*) as total FROM staff_event_reg WHERE staff_id=? OR name=?";
    $total_stmt       = $conn->prepare($total_events_sql);
    if ($total_stmt) {
        $total_stmt->bind_param("ss", $teacher_id, $teacher_data['name']);
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
                         'completed' as status,
                         organisation, sponsors
                         FROM staff_event_reg
                         WHERE staff_id=? OR name=?
                         ORDER BY event_date DESC, id DESC LIMIT 5";
    $recent_stmt = $conn->prepare($recent_events_sql);
    if ($recent_stmt) {
        $recent_stmt->bind_param("ss", $teacher_id, $teacher_data['name']);
        $recent_stmt->execute();
        $recent_events = $recent_stmt->get_result();
    } else {
        // Fallback: create empty result
        $recent_events = null;
    }

    // Event type breakdown for teacher's events
    $event_types_sql = "SELECT event_type, COUNT(*) as count FROM staff_event_reg
                       WHERE staff_id=? OR name=?
                       GROUP BY event_type ORDER BY count DESC LIMIT 8";
    $types_stmt = $conn->prepare($event_types_sql);
    if ($types_stmt) {
        $types_stmt->bind_param("ss", $teacher_id, $teacher_data['name']);
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

    // Get recently registered students (last 10)
    $recent_students_sql = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join,
                                  ser.event_name, ser.event_type, ser.attended_date,
                                  ser.prize, ser.organisation
                           FROM student_register sr
                           JOIN student_event_register ser ON sr.regno = ser.regno
                           ORDER BY ser.attended_date DESC, ser.id DESC
                           LIMIT 10";
    $recent_students_result = $conn->query($recent_students_sql);

    // Get student registration statistics by event type
    $student_stats_sql = "SELECT ser.event_type, COUNT(DISTINCT ser.regno) as student_count,
                                COUNT(ser.id) as total_registrations
                         FROM student_event_register ser
                         GROUP BY ser.event_type
                         ORDER BY student_count DESC";
    $student_stats_result = $conn->query($student_stats_sql);

    // Get top performing students (students with prizes)
    $top_students_sql = "SELECT sr.name, sr.regno, sr.department,
                               COUNT(ser.id) as total_events,
                               SUM(CASE WHEN ser.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prizes_won
                        FROM student_register sr
                        JOIN student_event_register ser ON sr.regno = ser.regno
                        GROUP BY sr.regno, sr.name, sr.department
                        HAVING prizes_won > 0
                        ORDER BY prizes_won DESC, total_events DESC
                        LIMIT 5";
    $top_students_result = $conn->query($top_students_sql);

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

    <style>
        /* Teacher Dashboard Specific Styles */
        .main {
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        .welcome-section {
            background: linear-gradient(135deg, #2d5aa0 0%, #1e3a6f 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(45, 90, 160, 0.3);
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .main-card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .card-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-inner h3 {
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .card-inner .material-symbols-outlined {
            font-size: 2.5rem;
            color: #2d5aa0;
            opacity: 0.8;
        }

        .card h1 {
            font-size: 2.5rem;
            color: #2d5aa0;
            font-weight: 700;
            margin: 0;
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn-card {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #2d5aa0;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .action-btn-card:hover {
            background: #1e3a6f;
            transform: translateX(5px);
        }

        .action-btn-card.secondary {
            background: #6c757d;
        }

        .action-btn-card.secondary:hover {
            background: #5a6268;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .card-header .material-symbols-outlined {
            color: #2d5aa0;
            font-size: 1.5rem;
        }

        .card-header h3 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0;
            flex: 1;
        }

        .view-all-link {
            color: #2d5aa0;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .view-all-link:hover {
            color: #1e3a6f;
        }

        .activities-list {
            padding: 0;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px 25px;
            border-bottom: 1px solid #f1f3f4;
            transition: background 0.3s ease;
        }

        .activity-item:hover {
            background: #f8f9fa;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            background: #e3f2fd;
            color: #2d5aa0;
            padding: 10px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details h4 {
            color: #2c3e50;
            font-weight: 600;
            margin: 0 0 8px 0;
            font-size: 1rem;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 5px 0;
            font-size: 0.85rem;
        }

        .event-type {
            /* background: #e9ecef; */
            color: #ffffffff;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .event-date {
            color: #6c757d;
            font-weight: 500;
        }

        .prize-badge {
            background: #d1ecf1;
            color: #0c5460;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 25px;
        }

        .empty-state .material-symbols-outlined {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 15px;
            font-weight: 500;
        }

        .empty-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #2d5aa0;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .empty-action:hover {
            background: #1e3a6f;
            transform: translateY(-2px);
        }

        .categories-list {
            padding: 20px 25px;
        }

        .category-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .category-item:last-child {
            margin-bottom: 0;
        }

        .category-info {
            flex: 1;
            margin-right: 15px;
        }

        .category-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: block;
        }

        .category-progress {
            width: 100%;
        }

        .progress-bar {
            background: #e9ecef;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(90deg, #2d5aa0, #1e3a6f);
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .category-count {
            background: #2d5aa0;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
                padding-top: 80px;
            }

            .header .menu-icon {
                display: block;
            }

            .header .icon {
                display: none;
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
                z-index: 1004 !important;
                background: #ffffff !important;
                box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
                transition: transform 0.3s ease !important;
                padding: 20px 0 !important;
                overflow-y: auto !important;
            }

            .close-sidebar {
                display: flex !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 1005 !important;
            }

            .sidebar.active::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                z-index: -1;
                backdrop-filter: blur(2px);
                background: rgba(0, 0, 0, 0.3);
            }

            .main {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 15px;
            }

            .welcome-section {
                padding: 20px;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }

            .main-card {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .activity-item {
                padding: 15px 20px;
            }

            .card {
                padding: 20px;
            }
        }

        /* Alert Styles */
        .alert {
            margin: 20px 0;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert strong {
            font-weight: 600;
        }
    </style>
  </head>
  <body>
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
          <div class="sidebar-title"><?php echo $is_admin ? 'Admin Portal' : 'Teacher Portal'; ?></div>
          <div class="close-sidebar">
            <span class="material-symbols-outlined">close</span>
          </div>
        </div>

        <div class="student-info">
          <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
          <div class="student-regno">ID:                                         <?php echo htmlspecialchars($teacher_data['employee_id']); ?> <?php echo $is_admin ? '(Admin)' : ''; ?></div>
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
                Add Event Record
              </a>
            </li>
            <li class="nav-item">
              <a href="my_events.php" class="nav-link">
                <span class="material-symbols-outlined">calendar_month</span>
                My Events
              </a>
            </li>
            <li class="nav-item">
              <a href="registered_students.php" class="nav-link">
                <span class="material-symbols-outlined">group</span>
                Registered Students
              </a>
            </li>
            <?php if ($is_admin): ?>
            <li class="nav-item">
              <a href="../admin/index.php" class="nav-link">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                Admin Dashboard
              </a>
            </li>
            <li class="nav-item">
              <a href="../admin/user_management.php" class="nav-link">
                <span class="material-symbols-outlined">manage_accounts</span>
                User Management
              </a>
            </li>
            <li class="nav-item">
              <a href="../admin/participants.php" class="nav-link">
                <span class="material-symbols-outlined">people</span>
                Participants
              </a>
            </li>
            <li class="nav-item">
              <a href="../admin/reports.php" class="nav-link">
                <span class="material-symbols-outlined">bar_chart</span>
                Reports
              </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
              <a href="../admin/user_management.php" class="nav-link">
                <span class="material-symbols-outlined">manage_accounts</span>
                Teacher Management
              </a>
            </li>
            <?php endif; ?>
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
          <h1>Welcome back,                                                                                                                                                                                                                         <?php echo explode(' ', $teacher_data['name'])[0]; ?>!</h1>
          <p>Add your completed professional development events and track your achievements</p>
        </div>

        <!-- Access Denied Alert -->
        <?php if (isset($_SESSION['access_denied'])): ?>
        <div class="alert alert-warning" style="margin: 20px 0; padding: 15px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; color: #856404;">
          <strong>⚠️ Access Restricted:</strong>                                                                                                         <?php echo $_SESSION['access_denied']; ?>
          <?php unset($_SESSION['access_denied']); // Clear the message after displaying ?>
        </div>
        <?php endif; ?>

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
                Add Event Record
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
                          ✅ Completed
                        </span>
                      </p>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">event_busy</span>
                <p>No events recorded yet</p>
                <a href="staff_event_reg.php" class="empty-action">Add your first completed event</a>
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
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $total_events > 0 ? ($type['count'] / $total_events) * 100 : 0; ?>%"></div>
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
                <a href="staff_event_reg.php" class="empty-action">Start adding completed events</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Additional Teacher-specific sections -->
        <div class="content-grid" style="margin-top: 30px;">
          <!-- Recently Registered Students -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">group</span>
              <h3>Recently Registered Students</h3>
              <a href="registered_students.php" class="view-all-link">View All</a>
            </div>

            <?php if ($recent_students_result && $recent_students_result->num_rows > 0): ?>
              <div class="activities-list">
                <?php while ($student = $recent_students_result->fetch_assoc()): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <span class="material-symbols-outlined">person</span>
                    </div>
                    <div class="activity-details">
                      <h4><?php echo htmlspecialchars($student['name']); ?></h4>
                      <p class="activity-meta">
                        <span class="event-type"><?php echo htmlspecialchars($student['regno']); ?></span>
                        <span class="event-date"><?php echo htmlspecialchars($student['department']); ?></span>
                        <span class="prize-badge">
                          <?php echo htmlspecialchars($student['event_name']); ?>
                        </span>
                      </p>
                      <p class="activity-meta">
                        <small style="color: #666;">
                          <?php echo date('M d, Y', strtotime($student['attended_date'])); ?>
                          <?php if (! empty($student['prize']) && $student['prize'] !== 'None'): ?>
                            | 🏆<?php echo htmlspecialchars($student['prize']); ?>
                          <?php endif; ?>
                        </small>
                      </p>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">group_off</span>
                <p>No student registrations yet</p>
                <a href="registered_students.php" class="empty-action">View all registrations</a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Student Performance and Registration Statistics -->
        <div class="content-grid" style="margin-top: 30px;">
          <!-- Top Performing Students -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">star</span>
              <h3>Top Performing Students</h3>
              <a href="registered_students.php" class="view-all-link">View All</a>
            </div>

            <?php if ($top_students_result && $top_students_result->num_rows > 0): ?>
              <div class="categories-list">
                <?php while ($student = $top_students_result->fetch_assoc()): ?>
                  <div class="category-item">
                    <div class="category-info">
                      <span class="category-name">
                        <?php echo htmlspecialchars($student['name']); ?>
                        <small style="display: block; color: #666; font-size: 12px;">
                          <?php echo htmlspecialchars($student['regno']); ?> |<?php echo htmlspecialchars($student['department']); ?>
                        </small>
                      </span>
                      <div class="category-progress">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo min(($student['prizes_won'] / 3) * 100, 100); ?>%"></div>
                        </div>
                      </div>
                    </div>
                    <div style="text-align: center;">
                      <div style="font-weight: bold; color: #f39c12;">🏆                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $student['prizes_won']; ?></div>
                      <small style="color: #666; font-size: 11px;"><?php echo $student['total_events']; ?> events</small>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">emoji_events</span>
                <p>No prize winners yet</p>
                <a href="registered_students.php" class="empty-action">View all students</a>
              </div>
            <?php endif; ?>
          </div>

          <!-- Event Type Registration Stats -->
          <div class="content-card">
            <div class="card-header">
              <span class="material-symbols-outlined">analytics</span>
              <h3>Student Registration by Event Type</h3>
            </div>

            <?php if ($student_stats_result && $student_stats_result->num_rows > 0): ?>
              <div class="categories-list">
                <?php while ($stat = $student_stats_result->fetch_assoc()): ?>
                  <div class="category-item">
                    <div class="category-info">
                      <span class="category-name"><?php echo htmlspecialchars($stat['event_type']); ?></span>
                      <div class="category-progress">
                        <div class="progress-bar">
                          <div class="progress-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo $total_participants > 0 ? ($stat['student_count'] / $total_participants) * 100 : 0; ?>%"></div>
                        </div>
                      </div>
                    </div>
                    <div style="text-align: center;">
                      <div style="font-weight: bold; color: #3498db;"><?php echo $stat['student_count']; ?></div>
                      <small style="color: #666; font-size: 11px;"><?php echo $stat['total_registrations']; ?> reg.</small>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <span class="material-symbols-outlined">bar_chart</span>
                <p>No registration statistics available</p>
                <a href="registered_students.php" class="empty-action">View registrations</a>
              </div>
            <?php endif; ?>
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
