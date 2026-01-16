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

    // Don't close connection here as it's used later in the file
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Report</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="./CSS/report.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="grid-container">

    <div class="header">
        <div class="menu-icon" onclick="openSidebar()">
            <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="header-logo">
            <img class="logo" src="../sona_logo.jpg" alt="Sona College Logo" height="60px" width="200"/>
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
                <span class="material-symbols-outlined" onclick="closeSidebar()">close</span>
            </div>
            <ul class="sidebar-list">
                <li class="sidebar-list-item">
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
                  <span class="material-symbols-outlined">school</span>
                  <a href="manage_counselors.php">Manage Counselors</a>
                </li>
                <li class="sidebar-list-item active">
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

    <div class="main">
        <div class="main-card">
            <form class="form" action="" method="POST">
                <div class="card">
                    <div class="inner-card">
                        <label for="year">Academic Year:</label>
                        <select name="year" id="year">
                            <option value="">Select Academic Year</option>
                            <?php
                                // Generate last 10 academic years starting from current year
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
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="department">Department:</label>
                        <select name="department" id="department">
                            <option value="">Select Department</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="CSE">CSE</option>
                            <option value="AIML">AIML</option>
                            <option value="AIDS">AIDS</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
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
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="event_type">Event Type:</label>
                        <select name="event_type" id="event_type">
                            <option value="">Select Event Type</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Competition">Competition</option>
                            <option value="Hackathon">Hackathon</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="location">Location:</label>
                        <select name="location" id="location">
                            <option value="">Select Location</option>
                            <option value="tamilnadu">Tamil Nadu</option>
                            <option value="outside">Outside Tamil Nadu</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
                        <input type="submit" name="submit" value="Submit" id="button" />
                    </div>
                </div>
            </form>
        </div>

        <div class="report">
            <p class="main_report_heading">Report</p>
            <?php
                if ($_SERVER["REQUEST_METHOD"] == "POST") {
                    $conn = new mysqli("localhost", "root", "", "event_management_system");
                    if ($conn->connect_error) {
                        die("Connection failed: " . $conn->connect_error);
                    }

                    $year       = isset($_POST['year']) && $_POST['year'] !== '' ? $_POST['year'] : null;
                    $department = isset($_POST['department']) && $_POST['department'] !== '' ? $_POST['department'] : null;
                    $semester   = isset($_POST['semester']) && $_POST['semester'] !== '' ? $_POST['semester'] : null;
                    $event_type = isset($_POST['event_type']) && $_POST['event_type'] !== '' ? $_POST['event_type'] : null;
                    $location   = isset($_POST['location']) && $_POST['location'] !== '' ? $_POST['location'] : null;

                    // Build dynamic WHERE clause
                    $where_conditions = ["e.verification_status = 'Approved'"];
                    $bind_types       = "";
                    $bind_values      = [];

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
                            $bind_types .= 's';
                            $bind_values[] = $pattern;
                        }
                    }

                    // Add department filter if selected
                    if ($department !== null) {
                        $where_conditions[] = "e.department = ?";
                        $bind_types .= 's';
                        $bind_values[] = $department;
                    }

                    // Add semester filter if selected
                    if ($semester !== null) {
                        $where_conditions[] = "e.semester = ?";
                        $bind_types .= 's';
                        $bind_values[] = $semester;
                    }

                    // Add event type filter if selected
                    if ($event_type !== null) {
                        $where_conditions[] = "e.event_type = ?";
                        $bind_types .= 's';
                        $bind_values[] = $event_type;
                    }

                    // Add location filter if selected
                    if ($location !== null) {
                        if ($location === 'tamilnadu') {
                            $where_conditions[] = "(LOWER(e.state) = 'tamil nadu' OR LOWER(e.state) = 'tamilnadu')";
                        } else {
                            $where_conditions[] = "(LOWER(e.state) != 'tamil nadu' AND LOWER(e.state) != 'tamilnadu' AND e.state IS NOT NULL AND e.state != '')";
                        }
                    }

                    // Build final SQL query
                    $where_clause = implode(' AND ', $where_conditions);
                    $sql          = "SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
                                 e.state, e.district, e.event_type, e.event_name, e.start_date, e.end_date, e.no_of_days,
                                 e.organisation, e.prize, e.prize_amount, e.event_poster, e.certificates
                           FROM student_event_register e
                           JOIN student_register s ON e.regno = s.regno
                           WHERE $where_clause";

                    $stmt = $conn->prepare($sql);

                    // Bind parameters only if there are any
                    if (! empty($bind_values)) {
                        $stmt->bind_param($bind_types, ...$bind_values);
                    }

                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
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

                            // Event Poster Download (BLOB version)
                            if (! empty($row['event_poster'])) {
                                echo "<td><a href='download.php?id=" . $row['id'] . "&type=poster' target='_blank'>Download Poster</a></td>";
                            } else {
                                echo "<td><span style='color:gray;'>No Poster</span></td>";
                            }

                            // Certificate Download (BLOB version)
                            if (! empty($row['certificates'])) {
                                echo "<td><a href='download_new.php?id=" . $row['id'] . "&type=certificate' target='_blank'>Download Certificate</a></td>";
                            }

                            echo "</tr>";
                        }

                        echo "</tbody>";
                        echo "</table>";

                        echo "<div style='display: flex; gap: 10px; margin-top: 20px;'>";

                        // Excel export button
                        echo "<form method='POST' action='export_excel.php' target='_blank' style='margin: 0;'>";
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

                        echo "<button type='submit'>Download as Excel</button>";
                        echo "</form>";

                        // Download all certificates button
                        echo "<form method='POST' action='download_certificates.php' target='_blank' style='margin: 0;'>";
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

                        echo "<button type='submit' style='background-color: #28a745; border-color: #28a745;'>Download All Certificates</button>";
                        echo "</form>";

                        echo "</div>";

                    } else {
                        echo "<p>No records found.</p>";
                    }

                    $stmt->close();
                    $conn->close();
                }
            ?>
        </div>
    </div>
</div>

<script>
// Prevent back button navigation
history.pushState(null, null, location.href);
window.onpopstate = function () {
    history.go(1);
};

// Add sidebar functionality
function openSidebar() {
    document.getElementById("sidebar").style.display = "block";
}

function closeSidebar() {
    document.getElementById("sidebar").style.display = "none";
}

// Navigation function for header profile
function navigateToProfile() {
    window.location.href = 'profile.php';
}
</script>
</body>
</html>
