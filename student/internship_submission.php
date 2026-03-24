<?php
    // Start session
    session_start();

    // Include required classes
    require_once '../includes/FileCompressor.php';

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
    }

    // Get username from session
    $username = $_SESSION['username'];

    // Fetch student data from database
    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

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

    // Validation function for names (no numbers allowed)
    function validateNameField($name)
    {
    if (preg_match('/\d/', $name)) {
        return false; // Contains numbers
    }
    return true;
    }

    // Validation function for email domain (.com or .in)
    function validateEmailDomain($email)
    {
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (! preg_match('/\.(com|in)$/i', $email)) {
        return false; // Email must end with .com or .in
    }
    return true;
    }

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
    } elseif (strlen($company_name) < 2 || strlen($company_name) > 100) {
        $error_message = "❌ Company Name must be between 2 and 100 characters.";
    } elseif (! validateNameField($company_name)) {
        $error_message = "❌ Company Name cannot contain numbers.";
    } elseif (empty($company_website)) {
        $error_message = "❌ Company Website is required.";
    } elseif (! filter_var($company_website, FILTER_VALIDATE_URL)) {
        $error_message = "❌ Company Website must be a valid URL.";
    } elseif (empty($company_address)) {
        $error_message = "❌ Company Address is required.";
    } elseif (strlen($company_address) < 5 || strlen($company_address) > 250) {
        $error_message = "❌ Company Address must be between 5 and 250 characters.";
    } elseif (empty($supervisor_name)) {
        $error_message = "❌ Supervisor Name is required.";
    } elseif (strlen($supervisor_name) < 2 || strlen($supervisor_name) > 100) {
        $error_message = "❌ Supervisor Name must be between 2 and 100 characters.";
    } elseif (! validateNameField($supervisor_name)) {
        $error_message = "❌ Supervisor Name cannot contain numbers.";
    } elseif (empty($supervisor_email)) {
        $error_message = "❌ Supervisor Email is required.";
    } elseif (! validateEmailDomain($supervisor_email)) {
        $error_message = "❌ Supervisor Email must be valid and end with .com or .in";
    } elseif (empty($role_title)) {
        $error_message = "❌ Role/Title is required.";
    } elseif (strlen($role_title) < 2 || strlen($role_title) > 100) {
        $error_message = "❌ Role/Title must be between 2 and 100 characters.";
    } elseif (! validateNameField($role_title)) {
        $error_message = "❌ Role/Title cannot contain numbers.";
    } elseif (empty($domain)) {
        $error_message = "❌ Domain is required.";
    } elseif (empty($mode)) {
        $error_message = "❌ Mode is required.";
    } elseif (empty($start_date) || empty($end_date)) {
        $error_message = "❌ Start Date and End Date are required.";
    } elseif (! strtotime($start_date) || ! strtotime($end_date)) {
        $error_message = "❌ Invalid date format.";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error_message = "❌ Start Date cannot be after End Date.";
    } elseif ($stipend_amount < 0) {
        $error_message = "❌ Stipend Amount cannot be negative.";
    } elseif (empty($brief_report)) {
        $error_message = "❌ Brief Report/Key Learnings is required.";
    } elseif (strlen($brief_report) < 20 || strlen($brief_report) > 2000) {
        $error_message = "❌ Brief Report must be between 20 and 2000 characters.";
    } else {
        // Check for duplicate internship submission
        $check_conn = get_db_connection();
        {
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
            $conn = get_db_connection();

            {
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
  <meta name="theme-color" content="#1a408c">
  <title>Internship Submission</title>
  <link rel="stylesheet" href="student_dashboard.css" />
  <!-- Web App Manifest for Push Notifications -->
  <link rel="manifest" href="../manifest.json">

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
      --primary-color: #1e4276;
      --secondary-color: #2d5aa0;
      --accent-color: #2d5aa0;
      --light-bg: #f8f9fa;
      --border-color: #e1e8ed;
      --text-primary: #1e4276;
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
      padding: 0 15px 20px 15px;
    }

    .registration-form {
      background: white;
      border-radius: 0;
      padding: 20px;
      width: 100%;
      margin: 0;
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

    .error-message {
      color: #dc3545;
      display: none;
      font-style: normal;
      margin-top: 4px;
    }

    .error-message.show {
      display: block;
      font-size: 12px;
    }

    /* Character counter */
    .char-counter {
      font-size: 12px;
      color: #6c757d;
      margin-top: 4px;
      text-align: right;
    }
    .char-counter.near-limit { color: #f39c12; font-weight: 600; }
    .char-counter.at-limit   { color: #dc3545; font-weight: 600; }

    input:invalid {
      border-color: inherit;
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
        z-index: 1002;
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
        border-right: 1px solid #e0e0e0;
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

      .close-sidebar {
        display: block;
      }

      .main {
        padding: 50px 10px 30px 10px;
        margin: 0;
        grid-area: main;
        box-sizing: border-box;
      }

      .registration-form {
        padding: 15px;
        margin: 0;
        border-radius: 0;
        width: 100%;
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
      .main {
        padding: 30px 5px 20px 5px;
      }

      .registration-form {
        padding: 10px;
        margin: 0;
        border-radius: 0;
        width: 100%;
      }

      .form-title {
        font-size: 20px;
      }

      .form-section {
        padding: 10px;
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

    /* Notification Dropdown */
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
        <li class="nav-item">
          <a href="internship_submission.php" class="nav-link active">
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
          <a href="od_request.php" class="nav-link">
            <span class="material-symbols-outlined">person_raised_hand</span>
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
                pattern="[A-Za-z\s&.,-]*"
                required
                maxlength="100"
                oninput="validateNameField(this, 'company_name_error')"
              />
              <p class="form-field-helper error-message" id="company_name_error"></p>
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
                oninput="validateWebsite(this, 'company_website_error')"
              />
              <p class="form-field-helper error-message" id="company_website_error"></p>
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
                oninput="validateAddress(this, 'company_address_error'); updateCharCount(this, 'company_address_counter', 250)"
              />
              <p class="form-field-helper error-message" id="company_address_error"></p>
              <p class="char-counter" id="company_address_counter">0 / 250 characters</p>
            </div>

            <div class="item">
              <label for="supervisor_name">Supervisor Name <span class="required-asterisk">*</span></label>
              <input
                type="text"
                id="supervisor_name"
                name="supervisor_name"
                placeholder="Internship supervisor's name"
                value="<?php echo htmlspecialchars($form_data['supervisor_name'] ?? ''); ?>"
                pattern="[A-Za-z\s&.,-]*"
                required
                maxlength="100"
                oninput="validateNameField(this, 'supervisor_name_error')"
              />
              <p class="form-field-helper error-message" id="supervisor_name_error"></p>
            </div>

            <div class="item">
              <label for="supervisor_email">Supervisor Email <span class="required-asterisk">*</span></label>
              <input
                type="email"
                id="supervisor_email"
                name="supervisor_email"
                placeholder="supervisor@example.com"
                value="<?php echo htmlspecialchars($form_data['supervisor_email'] ?? ''); ?>"
                pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|in)$"
                required
                oninput="validateEmail(this, 'supervisor_email_error')"
              />
              <p class="form-field-helper error-message" id="supervisor_email_error">Email must end with .com or .in</p>
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
                pattern="[A-Za-z\s&.,-]*"
                required
                maxlength="100"
                oninput="validateNameField(this, 'role_title_error')"
              />
              <p class="form-field-helper error-message" id="role_title_error"></p>
            </div>

            <div class="item">
              <label for="domain">Domain <span class="required-asterisk">*</span></label>
              <select id="domain" name="domain" required>
                <option value="">Select Domain</option>
                <option value="Web Development"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo($form_data['domain'] ?? '') === 'Web Development' ? 'selected' : ''; ?>>Web Development</option>
                <option value="AI/ML"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo($form_data['domain'] ?? '') === 'AI/ML' ? 'selected' : ''; ?>>AI/ML</option>
                <option value="IoT"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo($form_data['domain'] ?? '') === 'IoT' ? 'selected' : ''; ?>>IoT</option>
                <option value="Data Science"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($form_data['domain'] ?? '') === 'Data Science' ? 'selected' : ''; ?>>Data Science</option>
                <option value="Cybersecurity"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo($form_data['domain'] ?? '') === 'Cybersecurity' ? 'selected' : ''; ?>>Cybersecurity</option>
                <option value="Cloud Computing"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              <?php echo($form_data['domain'] ?? '') === 'Cloud Computing' ? 'selected' : ''; ?>>Cloud Computing</option>
                <option value="Mobile Development"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($form_data['domain'] ?? '') === 'Mobile Development' ? 'selected' : ''; ?>>Mobile Development</option>
                <option value="Other"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo($form_data['domain'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>

            <div class="item">
              <label for="mode">Mode <span class="required-asterisk">*</span></label>
              <select id="mode" name="mode" required>
                <option value="">Select Mode</option>
                <option value="On-site"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo($form_data['mode'] ?? '') === 'On-site' ? 'selected' : ''; ?>>On-site</option>
                <option value="Remote"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($form_data['mode'] ?? '') === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                <option value="Hybrid"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($form_data['mode'] ?? '') === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
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
                onchange="validateDates()"
              />
              <p class="form-field-helper error-message" id="start_date_error"></p>
            </div>

            <div class="item">
              <label for="end_date">End Date <span class="required-asterisk">*</span></label>
              <input
                type="date"
                id="end_date"
                name="end_date"
                value="<?php echo htmlspecialchars($form_data['end_date'] ?? ''); ?>"
                required
                onchange="validateDates()"
              />
              <p class="form-field-helper error-message" id="end_date_error"></p>
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
                oninput="validateStipend(this, 'stipend_error')"
              />
              <p class="form-field-helper error-message" id="stipend_error"></p>
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
                oninput="updateCharCount(this, 'brief_report_counter', 2000)"
              ><?php echo htmlspecialchars($form_data['brief_report'] ?? ''); ?></textarea>
              <p class="form-field-helper error-message" id="brief_report_error"></p>
              <p class="char-counter" id="brief_report_counter">0 / 2000 characters (minimum 20)</p>
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

    // Validate name fields (no numbers allowed)
    function validateNameField(input, errorElementId) {
      const errorElement = document.getElementById(errorElementId);
      const value = input.value.trim();
      const hasNumbers = /\d/.test(value);
      if (hasNumbers) {
        if (errorElement) { errorElement.textContent = '❌ Numbers are not allowed in this field'; errorElement.classList.add('show'); }
      } else {
        if (errorElement) errorElement.classList.remove('show');
      }
    }

    // Validate email domain (.com or .in)
    function validateEmail(input, errorElementId) {
      const errorElement = document.getElementById(errorElementId);
      const value = input.value.trim();
      const validEmailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|in)$/i;
      if (value && !validEmailPattern.test(value)) {
        if (errorElement) { errorElement.textContent = '❌ Email must be valid and end with .com or .in'; errorElement.classList.add('show'); }
      } else {
        if (errorElement) errorElement.classList.remove('show');
      }
    }

    // Validate website URL
    function validateWebsite(input, errorId) {
      const errorEl = document.getElementById(errorId);
      const value = input.value.trim();
      const urlPattern = /^https?:\/\/.+\..+/i;
      if (value && !urlPattern.test(value)) {
        if (errorEl) { errorEl.textContent = '❌ Must be a valid URL starting with https:// or http://'; errorEl.classList.add('show'); }
      } else {
        if (errorEl) errorEl.classList.remove('show');
      }
    }

    // Validate address (alphanumeric, min 5 chars)
    function validateAddress(input, errorId) {
      const errorEl = document.getElementById(errorId);
      const value = input.value.trim();
      if (value.length > 0 && value.length < 5) {
        if (errorEl) { errorEl.textContent = '❌ Address must be at least 5 characters'; errorEl.classList.add('show'); }
      } else {
        if (errorEl) errorEl.classList.remove('show');
      }
    }

    // Validate stipend (non-negative whole number)
    function validateStipend(input, errorId) {
      const errorEl = document.getElementById(errorId);
      const value = input.value;
      if (value !== '' && (isNaN(value) || parseFloat(value) < 0 || !Number.isInteger(parseFloat(value)))) {
        if (errorEl) { errorEl.textContent = '❌ Must be a non-negative whole number (e.g. 5000)'; errorEl.classList.add('show'); }
      } else {
        if (errorEl) errorEl.classList.remove('show');
      }
    }

    // Validate date range (end >= start)
    function validateDates() {
      const startInput = document.getElementById('start_date');
      const endInput   = document.getElementById('end_date');
      const endErrorEl = document.getElementById('end_date_error');
      const startVal   = startInput.value;
      const endVal     = endInput.value;
      if (startVal && endVal) {
        if (new Date(startVal) > new Date(endVal)) {
          if (endErrorEl) { endErrorEl.textContent = '❌ End date must be on or after the start date'; endErrorEl.classList.add('show'); }
        } else {
          if (endErrorEl) endErrorEl.classList.remove('show');
        }
      }
    }

    // Live character counter
    function updateCharCount(el, counterId, maxLen) {
      const counterEl = document.getElementById(counterId);
      if (!counterEl) return;
      const len = el.value.length;
      const minLen = (el.id === 'brief_report') ? 20 : 5;
      counterEl.textContent = len + ' / ' + maxLen + ' characters' + (el.id === 'brief_report' ? ' (minimum 20)' : '');
      counterEl.className = 'char-counter';
      if (len >= maxLen)        counterEl.classList.add('at-limit');
      else if (len > maxLen * 0.9) counterEl.classList.add('near-limit');
      if (len >= minLen) {
        const errEl = document.getElementById(el.id + '_error');
        if (errEl) errEl.classList.remove('show');
      } else if (len > 0) {
        const errEl = document.getElementById(el.id + '_error');
        if (errEl) { errEl.textContent = '❌ Minimum ' + minLen + ' characters required'; errEl.classList.add('show'); }
      } else {
        const errEl = document.getElementById(el.id + '_error');
        if (errEl) errEl.classList.remove('show');
      }
    }

    // Form Validation on submit
    document.querySelector('form').addEventListener('submit', function (e) {
      let isValid = true;
      const errorMessages = [];

      // Trigger all real-time validators so fields turn red before alert
      validateNameField(document.getElementById('company_name'),    'company_name_error');
      validateWebsite  (document.getElementById('company_website'), 'company_website_error');
      validateAddress  (document.getElementById('company_address'), 'company_address_error');
      validateNameField(document.getElementById('supervisor_name'), 'supervisor_name_error');
      validateEmail    (document.getElementById('supervisor_email'),'supervisor_email_error');
      validateNameField(document.getElementById('role_title'),      'role_title_error');
      validateStipend  (document.getElementById('stipend_amount'),  'stipend_error');
      validateDates();

      // Company Name
      if (/\d/.test(document.getElementById('company_name').value.trim())) {
        isValid = false; errorMessages.push('Company Name cannot contain numbers');
      }

      // Company Website
      const websiteVal = document.getElementById('company_website').value.trim();
      if (websiteVal && !/^https?:\/\/.+\..+/i.test(websiteVal)) {
        isValid = false; errorMessages.push('Company Website must be a valid URL (https://...)');
      }

      // Company Address min length
      if (document.getElementById('company_address').value.trim().length < 5) {
        isValid = false; errorMessages.push('Company Address must be at least 5 characters');
      }

      // Supervisor Name
      if (/\d/.test(document.getElementById('supervisor_name').value.trim())) {
        isValid = false; errorMessages.push('Supervisor Name cannot contain numbers');
      }

      // Supervisor Email
      const supervisorEmail = document.getElementById('supervisor_email').value.trim();
      if (supervisorEmail && !/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|in)$/i.test(supervisorEmail)) {
        isValid = false; errorMessages.push('Supervisor Email must end with .com or .in');
      }

      // Role/Title
      if (/\d/.test(document.getElementById('role_title').value.trim())) {
        isValid = false; errorMessages.push('Role/Title cannot contain numbers');
      }

      // Stipend non-negative integer
      const stipendVal = document.getElementById('stipend_amount').value;
      if (stipendVal !== '' && (isNaN(stipendVal) || parseFloat(stipendVal) < 0 || !Number.isInteger(parseFloat(stipendVal)))) {
        isValid = false; errorMessages.push('Stipend Amount must be a non-negative whole number');
      }

      // Date range
      const startDate = new Date(document.getElementById('start_date').value);
      const endDate   = new Date(document.getElementById('end_date').value);
      if (document.getElementById('start_date').value && document.getElementById('end_date').value && startDate > endDate) {
        isValid = false; errorMessages.push('End Date must be on or after Start Date');
      }

      // Certificate upload
      if (document.getElementById('internship_certificate').files.length === 0) {
        isValid = false; errorMessages.push('Please upload the Internship Certificate');
      }

      // Brief Report length
      const briefReport = document.getElementById('brief_report').value.trim();
      if (briefReport.length < 20 || briefReport.length > 2000) {
        isValid = false; errorMessages.push('Brief Report must be between 20 and 2000 characters');
      }

      if (!isValid) {
        e.preventDefault();
        alert('Please fix the following errors:\n\n' + errorMessages.map((m, i) => (i+1) + '. ' + m).join('\n'));
        return false;
      }
    });
  </script>
  <!-- Push Notifications Manager for Median.co -->
</body>
</html>
