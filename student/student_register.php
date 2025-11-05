<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Get logged-in user's registration number and student data
    $logged_in_regno = '';
    $student_data    = null;
    if (isset($_SESSION['username'])) {
        $conn_user = new mysqli("localhost", "root", "", "event_management_system");
        if ($conn_user->connect_error) {
            die("Connection failed: " . htmlspecialchars($conn_user->connect_error));
        }

        $username  = $_SESSION['username'];
        $user_sql  = "SELECT name, regno FROM student_register WHERE username=?";
        $user_stmt = $conn_user->prepare($user_sql);
        $user_stmt->bind_param("s", $username);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result->num_rows > 0) {
            $student_data    = $user_result->fetch_assoc();
            $logged_in_regno = $student_data['regno'];
        }

        $user_stmt->close();
        $conn_user->close();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $servername = "localhost";
        $username   = "root";
        $password   = "";
        $dbname     = "event_management_system";
        $conn       = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("Connection failed: " . htmlspecialchars($conn->connect_error));
        }

        // Sanitize inputs
        $regno         = isset($_POST['regno']) ? trim($_POST['regno']) : '';
        $current_year  = isset($_POST['year']) ? trim($_POST['year']) : '';
        $semester      = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $department    = isset($_POST['department']) ? trim($_POST['department']) : '';
        $state         = isset($_POST['state']) ? trim($_POST['state']) : '';
        $district      = isset($_POST['district']) ? trim($_POST['district']) : '';
        $event_type    = isset($_POST['eventType']) ? trim($_POST['eventType']) : '';
        $event_name    = isset($_POST['eventName']) ? trim($_POST['eventName']) : '';
        $attended_date = isset($_POST['attendedDate']) ? $_POST['attendedDate'] : '';
        $organisation  = isset($_POST['organisation']) ? trim($_POST['organisation']) : '';
        $prize         = isset($_POST['prize']) ? trim($_POST['prize']) : '';
        $prize_amount  = isset($_POST['amount']) ? trim($_POST['amount']) : '';

        // Check for approved OD request
        $od_check_sql  = "SELECT status FROM od_requests WHERE student_regno = ? AND event_name = ? AND status = 'approved'";
        $od_check_stmt = $conn->prepare($od_check_sql);
        $od_check_stmt->bind_param("ss", $regno, $event_name);
        $od_check_stmt->execute();
        $od_check_stmt->store_result();

        if ($od_check_stmt->num_rows == 0) {
            echo "<div style='background: #fff3cd; border: 1px solid #ffecb5; color: #856404; padding: 15px; border-radius: 5px; margin: 20px; text-align: center;'>";
            echo "<h3>🔒 OD Approval Required</h3>";
            echo "<p>You need to submit an OD (On Duty) request and get approval from your class counselor before registering for this event.</p>";
            echo "<p><a href='od_request.php' style='color: #0056b3; text-decoration: none; font-weight: bold;'>➤ Submit OD Request</a></p>";
            echo "</div>";
            $od_check_stmt->close();
            $conn->close();
            exit;
        }
        $od_check_stmt->close();

        $target_dir = "uploads/";
        if (! is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Validate PDF file
        function valid_pdf($file)
        {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file);
            finfo_close($finfo);
            return ($mime === 'application/pdf');
        }

        $event_poster_path = null;
        $certificate_path  = null;

        // Handle uploaded event poster
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
            $event_poster      = basename($_FILES['poster']['name']);
            $event_poster_sane = preg_replace('/[^A-Za-z0-9_\.-]/', '', $event_poster);
            $target_path       = $target_dir . uniqid('poster_') . '_' . $event_poster_sane;
            if (valid_pdf($_FILES["poster"]["tmp_name"])) {
                move_uploaded_file($_FILES["poster"]["tmp_name"], $target_path);
                $event_poster_path = $target_path;
            } else {
                echo "<p style='color:red;'>❌ Event poster must be a PDF file.</p>";
                $conn->close();exit;
            }
        }
        // Handle uploaded certificate
        if (isset($_FILES['certificates']) && $_FILES['certificates']['error'] === UPLOAD_ERR_OK) {
            $certificate      = basename($_FILES['certificates']['name']);
            $certificate_sane = preg_replace('/[^A-Za-z0-9_\.-]/', '', $certificate);
            $target_path      = $target_dir . uniqid('cert_') . '_' . $certificate_sane;
            if (valid_pdf($_FILES["certificates"]["tmp_name"])) {
                move_uploaded_file($_FILES["certificates"]["tmp_name"], $target_path);
                $certificate_path = $target_path;
            } else {
                echo "<p style='color:red;'>❌ Certificate must be a PDF file.</p>";
                $conn->close();exit;
            }
        }

        // Duplicate registration check
        $check_sql  = "SELECT id FROM student_event_register WHERE regno = ? AND event_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $regno, $event_name);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            echo "<p style='color:orange;'>⚠️ You have already registered for this event.</p>";
            $check_stmt->close();
            $conn->close();
            exit;
        }
        $check_stmt->close();

        // Insert registration
        $sql = "INSERT INTO student_event_register
        (regno, current_year, semester, state, district, department, event_type, event_name, attended_date, organisation, prize, prize_amount, event_poster, certificates)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param(
            "ssssssssssssss",
            $regno,
            $current_year,
            $semester,
            $state,
            $district,
            $department,
            $event_type,
            $event_name,
            $attended_date,
            $organisation,
            $prize,
            $prize_amount,
            $event_poster_path,
            $certificate_path
        );

        if ($stmt->execute()) {
            header("Location: thankyou.php");
            $stmt->close();
            $conn->close();
            exit;
        } else {
            echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($stmt->error) . "</p>";
            $stmt->close();
            $conn->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>Student Event Registration</title>
  <link rel="stylesheet" href="student_dashboard.css"/>

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
  <style>
    /* Modern Form Layout Styles */
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
      background: #ffffff;
      min-height: 100vh;
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
      text-size-adjust: 100%;
    }

    .registration-main {
      padding: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: #ffffff;
    }

    .registration-form {
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(12, 56, 120, 0.1);
      border: 1px solid #e1e8ed;
      padding: 40px;
      max-width: 1000px;
      width: 100%;
      margin: 20px;
    }

    .form-title {
      color: #0c3878;
      text-align: center;
      margin-bottom: 30px;
      font-size: 28px;
      font-weight: 600;
    }

    .parent {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .item {
      display: flex;
      flex-direction: column;
    }

    label {
      font-weight: 500;
      color: #0c3878;
      margin-bottom: 8px;
      font-size: 14px;
      display: flex;
      align-items: center;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="number"],
    select,
    textarea {
      padding: 12px 16px;
      border: 2px solid #e1e8ed;
      border-radius: 8px;
      font-size: 14px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s ease;
      background: #fff;
      width: 100%;
      box-sizing: border-box;
      -webkit-appearance: none;
      -moz-appearance: none;
      appearance: none;
    }

    /* Prevent zoom on iOS when focusing inputs */
    @media screen and (-webkit-min-device-pixel-ratio: 0) {
      input[type="text"],
      input[type="email"],
      input[type="date"],
      input[type="number"],
      select,
      textarea {
        font-size: 16px;
      }
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="date"]:focus,
    input[type="number"]:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: #0c3878;
      box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
      transform: translateY(-1px);
    }

    input[readonly] {
      background-color: #f8f9fa !important;
      color: #6c757d;
      cursor: not-allowed;
    }

    select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 12px center;
      background-repeat: no-repeat;
      background-size: 16px;
      padding-right: 40px;
    }

    .file-upload {
      position: relative;
      display: inline-block;
      width: 100%;
    }

    .file-upload input[type="file"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      cursor: pointer;
    }

    .file-upload-label {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 12px 16px;
      border: 2px dashed #e1e8ed;
      border-radius: 10px;
      background: #f8f9fa;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
      min-height: 50px;
    }

    .file-upload-label:hover {
      border-color: #0c3878;
      background: #f0f8ff;
    }

    .file-upload-icon {
      margin-right: 8px;
      font-size: 18px;
      color: #0c3878;
    }

    .register-btn {
      background: #0c3878;
      color: white;
      padding: 15px 40px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
      margin-top: 20px;
      box-shadow: 0 4px 15px rgba(12, 56, 120, 0.2);
    }

    .register-btn:hover {
      background: #094067;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(12, 56, 120, 0.3);
    }

    .register-btn:active {
      transform: translateY(0);
    }

    /* Enhanced form validation and UX improvements */
    .form-field-wrapper {
      position: relative;
    }

    .field-error {
      color: #dc3545;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .field-success {
      color: #28a745;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .required-asterisk {
      color: #dc3545;
      margin-left: 3px;
      font-weight: 600;
    }

    .form-field-helper {
      font-size: 12px;
      color: #6c757d;
      margin-top: 4px;
      font-style: italic;
    }

    .character-count {
      font-size: 11px;
      color: #0c3878;
      text-align: right;
      margin-top: 4px;
      font-weight: 500;
    }

    /* Success/Error Messages */
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-weight: 500;
      display: flex;
      align-items: center;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .alert-icon {
      margin-right: 10px;
      font-size: 18px;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
      body {
        font-size: 14px;
      }

      .registration-main {
        padding: 10px;
      }

      .registration-form {
        padding: 20px;
        margin: 10px;
        border-radius: 10px;
        max-width: 100%;
        width: calc(100% - 20px);
      }

      .parent {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .form-title {
        font-size: 22px;
        margin-bottom: 20px;
      }

      .form-section {
        padding: 20px;
        margin-bottom: 20px;
      }

      .form-section-title {
        font-size: 14px;
      }

      input[type="text"],
      input[type="email"],
      input[type="date"],
      input[type="number"],
      select,
      textarea {
        padding: 12px;
        font-size: 16px;
        border-radius: 6px;
        width: 100%;
        box-sizing: border-box;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
      }

      select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 12px center;
        background-repeat: no-repeat;
        background-size: 16px;
        padding-right: 40px;
      }

      .register-btn {
        padding: 16px 20px;
        font-size: 16px;
        margin-top: 25px;
      }

      .file-upload-label {
        padding: 16px;
        font-size: 14px;
      }

      label {
        font-size: 14px;
        margin-bottom: 6px;
      }

      .character-count,
      .form-field-helper,
      .file-size-info {
        font-size: 12px;
      }
    }

    /* Extra small devices */
    @media (max-width: 480px) {
      .registration-form {
        padding: 15px;
        margin: 5px;
      }

      .form-title {
        font-size: 20px;
      }

      .form-section {
        padding: 15px;
      }

      input[type="text"],
      input[type="email"],
      input[type="date"],
      input[type="number"],
      select,
      textarea {
        padding: 14px;
        font-size: 16px;
      }
    }

    /* Loading Animation */
    .loading {
      position: relative;
      pointer-events: none;
    }

    .loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid #ffffff;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* File upload styling improvements */
    .file-info {
      margin-top: 8px;
      padding: 8px 12px;
      background: #e8f5e8;
      border: 1px solid #c3e6cb;
      border-radius: 6px;
      font-size: 12px;
      color: #155724;
      display: none;
    }

    .file-size-info {
      font-size: 11px;
      color: #0c3878;
      margin-top: 4px;
      font-weight: 500;
    }

    /* Form section styling */
    .form-section {
      margin-bottom: 30px;
      padding: 25px;
      background: #f8f9fa;
      border-radius: 10px;
      border-left: 4px solid #0c3878;
      border: 1px solid #e1e8ed;
    }

    .form-section-title {
      font-size: 16px;
      font-weight: 600;
      color: #0c3878;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
    }

    .form-section-icon {
      margin-right: 8px;
      font-size: 18px;
      color: #0c3878;
    }

    .progress-bar {
      position: fixed;
      top: 0;
      left: 0;
      height: 4px;
      background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
      z-index: 1003;
      transition: width 0.3s ease;
      width: 0%;
    }

    .form-step-indicator {
      text-align: center;
      margin-bottom: 30px;
      color: #6c757d;
      font-size: 14px;
    }

    .tooltip {
      position: relative;
      display: inline-block;
      cursor: help;
    }

    .tooltip .tooltiptext {
      visibility: hidden;
      width: 200px;
      background-color: #ffffffff;
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -100px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 12px;
    }

    .tooltip:hover .tooltiptext {
      visibility: visible;
      opacity: 1;
    }

    .character-count {
      font-size: 11px;
      color: #6c757d;
      text-align: right;
      margin-top: 2px;
    }

    .file-preview {
      margin-top: 10px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      display: none;
    }

    .file-preview.show {
      display: block;
    }

    .file-name {
      font-size: 13px;
      color: #495057;
      font-weight: 500;
    }

    .file-size {
      font-size: 11px;
      color: #6c757d;
    }

    /* State-District dropdown styling */
    select:disabled {
      background-color: #f8f9fa;
      color: #6c757d;
      cursor: not-allowed;
      opacity: 0.6;
    }

    .form-field-helper {
      font-size: 11px;
      color: #6c757d;
      margin-top: 2px;
      font-style: italic;
    }

    /* Sidebar Mobile Styles */
    .menu-icon {
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 8px;
      transition: all 0.3s ease;
    }

    .menu-icon:hover {
      background: rgba(12, 56, 120, 0.1);
      border-radius: 4px;
    }

    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        left: -100%;
        top: 0;
        height: 100vh;
        width: 280px;
        background: white;
        transition: left 0.3s ease;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
      }

      .sidebar.active {
        left: 0;
      }

      .sidebar-open {
        overflow: hidden;
      }

      .sidebar-open::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
      }

      .close-sidebar {
        display: block;
        cursor: pointer;
        padding: 8px;
        transition: all 0.3s ease;
      }

      .close-sidebar:hover {
        background: rgba(12, 56, 120, 0.1);
        border-radius: 4px;
      }
    }

    @media (min-width: 769px) {
      .menu-icon {
        display: none;
      }

      .close-sidebar {
        display: none;
      }
    }
  </style>
