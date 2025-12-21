<?php
    // Start session
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Get username from session
    $username = $_SESSION['username'];

    // Fetch student data from database
    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $stmt = $conn->prepare("SELECT regno, name FROM student_register WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: ../index.php");
        exit();
    }

    $student_info    = $result->fetch_assoc();
    $logged_in_regno = $student_info['regno'];
    $student_name    = $student_info['name'];
    $stmt->close();

    // Initialize variables
    $success_message = '';
    $error_message   = '';
    $form_data       = [];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token (optional, add if you have one)

        // Sanitize and validate inputs
        $company_name     = trim($_POST['company_name'] ?? '');
        $company_website  = trim($_POST['company_website'] ?? '');
        $company_address  = trim($_POST['company_address'] ?? '');
        $supervisor_name  = trim($_POST['supervisor_name'] ?? '');
        $supervisor_email = trim($_POST['supervisor_email'] ?? '');
        $role_title       = trim($_POST['role_title'] ?? '');
        $domain           = trim($_POST['domain'] ?? '');
        $mode             = trim($_POST['mode'] ?? '');
        $start_date       = trim($_POST['start_date'] ?? '');
        $end_date         = trim($_POST['end_date'] ?? '');
        $stipend_amount   = isset($_POST['stipend_amount']) ? floatval($_POST['stipend_amount']) : 0;
        $brief_report     = trim($_POST['brief_report'] ?? '');

        // Validate required fields
        if (empty($company_name)) {
            $error_message = "❌ Company Name is required.";
        } elseif (empty($company_website)) {
            $error_message = "❌ Company Website is required.";
        } elseif (empty($company_address)) {
            $error_message = "❌ Company Address is required.";
        } elseif (empty($supervisor_name)) {
            $error_message = "❌ Supervisor Name is required.";
        } elseif (empty($supervisor_email) || ! filter_var($supervisor_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "❌ Valid Supervisor Email is required.";
        } elseif (empty($role_title)) {
            $error_message = "❌ Role/Title is required.";
        } elseif (empty($domain)) {
            $error_message = "❌ Domain is required.";
        } elseif (empty($mode)) {
            $error_message = "❌ Mode is required.";
        } elseif (empty($start_date) || empty($end_date)) {
            $error_message = "❌ Start Date and End Date are required.";
        } elseif (strtotime($start_date) > strtotime($end_date)) {
            $error_message = "❌ Start Date cannot be after End Date.";
        } elseif (empty($brief_report)) {
            $error_message = "❌ Brief Report/Key Learnings is required.";
        } else {
            // Check for duplicate internship submission
            $check_conn = new mysqli("localhost", "root", "", "event_management_system");
            if ($check_conn->connect_error) {
                $error_message = "❌ Database connection failed.";
            } else {
                $check_stmt = $check_conn->prepare(
                    "SELECT id, company_name FROM internship_submissions
                     WHERE regno = ? AND company_name = ? AND start_date = ?"
                );
                $check_stmt->bind_param("sss", $logged_in_regno, $company_name, $start_date);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    // Duplicate found - set error message
                    $error_message = "⚠️ You have already submitted an internship for this company with the same start date: " . htmlspecialchars($company_name);
                    $check_stmt->close();
                    $check_conn->close();
                } else {
                    $check_stmt->close();
                    $check_conn->close();
                }
            }

            // Handle file uploads
            $internship_cert_path = null;
            $offer_letter_path    = null;

            // Create upload directory if it doesn't exist
            $upload_dir = '../uploads/internship_submissions/';
            if (! is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Handle Internship Certificate upload with compression (required)
            if (isset($_FILES['internship_certificate']) && $_FILES['internship_certificate']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['internship_certificate']['error'] === UPLOAD_ERR_OK) {
                    $cert_file     = $_FILES['internship_certificate'];
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_file_size = 5 * 1024 * 1024; // 5MB

                    if (! in_array($cert_file['type'], $allowed_types)) {
                        $error_message = "❌ Internship Certificate must be PDF, JPG, or PNG.";
                    } elseif ($cert_file['size'] > $max_file_size) {
                        $error_message = "❌ Internship Certificate file size must be less than 5MB.";
                    } else {
                        $file_ext      = pathinfo($cert_file['name'], PATHINFO_EXTENSION);
                        $base_filename = $upload_dir . 'cert_' . $logged_in_regno . '_' . time();

                        // Compress and save the file
                        $compression_result = FileCompressor::compressUploadedFile(
                            $cert_file['tmp_name'],
                            $base_filename,
                            $file_ext,
                            85
                        );

                        if ($compression_result['success']) {
                            $internship_cert_path = 'internship_submissions/' . basename($compression_result['path']);
                            error_log(sprintf(
                                "Internship Cert compressed: %s -> %s (%.2f%% saved)",
                                FileCompressor::formatSize($compression_result['original_size']),
                                FileCompressor::formatSize($compression_result['compressed_size']),
                                $compression_result['savings_percent']
                            ));
                        } else {
                            $error_message = "❌ Failed to upload Internship Certificate.";
                        }
                    }
                } else {
                    $error_message = "❌ Error uploading Internship Certificate. Please try again.";
                }
            } else {
                $error_message = "❌ Internship Certificate is required.";
            }

            // Handle Offer Letter upload with compression (optional)
            if (empty($error_message) && isset($_FILES['offer_letter']) && $_FILES['offer_letter']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['offer_letter']['error'] === UPLOAD_ERR_OK) {
                    $offer_file    = $_FILES['offer_letter'];
                    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                    $max_file_size = 5 * 1024 * 1024;

                    if (! in_array($offer_file['type'], $allowed_types)) {
                        $error_message = "❌ Offer Letter must be PDF, JPG, or PNG.";
                    } elseif ($offer_file['size'] > $max_file_size) {
                        $error_message = "❌ Offer Letter file size must be less than 5MB.";
                    } else {
                        $file_ext      = pathinfo($offer_file['name'], PATHINFO_EXTENSION);
                        $base_filename = $upload_dir . 'offer_' . $logged_in_regno . '_' . time();

                        // Compress and save the file
                        $compression_result = FileCompressor::compressUploadedFile(
                            $offer_file['tmp_name'],
                            $base_filename,
                            $file_ext,
                            85
                        );

                        if ($compression_result['success']) {
                            $offer_letter_path = 'internship_submissions/' . basename($compression_result['path']);
                        }
                    }
                }
            }

            // If no errors, insert into database
            if (empty($error_message)) {
                $conn = new mysqli("localhost", "root", "", "event_management_system");

                if ($conn->connect_error) {
                    $error_message = "❌ Database connection failed.";
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO internship_submissions
                    (regno, company_name, company_website, company_address, supervisor_name, supervisor_email,
                     role_title, domain, mode, start_date, end_date, stipend_amount,
                     internship_certificate, offer_letter, brief_report, submission_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );

                    if (! $stmt) {
                        $error_message = "❌ Prepare failed: " . $conn->error;
                    } else {
                        $stmt->bind_param(
                            "sssssssssssdsss",
                            $logged_in_regno,
                            $company_name,
                            $company_website,
                            $company_address,
                            $supervisor_name,
                            $supervisor_email,
                            $role_title,
                            $domain,
                            $mode,
                            $start_date,
                            $end_date,
                            $stipend_amount,
                            $internship_cert_path,
                            $offer_letter_path,
                            $brief_report
                        );

                        if ($stmt->execute()) {
                            $success_message = "✅ Internship submission successful! Your data has been recorded.";
                            // Reset form
                            $form_data = [];
                        } else {
                            $error_message = "❌ Error: " . htmlspecialchars($stmt->error);
                        }

                        $stmt->close();
                    }

                    $conn->close();
                }
            }
        }

        // Store form data for re-population in case of error
        if (! empty($error_message)) {
            $form_data = [
                'company_name'     => $company_name,
                'company_website'  => $company_website,
                'company_address'  => $company_address,
                'supervisor_name'  => $supervisor_name,
                'supervisor_email' => $supervisor_email,
                'role_title'       => $role_title,
                'domain'           => $domain,
                'mode'             => $mode,
                'start_date'       => $start_date,
                'end_date'         => $end_date,
                'stipend_amount'   => $stipend_amount,
                'brief_report'     => $brief_report,
            ];
        }
    }

    // Student data already available from login session fetch above
    $student_data = [
        'name'       => $student_name,
        'regno'      => $logged_in_regno,
        'department' => '', // Can be fetched if needed
    ];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
  <title>Internship Submission</title>
  <link rel="stylesheet" href="student_dashboard.css" />

  <!-- Material Icons -->
  <link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
    rel="stylesheet"
  />
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet"
  />

  <style>
    /* Root Color Variables */
    :root {
      --primary-color: #0c3878;
      --secondary-color: #1e4276;
      --accent-color: #2d5aa0;
      --light-bg: #f8f9fa;
      --border-color: #e1e8ed;
      --text-primary: #0c3878;
      --text-secondary: #495057;
      --success-color: #28a745;
      --error-color: #dc3545;
    }

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
    }

    .grid-container {
      display: grid;
      grid-template-areas: "sidebar main";
      grid-template-columns: 280px 1fr;
      grid-template-rows: 1fr;
      min-height: 100vh;
      padding-top: 80px;
      transition: all 0.3s ease;
    }

    /* Header Styling */
    .header {
      grid-area: header;
      background: #ffffff;
      border-bottom: 1px solid #e9ecef;
      padding: 15px 30px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      width: 100%;
      z-index: 1002;
    }

    .header .menu-icon {
      display: none;
    }

    .header .icon img {
      height: 60px;
      object-fit: contain;
      flex-shrink: 0;
    }

    .header-title {
      flex: 1;
      text-align: center;
    }

    .header-title p {
      font-size: 24px;
      font-weight: 600;
      color: var(--primary-color);
      margin: 0;
    }

    /* Sidebar Styling */
    .sidebar {
      grid-area: sidebar;
      background: #ffffff;
      border-right: 1px solid #e9ecef;
      padding: 20px 0;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
      position: fixed;
      width: 280px;
      top: 80px;
      left: 0;
      height: calc(100vh - 80px);
      overflow-y: auto;
      transform: translateX(0);
      transition: transform 0.3s ease;
      z-index: 1000;
    }

    .sidebar-header {
      padding: 20px;
      border-bottom: 1px solid #e0e0e0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .sidebar-title {
      font-size: 20px;
      font-weight: 600;
      color: #1e4276;
      flex: 1;
    }

    .close-sidebar {
      display: none;
      cursor: pointer;
      color: #0c3878;
      font-size: 24px;
    }

    .student-info {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
      color: white;
      padding: 20px;
      margin: 0 20px 25px;
      border-radius: 15px;
      text-align: center;
    }

    .student-info .student-name {
      font-weight: 600;
      font-size: 15px;
      margin-bottom: 8px;
      color: white;
    }

    .student-info .student-regno {
      font-size: 12px;
      color: rgba(255, 255, 255, 0.8);
    }

    .nav-menu {
      list-style: none;
      padding: 10px 0;
      margin: 0;
    }

    .nav-item {
      margin: 0;
    }

    .nav-link {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px 20px;
      color: #495057;
      text-decoration: none;
      border-radius: 12px;
      transition: all 0.3s ease;
      font-weight: 500;
      margin: 0 20px;
    }

    .nav-link:hover, .nav-link.active {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
      color: white;
      transform: translateX(5px);
    }

    .nav-link span {
      font-size: 20px;
      color: inherit;
    }

    .main {
      grid-area: main;
      padding: 30px;
    }

    .registration-form {
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(12, 56, 120, 0.1);
      border: 1px solid #e1e8ed;
      padding: 40px;
      max-width: 1000px;
      width: 100%;
      margin: 0 auto;
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

    .required-asterisk {
      color: #dc3545;
      margin-left: 3px;
      font-weight: 600;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="number"],
    input[type="url"],
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
      border: 1px solid #ced4da;
      font-family: 'Poppins', sans-serif;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="date"]:focus,
    input[type="number"]:focus,
    input[type="url"]:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: #0c3878;
      box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
    }

    select {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
      background-position: right 12px center;
      background-repeat: no-repeat;
      background-size: 16px;
      padding-right: 40px;
    }

    textarea {
      resize: vertical;
      min-height: 100px;
      font-family: 'Poppins', sans-serif;
    }

    /* Form Sections */
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
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }

    .form-section-icon {
      margin-right: 8px;
      font-size: 18px;
      color: #0c3878;
    }

    /* File Upload Styling */
    .file-upload-wrapper {
      position: relative;
    }

    .file-upload-label {
      padding: 16px;
      font-size: 14px;
      font-weight: 500;
      color: #0c3878;
    }

    input[type="file"] {
      display: none;
    }

    .file-upload-box {
      border: 2px dashed #0c3878;
      border-radius: 8px;
      padding: 25px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: #f8f9fa;
    }

    .file-upload-box:hover {
      background: #e8ecf1;
      border-color: #1e4276;
    }

    .file-upload-box.active {
      border-color: #28a745;
      background: #e8f5e9;
    }

    .file-upload-text {
      color: #0c3878;
      font-weight: 500;
      margin-bottom: 5px;
    }

    .file-upload-hint {
      font-size: 12px;
      color: #6c757d;
    }

    .file-size-info {
      font-size: 12px;
      color: #6c757d;
      margin-top: 8px;
    }

    .file-selected {
      font-size: 12px;
      color: #28a745;
      margin-top: 8px;
      font-weight: 500;
    }

    /* Button Styling */
    .button-group {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      justify-content: center;
    }

    button {
      padding: 14px 30px;
      font-size: 16px;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-family: 'Poppins', sans-serif;
    }

    .btn-submit {
      background: linear-gradient(135deg, #0c3878 0%, #1e4276 100%);
      color: white;
      min-width: 200px;
    }

    .btn-submit:hover {
      background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(12, 56, 120, 0.3);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    .btn-cancel {
      background: #e9ecef;
      color: #495057;
      min-width: 150px;
    }

    .btn-cancel:hover {
      background: #dee2e6;
      transform: translateY(-2px);
    }

    /* Alert Messages */
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-weight: 500;
      display: flex;
      align-items: center;
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
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

    /* Two-column layout for some fields */
    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 20px;
    }

    .form-row-full {
      grid-column: 1 / -1;
    }

    /* Helper text */
    .form-field-helper {
      font-size: 12px;
      color: #6c757d;
      margin-top: 4px;
      font-style: italic;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      body {
        overflow-x: hidden;
      }

      .grid-container {
        grid-template-columns: 1fr;
        grid-template-areas:
          "header"
          "main";
        min-height: 100vh;
        width: 100%;
        max-width: 100vw;
      }

      .header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 999;
        width: 100%;
        padding: 0 15px;
        height: 70px;
      }

      .header .menu-icon {
        display: block;
      }

      .header .icon img {
        height: 50px;
        width: auto;
      }

      .header-title p {
        font-size: 18px;
      }

      .sidebar {
        position: fixed;
        left: -100%;
        top: 0;
        width: 300px;
        height: 100vh;
        background: white;
        border-right: 1px solid #e0e0e0;
        z-index: 1000;
        transition: left 0.3s ease;
      }

      .sidebar.active {
        left: 0;
      }

      .close-sidebar {
        display: block;
      }

      .main {
        padding: 90px 20px 30px 20px;
        margin: 0;
        grid-area: main;
        box-sizing: border-box;
      }

      .registration-form {
        padding: 25px;
        margin: 10px;
        border-radius: 10px;
        max-width: 100%;
        width: calc(100% - 20px);
      }

      .parent {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .form-row {
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

      .button-group {
        flex-direction: column;
      }

      .btn-submit,
      .btn-cancel {
        width: 100%;
        min-width: auto;
      }

      input[type="text"],
      input[type="email"],
      input[type="date"],
      input[type="number"],
      input[type="url"],
      select,
      textarea {
        padding: 12px;
        font-size: 16px;
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
      input[type="url"],
      select,
      textarea {
        padding: 12px;
        font-size: 16px;
      }

      .file-upload-box {
        padding: 15px;
      }

      .button-group {
        gap: 10px;
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
      <div class="icon">
        <img
          src="../sona_logo.jpg"
          alt="Sona College Logo"
          height="60px"
          width="200"
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
        <div class="close-sidebar">
          <span class="material-symbols-outlined">close</span>
        </div>
      </div>

      <div class="student-info">
        <div class="student-name"><?php echo htmlspecialchars($student_name); ?></div>
        <div class="student-regno"><?php echo htmlspecialchars($logged_in_regno); ?></div>
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
          <a href="student_register.php" class="nav-link">
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
        <li class="nav-item active">
          <a href="internship_submission.php" class="nav-link">
            <span class="material-symbols-outlined">work</span>
            Internship Submission
          </a>
        </li>
        <li class="nav-item">
          <a href="od_request.php" class="nav-link">
            <span class="material-symbols-outlined">description</span>
            OD Request
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
    <div class="registration-form">
      <h2 class="form-title"> Internship Submission</h2>

      <!-- Alert Messages -->
      <?php if (! empty($success_message)): ?>
        <div class="alert alert-success">
          <span class="alert-icon">✓</span>
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>

      <?php if (! empty($error_message)): ?>
        <div class="alert alert-error">
          <span class="alert-icon">✕</span>
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>

      <!-- Form Start -->
      <form method="POST" enctype="multipart/form-data" novalidate>

        <!-- SECTION A: Company Details -->
        <div class="form-section">
          <div class="form-section-title">
            <span class="form-section-icon"></span>
            Company Details
          </div>

          <div class="parent">
            <div class="item">
              <label for="company_name">Company Name <span class="required-asterisk">*</span></label>
              <input
                type="text"
                id="company_name"
                name="company_name"
                placeholder="Enter company name"
                value="<?php echo htmlspecialchars($form_data['company_name'] ?? ''); ?>"
                required
                maxlength="100"
              />
            </div>

            <div class="item">
              <label for="company_website">Company Website <span class="required-asterisk">*</span></label>
              <input
                type="url"
                id="company_website"
                name="company_website"
                placeholder="https://example.com"
                value="<?php echo htmlspecialchars($form_data['company_website'] ?? ''); ?>"
                required
              />
            </div>

            <div class="item form-row-full">
              <label for="company_address">Company Address <span class="required-asterisk">*</span></label>
              <input
                type="text"
                id="company_address"
                name="company_address"
                placeholder="Enter complete company address"
                value="<?php echo htmlspecialchars($form_data['company_address'] ?? ''); ?>"
                required
                maxlength="250"
              />
            </div>

            <div class="item">
              <label for="supervisor_name">Supervisor Name <span class="required-asterisk">*</span></label>
              <input
                type="text"
                id="supervisor_name"
                name="supervisor_name"
                placeholder="Internship supervisor's name"
                value="<?php echo htmlspecialchars($form_data['supervisor_name'] ?? ''); ?>"
                required
                maxlength="100"
              />
            </div>

            <div class="item">
              <label for="supervisor_email">Supervisor Email <span class="required-asterisk">*</span></label>
              <input
                type="email"
                id="supervisor_email"
                name="supervisor_email"
                placeholder="supervisor@example.com"
                value="<?php echo htmlspecialchars($form_data['supervisor_email'] ?? ''); ?>"
                required
              />
            </div>
          </div>
        </div>

        <!-- SECTION B: Internship Details -->
        <div class="form-section">
          <div class="form-section-title">
            <span class="form-section-icon"></span>
            Internship Details
          </div>

          <div class="parent">
            <div class="item">
              <label for="role_title">Role/Title <span class="required-asterisk">*</span></label>
              <input
                type="text"
                id="role_title"
                name="role_title"
                placeholder="e.g., Full Stack Developer"
                value="<?php echo htmlspecialchars($form_data['role_title'] ?? ''); ?>"
                required
                maxlength="100"
              />
            </div>

            <div class="item">
              <label for="domain">Domain <span class="required-asterisk">*</span></label>
              <select id="domain" name="domain" required>
                <option value="">Select Domain</option>
                <option value="Web Development"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($form_data['domain'] ?? '') === 'Web Development' ? 'selected' : ''; ?>>Web Development</option>
                <option value="AI/ML"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($form_data['domain'] ?? '') === 'AI/ML' ? 'selected' : ''; ?>>AI/ML</option>
                <option value="IoT"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($form_data['domain'] ?? '') === 'IoT' ? 'selected' : ''; ?>>IoT</option>
                <option value="Data Science"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo($form_data['domain'] ?? '') === 'Data Science' ? 'selected' : ''; ?>>Data Science</option>
                <option value="Cybersecurity"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($form_data['domain'] ?? '') === 'Cybersecurity' ? 'selected' : ''; ?>>Cybersecurity</option>
                <option value="Cloud Computing"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($form_data['domain'] ?? '') === 'Cloud Computing' ? 'selected' : ''; ?>>Cloud Computing</option>
                <option value="Mobile Development"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($form_data['domain'] ?? '') === 'Mobile Development' ? 'selected' : ''; ?>>Mobile Development</option>
                <option value="Other"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($form_data['domain'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>

            <div class="item">
              <label for="mode">Mode <span class="required-asterisk">*</span></label>
              <select id="mode" name="mode" required>
                <option value="">Select Mode</option>
                <option value="On-site"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($form_data['mode'] ?? '') === 'On-site' ? 'selected' : ''; ?>>On-site</option>
                <option value="Remote"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($form_data['mode'] ?? '') === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                <option value="Hybrid"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($form_data['mode'] ?? '') === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
              </select>
            </div>

            <div class="item">
              <label for="start_date">Start Date <span class="required-asterisk">*</span></label>
              <input
                type="date"
                id="start_date"
                name="start_date"
                value="<?php echo htmlspecialchars($form_data['start_date'] ?? ''); ?>"
                required
              />
            </div>

            <div class="item">
              <label for="end_date">End Date <span class="required-asterisk">*</span></label>
              <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?php echo htmlspecialchars($form_data['end_date'] ?? ''); ?>"
                required
              />
            </div>

            <div class="item">
              <label for="stipend_amount">Stipend Amount (₹) <span class="required-asterisk">*</span></label>
              <input
                type="number"
                id="stipend_amount"
                name="stipend_amount"
                placeholder="0"
                value="<?php echo htmlspecialchars($form_data['stipend_amount'] ?? 0); ?>"
                min="0"
                step="1"
                required
              />
              <p class="form-field-helper">Enter 0 if no stipend was provided</p>
            </div>
          </div>
        </div>

        <!-- SECTION C: Documents & Report -->
        <div class="form-section">
          <div class="form-section-title">
            <span class="form-section-icon"></span>
            Documents & Report
          </div>

          <div class="parent">
            <div class="item form-row-full">
              <label for="internship_certificate" class="file-upload-label">
                Internship Certificate <span class="required-asterisk">*</span>
              </label>
              <div class="file-upload-wrapper">
                <div class="file-upload-box" id="cert-upload-box">
                  <div class="file-upload-text">📎 Click or drag to upload</div>
                  <div class="file-upload-hint">PDF, JPG, or PNG (Max 5MB)</div>
                  <input
                    type="file"
                    id="internship_certificate"
                    name="internship_certificate"
                    accept=".pdf,.jpg,.jpeg,.png"
                    required
                    onchange="handleFileSelect(this, 'cert-upload-box')"
                  />
                </div>
                <div class="file-selected" id="cert-file-selected"></div>
              </div>
            </div>

            <div class="item form-row-full">
              <label for="offer_letter" class="file-upload-label">
                Offer Letter (Optional)
              </label>
              <div class="file-upload-wrapper">
                <div class="file-upload-box" id="offer-upload-box">
                  <div class="file-upload-text">📎 Click or drag to upload</div>
                  <div class="file-upload-hint">PDF, JPG, or PNG (Max 5MB)</div>
                  <input
                    type="file"
                    id="offer_letter"
                    name="offer_letter"
                    accept=".pdf,.jpg,.jpeg,.png"
                    onchange="handleFileSelect(this, 'offer-upload-box')"
                  />
                </div>
                <div class="file-selected" id="offer-file-selected"></div>
              </div>
            </div>

            <div class="item form-row-full">
              <label for="brief_report">Brief Report / Key Learnings <span class="required-asterisk">*</span></label>
              <textarea
                id="brief_report"
                name="brief_report"
                placeholder="Share your internship experience, key learnings, and accomplishments..."
                required
                maxlength="2000"
              ><?php echo htmlspecialchars($form_data['brief_report'] ?? ''); ?></textarea>
              <p class="form-field-helper">Maximum 2000 characters</p>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="button-group">
          <button type="submit" class="btn-submit"> Submit Internship</button>
          <button type="button" class="btn-cancel" onclick="cancelForm()">Cancel</button>
        </div>

      </form>
    </div>
    </div>
  </div>

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
      // Header menu icon functionality
      const headerMenuIcon = document.querySelector('.header .menu-icon');
      const sidebar = document.getElementById('sidebar');

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

    // File Upload Handler
    function handleFileSelect(input, boxId) {
      const uploadBox = document.getElementById(boxId);
      const fileSelected = document.getElementById(boxId.includes('cert') ? 'cert-file-selected' : 'offer-file-selected');

      if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileSize = (file.size / 1024 / 1024).toFixed(2);

        uploadBox.classList.add('active');
        fileSelected.textContent = `✓ ${file.name} (${fileSize}MB)`;
      } else {
        uploadBox.classList.remove('active');
        fileSelected.textContent = '';
      }
    }

    // File upload box drag and drop
    const uploadBoxes = document.querySelectorAll('.file-upload-box');
    uploadBoxes.forEach((box) => {
      box.addEventListener('click', function () {
        const input = this.querySelector('input[type="file"]');
        input.click();
      });

      box.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = '#0c3878';
        this.style.backgroundColor = '#e8ecf1';
      });

      box.addEventListener('dragleave', function (e) {
        e.preventDefault();
        this.style.borderColor = '#0c3878';
        this.style.backgroundColor = '#f8f9fa';
      });

      box.addEventListener('drop', function (e) {
        e.preventDefault();
        const input = this.querySelector('input[type="file"]');
        input.files = e.dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        input.dispatchEvent(event);
      });
    });

    // Cancel Form
    function cancelForm() {
      if (confirm('Are you sure you want to cancel? Unsaved changes will be lost.')) {
        window.location.href = 'index.php';
      }
    }

    // Form Validation
    document.querySelector('form').addEventListener('submit', function (e) {
      const startDate = new Date(document.getElementById('start_date').value);
      const endDate = new Date(document.getElementById('end_date').value);

      if (startDate > endDate) {
        e.preventDefault();
        alert('Start Date cannot be after End Date');
        return false;
      }

      if (document.getElementById('internship_certificate').files.length === 0) {
        e.preventDefault();
        alert('Please upload the Internship Certificate');
        return false;
      }
    });
  </script>
</body>
</html>
