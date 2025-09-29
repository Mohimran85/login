<?php
session_start();

// Prevent caching to avoid back button issues
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // User is not logged in, redirect to login page
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
              <h3>Total No Students:</h3>
              <span class="material-symbols-outlined">school</span>
            </div>
            <h1>1500</h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total No Event:</h3>
              <span class="material-symbols-outlined">newsmode</span>
            </div>
            <h1>1500</h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total No faculty:</h3>
              <span class="material-symbols-outlined">person_book</span>
            </div>
            <h1>1500</h1>
          </div>

           <div class="card">
            <div class="card-inner">
              <h3>Total No Events:</h3>
              <span class="material-symbols-outlined">newsmode</span>
            </div>
            <h1>1500</h1>
          </div>
        </div>
        <!-- charts -->
        <div class="charts">
          <div class="charts-card">
            <h2 class="chart-title">Event Details</h2>
            <div id="bar-chart"></div>
          </div>

          <div class="charts-card">
            <h2 class="chart-title">Event Details</h2>
            </h2>
            <div id="area-chart"></div>
          </div>
        </div>
      </div>

      <!-- Scripts -->
      <!-- js scripts-  -->
      <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/5.3.4/apexcharts.min.js"></script>
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