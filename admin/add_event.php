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
    <title>Add Event</title>
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
            <li class="sidebar-list-item active">
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

      <!-- Main content for Add Event page -->
      <div class="main">
        <div class="main-content">
          <h2>Add New Event</h2>
          <p>Event creation functionality will be implemented here.</p>
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