</head>
<body>
<div class="progress-bar" id="progressBar"></div>

<div class="grid-container">
  <!-- header -->
  <div class="header">
    <div class="menu-icon" onclick="toggleSidebar()">
      <span class="material-symbols-outlined">menu</span>
    </div>
    <div class="icon">
      <img
        src="../asserts/images/Sona Logo.png"
        alt="Sona College Logo"
      />
    </div>
    <div class="header-title">
      <p>Event Management System</p>
    </div>

  </div>

  <!-- sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-title">Student Portal</div>
      <div class="close-sidebar" onclick="toggleSidebar()">
        <span class="material-symbols-outlined">close</span>
      </div>
    </div>

    <div class="student-info">
      <div class="student-name"><?php echo $student_data ? htmlspecialchars($student_data['name']) : 'Student'; ?></div>
      <div class="student-regno"><?php echo $student_data ? htmlspecialchars($student_data['regno']) : ''; ?></div>
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
          <a href="student_register.php" class="nav-link active">
            <span class="material-symbols-outlined">add_circle</span>
            Register Event
          </a>
        </li>
        <li class="nav-item">
          <a href="student_participations.php" class="nav-link">
            <span class="material-symbols-outlined">event_note</span>
            My Participations
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
    <main class="registration-main">
  <form action="" method="POST" enctype="multipart/form-data" class="registration-form">
    <div class="registration-container">
      <h2 class="form-title">📋 Student Event Registration</h2>

      <!-- Personal Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon">👤</span>
          Personal Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="regno">Registration Number:<span class="required-asterisk">*</span></label>
            <input type="text" id="regno" name="regno" value="<?php echo htmlspecialchars($logged_in_regno); ?>"
                   placeholder="Auto-filled from your profile"
                   pattern="[0-9]{2}[A-Z]{2,4}[0-9]{3}" title="Format: 23CS001"
                   maxlength="10" readonly required />
            <div class="form-field-helper">This is auto-filled from your student profile</div>
          </div>
          <div class="item">
            <label for="year">Academic Year:<span class="required-asterisk">*</span></label>
            <select id="year" name="year" required>
              <option value="" disabled selected>Select Academic Year</option>
              <?php
                  // Generate last 10 academic years starting from current year
                  $current_year = date('Y');
                  for ($i = 0; $i < 10; $i++) {
                      $start_year    = $current_year - $i;
                      $end_year      = substr($start_year + 1, -2); // Get last 2 digits
                      $academic_year = $start_year . '-' . $end_year;
                      echo "<option value='$academic_year'>$academic_year</option>";
                  }
              ?>
            </select>
          </div>
          <div class="item">
            <label for="department">Department:<span class="required-asterisk">*</span></label>
            <select id="department" name="department" required>
              <option value="" disabled selected>Select Your Department</option>
              <option value="CSE">Computer Science and Engineering (CSE)</option>
              <option value="IT">Information Technology (IT)</option>
              <option value="ECE">Electronics and Communication Engineering (ECE)</option>
              <option value="EEE">Electrical and Electronics Engineering (EEE)</option>
              <option value="MECH">Mechanical Engineering (MECH)</option>
              <option value="CIVIL">Civil Engineering (CIVIL)</option>
              <option value="BME">Biomedical Engineering (BME)</option>
            </select>
          </div>
          <div class="item">
            <label for="semester">Semester:<span class="required-asterisk">*</span></label>
            <select id="semester" name="semester" required>
              <option value="" disabled selected>Select Semester</option>
              <option value="Odd">Odd Semester</option>
              <option value="Even">Even Semester</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Location Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon">🗺️</span>
          Location Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="state">State:<span class="required-asterisk">*</span></label>
            <select id="state" name="state" required>
              <option value="" disabled selected>Select State</option>
              <option value="Tamil Nadu">Tamil Nadu</option>
              <option value="Kerala">Kerala</option>
              <option value="Karnataka">Karnataka</option>
              <option value="Andhra Pradesh">Andhra Pradesh</option>
              <option value="Telangana">Telangana</option>
              <option value="Maharashtra">Maharashtra</option>
              <option value="Goa">Goa</option>
            </select>
          </div>
          <div class="item">
            <label for="district">District:<span class="required-asterisk">*</span></label>
            <select id="district" name="district" required disabled>
              <option value="" disabled selected>Select District</option>
            </select>
            <div class="form-field-helper">Please select a state first</div>
          </div>
        </div>
      </div>

      <!-- Event Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon">🎯</span>
          Event Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="eventType">Event Type:<span class="required-asterisk">*</span></label>
            <select id="eventType" name="eventType" required>
              <option value="" disabled selected>Select The Event</option>
              <option value="Workshop">Workshop</option>
              <option value="Symposium">Symposium</option>
              <option value="Conference">Conference</option>
              <option value="Webinar">Webinar</option>
              <option value="Competition">Competition</option>
              <option value="Seminar">Seminar</option>
              <option value="Hackathon">Hackathon</option>
              <option value="Training">Training</option>
              <option value="Certification">Certification</option>
              <option value="Cultural Event">Cultural Event</option>
              <option value="Sports Event">Sports Event</option>
              <option value="Technical Event">Technical Event</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="item">
            <label for="eventName">Event Name:<span class="required-asterisk">*</span></label>
            <input type="text" id="eventName" name="eventName"
                   placeholder="Enter the Event Name"
                   maxlength="100" required />
            <div class="character-count">
              <span id="eventNameCount">0</span>/100 characters
            </div>
          </div>
          <div class="item">
            <label for="attendedDate">Attended Date:<span class="required-asterisk">*</span></label>
            <input type="date" id="attendedDate" name="attendedDate" required />
          </div>
          <div class="item">
            <label for="organisation">Organisation By:<span class="required-asterisk">*</span></label>
            <input type="text" id="organisation" name="organisation"
                   placeholder="Enter the Organisation Name"
                   maxlength="80" required />
            <div class="character-count">
              <span id="organisationCount">0</span>/80 characters
            </div>
          </div>
        </div>
      </div>

      <!-- Achievement Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon">🏆</span>
          Achievement Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="prize">Prize:</label>
            <select id="prize" name="prize">
              <option value="" disabled selected>Select The Prize</option>
              <option value="First Prize">🥇 First Prize</option>
              <option value="Second Prize">🥈 Second Prize</option>
              <option value="Third Prize">🥉 Third Prize</option>
              <option value="Participation">🎗️ Participation</option>
            </select>
          </div>
          <div class="item">
            <label for="amount">Prize Amount (Optional):</label>
            <input type="number" id="amount" name="amount"
                   placeholder="Enter the Prize Amount"
                   min="0" step="0.01" />
            <div class="form-field-helper">Enter amount in rupees (₹)</div>
          </div>
        </div>
      </div>

      <!-- Document Upload Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon">📎</span>
          Document Upload
        </div>
        <div class="parent">
          <div class="item">
            <label for="poster">Upload Event Poster:<span class="required-asterisk">*</span></label>
            <div class="file-upload">
              <input type="file" id="poster" name="poster" accept=".pdf" required />
              <label for="poster" class="file-upload-label">
                <span class="file-upload-icon">📄</span>
                <span>Choose Event Poster (PDF Only)</span>
              </label>
            </div>
            <div class="file-size-info">Allowed file types: PDF Only (Max size: 5MB)</div>
            <div class="file-info" id="posterInfo"></div>
          </div>
          <div class="item">
            <label for="certificates">Upload Certificates:<span class="required-asterisk">*</span></label>
            <div class="file-upload">
              <input type="file" id="certificates" name="certificates" accept=".pdf" required />
              <label for="certificates" class="file-upload-label">
                <span class="file-upload-icon">🏅</span>
                <span>Choose Certificate (PDF Only)</span>
              </label>
            </div>
            <div class="file-size-info">Allowed file types: PDF Only (Max size: 5MB)</div>
            <div class="file-info" id="certificatesInfo"></div>
          </div>
        </div>
      </div>

      <button type="submit" class="register-btn">
        🚀 Register for Event
      </button>
    </div>
  </form>
    </main>
  </div>

  <script>
    // Mobile sidebar toggle function
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

    // Character count functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Event name character count
        const eventNameInput = document.getElementById('eventName');
        const eventNameCount = document.getElementById('eventNameCount');

        if (eventNameInput && eventNameCount) {
            eventNameInput.addEventListener('input', function() {
                eventNameCount.textContent = this.value.length;
            });
        }

        // Organisation character count
        const organisationInput = document.getElementById('organisation');
        const organisationCount = document.getElementById('organisationCount');

        if (organisationInput && organisationCount) {
            organisationInput.addEventListener('input', function() {
                organisationCount.textContent = this.value.length;
            });
        }

        // File upload handling
        const posterInput = document.getElementById('poster');
        const posterInfo = document.getElementById('posterInfo');

        if (posterInput && posterInfo) {
            posterInput.addEventListener('change', function() {
                handleFileUpload(this, posterInfo, 'Event Poster');
            });
        }

        const certificatesInput = document.getElementById('certificates');
        const certificatesInfo = document.getElementById('certificatesInfo');

        if (certificatesInput && certificatesInfo) {
            certificatesInput.addEventListener('change', function() {
                handleFileUpload(this, certificatesInfo, 'Certificate');
            });
        }

        function handleFileUpload(input, infoElement, fileType) {
            const file = input.files[0];
            if (file) {
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Size in MB
                const fileName = file.name;

                if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    alert('File size exceeds 5MB limit. Please choose a smaller file.');
                    input.value = '';
                    infoElement.style.display = 'none';
                    return;
                }

                if (file.type !== 'application/pdf') {
                    alert('Only PDF files are allowed.');
                    input.value = '';
                    infoElement.style.display = 'none';
                    return;
                }

                infoElement.innerHTML = `
                    <span style="color: #155724; font-weight: 500;">✓ ${fileType} Selected</span><br>
                    <span style="color: #6c757d;">${fileName} (${fileSize} MB)</span>
                `;
                infoElement.style.display = 'block';
            } else {
                infoElement.style.display = 'none';
            }
        }

        // Form submission handling
        const form = document.querySelector('.registration-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = document.querySelector('.register-btn');
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = 'Processing...';
                submitBtn.disabled = true;
            });
        }

        // State-District mapping
        const stateDistricts = {
            'Tamil Nadu': [
                'Chennai', 'Coimbatore', 'Madurai', 'Tiruchirappalli', 'Salem', 'Tirunelveli',
                'Erode', 'Vellore', 'Thoothukudi', 'Dindigul', 'Thanjavur', 'Ranipet',
                'Sivaganga', 'Karur', 'Ramanathapuram', 'Virudhunagar', 'Cuddalorem',
                'Kanchipuram', 'Villupuram', 'Nagapattinam', 'Dharmapuri', 'Krishnagiri',
                'Ariyalur', 'Namakkal', 'Perambalur', 'Nilgiris', 'Tiruvarur', 'Thiruvallur',
                'Tirupattur', 'Chengalpattu', 'Tenkasi', 'Tiruppur', 'Mayiladuthurai', 'Kallakurichi'
            ],
            'Kerala': [
                'Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam',
                'Idukki', 'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram', 'Kozhikode',
                'Wayanad', 'Kannur', 'Kasaragod'
            ],
            'Karnataka': [
                'Bagalkot', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru Urban',
                'Bidar', 'Chamarajanagar', 'Chikballapur', 'Chikkamagaluru', 'Chitradurga',
                'Dakshina Kannada', 'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri',
                'Kalaburagi', 'Kodagu', 'Kolar', 'Koppal', 'Mandya', 'Mysuru', 'Raichur',
                'Ramanagara', 'Shivamogga', 'Tumakuru', 'Udupi', 'Uttara Kannada', 'Vijayapura', 'Yadgir'
            ],
            'Andhra Pradesh': [
                'Anantapur', 'Chittoor', 'East Godavari', 'Guntur', 'Kadapa', 'Krishna',
                'Kurnool', 'Nellore', 'Prakasam', 'Srikakulam', 'Visakhapatnam',
                'Vizianagaram', 'West Godavari'
            ],
            'Telangana': [
                'Adilabad', 'Bhadradri Kothagudem', 'Hyderabad', 'Jagtial', 'Jangaon',
                'Jayashankar', 'Jogulamba', 'Kamareddy', 'Karimnagar', 'Khammam',
                'Komaram Bheem', 'Mahabubabad', 'Mahbubnagar', 'Mancherial', 'Medak',
                'Medchal', 'Mulugu', 'Nagarkurnool', 'Nalgonda', 'Narayanpet',
                'Nirmal', 'Nizamabad', 'Peddapalli', 'Rajanna Sircilla', 'Ranga Reddy',
                'Sangareddy', 'Siddipet', 'Suryapet', 'Vikarabad', 'Wanaparthy', 'Warangal Rural', 'Warangal Urban', 'Yadadri Bhuvanagiri'
            ],
            'Maharashtra': [
                'Mumbai City', 'Mumbai Suburban', 'Thane', 'Pune', 'Nashik', 'Nagpur',
                'Aurangabad', 'Solapur', 'Amravati', 'Nanded', 'Kolhapur', 'Akola',
                'Latur', 'Ahmednagar', 'Chandrapur', 'Parbhani', 'Jalgaon', 'Buldhana',
                'Ratnagiri', 'Gondia', 'Yavatmal', 'Nandurbar', 'Wardha', 'Beed',
                'Washim', 'Gadchiroli', 'Hingoli', 'Osmanabad', 'Raigad', 'Sangli',
                'Sindhudurg', 'Satara', 'Jalna', 'Dhule', 'Bhandara'
            ],
            'Goa': [
                'North Goa', 'South Goa'
            ]
        };

        const stateSelect = document.getElementById('state');
        const districtSelect = document.getElementById('district');

        if (stateSelect && districtSelect) {
            stateSelect.addEventListener('change', function() {
                const selectedState = this.value;

                // Clear existing district options
                districtSelect.innerHTML = '<option value="" disabled selected>Select District</option>';

                if (selectedState && stateDistricts[selectedState]) {
                    // Populate districts for selected state
                    stateDistricts[selectedState].forEach(function(district) {
                        const option = document.createElement('option');
                        option.value = district;
                        option.textContent = district;
                        districtSelect.appendChild(option);
                    });

                    // Enable district dropdown
                    districtSelect.disabled = false;
                } else {
                    // Disable district dropdown if no state selected
                    districtSelect.disabled = true;
                }
            });
        }
    });
</script>
</body>
</html>






</script>
</body>
</html>
