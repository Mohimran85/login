<?php
    session_start();

    // Include file compression utility
    require_once '../includes/FileCompressor.php';

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
    }

    // Get logged-in user's registration number and student data
    $logged_in_regno = '';
    $student_data    = null;
    if (isset($_SESSION['username'])) {
    require_once __DIR__ . '/../includes/db_config.php';
    $conn_user = get_db_connection();

    $username  = $_SESSION['username'];
    $user_sql  = "SELECT name, regno, semester, department FROM student_register WHERE username=?";
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

    // Auto-fill functionality: Get event details from URL parameters
    $auto_event_name     = isset($_GET['event']) ? trim($_GET['event']) : '';
    $auto_event_type     = isset($_GET['type']) ? trim($_GET['type']) : '';
    $auto_organisation   = isset($_GET['org']) ? trim($_GET['org']) : '';
    $auto_start_date     = isset($_GET['date']) ? trim($_GET['date']) : (isset($_GET['start_date']) ? trim($_GET['start_date']) : '');
    $auto_end_date       = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
    $auto_event_state    = isset($_GET['state']) ? trim($_GET['state']) : '';
    $auto_event_district = isset($_GET['district']) ? trim($_GET['district']) : '';
    $auto_department     = isset($_GET['dept']) ? trim($_GET['dept']) : '';
    $auto_days           = isset($_GET['days']) ? trim($_GET['days']) : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! function_exists('get_db_connection')) {
        require_once __DIR__ . '/../includes/db_config.php';
    }
    $conn = get_db_connection();

    // Sanitize inputs
    $regno        = isset($_POST['regno']) ? trim($_POST['regno']) : '';
    $current_year = isset($_POST['year']) ? trim($_POST['year']) : '';
    $semester     = isset($_POST['semester']) ? trim($_POST['semester']) : '';
    // Use department code for database storage, fallback to department field if code not available
    $department = isset($_POST['department_code']) && ! empty($_POST['department_code'])
        ? trim($_POST['department_code'])
        : (isset($_POST['department']) ? trim($_POST['department']) : '');
    $state      = isset($_POST['state']) ? trim($_POST['state']) : '';
    $district   = isset($_POST['district']) ? trim($_POST['district']) : '';
    $event_type = isset($_POST['eventType']) ? trim($_POST['eventType']) : '';
    $event_name = isset($_POST['eventName']) ? trim($_POST['eventName']) : '';
    $start_date = isset($_POST['startDate']) ? $_POST['startDate'] : '';
    $end_date   = isset($_POST['endDate']) ? $_POST['endDate'] : '';

    // Calculate number of days
    $no_of_days = 0;
    if (! empty($start_date) && ! empty($end_date)) {
        $start      = new DateTime($start_date);
        $end        = new DateTime($end_date);
        $interval   = $start->diff($end);
        $no_of_days = $interval->days + 1; // +1 to include both start and end dates
    }

    $organisation = isset($_POST['organisation']) ? trim($_POST['organisation']) : '';
    $prize        = isset($_POST['prize']) ? trim($_POST['prize']) : '';
    $prize_amount = isset($_POST['amount']) ? trim($_POST['amount']) : '';

    // Note: OD letter is optional for data collection purposes only

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
    $event_photo_path  = null;

    // Handle uploaded event poster with compression
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        if (valid_pdf($_FILES["poster"]["tmp_name"])) {
            $file_ext      = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
            $base_filename = $target_dir . uniqid('poster_') . '_' . time();

            // Compress and save
            $compression_result = FileCompressor::compressUploadedFile(
                $_FILES['poster']['tmp_name'],
                $base_filename,
                $file_ext,
                85
            );

            if ($compression_result['success']) {
                $event_poster_path = $compression_result['path'];
            } else {
                echo "<p style='color:red;'> Failed to upload event poster.</p>";
                $conn->close();exit;
            }
        } else {
            echo "<p style='color:red;'> Event poster must be a PDF file.</p>";
            $conn->close();exit;
        }
    }
    // Handle uploaded certificate with compression
    if (isset($_FILES['certificates']) && $_FILES['certificates']['error'] === UPLOAD_ERR_OK) {
        if (valid_pdf($_FILES["certificates"]["tmp_name"])) {
            $file_ext      = pathinfo($_FILES['certificates']['name'], PATHINFO_EXTENSION);
            $base_filename = $target_dir . uniqid('cert_') . '_' . time();

            // Compress and save
            $compression_result = FileCompressor::compressUploadedFile(
                $_FILES['certificates']['tmp_name'],
                $base_filename,
                $file_ext,
                85
            );

            if ($compression_result['success']) {
                $certificate_path = $compression_result['path'];
            } else {
                echo "<p style='color:red;'> Failed to upload certificate.</p>";
                $conn->close();exit;
            }
        } else {
            echo "<p style='color:red;'> Certificate must be a PDF file.</p>";
            $conn->close();exit;
        }
    }

    // Handle uploaded event photo with compression (optional)
    if (isset($_FILES['event_photo']) && $_FILES['event_photo']['error'] === UPLOAD_ERR_OK) {
        // Validate image file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES["event_photo"]["tmp_name"]);
        finfo_close($finfo);

        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (in_array($mime, $allowed_types)) {
            $file_ext      = pathinfo($_FILES['event_photo']['name'], PATHINFO_EXTENSION);
            $base_filename = $target_dir . uniqid('photo_') . '_' . time();

            // Compress and save (higher quality for photos: 90%)
            $compression_result = FileCompressor::compressUploadedFile(
                $_FILES['event_photo']['tmp_name'],
                $base_filename,
                $file_ext,
                90
            );

            if ($compression_result['success']) {
                $event_photo_path = $compression_result['path'];
            } else {
                echo "<p style='color:red;'> Failed to upload event photo.</p>";
                $conn->close();exit;
            }
        } else {
            echo "<p style='color:red;'> Event photo must be an image file (JPG, PNG, or GIF).</p>";
            $conn->close();exit;
        }
    }

    // Duplicate registration check
    $check_sql  = "SELECT id, event_type, start_date FROM student_event_register WHERE regno = ? AND event_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $regno, $event_name);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        $conn->close();

        // Show JavaScript alert popup
        echo "<!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: 'Poppins', sans-serif;
                        background: #f5f5f5;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        margin: 0;
                    }
                    .alert-box {
                        background: white;
                        padding: 30px;
                        border-radius: 15px;
                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                        text-align: center;
                        max-width: 400px;
                        animation: slideIn 0.3s ease;
                    }
                    @keyframes slideIn {
                        from { opacity: 0; transform: translateY(-20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    .alert-icon {
                        font-size: 48px;
                        margin-bottom: 15px;
                    }
                    .alert-title {
                        color: #ff9800;
                        font-size: 24px;
                        font-weight: 600;
                        margin-bottom: 10px;
                    }
                    .alert-message {
                        color: #6c757d;
                        font-size: 16px;
                        margin-bottom: 25px;
                        line-height: 1.5;
                    }
                    .alert-btn {
                        background: #0c3878;
                        color: white;
                        padding: 12px 30px;
                        border: none;
                        border-radius: 8px;
                        font-size: 16px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }
                    .alert-btn:hover {
                        background: #094067;
                        transform: translateY(-2px);
                        box-shadow: 0 4px 12px rgba(12, 56, 120, 0.3);
                    }
                </style>
            </head>
            <body>
                <div class='alert-box'>
                    <div class='alert-icon'></div>
                    <div class='alert-title'>Already Registered!</div>
                    <div class='alert-message'>
                        You have already registered for the event:<br>
                        <strong>" . htmlspecialchars($event_name) . "</strong>
                    </div>
                    <button class='alert-btn' onclick='window.history.back()'>Go Back</button>
                </div>
                <script>
                    // Also show browser alert
                    setTimeout(function() {
                        alert('You have already registered for this event: " . addslashes(htmlspecialchars($event_name)) . "');
                    }, 100);
                </script>
            </body>
            </html>";
        exit;
    }
    $check_stmt->close();

    // Insert registration
    $sql = "INSERT INTO student_event_register
        (regno, current_year, semester, state, district, department, event_type, event_name, start_date, end_date, no_of_days, organisation, prize, prize_amount, event_poster, certificates, event_photo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }

    $stmt->bind_param(
        "ssssssssssissssss",
        $regno,
        $current_year,
        $semester,
        $state,
        $district,
        $department,
        $event_type,
        $event_name,
        $start_date,
        $end_date,
        $no_of_days,
        $organisation,
        $prize,
        $prize_amount,
        $event_poster_path,
        $certificate_path,
        $event_photo_path
    );

    if ($stmt->execute()) {
        // Invalidate dashboard cache so newly registered event appears immediately
        $dashboard_cache_key = 'EMS_CACHE_dashboard_' . $regno;
        unset($_SESSION[$dashboard_cache_key]);
        $stmt->close();
        $conn->close();
        header("Location: student_register.php?success=1");
        exit;
    } else {
        echo "<p style='color:red;'>Error: " . htmlspecialchars($stmt->error) . "</p>";
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
  <meta name="theme-color" content="#1a408c">
  <title>Student Event Registration</title>
  <link rel="stylesheet" href="student_dashboard.css"/>
  <!-- Web App Manifest for Push Notifications -->
  <link rel="manifest" href="../manifest.json">

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
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        min-height: 100vh !important;
        max-height: 100vh !important;
        transform: translateX(-100%) !important;
        z-index: 10000 !important;
        background: white !important;
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
        transition: transform 0.3s ease !important;
        overflow-y: auto !important;
      }

      .sidebar.active {
        transform: translateX(0) !important;
        z-index: 10001 !important;
      }

      body.sidebar-open {
        overflow: hidden;
        position: fixed;
        width: 100%;
        height: 100%;
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

    /* Notification Bell Styles */
    .notification-bell-container {
      position: absolute;
      top: 12px;
      right: 20px;
      display: flex;
      align-items: center;
      z-index: 1001;
    }

    .notification-bell {
      position: relative;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: white;
      border: 2px solid #1a408c;
      border-radius: 50%;
      width: 45px;
      height: 45px;
      transition: all 0.3s ease;
      margin: 0;
    }

    .notification-bell:hover {
      background: #f0f4f8;
      transform: scale(1.05);
    }

    .notification-bell .material-symbols-outlined {
      font-size: 24px;
      color: #1a408c;
    }

    .notification-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #dc3545;
      color: white;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      min-width: 24px;
    }

    .notification-badge.hidden {
      display: none;
    }

    /* Notification Dropdown/Modal */
    .notification-dropdown {
      position: fixed;
      top: 70px;
      right: 20px;
      background: white;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      border: 1px solid #eee;
      width: 350px;
      max-height: 500px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
    }

    .notification-dropdown.show {
      display: block;
    }

    .notification-header {
      padding: 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .notification-header h3 {
      margin: 0;
      font-size: 18px;
      color: #1a408c;
    }

    .notification-header-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .notification-header .mark-all,
    .notification-header .clear-all {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 12px;
      text-decoration: underline;
      padding: 0;
      transition: all 0.3s ease;
    }

    .notification-header .mark-all {
      color: #1a408c;
    }

    .notification-header .mark-all:hover {
      color: #15306b;
    }

    .notification-header .clear-all {
      color: #dc3545;
    }

    .notification-header .clear-all:hover {
      color: #a71d2a;
    }

    .notification-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .notification-item {
      padding: 15px 20px;
      border-bottom: 1px solid #f0f0f0;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      gap: 12px;
    }

    .notification-item:hover {
      background: #f9f9f9;
    }

    .notification-item.unread {
      background: #f0f4f8;
    }

    .notification-item-icon {
      flex-shrink: 0;
      width: 40px;
      height: 40px;
      background: #1a408c;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
    }

    .notification-item-content {
      flex: 1;
      min-width: 0;
    }

    .notification-item-content h4 {
      margin: 0 0 5px 0;
      font-size: 14px;
      font-weight: 600;
      color: #2c3e50;
    }

    .notification-item-content p {
      margin: 0 0 5px 0;
      font-size: 13px;
      color: #666;
      line-height: 1.4;
    }

    .notification-item-time {
      font-size: 12px;
      color: #999;
    }

    .notification-empty {
      padding: 40px 20px;
      text-align: center;
      color: #999;
    }

    .notification-empty-icon {
      font-size: 48px;
      margin-bottom: 10px;
      display: block;
    }

    /* Overlay */
    .notification-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: none;
      z-index: 999;
    }

    .notification-overlay.show {
      display: block;
    }

    @media (max-width: 768px) {
      .notification-bell-container {
        position: absolute;
        top: 8px;
        right: 10px;
      }

      .notification-dropdown {
        position: fixed;
        top: auto;
        right: 10px;
        left: 10px;
        bottom: 80px;
        width: auto;
        max-height: 300px;
      }

      .notification-bell {
        width: 40px;
        height: 40px;
        margin: 0;
      }

      .notification-bell .material-symbols-outlined {
        font-size: 20px;
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
        src="sona_logo.jpg"
        alt="Sona College Logo"
        height="60px"
        width="200"
      />
    </div>
    <div class="notification-bell-container">
      <div class="notification-bell" id="notificationBell">
        <span class="material-symbols-outlined">notifications</span>
        <span class="notification-badge hidden" id="notificationBadge">0</span>
      </div>
      <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
          <h3>Notifications</h3>
          <div class="notification-header-actions">
            <button class="mark-all" onclick="markAllNotificationsAsRead()">Mark all as read</button>
            <button class="clear-all" onclick="clearAllNotifications()">Clear all</button>
          </div>
        </div>
        <ul class="notification-list" id="notificationList">
          <li class="notification-empty">
            <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
            <p>No notifications</p>
          </li>
        </ul>
      </div>
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
          <a href="od_request.php" class="nav-link">
            <span class="material-symbols-outlined">person_raised_hand</span>
            OD Request
          </a>
        </li>
        <li class="nav-item">
          <a href="internship_submission.php" class="nav-link">
            <span class="material-symbols-outlined">work</span>
            Internship Submission
          </a>
        </li>
        <li class="nav-item">
          <a href="hackathons.php" class="nav-link">
            <span class="material-symbols-outlined">emoji_events</span>
            Hackathons
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
      <h2 class="form-title">  Student Event Registration</h2>

      <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
        <div id="success-banner" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:14px 20px;margin-bottom:20px;font-weight:500;display:flex;align-items:center;gap:10px;">
          ✅ Registration Successful! Your event has been submitted.
        </div>
        <script>
          // Scroll to top so banner is visible
          window.scrollTo({ top: 0, behavior: 'smooth' });
          // Auto-hide after 5 seconds
          setTimeout(function() {
            var b = document.getElementById('success-banner');
            if (b) b.style.display = 'none';
          }, 5000);
        </script>
      <?php endif; ?>

      <!-- Personal Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon"></span>
          Personal Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="regno">Registration Number:<span class="required-asterisk">*</span></label>
            <input type="text" id="regno" name="regno" value="<?php echo htmlspecialchars($logged_in_regno); ?>"
                   placeholder="Auto-filled from your profile"
                   pattern="[0-9]{2}[A-Z]{2,4}[0-9]{3}" title="Format: 23CS001"
                   maxlength="10" readonly required />

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
            <input type="text" id="department" name="department"
                   value="<?php
                              // Use URL parameter if available, otherwise use profile data
                              $display_dept = '';
                              $dept_code    = '';

                              if (! empty($auto_department)) {
                                  $dept_code = $auto_department;
                              } elseif (isset($student_data['department']) && ! empty($student_data['department'])) {
                                  $dept_code = $student_data['department'];
                              }

                              if ($dept_code) {
                                  // Display full department name based on code
                                  $dept_names = [
                                      'CSE'   => 'Computer Science and Engineering (CSE)',
                                      'IT'    => 'Information Technology (IT)',
                                      'ECE'   => 'Electronics and Communication Engineering (ECE)',
                                      'EEE'   => 'Electrical and Electronics Engineering (EEE)',
                                      'MECH'  => 'Mechanical Engineering (MECH)',
                                      'CIVIL' => 'Civil Engineering (CIVIL)',
                                      'BME'   => 'Biomedical Engineering (BME)',
                                  ];
                                  $display_dept = $dept_names[$dept_code] ?? $dept_code;
                              }

                          echo htmlspecialchars($display_dept);
                          ?>"
                   placeholder="Auto-filled from your profile"
                   readonly required />

            <!-- Hidden input to maintain the department code for form submission -->
            <input type="hidden" name="department_code" value="<?php echo htmlspecialchars($dept_code ?? ''); ?>" />
          </div>
          <div class="item">
            <label for="semester">Semester:<span class="required-asterisk">*</span></label>
            <input type="text" id="semester" name="semester" value="<?php echo isset($student_data['semester']) ? htmlspecialchars($student_data['semester']) : ''; ?>" readonly required>
          </div>
        </div>
      </div>

      <!-- Location Information Section -->
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon"></span>
          Location Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="state">State:<span class="required-asterisk">*</span></label>
            <select id="state" name="state" required>
              <option value="" disabled                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo empty($auto_event_state) ? 'selected' : ''; ?>>Select State</option>
              <option value="Andhra Pradesh"                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Andhra Pradesh') ? 'selected' : ''; ?>>Andhra Pradesh</option>
              <option value="Arunachal Pradesh"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Arunachal Pradesh') ? 'selected' : ''; ?>>Arunachal Pradesh</option>
              <option value="Assam"                                                                                                                                                                                                                                                                                                                                                               <?php echo($auto_event_state == 'Assam') ? 'selected' : ''; ?>>Assam</option>
              <option value="Bihar"                                                                                                                                                                                                                                                                                                                                                               <?php echo($auto_event_state == 'Bihar') ? 'selected' : ''; ?>>Bihar</option>
              <option value="Chhattisgarh"                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($auto_event_state == 'Chhattisgarh') ? 'selected' : ''; ?>>Chhattisgarh</option>
              <option value="Goa"                                                                                                                                                                                                                                                                                                                                           <?php echo($auto_event_state == 'Goa') ? 'selected' : ''; ?>>Goa</option>
              <option value="Gujarat"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_state == 'Gujarat') ? 'selected' : ''; ?>>Gujarat</option>
              <option value="Haryana"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_state == 'Haryana') ? 'selected' : ''; ?>>Haryana</option>
              <option value="Himachal Pradesh"                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($auto_event_state == 'Himachal Pradesh') ? 'selected' : ''; ?>>Himachal Pradesh</option>
              <option value="Jharkhand"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Jharkhand') ? 'selected' : ''; ?>>Jharkhand</option>
              <option value="Karnataka"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Karnataka') ? 'selected' : ''; ?>>Karnataka</option>
              <option value="Kerala"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Kerala') ? 'selected' : ''; ?>>Kerala</option>
              <option value="Madhya Pradesh"                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Madhya Pradesh') ? 'selected' : ''; ?>>Madhya Pradesh</option>
              <option value="Maharashtra"                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($auto_event_state == 'Maharashtra') ? 'selected' : ''; ?>>Maharashtra</option>
              <option value="Manipur"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_state == 'Manipur') ? 'selected' : ''; ?>>Manipur</option>
              <option value="Meghalaya"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Meghalaya') ? 'selected' : ''; ?>>Meghalaya</option>
              <option value="Mizoram"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_state == 'Mizoram') ? 'selected' : ''; ?>>Mizoram</option>
              <option value="Nagaland"                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($auto_event_state == 'Nagaland') ? 'selected' : ''; ?>>Nagaland</option>
              <option value="Odisha"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Odisha') ? 'selected' : ''; ?>>Odisha</option>
              <option value="Punjab"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Punjab') ? 'selected' : ''; ?>>Punjab</option>
              <option value="Rajasthan"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Rajasthan') ? 'selected' : ''; ?>>Rajasthan</option>
              <option value="Sikkim"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_state == 'Sikkim') ? 'selected' : ''; ?>>Sikkim</option>
              <option value="Tamil Nadu"                                                                                                                                                                                                                                                                                                                                                                                                                 <?php echo($auto_event_state == 'Tamil Nadu') ? 'selected' : ''; ?>>Tamil Nadu</option>
              <option value="Telangana"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($auto_event_state == 'Telangana') ? 'selected' : ''; ?>>Telangana</option>
              <option value="Tripura"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_state == 'Tripura') ? 'selected' : ''; ?>>Tripura</option>
              <option value="Uttar Pradesh"                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($auto_event_state == 'Uttar Pradesh') ? 'selected' : ''; ?>>Uttar Pradesh</option>
              <option value="Uttarakhand"                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($auto_event_state == 'Uttarakhand') ? 'selected' : ''; ?>>Uttarakhand</option>
              <option value="West Bengal"                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($auto_event_state == 'West Bengal') ? 'selected' : ''; ?>>West Bengal</option>
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

      <!-- Event Information Section -->`
      <div class="form-section">
        <div class="form-section-title">
          <span class="form-section-icon"></span>
          Event Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="eventType">Event Type:<span class="required-asterisk">*</span></label>
            <select id="eventType" name="eventType" required>
              <option value="" disabled                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo empty($auto_event_type) ? 'selected' : ''; ?>>Select The Event</option>
              <option value="Workshop"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($auto_event_type == 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
              <option value="Symposium"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo($auto_event_type == 'Symposium') ? 'selected' : ''; ?>>Symposium</option>
              <option value="Conference"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo($auto_event_type == 'Conference') ? 'selected' : ''; ?>>Conference</option>
              <option value="Webinar"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo($auto_event_type == 'Webinar') ? 'selected' : ''; ?>>Webinar</option>
              <option value="Competition  "                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo($auto_event_type == 'Competition') ? 'selected' : ''; ?>>Competition</option>
              <option value="Seminar"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo($auto_event_type == 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
              <option value="Hackathon"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo($auto_event_type == 'Hackathon') ? 'selected' : ''; ?>>Hackathon</option>
              <option value="Training"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($auto_event_type == 'Training') ? 'selected' : ''; ?>>Training</option>
              <option value="Cultural Event"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($auto_event_type == 'Cultural Event') ? 'selected' : ''; ?>>Cultural Event</option>
              <option value="Sports Event"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($auto_event_type == 'Sports Event') ? 'selected' : ''; ?>>Sports Event</option>
              <option value="Technical Event"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  <?php echo($auto_event_type == 'Technical Event') ? 'selected' : ''; ?>>Technical Event</option>
              <option value="Other"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo($auto_event_type == 'Other') ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
          <div class="item">
            <label for="eventName">Event Name:<span class="required-asterisk">*</span></label>
            <input type="text" id="eventName" name="eventName"
                   placeholder="Enter the Event Name"
                   value="<?php echo htmlspecialchars($auto_event_name); ?>"
                   maxlength="100" required />
            <div class="character-count">
              <span id="eventNameCount">0</span>/100 characters
            </div>
          </div>
          <div class="item">
            <label for="eventDate">Start Date:<span class="required-asterisk">*</span></label>
            <input type="date" id="eventDate" name="startDate"
                   value="<?php echo htmlspecialchars($auto_start_date); ?>" required />
          </div>
          <div class="item">
            <label for="eventEndDate">End Date:<span class="required-asterisk">*</span></label>
            <input type="date" id="eventEndDate" name="endDate"
                   value="<?php echo htmlspecialchars($auto_end_date); ?>" required />
            <div class="form-field-helper">End date must be same or after start date</div>
          </div>
          <div class="item">
            <label for="noOfDays">Number of Days:</label>
            <input type="number" id="noOfDays" name="noOfDays"
                   value="<?php echo htmlspecialchars($auto_days); ?>"
                   min="1" placeholder="Auto-calculated" readonly />
            <div class="form-field-helper">Automatically calculated from start and end dates</div>
          </div>
          <div class="item">
            <label for="organisation">Organisation By:<span class="required-asterisk">*</span></label>
            <input type="text" id="organisation" name="organisation"
                   placeholder="Enter the Organisation Name"
                   value=""
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
          <span class="form-section-icon"></span>
          Achievement Information
        </div>
        <div class="parent">
          <div class="item">
            <label for="prize">Prize:</label>
            <select id="prize" name="prize">
              <option value="" disabled selected>Select The Prize</option>
              <option value="first">First Prize</option>
              <option value="second">Second Prize</option>
              <option value="third">Third Prize</option>
              <option value="Participation">Participation</option>
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
          <span class="form-section-icon"></span>
          Document Upload
        </div>
        <div class="parent">
          <div class="item">
            <label for="poster">Upload Event Poster:<span class="required-asterisk">*</span></label>
            <div class="file-upload">
              <input type="file" id="poster" name="poster" accept=".pdf" required />
              <label for="poster" class="file-upload-label">
                <span class="file-upload-icon"></span>
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
                <span class="file-upload-icon"></span>
                <span>Choose Certificate (PDF Only)</span>
              </label>
            </div>
            <div class="file-size-info">Allowed file types: PDF Only (Max size: 5MB)</div>
            <div class="file-info" id="certificatesInfo"></div>
          </div>
          <div class="item">
            <label for="event_photo">Upload Event Photo (Optional):</label>
            <div class="file-upload">
              <input type="file" id="event_photo" name="event_photo" accept="image/jpeg,image/jpg,image/png,image/gif" />
              <label for="event_photo" class="file-upload-label">
                <span class="file-upload-icon"></span>
                <span>Choose Event Photo (JPG, PNG, GIF)</span>
              </label>
            </div>
            <div class="file-size-info">Allowed file types: JPG, PNG, GIF (Max size: 5MB)</div>
            <div class="file-info" id="eventPhotoInfo"></div>
          </div>
        </div>
      </div>

      <button type="submit" class="register-btn">
        Register for Event
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
            // Initialize counter for pre-filled values
            eventNameCount.textContent = eventNameInput.value.length;

            eventNameInput.addEventListener('input', function() {
                eventNameCount.textContent = this.value.length;
            });
        }

        // Organisation character count
        const organisationInput = document.getElementById('organisation');
        const organisationCount = document.getElementById('organisationCount');

        if (organisationInput && organisationCount) {
            // Initialize counter for pre-filled values
            organisationCount.textContent = organisationInput.value.length;

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

        // Event photo upload handling
        const eventPhotoInput = document.getElementById('event_photo');
        const eventPhotoInfo = document.getElementById('eventPhotoInfo');

        if (eventPhotoInput && eventPhotoInfo) {
            eventPhotoInput.addEventListener('change', function() {
                handleImageUpload(this, eventPhotoInfo, 'Event Photo');
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

        function handleImageUpload(input, infoElement, fileType) {
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

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only image files (JPG, PNG, GIF) are allowed.');
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
            'Andhra Pradesh': [
                'Anantapur', 'Chittoor', 'East Godavari', 'Guntur', 'Kadapa', 'Krishna',
                'Kurnool', 'Nellore', 'Prakasam', 'Srikakulam', 'Visakhapatnam',
                'Vizianagaram', 'West Godavari'
            ],
            'Arunachal Pradesh': [
                'Anjaw', 'Changlang', 'Dibang Valley', 'East Kameng', 'East Siang',
                'Kra Daadi', 'Kurung Kumey', 'Lohit', 'Longding', 'Lower Dibang Valley',
                'Lower Siang', 'Lower Subansiri', 'Papum Pare', 'Siang', 'Upper Dibang Valley',
                'Upper Siang', 'Upper Subansiri', 'West Kameng', 'West Siang'
            ],
            'Assam': [
                'Baksa', 'Barpeta', 'Biswanath', 'Bongaigaon', 'Cachar', 'Charaideo',
                'Chirang', 'Darrang', 'Dhemaji', 'Dima Hasao', 'Dibrugarh', 'Goalpara',
                'Golaghat', 'Hailakandi', 'Hojai', 'Jorhat', 'Kamrup', 'Kamrup Metropolitan',
                'Karbi Anglong', 'Karimganj', 'Kokrajhar', 'Lakhimpur', 'Majuli', 'Morigaon',
                'Nagaon', 'Nalbari', 'Sonitpur', 'South Salmara-Mankachar', 'Sibsagar', 'Sualkuchi'
            ],
            'Bihar': [
                'Araria', 'Arwal', 'Atapur', 'Aurangabad', 'Banka', 'Begusarai', 'Bhagalpur',
                'Bhojpur', 'Buxar', 'Chhapra', 'Darbhanga', 'East Champaran', 'Gaya', 'Gopalganj',
                'Jehanabad', 'Jha Jha', 'Jhajha', 'Katihar', 'Khagaria', 'Kishanganj', 'Lakhisarai',
                'Madhepura', 'Madhubani', 'Munger', 'Muzaffarpur', 'Nalanda', 'Nawada', 'Patna',
                'Purnia', 'Rohtas', 'Saharsa', 'Samastipur', 'Sambhal', 'Saran', 'Sheikhpura',
                'Sheohar', 'Sheopur', 'Sipaul', 'Sitamarhi', 'Siwan', 'Supaul', 'Vaishali', 'West Champaran'
            ],
            'Chhattisgarh': [
                'Balod', 'Baloda Bazar', 'Balrampur', 'Bastar', 'Bemetara', 'Bijapur', 'Bilaspur',
                'Dantewada', 'Dhamtari', 'Durg', 'Gariaband', 'Gondia', 'Jajpur', 'Jashpur',
                'Kabirdham', 'Kanker', 'Konta', 'Korba', 'Koriya', 'Mahasamund', 'Manpur',
                'Mungeli', 'Narayanpur', 'Pendra', 'Raigarh', 'Raipur', 'Rajnandgaon', 'Sukma'
            ],
            'Goa': [
                'North Goa', 'South Goa'
            ],
            'Gujarat': [
                'Ahmedabad', 'Amreli', 'Anand', 'Aravalli', 'Banaskantha', 'Bharuch', 'Bhavnagar',
                'Botad', 'Chhota Udaipur', 'Dahod', 'Dang', 'Dhari', 'Devbhoomi Dwarka', 'Gandhinagar',
                'Gir Somnath', 'Godhra', 'Halol', 'Jamnagar', 'Junagadh', 'Kalol', 'Kapadwanj',
                'Khambhat', 'Khimaj', 'Khoja', 'Kodinar', 'Kunkavati', 'Lunawada', 'Mahisagar',
                'Mahuva', 'Mandvi', 'Manpur', 'Mehmadabad', 'Mehsana', 'Mithapur', 'Modasa', 'Morbi',
                'Morvi', 'Nadiad', 'Navsari', 'Padra', 'Palitana', 'Panchmahal', 'Pardi', 'Patan',
                'Petlad', 'Porbandar', 'Radhanpur', 'Rajkot', 'Rajula', 'Rampur Vav', 'Ranavav', 'Rapar',
                'Rasulia', 'Raysan', 'Rizwana', 'Ropdi', 'Rupnagar', 'Salaya', 'Salod', 'Salumber',
                'Sambhal', 'Samkhiali', 'Sanand', 'Sanand City', 'Sangli', 'Sankaran', 'Sankheda',
                'Santalpur', 'Sants', 'Satadal', 'Satara', 'Savarkundla', 'Sayla', 'Selod', 'Sendhwa',
                'Seoni', 'Seoni Malwa', 'Setali', 'Shekhpura', 'Shergarh', 'Sherpura', 'Shihor',
                'Shilod', 'Shivrajpur', 'Siddhpur', 'Sikandrabad', 'Sikanpura', 'Sinder', 'Singhapur',
                'Singrol', 'Siren', 'Sisodiya', 'Sitamau', 'Sitapur', 'Sivaganj', 'Siyana', 'Siyon',
                'Skandi', 'Sohna', 'Sokhada', 'Sola', 'Soladi', 'Somkhapur', 'Somnath', 'Sompur',
                'Sondha', 'Soner', 'Sonkatch', 'Sonpur', 'Sonwad', 'Sora', 'Soraba', 'Sorali',
                'Sorat', 'Sorathia', 'Sorbi', 'Sorbhoy', 'Sordu', 'Soren', 'Sorgarh', 'Sorget',
                'Sorghana', 'Sorhar', 'Sori', 'Sorigin', 'Soriya', 'Soriyali', 'Sorkha', 'Sorlai',
                'Sorma', 'Sormudi', 'Sornad', 'Sornal', 'Sornari', 'Sorni', 'Sorno', 'Soroda',
                'Soron', 'Sorondi', 'Sorong', 'Soroni', 'Soronidara', 'Soronka', 'Soronkhanpur',
                'Sorono', 'Sorota', 'Sorotan', 'Soroth', 'Soroti', 'Soroz', 'Sorpa', 'Sorpadhi',
                'Sura', 'Surajpur', 'Surat', 'Surendranagar', 'Surguja', 'Surigaon', 'Surigaonda',
                'Surkha', 'Surla', 'Surlana', 'Surlino', 'Surlod', 'Surloi', 'Surma', 'Surmadi',
                'Surmali', 'Surman', 'Surmar', 'Surmari', 'Surmel', 'Surmidha', 'Surmis', 'Surmoha',
                'Surmoli', 'Surmota', 'Surmoti', 'Surmudi', 'Surmul', 'Surmuli', 'Surmuni', 'Surmur',
                'Surmurda', 'Surmuro', 'Surmuse', 'Surmut', 'Surmuti', 'Surmva', 'Surmvad', 'Surmvel'
            ],
            'Haryana': [
                'Ambala', 'Bhiwani', 'Charkhi Dadri', 'Faridabad', 'Fatehabad', 'Gurgaon', 'Hisar',
                'Jhajjar', 'Jind', 'Kaithal', 'Karnal', 'Kurukshetra', 'Mahendragarh', 'Mewat',
                'Palwal', 'Panchkula', 'Panipat', 'Rewari', 'Rohtak', 'Sirsa', 'Sonipat', 'Yamunanagar'
            ],
            'Himachal Pradesh': [
                'Bilaspur', 'Chamba', 'Hamirpur', 'Kangra', 'Kinnaur', 'Kullu', 'Lahaul Spiti',
                'Mandi', 'Shimla', 'Sirmaur', 'Solan', 'Una'
            ],
            'Jharkhand': [
                'Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa',
                'Giridih', 'Godda', 'Gumla', 'Hazaribag', 'Jamtara', 'Jamui', 'Jharia', 'Khunti',
                'Koderma', 'Latehar', 'Lohardaga', 'Madhupur', 'Munger', 'Pakur', 'Palamu',
                'Purbi Singhbhum', 'Ramgarh', 'Ranchi', 'Sahibganj', 'Seraikela Kharsawan',
                'Simdega', 'West Singhbhum'
            ],
            'Karnataka': [
                'Bagalkot', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru Urban', 'Bidar',
                'Chamarajanagar', 'Chikballapur', 'Chikkamagaluru', 'Chitradurga', 'Dakshina Kannada',
                'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri', 'Kalaburagi', 'Kodagu',
                'Kolar', 'Koppal', 'Mandya', 'Mysuru', 'Raichur', 'Ramanagara', 'Shivamogga',
                'Tumakuru', 'Udupi', 'Uttara Kannada', 'Vijayapura', 'Yadgir'
            ],
            'Kerala': [
                'Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam', 'Idukki',
                'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram', 'Kozhikode', 'Wayanad', 'Kannur',
                'Kasaragod'
            ],
            'Madhya Pradesh': [
                'Agar Malwa', 'Alirajpur', 'Anuppur', 'Ashoknagar', 'Balaghat', 'Baloda Bazar',
                'Barwani', 'Betul', 'Bhopal', 'Bhind', 'Bhojpur', 'Biaora', 'Biora', 'Birlagram',
                'Burhanpur', 'Chhindwara', 'Chhotaudepur', 'Daman', 'Damoh', 'Dantewada', 'Datia',
                'Deosar', 'Dewas', 'Dhar', 'Dharampuri', 'Dindori', 'Dohrighat', 'Duma',
                'Dumariya', 'Dungarpur', 'Durg', 'East Nimar', 'Gaj', 'Gajraula', 'Galichhpur',
                'Garoth', 'Garudpur', 'Gat', 'Gaud', 'Gaudha', 'Gaurihar', 'Gaur', 'Gavhan',
                'Gaya', 'Gayan', 'Gayathri', 'Gayeri', 'Gela', 'Gelhaunia', 'Gelkund', 'Gelpur',
                'Gelrad', 'Gelsa', 'Gelsar', 'Gelsaud', 'Gelson', 'Gelsora', 'Gelsot', 'Gelsu',
                'Gelsy', 'Geltala', 'Geltha', 'Gelthi', 'Gelti', 'Gelto', 'Geltora', 'Geltra',
                'Geltu', 'Geltuk', 'Geltul', 'Geltum', 'Geltun', 'Geltuo', 'Geltup', 'Geltur'
            ],
            'Maharashtra': [
                'Ahmednagar', 'Akola', 'Amravati', 'Aurangabad', 'Beed', 'Bhandara', 'Bhir',
                'Bhor', 'Buldana', 'Chandrapur', 'Chhatrapati Sambhajinagar', 'Chikli', 'Chiplun',
                'Chirud', 'Chitradurga', 'Chotipur', 'Choudhapur', 'Choutala', 'Chouthui', 'Chunar',
                'Chunavali', 'Chunbhal', 'Chunbhar', 'Chundal', 'Chundhai', 'Chundhar', 'Chundhri',
                'Chundla', 'Chundli', 'Chundoli', 'Chundra', 'Chundraj', 'Chundri', 'Chunduga',
                'Chundupur', 'Chundur', 'Chunead', 'Chunedh', 'Chunegal', 'Chunel', 'Chunelihali',
                'Chunepalli', 'Chunepur', 'Chunera', 'Chunerabad', 'Chunering', 'Chuneru', 'Chunesa',
                'Chunetala', 'Chunetha', 'Chuneti', 'Chunethpal', 'Chunetpur', 'Chunetta', 'Chuneur',
                'Chuneva', 'Chunewad', 'Chunewala', 'Chunewali', 'Chunewa', 'Chunewda', 'Chunewed',
                'Chunewal', 'Chuneyan', 'Chunezpura', 'Chunfa', 'Chunfara', 'Chunfari', 'Chunfapur',
                'Chunfara', 'Chungar', 'Chungari', 'Chungda', 'Chungdal', 'Chungdao', 'Chungdara',
                'Chungdari', 'Chungdatala', 'Chungdather', 'Chungdauli', 'Chungdav', 'Chungdawa',
                'Chungdawal', 'Chungde', 'Chungdel', 'Chungden', 'Chungdeo', 'Chungder', 'Chungdera',
                'Chungdet', 'Chungdeu', 'Chungdew', 'Chungdey', 'Chungdha', 'Chungdhal', 'Chungdhan',
                'Chungdhar', 'Chungdhari', 'Chungdhaspur', 'Chungdhata', 'Chungdhau', 'Chungdhav',
                'Chungdhawa', 'Chungdhay', 'Chungdhazar', 'Chungdhazpur', 'Chungdhe', 'Chungdhel',
                'Chungdhen', 'Chungdher', 'Chungdhera', 'Chungdhetal', 'Chungdhetara', 'Chungdhetay',
                'Chungdhey', 'Chungdhi', 'Chungdhia', 'Chungdhial', 'Chungdhian', 'Chungdhiar',
                'Chungdhiapur', 'Chungdhiat', 'Chungdhiau', 'Chungdhiawan', 'Chungdhib', 'Chungdhicul',
                'Chungdhie', 'Chungdhiel', 'Chungdhien', 'Chungdhier', 'Chungdhiet', 'Chungdhieu',
                'Chungdhiew', 'Chungdhey', 'Chungdho', 'Chungdhol', 'Chungdhon', 'Chungdhor',
                'Chungdhra', 'Chungdhratan', 'Chungdhu', 'Chungdhul', 'Chungdhun', 'Chungdhup',
                'Chungdhur', 'Chungdhuri', 'Chungdhurst', 'Chungdhuta', 'Chungdhute', 'Chungdhutel',
                'Chungdhuti', 'Chungdhutia', 'Chungdhutial', 'Chungdhuye', 'Chungdhya', 'Chungdhyal',
                'Chungdhyan', 'Chungdhyar', 'Chungdhyara', 'Chungdhyaru', 'Chungdhyat', 'Chungdhyau',
                'Chungdhyaw', 'Chungdhyay', 'Chungdhye', 'Chungdhyel', 'Chungdhyen', 'Chungdhyer',
                'Chungdhyera', 'Chungdhyet', 'Chungdhyeu', 'Chungdhyew', 'Chungdhyey', 'Chungdhyo',
                'Chungdhyol', 'Chungdhyon', 'Chungdhyor', 'Chungdhyora', 'Chungdhyu', 'Chungdhyul'
            ],
            'Manipur': [
                'Bishnupur', 'Chandel', 'Churachandpur', 'Imphal East', 'Imphal West', 'Jiribam',
                'Kakching', 'Kamjong', 'Kangpokpi', 'Noney', 'Pherzawl', 'Senapati', 'Tamenglong',
                'Tengnoupal', 'Thoubal', 'Ukhrul'
            ],
            'Meghalaya': [
                'East Garo Hills', 'East Khasi Hills', 'East Jaintia Hills', 'Ri Bhoi', 'South Garo Hills',
                'South West Garo Hills', 'South West Khasi Hills', 'Wahlynngdoh', 'West Garo Hills',
                'West Jaintia Hills', 'West Khasi Hills'
            ],
            'Mizoram': [
                'Aizawl', 'Aizol', 'Aizel', 'Aizul', 'Aizul', 'Champhai', 'Kolasib', 'Lawngtlai',
                'Lunglei', 'Mamit', 'Saiha', 'Serchhip'
            ],
            'Nagaland': [
                'Chumoukedima', 'Dimapur', 'Kiphire', 'Kohima', 'Longleng', 'Mokokchung', 'Mon',
                'Nagaon', 'Peren', 'Phek', 'Tuensang', 'Wokha', 'Zunheboto'
            ],
            'Odisha': [
                'Angul', 'Balangir', 'Balasore', 'Bargarh', 'Barkot', 'Berhampur', 'Bhadrak',
                'Bhadrakh', 'Bhawanipatna', 'Bhilai', 'Bhubaneswar', 'Bhubneshwar', 'Bikaner',
                'Bilaspur', 'Biramitrapur', 'Birganj', 'Birsinghpur', 'Bishwanath', 'Bisra',
                'Bitra', 'Bituruni', 'Bivarni', 'Bjpur', 'Bjodhpur', 'Blangir', 'Bode', 'Bogur',
                'Boh', 'Bohar', 'Boharpur', 'Bohi', 'Bohra', 'Bohria', 'Bohuria', 'Boida',
                'Boidasahi', 'Boikhal', 'Boira', 'Boirali', 'Boirpur', 'Boitalpur', 'Boitamari',
                'Boitanda', 'Boitandi', 'Boitangi', 'Boitanpur', 'Boitari', 'Boitarpur', 'Boitate',
                'Boitelpur', 'Boitelripalli', 'Boitelu', 'Boitelupalli', 'Boitemar', 'Boitemara',
                'Boitembur', 'Boitemi', 'Boitemia', 'Boitemira', 'Boitemirpur', 'Boitena', 'Boitenapalli',
                'Boitenapuri', 'Boitenar', 'Boitenapur', 'Boitendi', 'Boitendipalli', 'Boitengali',
                'Boitengalipalli', 'Boitengi', 'Boiteni', 'Boitenia', 'Boiteo', 'Boiteoli', 'Boitepalli',
                'Boitepar', 'Boitepata', 'Boitepathi', 'Boitepur', 'Boitepura', 'Boitepuri', 'Boiter',
                'Boitera', 'Boiterah', 'Boiterai', 'Boiteraj', 'Boiteran', 'Boiterapalli', 'Boiterapur',
                'Boiterapura', 'Boiterapuri', 'Boiteras', 'Boiterat', 'Boiterat', 'Boiterau', 'Boiterav',
                'Boiteraw', 'Boiteray', 'Boiteraya', 'Boitere', 'Boiterel', 'Boiterem', 'Boiteremunda',
                'Boiteremundia', 'Boiterena', 'Boiterepu', 'Boiterewali', 'Boiterez', 'Boiterfuli',
                'Boitergada', 'Boitergarh', 'Boiterghat', 'Boiterghol', 'Boitergolla', 'Boitergota',
                'Boiterhara', 'Boiterhardi', 'Boiterharina', 'Boiterharpur', 'Boiterhata', 'Boiterhati',
                'Boiterhator', 'Boiterhatu', 'Boiterhav', 'Boiterhawa', 'Boiterhawapur', 'Boiterhay',
                'Boiterhaya', 'Boiterhazra', 'Boitehe', 'Boitehel', 'Boiteheli', 'Boitehen', 'Boitehera',
                'Boiteheri', 'Boiterheran', 'Boitehey', 'Boitehi', 'Boitehia', 'Boitehira', 'Boiteho',
                'Boitehol', 'Boiteholia', 'Boitehora', 'Boitehori', 'Boitehorpur', 'Boitehy', 'Boitehya',
                'Boitehyara', 'Boitehyari', 'Boitehyapur', 'Boitehyata', 'Boitehye', 'Boitehyeli',
                'Boitehyen', 'Boitehyer', 'Boitehyera', 'Boitehyeri', 'Boitehyey', 'Boitehyo',
                'Boitehyol', 'Boitehyon', 'Boitehyor', 'Boitehyora', 'Boitehyu', 'Boitehyul',
                'Boitehyuna', 'Boitehyuni', 'Boitehyup', 'Boitehyur', 'Boitehyura', 'Boitehyuri',
                'Boitehyut', 'Boitehyuta', 'Boitehyuyi', 'Boitei', 'Boiteia', 'Boiteial', 'Boiteiara',
                'Boiteib', 'Boiteibara', 'Boiteic', 'Boiteid', 'Boiteida', 'Boiteide', 'Boiteidi',
                'Boiteido', 'Boiteidu', 'Boiteie', 'Boiteiea', 'Boiteiee', 'Boiteieh', 'Boiteiej',
                'Boiteiek', 'Boiteiela', 'Boiteiem', 'Boiteien', 'Boiteieoa', 'Boiteiep', 'Boiteiepali',
                'Boiteiera', 'Boiteierat', 'Boiteies', 'Boiteiet', 'Boiteieu', 'Boiteieua', 'Boiteieva',
                'Boiteiew', 'Boiteieya', 'Boiteiez', 'Boiteif', 'Boiteifa', 'Boiteife', 'Boiteifo',
                'Boiteifu', 'Boiteig', 'Boiteigar', 'Boiteigarh', 'Boiteigata', 'Boiteigati',
                'Boiteigatpur', 'Boiteige', 'Boiteiger', 'Boiteigera', 'Boiteigeri', 'Boiteiget',
                'Boiteigi', 'Boiteigia', 'Boiteigio', 'Boiteigipur', 'Boiteigiroad', 'Boiteigir',
                'Boiteigirad', 'Boiteigis', 'Boiteiglot', 'Boiteigoa', 'Boiteigola', 'Boiteigot',
                'Boiteigotha', 'Boiteigra', 'Boiteigraj', 'Boiteigram', 'Boiteigrampalli', 'Boiteigran',
                'Boiteigrap', 'Boiteigrar', 'Boiteigras', 'Boiteigrat', 'Boiteigrata', 'Boiteigre',
                'Boiteigreh', 'Boiteigreja', 'Boiteigrem', 'Boiteigren', 'Boiteigrena', 'Boiteigrer',
                'Boiteigrera', 'Boiteigres', 'Boiteigresha', 'Boiteigret', 'Boiteigreta', 'Boiteigreu',
                'Boiteigreya', 'Boiteigri', 'Boiteigrih', 'Boiteigrihpur', 'Boiteigrij', 'Boiteigril',
                'Boiteigrila', 'Boiteigrile', 'Boiteigrili', 'Boiteigrilla', 'Boiteigrilo', 'Boiteigrilpur',
                'Boiteigrilya', 'Boiteigrim', 'Boiteigrima', 'Boiteigrimi', 'Boiteigrimo', 'Boiteigrip',
                'Boiteigripa', 'Boiteigripe', 'Boiteigripo', 'Boiteigrip', 'Boiteigripepur', 'Boiteigrir',
                'Boiteigrira', 'Boiteigrire', 'Boiteigrireta', 'Boiteigri', 'Boiteigriri', 'Boiteigriro',
                'Boiteigrirup', 'Boiteigris', 'Boiteigrisa', 'Boiteigrish', 'Boiteigrishapur', 'Boiteigrish',
                'Boiteigrisi', 'Boiteigrispur', 'Boiteigrit', 'Boiteigritan', 'Boiteigritanur', 'Boiteigrity',
                'Boiteigriu', 'Boiteigriv', 'Boiteigriya', 'Boiteigriyapur', 'Boiteigriyawar', 'Boiteigriyaz'
            ],
            'Punjab': [
                'Amritsar', 'Barnala', 'Bathinda', 'Faridkot', 'Fatehgarh Sahib', 'Fazilka', 'Firozpur',
                'Gurdaspur', 'Hoshiarpur', 'Jalandhar', 'Kapurthala', 'Ludhiana', 'Mansa', 'Moga',
                'Mohali', 'Muktsar', 'Nawanshahr', 'Pathankot', 'Patiala', 'Rupnagar', 'Sangrur', 'SBS Nagar'
            ],
            'Rajasthan': [
                'Ajmer', 'Alwar', 'Banswara', 'Baran', 'Barmer', 'Beawar', 'Bhilwara', 'Bhind',
                'Bikaner', 'Binder', 'Böloti', 'Bölpur', 'Boner', 'Bönpur', 'Boparpur', 'Boranpur',
                'Bòrmpur', 'Börnpur', 'Böro', 'Bòrpur', 'Börspur', 'Börtpur', 'Bòrupur', 'Bòrvpur',
                'Börwpur', 'Börypur', 'Bòsapur', 'Bösbpur', 'Böscpur', 'Bösdpur', 'Bòsepur', 'Bösfpur',
                'Bösgpur', 'Böshpur', 'Bösipur', 'Bösjpur', 'Böskpur', 'Böslpur', 'Bösmpur', 'Bösnpur',
                'Bòsopur', 'Bösppur', 'Bösqpur', 'Bösrpur', 'Bössburg', 'Böstpur', 'Bösupur', 'Bösvpur',
                'Böswpur', 'Bösxpur', 'Bösypur', 'Böszpur', 'Böta', 'Bötaar', 'Bötab', 'Bötac',
                'Bötad', 'Bötae', 'Bötaf', 'Bötag', 'Bötah', 'Bötai', 'Bötaj', 'Bötak', 'Bötala',
                'Bötam', 'Bötan', 'Bötao', 'Bötap', 'Bötar', 'Bötara', 'Bötari', 'Bötas', 'Bötat',
                'Bötau', 'Bötav', 'Bötaw', 'Bötax', 'Bötay', 'Bötaz', 'Böte', 'Böteaa', 'Böteab',
                'Böteac', 'Bötead', 'Böteae', 'Böteaf', 'Böteag', 'Böteah', 'Böteai', 'Böteaj',
                'Böteau', 'Böteav', 'Böteaw', 'Böteax', 'Böteay', 'Böteaz'
            ],
            'Sikkim': [
                'East Sikkim', 'North Sikkim', 'South Sikkim', 'West Sikkim'
            ],
            'Tamil Nadu': [
                'Ariyalur', 'Chengalpattu', 'Chengelpet', 'Chennai', 'Coimbatore', 'Cuddalore',
                'Dharmapuri', 'Dindigul', 'Erode', 'Kallakurichi', 'Kanchipuram', 'Kanyakumari',
                'Karur', 'Krishnagiri', 'Madurai', 'Mayiladuthurai', 'Nagapattinam', 'Namakkal',
                'Nilgiris', 'Perambalur', 'Pudukkottai', 'Ramanathapuram', 'Ranipet', 'Salem',
                'Sivaganga', 'Tenkasi', 'Thanjavur', 'Theni', 'Thiruvallur', 'Thiruvannamalai',
                'Thiruvarur', 'Tirupattur', 'Tiruppur', 'Tiruvannamalai', 'Thoothukudi', 'Tirunelveli',
                'Vellore', 'Villupuram', 'Virudunagar'
            ],
            'Telangana': [
                'Adilabad', 'Bhadradri Kothagudem', 'Hyderabad', 'Jagtial', 'Jangaon', 'Jayashankar',
                'Jogulamba Gadwal', 'Kamareddy', 'Karimnagar', 'Khammam', 'Komaram Bheem Asifabad',
                'Mahabubabad', 'Mahbubnagar', 'Mancherial', 'Medak', 'Medchal Malkajgiri', 'Mulugu',
                'Nagarkurnool', 'Nalgonda', 'Narayanpet', 'Nirmal', 'Nizamabad', 'Peddapalli',
                'Rajanna Sircilla', 'Ranga Reddy', 'Sangareddy', 'Siddipet', 'Suryapet', 'Vikarabad',
                'Wanaparthy', 'Warangal Rural', 'Warangal Urban', 'Yadadri Bhuvanagiri'
            ],
            'Tripura': [
                'Dhalai', 'Gomati', 'Khowai', 'North Tripura', 'Sepahijala', 'South Tripura',
                'Unakoti', 'West Tripura'
            ],
            'Uttar Pradesh': [
                'Agra', 'Aligarh', 'Allahabad', 'Ambedkar Nagar', 'Amethi', 'Amroha', 'Auraiya',
                'Azamgarh', 'Baghpat', 'Bahraich', 'Ballia', 'Balrampur', 'Banda', 'Barabanki',
                'Bareilly', 'Basti', 'Bijnor', 'Bhapur', 'Bhindypur', 'Bhira', 'Bhirpur', 'Bhisauli',
                'Bhoranj', 'Bhorey', 'Bhowal', 'Bhupur', 'Bhuraria', 'Bhurha', 'Bhurhapur', 'Bhusawal',
                'Bhushara', 'Bhusura', 'Bhusurgarh', 'Bhuta', 'Bhutaha', 'Bhutapur', 'Bhutkhedi',
                'Bhutwada', 'Biara', 'Biarpur', 'Bibia', 'Bibiganj', 'Bibinagar', 'Bibio', 'Bibipur',
                'Bibisarai', 'Bibispur', 'Bibiswara', 'Bichua', 'Bichupa', 'Bichurapur', 'Bichuri',
                'Bichurwa', 'Bichya', 'Bidahi', 'Bidaila', 'Bidalpur', 'Bidalur', 'Bidalwa', 'Bidana',
                'Bidanapur', 'Bidanpur', 'Bidanur', 'Bidanwa', 'Bidapur', 'Bidara', 'Bidarapur',
                'Bidarau', 'Bidarauli', 'Bidarawa', 'Bidarbad', 'Bidardi', 'Bidarg', 'Bidargarh',
                'Bidargar', 'Bidargha', 'Bidargh', 'Bidarghati', 'Bidari', 'Bidaria', 'Bidaria',
                'Bidariapur', 'Bidariba', 'Bidaribagh', 'Bidaribail', 'Bidaribandh', 'Bidaribari',
                'Bidaribawa', 'Bidaribhang', 'Bidaribhopal', 'Bidaribhua', 'Bidaribiha', 'Bidaribir',
                'Bidaribira', 'Bidaribiro', 'Bidaribiska', 'Bidaribkul', 'Bidaribnagar', 'Bidaribo',
                'Bidaribod', 'Bidaribodh', 'Bidaribodpur', 'Bidariboji', 'Bidaribojpur', 'Bidaribola',
                'Bidariboldh', 'Bidaribore', 'Bidaribori', 'Bidariboria', 'Bidariborial', 'Bidariboru',
                'Bidaribos', 'Bidaribosh', 'Bidaribosi', 'Bidaribota', 'Bidaribotai', 'Bidaribotair',
                'Bidaribotaj', 'Bidaribotal', 'Bidaribotali', 'Bidaribotapur', 'Bidaribotara',
                'Bidaribotari', 'Bidaribotariya', 'Bidaribotary', 'Bidaribotash', 'Bidaribotashpur',
                'Bidaribotasia', 'Bidaribotasin', 'Bidaribotasir', 'Bidaribotasirah', 'Bidaribotasir',
                'Bidaribotasira', 'Bidaribotasire', 'Bidaribotasir', 'Bidaribotasir', 'Bidaribotasira',
                'Bidaribotasir', 'Bidaribotasir', 'Bidaribotasir', 'Bidaribotasir', 'Bidaribotasir',
                'Bihapur'
            ],
            'Uttarakhand': [
                'Almora', 'Bageshwar', 'Chamoli', 'Champawat', 'Dehradun', 'Garhwal', 'Haridwar',
                'Kumaon', 'Nainital', 'Pauri', 'Pithoragarh', 'Rudraprayag', 'Tehri', 'Udham Singh Nagar',
                'Uttarkashi'
            ],
            'West Bengal': [
                'Alipurduar', 'Bankura', 'Birbhum', 'Cooch Behar', 'Darjeeling', 'Dinajpur',
                'East Midnapore', 'Hooghly', 'Howrah', 'Jalpaiguri', 'Jhargram', 'Kalimpong',
                'Kolkata', 'Malda', 'Murshidabad', 'Nadia', 'North 24 Parganas', 'North Dinajpur',
                'Paschim Medinipur', 'Purba Bardhaman', 'Purba Medinipur', 'Purulia', 'South 24 Parganas',
                'South Dinajpur', 'Sundarban', 'West Medinipur', 'Yamunanagar'
            ]
        };

        const stateSelect = document.getElementById('state');
        const districtSelect = document.getElementById('district');

        if (stateSelect && districtSelect) {
            // Function to populate districts for a given state
            function populateDistricts(selectedState, selectedDistrict = '') {
                districtSelect.innerHTML = '<option value="" disabled>Select District</option>';

                if (selectedState && stateDistricts[selectedState]) {
                    stateDistricts[selectedState].forEach(function(district) {
                        const option = document.createElement('option');
                        option.value = district;
                        option.textContent = district;
                        if (district === selectedDistrict) {
                            option.selected = true;
                        }
                        districtSelect.appendChild(option);
                    });
                    districtSelect.disabled = false;
                } else {
                    districtSelect.disabled = true;
                }
            }

            // Auto-fill initialization: Check if state is pre-selected from URL parameters
            const autoState = '<?php echo htmlspecialchars($auto_event_state); ?>';
            const autoDistrict = '<?php echo htmlspecialchars($auto_event_district); ?>';

            if (autoState && stateSelect.value === autoState) {
                populateDistricts(autoState, autoDistrict);
            }

            stateSelect.addEventListener('change', function() {
                populateDistricts(this.value);
            });
        }

        // Auto-calculate number of days from start and end dates
        const eventDateInput = document.getElementById('eventDate');
        const eventEndDateInput = document.getElementById('eventEndDate');
        const noOfDaysInput = document.getElementById('noOfDays');

        function calculateDays() {
            if (eventDateInput && eventEndDateInput && noOfDaysInput) {
                const startDate = eventDateInput.value;
                const endDate = eventEndDateInput.value;

                if (startDate && endDate) {
                    const start = new Date(startDate);
                    const end = new Date(endDate);

                    // Validate end date is not before start date
                    if (end < start) {
                        eventEndDateInput.setCustomValidity('End date must be same or after start date');
                        noOfDaysInput.value = '';
                    } else {
                        eventEndDateInput.setCustomValidity('');
                        // Calculate difference in days (+1 to include both start and end dates)
                        const diffTime = Math.abs(end - start);
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                        noOfDaysInput.value = diffDays;
                    }
                } else if (startDate && !endDate) {
                    // If only start date is set, default end date to same as start date
                    eventEndDateInput.value = startDate;
                    noOfDaysInput.value = 1;
                }
            }
        }

        // Set default value when dates are selected
        if (eventDateInput && eventEndDateInput && noOfDaysInput) {
            eventDateInput.addEventListener('change', function() {
                // Set min attribute for end date
                eventEndDateInput.min = this.value;
                calculateDays();
            });

            eventEndDateInput.addEventListener('change', calculateDays);

            // Calculate on page load if dates are pre-filled
            if (eventDateInput.value) {
                eventEndDateInput.min = eventDateInput.value;
                calculateDays();
            }
        }

        // Hide/show prize section based on event type
        const eventTypeSelect = document.getElementById('eventType');
        const prizeSection = document.querySelector('.form-section:has(#prize)');

        function togglePrizeSection() {
            if (eventTypeSelect && prizeSection) {
                const eventType = eventTypeSelect.value;
                // Hide prize section for Workshop, Seminar, Webinar, Training, Symposium
                const noPrizeEvents = ['Workshop', 'Seminar', 'Webinar', 'Training', 'Symposium'];

                if (noPrizeEvents.includes(eventType)) {
                    prizeSection.style.display = 'none';
                    // Clear prize values when hiding
                    const prizeSelect = document.getElementById('prize');
                    const amountInput = document.getElementById('amount');
                    if (prizeSelect) prizeSelect.value = '';
                    if (amountInput) amountInput.value = '';
                } else {
                    prizeSection.style.display = 'block';
                }
            }
        }

        if (eventTypeSelect) {
            eventTypeSelect.addEventListener('change', togglePrizeSection);
            // Check on page load
            togglePrizeSection();
        }

        // ============================================================================
        // NOTIFICATION SYSTEM
        // ============================================================================

        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationOverlay = document.createElement('div');
        notificationOverlay.className = 'notification-overlay';
        document.body.appendChild(notificationOverlay);

        // Fetch notifications when page loads
        function loadNotifications() {
            fetch('ajax/get_notifications.php?action=get_notifications')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayNotifications(data.notifications, data.unread_count);
                    }
                })
                .catch(error => console.log('Error loading notifications:', error));
        }

        function displayNotifications(notifications, unreadCount) {
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationBadge');

            // Update badge
            if (unreadCount > 0) {
                notificationBadge.textContent = unreadCount;
                notificationBadge.classList.remove('hidden');
            } else {
                notificationBadge.classList.add('hidden');
            }

            // Clear list
            notificationList.innerHTML = '';

            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <li class="notification-empty">
                        <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                        <p>No notifications</p>
                    </li>
                `;
                return;
            }

            // Add notifications
            notifications.forEach(notification => {
                const date = new Date(notification.created_at);
                const timeString = getTimeString(date);

                const li = document.createElement('li');
                li.className = `notification-item ${(notification.is_read == 0 || notification.is_read === null) ? 'unread' : ''}`;
                li.innerHTML = `
                    <div class="notification-item-icon">
                        <span class="material-symbols-outlined">emoji_events</span>
                    </div>
                    <div class="notification-item-content">
                        <h4>${escapeHtml(notification.title || notification.hackathon_title)}</h4>
                        <p>${escapeHtml(notification.message)}</p>
                        <span class="notification-item-time">${timeString}</span>
                    </div>
                `;
                li.style.cursor = 'pointer';
                li.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Notification clicked:', notification.id, notification.link);
                    handleNotificationClick(notification.id, notification.link);
                };
                notificationList.appendChild(li);
            });
        }

        function resolveNotificationLink(link) {
            if (!link) return '/event_management_system/login/student/hackathons.php';
            if (link.startsWith('/event_management_system/')) return link;
            if (link.startsWith('/student/')) return '/event_management_system/login' + link;
            if (link.startsWith('student/')) return '/event_management_system/login/' + link;
            if (!link.startsWith('/') && !link.startsWith('http')) return '/event_management_system/login/student/' + link;
            return link;
        }

        function handleNotificationClick(notificationId, link) {
            // Close dropdown immediately
            notificationDropdown.classList.remove('show');
            notificationOverlay.classList.remove('show');

            const fullLink = resolveNotificationLink(link);

            // Mark as read then redirect (always redirect regardless of mark_as_read result)
            fetch(`ajax/get_notifications.php?action=mark_as_read&id=${notificationId}`)
                .finally(() => {
                    window.location.href = fullLink;
                });
        }

        window.clearAllNotifications = function() {
            if (!confirm('Are you sure you want to clear all notifications?')) return;

            const notificationList = document.getElementById('notificationList');
            notificationList.innerHTML = `
                <li class="notification-empty">
                    <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                    <p>No notifications</p>
                </li>
            `;
            const notificationBadge = document.getElementById('notificationBadge');
            notificationBadge.classList.add('hidden');
            notificationBadge.textContent = '0';

            fetch('ajax/get_notifications.php?action=clear_all')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                    }
                })
                .catch(error => console.log('Error clearing notifications:', error));
        };

        window.markAllNotificationsAsRead = function() {
            const notificationItems = document.querySelectorAll('#notificationList .notification-item.unread');
            notificationItems.forEach(item => item.classList.remove('unread'));
            const notificationBadge = document.getElementById('notificationBadge');
            notificationBadge.classList.add('hidden');
            notificationBadge.textContent = '0';

            fetch('ajax/get_notifications.php?action=mark_all_read')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        loadNotifications();
                    }
                })
                .catch(error => console.log('Error marking all notifications as read:', error));
        };

        function getTimeString(date) {
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);

            if (minutes < 1) return 'just now';
            if (minutes < 60) return `${minutes}m ago`;
            if (hours < 24) return `${hours}h ago`;
            if (days < 7) return `${days}d ago`;

            return date.toLocaleDateString();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Toggle notification dropdown
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            notificationOverlay.classList.toggle('show');
        });

        // Close dropdown when clicking overlay
        notificationOverlay.addEventListener('click', function() {
            notificationDropdown.classList.remove('show');
            notificationOverlay.classList.remove('show');
        });

        // Close dropdown when clicking outside (except on bell)
        document.addEventListener('click', function(e) {
            if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
                notificationOverlay.classList.remove('show');
            }
        });

        // Load notifications on page load
        loadNotifications();
        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
    });
    </script>
    <!-- Push Notifications Manager for Median.co -->
</body>
</html>






</script>
</body>
</html>
