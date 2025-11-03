<?php
    session_start();

    // Check if user is logged in as a student
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get student data
    $username     = $_SESSION['username'];
    $student_data = null;

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

    // Check if student has an assigned counselor
    $counselor_info = null;
    $counselor_sql  = "SELECT tr.name as counselor_name, tr.email as counselor_email,
                            ca.assigned_date, tr.faculty_id as counselor_id, tr.id as teacher_id
                     FROM counselor_assignments ca
                     JOIN teacher_register tr ON ca.counselor_id = tr.id
                     WHERE ca.student_regno = ? AND ca.status = 'active'
                     ORDER BY ca.assigned_date DESC
                     LIMIT 1";
    $counselor_stmt = $conn->prepare($counselor_sql);
    $counselor_stmt->bind_param("s", $student_data['regno']);
    $counselor_stmt->execute();
    $counselor_result = $counselor_stmt->get_result();

    if ($counselor_result->num_rows > 0) {
        $counselor_info = $counselor_result->fetch_assoc();
    }
    $counselor_stmt->close();

    $message      = '';
    $message_type = '';

    // Check for success message from session
    if (isset($_SESSION['od_success']) && $_SESSION['od_success'] === true) {
        $message      = "OD request submitted successfully! Your request is now pending approval from your class counselor.";
        $message_type = 'success';
        unset($_SESSION['od_success']); // Remove the session variable so it doesn't show again
    }

    // Handle OD request submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_od_request'])) {
        if (! $counselor_info) {
            $message      = "You don't have an assigned class counselor. Please contact the administration.";
            $message_type = 'error';
        } else {
            // Create OD requests table if not exists
            $create_table = "CREATE TABLE IF NOT EXISTS od_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_regno VARCHAR(50) NOT NULL,
                counselor_id INT NOT NULL,
                event_name VARCHAR(255) NOT NULL,
                event_description TEXT NOT NULL,
                event_location VARCHAR(255) NOT NULL,
                event_date DATE NOT NULL,
                event_time TIME NOT NULL,
                event_days VARCHAR(20) NOT NULL,
                event_poster VARCHAR(255) NULL,
                reason TEXT NOT NULL,
                request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                counselor_remarks TEXT,
                response_date TIMESTAMP NULL,
                FOREIGN KEY (counselor_id) REFERENCES teacher_register(id) ON DELETE CASCADE
            )";
            $conn->query($create_table);

            // Insert OD request
            $event_name        = trim($_POST['event_name']);
            $event_description = trim($_POST['event_description']);
            $event_location    = trim($_POST['event_location']);
            $event_date        = $_POST['event_date'];
            $event_time        = $_POST['event_time'];
            $event_days        = $_POST['event_days'];
            $reason            = trim($_POST['reason']);

            // Handle file upload
            $poster_filename = null;
            if (isset($_FILES['event_poster']) && $_FILES['event_poster']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/posters/';

                // Create directory if it doesn't exist
                if (! is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_info      = pathinfo($_FILES['event_poster']['name']);
                $file_extension = strtolower($file_info['extension']);

                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                if (in_array($file_extension, $allowed_types)) {
                    // Validate file size (5MB max)
                    if ($_FILES['event_poster']['size'] <= 5 * 1024 * 1024) {
                        $poster_filename = 'poster_' . $student_data['regno'] . '_' . time() . '.' . $file_extension;
                        $upload_path     = $upload_dir . $poster_filename;

                        if (! move_uploaded_file($_FILES['event_poster']['tmp_name'], $upload_path)) {
                            $message         = "Error uploading poster file.";
                            $message_type    = 'error';
                            $poster_filename = null;
                        }
                    } else {
                        $message      = "Poster file size must be less than 5MB.";
                        $message_type = 'error';
                    }
                } else {
                    $message      = "Please upload a valid image file (JPG, PNG) or PDF.";
                    $message_type = 'error';
                }
            }

            // Only proceed with database insert if no file upload errors
            if (empty($message)) {
                $insert_sql  = "INSERT INTO od_requests (student_regno, counselor_id, event_name, event_description, event_location, event_date, event_time, event_days, event_poster, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sissssssss", $student_data['regno'], $counselor_info['teacher_id'], $event_name, $event_description, $event_location, $event_date, $event_time, $event_days, $poster_filename, $reason);

                if ($insert_stmt->execute()) {
                    $_SESSION['od_success'] = true;
                    $insert_stmt->close();
                    $conn->close();
                    header("Location: od_request.php");
                    exit();
                } else {
                    $message      = "Error submitting OD request: " . $conn->error;
                    $message_type = 'error';
                }
                $insert_stmt->close();
            }
        }
    }

    // Get student's OD requests
    $od_requests_sql  = "SELECT * FROM od_requests WHERE student_regno = ? ORDER BY request_date DESC";
    $od_requests_stmt = $conn->prepare($od_requests_sql);
    $od_requests_stmt->bind_param("s", $student_data['regno']);
    $od_requests_stmt->execute();
    $od_requests_result = $od_requests_stmt->get_result();
    $od_requests_stmt->close();

    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Request - Event Management System</title>
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        .od-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .od-form-card, .od-status-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .card-title {
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

        .form-input[type="file"] {
            padding: 8px 12px;
            background: #f8f9fa;
            cursor: pointer;
        }

        .form-input[type="file"]:hover {
            background: #e9ecef;
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
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
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 2px solid #28a745;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
            animation: slideInSuccess 0.5s ease-out;
        }

        @keyframes slideInSuccess {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .counselor-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8ff 100%);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #28a745;
            margin-bottom: 25px;
        }

        .counselor-info.no-counselor {
            background: linear-gradient(135deg, #fff3cd 0%, #f8f9fa 100%);
            border-left-color: #ffc107;
        }

        .counselor-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #28a745;
            margin-bottom: 10px;
        }

        .counselor-info.no-counselor .counselor-title {
            color: #856404;
        }

        .counselor-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }

        .od-request-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 4px solid #0c3878;
        }

        .od-request-item.pending {
            border-left-color: #ffc107;
        }

        .od-request-item.approved {
            border-left-color: #28a745;
        }

        .od-request-item.rejected {
            border-left-color: #dc3545;
        }

        .od-request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .od-event-name {
            font-size: 16px;
            font-weight: 600;
            color: #0c3878;
        }

        .od-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .od-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .od-status.approved {
            background: #d4edda;
            color: #155724;
        }

        .od-status.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .od-details {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .od-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .od-form-card, .od-status-card {
                padding: 20px;
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
                        <a href="od_request.php" class="nav-link active">
                            <span class="material-symbols-outlined">request_page</span>
                            OD Request
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

            <div class="od-container">
                <!-- OD Request Form -->
                <div class="od-form-card">
                    <div class="card-title">
                        <span class="material-symbols-outlined">request_page</span>
                        Request OD for Event Participation
                    </div>

                    <!-- Counselor Information -->
                    <?php if ($counselor_info): ?>
                    <div class="counselor-info">
                        <div class="counselor-title">
                            <span class="material-symbols-outlined">supervisor_account</span>
                            Your Class Counselor
                        </div>
                        <div class="counselor-name"><?php echo htmlspecialchars($counselor_info['counselor_name']); ?></div>
                        <div style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                            ID:                                                                                              <?php echo htmlspecialchars($counselor_info['counselor_id']); ?> |
                            <?php echo htmlspecialchars($counselor_info['counselor_email']); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="counselor-info no-counselor">
                        <div class="counselor-title">
                            <span class="material-symbols-outlined">warning</span>
                            No Class Counselor Assigned
                        </div>
                        <p style="margin: 0; color: #856404;">Please contact the administration to get a class counselor assigned.</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($counselor_info): ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Event Name *</label>
                                <input type="text" name="event_name" class="form-input" required
                                       placeholder="Enter event name">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Location *</label>
                                <input type="text" name="event_location" class="form-input" required
                                       placeholder="Enter event venue/location">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Date *</label>
                                <input type="date" name="event_date" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Event Time *</label>
                                <input type="time" name="event_time" class="form-input" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Number of Days *</label>
                                <select name="event_days" class="form-select" required>
                                    <option value="">Select number of days</option>
                                    <option value="1">1 Day</option>
                                    <option value="2">2 Days</option>
                                    <option value="3">3 Days</option>
                                    <option value="4">4 Days</option>
                                    <option value="5">5 Days</option>
                                    <option value="6">6 Days</option>
                                    <option value="7">7 Days</option>
                                    <option value="other">More than 7 days</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Event Description *</label>
                                <textarea name="event_description" class="form-textarea" required
                                          placeholder="Describe what the event is about, activities involved, etc."></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Event Poster</label>
                                <input type="file" name="event_poster" class="form-input" accept="image/*,.pdf">
                                <small style="color: #6c757d; font-size: 12px; margin-top: 5px; display: block;">
                                    Upload event poster/flyer (JPG, PNG, PDF - Max 5MB). This helps your counselor understand the event better.
                                </small>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">Reason for OD *</label>
                                <textarea name="reason" class="form-textarea" required
                                          placeholder="Explain why you need OD for this event participation"></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <button type="submit" name="submit_od_request" class="btn btn-primary">
                                <span class="material-symbols-outlined">send</span>
                                Submit OD Request
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">arrow_back</span>
                                Back to Dashboard
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- OD Request Status -->
                <div class="od-status-card">
                    <div class="card-title">
                        <span class="material-symbols-outlined">history</span>
                        My OD Requests
                    </div>

                    <?php if ($od_requests_result->num_rows > 0): ?>
                        <?php while ($request = $od_requests_result->fetch_assoc()): ?>
                        <div class="od-request-item<?php echo $request['status']; ?>">
                            <div class="od-request-header">
                                <div class="od-event-name"><?php echo htmlspecialchars($request['event_name']); ?></div>
                                <span class="od-status                                                                                                                                                                   <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                            <div class="od-details">
                                <strong>Date:</strong>                                                       <?php echo date('M d, Y', strtotime($request['event_date'])); ?> at<?php echo date('h:i A', strtotime($request['event_time'])); ?><br>
                                <strong>Duration:</strong>                                                           <?php echo isset($request['event_days']) ? htmlspecialchars($request['event_days']) . ' day(s)' : 'Not specified'; ?><br>
                                <strong>Location:</strong>                                                           <?php echo htmlspecialchars($request['event_location']); ?><br>
                                <?php if (! empty($request['event_poster'])): ?>
                                <div style="margin: 10px 0;">
                                    <strong>Event Poster:</strong><br>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 8px;">
                                        <?php
                                            $poster_path    = 'uploads/posters/' . $request['event_poster'];
                                            $file_extension = strtolower(pathinfo($request['event_poster'], PATHINFO_EXTENSION));
                                        ?>

                                        <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && file_exists($poster_path)): ?>
                                        <div style="flex-shrink: 0;">
                                            <img src="<?php echo htmlspecialchars($poster_path); ?>"
                                                 alt="Event Poster Thumbnail"
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 2px solid #e9ecef; cursor: pointer;"
                                                 onclick="window.open('view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>', '_blank')">
                                        </div>
                                        <?php endif; ?>

                                        <div style="display: flex; flex-direction: column; gap: 5px;">
                                            <a href="view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>"
                                               target="_blank"
                                               style="color: #0c3878; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 5px;">
                                                <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                                View Poster
                                            </a>
                                            <small style="color: #6c757d; font-size: 11px;">
                                                <?php echo htmlspecialchars($request['event_poster']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <strong>Requested:</strong><?php echo date('M d, Y h:i A', strtotime($request['request_date'])); ?><br>
                                <?php if ($request['status'] !== 'pending' && $request['counselor_remarks']): ?>
                                <strong>Counselor Remarks:</strong><?php echo htmlspecialchars($request['counselor_remarks']); ?><br>
                                <?php endif; ?>
                                <?php if ($request['status'] === 'approved'): ?>
                                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <a href="student_register.php?od_id=<?php echo $request['id']; ?>" class="btn btn-primary" style="font-size: 12px; padding: 8px 15px;">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">add_circle</span>
                                        Register for Event
                                    </a>
                                    <a href="download_od_letter.php?od_id=<?php echo $request['id']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 8px 15px;" target="_blank">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                                        Download OD Letter
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined">description</span>
                            <p>No OD requests submitted yet.</p>
                        </div>
                    <?php endif; ?>
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

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                // Show popup for OD submission success
                setTimeout(function() {
                    alert('✅ OD Request Submitted Successfully!\\n\\nYour On Duty (OD) request has been submitted and is now pending approval from your class counselor. You will be notified once your request is reviewed.');
                }, 500);

                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>