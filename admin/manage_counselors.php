<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Database connection
    $servername  = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname      = "event_management_system";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get user data and check admin access (same logic as user_management.php)
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

                                 // Check teacher status if user is a teacher
    $teacher_status = 'teacher'; // Default status
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

    // Only allow admin-level teachers to access counselor management
    if ($user_type === 'teacher' && $teacher_status !== 'admin') {
        $_SESSION['access_denied'] = 'Only administrators can access counselor management. Your role is: ' . ucfirst($teacher_status);
        header("Location: index.php");
        exit();
    }

    // Redirect students who shouldn't have access
    if ($user_type === 'student') {
        header("Location: ../student/index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $message      = '';
    $message_type = '';

    // Handle role change
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_role'])) {
        $teacher_id = $_POST['teacher_id'];
        $new_status = $_POST['new_status'];

        $update_sql  = "UPDATE teacher_register SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_status, $teacher_id);

        if ($update_stmt->execute()) {
            $message      = "Teacher role updated successfully to " . ucfirst($new_status) . "!";
            $message_type = 'success';
        } else {
            $message      = "Error updating role: " . $conn->error;
            $message_type = 'error';
        }
    }

    // Get all teachers with their student assignment counts
    $teachers_sql = "SELECT t.id, t.name, t.email, t.username, t.status,
                     (SELECT COUNT(*) FROM counselor_assignments ca WHERE ca.counselor_id = t.id AND ca.status = 'active') as student_count
                 FROM teacher_register t
                 ORDER BY FIELD(t.status, 'admin', 'counselor', 'active', 'inactive'), t.name";
    $teachers_result = $conn->query($teachers_sql);

    // Handle student assignment by registration number range
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_students'])) {
        $counselor_id = $_POST['counselor_id'];
        $from_regno   = trim($_POST['from_regno']);
        $to_regno     = trim($_POST['to_regno']);

        if (! empty($counselor_id) && ! empty($from_regno) && ! empty($to_regno)) {
            // Create counselor_assignments table if not exists
            $create_table = "CREATE TABLE IF NOT EXISTS counselor_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                counselor_id INT NOT NULL,
                student_regno VARCHAR(50) NOT NULL,
                assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('active','inactive') DEFAULT 'active',
                UNIQUE KEY uq_assignment (counselor_id, student_regno),
                FOREIGN KEY (counselor_id) REFERENCES teacher_register(id) ON DELETE CASCADE
            )";
            $conn->query($create_table);

            // Get students in the registration number range
            $students_sql  = "SELECT regno FROM student_register WHERE regno BETWEEN ? AND ? ORDER BY regno";
            $students_stmt = $conn->prepare($students_sql);
            $students_stmt->bind_param("ss", $from_regno, $to_regno);
            $students_stmt->execute();
            $students_result = $students_stmt->get_result();

            $assigned_count = 0;
            while ($student = $students_result->fetch_assoc()) {
                // Insert or update assignment
                $assign_sql = "INSERT INTO counselor_assignments (counselor_id, student_regno)
                              VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE
                              counselor_id = VALUES(counselor_id),
                              assigned_date = CURRENT_TIMESTAMP,
                              status = 'active'";
                $assign_stmt = $conn->prepare($assign_sql);
                $assign_stmt->bind_param("is", $counselor_id, $student['regno']);
                if ($assign_stmt->execute()) {
                    $assigned_count++;
                }
                $assign_stmt->close();
            }

            $message      = "Successfully assigned $assigned_count students (regno: $from_regno to $to_regno) to the selected counselor!";
            $message_type = 'success';
            $students_stmt->close();
        } else {
            $message      = "Please fill all fields for student assignment.";
            $message_type = 'error';
        }
    }

    // Handle removing a single student assignment
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_student'])) {
        $assignment_id = $_POST['assignment_id'];

        $delete_sql  = "DELETE FROM counselor_assignments WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $assignment_id);

        if ($delete_stmt->execute()) {
            $message      = "Student assignment removed successfully!";
            $message_type = 'success';
        } else {
            $message      = "Error removing assignment: " . $conn->error;
            $message_type = 'error';
        }
        $delete_stmt->close();
    }

    // Handle removing all students from a counselor
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_all_students'])) {
        $counselor_id = $_POST['counselor_id'];

        $delete_all_sql  = "DELETE FROM counselor_assignments WHERE counselor_id = ?";
        $delete_all_stmt = $conn->prepare($delete_all_sql);
        $delete_all_stmt->bind_param("i", $counselor_id);

        if ($delete_all_stmt->execute()) {
            $affected     = $delete_all_stmt->affected_rows;
            $message      = "Successfully removed all $affected student assignments from this counselor!";
            $message_type = 'success';
        } else {
            $message      = "Error removing assignments: " . $conn->error;
            $message_type = 'error';
        }
        $delete_all_stmt->close();
    }

    // Get statistics including student assignment counts
    $stats_sql = "SELECT
    (SELECT COUNT(*) FROM teacher_register) as total_teachers,
    (SELECT COUNT(*) FROM teacher_register WHERE status = 'counselor') as total_counselors,
    (SELECT COUNT(*) FROM counselor_assignments WHERE status = 'active') as total_assigned";
    $stats_result = $conn->query($stats_sql);
    $stats        = $stats_result ? $stats_result->fetch_assoc() : ['total_teachers' => 0, 'total_counselors' => 0, 'total_assigned' => 0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Counselors - Admin Dashboard</title>
    <link rel="stylesheet" href="./CSS/report.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Override background to white */
        body {
            background: white !important;
        }

        .main {
            padding: 20px;
            overflow-y: auto;
            max-height: none;
        }

        .main-content {
            max-width: none;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #0c3878;
            margin-bottom: 10px;
            font-size: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 16px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .message.success {
            background: #d1e7dd;
            color: #0a3622;
            border: 1px solid #a3cfbb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f1aeb5;
        }

        .info-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .info-box h3 {
            color: #0c3878;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            line-height: 1.8;
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #0c3878;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .teacher-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #0c3878;
            transition: transform 0.3s ease;
        }

        .teacher-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .teacher-card.counselor {
            border-left-color: #28a745;
        }

        .teacher-card.admin {
            border-left-color: #dc3545;
        }

        .teacher-card.inactive {
            opacity: 0.7;
            border-left-color: #6c757d;
        }

        .teacher-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .teacher-name {
            font-size: 20px;
            font-weight: 600;
            color: #0c3878;
            margin-bottom: 5px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.counselor {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.admin {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge.active {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-badge.inactive {
            background: #e2e3e5;
            color: #383d41;
        }

        .teacher-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .teacher-info strong {
            color: #333;
        }

        .teacher-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            transition: border-color 0.3s;
        }

        select:focus {
            outline: none;
            border-color: #0c3878;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #0c3878;
            color: white;
        }

        .btn-primary:hover {
            background: #0a2d5f;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #198754;
            color: white;
            margin-top: 10px;
        }

        .btn-success:hover {
            background: #157347;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
            padding: 12px 20px;
            background: #0c3878;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
        }

        .icon-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .teachers-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .assignment-form {
                grid-template-columns: 1fr !important;
                gap: 15px !important;
            }

            .assignment-form .form-group {
                margin-bottom: 0;
            }

            .assignment-btn {
                width: 100% !important;
                margin-top: 0;
                height: 48px;
            }
        }

        /* Assignment Form Styles */
        .assignment-form {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 200px;
            gap: 20px;
            align-items: end;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #1e4276;
            font-size: 14px;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: #0c3878;
            box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
        }

        .assignment-btn {
            background: #198754;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 46px;
            width: 100%;
            box-sizing: border-box;
        }

        .assignment-btn:hover {
            background: #157347;
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }

        .modal-header {
            background: #0c3878;
            color: white;
            padding: 20px 30px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close:hover {
            transform: scale(1.2);
        }

        .modal-body {
            padding: 30px;
            max-height: 500px;
            overflow-y: auto;
        }

        .student-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 3px solid #0c3878;
            transition: all 0.3s ease;
        }

        .student-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .student-info {
            flex: 1;
        }

        .student-regno {
            font-weight: 600;
            color: #0c3878;
            font-size: 16px;
        }

        .student-name {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .btn-remove {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-remove:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            margin-top: 10px;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state .material-symbols-outlined {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .btn-view-students {
            background: #17a2b8;
            color: white;
            margin-top: 10px;
        }

        .btn-view-students:hover {
            background: #138496;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .student-count-badge {
            background: #17a2b8;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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
            <div class="icon" style="padding-left: 10px;">
                <img class="logo" src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
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
                        <span class="material-symbols-outlined">school</span>
                        <a href="manage_counselors.php">Manage Counselors</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">bar_chart</span>
                        <a href="reports.php">Reports</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">account_circle</span>
                        <a href="profile.php">Profile</a>
                    </li>
                    <?php if ($user_type === 'teacher' && $teacher_status === 'teacher'): ?>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">dashboard</span>
                        <a href="../teacher/index.php">Teacher Dashboard</a>`
                    </li>
                    <?php endif; ?>
                    <?php if ($user_type === 'teacher' && $teacher_status === 'counselor'): ?>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">supervisor_account</span>
                        <a href="../teacher/assigned_students.php">Counselor Dashboard</a>
                    </li>
                    <?php endif; ?>
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
                <div class="container">


                <!-- Alert Messages -->
                <?php if (! empty($message)): ?>
                    <div class="message<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_teachers']; ?></div>
                <div class="stat-label">Total Teachers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_counselors']; ?></div>
                <div class="stat-label">Active Counselors</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_assigned']; ?></div>
                <div class="stat-label">Students Assigned</div>
            </div>

        </div>

        <!-- Student Assignment Section -->
        <div class="info-box">
            <h3>👥 Assign Students to Class Counselors</h3>
            <form method="POST" class="assignment-form">
                <div class="form-group">
                    <label for="counselor_id" class="form-label">Select Counselor:</label>
                    <select name="counselor_id" id="counselor_id" class="form-select" required>
                        <option value="">-- Choose Counselor --</option>
                        <?php
                            // Reset result pointer and get counselors
                            $counselors_sql    = "SELECT id, name FROM teacher_register WHERE status = 'counselor' ORDER BY name";
                            $counselors_result = $conn->query($counselors_sql);
                        while ($counselor = $counselors_result->fetch_assoc()): ?>
                            <option value="<?php echo $counselor['id']; ?>"><?php echo htmlspecialchars($counselor['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="from_regno" class="form-label">From Reg. No:</label>
                    <input type="text" name="from_regno" id="from_regno" class="form-input"
                           placeholder="e.g., 12345678901234" required style="margin-bottom:12px;">
                </div>
                <div class="form-group">
                    <label for="to_regno" class="form-label">To Reg. No:</label>
                    <input type="text" name="to_regno" id="to_regno" class="form-input"
                           placeholder="e.g., 12345678901250" required style="margin-bottom:12px;">
                </div>
                <div class="form-group">
                    <label class="form-label" style="visibility: hidden;">Action</label>
                    <button type="submit" name="assign_students" class="assignment-btn">
                        <span class="material-symbols-outlined">group_add</span>
                        Assign Students
                    </button>
                </div>
            </form>
            <p style="margin-top: 15px; color: #666; font-size: 14px;">
                <strong>Note:</strong> This will assign all students with registration numbers between the specified range to the selected counselor.
            </p>
        </div>

                <h2 style="color: #0c3878; margin-bottom: 20px; font-size: 24px; display: flex; align-items: center; gap: 10px;">
                    <span class="material-symbols-outlined">people</span>
                    Teachers & Counselors
                </h2>

        <div class="teachers-grid">
            <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                <div class="teacher-card<?php echo $teacher['status']; ?>">
                    <div class="teacher-header">
                        <div>
                            <div class="teacher-name">
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </div>
                        </div>
                        <span class="status-badge                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo $teacher['status']; ?>">
                            <?php
                                if ($teacher['status'] == 'counselor') {
                                    echo '🎓 COUNSELOR';
                                } elseif ($teacher['status'] == 'admin') {
                                    echo '👑 ADMIN';
                                } elseif ($teacher['status'] == 'active') {
                                    echo '✓ TEACHER';
                                } else {
                                    echo '⊘ INACTIVE';
                                }
                            ?>
                        </span>
                    </div>

                    <div class="teacher-info">
                        <strong>Email:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo htmlspecialchars($teacher['email']); ?><br>
                        <strong>Username:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo htmlspecialchars($teacher['username']); ?><br>
                        <?php if ($teacher['status'] == 'counselor'): ?>
                            <strong>Assigned Students:</strong> <span style="color: #28a745; font-weight: bold;"><?php echo $teacher['student_count']; ?> students</span>
                        <?php endif; ?>
                    </div>

                    <div class="teacher-actions">
                        <form method="POST">
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                            <select name="new_status" required>
                                <option value="">-- Change Role --</option>
                                <option value="active"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($teacher['status'] == 'active') ? 'selected' : ''; ?>>
                                    Regular Teacher
                                </option>
                                <option value="counselor"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($teacher['status'] == 'counselor') ? 'selected' : ''; ?>>
                                    Class Counselor
                                </option>
                                <option value="admin"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($teacher['status'] == 'admin') ? 'selected' : ''; ?>>
                                    Administrator
                                </option>
                            </select>
                            <button type="submit" name="change_role" class="btn btn-primary">
                                <span class="icon-text">
                                    <span class="material-symbols-outlined">update</span>
                                    Update Role
                                </span>
                            </button>
                        </form>

                        <?php if ($teacher['status'] == 'counselor' && $teacher['student_count'] > 0): ?>
                            <button type="button" class="btn btn-view-students" onclick="viewStudents(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['name'], ENT_QUOTES); ?>')">
                                <span class="icon-text">
                                    <span class="material-symbols-outlined">visibility</span>
                                    View Assigned Students (<?php echo $teacher['student_count']; ?>)
                                </span>
                            </button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove ALL student assignments from this counselor?');" style="margin-top: 10px;">
                                <input type="hidden" name="counselor_id" value="<?php echo $teacher['id']; ?>">
                                <button type="submit" name="remove_all_students" class="btn btn-danger">
                                    <span class="icon-text">
                                        <span class="material-symbols-outlined">person_remove</span>
                                        Remove All Students
                                    </span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

                <div style="text-align: center; margin-top: 30px;">
                    <a href="index.php" class="back-link">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to Admin Dashboard
                    </a>
                </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for viewing assigned students -->
    <div id="studentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Assigned Students</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="empty-state">
                    <span class="material-symbols-outlined">hourglass_empty</span>
                    <p>Loading...</p>
                </div>
            </div>
            <div class="modal-footer">
                <span class="student-count-badge" id="studentCount">0 students</span>
                <button class="btn btn-primary" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Sidebar functionality
        function navigateToProfile() {
            window.location.href = 'profile.php';
        }

        function closeSidebar() {
            // Add your sidebar close functionality here
        }

        // Modal functions
        function viewStudents(counselorId, counselorName) {
            const modal = document.getElementById('studentsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const studentCount = document.getElementById('studentCount');

            modalTitle.textContent = 'Students Assigned to ' + counselorName;
            modal.style.display = 'block';

            // Show loading state
            modalBody.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">hourglass_empty</span><p>Loading...</p></div>';

            // Fetch students via AJAX
            fetch('get_counselor_students.php?counselor_id=' + counselorId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.students.length === 0) {
                            modalBody.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">person_off</span><p>No students assigned</p></div>';
                            studentCount.textContent = '0 students';
                        } else {
                            let html = '<ul class="student-list">';
                            data.students.forEach(student => {
                                html += `
                                    <li class="student-item">
                                        <div class="student-info">
                                            <div class="student-regno">${student.regno}</div>
                                            <div class="student-name">${student.name || 'N/A'}</div>
                                        </div>
                                        <form method="POST" style="margin: 0;" onsubmit="return confirm('Remove this student assignment?');">
                                            <input type="hidden" name="assignment_id" value="${student.assignment_id}">
                                            <button type="submit" name="remove_student" class="btn-remove">
                                                <span class="material-symbols-outlined" style="font-size: 16px;">delete</span>
                                                Remove
                                            </button>
                                        </form>
                                    </li>
                                `;
                            });
                            html += '</ul>';
                            modalBody.innerHTML = html;
                            studentCount.textContent = data.students.length + ' student' + (data.students.length !== 1 ? 's' : '');
                        }
                    } else {
                        modalBody.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">error</span><p>Error loading students</p></div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = '<div class="empty-state"><span class="material-symbols-outlined">error</span><p>Error loading students</p></div>';
                });
        }

        function closeModal() {
            document.getElementById('studentsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('studentsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>

<?php
    $conn->close();
?>
