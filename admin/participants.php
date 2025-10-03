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

    // Handle delete operation
    if (isset($_POST['delete_id'])) {
        $delete_id   = $_POST['delete_id'];
        $delete_sql  = "DELETE FROM student_event_register WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $delete_id);
        if ($delete_stmt->execute()) {
            $success_message = "Participant record deleted successfully!";
        } else {
            $error_message = "Error deleting record: " . $conn->error;
        }
        $delete_stmt->close();
    }

    // Get filter parameters
    $filter_event_type = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $filter_department = isset($_GET['department']) ? $_GET['department'] : '';
    $filter_year       = isset($_GET['year']) ? $_GET['year'] : '';
    $filter_prize      = isset($_GET['prize']) ? $_GET['prize'] : '';
    $search_query      = isset($_GET['search']) ? $_GET['search'] : '';
    $entries_param     = isset($_GET['entries']) ? $_GET['entries'] : '10';
    $entries_per_page  = ($entries_param === 'all') ? PHP_INT_MAX : (int) $entries_param;
    $current_page      = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Build WHERE clause based on filters
    $where_conditions = [];
    $params           = [];
    $param_types      = "";

    if (! empty($filter_event_type)) {
        $where_conditions[] = "e.event_type = ?";
        $params[]           = $filter_event_type;
        $param_types .= "s";
    }

    if (! empty($filter_department)) {
        $where_conditions[] = "e.department = ?";
        $params[]           = $filter_department;
        $param_types .= "s";
    }

    if (! empty($filter_year)) {
        $where_conditions[] = "e.current_year = ?";
        $params[]           = $filter_year;
        $param_types .= "s";
    }

    if (! empty($filter_prize) && $filter_prize !== 'all') {
        if ($filter_prize === 'no_prize') {
            $where_conditions[] = "(e.prize IS NULL OR e.prize = '' OR e.prize = 'No Prize')";
        } else {
            $where_conditions[] = "e.prize = ?";
            $params[]           = $filter_prize;
            $param_types .= "s";
        }
    }

    if (! empty($search_query)) {
        $where_conditions[] = "(s.name LIKE ? OR e.regno LIKE ? OR e.event_name LIKE ?)";
        $search_param       = "%$search_query%";
        $params[]           = $search_param;
        $params[]           = $search_param;
        $params[]           = $search_param;
        $param_types .= "sss";
    }

    $where_clause = ! empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM student_event_register e
              LEFT JOIN student_register s ON e.regno = s.regno $where_clause";
    $count_stmt = $conn->prepare($count_sql);
    if (! empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
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
    <title>Participants</title>
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

          <!-- Filters Section -->
          <div class="filters-container">
            <h3>Filter Participants</h3>
            <form method="GET" action="">
              <div class="filters-row">
                <div class="filter-group">
                  <label for="search">Search:</label>
                  <input type="text" id="search" name="search"
                         placeholder="Name, Reg No, Event..."
                         value="<?php echo htmlspecialchars($search_query); ?>" style="margin-bottom:20px;">
                </div>

                <div class="filter-group">
                  <label for="event_type">Event Type:</label>
                  <select name="event_type" id="event_type">
                    <option value="">All Event Types</option>
                    <?php
                        // Get distinct event types
                        $types_sql    = "SELECT DISTINCT event_type FROM student_event_register WHERE event_type IS NOT NULL ORDER BY event_type";
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
                        // Get distinct departments
                        $dept_sql    = "SELECT DISTINCT department FROM student_event_register WHERE department IS NOT NULL ORDER BY department";
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
                  <label for="year">Year:</label>
                  <select name="year" id="year">
                    <option value="">All Years</option>
                    <option value="I"                                                                                                                                                                                                                               <?php echo($filter_year === 'I') ? 'selected' : ''; ?>>I Year</option>
                    <option value="II"                                                                                                                                                                                                                                     <?php echo($filter_year === 'II') ? 'selected' : ''; ?>>II Year</option>
                    <option value="III"                                                                                                                                                                                                                                           <?php echo($filter_year === 'III') ? 'selected' : ''; ?>>III Year</option>
                    <option value="IV"                                                                                                                                                                                                                                     <?php echo($filter_year === 'IV') ? 'selected' : ''; ?>>IV Year</option>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="prize">Prize Status:</label>
                  <select name="prize" id="prize">
                    <option value="">All</option>
                    <option value="First"                                                                                                                                                                                                                                                       <?php echo($filter_prize === 'First') ? 'selected' : ''; ?>>First Prize</option>
                    <option value="Second"                                                                                                                                                                                                                                                             <?php echo($filter_prize === 'Second') ? 'selected' : ''; ?>>Second Prize</option>
                    <option value="Third"                                                                                                                                                                                                                                                       <?php echo($filter_prize === 'Third') ? 'selected' : ''; ?>>Third Prize</option>
                    <option value="no_prize"                                                                                                                                                                                                                                                                         <?php echo($filter_prize === 'no_prize') ? 'selected' : ''; ?>>No Prize</option>
                  </select>
                </div>

                <div class="filter-group">
                  <label for="entries">Show:</label>
                  <select name="entries" id="entries">
                    <option value="10"                                                                             <?php echo($entries_param === '10') ? 'selected' : ''; ?>>10 entries</option>
                    <option value="25"                                                                             <?php echo($entries_param === '25') ? 'selected' : ''; ?>>25 entries</option>
                    <option value="50"                                                                             <?php echo($entries_param === '50') ? 'selected' : ''; ?>>50 entries</option>
                    <option value="100"                                                                               <?php echo($entries_param === '100') ? 'selected' : ''; ?>>100 entries</option>
                    <option value="all"                                                                               <?php echo($entries_param === 'all') ? 'selected' : ''; ?>>All entries</option>
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
              // Get participants data with filters and pagination
              $sql = "SELECT
                      e.id,
                      e.regno,
                      s.name,
                      e.current_year,
                      e.semester,
                      e.department,
                      e.event_type,
                      e.event_name,
                      e.attended_date,
                      e.organisation,
                      e.prize,
                      e.prize_amount,
                      e.event_poster,
                      e.certificates
                  FROM student_event_register e
                  LEFT JOIN student_register s ON e.regno = s.regno
                  $where_clause
                  ORDER BY e.attended_date DESC, e.id DESC";

              // Add LIMIT clause only if not showing all entries
              if ($entries_param !== 'all') {
                  $sql .= " LIMIT ? OFFSET ?";
              }

              $stmt = $conn->prepare($sql);

              // Add pagination parameters only if not showing all entries
              if ($entries_param !== 'all') {
                  $params[] = $entries_per_page;
                  $params[] = $offset;
                  $param_types .= "ii";
              }

              if (! empty($params)) {
                  $stmt->bind_param($param_types, ...$params);
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
                  echo "<div><a href='export_excel.php?" . http_build_query($_GET) . "' class='btn btn-primary'>
                      <span class='material-symbols-outlined'>download</span> Export Excel</a></div>";
                  echo "</div>";

                  echo "<div class='participants-container'>";
                  echo "<table class='participants-table'>";
                  echo "<thead>";
                  echo "<tr>";
                  echo "<th>S.No</th>";
                  echo "<th>Reg No</th>";
                  echo "<th>Student Name</th>";
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
                      echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                      echo "<td>" . htmlspecialchars($row['current_year']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
                      echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
                      echo "<td>" . date('d-M-Y', strtotime($row['attended_date'])) . "</td>";
                      echo "<td>" . htmlspecialchars($row['prize'] ?: 'No Prize') . "</td>";

                      // Files column with download links
                      echo "<td class='files-cell'>";
                      if (! empty($row['event_poster'])) {
                          echo "<a href='download.php?id=" . $row['id'] . "&type=poster' class='download-btn' target='_blank'>📄 Poster</a>";
                      }
                      if (! empty($row['certificates'])) {
                          echo "<a href='download.php?id=" . $row['id'] . "&type=certificate' class='download-btn' target='_blank'>🏆 Certificate</a>";
                      }
                      if (empty($row['event_poster']) && empty($row['certificates'])) {
                          echo "<span class='no-files'>No files</span>";
                      }
                      echo "</td>";

                      // Actions column
                      echo "<td>";
                      echo "<div class='action-buttons'>";
                      echo "<a href='edit_participant.php?id=" . $row['id'] . "' class='btn btn-warning' title='Edit'>
                          <span class='material-symbols-outlined'>edit</span>
                        </a>";
                      echo "<button onclick='confirmDelete(" . $row['id'] . ")' class='btn btn-danger' title='Delete'>
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

                  // Get additional stats with same filters
                  $stats_sql = "SELECT
                  COUNT(DISTINCT e.event_name) as total_events,
                  COUNT(DISTINCT e.regno) as unique_students,
                  COUNT(*) as total_registrations,
                  SUM(CASE WHEN e.prize IN ('First', 'Second', 'Third') THEN 1 ELSE 0 END) as prize_winners
              FROM student_event_register e
              LEFT JOIN student_register s ON e.regno = s.regno
              $where_clause";

                  $stats_stmt = $conn->prepare($stats_sql);
                  if (! empty($where_conditions)) {
                      // Remove pagination params for stats
                      $stats_params      = array_slice($params, 0, -2);
                      $stats_param_types = substr($param_types, 0, -2);
                      $stats_stmt->bind_param($stats_param_types, ...$stats_params);
                  }
                  $stats_stmt->execute();
                  $stats_result = $stats_stmt->get_result();

                  if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
                      echo "<p><strong>Total Events:</strong> " . $stats_row['total_events'] . "</p>";
                      echo "<p><strong>Unique Students:</strong> " . $stats_row['unique_students'] . "</p>";
                      echo "<p><strong>Total Registrations:</strong> " . $stats_row['total_registrations'] . "</p>";
                      echo "<p><strong>Prize Winners:</strong> " . $stats_row['prize_winners'] . "</p>";
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
      function confirmDelete(id) {
        document.getElementById('deleteId').value = id;
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
    </script>
  </body>
</html>