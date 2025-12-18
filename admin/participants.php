<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
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

    // Handle delete operation (students only)
    if (isset($_POST['delete_id'])) {
        $delete_id = $_POST['delete_id'];

        $delete_sql = "DELETE FROM student_event_register WHERE id = ?";

        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        if ($delete_stmt->execute()) {
            $success_message = "Student record deleted successfully!";
        } else {
            $error_message = "Error deleting record: " . $conn->error;
        }
        $delete_stmt->close();
    }

    // Get filter parameters
    $filter_event_type       = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $filter_department       = isset($_GET['department']) ? $_GET['department'] : '';
    $filter_year             = isset($_GET['year']) ? $_GET['year'] : '';
    $filter_prize            = isset($_GET['prize']) ? $_GET['prize'] : '';
    $filter_participant_type = isset($_GET['participant_type']) ? $_GET['participant_type'] : 'student'; // Default to students only
    $search_query            = isset($_GET['search']) ? $_GET['search'] : '';
    $entries_param           = isset($_GET['entries']) ? $_GET['entries'] : '10';
    $entries_per_page        = ($entries_param === 'all') ? PHP_INT_MAX : (int) $entries_param;
    $current_page            = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Validate search query
    $search_error = '';
    if (! empty($search_query) && strlen(trim($search_query)) < 2) {
        $search_error = 'Search query must be at least 2 characters long.';
        $search_query = ''; // Reset search query to prevent database errors
    }

    // Build WHERE clause based on filters - separate for student and teacher queries
    $student_where_conditions = [];
    $teacher_where_conditions = [];
    $student_params           = [];
    $teacher_params           = [];
    $student_param_types      = "";
    $teacher_param_types      = "";

    // Build conditions for students
    if (! empty($filter_event_type)) {
        $student_where_conditions[] = "se.event_type = ?";
        $teacher_where_conditions[] = "te.event_type = ?";
        $student_params[]           = $filter_event_type;
        $teacher_params[]           = $filter_event_type;
        $student_param_types .= "s";
        $teacher_param_types .= "s";
    }

    if (! empty($filter_department)) {
        $student_where_conditions[] = "se.department = ?";
        $teacher_where_conditions[] = "te.department = ?";
        $student_params[]           = $filter_department;
        $teacher_params[]           = $filter_department;
        $student_param_types .= "s";
        $teacher_param_types .= "s";
    }

    if (! empty($filter_year)) {
        $student_where_conditions[] = "se.current_year = ?";
        // For teachers, we'll skip year filter as they don't have current_year
        $student_params[] = $filter_year;
        $student_param_types .= "s";
    }

    if (! empty($filter_prize) && $filter_prize !== 'all') {
        if ($filter_prize === 'no_prize') {
            $student_where_conditions[] = "(se.prize IS NULL OR se.prize = '' OR se.prize = 'No Prize')";
            // Teachers don't have prize field in staff_event_reg, so we'll skip this filter for them
        } else {
            $student_where_conditions[] = "se.prize = ?";
            // Skip prize filter for teachers as they don't have this field
            $student_params[] = $filter_prize;
            $student_param_types .= "s";
        }
    }

    if (! empty($search_query)) {
        $student_where_conditions[] = "(sr.name LIKE ? OR se.regno LIKE ? OR se.event_name LIKE ?)";
        $teacher_where_conditions[] = "(tr.name LIKE ? OR te.staff_id LIKE ? OR te.topic LIKE ?)";
        $search_param               = "%$search_query%";
        $student_params[]           = $search_param;
        $student_params[]           = $search_param;
        $student_params[]           = $search_param;
        $teacher_params[]           = $search_param;
        $teacher_params[]           = $search_param;
        $teacher_params[]           = $search_param;
        $student_param_types .= "sss";
        $teacher_param_types .= "sss";
    }

    $student_where_clause = ! empty($student_where_conditions) ? "WHERE " . implode(" AND ", $student_where_conditions) : "";
    $teacher_where_clause = ! empty($teacher_where_conditions) ? "WHERE " . implode(" AND ", $teacher_where_conditions) : "";

    // Build the UNION query based on participant type filter
    if ($filter_participant_type === 'student') {
        // Only students
        $count_sql = "SELECT COUNT(*) as total FROM student_event_register se
                      LEFT JOIN student_register sr ON se.regno = sr.regno $student_where_clause";
        $count_params      = $student_params;
        $count_param_types = $student_param_types;
    } elseif ($filter_participant_type === 'teacher') {
        // Only teachers
        $count_sql = "SELECT COUNT(*) as total FROM staff_event_reg te
                      LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id $teacher_where_clause";
        $count_params      = $teacher_params;
        $count_param_types = $teacher_param_types;
    } else {
        // Both students and teachers (UNION)
        $count_sql = "SELECT COUNT(*) as total FROM (
            SELECT se.id FROM student_event_register se
            LEFT JOIN student_register sr ON se.regno = sr.regno $student_where_clause
            UNION ALL
            SELECT te.id FROM staff_event_reg te
            LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id $teacher_where_clause
        ) as combined";
        $count_params      = array_merge($student_params, $teacher_params);
        $count_param_types = $student_param_types . $teacher_param_types;
    }
    $count_stmt = $conn->prepare($count_sql);
    if (! empty($count_params)) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages   = ($entries_param === 'all') ? 1 : ceil($total_records / $entries_per_page);
    $count_stmt->close();

    // Calculate offset for pagination
    $offset = ($entries_param === 'all') ? 0 : (($current_page - 1) * $entries_per_page);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Participants</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="./CSS/report.css" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
      rel="stylesheet"
    />
    <link
      href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap"
      rel="stylesheet"
    />
    <style>
      /* Fix scrolling issue */
      .grid-container {
        min-height: 100vh !important;
        height: auto !important;
      }

      .main {
        padding-bottom: 20px !important;
        overflow-y: auto !important;
        max-height: none !important;
      }

      .main-content {
        max-width: none !important;
        padding: 20px !important;
      }

      .filters-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
      }

      .filters-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
      }

      .filter-group {
        display: flex;
        flex-direction: column;
      }

      .filter-group label {
        font-weight: 500;
        margin-bottom: 5px;
        color: #333;
        font-size: 14px;
      }

      .filter-group select,
      .filter-group input {
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
        height: 40px;
        box-sizing: border-box;
        line-height: 1.4;
      }

      .filter-group input {
        width: 100%;
      }

      .filter-group select {
        width: 100%;
        background-color: white;
        cursor: pointer;
      }

      .filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 20px;
      }

      .filter-actions .filter-group {
        margin: 0;
      }

      .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
      }

      .btn-primary {
        background-color: #0c3878;
        color: white;
      }

      .btn-secondary {
        background-color: #6c757d;
        color: white;
      }

      .btn-danger {
        background-color: #dc3545;
        color: white;
      }

      .btn-warning {
        background-color: #ffc107;
        color: #212529;
      }

      .btn:hover {
        opacity: 0.9;
      }

      .action-buttons {
        display: flex;
        gap: 5px;
      }

      .action-buttons .btn {
        padding: 4px 8px;
        font-size: 12px;
      }

      .entries-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        gap: 20px;
      }

      .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
      }

      .pagination .btn {
        padding: 6px 12px;
      }

      .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        text-align: center;
      }

      .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
      }

      .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
      }

      .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
      }

      .modal-content {
        background-color: white;
        margin: 15% auto;
        padding: 20px;
        border-radius: 10px;
        width: 400px;
        text-align: center;
      }

      .modal-actions {
        margin-top: 20px;
        display: flex;
        gap: 10px;
        justify-content: center;
      }

      /* Ensure table is responsive */
      .participants-container {
        overflow-x: auto;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }

      .participants-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
      }

      .participants-table th,
      .participants-table td {
        padding: 12px 8px;
        text-align: left;
        border-bottom: 1px solid #ddd;
        white-space: nowrap;
      }

      .participants-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #ffffffff;
        position: sticky;
        top: 0;
      }

      .participants-table tr:hover {
        background-color: #f5f5f5;
      }

      .files-cell {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
      }

      .download-btn {
        padding: 4px 8px;
        background-color: #0c3878;
        color: white;
        text-decoration: none;
        border-radius: 3px;
        font-size: 12px;
      }

      .download-btn:hover {
        background-color: #0a2d5f;
      }

      .no-files {
        color: #666;
        font-style: italic;
      }

      .stats-summary {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-top: 20px;
      }

      .no-data {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        text-align: center;
      }

      /* Responsive design for mobile devices */
      @media (max-width: 768px) {
        .filters-row {
          grid-template-columns: 1fr;
          gap: 10px;
        }

        .filter-group {
          margin-bottom: 10px;
        }

        .filter-actions {
          flex-direction: column;
          align-items: stretch;
          margin-top: 15px;
        }

        .filter-actions .btn {
          width: 100%;
          justify-content: center;
        }

        .participants-table {
          font-size: 12px;
        }

        .participants-table th,
        .participants-table td {
          padding: 8px 4px;
        }
      }

      @media (max-width: 480px) {
        .filters-container {
          padding: 15px;
        }

        .filter-group select,
        .filter-group input {
          font-size: 16px; /* Prevents zoom on iOS */
        }
      }

      /* Alert Messages - matching login page style */
      .alert {
        padding: 12px 15px;
        margin: 15px 0;
        border-radius: 8px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .alert-success {
        background-color: #d1e7dd;
        border: 1px solid #a3cfbb;
        color: #0a3622;
      }

      .alert-error {
        background-color: #f8d7da;
        border: 1px solid #f1aeb5;
        color: #721c24;
      }

      .alert-success::before {
        content: "✓";
        font-weight: bold;
      }

      .alert-error::before {
        content: "⚠";
        font-weight: bold;
      }

      /* Export dropdown styles */
      .dropdown {
        position: relative;
        display: inline-block;
      }

      .dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 5px;
      }

      .dropdown-content {
        position: absolute;
        background-color: #f9f9f9;
        min-width: 220px;
        width: max-content;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1000;
        right: 0;
        border-radius: 6px;
        border: 1px solid #ddd;
        white-space: nowrap;
      }

      .dropdown-content a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #eee;
        white-space: nowrap;
        font-size: 14px;
      }

      .dropdown-content a:last-child {
        border-bottom: none;
        border-radius: 0 0 6px 6px;
      }

      .dropdown-content a:first-child {
        border-radius: 6px 6px 0 0;
      }

      .dropdown-content a:hover {
        background-color: #e9ecef;
      }
    </style>
  </head>
  <body>
    <div class="grid-container">
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

      <aside id="sidebar">
        <div class="sidebar-title">
          <div class="sidebar-band">
            <h2 style="color: white; padding: 10px">Admin Panel</h2>
            <span class="material-symbols-outlined" onclick="closeSidebar()"
              >close</span
            >
          </div>
          <ul class="sidebar-list">
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">dashboard</span>
              <a href="index.php">Home</a>
            </li>
            <li class="sidebar-list-item active">
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

      <!-- Main content for Participants page -->
      <div class="main">
        <div class="main-content">
          <h2>Event Participants Management</h2>

          <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
          <?php endif; ?>

          <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
          <?php endif; ?>

          <?php if (! empty($search_error)): ?>
            <div class="alert alert-error"><?php echo $search_error; ?></div>
          <?php endif; ?>

          <!-- Filters Section -->
          <div class="filters-container">
            <h3>Filter Participants</h3>
            <form method="GET" action="">
              <div class="filters-row">
                <div class="filter-group">
                  <label for="search">Search:</label>
                  <input type="text" id="search" name="search"
                         placeholder="Name, Reg No, Event..."
                         value="<?php echo htmlspecialchars($search_query); ?>" style="margin-bottom: 5px;">
                  <small style="color: #666; font-size: 12px;">Minimum 2 characters required</small>
                </div>

                <div class="filter-group">
                  <label for="participant_type">Participant Type:</label>
                  <select name="participant_type" id="participant_type">
                    <option value="student"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo($filter_participant_type === 'student') ? 'selected' : ''; ?>>Students Only</option>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="event_type">Event Type:</label>
                  <select name="event_type" id="event_type">
                    <option value="">All Event Types</option>
                    <?php
                        // Get distinct event types from both tables
                        $types_sql = "SELECT DISTINCT event_type FROM student_event_register WHERE event_type IS NOT NULL
                                     UNION
                                     SELECT DISTINCT event_type FROM staff_event_reg WHERE event_type IS NOT NULL
                                     ORDER BY event_type";
                        $types_result = $conn->query($types_sql);
                        if ($types_result) {
                            while ($type_row = $types_result->fetch_assoc()) {
                                $selected = ($filter_event_type === $type_row['event_type']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($type_row['event_type']) . "' $selected>" .
                                htmlspecialchars($type_row['event_type']) . "</option>";
                            }
                        }
                    ?>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="department">Department:</label>
                  <select name="department" id="department">
                    <option value="">All Departments</option>
                    <?php
                        // Get distinct departments from both tables
                        $dept_sql = "SELECT DISTINCT department FROM student_event_register WHERE department IS NOT NULL
                                    UNION
                                    SELECT DISTINCT department FROM staff_event_reg WHERE department IS NOT NULL
                                    ORDER BY department";
                        $dept_result = $conn->query($dept_sql);
                        if ($dept_result) {
                            while ($dept_row = $dept_result->fetch_assoc()) {
                                $selected = ($filter_department === $dept_row['department']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($dept_row['department']) . "' $selected>" .
                                htmlspecialchars($dept_row['department']) . "</option>";
                            }
                        }
                    ?>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="year">Academic Year:</label>
                  <select name="year" id="year">
                    <option value="">All Academic Years</option>
                    <?php
                        // Get distinct years from the database to see what's actually stored
                        $years_sql      = "SELECT DISTINCT current_year FROM student_event_register WHERE current_year IS NOT NULL AND current_year != '' ORDER BY current_year DESC";
                        $years_result   = $conn->query($years_sql);
                        $existing_years = [];

                        if ($years_result) {
                            while ($year_row = $years_result->fetch_assoc()) {
                                $year_value       = $year_row['current_year'];
                                $existing_years[] = $year_value;
                                $selected         = ($filter_year === $year_value) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($year_value) . "' $selected>" . htmlspecialchars($year_value) . "</option>";
                            }
                        }

                        // Also add the generated academic years for future entries
                        $current_year_num = date('Y');
                        for ($i = 0; $i < 10; $i++) {
                            $start_year    = $current_year_num - $i;
                            $end_year      = substr($start_year + 1, -2); // Get last 2 digits
                            $academic_year = $start_year . '-' . $end_year;

                            // Only add if it doesn't already exist in database
                            if (! in_array($academic_year, $existing_years)) {
                                $selected = ($filter_year === $academic_year) ? 'selected' : '';
                                echo "<option value='$academic_year' $selected>$academic_year</option>";
                            }
                        }
                    ?>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="prize">Prize Status:</label>
                  <select name="prize" id="prize">
                    <option value="">All</option>
                    <option value="First"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo($filter_prize === 'First') ? 'selected' : ''; ?>>First Prize</option>
                    <option value="Second"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($filter_prize === 'Second') ? 'selected' : ''; ?>>Second Prize</option>
                    <option value="Third"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo($filter_prize === 'Third') ? 'selected' : ''; ?>>Third Prize</option>
                    <option value="no_prize"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($filter_prize === 'no_prize') ? 'selected' : ''; ?>>No Prize</option>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="entries">Show:</label>
                  <select name="entries" id="entries">
                    <option value="10"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($entries_param === '10') ? 'selected' : ''; ?>>10 entries</option>
                    <option value="25"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($entries_param === '25') ? 'selected' : ''; ?>>25 entries</option>
                    <option value="50"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($entries_param === '50') ? 'selected' : ''; ?>>50 entries</option>
                    <option value="100"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo($entries_param === '100') ? 'selected' : ''; ?>>100 entries</option>
                    <option value="all"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo($entries_param === 'all') ? 'selected' : ''; ?>>All entries</option>
                  </select>
                </div>

                <div class="filter-group">
                  <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                      <span class="material-symbols-outlined">filter_list</span>
                      Filter
                    </button>
                    <a href="participants.php" class="btn btn-secondary">
                      <span class="material-symbols-outlined">clear</span>
                      Clear
                    </a>
                  </div>
                </div>
              </div>
            </form>
          </div>

          <?php
              // Get participants data with filters and pagination - Combined Students and Teachers

              // Build the main query based on participant type filter
              if ($filter_participant_type === 'student') {
                  // Only students
                  $sql = "SELECT
                          se.id,
                          se.regno as reg_id,
                          sr.name,
                          se.current_year as year_info,
                          se.semester,
                          se.department,
                          se.event_type,
                          se.event_name as event_title,
                          se.start_date,
                          se.end_date,
                          se.no_of_days,
                          se.organisation,
                          se.prize,
                          se.prize_amount,
                          se.event_poster,
                          se.certificates,
                          'student' as participant_type
                      FROM student_event_register se
                      LEFT JOIN student_register sr ON se.regno = sr.regno
                      $student_where_clause
                      ORDER BY se.start_date DESC, se.id DESC";
              } elseif ($filter_participant_type === 'teacher') {
                  // Only teachers
                  $sql = "SELECT
                          te.id,
                          te.staff_id as reg_id,
                          tr.name,
                          '' as year_info,
                          '' as semester,
                          te.department,
                          te.event_type,
                          te.topic as event_title,
                          te.event_date as start_date,
                          te.event_date as end_date,
                          1 as no_of_days,
                          te.organisation,
                          '' as prize,
                          '' as prize_amount,
                          '' as event_poster,
                          te.certificate_path as certificates,
                          'teacher' as participant_type
                      FROM staff_event_reg te
                      LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
                      $teacher_where_clause
                      ORDER BY te.event_date DESC, te.id DESC";
              } else {
                  // Both students and teachers (UNION)
                  $sql = "SELECT * FROM (
                      SELECT
                          se.id,
                          se.regno as reg_id,
                          sr.name,
                          se.current_year as year_info,
                          se.semester,
                          se.department,
                          se.event_type,
                          se.event_name as event_title,
                          se.start_date,
                          se.end_date,
                          se.no_of_days,
                          se.organisation,
                          se.prize,
                          se.prize_amount,
                          se.event_poster,
                          se.certificates,
                          'student' as participant_type
                      FROM student_event_register se
                      LEFT JOIN student_register sr ON se.regno = sr.regno
                      $student_where_clause

                      UNION ALL

                      SELECT
                          te.id,
                          te.staff_id as reg_id,
                          tr.name,
                          '' as year_info,
                          '' as semester,
                          te.department,
                          te.event_type,
                          te.topic as event_title,
                          te.event_date as start_date,
                          te.event_date as end_date,
                          1 as no_of_days,
                          te.organisation,
                          '' as prize,
                          '' as prize_amount,
                          '' as event_poster,
                          te.certificate_path as certificates,
                          'teacher' as participant_type
                      FROM staff_event_reg te
                      LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
                      $teacher_where_clause
                  ) as combined_results
                  ORDER BY start_date DESC, id DESC";
              }

              // Add LIMIT clause only if not showing all entries
              if ($entries_param !== 'all') {
                  $sql .= " LIMIT ? OFFSET ?";
              }

              $stmt = $conn->prepare($sql);

              // Rebuild parameters for the main query based on participant type
              if ($filter_participant_type === 'student') {
                  // Use only student parameters
                  $main_params      = $student_params;
                  $main_param_types = $student_param_types;
              } elseif ($filter_participant_type === 'teacher') {
                  // Use only teacher parameters
                  $main_params      = $teacher_params;
                  $main_param_types = $teacher_param_types;
              } else {
                  // Use parameters for both student and teacher queries (UNION)
                  $main_params      = array_merge($student_params, $teacher_params);
                  $main_param_types = $student_param_types . $teacher_param_types;
              }

              // Add pagination parameters only if not showing all entries
              if ($entries_param !== 'all') {
                  $main_params[] = $entries_per_page;
                  $main_params[] = $offset;
                  $main_param_types .= "ii";
              }

              if (! empty($main_params)) {
                  $stmt->bind_param($main_param_types, ...$main_params);
              }
              $stmt->execute();
              $result = $stmt->get_result();

              if ($result && $result->num_rows > 0) {
                  // Display entries info
                  if ($entries_param === 'all') {
                      $start_entry  = 1;
                      $end_entry    = $total_records;
                      $entries_text = "Showing all $total_records entries";
                  } else {
                      $start_entry  = $offset + 1;
                      $end_entry    = min($offset + $entries_per_page, $total_records);
                      $entries_text = "Showing $start_entry to $end_entry of $total_records entries";
                  }

                  echo "<div class='entries-info'>";
                  echo "<div>$entries_text</div>";
                  echo "<div class='export-options' style='display: flex; gap: 10px; flex-wrap: wrap;'>";
                  echo "<div class='dropdown' style='position: relative; display: inline-block;'>";
                  echo "<button class='btn btn-primary dropdown-toggle' onclick='toggleParticipantExportDropdown()' style='display: flex; align-items: center; gap: 5px;'>";
                  echo "<span class='material-symbols-outlined'>download</span> Export";
                  echo "<span class='material-symbols-outlined' style='font-size: 16px;'>arrow_drop_down</span>";
                  echo "</button>";
                  echo "<div id='participantExportDropdownContent' class='dropdown-content' style='display: none; position: absolute; background-color: #f9f9f9; min-width: 200px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2); z-index: 1000; right: 0; border-radius: 6px; border: 1px solid #ddd;'>";

                  $query_params = http_build_query($_GET);

                  echo "<a href='export_participants.php?format=csv&export_type=detailed&" . $query_params . "' style='color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #eee;'>";
                  echo "<span class='material-symbols-outlined' style='font-size: 18px;'>table_view</span> Detailed CSV";
                  echo "</a>";

                  echo "<a href='export_participants.php?format=csv&export_type=summary&" . $query_params . "' style='color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #eee;'>";
                  echo "<span class='material-symbols-outlined' style='font-size: 18px;'>summarize</span> Summary CSV";
                  echo "</a>";

                  echo "<a href='export_participants.php?format=excel&export_type=detailed&" . $query_params . "' style='color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #eee;'>";
                  echo "<span class='material-symbols-outlined' style='font-size: 18px;'>grid_on</span> Detailed Excel";
                  echo "</a>";

                  echo "<a href='export_excel.php?" . $query_params . "' style='color: black; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 8px;'>";
                  echo "<span class='material-symbols-outlined' style='font-size: 18px;'>description</span> Legacy Excel";
                  echo "</a>";

                  echo "</div>";
                  echo "</div>";
                  echo "</div>";
                  echo "</div>";

                  echo "<div class='participants-container'>";
                  echo "<table class='participants-table'>";
                  echo "<thead>";
                  echo "<tr>";
                  echo "<th>S.No</th>";
                  echo "<th>Type</th>";
                  echo "<th>ID</th>";
                  echo "<th>Name</th>";
                  echo "<th>Year</th>";
                  echo "<th>Dept</th>";
                  echo "<th>Event Type</th>";
                  echo "<th>Event Name</th>";
                  echo "<th>Date</th>";
                  echo "<th>Prize</th>";
                  echo "<th>Files</th>";
                  echo "<th>Actions</th>";
                  echo "</tr>";
                  echo "</thead>";
                  echo "<tbody>";

                  $sno = $start_entry;
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr>";
                      echo "<td>" . $sno++ . "</td>";

                      // Participant type badge
                      $type_badge = ($row['participant_type'] === 'teacher') ?
                      "<span style='background: #28a745; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px;'>👨‍🏫 Teacher</span>" :
                      "<span style='background: #007bff; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px;'>👨‍🎓 Student</span>";
                      echo "<td>" . $type_badge . "</td>";

                      echo "<td>" . htmlspecialchars($row['reg_id']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                      echo "<td>" . htmlspecialchars($row['year_info'] ?: 'N/A') . "</td>";
                      echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['event_title']) . "</td>";
                      echo "<td>";
                      if ($row['start_date'] === $row['end_date']) {
                          echo date('d-M-Y', strtotime($row['start_date'])) . ' (' . $row['no_of_days'] . ' day)';
                      } else {
                          echo date('d-M', strtotime($row['start_date'])) . ' - ' . date('d-M-Y', strtotime($row['end_date'])) . ' (' . $row['no_of_days'] . ' days)';
                      }
                      echo "</td>";
                      echo "<td>" . htmlspecialchars($row['prize'] ?: 'No Prize') . "</td>";

                      // Files column with download links
                      echo "<td class='files-cell'>";
                      if (! empty($row['event_poster'])) {
                          $download_type = ($row['participant_type'] === 'teacher') ? 'teacher_poster' : 'poster';
                          echo "<a href='download.php?id=" . $row['id'] . "&type=$download_type&participant_type=" . $row['participant_type'] . "' class='download-btn' target='_blank'>📄 Poster</a>";
                      }
                      if (! empty($row['certificates'])) {
                          $download_type = ($row['participant_type'] === 'teacher') ? 'teacher_certificate' : 'certificate';
                          echo "<a href='download.php?id=" . $row['id'] . "&type=$download_type&participant_type=" . $row['participant_type'] . "' class='download-btn' target='_blank'>🏆 Certificate</a>";
                      }
                      if (empty($row['event_poster']) && empty($row['certificates'])) {
                          echo "<span class='no-files'>No files</span>";
                      }
                      echo "</td>";

                      // Actions column
                      echo "<td>";
                      echo "<div class='action-buttons'>";
                      // Only show edit button for students (teacher event editing removed)
                      if ($row['participant_type'] === 'student') {
                          echo "<a href='edit_participant.php?id=" . $row['id'] . "' class='btn btn-warning' title='Edit'>
                              <span class='material-symbols-outlined'>edit</span>
                            </a>";
                      }
                      echo "<button onclick='confirmDelete(" . $row['id'] . ", \"" . $row['participant_type'] . "\")' class='btn btn-danger' title='Delete'>
                          <span class='material-symbols-outlined'>delete</span>
                        </button>";
                      echo "</div>";
                      echo "</td>";

                      echo "</tr>";
                  }

                  echo "</tbody>";
                  echo "</table>";
                  echo "</div>";

                  // Pagination (only show if not displaying all entries)
                  if ($total_pages > 1 && $entries_param !== 'all') {
                      echo "<div class='pagination'>";

                      // Previous button
                      if ($current_page > 1) {
                          $prev_params         = $_GET;
                          $prev_params['page'] = $current_page - 1;
                          echo "<a href='?" . http_build_query($prev_params) . "' class='btn btn-secondary'>« Previous</a>";
                      }

                      // Page numbers
                      $start_page = max(1, $current_page - 2);
                      $end_page   = min($total_pages, $current_page + 2);

                      for ($i = $start_page; $i <= $end_page; $i++) {
                          $page_params         = $_GET;
                          $page_params['page'] = $i;
                          $active_class        = ($i == $current_page) ? 'btn-primary' : 'btn-secondary';
                          echo "<a href='?" . http_build_query($page_params) . "' class='btn $active_class'>$i</a>";
                      }

                      // Next button
                      if ($current_page < $total_pages) {
                          $next_params         = $_GET;
                          $next_params['page'] = $current_page + 1;
                          echo "<a href='?" . http_build_query($next_params) . "' class='btn btn-secondary'>Next »</a>";
                      }

                      echo "</div>";
                  }

                  // Add some statistics
                  echo "<div class='stats-summary'>";
                  echo "<h3>Summary</h3>";
                  echo "<p><strong>Filtered Results:</strong> " . $total_records . " participants</p>";

                  // Get additional stats based on participant type filter
                  if ($filter_participant_type === 'student') {
                      $stats_sql = "SELECT
                          COUNT(DISTINCT se.event_name) as total_events,
                          COUNT(DISTINCT se.regno) as unique_participants,
                          COUNT(*) as total_registrations,
                          SUM(CASE WHEN se.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners,
                          'Students' as participant_label
                      FROM student_event_register se
                      LEFT JOIN student_register sr ON se.regno = sr.regno
                      $student_where_clause";
                  } elseif ($filter_participant_type === 'teacher') {
                      $stats_sql = "SELECT
                          COUNT(DISTINCT te.topic) as total_events,
                          COUNT(DISTINCT te.staff_id) as unique_participants,
                          COUNT(*) as total_registrations,
                          0 as prize_winners,
                          'Teachers' as participant_label
                      FROM staff_event_reg te
                      LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
                      $teacher_where_clause";
                  } else {
                      // Combined stats for both students and teachers
                      $stats_sql = "SELECT
                          (student_stats.total_events + teacher_stats.total_events) as total_events,
                          (student_stats.unique_participants + teacher_stats.unique_participants) as unique_participants,
                          (student_stats.total_registrations + teacher_stats.total_registrations) as total_registrations,
                          student_stats.prize_winners,
                          'All Participants' as participant_label
                      FROM
                      (SELECT
                          COUNT(DISTINCT se.event_name) as total_events,
                          COUNT(DISTINCT se.regno) as unique_participants,
                          COUNT(*) as total_registrations,
                          SUM(CASE WHEN se.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners
                      FROM student_event_register se
                      LEFT JOIN student_register sr ON se.regno = sr.regno
                      $student_where_clause) as student_stats,
                      (SELECT
                          COUNT(DISTINCT te.topic) as total_events,
                          COUNT(DISTINCT te.staff_id) as unique_participants,
                          COUNT(*) as total_registrations
                      FROM staff_event_reg te
                      LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
                      $teacher_where_clause) as teacher_stats";
                  }

                  $stats_stmt = $conn->prepare($stats_sql);

                  // Set parameters for stats based on participant type
                  if ($filter_participant_type === 'student') {
                      $stats_params      = $student_params;
                      $stats_param_types = $student_param_types;
                  } elseif ($filter_participant_type === 'teacher') {
                      $stats_params      = $teacher_params;
                      $stats_param_types = $teacher_param_types;
                  } else {
                      // Combined query needs both student and teacher params
                      $stats_params      = array_merge($student_params, $teacher_params);
                      $stats_param_types = $student_param_types . $teacher_param_types;
                  }

                  if (! empty($stats_params)) {
                      $stats_stmt->bind_param($stats_param_types, ...$stats_params);
                  }
                  $stats_stmt->execute();
                  $stats_result = $stats_stmt->get_result();

                  if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
                      echo "<p><strong>Total Events:</strong> " . $stats_row['total_events'] . "</p>";
                      echo "<p><strong>Unique " . $stats_row['participant_label'] . ":</strong> " . $stats_row['unique_participants'] . "</p>";
                      echo "<p><strong>Total Registrations:</strong> " . $stats_row['total_registrations'] . "</p>";
                      echo "<p><strong>Prize Winners:</strong> " . $stats_row['prize_winners'] . " (Students only)</p>";
                  }
                  echo "</div>";

              } else {
                  echo "<div class='no-data'>";
                  echo "<h3>No Participants Found</h3>";
                  echo "<p>No participants match your current filter criteria.</p>";
                  echo "<a href='participants.php' class='btn btn-primary'>Clear Filters</a>";
                  echo "</div>";
              }

              $stmt->close();
              $conn->close();
          ?>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
      <div class="modal-content">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this participant record? This action cannot be undone.</p>
        <div class="modal-actions">
          <button onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
          <form method="POST" style="display: inline;">
            <input type="hidden" name="delete_id" id="deleteId">
            <input type="hidden" name="delete_type" id="deleteType">
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>

    <script src="./JS/scripts.js"></script>
    <script>
      // Prevent back button to login page
      if (window.history && window.history.pushState) {
        window.history.pushState(null, null, window.location.href);
        window.addEventListener('popstate', function () {
          window.history.pushState(null, null, window.location.href);
        });
      }

      // Navigation function for header profile
      function navigateToProfile() {
        window.location.href = 'profile.php';
      }

      // Delete confirmation functions
      function confirmDelete(id, participantType) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteType').value = participantType || 'student';
        document.getElementById('deleteModal').style.display = 'block';
      }

      function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
      }

      // Close modal when clicking outside
      window.onclick = function(event) {
        const modal = document.getElementById('deleteModal');
        if (event.target == modal) {
          modal.style.display = 'none';
        }
      }

      // Auto-submit form when changing entries per page
      document.getElementById('entries').addEventListener('change', function() {
        this.form.submit();
      });

      // Search validation
      document.querySelector('form').addEventListener('submit', function(e) {
        const searchInput = document.getElementById('search');
        const searchValue = searchInput.value.trim();

        if (searchValue.length > 0 && searchValue.length < 2) {
          e.preventDefault();
          alert('Search query must be at least 2 characters long.');
          searchInput.focus();
          return false;
        }
      });

      // Real-time search validation feedback
      document.getElementById('search').addEventListener('input', function() {
        const searchValue = this.value.trim();

        if (searchValue.length === 1) {
          this.style.borderColor = '#dc3545';
          this.style.backgroundColor = '#fff5f5';
          this.title = 'Search query must be at least 2 characters long';
        } else {
          this.style.borderColor = '';
          this.style.backgroundColor = '';
          this.title = '';
        }
      });

      // Participant export dropdown functionality
      function toggleParticipantExportDropdown() {
        const dropdown = document.getElementById("participantExportDropdownContent");
        dropdown.style.display = dropdown.style.display === "none" ? "block" : "none";
      }

      // Close dropdown when clicking outside
      window.onclick = function(event) {
        if (!event.target.matches('.dropdown-toggle') && !event.target.closest('.dropdown-toggle')) {
          const dropdowns = document.getElementsByClassName("dropdown-content");
          for (let i = 0; i < dropdowns.length; i++) {
            dropdowns[i].style.display = "none";
          }
        }
      }
    </script>
  </body>
</html>