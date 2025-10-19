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

    $success_message = "";
    $error_message   = "";

    // Get participant ID from URL
    $participant_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($participant_id <= 0) {
        header("Location: participants.php");
        exit();
    }

    // Handle form submission
    if ($_POST && isset($_POST['update_teacher_participant'])) {
        $staff_id      = trim($_POST['staff_id']);
        $teacher_name  = trim($_POST['teacher_name']);
        $department    = $_POST['department'];
        $event_type    = $_POST['event_type'];
        $topic         = trim($_POST['topic']);
        $event_date    = $_POST['event_date'];
        $academic_year = $_POST['academic_year'];
        $no_of_dates   = trim($_POST['no_of_dates']);
        $from_date     = $_POST['from_date'];
        $to_date       = $_POST['to_date'];
        $organisation  = trim($_POST['organisation']);
        $sponsors      = trim($_POST['sponsors']);

        // Validate required fields
        if (empty($staff_id) || empty($teacher_name) || empty($topic) || empty($event_date)) {
            $error_message = "Staff ID, teacher name, topic, and event date are required!";
        } else {
            // Handle file upload
            $certificate_filename = "";

            // Get current certificate
            $current_sql  = "SELECT certificate_path FROM staff_event_reg WHERE id = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("i", $participant_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_data   = $current_result->fetch_assoc();
            $current_stmt->close();

            $certificate_filename = $current_data['certificate_path'];

            // Handle certificate upload
            if (isset($_FILES['certificate_path']) && $_FILES['certificate_path']['error'] == 0) {
                $cert_temp            = $_FILES['certificate_path']['tmp_name'];
                $cert_name            = $_FILES['certificate_path']['name'];
                $cert_ext             = pathinfo($cert_name, PATHINFO_EXTENSION);
                $certificate_filename = "teacher_cert_" . uniqid() . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($cert_name, PATHINFO_FILENAME)) . "." . $cert_ext;

                if (! move_uploaded_file($cert_temp, "../uploads/" . $certificate_filename)) {
                    $error_message = "Failed to upload certificate file.";
                }
            }

            if (empty($error_message)) {
                // Update teacher participant record
                $update_sql = "UPDATE staff_event_reg SET
                          staff_id = ?, name = ?, department = ?, event_type = ?,
                          topic = ?, event_date = ?, academic_year = ?, no_of_dates = ?,
                          from_date = ?, to_date = ?, organisation = ?, sponsors = ?, certificate_path = ?
                          WHERE id = ?";

                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssssssssssi",
                    $staff_id, $teacher_name, $department, $event_type,
                    $topic, $event_date, $academic_year, $no_of_dates,
                    $from_date, $to_date, $organisation, $sponsors, $certificate_filename, $participant_id);

                if ($update_stmt->execute()) {
                    $success_message = "Teacher participant record updated successfully!";
                } else {
                    $error_message = "Error updating record: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
    }

    // Fetch teacher participant data
    $sql = "SELECT e.*, t.name as registered_teacher_name
        FROM staff_event_reg e
        LEFT JOIN teacher_register t ON e.staff_id = t.faculty_id
        WHERE e.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $participant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: participants.php");
        exit();
    }

    $participant = $result->fetch_assoc();
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Teacher Participant</title>
    <link rel="stylesheet" href="./CSS/report.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        .grid-container {
            min-height: 100vh !important;
            height: auto !important;
        }

        .main {
            padding-top: 100px !important;
            padding-bottom: 20px !important;
            overflow-y: auto !important;
            max-height: none !important;
        }

        .main-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #0c3878;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            line-height: 1.4;
            height: 45px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            height: auto;
        }

        .form-group select {
            background-color: white;
            cursor: pointer;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0c3878;
            box-shadow: 0 0 0 2px rgba(12, 56, 120, 0.1);
        }

        .form-group input[readonly] {
            background-color: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #0c3878;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .current-files {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
        }

        .file-link {
            display: inline-block;
            margin-right: 10px;
            padding: 5px 10px;
            background-color: #0c3878;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }

        .file-link:hover {
            background-color: #0a2d5f;
        }

        .date-range-note {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 12px;
            color: #0066cc;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .form-container {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .form-actions {
                justify-content: stretch;
                flex-direction: column;
            }

            .btn {
                flex: 1;
                justify-content: center;
                padding: 12px 20px;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 15px;
            }

            .form-group input,
            .form-group select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <div class="header">
            <div class="menu-icon" onclick="openSidebar()">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="header-logo">
                <img class="logo" src="./asserts/sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
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
                    <span class="material-symbols-outlined" onclick="closeSidebar()">close</span>
                </div>
                <ul class="sidebar-list">
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">dashboard</span>
                        <a href="index.php">Home</a>
                    </li>
                    <li class="sidebar-list-item active">
                        <span class="material-symbols-outlined">people</span>
                        <a href="participants.php">Participants</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">manage_accounts</span>
                        <a href="user_management.php">User Management</a>
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

        <div class="main">
            <div class="main-content">
                <h2>Edit Teacher Participant Record</h2>

                <?php if (! empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (! empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">

                        <!-- Teacher Information Section -->
                        <div class="form-section">
                            <h3>👨‍🏫 Teacher Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="staff_id">Staff ID *</label>
                                    <input type="text" id="staff_id" name="staff_id" value="<?php echo htmlspecialchars($participant['staff_id']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="teacher_name">Teacher Name *</label>
                                    <input type="text" id="teacher_name" name="teacher_name" value="<?php echo htmlspecialchars($participant['name']); ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="department">Department *</label>
                                <select id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science"                                                                     <?php echo($participant['department'] === 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Information Technology"                                                                           <?php echo($participant['department'] === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics and Communication"                                                                                  <?php echo($participant['department'] === 'Electronics and Communication') ? 'selected' : ''; ?>>Electronics and Communication</option>
                                    <option value="Electrical and Electronics"                                                                               <?php echo($participant['department'] === 'Electrical and Electronics') ? 'selected' : ''; ?>>Electrical and Electronics</option>
                                    <option value="Electronics"                                                                <?php echo($participant['department'] === 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Electrical"                                                               <?php echo($participant['department'] === 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                                    <option value="Mechanical"                                                               <?php echo($participant['department'] === 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                                    <option value="Civil"                                                          <?php echo($participant['department'] === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                    <option value="Automobile"                                                               <?php echo($participant['department'] === 'Automobile') ? 'selected' : ''; ?>>Automobile</option>
                                    <option value="Biomedical"                                                               <?php echo($participant['department'] === 'Biomedical') ? 'selected' : ''; ?>>Biomedical</option>
                                    <option value="Chemical"                                                             <?php echo($participant['department'] === 'Chemical') ? 'selected' : ''; ?>>Chemical</option>
                                    <option value="Aeronautical"                                                                 <?php echo($participant['department'] === 'Aeronautical') ? 'selected' : ''; ?>>Aeronautical</option>
                                    <option value="Mathematics"                                                                <?php echo($participant['department'] === 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                                    <option value="Physics"                                                            <?php echo($participant['department'] === 'Physics') ? 'selected' : ''; ?>>Physics</option>
                                    <option value="Chemistry"                                                              <?php echo($participant['department'] === 'Chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                                    <option value="English"                                                            <?php echo($participant['department'] === 'English') ? 'selected' : ''; ?>>English</option>
                                    <option value="Management Studies"                                                                       <?php echo($participant['department'] === 'Management Studies') ? 'selected' : ''; ?>>Management Studies</option>
                                </select>
                            </div>
                        </div>

                        <!-- Event Information Section -->
                        <div class="form-section">
                            <h3>🎯 Event Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="event_type">Event Type *</label>
                                    <select id="event_type" name="event_type" required>
                                        <option value="">Select Event Type</option>
                                        <option value="Conference"                                                                   <?php echo($participant['event_type'] === 'Conference') ? 'selected' : ''; ?>>Conference</option>
                                        <option value="Workshop"                                                                 <?php echo($participant['event_type'] === 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                                        <option value="Seminar"                                                                <?php echo($participant['event_type'] === 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                                        <option value="Training"                                                                 <?php echo($participant['event_type'] === 'Training') ? 'selected' : ''; ?>>Training</option>
                                        <option value="Webinar"                                                                <?php echo($participant['event_type'] === 'Webinar') ? 'selected' : ''; ?>>Webinar</option>
                                        <option value="Symposium"                                                                  <?php echo($participant['event_type'] === 'Symposium') ? 'selected' : ''; ?>>Symposium</option>
                                        <option value="Faculty Development Program"                                                                                    <?php echo($participant['event_type'] === 'Faculty Development Program') ? 'selected' : ''; ?>>Faculty Development Program</option>
                                        <option value="Research Paper Presentation"                                                                                    <?php echo($participant['event_type'] === 'Research Paper Presentation') ? 'selected' : ''; ?>>Research Paper Presentation</option>
                                        <option value="Guest Lecture"                                                                      <?php echo($participant['event_type'] === 'Guest Lecture') ? 'selected' : ''; ?>>Guest Lecture</option>
                                        <option value="Industrial Visit"                                                                         <?php echo($participant['event_type'] === 'Industrial Visit') ? 'selected' : ''; ?>>Industrial Visit</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="event_date">Event Date *</label>
                                    <input type="date" id="event_date" name="event_date" value="<?php echo $participant['event_date']; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="topic">Topic/Event Title *</label>
                                <input type="text" id="topic" name="topic" value="<?php echo htmlspecialchars($participant['topic']); ?>" required placeholder="Enter the event topic or title">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="academic_year">Academic Year</label>
                                    <select id="academic_year" name="academic_year">
                                        <option value="">Select Academic Year</option>
                                        <option value="2023-2024"                                                                  <?php echo($participant['academic_year'] === '2023-2024') ? 'selected' : ''; ?>>2023-2024</option>
                                        <option value="2024-2025"                                                                  <?php echo($participant['academic_year'] === '2024-2025') ? 'selected' : ''; ?>>2024-2025</option>
                                        <option value="2025-2026"                                                                  <?php echo($participant['academic_year'] === '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
                                        <option value="2026-2027"                                                                  <?php echo($participant['academic_year'] === '2026-2027') ? 'selected' : ''; ?>>2026-2027</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="organisation">Organisation/Institution</label>
                                    <input type="text" id="organisation" name="organisation" value="<?php echo htmlspecialchars($participant['organisation']); ?>" placeholder="Enter hosting organisation">
                                </div>
                            </div>
                        </div>

                        <!-- Event Duration Section -->
                        <div class="form-section">
                            <h3>📅 Event Duration</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="no_of_dates">Number of Days</label>
                                    <input type="number" id="no_of_dates" name="no_of_dates" value="<?php echo htmlspecialchars($participant['no_of_dates']); ?>" min="1" placeholder="e.g., 1, 3, 5">
                                </div>
                                <div class="form-group">
                                    <label for="sponsors">Sponsors</label>
                                    <input type="text" id="sponsors" name="sponsors" value="<?php echo htmlspecialchars($participant['sponsors']); ?>" placeholder="Enter sponsor names">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="from_date">From Date</label>
                                    <input type="date" id="from_date" name="from_date" value="<?php echo $participant['from_date']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="to_date">To Date</label>
                                    <input type="date" id="to_date" name="to_date" value="<?php echo $participant['to_date']; ?>">
                                </div>
                            </div>
                            <div class="date-range-note">
                                <strong>Note:</strong> From Date and To Date are optional. Use them only for multi-day events. For single-day events, the Event Date above is sufficient.
                            </div>
                        </div>

                        <!-- File Upload Section -->
                        <div class="form-section">
                            <h3>📁 Certificate</h3>
                            <div class="form-group">
                                <label for="certificate_path">Event Certificate</label>
                                <input type="file" id="certificate_path" name="certificate_path" accept=".pdf,.jpg,.jpeg,.png">
                                <?php if (! empty($participant['certificate_path'])): ?>
                                    <div class="current-files">
                                        <strong>Current Certificate:</strong>
                                        <a href="download.php?id=<?php echo $participant['id']; ?>&type=teacher_certificate&participant_type=teacher" class="file-link" target="_blank">🏆 View Certificate</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="participants.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Participants
                            </a>
                            <button type="submit" name="update_teacher_participant" class="btn btn-primary">
                                <span class="material-symbols-outlined">save</span>
                                Update Teacher Record
                            </button>
                        </div>
                    </form>
                </div>
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const staffId = document.getElementById('staff_id').value.trim();
            const teacherName = document.getElementById('teacher_name').value.trim();
            const topic = document.getElementById('topic').value.trim();
            const eventDate = document.getElementById('event_date').value;
            const department = document.getElementById('department').value;
            const eventType = document.getElementById('event_type').value;

            if (!staffId) {
                alert('Staff ID is required!');
                e.preventDefault();
                document.getElementById('staff_id').focus();
                return false;
            }

            if (!teacherName) {
                alert('Teacher name is required!');
                e.preventDefault();
                document.getElementById('teacher_name').focus();
                return false;
            }

            if (!topic) {
                alert('Topic/Event title is required!');
                e.preventDefault();
                document.getElementById('topic').focus();
                return false;
            }

            if (!eventDate) {
                alert('Event date is required!');
                e.preventDefault();
                document.getElementById('event_date').focus();
                return false;
            }

            if (!department) {
                alert('Department is required!');
                e.preventDefault();
                document.getElementById('department').focus();
                return false;
            }

            if (!eventType) {
                alert('Event type is required!');
                e.preventDefault();
                document.getElementById('event_type').focus();
                return false;
            }

            return true;
        });

        // Date validation
        document.getElementById('from_date').addEventListener('change', function() {
            const fromDate = this.value;
            const toDateInput = document.getElementById('to_date');
            if (fromDate) {
                toDateInput.min = fromDate;
            }
        });

        document.getElementById('to_date').addEventListener('change', function() {
            const toDate = this.value;
            const fromDateInput = document.getElementById('from_date');
            if (toDate) {
                fromDateInput.max = toDate;
            }
        });

        // File upload validation
        const certInput = document.getElementById('certificate_path');

        function validateFileSize(input, maxSizeMB = 5) {
            if (input.files && input.files[0]) {
                const fileSize = input.files[0].size / 1024 / 1024; // Convert to MB
                if (fileSize > maxSizeMB) {
                    alert(`File size must be less than ${maxSizeMB}MB`);
                    input.value = '';
                    return false;
                }
            }
            return true;
        }

        certInput.addEventListener('change', function() {
            validateFileSize(this);
        });

        // Auto-populate number of dates based on date range
        function calculateDays() {
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            const noOfDatesInput = document.getElementById('no_of_dates');

            if (fromDate && toDate) {
                const from = new Date(fromDate);
                const to = new Date(toDate);
                const timeDiff = to.getTime() - from.getTime();
                const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // +1 to include both start and end dates

                if (dayDiff > 0) {
                    noOfDatesInput.value = dayDiff;
                }
            }
        }

        document.getElementById('from_date').addEventListener('change', calculateDays);
        document.getElementById('to_date').addEventListener('change', calculateDays);
    </script>
</body>
</html>

<?php
$conn->close();
?>