<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get logged in teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;

    // Try to get teacher data from teacher_register table first
    $sql  = "SELECT name, faculty_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        // Fallback: Check if username exists in student_register table
        $sql2  = "SELECT name, regno as faculty_id FROM student_register WHERE username=?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("s", $username);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        if ($result2->num_rows > 0) {
            $teacher_data = $result2->fetch_assoc();
        } else {
            // If no data found anywhere, create a default entry
            $teacher_data = [
                'name'       => ucfirst($username),                            // Use username as name
                'faculty_id' => 'TEMP-' . strtoupper(substr($username, 0, 4)), // Generate temp ID
            ];
        }
        $stmt2->close();
    }
    $stmt->close();

    $success_message = "";
    $error_message   = "";

    // Process form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Get form data
        $staff_id      = trim($_POST["staffid"]);
        $name          = trim($_POST["name"]);
        $department    = trim($_POST["department"]);
        $event_date    = trim($_POST["event-of-date"]);
        $academic_year = trim($_POST["academic"]);
        $event_type    = trim($_POST["eventType"]);
        $topic         = trim($_POST["topic"]);
        $no_of_dates   = trim($_POST["dates"]);
        $from_date     = trim($_POST["from"]);
        $to_date       = trim($_POST["to"]);
        $organisation  = trim($_POST["organisation"]);
        $sponsors      = trim($_POST["sponsors"]);

        // Validate required fields
        if (empty($staff_id) || empty($name) || empty($department) || empty($event_date) ||
            empty($academic_year) || empty($event_type) || empty($topic) || empty($no_of_dates) ||
            empty($from_date) || empty($to_date) || empty($organisation) || empty($sponsors)) {
            $error_message = "All fields are required!";
        } else {
            // Validate that event dates are in the past (event must be completed)
            $today = date('Y-m-d');
            if ($event_date > $today) {
                $error_message = "Event date must be in the past. You can only register completed events!";
            } else if ($to_date > $today) {
                $error_message = "Event end date must be in the past. You can only register completed events!";
            } else if ($from_date > $to_date) {
                $error_message = "Event start date cannot be after end date!";
            } else {
                // Handle file upload
                $certificate_path = "";
                if (isset($_FILES["certificates"]) && $_FILES["certificates"]["error"] == 0) {
                    $target_dir = "../../uploads/";

                    // Create uploads directory if it doesn't exist
                    if (! file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }

                    $file_extension = strtolower(pathinfo($_FILES["certificates"]["name"], PATHINFO_EXTENSION));

                    // Check if file is PDF
                    if ($file_extension != "pdf") {
                        $error_message = "Only PDF files are allowed!";
                    } else {
                        // Generate unique filename
                        $unique_name = "staff_cert_" . uniqid() . "_" . basename($_FILES["certificates"]["name"]);
                        $target_file = $target_dir . $unique_name;

                        if (move_uploaded_file($_FILES["certificates"]["tmp_name"], $target_file)) {
                            $certificate_path = $unique_name;
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                        }
                    }
                } else {
                    $error_message = "Certificate upload is required!";
                }

                // If no errors, insert into database
                if (empty($error_message)) {
                    try {
                        // Check if staff already registered for this event
                        $check_sql  = "SELECT id FROM staff_event_reg WHERE staff_id = ? AND topic = ? AND event_date = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("sss", $staff_id, $topic, $event_date);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();

                        if ($check_result->num_rows > 0) {
                            $error_message = "You have already registered for this event!";
                            $check_stmt->close();
                        } else {
                            $check_stmt->close();

                            // Insert new registration
                            $sql = "INSERT INTO staff_event_reg
                            (staff_id, name, department, event_date, academic_year, event_type, topic,
                             no_of_dates, from_date, to_date, organisation, sponsors, certificate_path)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("sssssssssssss",
                                $staff_id, $name, $department, $event_date, $academic_year, $event_type,
                                $topic, $no_of_dates, $from_date, $to_date, $organisation, $sponsors, $certificate_path
                            );

                            if ($stmt->execute()) {
                                $success_message = "Staff event registration successful!";
                                // Clear form data after successful submission
                                $_POST = [];
                            } else {
                                $error_message = "Error: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    } catch (Exception $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                }
            } // Close the date validation else block
        }
    }

    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Event Registration - Teacher Dashboard</title>
    <link
      rel="icon"
      type="icon/png"
      sizes="32x32"
      href="../asserts/images/Sona Logo.png"
    />
    <link rel="stylesheet" href="../student/student_dashboard.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Modern Registration Form Design */
        .registration-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .header {
  grid-area: header;
  background-color: #fff;
  height: 80px;
  display: flex;
  font-size: 15px;
  font-weight: 100;
  align-items: center;
  justify-content: space-between;
  box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px,
    rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;
  color: #1e4276;
  position: fixed;
  width: 100%;
  z-index: 1001;
  top: 0;
  left: 0;
}

        .registration-form {
            background: #ffffff;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f2f5;
        }

        .form-title {
            color: #2c5aa0;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .form-subtitle {
            color: #6c757d;
            font-size: 16px;
            font-weight: 400;
            margin: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input,
        .form-select {
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #ffffff;
            width: 100%;
            box-sizing: border-box;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 4px rgba(44, 90, 160, 0.1);
            transform: translateY(-2px);
        }

        .form-input:hover,
        .form-select:hover {
            border-color: #2c5aa0;
        }

        .file-upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
        }

        .file-upload-area:hover {
            border-color: #2c5aa0;
            background: #f0f4ff;
        }

        .file-upload-area.dragover {
            border-color: #2c5aa0;
            background: #e3f2fd;
        }

        .file-upload-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .file-upload-text {
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .file-upload-hint {
            color: #6c757d;
            font-size: 14px;
        }

        .file-preview {
            margin-top: 15px;
            padding: 15px;
            background: #e8f5e8;
            border-radius: 8px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .file-preview.show {
            display: flex;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #28a745;
            margin-bottom: 2px;
        }

        .file-size {
            font-size: 12px;
            color: #6c757d;
        }

        .submit-button {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3f72 100%);
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 30px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(44, 90, 160, 0.4);
        }

        .submit-button:active {
            transform: translateY(-1px);
        }

        .form-note {
            font-size: 13px;
            color: #6c757d;
            margin-top: 8px;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header .header-logo {
                display: none;
            }

            .registration-container {
                padding: 0 10px;
                margin: 10px auto;
            }

            .registration-form {
                padding: 30px 20px;
                margin: 0;
            }

            .form-title {
                font-size: 28px;
                flex-direction: column;
                gap: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .form-group.full-width {
                grid-column: 1;
            }

            .submit-button {
                padding: 16px 30px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .registration-container {
                padding: 0 5px;
            }

            .registration-form {
                padding: 25px 15px;
                border-radius: 15px;
            }

            .form-title {
                font-size: 24px;
            }

            .form-subtitle {
                font-size: 14px;
            }

            .form-grid {
                gap: 18px;
            }

            .form-input,
            .form-select {
                padding: 12px 15px;
                font-size: 16px;
            }

            .form-label {
                font-size: 13px;
            }

            .submit-button {
                padding: 15px 25px;
                font-size: 16px;
                border-radius: 30px;
            }

            .file-upload-area {
                padding: 25px 15px;
            }

            .file-upload-icon {
                font-size: 36px;
            }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .alert .material-symbols-outlined {
            font-size: 20px;
        }
    </style>
  </head>
  <body>
    <div class="grid-container">
      <!-- Header -->
      <div class="header">
         <div class="menu-icon" onclick="openSidebar()">
          <span class="material-symbols-outlined">menu</span>
        </div>
        <div class="header-logo">
          <img
            class="logo"
            src="sona_logo.jpg"
            alt="Sona College Logo"
            height="60px"
            width="200"
          />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div >
          <!-- empty -->
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
          <div class="student-regno">ID:                                                                                                                                                                                                         <?php echo htmlspecialchars($teacher_data['faculty_id']); ?></div>
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
              <a href="staff_event_reg.php" class="nav-link active">
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


        <?php if (! empty($success_message)): ?>
          <div class="alert alert-success">
            <span class="material-symbols-outlined">check_circle</span>
            <?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (! empty($error_message)): ?>
          <div class="alert alert-error">
            <span class="material-symbols-outlined">error</span>
            <?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <div class="registration-container">
          <form action="" method="POST" enctype="multipart/form-data" class="registration-form" id="staffEventForm">
            <div class="form-header">
              <h1 class="form-title">
                <span class="material-symbols-outlined">event_note</span>
                Staff Event Registration
              </h1>
              <p class="form-subtitle">Register your completed professional development events</p>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="staffid">Staff ID</label>
                <input
                  type="text"
                  id="staffid"
                  name="staffid"
                  class="form-input"
                  value="<?php echo isset($_POST['staffid']) ? htmlspecialchars($_POST['staffid']) : htmlspecialchars($teacher_data['faculty_id']); ?>"
                  required
                  readonly
                  style="background-color: #f8f9fa; cursor: not-allowed;"
                >
                <div class="form-note">Auto-filled from your profile</div>
              </div>

              <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  class="form-input"
                  value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($teacher_data['name']); ?>"
                  required
                  readonly
                  style="background-color: #f8f9fa; cursor: not-allowed;"
                >
                <div class="form-note">Auto-filled from your profile</div>
              </div>

              <div class="form-group">
                <label class="form-label" for="department">Department</label>
                <select id="department" name="department" class="form-select" required>
                  <option value="">Select Your Department</option>
                  <option value="Computer Science"                                                                                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                  <option value="Information Technology"                                                                                                                 <?php echo(isset($_POST['department']) && $_POST['department'] == 'Information Technology') ? 'selected' : ''; ?>>Information Technology</option>
                  <option value="Electronics"                                                                                           <?php echo(isset($_POST['department']) && $_POST['department'] == 'Electronics') ? 'selected' : ''; ?>>Electronics</option>
                  <option value="Mechanical"                                                                                         <?php echo(isset($_POST['department']) && $_POST['department'] == 'Mechanical') ? 'selected' : ''; ?>>Mechanical</option>
                  <option value="Civil"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'Civil') ? 'selected' : ''; ?>>Civil</option>
                  <option value="Electrical"                                                                                         <?php echo(isset($_POST['department']) && $_POST['department'] == 'Electrical') ? 'selected' : ''; ?>>Electrical</option>
                  <option value="MBA"                                                                           <?php echo(isset($_POST['department']) && $_POST['department'] == 'MBA') ? 'selected' : ''; ?>>MBA</option>
                  <option value="Other"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="academic">Academic Year</label>
                <select id="academic" name="academic" class="form-select" required>
                  <option value="">Select Academic Year</option>
                  <option value="2024-25"                                                                                   <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2024-25') ? 'selected' : ''; ?>>2024-25</option>
                  <option value="2023-24"                                                                                   <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2023-24') ? 'selected' : ''; ?>>2023-24</option>
                  <option value="2022-23"                                                                                   <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2022-23') ? 'selected' : ''; ?>>2022-23</option>
                  <option value="2021-22"                                                                                   <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2021-22') ? 'selected' : ''; ?>>2021-22</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="eventType">Event Type</label>
                <select id="eventType" name="eventType" class="form-select" required>
                  <option value="">Select Event Type</option>
                  <option value="Workshop"                                                                                     <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                  <option value="Seminar"                                                                                   <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Seminar') ? 'selected' : ''; ?>>Seminar</option>
                  <option value="Conference"                                                                                         <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Conference') ? 'selected' : ''; ?>>Conference</option>
                  <option value="Training"                                                                                     <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Training') ? 'selected' : ''; ?>>Training</option>
                  <option value="FDP"                                                                           <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'FDP') ? 'selected' : ''; ?>>Faculty Development Program</option>
                  <option value="Webinar"                                                                                   <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Webinar') ? 'selected' : ''; ?>>Webinar</option>
                  <option value="Other"                                                                               <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="event-of-date">Event Date</label>
                <input
                  type="date"
                  id="event-of-date"
                  name="event-of-date"
                  class="form-input"
                  value="<?php echo isset($_POST['event-of-date']) ? htmlspecialchars($_POST['event-of-date']) : ''; ?>"
                  required
                  max="<?php echo date('Y-m-d'); ?>"
                >
                <div class="form-note">Select past date only (completed events)</div>
              </div>

              <div class="form-group full-width">
                <label class="form-label" for="topic">Event Topic/Subject</label>
                <input
                  type="text"
                  id="topic"
                  name="topic"
                  class="form-input"
                  value="<?php echo isset($_POST['topic']) ? htmlspecialchars($_POST['topic']) : ''; ?>"
                  required
                  maxlength="100"
                  placeholder="Enter the main topic or subject of the event"
                >
                <div class="form-note">Brief description of the event topic (max 100 characters)</div>
              </div>

              <div class="form-group">
                <label class="form-label" for="dates">Duration (Days)</label>
                <select id="dates" name="dates" class="form-select" required>
                  <option value="">Select Duration</option>
                  <option value="1"                                                                       <?php echo(isset($_POST['dates']) && $_POST['dates'] == '1') ? 'selected' : ''; ?>>1 Day</option>
                  <option value="2"                                                                       <?php echo(isset($_POST['dates']) && $_POST['dates'] == '2') ? 'selected' : ''; ?>>2 Days</option>
                  <option value="3"                                                                       <?php echo(isset($_POST['dates']) && $_POST['dates'] == '3') ? 'selected' : ''; ?>>3 Days</option>
                  <option value="5"                                                                       <?php echo(isset($_POST['dates']) && $_POST['dates'] == '5') ? 'selected' : ''; ?>>5 Days</option>
                  <option value="7"                                                                       <?php echo(isset($_POST['dates']) && $_POST['dates'] == '7') ? 'selected' : ''; ?>>1 Week</option>
                  <option value="14"                                                                         <?php echo(isset($_POST['dates']) && $_POST['dates'] == '14') ? 'selected' : ''; ?>>2 Weeks</option>
                  <option value="30"                                                                         <?php echo(isset($_POST['dates']) && $_POST['dates'] == '30') ? 'selected' : ''; ?>>1 Month</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label" for="from">From Date</label>
                <input
                  type="date"
                  id="from"
                  name="from"
                  class="form-input"
                  value="<?php echo isset($_POST['from']) ? htmlspecialchars($_POST['from']) : ''; ?>"
                  required
                  max="<?php echo date('Y-m-d'); ?>"
                >
              </div>

              <div class="form-group">
                <label class="form-label" for="to">To Date</label>
                <input
                  type="date"
                  id="to"
                  name="to"
                  class="form-input"
                  value="<?php echo isset($_POST['to']) ? htmlspecialchars($_POST['to']) : ''; ?>"
                  required
                  max="<?php echo date('Y-m-d'); ?>"
                >
              </div>

              <div class="form-group">
                <label class="form-label" for="organisation">Organizing Institution</label>
                <input
                  type="text"
                  id="organisation"
                  name="organisation"
                  class="form-input"
                  value="<?php echo isset($_POST['organisation']) ? htmlspecialchars($_POST['organisation']) : ''; ?>"
                  required
                  maxlength="80"
                  placeholder="Name of the organizing institution"
                >
              </div>

              <div class="form-group">
                <label class="form-label" for="sponsors">Event Sponsors</label>
                <input
                  type="text"
                  id="sponsors"
                  name="sponsors"
                  class="form-input"
                  value="<?php echo isset($_POST['sponsors']) ? htmlspecialchars($_POST['sponsors']) : ''; ?>"
                  required
                  placeholder="Enter sponsors or 'Self-funded' if none"
                >
              </div>

              <div class="form-group full-width">
                <label class="form-label" for="certificates">Event Certificate</label>
                <input type="file" id="certificates" name="certificates" accept=".pdf" required style="display: none;">
                <div class="file-upload-area" onclick="document.getElementById('certificates').click();" id="fileUploadArea">
                  <div class="file-upload-icon">
                    <span class="material-symbols-outlined">cloud_upload</span>
                  </div>
                  <div class="file-upload-text">Click to upload certificate</div>
                  <div class="file-upload-hint">PDF files only (Max: 5MB)</div>
                </div>
                <div class="file-preview" id="certificatePreview">
                  <span class="material-symbols-outlined" style="color: #28a745;">check_circle</span>
                  <div class="file-info">
                    <div class="file-name" id="certificateName"></div>
                    <div class="file-size" id="certificateSize"></div>
                  </div>
                </div>
              </div>
            </div>

            <button type="submit" class="submit-button" id="submitBtn">
              <span class="material-symbols-outlined" style="margin-right: 10px;">event_available</span>
              Register Event
            </button>
          </form>
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

        // Modern form functionality
        const form = document.getElementById('staffEventForm');
        const fileInput = document.getElementById('certificates');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const submitBtn = document.getElementById('submitBtn');

        // File upload enhancement
        if (fileInput) {
          fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('certificatePreview');
            const fileName = document.getElementById('certificateName');
            const fileSize = document.getElementById('certificateSize');

            if (file) {
              if (file.type !== 'application/pdf') {
                alert('Please select a PDF file only.');
                this.value = '';
                preview.classList.remove('show');
                return;
              }

              if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('File size must be less than 5MB.');
                this.value = '';
                preview.classList.remove('show');
                return;
              }

              // Show file preview
              fileName.textContent = file.name;
              fileSize.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
              preview.classList.add('show');

              // Update upload area
              fileUploadArea.style.borderColor = '#28a745';
              fileUploadArea.style.backgroundColor = '#f8f9fa';
            } else {
              preview.classList.remove('show');
              fileUploadArea.style.borderColor = '#e9ecef';
              fileUploadArea.style.backgroundColor = '#f8f9fa';
            }
          });

          // Drag and drop functionality
          fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
          });

          fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
          });

          fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
              fileInput.files = files;
              fileInput.dispatchEvent(new Event('change'));
            }
          });
        }

        // Date validation
        const eventDate = form.querySelector('input[name="event-of-date"]');
        const fromDate = form.querySelector('input[name="from"]');
        const toDate = form.querySelector('input[name="to"]');

        function validateDates() {
          const event = new Date(eventDate.value);
          const from = new Date(fromDate.value);
          const to = new Date(toDate.value);

          if (fromDate.value && toDate.value) {
            if (from > to) {
              toDate.setCustomValidity('End date must be after start date');
            } else {
              toDate.setCustomValidity('');
            }
          }
        }

        fromDate.addEventListener('change', validateDates);
        toDate.addEventListener('change', validateDates);

        // Enhanced form submission
        if (form && submitBtn) {
          form.addEventListener('submit', function(e) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="material-symbols-outlined" style="animation: spin 1s linear infinite;">sync</span> Submitting...';
            submitBtn.disabled = true;

            // Re-enable button after 5 seconds in case of errors
            setTimeout(() => {
              submitBtn.innerHTML = originalText;
              submitBtn.disabled = false;
            }, 5000);
          });
        }

        // Input animations and focus effects
        const inputs = form.querySelectorAll('.form-input, .form-select');
        inputs.forEach(input => {
          input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
            this.parentElement.style.transition = 'transform 0.2s ease';
          });

          input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
          });
        });

        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
          setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
              alert.style.display = 'none';
            }, 300);
          }, 5000);
        });

        // Initial progress calculation
        updateProgress();

        // Character counting functionality
        const topicInput = document.querySelector('input[name="topic"]');
        const organizationInput = document.querySelector('input[name="organisation"]');
        const topicCount = document.getElementById('topicCount');
        const organizationCount = document.getElementById('organizationCount');

        if (topicInput && topicCount) {
          topicInput.addEventListener('input', function() {
            topicCount.textContent = this.value.length;
            if (this.value.length > 90) {
              topicCount.style.color = '#e74c3c';
            } else {
              topicCount.style.color = '#666';
            }
          });
          // Initial count
          topicCount.textContent = topicInput.value.length;
        }

        if (organizationInput && organizationCount) {
          organizationInput.addEventListener('input', function() {
            organizationCount.textContent = this.value.length;
            if (this.value.length > 70) {
              organizationCount.style.color = '#e74c3c';
            } else {
              organizationCount.style.color = '#666';
            }
          });
          // Initial count
          organizationCount.textContent = organizationInput.value.length;
        }

        // File preview functionality
        const certificateFileInput = document.getElementById('certificateFile');
        const certificatePreview = document.getElementById('certificatePreview');
        const certificateName = document.getElementById('certificateName');
        const certificateSize = document.getElementById('certificateSize');

        if (certificateFileInput) {
          certificateFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
              if (file.type !== 'application/pdf') {
                alert('Please select a PDF file only.');
                this.value = '';
                certificatePreview.style.display = 'none';
                return;
              }

              if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('File size must be less than 5MB.');
                this.value = '';
                certificatePreview.style.display = 'none';
                return;
              }

              // Show file preview
              certificateName.textContent = file.name;
              certificateSize.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB`;
              certificatePreview.style.display = 'block';
            } else {
              certificatePreview.style.display = 'none';
            }
          });
        }
      });

      // CSS for spinning animation
      const style = document.createElement('style');
      style.textContent = `
        @keyframes spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
      `;
      document.head.appendChild(style);
    </script>
  </body>
</html>