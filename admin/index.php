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

    // Check teacher role/status if user is a teacher
    if ($user_type === 'teacher') {
        $teacher_status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
        $teacher_status_stmt = $conn->prepare($teacher_status_sql);
        $teacher_status_stmt->bind_param("s", $username);
        $teacher_status_stmt->execute();
        $teacher_status_result = $teacher_status_stmt->get_result();

        $teacher_status = 'teacher'; // Default status
        if ($teacher_status_result->num_rows > 0) {
            $status_data    = $teacher_status_result->fetch_assoc();
            $teacher_status = $status_data['status'];
        }
        $teacher_status_stmt->close();

        // Only allow admin role to access admin dashboard
        if ($teacher_status !== 'admin') {
            $message = ($teacher_status === 'inactive')
                ? 'Your account is inactive. Please contact an administrator to restore access.'
                : 'Admin dashboard access requires administrator role. Your current role is: ' . ucfirst($teacher_status);
            $_SESSION['access_denied'] = $message;
            header("Location: ../teacher/index.php");
            exit();
        }
    }

    // Redirect students who shouldn't have access to admin dashboard
    if ($user_type === 'student') {
        header("Location: ../student.php");
        exit();
    }

    // Get selected year from URL parameter or default to current year
    $current_year  = isset($_GET['year']) && is_numeric($_GET['year']) ? (int) $_GET['year'] : date('Y');
    $previous_year = $current_year - 1;

    // Get comparison year if provided
    $compare_year       = isset($_GET['compare_year']) && is_numeric($_GET['compare_year']) ? (int) $_GET['compare_year'] : null;
    $is_comparison_mode = $compare_year !== null;

    // Get dashboard statistics for selected year
    $total_students       = 0;
    $total_teachers       = 0;
    $total_events         = 0;
    $total_participations = 0;

    // Count total students (not year-specific)
    $student_sql    = "SELECT COUNT(*) as count FROM student_register";
    $student_result = $conn->query($student_sql);
    if ($student_result) {
        $total_students = $student_result->fetch_assoc()['count'];
    } else {
        // Fallback value if query fails
        $total_students = 0;
    }

    // Count total teachers (not year-specific)
    $teacher_sql    = "SELECT COUNT(*) as count FROM teacher_register";
    $teacher_result = $conn->query($teacher_sql);
    if ($teacher_result) {
        $total_teachers = $teacher_result->fetch_assoc()['count'];
    } else {
        // Fallback value if query fails
        $total_teachers = 0;
    }

    // Count total unique event types from student registrations for selected year (only approved)
    $student_events_sql    = "SELECT COUNT(DISTINCT event_type) as count FROM student_event_register WHERE event_type IS NOT NULL AND event_type != '' AND YEAR(start_date) = $current_year AND verification_status = 'Approved'";
    $student_events_result = $conn->query($student_events_sql);
    $student_events_count  = $student_events_result ? $student_events_result->fetch_assoc()['count'] : 0;

    $total_events = $student_events_count;

    // Count total participations for selected year (students only, approved only)
    $student_participation_sql    = "SELECT COUNT(*) as count FROM student_event_register WHERE YEAR(start_date) = $current_year AND verification_status = 'Approved'";
    $student_participation_result = $conn->query($student_participation_sql);
    $student_participations       = $student_participation_result ? $student_participation_result->fetch_assoc()['count'] : 0;

    $total_participations = $student_participations;

    // Get comparison year data if in comparison mode
    $compare_total_events         = 0;
    $compare_total_participations = 0;
    $compare_category_analytics   = [];

    if ($is_comparison_mode) {
        // Count events for comparison year (only approved)
        $compare_events_sql    = "SELECT COUNT(DISTINCT event_type) as count FROM student_event_register WHERE event_type IS NOT NULL AND event_type != '' AND YEAR(start_date) = $compare_year AND verification_status = 'Approved'";
        $compare_events_result = $conn->query($compare_events_sql);
        $compare_total_events  = $compare_events_result ? $compare_events_result->fetch_assoc()['count'] : 0;

        // Count participations for comparison year (only approved)
        $compare_parts_sql            = "SELECT COUNT(*) as count FROM student_event_register WHERE YEAR(start_date) = $compare_year AND verification_status = 'Approved'";
        $compare_parts_result         = $conn->query($compare_parts_sql);
        $compare_total_participations = $compare_parts_result ? $compare_parts_result->fetch_assoc()['count'] : 0;

        // Get category analytics for comparison year (only approved)
        $compare_category_sql = "SELECT
            event_type,
            COUNT(*) as participations,
            COUNT(DISTINCT event_type) as unique_events,
            COUNT(DISTINCT regno) as unique_participants,
            SUM(CASE WHEN prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners
            FROM student_event_register
            WHERE event_type IS NOT NULL AND event_type != ''
            AND YEAR(start_date) = $compare_year
            AND verification_status = 'Approved'
            GROUP BY event_type
            ORDER BY participations DESC";

        $compare_category_result = $conn->query($compare_category_sql);

        if ($compare_category_result) {
            while ($row = $compare_category_result->fetch_assoc()) {
                $category                              = $row['event_type'];
                $compare_category_analytics[$category] = [
                    'name'                 => $category,
                    'total_participations' => (int) $row['participations'],
                    'total_events'         => (int) $row['unique_events'],
                    'total_participants'   => (int) $row['unique_participants'],
                    'prize_winners'        => (int) $row['prize_winners'],
                    'success_rate'         => $row['participations'] > 0 ? round(($row['prize_winners'] / $row['participations']) * 100, 1) : 0,
                ];
            }
        }
    }

    // Enhanced Events by Category Analytics System (Students Only) - Year Specific
    $category_analytics            = [];
    $total_category_events         = 0;
    $total_category_participations = 0;

    // Get comprehensive student event data with detailed analytics for selected year (only approved)
    $student_category_sql = "SELECT
        event_type,
        COUNT(*) as participations,
        COUNT(DISTINCT event_type) as unique_events,
        COUNT(DISTINCT regno) as unique_participants,
        SUM(CASE WHEN prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners,
        AVG(CASE WHEN prize_amount IS NOT NULL AND prize_amount > 0 THEN CAST(REPLACE(REPLACE(prize_amount, 'Rs.', ''), ',', '') AS DECIMAL(10,2)) ELSE 0 END) as avg_prize_amount,
        MIN(start_date) as first_event_date,
        MAX(start_date) as latest_event_date
        FROM student_event_register
        WHERE event_type IS NOT NULL AND event_type != ''
        AND YEAR(start_date) = $current_year
        AND verification_status = 'Approved'
        GROUP BY event_type
        ORDER BY participations DESC";

    $student_category_result = $conn->query($student_category_sql);

    // Process student events only
    if ($student_category_result) {
        while ($row = $student_category_result->fetch_assoc()) {
            $category                      = $row['event_type'];
            $category_analytics[$category] = [
                'name'                 => $category,
                'total_participations' => (int) $row['participations'],
                'total_events'         => (int) $row['unique_events'],
                'total_participants'   => (int) $row['unique_participants'],
                'prize_winners'        => (int) $row['prize_winners'],
                'avg_prize_amount'     => round((float) $row['avg_prize_amount'], 2),
                'success_rate'         => $row['participations'] > 0 ? round(($row['prize_winners'] / $row['participations']) * 100, 1) : 0,
                'first_event'          => $row['first_event_date'],
                'latest_event'         => $row['latest_event_date'],
                'category_type'        => 'student_only',
                'activity_months'      => 0,
                'has_success_metrics'  => true,
            ];
        }
    }

    // Calculate activity months for each category
    foreach ($category_analytics as $key => $category) {
        if ($category['first_event'] && $category['latest_event']) {
            $first_date                                  = new DateTime($category['first_event']);
            $latest_date                                 = new DateTime($category['latest_event']);
            $interval                                    = $first_date->diff($latest_date);
            $category_analytics[$key]['activity_months'] = $interval->m + ($interval->y * 12) + 1;
        } else {
            $category_analytics[$key]['activity_months'] = 1;
        }
    }

    // Sort categories by total participations and limit to top 12
    uasort($category_analytics, function ($a, $b) {
        return $b['total_participations'] - $a['total_participations'];
    });
    $category_analytics = array_slice($category_analytics, 0, 12, true);

    // Calculate totals for percentages
    $total_category_participations = array_sum(array_column($category_analytics, 'total_participations'));
    $total_category_events         = array_sum(array_column($category_analytics, 'total_events'));

    // Add percentage calculations
    foreach ($category_analytics as $key => $category) {
        $category_analytics[$key]['event_percentage'] = $total_category_events > 0 ?
        round(($category['total_events'] / $total_category_events) * 100, 1) : 0;
    }

    // Prepare data for charts (Students Only)
    if (! empty($category_analytics)) {
        $category_data          = array_keys($category_analytics);
        $category_counts        = array_column($category_analytics, 'total_participations');
        $category_events_count  = array_column($category_analytics, 'total_events');
        $category_success_rates = array_column($category_analytics, 'success_rate');
    } else {
        // No sample data - show empty state
        $category_data                 = [];
        $category_counts               = [];
        $category_events_count         = [];
        $category_success_rates        = [];
        $category_analytics            = [];
        $total_category_participations = 0;
        $total_category_events         = 0;
    }

    // Enhanced Monthly Event Trends Data Collection (Students Only)
    $monthly_data = [];
    $months       = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $month_names  = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    // Initialize arrays for chart data
    $monthly_events         = [];
    $monthly_participations = [];
    $monthly_wins           = [];

    // Initialize arrays for previous year data (for YoY comparison)
    $previous_year_events         = [];
    $previous_year_participations = [];
    $previous_year_wins           = [];

    for ($i = 1; $i <= 12; $i++) {
        $month_num = str_pad($i, 2, '0', STR_PAD_LEFT);

        // Enhanced date matching for better accuracy
        $date_condition          = "YEAR(start_date) = $current_year AND MONTH(start_date) = $i";
        $previous_year_condition = "YEAR(start_date) = $previous_year AND MONTH(start_date) = $i";

        // EVENT COUNTING LOGIC:
        // - Events: Count unique combinations of event_name + date (1 workshop = 1 event, regardless of attendees)
        // - Participations: Count total individual student registrations
        // - Wins: Count individual prize winners

        // Count DISTINCT events by event_type for this month
        // One event type = one unique type of event, regardless of how many instances or students attend
        $student_events_sql = "SELECT COUNT(DISTINCT event_type) as count
                                  FROM student_event_register
                                  WHERE $date_condition AND event_type IS NOT NULL AND event_type != '' AND verification_status = 'Approved'";
        $student_events_result = $conn->query($student_events_sql);
        $student_events_count  = $student_events_result ? (int) $student_events_result->fetch_assoc()['count'] : 0;

        // Count DISTINCT student participations for this month with enhanced uniqueness
        $student_parts_sql = "SELECT COUNT(DISTINCT CONCAT(regno, '-', event_name, '-', DATE(start_date), '-', COALESCE(event_type, 'unknown'))) as count
                                 FROM student_event_register
                                 WHERE $date_condition AND regno IS NOT NULL AND event_name IS NOT NULL
                                 AND regno != '' AND event_name != '' AND verification_status = 'Approved'";
        $student_parts_result = $conn->query($student_parts_sql);
        $student_parts_count  = $student_parts_result ? (int) $student_parts_result->fetch_assoc()['count'] : 0;

        // Count DISTINCT prize winners for this month with enhanced uniqueness
        $wins_sql = "SELECT COUNT(DISTINCT CONCAT(regno, '-', event_name, '-', DATE(start_date))) as count
                     FROM student_event_register
                     WHERE $date_condition AND prize IN ('First', 'Second', 'Third')
                     AND regno IS NOT NULL AND event_name IS NOT NULL
                     AND regno != '' AND event_name != '' AND verification_status = 'Approved'";
        $wins_result = $conn->query($wins_sql);
        $wins_count  = $wins_result ? (int) $wins_result->fetch_assoc()['count'] : 0;

        // Collect previous year data for YoY comparison - count unique event types only
        $prev_events_sql = "SELECT COUNT(DISTINCT event_type) as count
                               FROM student_event_register
                               WHERE $previous_year_condition AND event_type IS NOT NULL AND event_type != '' AND verification_status = 'Approved'";
        $prev_events_result = $conn->query($prev_events_sql);
        $prev_events_count  = $prev_events_result ? (int) $prev_events_result->fetch_assoc()['count'] : 0;

        $prev_parts_sql = "SELECT COUNT(DISTINCT CONCAT(regno, '-', event_name, '-', DATE(start_date), '-', COALESCE(event_type, 'unknown'))) as count
                              FROM student_event_register
                              WHERE $previous_year_condition AND regno IS NOT NULL AND event_name IS NOT NULL
                              AND regno != '' AND event_name != '' AND verification_status = 'Approved'";
        $prev_parts_result = $conn->query($prev_parts_sql);
        $prev_parts_count  = $prev_parts_result ? (int) $prev_parts_result->fetch_assoc()['count'] : 0;

        $prev_wins_sql = "SELECT COUNT(DISTINCT CONCAT(regno, '-', event_name, '-', DATE(start_date))) as count
                          FROM student_event_register
                          WHERE $previous_year_condition AND prize IN ('First', 'Second', 'Third')
                          AND regno IS NOT NULL AND event_name IS NOT NULL
                          AND regno != '' AND event_name != '' AND verification_status = 'Approved'";
        $prev_wins_result = $conn->query($prev_wins_sql);
        $prev_wins_count  = $prev_wins_result ? (int) $prev_wins_result->fetch_assoc()['count'] : 0;

        // Store data for charts
        $monthly_events[]         = $student_events_count;
        $monthly_participations[] = $student_parts_count;
        $monthly_wins[]           = $wins_count;

        // Store previous year data for YoY comparison
        $previous_year_events[]         = $prev_events_count;
        $previous_year_participations[] = $prev_parts_count;
        $previous_year_wins[]           = $prev_wins_count;

        // Debug logging for event counting logic (remove in production)
        if ($student_events_count > 0 || $student_parts_count > 0 || $wins_count > 0) {
            error_log("Month $i ($current_year): Unique Events=$student_events_count, Total Participations=$student_parts_count, Wins=$wins_count");
        }

        // Store detailed data for analysis
        $monthly_data[] = [
            'month'             => $months[$i - 1],
            'month_full'        => $month_names[$i - 1],
            'events'            => $student_events_count,
            'participations'    => $student_parts_count,
            'wins'              => $wins_count,
            'avg_participation' => $student_events_count > 0 ? round($student_parts_count / $student_events_count, 1) : 0,
        ];
    }

    // Calculate trend analytics (Students Only)
    $total_year_events         = array_sum($monthly_events);
    $total_year_participations = array_sum($monthly_participations);
    $total_year_wins           = array_sum($monthly_wins);

    // Find peak month
    $peak_month_index = array_search(max($monthly_events), $monthly_events);
    $peak_month       = $peak_month_index !== false ? $month_names[$peak_month_index] : 'N/A';

    // Find most active month for participations
    $most_active_month_index = array_search(max($monthly_participations), $monthly_participations);
    $most_active_month       = $most_active_month_index !== false ? $month_names[$most_active_month_index] : 'N/A';

    // Calculate average events per month
    $avg_events_per_month         = $total_year_events > 0 ? round($total_year_events / 12, 1) : 0;
    $avg_participations_per_month = $total_year_participations > 0 ? round($total_year_participations / 12, 1) : 0;

    // No sample data - use only real database data
    // If no real data exists, arrays will remain with actual zeros

    // Data Quality Check: Ensure no duplicate months and validate data integrity
    if (count($monthly_data) !== 12) {
        // Fix array if somehow we don't have 12 months
        $monthly_data = array_slice($monthly_data, 0, 12);
        while (count($monthly_data) < 12) {
            $monthly_data[] = [
                'month'             => $months[count($monthly_data)],
                'month_full'        => $month_names[count($monthly_data)],
                'events'            => 0,
                'participations'    => 0,
                'wins'              => 0,
                'avg_participation' => 0,
            ];
        }
    }

    // Final validation: ensure arrays are exactly 12 elements for 2025
    $monthly_events         = array_slice($monthly_events, 0, 12);
    $monthly_participations = array_slice($monthly_participations, 0, 12);
    $monthly_wins           = array_slice($monthly_wins, 0, 12);

    $previous_year_events         = array_slice($previous_year_events, 0, 12);
    $previous_year_participations = array_slice($previous_year_participations, 0, 12);
    $previous_year_wins           = array_slice($previous_year_wins, 0, 12);

    while (count($monthly_events) < 12) {
        $monthly_events[] = 0;
    }

    while (count($monthly_participations) < 12) {
        $monthly_participations[] = 0;
    }

    while (count($monthly_wins) < 12) {
        $monthly_wins[] = 0;
    }

    while (count($previous_year_events) < 12) {
        $previous_year_events[] = 0;
    }

    while (count($previous_year_participations) < 12) {
        $previous_year_participations[] = 0;
    }

    while (count($previous_year_wins) < 12) {
        $previous_year_wins[] = 0;
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
            src="../sona_logo.jpg"
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
              <span class="material-symbols-outlined">people</span>
              <a href="participants.php">Participants</a>
            </li>
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">manage_accounts</span>
              <a href="user_management.php">User Management</a>
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
        <!-- Quick Navigation Menu -->
        <div class="quick-nav-menu">
          <div class="nav-container">
            <span class="nav-title">Quick Analytics Navigation:</span>
            <div class="nav-controls">
              <div class="year-selector-container">
                <span class="year-label">Analysis Year:</span>
                <select id="yearSelector" class="year-dropdown" onchange="changeAnalysisYear()">
                  <?php
                      // Get available years from database
                      $conn        = new mysqli("localhost", "root", "", "event_management_system");
                      $year_sql    = "SELECT DISTINCT YEAR(start_date) as year FROM student_event_register WHERE start_date IS NOT NULL ORDER BY year DESC";
                      $year_result = $conn->query($year_sql);

                      $available_years = [];
                      if ($year_result && $year_result->num_rows > 0) {
                          while ($year_row = $year_result->fetch_assoc()) {
                              $available_years[] = $year_row['year'];
                          }
                      }

                      // Generate years from current year to 10 years back
                      $current_system_year = date('Y');
                      $all_years           = [];
                      for ($i = 0; $i <= 10; $i++) {
                          $year        = $current_system_year - $i;
                          $all_years[] = $year;
                      }

                      // Merge available years with system years and remove duplicates
                      $all_years = array_unique(array_merge($available_years, $all_years));
                      rsort($all_years); // Sort descending

                      foreach ($all_years as $year) {
                          $selected = ($year == $current_year) ? 'selected' : '';
                          $has_data = in_array($year, $available_years) ? ' ✓' : ' ○';
                          echo "<option value=\"$year\" $selected>$year$has_data</option>";
                      }

                      $conn->close();
                  ?>
                </select>
                <span class="year-info">
                  <?php echo count($available_years); ?> years with data
                </span>
              </div>
              <div class="nav-buttons">
                <button class="nav-btn" onclick="scrollToSection('dashboard-cards')">
                  <span class="material-symbols-outlined">dashboard</span>
                  Dashboard
                </button>
                <button class="nav-btn" onclick="scrollToSection('category-analytics')">
                  <span class="material-symbols-outlined">analytics</span>
                  Category Analytics
                </button>
                <button class="nav-btn" onclick="scrollToSection('monthly-trends')">
                  <span class="material-symbols-outlined">trending_up</span>
                  Monthly Trends
                </button>
                <button class="nav-btn" onclick="scrollToSection('detailed-insights')">
                  <span class="material-symbols-outlined">insights</span>
                  Detailed Insights
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- cards  -->
        <div class="main-card" id="dashboard-cards">
          <div class="year-indicator">
            <?php if ($is_comparison_mode): ?>
              <h3>📊 Year-over-Year Comparison:<?php echo $current_year; ?> vs<?php echo $compare_year; ?></h3>
              <p>Comparing analytics between                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo $current_year; ?> and<?php echo $compare_year; ?>
                <span class="custom-year-note">(Comparison Mode)</span>
              </p>
            <?php else: ?>
              <h3>📊 Dashboard Analytics for<?php echo $current_year; ?></h3>
              <p>Showing data for academic year                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo $current_year; ?>
                <?php if (isset($_GET['year'])): ?>
                  <span class="custom-year-note">(Custom Year Selected)</span>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($is_comparison_mode): ?>
        <!-- Comparison Cards -->
        <div class="comparison-cards">
          <h3 class="comparison-title">📈 Year-over-Year Comparison Results</h3>
          <div class="comparison-grid">
            <div class="comparison-card">
              <div class="comparison-header">
                <h4>Student Events</h4>
                <span class="comparison-icon">📅</span>
              </div>
              <div class="comparison-data">
                <div class="year-data current-year">
                  <span class="year-label"><?php echo $current_year; ?></span>
                  <span class="year-value"><?php echo number_format($total_events); ?></span>
                </div>
                <div class="comparison-arrow">
                  <?php
                      $events_change     = $total_events - $compare_total_events;
                      $events_change_pct = $compare_total_events > 0 ? round(($events_change / $compare_total_events) * 100, 1) : 0;
                  ?>
                  <span class="change-indicator<?php echo $events_change >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $events_change >= 0 ? '▲' : '▼'; ?>
                    <?php echo abs($events_change_pct); ?>%
                  </span>
                </div>
                <div class="year-data compare-year">
                  <span class="year-label"><?php echo $compare_year; ?></span>
                  <span class="year-value"><?php echo number_format($compare_total_events); ?></span>
                </div>
              </div>
            </div>

            <div class="comparison-card">
              <div class="comparison-header">
                <h4>Participations</h4>
                <span class="comparison-icon">👥</span>
              </div>
              <div class="comparison-data">
                <div class="year-data current-year">
                  <span class="year-label"><?php echo $current_year; ?></span>
                  <span class="year-value"><?php echo number_format($total_participations); ?></span>
                </div>
                <div class="comparison-arrow">
                  <?php
                      $parts_change     = $total_participations - $compare_total_participations;
                      $parts_change_pct = $compare_total_participations > 0 ? round(($parts_change / $compare_total_participations) * 100, 1) : 0;
                  ?>
                  <span class="change-indicator<?php echo $parts_change >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $parts_change >= 0 ? '▲' : '▼'; ?>
                    <?php echo abs($parts_change_pct); ?>%
                  </span>
                </div>
                <div class="year-data compare-year">
                  <span class="year-label"><?php echo $compare_year; ?></span>
                  <span class="year-value"><?php echo number_format($compare_total_participations); ?></span>
                </div>
              </div>
            </div>

            <div class="comparison-card">
              <div class="comparison-header">
                <h4>Categories</h4>
                <span class="comparison-icon">📊</span>
              </div>
              <div class="comparison-data">
                <div class="year-data current-year">
                  <span class="year-label"><?php echo $current_year; ?></span>
                  <span class="year-value"><?php echo count($category_analytics); ?></span>
                </div>
                <div class="comparison-arrow">
                  <?php
                      $categories_change     = count($category_analytics) - count($compare_category_analytics);
                      $categories_change_pct = count($compare_category_analytics) > 0 ? round(($categories_change / count($compare_category_analytics)) * 100, 1) : 0;
                  ?>
                  <span class="change-indicator<?php echo $categories_change >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $categories_change >= 0 ? '▲' : '▼'; ?>
                    <?php echo abs($categories_change_pct); ?>%
                  </span>
                </div>
                <div class="year-data compare-year">
                  <span class="year-label"><?php echo $compare_year; ?></span>
                  <span class="year-value"><?php echo count($compare_category_analytics); ?></span>
                </div>
              </div>
            </div>

            <div class="comparison-action">
              <button class="exit-comparison-btn" onclick="exitComparisonMode()">
                <span class="material-symbols-outlined">close</span>
                Exit Comparison
              </button>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <div class="main-card">
          <div class="card">
            <div class="card-inner">
              <h3>Total Students:</h3>
              <span class="material-symbols-outlined">school</span>
            </div>
            <h1><?php echo number_format($total_students); ?></h1>
            <small>All time registrations</small>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total Teachers:</h3>
              <span class="material-symbols-outlined">person_book</span>
            </div>
            <h1><?php echo number_format($total_teachers); ?></h1>
            <small>All time registrations</small>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Student Events (<?php echo $current_year; ?>):</h3>
              <span class="material-symbols-outlined">event</span>
            </div>
            <h1><?php echo number_format($total_events); ?></h1>
            <small>Event types in                                                                                                                                                                                                                                                                                                                                                                            <?php echo $current_year; ?></small>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Participations (<?php echo $current_year; ?>):</h3>
              <span class="material-symbols-outlined">groups</span>
            </div>
            <h1><?php echo number_format($total_participations); ?></h1>
            <small>Total in                                                                                                                                                                                                                                                                                                          <?php echo $current_year; ?></small>
          </div>
        </div>
        <!-- Enhanced Charts Section -->
        <div class="charts" id="category-analytics">
          <!-- Enhanced Category Analytics Card -->
          <div class="charts-card enhanced-category-card">
            <div class="chart-header">
              <h2 class="chart-title">Student Events Analysis -                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo $current_year; ?></h2>
              <div class="chart-controls">
                <select id="categoryView" onchange="updateCategoryChart()">
                  <option value="participations">Total Participations</option>
                  <option value="events">Number of Events</option>
                  <option value="success">Success Rate</option>
                </select>
                <button class="chart-toggle" onclick="toggleChartType()" title="Toggle Chart Type">
                  <span class="material-symbols-outlined">bar_chart</span>
                </button>
              </div>
            </div>

            <!-- Category Statistics Summary -->
            <div class="category-summary">
              <div class="summary-stats">
                <div class="summary-item">
                  <span class="summary-value"><?php
                                                  $total_winners = array_sum(array_column($category_analytics, 'prize_winners'));
                                              echo number_format($total_winners);
                                              ?></span>
                  <span class="summary-label">No of Winners</span>
                </div>
                <div class="summary-item">
                  <span class="summary-value"><?php echo number_format($total_category_participations); ?></span>
                  <span class="summary-label">Total Participations</span>
                </div>
                <div class="summary-item">
                  <span class="summary-value"><?php echo number_format($total_category_events); ?></span>
                  <span class="summary-label">Total Events</span>
                </div>
                <div class="summary-item">
                  <span class="summary-value"><?php
                                                  // Calculate average success rate for student categories
                                              $avg_success = count($category_analytics) > 0 ? array_sum(array_column($category_analytics, 'success_rate')) / count($category_analytics) : 0;
                                              echo round($avg_success, 1); ?>%</span>
                  <span class="summary-label">Avg Success Rate</span>
                </div>
              </div>
            </div>

            <!-- Main Chart Container -->
            <div id="enhanced-category-chart"></div>

            <!-- Category Performance Indicators -->
            <div class="performance-indicators">
              <div class="indicator-grid">
                <div class="indicator-item top-category">
                  <span class="indicator-icon">👑</span>
                  <div class="indicator-content">
                    <span class="indicator-label">Top Category</span>
                    <span class="indicator-value"><?php
                                                      $top_category = ! empty($category_analytics) ? array_keys($category_analytics)[0] : 'No Data Available';
                                                  echo htmlspecialchars($top_category);
                                                  ?></span>
                    <span class="indicator-desc"><?php
                                                 echo ! empty($category_analytics) ? number_format(array_values($category_analytics)[0]['total_participations']) . ' participations' : 'No event data in database';
                                                 ?></span>
                  </div>
                </div>

                <div class="indicator-item best-performance">
                  <span class="indicator-icon">🏆</span>
                  <div class="indicator-content">
                    <span class="indicator-label">Best Performance</span>
                    <span class="indicator-value"><?php
                                                      $best_category = '';
                                                      $best_rate     = 0;
                                                      foreach ($category_analytics as $name => $data) {
                                                          if ($data['success_rate'] > $best_rate) {
                                                              $best_rate     = $data['success_rate'];
                                                              $best_category = $name;
                                                          }
                                                      }
                                                  echo htmlspecialchars($best_category ?: 'No Data Available');
                                                  ?></span>
                    <span class="indicator-desc"><?php echo $best_rate > 0 ? $best_rate . '% success rate' : 'No event data in database'; ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detailed Category Breakdown Table -->
            <div class="category-details">
              <h3>Detailed Student Category Analytics -                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo $current_year; ?></h3>
              <div class="category-table-container">
                <table class="category-table">
                  <thead>
                    <tr>
                      <th>Category</th>
                      <th>Participations</th>
                      <th>Participants</th>
                      <th>Success Rate</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($category_analytics)): ?>
                    <tr>
                      <td colspan="4" style="text-align: center; padding: 40px; color: #666;">
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                          <span style="font-size: 48px;">📊</span>
                          <strong>No Event Data Available for                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo $current_year; ?></strong>
                          <p>No student event categories found for the year                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo $current_year; ?>.</p>
                          <div style="font-size: 12px; color: #aaa; margin-top: 8px;">
                            Try selecting a different year from the dropdown above, or add events for                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo $current_year; ?>.
                          </div>
                        </div>
                      </td>
                    </tr>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($category_analytics as $category): ?>
                    <tr class="category-row" data-category="<?php echo htmlspecialchars($category['name']); ?>">
                      <td class="category-name">
                        <span class="category-icon">
                          <?php
                              // Smart icon assignment based on category name
                              $name_lower = strtolower($category['name']);
                              if (strpos($name_lower, 'technical') !== false || strpos($name_lower, 'workshop') !== false) {
                                  echo '🔧';
                              } elseif (strpos($name_lower, 'cultural') !== false || strpos($name_lower, 'art') !== false) {
                                  echo '🎭';
                              } elseif (strpos($name_lower, 'sports') !== false || strpos($name_lower, 'game') !== false) {
                                  echo '⚽';
                              } elseif (strpos($name_lower, 'academic') !== false || strpos($name_lower, 'conference') !== false) {
                                  echo '📚';
                              } elseif (strpos($name_lower, 'research') !== false || strpos($name_lower, 'science') !== false) {
                                  echo '🔬';
                              } elseif (strpos($name_lower, 'innovation') !== false || strpos($name_lower, 'hackathon') !== false) {
                                  echo '💡';
                              } elseif (strpos($name_lower, 'skill') !== false || strpos($name_lower, 'training') !== false) {
                                  echo '🎯';
                              } else {
                                  echo '📝';
                              }

                          ?>
                        </span>
                        <div class="category-info">
                          <span class="category-title"><?php echo htmlspecialchars($category['name']); ?></span>
                          <span class="category-type-badge student-only">
                            Student Event
                          </span>
                        </div>
                      </td>
                      <td class="participations-cell">
                        <span class="participation-count"><?php echo number_format($category['total_participations']); ?></span>
                        <div class="participation-bar">
                          <div class="participation-fill" style="width:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo($total_category_participations > 0) ? ($category['total_participations'] / $total_category_participations) * 100 : 0; ?>%"></div>
                        </div>
                      </td>
                      <td class="participants-cell">
                        <span class="participants-count"><?php echo $category['total_participants']; ?></span>
                      </td>
                      <td class="success-cell">
                        <span class="success-rate                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php echo $category['success_rate'] > 30 ? 'high' : ($category['success_rate'] > 20 ? 'medium' : 'low'); ?>">
                          <?php echo $category['success_rate']; ?>%
                        </span>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Monthly Student Event Trends Chart (Enhanced) -->
          <div class="charts-card" id="monthly-trends">
            <div class="trend-header">
              <h2 class="chart-title">Monthly Student Event Analysis -                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $current_year; ?>
                <span class="year-badge"><?php echo $current_year; ?></span>
              </h2>
              <div class="trend-controls">
                <select id="trendView" onchange="updateTrendChart()">
                  <option value="overview">Overview</option>
                  <option value="detailed">Detailed Analysis</option>
                  <option value="comparison">YoY Comparison</option>
                </select>
                <button class="trend-comparison" onclick="showYearComparison()" title="Compare Years">
                  <span class="material-symbols-outlined">compare_arrows</span>
                  Compare
                </button>
                <button class="trend-refresh" onclick="refreshTrendData()" title="Refresh Data">
                  <span class="material-symbols-outlined">refresh</span>
                </button>
              </div>
            </div>
            <div class="trend-summary">
              <div class="trend-stats">
                <div class="stat-item">
                  <span class="stat-value"><?php echo $total_year_events; ?></span>
                  <span class="stat-label">Total Student Events</span>
                  <span class="stat-change"><?php
                                                $prev_total = array_sum($previous_year_events);
                                                if ($prev_total > 0) {
                                                    $change = (($total_year_events - $prev_total) / $prev_total) * 100;
                                                    echo($change >= 0 ? '+' : '') . round($change, 1) . '% vs last year';
                                                } else {
                                                    echo $total_year_events > 0 ? 'New events this year' : 'No events yet';
                                            }
                                            ?></span>
                </div>
                <div class="stat-item">
                  <span class="stat-value"><?php echo $total_year_participations; ?></span>
                  <span class="stat-label">Total Student Participations</span>
                  <span class="stat-change"><?php
                                                $prev_parts = array_sum($previous_year_participations);
                                                if ($prev_parts > 0) {
                                                    $change = (($total_year_participations - $prev_parts) / $prev_parts) * 100;
                                                    echo($change >= 0 ? '+' : '') . round($change, 1) . '% vs last year';
                                                } else {
                                                    echo $total_year_participations > 0 ? 'New participations this year' : 'No participations yet';
                                            }
                                            ?></span>
                </div>
                <div class="stat-item">
                  <span class="stat-value"><?php echo $peak_month; ?></span>
                  <span class="stat-label">Peak Month</span>
                  <span class="stat-change">Best performing</span>
                </div>
                <div class="stat-item">
                  <span class="stat-value"><?php echo $avg_events_per_month; ?></span>
                  <span class="stat-label">Avg Events/Month</span>
                  <span class="stat-change"><?php
                                                if ($total_year_events > 0) {
                                                    echo 'Real data analysis';
                                                } else {
                                                    echo 'No events to analyze';
                                            }
                                            ?></span>
                </div>
              </div>

              <!-- Interactive Month Selector -->
              <div class="month-selector">
                <h4>📅 Quick Month Analysis</h4>
                <div class="month-buttons">
                  <?php for ($m = 1; $m <= 12; $m++): ?>
                  <button class="month-btn" data-month="<?php echo $m; ?>" onclick="showMonthDetails(<?php echo $m; ?>)">
                    <?php echo $months[$m - 1]; ?>
                    <span class="month-value"><?php echo $monthly_events[$m - 1]; ?></span>
                  </button>
                  <?php endfor; ?>
                </div>
              </div>
            </div>
            <div id="area-chart"></div>

            <!-- Enhanced Month Details Panel -->
            <div id="month-details-panel" class="month-details-panel" style="display: none;">
              <div class="month-details-content">
                <h4 id="selected-month-title">Month Details</h4>
                <div class="month-stats-grid">
                  <div class="month-stat">
                    <span class="month-stat-icon">📅</span>
                    <div class="month-stat-info">
                      <span class="month-stat-label">Events</span>
                      <span class="month-stat-value" id="month-events">0</span>
                    </div>
                  </div>
                  <div class="month-stat">
                    <span class="month-stat-icon">👥</span>
                    <div class="month-stat-info">
                      <span class="month-stat-label">Participants</span>
                      <span class="month-stat-value" id="month-participants">0</span>
                    </div>
                  </div>
                  <div class="month-stat">
                    <span class="month-stat-icon">🏆</span>
                    <div class="month-stat-info">
                      <span class="month-stat-label">Winners</span>
                      <span class="month-stat-value" id="month-winners">0</span>
                    </div>
                  </div>
                  <div class="month-stat">
                    <span class="month-stat-icon">📈</span>
                    <div class="month-stat-info">
                      <span class="month-stat-label">Success Rate</span>
                      <span class="month-stat-value" id="month-success">0%</span>
                    </div>
                  </div>
                </div>
                <button class="close-month-details" onclick="hideMonthDetails()">Close</button>
              </div>
            </div>

            <div class="trend-legend">
              <div class="legend-item">
                <span class="legend-color" style="background-color: #008FFB;"></span>
                <span>Student Events</span>
              </div>
              <div class="legend-item">
                <span class="legend-color" style="background-color: #00E396;"></span>
                <span>Student Participations</span>
              </div>
              <div class="legend-item">
                <span class="legend-color" style="background-color: #FEB019;"></span>
                <span>Student Prize Winners</span>
              </div>
            </div>

            <!-- Trend Insights Cards -->
            <div class="trend-insights">
              <div class="trend-insight-card">
                <div class="insight-header">
                  <span class="insight-emoji">📊</span>
                  <h4>Growth Pattern</h4>
                </div>
                <p><?php if ($total_year_events > 0): ?>Events show <strong>consistent growth</strong> with peak activity in<?php echo $peak_month; ?>. The success rate averages <strong><?php echo round(($total_year_wins / $total_year_participations) * 100, 1); ?>%</strong> across all months.<?php else: ?>No event data available for<?php echo $current_year; ?>. Start adding student events to see growth patterns and analytics.<?php endif; ?></p>
              </div>

              <div class="trend-insight-card">
                <div class="insight-header">
                  <span class="insight-emoji">🎯</span>
                  <h4>Key Highlights</h4>
                </div>
                <ul class="insight-list">
                  <?php if ($total_year_events > 0): ?>
                  <li><strong><?php echo $peak_month; ?></strong> was the most active month</li>
                  <li><strong><?php echo max($monthly_wins); ?></strong> winners in best performing month</li>
                  <li>Average <strong><?php echo $avg_events_per_month; ?></strong> events per month</li>
                  <li><strong><?php echo $total_year_participations; ?></strong> total student participations</li>
                  <?php else: ?>
                  <li>No event data available for analysis</li>
                  <li>Add student events to see monthly highlights</li>
                  <li>Track participation trends over time</li>
                  <li>Monitor success rates and achievements</li>
                  <?php endif; ?>
                </ul>
              </div>

              <div class="trend-insight-card">
                <div class="insight-header">
                  <span class="insight-emoji">🚀</span>
                  <h4>Recommendations</h4>
                </div>
                <ul class="insight-list">
                  <?php if ($total_year_events > 0): ?>
                  <li>Focus on replicating<?php echo $peak_month; ?>'s success factors</li>
                  <li>Consider increasing events in lower-activity months</li>
                  <li>Maintain current participation quality standards</li>
                  <li>Explore new event categories for growth</li>
                  <?php else: ?>
                  <li>Start by organizing student events in various categories</li>
                  <li>Plan events throughout the academic year</li>
                  <li>Track participation and success metrics</li>
                  <li>Build a comprehensive event management system</li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Analytics Section -->
        <div class="analytics-section" id="detailed-insights">
          <div class="analytics-card">
            <h3> Student Event Distribution Overview</h3>
            <div class="distribution-chart-container">
              <!-- Chart Controls -->
              <div class="distribution-controls">
                <select id="distributionView" onchange="updateDistributionChart()">
                  <option value="student-detail">Student Events Detail</option>
                  <option value="timeline">Timeline View</option>
                  <option value="performance">Performance Metrics</option>
                </select>
                <button class="distribution-toggle" onclick="toggleDistributionData()" title="Toggle Data View">
                  <span class="material-symbols-outlined">view_module</span>
                </button>
              </div>

              <!-- Main Distribution Chart -->
              <div id="distribution-chart"></div>

              <!-- Student Event Statistics -->
              <div class="distribution-summary">
                <div class="student-stats">
                  <!-- Student Events Section -->
                  <div class="event-category-section">
                    <h4 class="category-title">🎓 Student Events Overview</h4>
                    <div class="category-stats">
                      <div class="stat-card">
                        <span class="stat-number"><?php echo array_sum($monthly_events); ?></span>
                        <span class="stat-label">Student Events</span>
                      </div>
                      <div class="stat-card">
                        <span class="stat-number"><?php echo $student_participations; ?></span>
                        <span class="stat-label">Student Participations</span>
                      </div>
                      <div class="stat-card">
                        <span class="stat-number"><?php echo $total_year_wins; ?></span>
                        <span class="stat-label">Prize Winners</span>
                      </div>
                      <div class="stat-card">
                        <span class="stat-number"><?php echo $student_participations > 0 ? round(($total_year_wins / $student_participations) * 100, 1) : 0; ?>%</span>
                        <span class="stat-label">Success Rate</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="analytics-card">
            <h3>🎯 Monthly Student Event Insights</h3>
            <div class="insights-grid">
              <div class="insight-item">
                <span class="insight-icon">📈</span>
                <div class="insight-content">
                  <span class="insight-title">Most Active Month</span>
                  <span class="insight-value"><?php echo $most_active_month; ?></span>
                  <span class="insight-desc"><?php echo max($monthly_participations); ?> participations</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">🏆</span>
                <div class="insight-content">
                  <span class="insight-title">Best Performance</span>
                  <span class="insight-value"><?php
                                                  $best_month_index = array_search(max($monthly_wins), $monthly_wins);
                                              echo $best_month_index !== false ? $month_names[$best_month_index] : 'N/A';
                                              ?></span>
                  <span class="insight-desc"><?php echo max($monthly_wins); ?> prize winners</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">📊</span>
                <div class="insight-content">
                  <span class="insight-title">Avg Participation</span>
                  <span class="insight-value"><?php echo $avg_participations_per_month; ?></span>
                  <span class="insight-desc">per month</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">🎯</span>
                <div class="insight-content">
                  <span class="insight-title">Peak Event Month</span>
                  <span class="insight-value"><?php echo $peak_month; ?></span>
                  <span class="insight-desc"><?php echo max($monthly_events); ?> unique events</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">📅</span>
                <div class="insight-content">
                  <span class="insight-title">Total Event Types</span>
                  <span class="insight-value"><?php echo count($category_analytics); ?></span>
                  <span class="insight-desc">categories available</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">💪</span>
                <div class="insight-content">
                  <span class="insight-title">Success Rate</span>
                  <span class="insight-value"><?php echo $total_year_participations > 0 ? round(($total_year_wins / $total_year_participations) * 100, 1) : 0; ?>%</span>
                  <span class="insight-desc">overall achievement</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">🔥</span>
                <div class="insight-content">
                  <span class="insight-title">Most Popular Category</span>
                  <span class="insight-value"><?php echo ! empty($category_analytics) ? array_keys($category_analytics)[0] : 'N/A'; ?></span>
                  <span class="insight-desc"><?php echo ! empty($category_analytics) ? reset($category_analytics)['total_participations'] : 0; ?> participants</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">⭐</span>
                <div class="insight-content">
                  <span class="insight-title">Yearly Growth</span>
                  <span class="insight-value"><?php
                                                  $current_total  = array_sum($monthly_participations);
                                                  $previous_total = array_sum($previous_year_participations);
                                                  if ($previous_total > 0) {
                                                      $growth = round((($current_total - $previous_total) / $previous_total) * 100, 1);
                                                      echo($growth >= 0 ? '+' : '') . $growth . '%';
                                                  } else {
                                                      echo $current_total > 0 ? 'New!' : '0%';
                                              }
                                              ?></span>
                  <span class="insight-desc">vs last year</span>
                </div>
              </div>
              <div class="insight-item">
                <span class="insight-icon">🎓</span>
                <div class="insight-content">
                  <span class="insight-title">Avg Events/Month</span>
                  <span class="insight-value"><?php echo $avg_events_per_month; ?></span>
                  <span class="insight-desc">event types hosted</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Scroll to Top Button -->
      <button class="scroll-to-top" id="scrollToTop" onclick="scrollToTop()">
        <span class="material-symbols-outlined">keyboard_arrow_up</span>
      </button>

      <!-- Scripts -->
      <!-- js scripts-  -->
      <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/5.3.4/apexcharts.min.js"></script>

      <script>
      // Get PHP data for charts and make them globally available (Students Only)
      window.categoryData =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo json_encode($category_data); ?>;
      window.categoryCounts =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo json_encode($category_counts); ?>;
      window.monthlyEvents =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo json_encode($monthly_events); ?>;
      window.monthlyParticipations =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo json_encode($monthly_participations); ?>;
      window.monthlyWins =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo json_encode($monthly_wins); ?>;
      window.months =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo json_encode($months); ?>;
      window.currentYear =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo json_encode($current_year); ?>;
      window.previousYear =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo json_encode($previous_year); ?>;

      // Previous year data for YoY comparison (Students Only)
      window.previousYearEvents =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo json_encode($previous_year_events); ?>;
      window.previousYearParticipations =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo json_encode($previous_year_participations); ?>;
      window.previousYearWins =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php echo json_encode($previous_year_wins); ?>;

      // Enhanced Category Analytics Data (Students Only)
      window.categoryAnalytics =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo json_encode(array_values($category_analytics)); ?>;
      window.totalCategoryParticipations =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo json_encode($total_category_participations); ?>;
      window.totalCategoryEvents =                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo json_encode($total_category_events); ?>;

      // Distribution Chart Data (Students Only)
      window.distributionData = {
        totalWins:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo $total_year_wins; ?>,
        totalEvents:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $total_year_events; ?>,
        totalParticipations:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo $total_year_participations; ?>,
        studentParticipations:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo $student_participations; ?>,
        colors: ['#008FFB', '#00E396', '#FEB019', '#FF4560']
      };

      console.log('🎓 Student Event Analytics Data Loaded:');
      console.log('Year:', window.currentYear);
      console.log('Categories:', window.categoryData);
      console.log('Category Counts:', window.categoryCounts);
      console.log('Category Analytics:', window.categoryAnalytics);
      console.log('Monthly Events:', window.monthlyEvents);
      console.log('Monthly Participations:', window.monthlyParticipations);
      console.log('Monthly Wins:', window.monthlyWins);
      console.log('Months:', window.months);

      // Data Validation: Ensure all arrays have exactly 12 unique values for 2025
      function validateAndCleanData() {
        // Check for duplicate patterns in monthly data
        function detectDuplicatePattern(arr, name) {
          const uniqueValues = new Set(arr.filter(val => val > 0));
          const nonZeroValues = arr.filter(val => val > 0);

          if (nonZeroValues.length > 0 && uniqueValues.size < nonZeroValues.length) {
            console.warn(`⚠️ Potential duplicate pattern detected in ${name}:`, arr);
            console.warn(`${name} has ${nonZeroValues.length} non-zero values but only ${uniqueValues.size} unique values`);
          }

          // Check for consecutive identical values (potential data error)
          let consecutiveCount = 0;
          for (let i = 1; i < arr.length; i++) {
            if (arr[i] > 0 && arr[i] === arr[i-1]) {
              consecutiveCount++;
            }
          }

          if (consecutiveCount > 2) {
            console.warn(`⚠️ ${name} has ${consecutiveCount} consecutive identical values - possible data duplication`);
          }
        }

        // Detect duplicate patterns
        detectDuplicatePattern(window.monthlyEvents, 'Monthly Events');
        detectDuplicatePattern(window.monthlyParticipations, 'Monthly Participations');
        detectDuplicatePattern(window.monthlyWins, 'Monthly Wins');

        // Ensure monthly arrays have exactly 12 values
        if (window.monthlyEvents.length !== 12) {
          console.warn('Monthly events array length mismatch, padding with zeros');
          while (window.monthlyEvents.length < 12) window.monthlyEvents.push(0);
          window.monthlyEvents = window.monthlyEvents.slice(0, 12);
        }

        if (window.monthlyParticipations.length !== 12) {
          console.warn('Monthly participations array length mismatch, padding with zeros');
          while (window.monthlyParticipations.length < 12) window.monthlyParticipations.push(0);
          window.monthlyParticipations = window.monthlyParticipations.slice(0, 12);
        }

        if (window.monthlyWins.length !== 12) {
          console.warn('Monthly wins array length mismatch, padding with zeros');
          while (window.monthlyWins.length < 12) window.monthlyWins.push(0);
          window.monthlyWins = window.monthlyWins.slice(0, 12);
        }

        // Validate previous year data arrays
        if (window.previousYearEvents.length !== 12) {
          console.warn('Previous year events array length mismatch, padding with zeros');
          while (window.previousYearEvents.length < 12) window.previousYearEvents.push(0);
          window.previousYearEvents = window.previousYearEvents.slice(0, 12);
        }

        if (window.previousYearParticipations.length !== 12) {
          console.warn('Previous year participations array length mismatch, padding with zeros');
          while (window.previousYearParticipations.length < 12) window.previousYearParticipations.push(0);
          window.previousYearParticipations = window.previousYearParticipations.slice(0, 12);
        }

        if (window.previousYearWins.length !== 12) {
          console.warn('Previous year wins array length mismatch, padding with zeros');
          while (window.previousYearWins.length < 12) window.previousYearWins.push(0);
          window.previousYearWins = window.previousYearWins.slice(0, 12);
        }

        // Log final validated data with uniqueness statistics
        console.log('✅ Data Validation Complete for 2025:');
        console.log('Final Monthly Events (12 months):', window.monthlyEvents);
        console.log('Final Monthly Participations (12 months):', window.monthlyParticipations);
        console.log('Final Monthly Wins (12 months):', window.monthlyWins);
        console.log('Previous Year Events (' + window.previousYear + '):', window.previousYearEvents);
        console.log('Previous Year Participations (' + window.previousYear + '):', window.previousYearParticipations);
        console.log('Previous Year Wins (' + window.previousYear + '):', window.previousYearWins);

        // Calculate and log uniqueness statistics
        const uniqueEvents = new Set(window.monthlyEvents.filter(v => v > 0));
        const uniqueParticipations = new Set(window.monthlyParticipations.filter(v => v > 0));
        const uniqueWins = new Set(window.monthlyWins.filter(v => v > 0));

        console.log('📊 Uniqueness Statistics:');
        console.log(`Events: ${uniqueEvents.size} unique values out of ${window.monthlyEvents.filter(v => v > 0).length} non-zero months`);
        console.log(`Participations: ${uniqueParticipations.size} unique values out of ${window.monthlyParticipations.filter(v => v > 0).length} non-zero months`);
        console.log(`Wins: ${uniqueWins.size} unique values out of ${window.monthlyWins.filter(v => v > 0).length} non-zero months`);
        console.log('Enhanced duplicate prevention implemented');
        console.log('YoY Comparison now uses real database data only');
      }

      // Run validation
      validateAndCleanData();

      // Calculate and log summary statistics
      const totalEvents = window.monthlyEvents.reduce((a, b) => a + b, 0);
      const totalParticipations = window.monthlyParticipations.reduce((a, b) => a + b, 0);
      const totalWins = window.monthlyWins.reduce((a, b) => a + b, 0);

      console.log('📈 Student Event Summary Statistics:');
      console.log('Total Student Events:', totalEvents);
      console.log('Total Student Participations:', totalParticipations);
      console.log('Total Student Prize Winners:', totalWins);
      console.log('Average Student Events per Month:', (totalEvents / 12).toFixed(1));
      console.log('Average Student Participations per Month:', (totalParticipations / 12).toFixed(1));
      console.log('Student Success Rate:', totalParticipations > 0 ? ((totalWins / totalParticipations) * 100).toFixed(1) + '%' : '0%');
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

    // Scroll to Top Functionality
    function scrollToTop() {
      const main = document.querySelector('.main');
      if (main) {
        main.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      } else {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    }

    // Scroll to Section Functionality
    function scrollToSection(sectionId) {
      const section = document.getElementById(sectionId);
      const main = document.querySelector('.main');

      if (section && main) {
        const sectionTop = section.offsetTop - 100; // Account for navigation menu
        main.scrollTo({
          top: sectionTop,
          behavior: 'smooth'
        });
      } else if (section) {
        section.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    }

    // Year Change Functionality
    function changeAnalysisYear() {
      const yearSelector = document.getElementById('yearSelector');
      const selectedYear = yearSelector.value;

      // Show loading state
      showLoadingState();

      // Reload page with selected year
      const currentUrl = new URL(window.location);
      currentUrl.searchParams.set('year', selectedYear);
      window.location.href = currentUrl.toString();
    }

    // Loading State Function
    function showLoadingState() {
      // Add loading overlay
      const loadingOverlay = document.createElement('div');
      loadingOverlay.id = 'loadingOverlay';
      loadingOverlay.innerHTML = `
        <div class="loading-container">
          <div class="loading-spinner"></div>
          <div class="loading-text">Loading Analytics for ${document.getElementById('yearSelector').value}...</div>
          <div class="loading-subtext">Updating category analytics and trend data</div>
        </div>
      `;
      loadingOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(30, 66, 118, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        backdrop-filter: blur(5px);
      `;
      document.body.appendChild(loadingOverlay);
    }

    // Year Comparison Function
    function showYearComparison() {
      const currentYear = document.getElementById('yearSelector').value;
      const previousYear = parseInt(currentYear) - 1;

      // Create comparison popup
      const comparisonPopup = document.createElement('div');
      comparisonPopup.id = 'yearComparisonPopup';
      comparisonPopup.innerHTML = `
        <div class="comparison-content">
          <h3>Year-over-Year Comparison</h3>
          <div class="comparison-selector">
            <label>Compare ${currentYear} with:</label>
            <select id="compareYear">
              <option value="${previousYear}">${previousYear}</option>
              <option value="${previousYear - 1}">${previousYear - 1}</option>
              <option value="${previousYear - 2}">${previousYear - 2}</option>
            </select>
            <button onclick="generateComparison()">Generate Comparison</button>
            <button onclick="closeComparison()">Close</button>
          </div>
        </div>
      `;

      // Add styling
      comparisonPopup.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
      `;

      document.body.appendChild(comparisonPopup);
    }

    function closeComparison() {
      const popup = document.getElementById('yearComparisonPopup');
      if (popup) {
        popup.remove();
      }
    }

    // Generate Comparison Function
    function generateComparison() {
      const currentYear = document.getElementById('yearSelector').value;
      const compareYear = document.getElementById('compareYear').value;

      // Close the popup
      closeComparison();

      // Show loading state
      showLoadingState();

      // Create comparison URL with both years
      const currentUrl = new URL(window.location);
      currentUrl.searchParams.set('year', currentYear);
      currentUrl.searchParams.set('compare_year', compareYear);

      // Redirect to show comparison
      window.location.href = currentUrl.toString();
    }

    // Exit Comparison Mode Function
    function exitComparisonMode() {
      const currentUrl = new URL(window.location);
      currentUrl.searchParams.delete('compare_year');
      window.location.href = currentUrl.toString();
    }

    // Refresh trend data function
    function refreshTrendData() {
      // Show loading state
      showLoadingState();

      // Reload current page to refresh data
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    }

    // Update URL with current year parameter on page load
    document.addEventListener('DOMContentLoaded', function() {
      const yearSelector = document.getElementById('yearSelector');
      const currentYear = yearSelector.value;

      // Update URL if year is different from current year
      const urlParams = new URLSearchParams(window.location.search);
      const urlYear = urlParams.get('year');

      if (urlYear && urlYear !== currentYear) {
        yearSelector.value = urlYear;
      } else if (!urlYear && currentYear !== new Date().getFullYear().toString()) {
        // Add year parameter to URL if custom year is selected
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('year', currentYear);
        window.history.replaceState({}, '', newUrl);
      }
    });

    // Show/Hide Scroll to Top Button
    window.addEventListener('scroll', function() {
      const scrollToTopBtn = document.getElementById('scrollToTop');
      const main = document.querySelector('.main');

      if (main && main.scrollTop > 300) {
        scrollToTopBtn.classList.add('visible');
      } else {
        scrollToTopBtn.classList.remove('visible');
      }
    });

    // Add scroll listener to main content area
    document.addEventListener('DOMContentLoaded', function() {
      const main = document.querySelector('.main');
      const scrollToTopBtn = document.getElementById('scrollToTop');

      if (main) {
        main.addEventListener('scroll', function() {
          if (main.scrollTop > 300) {
            scrollToTopBtn.classList.add('visible');
          } else {
            scrollToTopBtn.classList.remove('visible');
          }
        });
      }

      // Smooth scroll for internal links
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });

      // Add smooth scroll navigation to analytics cards
      const analyticsCards = document.querySelectorAll('.analytics-card');
      analyticsCards.forEach((card, index) => {
        card.style.scrollMarginTop = '100px'; // Account for fixed header
      });
    });
    </script>

  </body>
</html>