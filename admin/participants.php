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

$conn->close();
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
            <li class="sidebar-list-item">
              <span class="material-symbols-outlined">event</span>
              <a href="add_event.php">Add Events</a>
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
          <h2>Event Participants</h2>
          
          <?php
          // Reconnect to database for participants data
          $conn = new mysqli("localhost", "root", "", "event_management_system");
          if ($conn->connect_error) {
              die("Connection failed: " . $conn->connect_error);
          }

          // Get all participants with their details
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
                  ORDER BY e.attended_date DESC, e.id DESC";
          
          $result = $conn->query($sql);
          
          if ($result && $result->num_rows > 0) {
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
              echo "</tr>";
              echo "</thead>";
              echo "<tbody>";
              
              $sno = 1;
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
                  echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
                  
                  // Files column with download links
                  echo "<td class='files-cell'>";
                  if (!empty($row['event_poster'])) {
                      echo "<a href='download.php?id=" . $row['id'] . "&type=poster' class='download-btn' target='_blank'>📄 Poster</a>";
                  }
                  if (!empty($row['certificates'])) {
                      echo "<a href='download.php?id=" . $row['id'] . "&type=certificate' class='download-btn' target='_blank'>🏆 Certificate</a>";
                  }
                  if (empty($row['event_poster']) && empty($row['certificates'])) {
                      echo "<span class='no-files'>No files</span>";
                  }
                  echo "</td>";
                  
                  echo "</tr>";
              }
              
              echo "</tbody>";
              echo "</table>";
              echo "</div>";
              
              // Add some statistics
              echo "<div class='stats-summary'>";
              echo "<h3>Summary</h3>";
              echo "<p><strong>Total Participants:</strong> " . $result->num_rows . "</p>";
              
              // Get additional stats
              $stats_sql = "SELECT 
                  COUNT(DISTINCT event_name) as total_events,
                  COUNT(DISTINCT regno) as unique_students,
                  COUNT(*) as total_registrations
              FROM student_event_register";
              
              $stats_result = $conn->query($stats_sql);
              if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
                  echo "<p><strong>Total Events:</strong> " . $stats_row['total_events'] . "</p>";
                  echo "<p><strong>Unique Students:</strong> " . $stats_row['unique_students'] . "</p>";
                  echo "<p><strong>Total Registrations:</strong> " . $stats_row['total_registrations'] . "</p>";
              }
              echo "</div>";
              
          } else {
              echo "<div class='no-data'>";
              echo "<h3>No Participants Found</h3>";
              echo "<p>No event registrations have been submitted yet.</p>";
              echo "</div>";
          }
          
          $conn->close();
          ?>
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
    </script>
  </body>
</html>