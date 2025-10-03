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
    if ($_POST && isset($_POST['update_participant'])) {
        $regno         = trim($_POST['regno']);
        $current_year  = $_POST['current_year'];
        $semester      = $_POST['semester'];
        $department    = $_POST['department'];
        $event_type    = $_POST['event_type'];
        $event_name    = trim($_POST['event_name']);
        $attended_date = $_POST['attended_date'];
        $organisation  = trim($_POST['organisation']);
        $prize         = $_POST['prize'];
        $prize_amount  = trim($_POST['prize_amount']);

        // Validate required fields
        if (empty($regno) || empty($event_name) || empty($attended_date)) {
            $error_message = "Registration number, event name, and date are required!";
        } else {
            // Handle file uploads
            $poster_filename      = "";
            $certificate_filename = "";

            // Get current files
            $current_sql  = "SELECT event_poster, certificates FROM student_event_register WHERE id = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("i", $participant_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_data   = $current_result->fetch_assoc();
            $current_stmt->close();

            $poster_filename      = $current_data['event_poster'];
            $certificate_filename = $current_data['certificates'];

            // Handle poster upload
            if (isset($_FILES['event_poster']) && $_FILES['event_poster']['error'] == 0) {
                $poster_temp     = $_FILES['event_poster']['tmp_name'];
                $poster_name     = $_FILES['event_poster']['name'];
                $poster_ext      = pathinfo($poster_name, PATHINFO_EXTENSION);
                $poster_filename = "poster_" . uniqid() . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($poster_name, PATHINFO_FILENAME)) . "." . $poster_ext;

                if (! move_uploaded_file($poster_temp, "../uploads/" . $poster_filename)) {
                    $error_message = "Failed to upload poster file.";
                }
            }

            // Handle certificate upload
            if (isset($_FILES['certificates']) && $_FILES['certificates']['error'] == 0) {
                $cert_temp            = $_FILES['certificates']['tmp_name'];
                $cert_name            = $_FILES['certificates']['name'];
                $cert_ext             = pathinfo($cert_name, PATHINFO_EXTENSION);
                $certificate_filename = "cert_" . uniqid() . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($cert_name, PATHINFO_FILENAME)) . "." . $cert_ext;

                if (! move_uploaded_file($cert_temp, "../uploads/" . $certificate_filename)) {
                    $error_message = "Failed to upload certificate file.";
                }
            }

            if (empty($error_message)) {
                // Update participant record
                $update_sql = "UPDATE student_event_register SET
                          regno = ?, current_year = ?, semester = ?, department = ?,
                          event_type = ?, event_name = ?, attended_date = ?, organisation = ?,
                          prize = ?, prize_amount = ?, event_poster = ?, certificates = ?
                          WHERE id = ?";

                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssssssssssssi",
                    $regno, $current_year, $semester, $department,
                    $event_type, $event_name, $attended_date, $organisation,
                    $prize, $prize_amount, $poster_filename, $certificate_filename, $participant_id);

                if ($update_stmt->execute()) {
                    $success_message = "Participant record updated successfully!";
                } else {
                    $error_message = "Error updating record: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
    }

    // Fetch participant data
    $sql = "SELECT e.*, s.name as student_name
        FROM student_event_register e
        LEFT JOIN student_register s ON e.regno = s.regno
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
    <title>Edit Participant</title>
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

        .form-grid {
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

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
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

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .form-container {
                padding: 20px;
            }

            .form-grid {
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
                <h2>Edit Participant Record</h2>

                <?php if (! empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (! empty($error_message)): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="form-container">
                    <form method="POST" enctype="multipart/form-data">

                        <!-- Student Information Section -->
                        <div class="form-section">
                            <h3>📋 Student Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="regno">Registration Number *</label>
                                    <input type="text" id="regno" name="regno" value="<?php echo htmlspecialchars($participant['regno']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="student_name">Student Name</label>
                                    <input type="text" id="student_name" value="<?php echo htmlspecialchars($participant['student_name'] ?? 'N/A'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="current_year">Current Year *</label>
                                    <select id="current_year" name="current_year" required>
                                        <option value="">Select Year</option>
                                        <option value="I"                                                                                                                                                                                                                                                                                              <?php echo($participant['current_year'] === 'I') ? 'selected' : ''; ?>>I Year</option>
                                        <option value="II"                                                                                                                                                                                                                                                                                                   <?php echo($participant['current_year'] === 'II') ? 'selected' : ''; ?>>II Year</option>
                                        <option value="III"                                                                                                                                                                                                                                                                                                        <?php echo($participant['current_year'] === 'III') ? 'selected' : ''; ?>>III Year</option>
                                        <option value="IV"                                                                                                                                                                                                                                                                                                   <?php echo($participant['current_year'] === 'IV') ? 'selected' : ''; ?>>IV Year</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <select id="semester" name="semester">
                                        <option value="">Select Semester</option>
                                        <option value="I"                                                                                                                                                                                                                                                                                              <?php echo($participant['semester'] === 'I') ? 'selected' : ''; ?>>I Semester</option>
                                        <option value="II"                                                                                                                                                                                                                                                                                                   <?php echo($participant['semester'] === 'II') ? 'selected' : ''; ?>>II Semester</option>
                                        <option value="III"                                                                                                                                                                                                                                                                                                        <?php echo($participant['semester'] === 'III') ? 'selected' : ''; ?>>III Semester</option>
                                        <option value="IV"                                                                                                                                                                                                                                                                                                   <?php echo($participant['semester'] === 'IV') ? 'selected' : ''; ?>>IV Semester</option>
                                        <option value="V"                                                                                                                                                                                                                                                                                              <?php echo($participant['semester'] === 'V') ? 'selected' : ''; ?>>V Semester</option>
                                        <option value="VI"                                                                                                                                                                                                                                                                                                   <?php echo($participant['semester'] === 'VI') ? 'selected' : ''; ?>>VI Semester</option>
                                        <option value="VII"                                                                                                                                                                                                                                                                                                        <?php echo($participant['semester'] === 'VII') ? 'selected' : ''; ?>>VII Semester</option>
                                        <option value="VIII"                                                                                                                                                                                                                                                                                                             <?php echo($participant['semester'] === 'VIII') ? 'selected' : ''; ?>>VIII Semester</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="department">Department *</label>
                                <select id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science"                                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['department'] === 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Information Technology"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo($participant['department'] === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics and Communication"                                                                                                                                                                                                                                                                                                                                                                                                                      <?php echo($participant['department'] === 'Electronics and Communication') ? 'selected' : ''; ?>>Electronics and Communication</option>
                                    <option value="Electrical and Electronics"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Electrical and Electronics') ? 'selected' : ''; ?>>Electrical and Electronics</option>
                                    <option value="Electronics"                                                                                                                                                                                                                                                                                                                            <?php echo($participant['department'] === 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Electrical"                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                                    <option value="Mechanical"                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                                    <option value="Civil"                                                                                                                                                                                                                                                                                              <?php echo($participant['department'] === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                    <option value="Automobile"                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Automobile') ? 'selected' : ''; ?>>Automobile</option>
                                    <option value="Biomedical"                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Biomedical') ? 'selected' : ''; ?>>Biomedical</option>
                                    <option value="Chemical"                                                                                                                                                                                                                                                                                                             <?php echo($participant['department'] === 'Chemical') ? 'selected' : ''; ?>>Chemical</option>
                                    <option value="Aeronautical"                                                                                                                                                                                                                                                                                                                                 <?php echo($participant['department'] === 'Aeronautical') ? 'selected' : ''; ?>>Aeronautical</option>
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
                                        <option value="Hackathon"                                                                                                                                                                                                                                                                                                                                      <?php echo($participant['event_type'] === 'Hackathon') ? 'selected' : ''; ?>>Hackathon</option>
                                        <option value="Workshop"                                                                                                                                                                                                                                                                                                                                 <?php echo($participant['event_type'] === 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                                        <option value="Seminar"                                                                                                                                                                                                                                                                                                                            <?php echo($participant['event_type'] === 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                                        <option value="Conference"                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['event_type'] === 'Conference') ? 'selected' : ''; ?>>Conference</option>
                                        <option value="Competition"                                                                                                                                                                                                                                                                                                                                                <?php echo($participant['event_type'] === 'Competition') ? 'selected' : ''; ?>>Competition</option>
                                        <option value="Training"                                                                                                                                                                                                                                                                                                                                 <?php echo($participant['event_type'] === 'Training') ? 'selected' : ''; ?>>Training</option>
                                        <option value="Webinar"                                                                                                                                                                                                                                                                                                                            <?php echo($participant['event_type'] === 'Webinar') ? 'selected' : ''; ?>>Webinar</option>
                                        <option value="Internship"                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['event_type'] === 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                        <option value="Project"                                                                                                                                                                                                                                                                                                                            <?php echo($participant['event_type'] === 'Project') ? 'selected' : ''; ?>>Project</option>
                                        <option value="Placement"                                                                                                                                                                                                                                                                                                                                      <?php echo($participant['event_type'] === 'Placement') ? 'selected' : ''; ?>>Placement</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="attended_date">Event Date *</label>
                                    <input type="date" id="attended_date" name="attended_date" value="<?php echo $participant['attended_date']; ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="event_name">Event Name *</label>
                                <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($participant['event_name']); ?>" required placeholder="Enter the full event name">
                            </div>
                            <div class="form-group">
                                <label for="organisation">Organisation/Company</label>
                                <input type="text" id="organisation" name="organisation" value="<?php echo htmlspecialchars($participant['organisation']); ?>" placeholder="Enter hosting organisation or company name">
                            </div>
                        </div>

                        <!-- Achievement Information Section -->
                        <div class="form-section">
                            <h3>🏆 Achievement Information</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="prize">Prize/Achievement</label>
                                    <select id="prize" name="prize">
                                        <option value="">No Prize</option>
                                        <option value="First"                                                                                                                                                                                                                                                                                                                  <?php echo($participant['prize'] === 'First') ? 'selected' : ''; ?>>First Prize</option>
                                        <option value="Second"                                                                                                                                                                                                                                                                                                                       <?php echo($participant['prize'] === 'Second') ? 'selected' : ''; ?>>Second Prize</option>
                                        <option value="Third"                                                                                                                                                                                                                                                                                                                  <?php echo($participant['prize'] === 'Third') ? 'selected' : ''; ?>>Third Prize</option>
                                        <option value="Participation"                                                                                                                                                                                                                                                                                                                                                          <?php echo($participant['prize'] === 'Participation') ? 'selected' : ''; ?>>Participation Certificate</option>
                                                                                                        <?php echo($participant['prize'] === 'Excellence') ? 'selected' : ''; ?>>Excellence Award</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="prize_amount">Prize Amount</label>
                                    <input type="text" id="prize_amount" name="prize_amount" value="<?php echo htmlspecialchars($participant['prize_amount']); ?>" placeholder="e.g., Rs. 5000">
                                </div>
                            </div>
                        </div>

                        <!-- File Upload Section -->
                        <div class="form-section">
                            <h3>📁 File Attachments</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="event_poster">Event Poster</label>
                                    <input type="file" id="event_poster" name="event_poster" accept=".pdf,.jpg,.jpeg,.png">
                                    <?php if (! empty($participant['event_poster'])): ?>
                                        <div class="current-files">
                                            <strong>Current Poster:</strong>
                                            <a href="download.php?id=<?php echo $participant['id']; ?>&type=poster" class="file-link" target="_blank">📄 View Poster</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="certificates">Achievement Certificate</label>
                                    <input type="file" id="certificates" name="certificates" accept=".pdf,.jpg,.jpeg,.png">
                                    <?php if (! empty($participant['certificates'])): ?>
                                        <div class="current-files">
                                            <strong>Current Certificate:</strong>
                                            <a href="download.php?id=<?php echo $participant['id']; ?>&type=certificate" class="file-link" target="_blank">🏆 View Certificate</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="participants.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Participants
                            </a>
                            <button type="submit" name="update_participant" class="btn btn-primary">
                                <span class="material-symbols-outlined">save</span>
                                Update Participant
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

        // Show/hide prize amount based on prize selection
        document.getElementById('prize').addEventListener('change', function() {
            const prizeAmount = document.getElementById('prize_amount');
            if (this.value === 'First' || this.value === 'Second' || this.value === 'Third') {
                prizeAmount.style.display = 'block';
                prizeAmount.previousElementSibling.style.display = 'block';
            } else {
                prizeAmount.style.display = 'none';
                prizeAmount.previousElementSibling.style.display = 'none';
                prizeAmount.value = '';
            }
        });

        // Trigger the prize change event on page load
        document.getElementById('prize').dispatchEvent(new Event('change'));
    </script>
</body>
</html>

<?php
    $conn->close();
?>

                            <div class="form-group">
                                <label for="student_name">Student Name</label>
                                <input type="text" id="student_name" value="<?php echo htmlspecialchars($participant['student_name'] ?? 'N/A'); ?>" readonly style="background-color: #f8f9fa;">
                            </div>

                            <div class="form-group">
                                <label for="current_year">Current Year</label>
                                <select id="current_year" name="current_year" required>
                                    <option value="I"                                                                                                                                                                                                                                                                                                                               <?php echo($participant['current_year'] === 'I') ? 'selected' : ''; ?>>I Year</option>
                                    <option value="II"                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['current_year'] === 'II') ? 'selected' : ''; ?>>II Year</option>
                                    <option value="III"                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['current_year'] === 'III') ? 'selected' : ''; ?>>III Year</option>
                                    <option value="IV"                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['current_year'] === 'IV') ? 'selected' : ''; ?>>IV Year</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <select id="semester" name="semester">
                                    <option value="">Select Semester</option>
                                    <option value="I"                                                                                                                                                                                                                                                                                                                               <?php echo($participant['semester'] === 'I') ? 'selected' : ''; ?>>I Semester</option>
                                    <option value="II"                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['semester'] === 'II') ? 'selected' : ''; ?>>II Semester</option>
                                    <option value="III"                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['semester'] === 'III') ? 'selected' : ''; ?>>III Semester</option>
                                    <option value="IV"                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['semester'] === 'IV') ? 'selected' : ''; ?>>IV Semester</option>
                                    <option value="V"                                                                                                                                                                                                                                                                                                                               <?php echo($participant['semester'] === 'V') ? 'selected' : ''; ?>>V Semester</option>
                                    <option value="VI"                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['semester'] === 'VI') ? 'selected' : ''; ?>>VI Semester</option>
                                    <option value="VII"                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['semester'] === 'VII') ? 'selected' : ''; ?>>VII Semester</option>
                                    <option value="VIII"                                                                                                                                                                                                                                                                                                                                                 <?php echo($participant['semester'] === 'VIII') ? 'selected' : ''; ?>>VIII Semester</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="department">Department</label>
                                <select id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Computer Science"                                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo($participant['department'] === 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                    <option value="Information Technology"                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo($participant['department'] === 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                                    <option value="Electronics"                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['department'] === 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                                    <option value="Mechanical"                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['department'] === 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                                    <option value="Civil"                                                                                                                                                                                                                                                                                                                                                       <?php echo($participant['department'] === 'Civil') ? 'selected' : ''; ?>>Civil</option>
                                    <option value="Electrical"                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['department'] === 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="event_type">Event Type</label>
                                <select id="event_type" name="event_type" required>
                                    <option value="">Select Event Type</option>
                                    <option value="Hackathon"                                                                                                                                                                                                                                                                                                                                                                               <?php echo($participant['event_type'] === 'Hackathon') ? 'selected' : ''; ?>>Hackathon</option>
                                    <option value="Workshop"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($participant['event_type'] === 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                                    <option value="Seminar"                                                                                                                                                                                                                                                                                                                                                                   <?php echo($participant['event_type'] === 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                                    <option value="Conference"                                                                                                                                                                                                                                                                                                                                                                                     <?php echo($participant['event_type'] === 'Conference') ? 'selected' : ''; ?>>Conference</option>
                                    <option value="Competition"                                                                                                                                                                                                                                                                                                                                                                                           <?php echo($participant['event_type'] === 'Competition') ? 'selected' : ''; ?>>Competition</option>
                                    <option value="Training"                                                                                                                                                                                                                                                                                                                                                                         <?php echo($participant['event_type'] === 'Training') ? 'selected' : ''; ?>>Training</option>
                                    <option value="Webinar"                                                                                                                                                                                                                                                                                                                                                                   <?php echo($participant['event_type'] === 'Webinar') ? 'selected' : ''; ?>>Webinar</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="event_name">Event Name *</label>
                                <input type="text" id="event_name" name="event_name" value="<?php echo htmlspecialchars($participant['event_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="attended_date">Event Date *</label>
                                <input type="date" id="attended_date" name="attended_date" value="<?php echo $participant['attended_date']; ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="organisation">Organisation</label>
                                <input type="text" id="organisation" name="organisation" value="<?php echo htmlspecialchars($participant['organisation']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="prize">Prize</label>
                                <select id="prize" name="prize">
                                    <option value="">No Prize</option>
                                    <option value="First"                                                                                                                                                                                                                                                                                                                                                       <?php echo($participant['prize'] === 'First') ? 'selected' : ''; ?>>First Prize</option>
                                    <option value="Second"                                                                                                                                                                                                                                                                                                                                                             <?php echo($participant['prize'] === 'Second') ? 'selected' : ''; ?>>Second Prize</option>
                                    <option value="Third"                                                                                                                                                                                                                                                                                                                                                       <?php echo($participant['prize'] === 'Third') ? 'selected' : ''; ?>>Third Prize</option>
                                    <option value="Participation"                                                                                                                                                                                                                                                                                                                                                                                                       <?php echo($participant['prize'] === 'Participation') ? 'selected' : ''; ?>>Participation Certificate</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="prize_amount">Prize Amount</label>
                                <input type="text" id="prize_amount" name="prize_amount" value="<?php echo htmlspecialchars($participant['prize_amount']); ?>" placeholder="e.g., Rs. 5000">
                            </div>

                            <div class="form-group">
                                <label for="event_poster">Event Poster</label>
                                <input type="file" id="event_poster" name="event_poster" accept=".pdf,.jpg,.jpeg,.png">
                                <?php if (! empty($participant['event_poster'])): ?>
                                    <div class="current-files">
                                        <strong>Current:</strong>
                                        <a href="download.php?id=<?php echo $participant['id']; ?>&type=poster" class="file-link" target="_blank">📄 View Current Poster</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label for="certificates">Certificate</label>
                                <input type="file" id="certificates" name="certificates" accept=".pdf,.jpg,.jpeg,.png">
                                <?php if (! empty($participant['certificates'])): ?>
                                    <div class="current-files">
                                        <strong>Current:</strong>
                                        <a href="download.php?id=<?php echo $participant['id']; ?>&type=certificate" class="file-link" target="_blank">🏆 View Current Certificate</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="participants.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Participants
                            </a>
                            <button type="submit" name="update_participant" class="btn btn-primary">
                                <span class="material-symbols-outlined">save</span>
                                Update Participant
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

        // Show/hide prize amount based on prize selection
        document.getElementById('prize').addEventListener('change', function() {
            const prizeAmount = document.getElementById('prize_amount');
            if (this.value === 'First' || this.value === 'Second' || this.value === 'Third') {
                prizeAmount.style.display = 'block';
                prizeAmount.previousElementSibling.style.display = 'block';
            } else {
                prizeAmount.style.display = 'none';
                prizeAmount.previousElementSibling.style.display = 'none';
                prizeAmount.value = '';
            }
        });

        // Trigger the prize change event on page load
        document.getElementById('prize').dispatchEvent(new Event('change'));
    </script>
</body>
</html>

<?php
$conn->close();
?>