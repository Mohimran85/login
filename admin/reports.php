<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Get user data for header profile
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = $_SESSION['username'];
$user_data = null;
$user_type = "";
$tables = ['student_register', 'teacher_register'];

foreach ($tables as $table) {
    $sql = "SELECT name FROM $table WHERE username=?";
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
    <title>Report</title>
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
            <img class="logo" src="./asserts/sona_logo.jpg" alt="Sona College Logo" height="60px" width="200"/>
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
                  <span class="material-symbols-outlined">event</span>
                  <a href="add_event.php">Add Events</a>
                </li>
                <li class="sidebar-list-item">
                  <span class="material-symbols-outlined">people</span>
                  <a href="participants.php">Participants</a>
                </li>
                <li class="sidebar-list-item active">
                  <span class="material-symbols-outlined">bar_chart</span>
                  <a href="reports.php">Reports</a>
                </li>
                <li class="sidebar-list-item">
                  <span class="material-symbols-outlined">account_circle</span>
                  <a href="profile.php">Profile</a>
                </li>
                <li class="sidebar-list-item"><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </aside>

    <div class="main">
        <div class="main-card">
            <form class="form" action="" method="POST">
                <div class="card">
                    <div class="inner-card">
                        <label for="year">Year:</label>
                        <select name="year" id="year" required>
                            <option value="">Select Year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="department">Department:</label>
                        <select name="department" id="department" required>
                            <option value="">Select Department</option>
                            <option value="IT">IT</option>
                            <option value="CSE">CSE</option>
                            <option value="AIML">AIML</option>
                            <option value="AIDS">AIDS</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="semester">Semester:</label>
                        <select name="semester" id="semester" required>
                            <option value="">Select Semester</option>
                            <option value="first semester">First semester</option>
                            <option value="second semester">Second semester</option>
                            <option value="third semester">Third semester</option>
                            <option value="fourth semester">Fourth semester</option>
                            <option value="fifth semester">Fifth semester</option>
                            <option value="sixth semester">Sixth semester</option>
                            <option value="seventh semester">Seventh semester</option>
                            <option value="eighth semester">Eighth semester</option>
                        </select>
                    </div>
                </div>

                <div class="card">
                    <div class="inner-card">
                        <label for="event_type">Event Type:</label>
                        <select name="event_type" id="event_type" required>
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

                $year = $_POST['year'];
                $department = $_POST['department'];
                $semester = $_POST['semester'];
                $event_type = $_POST['event_type'];

                $stmt = $conn->prepare("SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
                                         e.state, e.district, e.event_type, e.event_name, e.attended_date,
                                         e.organisation, e.prize, e.prize_amount, e.event_poster, e.certificates
                                   FROM student_event_register e
                                   JOIN student_register s ON e.regno = s.regno
                                   WHERE e.current_year=? AND e.department=? AND e.semester=? AND e.event_type=?");
                $stmt->bind_param("ssss", $year, $department, $semester, $event_type);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    echo "<table class='report-table'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>S.No</th>";
                    echo "<th>Reg No</th>";
                    echo "<th>Name</th>";
                    echo "<th>Current Year</th>";
                    echo "<th>Semester</th>";
                    echo "<th>Department</th>";
                    echo "<th>State</th>";
                    echo "<th>District</th>";
                    echo "<th>Event Type</th>";
                    echo "<th>Event Name</th>";
                    echo "<th>Attended Date</th>";
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
                        echo "<td>" . htmlspecialchars($row['attended_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['prize_amount']) . "</td>";
                        
                        // Event Poster Download (BLOB version)
                        if (!empty($row['event_poster'])) {
                            echo "<td><a href='download.php?id=" . $row['id'] . "&type=poster' target='_blank'>Download Poster</a></td>";
                        } else {
                            echo "<td><span style='color:gray;'>No Poster</span></td>";
                        }
                        
                        // Certificate Download (BLOB version)
                        if (!empty($row['certificates'])) {
                            echo "<td><a href='download.php?id=" . $row['id'] . "&type=certificate' target='_blank'>Download Certificate</a></td>";
                        } else {
                            echo "<td><span style='color:gray;'>No Certificate</span></td>";
                        }

                        echo "</tr>";
                    }

                    echo "</tbody>";
                    echo "</table>";
                    
                    echo "<form method='POST' action='export_excel.php' target='_blank'>";
                    echo "<input type='hidden' name='year' value='" . htmlspecialchars($year) . "'>";
                    echo "<input type='hidden' name='department' value='" . htmlspecialchars($department) . "'>";
                    echo "<input type='hidden' name='semester' value='" . htmlspecialchars($semester) . "'>";
                    echo "<input type='hidden' name='event_type' value='" . htmlspecialchars($event_type) . "'>";
                    echo "<button type='submit'>Download as Excel</button>";
                    echo "</form>";
                   

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
