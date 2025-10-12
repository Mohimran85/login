<?php
    session_start();

    // Check if user is logged in as a teacher
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;
    $message      = '';
    $message_type = '';

    // Get teacher data from teacher_register table
    $sql  = "SELECT * FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        // Fallback: use student data structure for now
        $sql  = "SELECT * FROM student_register WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $teacher_data = $result->fetch_assoc();
        } else {
            header("Location: ../index.php");
            exit();
        }
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Handle profile information update
            $name       = trim($_POST['name']);
            $email      = trim($_POST['email']);
            $department = trim($_POST['department']);

            // Validate inputs
            if (empty($name) || empty($email)) {
                $message      = "Name and email are required fields.";
                $message_type = "error";
            } else {
                // Determine which table to update
                $table = isset($teacher_data['faculty_id']) ? 'teacher_register' : 'student_register';

                if ($table === 'teacher_register') {
                    $update_sql = "UPDATE teacher_register SET name=?, email=?, department=? WHERE username=?";
                } else {
                    $update_sql = "UPDATE student_register SET name=?, personal_email=?, department=? WHERE username=?";
                }

                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $name, $email, $department, $username);

                if ($update_stmt->execute()) {
                    $message      = "Profile updated successfully!";
                    $message_type = "success";

                    // Refresh teacher data
                    $stmt->execute();
                    $result       = $stmt->get_result();
                    $teacher_data = $result->fetch_assoc();
                } else {
                    $message      = "Error updating profile: " . htmlspecialchars($update_stmt->error);
                    $message_type = "error";
                }
                $update_stmt->close();
            }
        } elseif (isset($_POST['update_password'])) {
            // Handle password update
            $current_password = $_POST['current_password'];
            $new_password     = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate password inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message      = "All password fields are required.";
                $message_type = "error";
            } elseif ($new_password !== $confirm_password) {
                $message      = "New password and confirm password do not match.";
                $message_type = "error";
            } elseif (strlen($new_password) < 6) {
                $message      = "New password must be at least 6 characters long.";
                $message_type = "error";
            } else {
                // Verify current password
                $table                = isset($teacher_data['faculty_id']) ? 'teacher_register' : 'student_register';
                $current_password_sql = "SELECT password FROM $table WHERE username=?";
                $current_stmt         = $conn->prepare($current_password_sql);
                $current_stmt->bind_param("s", $username);
                $current_stmt->execute();
                $current_result = $current_stmt->get_result();

                if ($current_result->num_rows > 0) {
                    $current_data = $current_result->fetch_assoc();

                    if (password_verify($current_password, $current_data['password'])) {
                        // Update password
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_update_sql = "UPDATE $table SET password=? WHERE username=?";
                        $password_stmt       = $conn->prepare($password_update_sql);
                        $password_stmt->bind_param("ss", $hashed_new_password, $username);

                        if ($password_stmt->execute()) {
                            $message      = "Password updated successfully!";
                            $message_type = "success";
                        } else {
                            $message      = "Error updating password: " . htmlspecialchars($password_stmt->error);
                            $message_type = "error";
                        }
                        $password_stmt->close();
                    } else {
                        $message      = "Current password is incorrect.";
                        $message_type = "error";
                    }
                }
                $current_stmt->close();
            }
        }
    }

    // Get statistics for the profile
    $teacher_id = $teacher_data['faculty_id'] ?? $teacher_data['regno'] ?? '';

    // Total events registered
    $total_events_sql = "SELECT COUNT(*) as total FROM staff_event_reg WHERE staff_id=? OR name=?";
    $total_stmt       = $conn->prepare($total_events_sql);
    $total_stmt->bind_param("ss", $teacher_id, $teacher_data['name']);
    $total_stmt->execute();
    $total_events = $total_stmt->get_result()->fetch_assoc()['total'];

    // Total students managed (from student registrations)
    $total_students_sql = "SELECT COUNT(DISTINCT regno) as total FROM student_event_register";
    $students_stmt      = $conn->prepare($total_students_sql);
    $students_stmt->execute();
    $total_students = $students_stmt->get_result()->fetch_assoc()['total'];

    $stmt->close();
    $total_stmt->close();
    $students_stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            height: fit-content;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background:linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color:linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            margin-bottom: 8px;
        }

        .profile-regno {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
        }

        .profile-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: #3498db;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .readonly-field {
            background-color: #f8f9fa !important;
            cursor: not-allowed;
            color: #6c757d;
            border-color: #dee2e6 !important;
        }

        .form-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background:linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .btn-primary:hover {
            background:linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-section {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .info-title {
            font-size: 16px;
            font-weight: 600;
            color: #3498db;
            margin-bottom: 10px;
        }

        .info-text {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }

        .profile-display {
            padding: 12px 15px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            color: #495057;
            min-height: 20px;
        }

        .profile-edit {
            display: none;
        }

        .edit-mode .profile-display {
            display: none;
        }

        .edit-mode .profile-edit {
            display: block !important;
        }

        #editToggleBtn {
            font-size: 12px;
            padding: 8px 15px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-card, .profile-form {
                padding: 20px;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .form-buttons {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
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
                <img src="../asserts/images/Sona Logo.png" alt="Sona College Logo">
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Teacher Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID:                                                                                             <?php echo htmlspecialchars($teacher_data['faculty_id'] ?? $teacher_data['regno'] ?? 'N/A'); ?></div>
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
                        <a href="staff_event_reg.php" class="nav-link">
                            <span class="material-symbols-outlined">event_note</span>
                            Event Registration
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_events.php" class="nav-link">
                            <span class="material-symbols-outlined">calendar_month</span>
                            My Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link active">
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

        <!-- Main Content -->
        <div class="main">
            <?php if ($message): ?>
                <div class="message<?php echo $message_type; ?>">
                    <span class="material-symbols-outlined">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-container">
                <!-- Profile Info Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($teacher_data['name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                        <div class="profile-regno">Faculty ID:                                                               <?php echo htmlspecialchars($teacher_data['faculty_id'] ?? $teacher_data['regno'] ?? 'N/A'); ?></div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $total_events; ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $total_students; ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-title">Account Information</div>
                        <div class="info-text">
                            <strong>Username:</strong>                                                       <?php echo htmlspecialchars($teacher_data['username']); ?><br>
                            <strong>Role:</strong> <span style="color: #3498db;">Faculty Member</span><br>
                            <strong>Status:</strong> <span style="color: #28a745;">Active</span>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="profile-form">
                    <div class="form-title" style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="material-symbols-outlined">person</span>
                            <span id="profile-title">Profile Information</span>
                        </div>
                        <button type="button" id="editToggleBtn" class="btn btn-primary" onclick="toggleEditMode()">
                            <span class="material-symbols-outlined">edit</span>
                            Edit
                        </button>
                    </div>

                    <!-- Profile Information Display/Edit -->
                    <form method="POST" action="" id="profileForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <div class="profile-display" id="name-display">
                                    <?php echo htmlspecialchars($teacher_data['name']); ?>
                                </div>
                                <input type="text" name="name" class="form-input profile-edit"
                                       value="<?php echo htmlspecialchars($teacher_data['name']); ?>"
                                       style="display: none;" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Faculty ID</label>
                                <div class="profile-display" id="faculty-display">
                                    <?php echo htmlspecialchars($teacher_data['faculty_id'] ?? $teacher_data['regno'] ?? 'N/A'); ?>
                                </div>
                                <input type="text" name="faculty_id" class="form-input profile-edit readonly-field"
                                       value="<?php echo htmlspecialchars($teacher_data['faculty_id'] ?? $teacher_data['regno'] ?? 'N/A'); ?>"
                                       style="display: none;" readonly title="Faculty ID cannot be changed">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <div class="profile-display" id="username-display">
                                    <?php echo htmlspecialchars($teacher_data['username']); ?>
                                </div>
                                <input type="text" name="username" class="form-input profile-edit readonly-field"
                                       value="<?php echo htmlspecialchars($teacher_data['username']); ?>"
                                       style="display: none;" readonly title="Username cannot be changed">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <div class="profile-display" id="email-display">
                                    <?php echo htmlspecialchars($teacher_data['email'] ?? $teacher_data['personal_email'] ?? 'Not provided'); ?>
                                </div>
                                <input type="email" name="email" class="form-input profile-edit"
                                       value="<?php echo htmlspecialchars($teacher_data['email'] ?? $teacher_data['personal_email'] ?? ''); ?>"
                                       style="display: none;" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <div class="profile-display" id="department-display">
                                    <?php
                                        $dept_names = [
                                            'CSE'   => 'Computer Science and Engineering',
                                            'IT'    => 'Information Technology',
                                            'ECE'   => 'Electronics and Communication Engineering',
                                            'EEE'   => 'Electrical and Electronics Engineering',
                                            'MECH'  => 'Mechanical Engineering',
                                            'CIVIL' => 'Civil Engineering',
                                            'BME'   => 'Biomedical Engineering',
                                        ];
                                        $dept = $teacher_data['department'] ?? '';
                                        echo htmlspecialchars($dept_names[$dept] ?? ($dept ?: 'Not specified'));
                                    ?>
                                </div>
                                <select name="department" class="form-select profile-edit" style="display: none;">
                                    <option value="">Select Department</option>
                                    <option value="CSE"                                                        <?php echo($teacher_data['department'] ?? '') === 'CSE' ? 'selected' : ''; ?>>Computer Science and Engineering (CSE)</option>
                                    <option value="IT"                                                       <?php echo($teacher_data['department'] ?? '') === 'IT' ? 'selected' : ''; ?>>Information Technology (IT)</option>
                                    <option value="ECE"                                                        <?php echo($teacher_data['department'] ?? '') === 'ECE' ? 'selected' : ''; ?>>Electronics and Communication Engineering (ECE)</option>
                                    <option value="EEE"                                                        <?php echo($teacher_data['department'] ?? '') === 'EEE' ? 'selected' : ''; ?>>Electrical and Electronics Engineering (EEE)</option>
                                    <option value="MECH"                                                         <?php echo($teacher_data['department'] ?? '') === 'MECH' ? 'selected' : ''; ?>>Mechanical Engineering (MECH)</option>
                                    <option value="CIVIL"                                                          <?php echo($teacher_data['department'] ?? '') === 'CIVIL' ? 'selected' : ''; ?>>Civil Engineering (CIVIL)</option>
                                    <option value="BME"                                                        <?php echo($teacher_data['department'] ?? '') === 'BME' ? 'selected' : ''; ?>>Biomedical Engineering (BME)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-buttons" id="editButtons" style="display: none;">
                            <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                                <span class="material-symbols-outlined">close</span>
                                Cancel
                            </button>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <span class="material-symbols-outlined">save</span>
                                Save Changes
                            </button>
                        </div>

                        <div class="form-buttons" id="viewButtons">
                            <a href="index.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Dashboard
                            </a>
                        </div>
                    </form>

                    <!-- Password Update Section -->
                    <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e9ecef;">
                        <h3 class="form-title">
                            <span class="material-symbols-outlined">lock</span>
                            Change Password
                        </h3>

                        <form method="POST" action="">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" name="current_password" class="form-input"
                                           placeholder="Enter current password" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" name="new_password" class="form-input"
                                           placeholder="Enter new password (min 6 characters)" required>
                                </div>

                                <div class="form-group full-width">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" name="confirm_password" class="form-input"
                                           placeholder="Confirm new password" required>
                                </div>
                            </div>

                            <div class="form-buttons">
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <span class="material-symbols-outlined">security</span>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
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

        // Profile edit functionality
        let isEditMode = false;

        function toggleEditMode() {
            isEditMode = !isEditMode;
            const editButton = document.getElementById('editToggleBtn');
            const profileDisplays = document.querySelectorAll('.profile-display');
            const profileEdits = document.querySelectorAll('.profile-edit');
            const editButtons = document.getElementById('editButtons');
            const viewButtons = document.getElementById('viewButtons');
            const profileTitle = document.getElementById('profile-title');

            if (isEditMode) {
                // Switch to edit mode
                profileDisplays.forEach(display => display.style.display = 'none');
                profileEdits.forEach(edit => edit.style.display = 'block');
                editButtons.style.display = 'flex';
                viewButtons.style.display = 'none';

                editButton.innerHTML = '<span class="material-symbols-outlined">close</span> Cancel';
                editButton.className = 'btn btn-secondary';
                editButton.onclick = cancelEdit;

                profileTitle.textContent = 'Edit Profile Information';
            } else {
                // Switch to view mode
                profileDisplays.forEach(display => display.style.display = 'block');
                profileEdits.forEach(edit => edit.style.display = 'none');
                editButtons.style.display = 'none';
                viewButtons.style.display = 'flex';

                editButton.innerHTML = '<span class="material-symbols-outlined">edit</span> Edit';
                editButton.className = 'btn btn-primary';
                editButton.onclick = toggleEditMode;

                profileTitle.textContent = 'Profile Information';
            }
        }

        function cancelEdit() {
            // Reset form to original values
            const form = document.getElementById('profileForm');
            form.reset();

            // Switch back to view mode
            isEditMode = false;
            toggleEditMode();
        }

        // Auto-hide messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            const closeSidebarBtn = document.querySelector('.close-sidebar');
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !headerMenuIcon.contains(event.target)) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });

            // Auto-hide messages
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.style.display = 'none';
                    }, 300);
                }, 5000);
            });

            // Password confirmation validation
            const passwordForm = document.querySelector('form[action=""]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New password and confirm password do not match!');
                        return false;
                    }

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long!');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>