<?php
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . htmlspecialchars($conn->connect_error));
    }

    // Get student data
    $username     = $_SESSION['username'];
    $student_data = null;
    $message      = '';
    $message_type = '';

    // Fetch complete student data
    $sql  = "SELECT * FROM student_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit();
    }

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Handle profile information update
            $name           = trim($_POST['name']);
            $personal_email = trim($_POST['personal_email']);
            $department     = trim($_POST['department']);

            // Validate inputs
            if (empty($name) || empty($personal_email)) {
                $message      = "Name and email are required fields.";
                $message_type = "error";
            } else {
                // Update profile
                $update_sql  = "UPDATE student_register SET name=?, personal_email=?, department=? WHERE username=?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssss", $name, $personal_email, $department, $username);

                if ($update_stmt->execute()) {
                    $message      = "Profile updated successfully!";
                    $message_type = "success";

                    // Refresh student data
                    $stmt->execute();
                    $result       = $stmt->get_result();
                    $student_data = $result->fetch_assoc();
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
                $current_password_sql = "SELECT password FROM student_register WHERE username=?";
                $current_stmt         = $conn->prepare($current_password_sql);
                $current_stmt->bind_param("s", $username);
                $current_stmt->execute();
                $current_result = $current_stmt->get_result();

                if ($current_result->num_rows > 0) {
                    $current_data = $current_result->fetch_assoc();

                    if (password_verify($current_password, $current_data['password'])) {
                        // Update password
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_update_sql = "UPDATE student_register SET password=? WHERE username=?";
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
    $regno = $student_data['regno'];

    // Total events participated
    $total_events_sql = "SELECT COUNT(*) as total FROM student_event_register WHERE regno=?";
    $total_stmt       = $conn->prepare($total_events_sql);
    $total_stmt->bind_param("s", $regno);
    $total_stmt->execute();
    $total_events = $total_stmt->get_result()->fetch_assoc()['total'];

    // Events won
    $events_won_sql = "SELECT COUNT(*) as won FROM student_event_register WHERE regno=? AND prize IN ('First', 'Second', 'Third')";
    $won_stmt       = $conn->prepare($events_won_sql);
    $won_stmt->bind_param("s", $regno);
    $won_stmt->execute();
    $events_won = $won_stmt->get_result()->fetch_assoc()['won'];

    $stmt->close();
    $total_stmt->close();
    $won_stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Event Management System</title>
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
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
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(30, 66, 118, 0.3);
        }

        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
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
            color: var(--primary-color);
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
            color: var(--primary-color);
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
            border-color: var(--primary-color);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .readonly-field {
            background-color: #f8f9fa !important;
            cursor: not-allowed;
            color: #6c757d;
            border-color: #dee2e6 !important;
        }

        .readonly-field:focus {
            border-color: #dee2e6 !important;
            box-shadow: none;
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
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
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
            color: var(--primary-color);
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
                <div class="sidebar-title">Student Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                <div class="student-regno"><?php echo htmlspecialchars($student_data['regno']); ?></div>
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
                            <?php echo strtoupper(substr($student_data['name'], 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                        <div class="profile-regno">Registration No:                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo htmlspecialchars($student_data['regno']); ?></div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $total_events; ?></div>
                                <div class="stat-label">Events</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $events_won; ?></div>
                                <div class="stat-label">Prizes</div>
                            </div>
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-title">Account Information</div>
                        <div class="info-text">
                            <strong>Username:</strong>                                                                                                                                                                                                                                                                                                                                     <?php echo htmlspecialchars($student_data['username']); ?><br>
                            <strong>Joined:</strong>                                                                                                                                                                                                                                                                                                                         <?php echo date('M d, Y', strtotime($student_data['created_at'] ?? 'now')); ?><br>
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
                                    <?php echo htmlspecialchars($student_data['name']); ?>
                                </div>
                                <input type="text" name="name" class="form-input profile-edit"
                                       value="<?php echo htmlspecialchars($student_data['name']); ?>"
                                       style="display: none;" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Registration Number</label>
                                <div class="profile-display" id="regno-display">
                                    <?php echo htmlspecialchars($student_data['regno']); ?>
                                </div>
                                <input type="text" name="regno" class="form-input profile-edit readonly-field"
                                       value="<?php echo htmlspecialchars($student_data['regno']); ?>"
                                       style="display: none;" readonly title="Registration number cannot be changed">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <div class="profile-display" id="username-display">
                                    <?php echo htmlspecialchars($student_data['username']); ?>
                                </div>
                                <input type="text" name="username" class="form-input profile-edit readonly-field"
                                       value="<?php echo htmlspecialchars($student_data['username']); ?>"
                                       style="display: none;" readonly title="Username cannot be changed">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Personal Email</label>
                                <div class="profile-display" id="email-display">
                                    <?php echo htmlspecialchars($student_data['personal_email']); ?>
                                </div>
                                <input type="email" name="personal_email" class="form-input profile-edit"
                                       value="<?php echo htmlspecialchars($student_data['personal_email']); ?>"
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
                                        $dept = $student_data['department'] ?? '';
                                        echo htmlspecialchars($dept_names[$dept] ?? ($dept ?: 'Not specified'));
                                    ?>
                                </div>
                                <select name="department" class="form-select profile-edit" style="display: none;">
                                    <option value="">Select Department</option>
                                    <option value="CSE"                                                                                                                                                                                                                             <?php echo($student_data['department'] ?? '') === 'CSE' ? 'selected' : ''; ?>>Computer Science and Engineering (CSE)</option>
                                    <option value="IT"                                                                                                                                                                                                                         <?php echo($student_data['department'] ?? '') === 'IT' ? 'selected' : ''; ?>>Information Technology (IT)</option>
                                    <option value="ECE"                                                                                                                                                                                                                             <?php echo($student_data['department'] ?? '') === 'ECE' ? 'selected' : ''; ?>>Electronics and Communication Engineering (ECE)</option>
                                    <option value="EEE"                                                                                                                                                                                                                             <?php echo($student_data['department'] ?? '') === 'EEE' ? 'selected' : ''; ?>>Electrical and Electronics Engineering (EEE)</option>
                                    <option value="MECH"                                                                                                                                                                                                                                 <?php echo($student_data['department'] ?? '') === 'MECH' ? 'selected' : ''; ?>>Mechanical Engineering (MECH)</option>
                                    <option value="CIVIL"                                                                                                                                                                                                                                     <?php echo($student_data['department'] ?? '') === 'CIVIL' ? 'selected' : ''; ?>>Civil Engineering (CIVIL)</option>
                                    <option value="BME"                                                                                                                                                                                                                             <?php echo($student_data['department'] ?? '') === 'BME' ? 'selected' : ''; ?>>Biomedical Engineering (BME)</option>
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
        // Mobile sidebar functionality
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

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            const sidebar = document.getElementById('sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !headerMenuIcon.contains(event.target)) {
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

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 3000);
            }
        });

        // Edit mode toggle functions
        function toggleEditMode() {
            const form = document.getElementById('profileForm');
            const editBtn = document.getElementById('editToggleBtn');
            const title = document.getElementById('profile-title');
            const editButtons = document.getElementById('editButtons');
            const viewButtons = document.getElementById('viewButtons');

            if (form.classList.contains('edit-mode')) {
                // Cancel edit mode
                cancelEdit();
            } else {
                // Enter edit mode
                form.classList.add('edit-mode');
                editBtn.innerHTML = '<span class="material-symbols-outlined">close</span> Cancel';
                title.textContent = 'Edit Profile Information';
                editButtons.style.display = 'flex';
                viewButtons.style.display = 'none';
            }
        }

        function cancelEdit() {
            const form = document.getElementById('profileForm');
            const editBtn = document.getElementById('editToggleBtn');
            const title = document.getElementById('profile-title');
            const editButtons = document.getElementById('editButtons');
            const viewButtons = document.getElementById('viewButtons');

            // Exit edit mode
            form.classList.remove('edit-mode');
            editBtn.innerHTML = '<span class="material-symbols-outlined">edit</span> Edit';
            title.textContent = 'Profile Information';
            editButtons.style.display = 'none';
            viewButtons.style.display = 'flex';

            // Reset form values to original
            form.reset();
            // Reload the page to restore original values
            location.reload();
        }
    </script>
</body>
</html>