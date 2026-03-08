<?php
    session_start();

    // Check if user is logged in
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
    $conn = get_db_connection();

    // Get teacher data
    $username       = $_SESSION['username'];
    $teacher_data   = null;
    $teacher_status = 'teacher';
    $is_admin       = false;
    $is_counselor   = false;

    $sql  = "SELECT id, name, faculty_id as employee_id, COALESCE(status, 'teacher') as status, COALESCE(is_hackathon_coordinator, 0) as is_hackathon_coordinator FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
    $teacher_data   = $result->fetch_assoc();
    $teacher_status = $teacher_data['status'];
    $is_admin       = ($teacher_status === 'admin');
    $is_counselor   = ($teacher_status === 'counselor');
    }
    $stmt->close();

    // Only counselors can access this page
    if (! $is_counselor) {
    header("Location: index.php");
    exit();
    }

    // Get counselor ID
    $counselor_id = $teacher_data['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Counselor Reports</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../assets/images/favicon_io/site.webmanifest">
    <!-- css link -->
    <link rel="stylesheet" href="../student/student_dashboard.css" />
    <!-- google icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <!-- google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet" />

    <style>
        /* Report Page Specific Styles */
        .main {
            padding: 20px;
            min-height: calc(100vh - 80px);
            overflow-x: hidden;
            max-width: 100%;
        }
        .header {
            grid-area: header;
            background-color: #fff;
            height: 80px;
            display: flex;
            font-size: 15px;
            font-weight: 100;
            align-items: center;
            justify-content: space-between;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px,
                rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;
            color: #1e4276;
            position: fixed;
            width: 100%;
            z-index: 1001;
            top: 0;
            left: 0;
        }

        /* Filter Form Styles */
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: #ffffff;
            border: 1px solid #e9ecef;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #1e4276;
        }

        .filter-group select {
            font-family: "Poppins", sans-serif;
            width: 100%;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            outline: none;
            color: #333;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus {
            border-color: #2d5aa0;
        }

        .filter-submit {
            display: flex;
            align-items: flex-end;
        }

        .filter-submit input[type="submit"] {
            font-family: "Poppins", sans-serif;
            width: 100%;
            padding: 10px 20px;
            background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .filter-submit input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 66, 118, 0.3);
        }

        /* Report Section */
        .report-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 25px;
            overflow: hidden;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            position: relative;
        }

        .table-wrapper::-webkit-scrollbar {
            height: 10px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 5px;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #2d5aa0;
            border-radius: 5px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #1e4276;
        }

        .scroll-hint {
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            padding: 6px 0 2px;
        }

        .scroll-hint span {
            vertical-align: middle;
            font-size: 16px;
        }

        .report-heading {
            font-size: 20px;
            font-weight: 600;
            color: #1e4276;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Report Table */
        .report-table {
            width: 100%;
            min-width: 1200px;
            border-collapse: collapse;
            font-family: "Poppins", sans-serif;
            font-size: 13px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }

        .report-table thead {
            background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
        }

        .report-table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            border: none;
            white-space: nowrap;
        }

        .report-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }

        .report-table tbody tr:hover {
            background: #f8f9fa;
        }

        .report-table tbody tr:nth-child(even) {
            background: #fdfdfd;
        }

        .report-table td {
            padding: 10px;
            color: #333;
            font-size: 13px;
            text-align: left;
            vertical-align: middle;
        }

        .report-table a {
            color: #2d5aa0;
            text-decoration: none;
            font-weight: 500;
        }

        .report-table a:hover {
            text-decoration: underline;
        }

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .export-buttons button {
            font-family: "Poppins", sans-serif;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            transition: all 0.3s ease;
        }

        .export-buttons button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-excel {
            background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
        }

        .btn-certificates {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }

        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-areas: "main";
                grid-template-columns: 1fr;
                padding-top: 80px;
            }

            .header .menu-icon {
                display: block;
            }

            .header .header-logo {
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

            .main {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 10px;
            }

            .filter-form {
                grid-template-columns: 1fr;
                padding: 15px;
                gap: 12px;
            }

            .report-section {
                padding: 15px 10px;
                border-radius: 10px;
            }

            .report-heading {
                font-size: 16px;
            }

            .report-table {
                font-size: 11px;
                min-width: 900px;
            }

            .report-table thead th,
            .report-table td {
                padding: 8px 6px;
            }

            .export-buttons {
                justify-content: center;
            }

            .export-buttons button {
                font-size: 12px;
                padding: 8px 14px;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 8px;
            }

            .filter-form {
                padding: 12px;
                gap: 10px;
            }

            .filter-group select {
                padding: 8px 10px;
                font-size: 13px;
            }

            .filter-submit input[type="submit"] {
                padding: 10px 16px;
                font-size: 14px;
            }

            .report-section {
                padding: 10px 5px;
            }

            .report-table {
                min-width: 800px;
            }

            .export-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .export-buttons button {
                width: 100%;
            }
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
        <div class="header-logo">
          <img class="logo" src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200" />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div>
          <!-- empty -->
        </div>
      </div>

      <!-- sidebar -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-title">Counselor Portal</div>
          <div class="close-sidebar">
            <span class="material-symbols-outlined">close</span>
          </div>
        </div>

        <div class="student-info">
          <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
          <div class="student-regno">ID: <?php echo htmlspecialchars($teacher_data['employee_id']); ?> (Counselor)</div>
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
              <a href="registered_students.php" class="nav-link">
                <span class="material-symbols-outlined">group</span>
                Registered Students
              </a>
            </li>
            <li class="nav-item">
              <a href="assigned_students.php" class="nav-link">
                <span class="material-symbols-outlined">supervisor_account</span>
                My Assigned Students
              </a>
            </li>
            <li class="nav-item">
              <a href="od_approvals.php" class="nav-link">
                <span class="material-symbols-outlined">approval</span>
                OD Approvals
              </a>
            </li>
            <li class="nav-item">
              <a href="internship_approvals.php" class="nav-link">
                <span class="material-symbols-outlined">school</span>
                Internship Approvals
              </a>
            </li>
            <li class="nav-item">
              <a href="verify_events.php" class="nav-link">
                <span class="material-symbols-outlined">card_giftcard</span>
                Event Certificate Validation
              </a>
            </li>
            <li class="nav-item">
              <a href="reports.php" class="nav-link active">
                <span class="material-symbols-outlined">bar_chart</span>
                Reports
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
        <form class="filter-form" action="" method="POST">
            <div class="filter-group">
                <label for="year">Academic Year:</label>
                <select name="year" id="year">
                    <option value="">Select Academic Year</option>
                    <?php
                        $current_year = date('Y');
                        for ($i = 0; $i < 10; $i++) {
                            $start_year    = $current_year - $i;
                            $end_year      = $start_year + 1;
                            $academic_year = $start_year . '-' . $end_year;
                            echo "<option value='$academic_year'>$academic_year</option>";
                        }
                    ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="department">Department:</label>
                <select name="department" id="department">
                    <option value="">Select Department</option>
                    <option value="Information Technology">Information Technology</option>
                    <option value="CSE">CSE</option>
                    <option value="AIML">AIML</option>
                    <option value="AIDS">AIDS</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="semester">Semester:</label>
                <select name="semester" id="semester">
                    <option value="">Select Semester</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                    <option value="3">Semester 3</option>
                    <option value="4">Semester 4</option>
                    <option value="5">Semester 5</option>
                    <option value="6">Semester 6</option>
                    <option value="7">Semester 7</option>
                    <option value="8">Semester 8</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="event_type">Event Type:</label>
                <select name="event_type" id="event_type">
                    <option value="">Select Event Type</option>
                    <option value="Workshop">Workshop</option>
                    <option value="Seminar">Seminar</option>
                    <option value="Competition">Competition</option>
                    <option value="Hackathon">Hackathon</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="location">Location:</label>
                <select name="location" id="location">
                    <option value="">Select Location</option>
                    <option value="tamilnadu">Tamil Nadu</option>
                    <option value="outside">Outside Tamil Nadu</option>
                </select>
            </div>

            <div class="filter-submit">
                <input type="submit" name="submit" value="Submit" />
            </div>
        </form>

        <div class="report-section">
            <p class="report-heading">Report</p>
            <?php
                if ($_SERVER["REQUEST_METHOD"] == "POST") {

                    $year       = isset($_POST['year']) && $_POST['year'] !== '' ? $_POST['year'] : null;
                    $department = isset($_POST['department']) && $_POST['department'] !== '' ? $_POST['department'] : null;
                    $semester   = isset($_POST['semester']) && $_POST['semester'] !== '' ? $_POST['semester'] : null;
                    $event_type = isset($_POST['event_type']) && $_POST['event_type'] !== '' ? $_POST['event_type'] : null;
                    $location   = isset($_POST['location']) && $_POST['location'] !== '' ? $_POST['location'] : null;

                    // Build dynamic WHERE clause - scoped to counselor's assigned students
                    $where_conditions = [
                        "e.verification_status = 'Approved'",
                        "ca.counselor_id = ?",
                        "ca.status = 'active'",
                    ];
                    $bind_types  = "i";
                    $bind_values = [$counselor_id];

                    // Add year filter if selected
                    if ($year !== null) {
                        $year_patterns = [$year];
                        if (strpos($year, '-') !== false) {
                            $year_parts = explode('-', $year);
                            if (count($year_parts) == 2) {
                                $short_year      = $year_parts[0] . '-' . substr($year_parts[1], -2);
                                $year_patterns[] = $short_year;
                            }
                        }
                        $year_conditions    = implode(' OR ', array_fill(0, count($year_patterns), 'e.current_year = ?'));
                        $where_conditions[] = "($year_conditions)";
                        foreach ($year_patterns as $pattern) {
                            $bind_types    .= 's';
                            $bind_values[]  = $pattern;
                        }
                    }

                    // Add department filter if selected
                    if ($department !== null) {
                        $where_conditions[]  = "e.department = ?";
                        $bind_types         .= 's';
                        $bind_values[]       = $department;
                    }

                    // Add semester filter if selected
                    if ($semester !== null) {
                        $where_conditions[]  = "e.semester = ?";
                        $bind_types         .= 's';
                        $bind_values[]       = $semester;
                    }

                    // Add event type filter if selected
                    if ($event_type !== null) {
                        $where_conditions[]  = "e.event_type = ?";
                        $bind_types         .= 's';
                        $bind_values[]       = $event_type;
                    }

                    // Add location filter if selected
                    if ($location !== null) {
                        if ($location === 'tamilnadu') {
                            $where_conditions[] = "(LOWER(e.state) = 'tamil nadu' OR LOWER(e.state) = 'tamilnadu')";
                        } else {
                            $where_conditions[] = "(LOWER(e.state) != 'tamil nadu' AND LOWER(e.state) != 'tamilnadu' AND e.state IS NOT NULL AND e.state != '')";
                        }
                    }

                    // Build final SQL query with counselor JOIN
                    $where_clause = implode(' AND ', $where_conditions);
                    $sql          = "SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
                                 e.state, e.district, e.event_type, e.event_name, e.start_date, e.end_date, e.no_of_days,
                                 e.organisation, e.prize, e.prize_amount, e.event_poster, e.certificates
                           FROM student_event_register e
                           JOIN student_register s ON e.regno = s.regno
                           INNER JOIN counselor_assignments ca ON e.regno = ca.student_regno
                           WHERE $where_clause";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($bind_types, ...$bind_values);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        echo "<p class='scroll-hint'><span class='material-symbols-outlined'>swipe_left</span> Scroll left / right to view all columns</p>";
                        echo "<div class='table-wrapper'>";
                        echo "<table class='report-table'>";
                        echo "<thead>";
                        echo "<tr>";
                        echo "<th>S.No</th>";
                        echo "<th>Reg No</th>";
                        echo "<th>Name</th>";
                        echo "<th>Academic Year</th>";
                        echo "<th>Semester</th>";
                        echo "<th>Department</th>";
                        echo "<th>State</th>";
                        echo "<th>District</th>";
                        echo "<th>Event Type</th>";
                        echo "<th>Event Name</th>";
                        echo "<th>Start Date</th>";
                        echo "<th>End Date</th>";
                        echo "<th>No of Days</th>";
                        echo "<th>Organisation</th>";
                        echo "<th>Prize</th>";
                        echo "<th>Prize Amount</th>";
                        echo "<th>Event Poster</th>";
                        echo "<th>Certificates</th>";
                        echo "</tr>";
                        echo "</thead>";
                        echo "<tbody>";

                        $sno = 1;
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $sno++ . "</td>";
                            echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['current_year']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['semester']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['state']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['district']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['no_of_days']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['prize_amount']) . "</td>";

                            // Event Poster Download
                            if (! empty($row['event_poster'])) {
                                echo "<td><a href='../admin/download.php?id=" . $row['id'] . "&type=poster' target='_blank'>Download Poster</a></td>";
                            } else {
                                echo "<td><span style='color:gray;'>No Poster</span></td>";
                            }

                            // Certificate Download
                            if (! empty($row['certificates'])) {
                                echo "<td><a href='../admin/download_new.php?id=" . $row['id'] . "&type=certificate' target='_blank'>Download Certificate</a></td>";
                            } else {
                                echo "<td><span style='color:gray;'>No Certificate</span></td>";
                            }

                            echo "</tr>";
                        }

                        echo "</tbody>";
                        echo "</table>";
                        echo "</div>";

                        echo "<div class='export-buttons'>";

                        // Excel export button
                        echo "<form method='POST' action='export_excel_counselor.php' target='_blank' style='margin: 0;'>";
                        if ($year !== null) {
                            echo "<input type='hidden' name='year' value='" . htmlspecialchars($year) . "'>";
                        }
                        if ($department !== null) {
                            echo "<input type='hidden' name='department' value='" . htmlspecialchars($department) . "'>";
                        }
                        if ($semester !== null) {
                            echo "<input type='hidden' name='semester' value='" . htmlspecialchars($semester) . "'>";
                        }
                        if ($event_type !== null) {
                            echo "<input type='hidden' name='event_type' value='" . htmlspecialchars($event_type) . "'>";
                        }
                        if ($location !== null) {
                            echo "<input type='hidden' name='location' value='" . htmlspecialchars($location) . "'>";
                        }
                        echo "<button type='submit' class='btn-excel'>Download as Excel</button>";
                        echo "</form>";

                        // Download all certificates button
                        echo "<form method='POST' action='download_certificates_counselor.php' target='_blank' style='margin: 0;'>";
                        if ($year !== null) {
                            echo "<input type='hidden' name='year' value='" . htmlspecialchars($year) . "'>";
                        }
                        if ($department !== null) {
                            echo "<input type='hidden' name='department' value='" . htmlspecialchars($department) . "'>";
                        }
                        if ($semester !== null) {
                            echo "<input type='hidden' name='semester' value='" . htmlspecialchars($semester) . "'>";
                        }
                        if ($event_type !== null) {
                            echo "<input type='hidden' name='event_type' value='" . htmlspecialchars($event_type) . "'>";
                        }
                        if ($location !== null) {
                            echo "<input type='hidden' name='location' value='" . htmlspecialchars($location) . "'>";
                        }
                        echo "<button type='submit' class='btn-certificates'>Download All Certificates</button>";
                        echo "</form>";

                        echo "</div>";

                    } else {
                        echo "<p class='no-records'>No records found for your assigned students.</p>";
                    }

                    $stmt->close();
                    $conn->close();
                }
            ?>
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
            const sidebar = document.getElementById('sidebar');
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
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
