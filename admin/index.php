<?php
    session_start();

    // Prevent caching to avoid back button issues
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        // User is not logged in, redirect to login page
        header("Location: ../index.php");
        exit();
    }

    // Get user data for header profile
    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username  = $_SESSION['username'];
    $user_data = null;
    $user_type = "";
    $tables    = ['student_register', 'teacher_register'];

    foreach ($tables as $table) {
        $sql  = "SELECT name FROM $table WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $user_type = $table === 'student_register' ? 'student' : 'teacher';
            break;
        }
        $stmt->close();
    }

    // Get dashboard statistics
    $total_students       = 0;
    $total_teachers       = 0;
    $total_events         = 0;
    $total_participations = 0;

    // Count total students
    $student_sql    = "SELECT COUNT(*) as count FROM student_register";
    $student_result = $conn->query($student_sql);
    if ($student_result) {
        $total_students = $student_result->fetch_assoc()['count'];
    } else {
        // Fallback value if query fails
        $total_students = 0;
    }

    // Count total teachers
    $teacher_sql    = "SELECT COUNT(*) as count FROM teacher_register";
    $teacher_result = $conn->query($teacher_sql);
    if ($teacher_result) {
        $total_teachers = $teacher_result->fetch_assoc()['count'];
    } else {
        // Fallback value if query fails
        $total_teachers = 0;
    }

    // Count total events from both student and staff registrations
    $student_events_sql    = "SELECT COUNT(DISTINCT event_name) as count FROM student_event_register";
    $student_events_result = $conn->query($student_events_sql);
    $student_events_count  = $student_events_result ? $student_events_result->fetch_assoc()['count'] : 0;

    $staff_events_sql    = "SELECT COUNT(DISTINCT topic) as count FROM staff_event_reg";
    $staff_events_result = $conn->query($staff_events_sql);
    $staff_events_count  = $staff_events_result ? $staff_events_result->fetch_assoc()['count'] : 0;

    $total_events = $student_events_count + $staff_events_count;

    // Count total participations (students + staff)
    $student_participation_sql    = "SELECT COUNT(*) as count FROM student_event_register";
    $student_participation_result = $conn->query($student_participation_sql);
    $student_participations       = $student_participation_result ? $student_participation_result->fetch_assoc()['count'] : 0;

    $staff_participation_sql    = "SELECT COUNT(*) as count FROM staff_event_reg";
    $staff_participation_result = $conn->query($staff_participation_sql);
    $staff_participations       = $staff_participation_result ? $staff_participation_result->fetch_assoc()['count'] : 0;

    $total_participations = $student_participations + $staff_participations;

    // Get chart data - Events by Category (combining student and staff events)
    $category_data   = [];
    $category_counts = [];

    // Get student event types
    $student_category_sql = "SELECT event_type, COUNT(*) as count FROM student_event_register
                            WHERE event_type IS NOT NULL AND event_type != ''
                            GROUP BY event_type";
    $student_category_result = $conn->query($student_category_sql);

    // Get staff event types
    $staff_category_sql = "SELECT event_type, COUNT(*) as count FROM staff_event_reg
                          WHERE event_type IS NOT NULL AND event_type != ''
                          GROUP BY event_type";
    $staff_category_result = $conn->query($staff_category_sql);

    // Combine the results
    $combined_categories = [];

    if ($student_category_result) {
        while ($row = $student_category_result->fetch_assoc()) {
            $combined_categories[$row['event_type']] = (int) $row['count'];
        }
    }

    if ($staff_category_result) {
        while ($row = $staff_category_result->fetch_assoc()) {
            if (isset($combined_categories[$row['event_type']])) {
                $combined_categories[$row['event_type']] += (int) $row['count'];
            } else {
                $combined_categories[$row['event_type']] = (int) $row['count'];
            }
        }
    }

    // Sort by count and limit to top 10
    arsort($combined_categories);
    $combined_categories = array_slice($combined_categories, 0, 10, true);

    if (! empty($combined_categories)) {
        $category_data   = array_keys($combined_categories);
        $category_counts = array_values($combined_categories);
    } else {
        // Fallback data - using realistic event categories for testing
        $category_data   = ['Hackathon', 'Workshop', 'Technical Seminar', 'Cultural Event', 'Sports', 'Conference'];
        $category_counts = [15, 25, 18, 12, 8, 10];
    }

    // Get monthly trend data
    $monthly_events         = [];
    $monthly_participations = [];
    $months                 = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    for ($i = 1; $i <= 12; $i++) {
        $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);

        // Count unique events per month
        $events_sql = "SELECT COUNT(DISTINCT event_name) as count FROM student_event_register
                       WHERE attended_date LIKE '%-$month_num-%' OR attended_date LIKE '%/$month_num/%'";
        $events_result    = $conn->query($events_sql);
        $monthly_events[] = $events_result ? (int) $events_result->fetch_assoc()['count'] : 0;

        // Count participations per month
        $parts_sql = "SELECT COUNT(*) as count FROM student_event_register
                      WHERE attended_date LIKE '%-$month_num-%' OR attended_date LIKE '%/$month_num/%'";
        $parts_result             = $conn->query($parts_sql);
        $monthly_participations[] = $parts_result ? (int) $parts_result->fetch_assoc()['count'] : 0;
    }

    // If no data found, use sample data for demonstration
    if (array_sum($monthly_events) == 0) {
        $monthly_events         = [5, 8, 12, 15, 10, 18, 22, 16, 14, 20, 25, 18];
        $monthly_participations = [45, 52, 68, 75, 60, 88, 95, 72, 65, 85, 105, 90];
    }

    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Event Admin Dashboard</title>
    <!-- css link -->
    <link rel="stylesheet" href="./CSS/styles.css" />
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
    <div class="grid-container">
      <!-- header -->
      <div class="header">
         <div class="menu-icon" onclick="openSidebar()">
          <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="header-logo">
          <img
            class="logo"
            src="./asserts/sona_logo.jpg"
            alt="Sona College Logo"
            height="60px"
            width="200"
          />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div class="header-profile">
          <div class="profile-info" onclick="navigateToProfile()">
            <span class="material-symbols-outlined">account_circle</span>
            <div class="profile-details">
              <span class="profile-name"><?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?></span>
              <span class="profile-role"><?php echo ucfirst($user_type); ?></span>
            </div>
          </div>
        </div>
      </div>
      <!-- sidebar -->
      <aside id="sidebar">
        <div class="sidebar-title">
          <div class="sidebar-band">
            <h2 style="color: white; padding: 10px">Admin Panel</h2>
            <span class="material-symbols-outlined"  onclick="closeSidebar()">close</span>
          </div>
          <ul class="sidebar-list">
            <li class="sidebar-list-item active">
              <span class="material-symbols-outlined">dashboard</span>
              <a href="index.php">Home</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">event</span>
              <a href="add_event.php">Add Events</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">people</span>
              <a href="participants.php">Participants</a>
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
      <!-- main container -->
      <div class="main">
        <!-- cards  -->
        <div class="main-card">
          <div class="card">
            <div class="card-inner">
              <h3>Total Students:</h3>
              <span class="material-symbols-outlined">school</span>
            </div>
            <h1><?php echo number_format($total_students); ?></h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total Teachers:</h3>
              <span class="material-symbols-outlined">person_book</span>
            </div>
            <h1><?php echo number_format($total_teachers); ?></h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total Events:</h3>
              <span class="material-symbols-outlined">event</span>
            </div>
            <h1><?php echo number_format($total_events); ?></h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total Participations:</h3>
              <span class="material-symbols-outlined">groups</span>
            </div>
            <h1><?php echo number_format($total_participations); ?></h1>
          </div>
        </div>
        <!-- charts -->
        <div class="charts">
          <div class="charts-card">
            <h2 class="chart-title">Events by Category</h2>
            <div id="bar-chart"></div>
          </div>

          <div class="charts-card">
            <h2 class="chart-title">Monthly Event Trends</h2>
            <div id="area-chart"></div>
          </div>
        </div>
      </div>

      <!-- Scripts -->
      <!-- js scripts-  -->
      <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/5.3.4/apexcharts.min.js"></script>

      <script>
      // Get PHP data for charts and make them globally available
      window.categoryData =                                                       <?php echo json_encode($category_data); ?>;
      window.categoryCounts =                                                           <?php echo json_encode($category_counts); ?>;
      window.monthlyEvents =                                                         <?php echo json_encode($monthly_events); ?>;
      window.monthlyParticipations =                                                                         <?php echo json_encode($monthly_participations); ?>;

      // Debug: Show the data in console
      console.log('PHP Data Loaded:');
      console.log('Categories:', window.categoryData);
      console.log('Counts:', window.categoryCounts);
      console.log('Monthly Events:', window.monthlyEvents);
      console.log('Monthly Participations:', window.monthlyParticipations);
      </script>

      <!-- CUSTOM JS -->
      <script src="./JS/scripts.js"></script>

    <script>
    // Prevent back button navigation
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };

    // Navigation function for header profile
    function navigateToProfile() {
      window.location.href = 'profile.php';
    }
    </script>

  </body>
</html>