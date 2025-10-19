<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Database connection
    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get user data for header profile
    $username       = $_SESSION['username'];
    $user_data      = null;
    $user_type      = "";
    $teacher_status = 'teacher';
    $tables         = ['student_register', 'teacher_register'];

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

    // Check teacher status if user is a teacher
    if ($user_type === 'teacher') {
        $teacher_status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
        $teacher_status_stmt = $conn->prepare($teacher_status_sql);
        $teacher_status_stmt->bind_param("s", $username);
        $teacher_status_stmt->execute();
        $teacher_status_result = $teacher_status_stmt->get_result();

        if ($teacher_status_result->num_rows > 0) {
            $status_data    = $teacher_status_result->fetch_assoc();
            $teacher_status = $status_data['status'];
        }
        $teacher_status_stmt->close();
    }

    // Only allow admin-level teachers to access bulk import
    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        $_SESSION['access_denied'] = 'Only administrators can access bulk import. Your role is: ' . ucfirst($teacher_status);
        header("Location: user_management.php");
        exit();
    }

    // Redirect students who shouldn't have access
    if ($user_type === 'student') {
        header("Location: ../student/index.php");
        exit();
    }

    // Initialize variables
    $success_message = '';
    $error_message   = '';
    $import_results  = [];
    $preview_data    = [];
    $show_preview    = false;

    // Handle file upload and processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {

            if ($_POST['action'] === 'upload' && isset($_FILES['import_file'])) {
                $file = $_FILES['import_file'];

                // Validate file upload
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = "File upload failed. Error code: " . $file['error'] . ". Please try again.";
                } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error_message = "File size too large. Maximum size is 5MB.";
                } elseif ($file['size'] == 0) {
                    $error_message = "File is empty. Please upload a valid CSV file with data.";
                } elseif ($file['size'] < 100) { // Minimum 100 bytes
                    $error_message = "File is too small (minimum 100 bytes required). Please ensure your file contains headers and at least one row of data.";
                } else {
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (! in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
                        $error_message = "Invalid file format. Please upload CSV, XLS, or XLSX files only.";
                    } else {
                        // Show Excel warning
                        if (in_array($file_extension, ['xlsx', 'xls'])) {
                            $error_message = "Excel file support requires PHPSpreadsheet library. Please convert to CSV format and try again.";
                        } else {
                            // Process the file
                            $preview_data = processImportFile($file['tmp_name'], $file_extension);

                            if (empty($preview_data)) {
                                $error_message = "No valid data found in the file. Please check:
                                <br>• File has header row with column names
                                <br>• File contains data rows
                                <br>• CSV format is correct (comma-separated values)";
                            } else {
                                $show_preview            = true;
                                $_SESSION['import_data'] = $preview_data;
                                $success_message         = "File uploaded successfully! Found " . count($preview_data) . " rows to preview.";
                            }
                        }
                    }
                }
            } elseif ($_POST['action'] === 'confirm' && isset($_SESSION['import_data'])) {
                $import_results = processImportData($_SESSION['import_data'], $_POST['import_type'], $conn);
                unset($_SESSION['import_data']);
            }
        }
    }

    // Function to process CSV/Excel files
    function processImportFile($file_path, $extension)
    {
        $data = [];

        if ($extension === 'csv') {
            if (($handle = fopen($file_path, "r")) !== false) {
                // Read the header
                $header = fgetcsv($handle, 1000, ",", '"', "\\");

                if ($header === false || empty($header)) {
                    fclose($handle);
                    return [];
                }

                // Clean header
                $header = array_map(function ($h) {
                    return trim($h, " \t\n\r\0\x0B\"'");
                }, $header);

                // Remove empty headers
                $header = array_filter($header, function ($h) {
                    return ! empty($h);
                });

                if (empty($header)) {
                    fclose($handle);
                    return [];
                }

                while (($row = fgetcsv($handle, 1000, ",", '"', "\\")) !== false) {
                    // Skip completely empty rows
                    if (empty(array_filter($row, function ($cell) {
                        return ! empty(trim($cell));
                    }))) {
                        continue;
                    }

                    // Clean row data
                    $row = array_map(function ($cell) {
                        return trim($cell, " \t\n\r\0\x0B\"'");
                    }, $row);

                    // Pad or trim row to match header count
                    if (count($row) < count($header)) {
                        $row = array_pad($row, count($header), '');
                    } elseif (count($row) > count($header)) {
                        $row = array_slice($row, 0, count($header));
                    }

                    // Create associative array
                    $data_row = array_combine($header, $row);
                    if ($data_row !== false) {
                        $data[] = $data_row;
                    }
                }
                fclose($handle);
            }
        } else {
            // For Excel files, we'll use a simple approach
            // In a real implementation, you'd use PHPSpreadsheet library
            // For now, return empty array and show error message
            return [];
        }

        return $data;
    }

    // Function to process and import data
    function processImportData($data, $import_type, $conn)
    {
        $results = [
            'success' => 0,
            'errors'  => 0,
            'details' => [],
        ];

        foreach ($data as $index => $row) {
            $row_number = $index + 2; // +2 because index starts at 0 and we skip header

            try {
                if ($import_type === 'students') {
                    $result = importStudent($row, $conn);
                } else {
                    $result = importTeacher($row, $conn);
                }

                if ($result['success']) {
                    $results['success']++;
                    $results['details'][] = "Row $row_number: Successfully imported " . $row['name'];
                } else {
                    $results['errors']++;
                    $results['details'][] = "Row $row_number: Error - " . $result['error'];
                }
            } catch (Exception $e) {
                $results['errors']++;
                $results['details'][] = "Row $row_number: Exception - " . $e->getMessage();
            }
        }

        return $results;
    }

    // Function to import a student
    function importStudent($row, $conn)
    {
        // Required fields validation
        $required_fields = ['name', 'username', 'personal_email', 'regno', 'department'];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Validate email format
        if (! filter_var($row['personal_email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email format"];
        }

        // Check for duplicate username, regno, or email
        $check_sql  = "SELECT id FROM student_register WHERE username = ? OR regno = ? OR personal_email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $row['username'], $row['regno'], $row['personal_email']);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $check_stmt->close();
            return ['success' => false, 'error' => "Username, Registration Number, or Email already exists"];
        }
        $check_stmt->close();

        // Prepare data with defaults
        $name           = $row['name'];
        $username       = $row['username'];
        $personal_email = $row['personal_email'];
        $regno          = $row['regno'];
        $department     = $row['department'];
        $year_of_join   = ! empty($row['year_of_join']) ? $row['year_of_join'] : date('Y');
        $degree         = ! empty($row['degree']) ? $row['degree'] : '';
        $dob            = ! empty($row['dob']) ? $row['dob'] : null;
        $password       = password_hash('sona123', PASSWORD_DEFAULT); // Hash the default password
        $status         = 'student';

        // Insert student with all available fields
        $insert_sql  = "INSERT INTO student_register (name, dob, username, regno, year_of_join, degree, department, personal_email, password, status, reg_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);

        $insert_stmt->bind_param("ssssssssss",
            $name,
            $dob,
            $username,
            $regno,
            $year_of_join,
            $degree,
            $department,
            $personal_email,
            $password,
            $status
        );

        if ($insert_stmt->execute()) {
            $insert_stmt->close();
            return ['success' => true];
        } else {
            $error = $insert_stmt->error;
            $insert_stmt->close();
            return ['success' => false, 'error' => $error];
        }
    }

    // Function to import a teacher
    function importTeacher($row, $conn)
    {
        // Required fields validation
        $required_fields = ['name', 'username', 'email', 'faculty_id', 'department'];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Validate email format
        if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Invalid email format"];
        }

        // Check for duplicate username, faculty_id, or email
        $check_sql  = "SELECT id FROM teacher_register WHERE username = ? OR faculty_id = ? OR email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sss", $row['username'], $row['faculty_id'], $row['email']);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $check_stmt->close();
            return ['success' => false, 'error' => "Username, Faculty ID, or Email already exists"];
        }
        $check_stmt->close();

        // Prepare data with defaults
        $name         = $row['name'];
        $username     = $row['username'];
        $email        = $row['email'];
        $faculty_id   = $row['faculty_id'];
        $department   = $row['department'];
        $year_of_join = ! empty($row['year_of_join']) ? $row['year_of_join'] : date('Y');
        $password     = password_hash('sona123', PASSWORD_DEFAULT); // Hash the default password
        $status       = 'teacher';                                  // Default status

        // Insert teacher with all available fields including password and created_at
        $insert_sql  = "INSERT INTO teacher_register (name, username, faculty_id, year_of_join, department, email, password, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        $insert_stmt->bind_param("ssssssss",
            $name,
            $username,
            $faculty_id,
            $year_of_join,
            $department,
            $email,
            $password,
            $status
        );

        if ($insert_stmt->execute()) {
            $insert_stmt->close();
            return ['success' => true];
        } else {
            $error = $insert_stmt->error;
            $insert_stmt->close();
            return ['success' => false, 'error' => $error];
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - Admin Dashboard</title>
    <link rel="stylesheet" href="./CSS/report.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .main {
            padding: 20px;
            overflow-y: auto;
            max-height: none;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .import-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .import-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6c757d;
        }

        .step.active {
            color: #0c3878;
            font-weight: 600;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .step.active .step-number {
            background: #0c3878;
            color: white;
        }

        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s ease;
        }

        .upload-area:hover {
            border-color: #0c3878;
        }

        .upload-area.dragover {
            border-color: #0c3878;
            background: #f8f9fa;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: #0c3878;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin: 10px;
        }

        .upload-btn:hover {
            background: #0a2d5f;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .preview-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .results-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .result-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .result-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .result-card.success {
            background: #d1e7dd;
            border: 1px solid #a3cfbb;
            color: #0a3622;
        }

        .result-card.error {
            background: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #721c24;
        }

        .result-details {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .alert {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1e7dd;
            border: 1px solid #a3cfbb;
            color: #0a3622;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f1aeb5;
            color: #721c24;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 5px;
        }

        .btn-primary {
            background: #0c3878;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #198754;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .template-download {
            margin: 20px 0;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid #1976d2;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img class="logo" src="./asserts/sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
            </div>
            <div class="header-title">
                <p>Event Management Dashboard</p>
            </div>
            <div class="header-profile">
                <div class="profile-info">
                    <span class="material-symbols-outlined">account_circle</span>
                    <div class="profile-details">
                        <span class="profile-name"><?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?></span>
                        <span class="profile-role"><?php echo ucfirst($user_type); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
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
                    <li class="sidebar-list-item active">
                        <span class="material-symbols-outlined">upload</span>
                        <a href="bulk_import.php">Bulk Import</a>
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

        <!-- Main Content -->
        <div class="main">
            <div class="main-content">
                <!-- Back to User Management -->
                <div style="margin-bottom: 20px;">
                    <a href="user_management.php" class="btn btn-secondary">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to User Management
                    </a>
                </div>



                <!-- Alert Messages -->
                <?php if (! empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (! empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Import Steps -->
                <div class="import-steps">
                    <div class="step                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo ! $show_preview && empty($import_results) ? 'active' : ''; ?>">
                        <div class="step-number">1</div>
                        <span>Upload File</span>
                    </div>
                    <div class="step                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo $show_preview ? 'active' : ''; ?>">
                        <div class="step-number">2</div>
                        <span>Preview & Confirm</span>
                    </div>
                    <div class="step                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo ! empty($import_results) ? 'active' : ''; ?>">
                        <div class="step-number">3</div>
                        <span>Import Results</span>
                    </div>
                </div>

                <?php if (! $show_preview && empty($import_results)): ?>
                <!-- Step 1: Upload File -->
                <div class="import-container">
                    <h3>📁 Step 1: Upload CSV File</h3>

                    <!-- Template Download -->
                    <div class="template-download">
                        <h4>📋 Download Templates</h4>
                        <p>Download sample CSV templates to ensure correct format:</p>
                        <a href="download_template.php?type=students" class="btn btn-success">
                            <span class="material-symbols-outlined">download</span>
                            Student Template
                        </a>
                        <a href="download_template.php?type=teachers" class="btn btn-success">
                            <span class="material-symbols-outlined">download</span>
                            Teacher Template
                        </a>
                    </div>

                    <!-- Export Existing Users -->
                    <div class="template-download" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                        <h4>📤 Export Existing Users</h4>
                        <p>Export current user data from the database to CSV format:</p>
                        <a href="export_users.php?type=students" class="btn btn-warning">
                            <span class="material-symbols-outlined">file_download</span>
                            Export Students
                        </a>
                        <a href="export_users.php?type=teachers" class="btn btn-warning">
                            <span class="material-symbols-outlined">file_download</span>
                            Export Teachers
                        </a>
                        <a href="export_users.php?type=all" class="btn btn-warning">
                            <span class="material-symbols-outlined">file_download</span>
                            Export All Users
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">

                        <div class="upload-area" id="uploadArea">
                            <div class="upload-content">
                                <span class="material-symbols-outlined" style="font-size: 48px; color: #6c757d;">cloud_upload</span>
                                <h4>Drag and drop your CSV file here</h4>
                                <p>or</p>
                                <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                                    Choose File
                                </button>
                                <input type="file" id="fileInput" name="import_file" class="file-input" accept=".csv,.xlsx,.xls" required>
                                <p style="margin-top: 15px; color: #6c757d; font-size: 14px;">
                                    <strong>⚠️ Please select a file before clicking "Upload and Preview"</strong><br>
                                    Supported formats: CSV, XLS, XLSX<br>
                                    Size: 100 bytes minimum, 5MB maximum
                                </p>
                            </div>
                        </div>

                        <div style="text-align: center; margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">upload</span>
                                Upload and Preview
                            </button>
                        </div>
                    </form>
                </div>

                <?php elseif ($show_preview): ?>
                <!-- Step 2: Preview and Confirm -->
                <div class="import-container">
                    <h3> Step 2: Preview Import Data</h3>
                    <p>Review the data below and select the import type:</p>

                    <form method="POST">
                        <input type="hidden" name="action" value="confirm">

                        <div style="margin: 20px 0;">
                            <label for="import_type" style="font-weight: 600; margin-right: 15px;">Import as:</label>
                            <select name="import_type" id="import_type" required style="padding: 8px 12px; border-radius: 6px; border: 2px solid #dee2e6;">
                                <option value="">Select user type</option>
                                <option value="students">Students</option>
                                <option value="teachers">Teachers</option>
                            </select>
                        </div>

                        <div style="margin: 20px 0;">
                            <strong>Preview (showing first 10 rows):</strong>
                        </div>

                        <div style="overflow-x: auto;">
                            <table class="preview-table">
                                <thead>
                                    <tr>
                                        <?php if (! empty($preview_data)): ?>
                                            <?php foreach (array_keys($preview_data[0]) as $header): ?>
                                                <th><?php echo htmlspecialchars($header); ?></th>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $preview_limit = array_slice($preview_data, 0, 10);
                                    foreach ($preview_limit as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $cell): ?>
                                                <td><?php echo htmlspecialchars($cell); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <p><strong>Total rows to import:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <?php echo count($preview_data); ?></p>

                        <div style="text-align: center; margin-top: 30px;">
                            <a href="bulk_import.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">cancel</span>
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">check_circle</span>
                                Confirm Import
                            </button>
                        </div>
                    </form>
                </div>

                <?php elseif (! empty($import_results)): ?>
                <!-- Step 3: Import Results -->
                <div class="results-container">
                    <h3> Step 3: Import Results</h3>

                    <div class="result-summary">
                        <div class="result-card success">
                            <h4><?php echo $import_results['success']; ?></h4>
                            <p>Successfully Imported</p>
                        </div>
                        <div class="result-card error">
                            <h4><?php echo $import_results['errors']; ?></h4>
                            <p>Errors</p>
                        </div>
                    </div>

                    <?php if (! empty($import_results['details'])): ?>
                    <div>
                        <h4>Detailed Results:</h4>
                        <div class="result-details">
                            <?php foreach ($import_results['details'] as $detail): ?>
                                <div style="margin: 5px 0; padding: 5px; border-left: 3px solid #dee2e6; padding-left: 10px;">
                                    <?php echo htmlspecialchars($detail); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="text-align: center; margin-top: 30px;">
                        <a href="user_management.php" class="btn btn-primary">
                            <span class="material-symbols-outlined">people</span>
                            View User Management
                        </a>
                        <a href="bulk_import.php" class="btn btn-secondary">
                            <span class="material-symbols-outlined">refresh</span>
                            Import More Users
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>

        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');

        // Drag and drop functionality
        if (uploadArea) {
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    // More reliable way to set files - create a new FileList
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    fileInput.files = dt.files;

                    updateFileDisplay(files[0]);
                }
            });
        }

        // File input change - use event delegation since element might be replaced
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'fileInput') {
                if (e.target.files.length > 0) {
                    updateFileDisplay(e.target.files[0]);
                }
            }
        });

        function updateFileDisplay(file) {
            const uploadContent = uploadArea.querySelector('.upload-content');

            // IMPORTANT: Don't replace the entire content - preserve the file input!
            // Instead, update only the visual elements
            uploadContent.innerHTML = `
                <span class="material-symbols-outlined" style="font-size: 48px; color: #28a745;">check_circle</span>
                <h4 style="color: #28a745;">${file.name} ✅</h4>
                <p>Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                <p style="color: #28a745; font-weight: bold;">File ready for upload!</p>
                <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                    Choose Different File
                </button>
                <!-- Re-add the file input since we replaced the innerHTML -->
                <input type="file" id="fileInput" name="import_file" class="file-input" accept=".csv,.xlsx,.xls" required>
            `;

            // Re-attach the file to the new input element
            const newFileInput = document.getElementById('fileInput');
            if (newFileInput && file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                newFileInput.files = dt.files;

                // Re-add the change event listener
                newFileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        updateFileDisplay(e.target.files[0]);
                    }
                });
            }
        }



        function closeSidebar() {
            // Add sidebar close functionality
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>