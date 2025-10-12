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
        /* Student Form Styling */
        .parent {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(7, 1fr);
            grid-column-gap: 20px;
            grid-row-gap: 20px;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .item {
            display: flex;
            flex-direction: column;
        }

        .div1 { grid-area: 1 / 1 / 2 / 2; }
        .div2 { grid-area: 1 / 2 / 2 / 3; }
        .div3 { grid-area: 2 / 1 / 3 / 2; }
        .div4 { grid-area: 2 / 2 / 3 / 3; }
        .div5 { grid-area: 3 / 1 / 4 / 2; }
        .div6 { grid-area: 3 / 2 / 4 / 3; }
        .div7 { grid-area: 4 / 1 / 5 / 3; }
        .div8 { grid-area: 5 / 1 / 6 / 2; }
        .div9 { grid-area: 5 / 2 / 6 / 3; }
        .div10 { grid-area: 6 / 1 / 7 / 2; }
        .div11 { grid-area: 6 / 2 / 7 / 3; }
        .div12 { grid-area: 7 / 1 / 8 / 2; }
        .div13 { grid-area: 7 / 2 / 8 / 3; }
        .div14 { grid-area: 8 / 1 / 9 / 3; }

        .item label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .required-asterisk {
            color: #e74c3c;
            margin-left: 3px;
        }

        .item input,
        .item select {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: white;
        }

        .item input:focus,
        .item select:focus {
            outline: none;
            border-color: #2d5aa0;
            box-shadow: 0 0 0 3px rgba(45, 90, 160, 0.1);
        }

        .character-count {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
            cursor: help;
        }

        .tooltiptext {
            visibility: hidden;
            width: 220px;
            background-color: #555;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -110px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .file-preview {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            display: none;
        }

        .file-name {
            font-weight: 600;
            color: #2d5aa0;
        }

        .file-size {
            font-size: 12px;
            color: #666;
        }

        #button {
            background: linear-gradient(135deg, #2d5aa0 0%, #1e3a6f 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        #button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(45, 90, 160, 0.3);
        }

        small {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .registration-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            margin: 20px auto;
            max-width: 1000px;
        }

        .registration-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            z-index: 1;
        }

        .form-content {
            position: relative;
            z-index: 2;
        }

        .form-title {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .form-title .material-symbols-outlined {
            font-size: 32px;
            color: #2d5aa0;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #ecf0f1;
            border-radius: 3px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2d5aa0, #1e3a6f);
            border-radius: 3px;
            transition: width 0.3s ease;
            width: 0%;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
                margin: 0;
                padding: 0;
            }

            .main {
                width: 100% !important;
                max-width: 100vw !important;
                padding: 20px 10px !important;
                margin: 0 !important;
                overflow-x: hidden;
                box-sizing: border-box;
            }

            .registration-form {
                padding: 20px 15px;
                margin: 10px 5px;
                width: calc(100% - 10px);
                max-width: calc(100vw - 20px);
                box-sizing: border-box;
            }

            .parent {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
                padding: 20px 15px;
                gap: 15px;
                width: 100%;
                box-sizing: border-box;
            }

            .div1 { grid-area: 1 / 1 / 2 / 2; }
            .div2 { grid-area: 2 / 1 / 3 / 2; }
            .div3 { grid-area: 3 / 1 / 4 / 2; }
            .div4 { grid-area: 4 / 1 / 5 / 2; }
            .div5 { grid-area: 5 / 1 / 6 / 2; }
            .div6 { grid-area: 6 / 1 / 7 / 2; }
            .div7 { grid-area: 7 / 1 / 8 / 2; }
            .div8 { grid-area: 8 / 1 / 9 / 2; }
            .div9 { grid-area: 9 / 1 / 10 / 2; }
            .div10 { grid-area: 10 / 1 / 11 / 2; }
            .div11 { grid-area: 11 / 1 / 12 / 2; }
            .div12 { grid-area: 12 / 1 / 13 / 2; }
            .div13 { grid-area: 13 / 1 / 14 / 2; }
            .div14 { grid-area: 14 / 1 / 15 / 2; }

            .item {
                width: 100%;
                box-sizing: border-box;
            }

            .item input,
            .item select {
                padding: 12px 15px;
                font-size: 16px;
                width: 100%;
                box-sizing: border-box;
            }

            .form-title {
                font-size: 24px;
                margin-bottom: 20px;
            }

            #button {
                padding: 14px 24px;
                font-size: 16px;
                width: 100%;
                box-sizing: border-box;
            }

            /* Ensure no horizontal overflow */
            * {
                max-width: 100%;
                box-sizing: border-box;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 15px 5px !important;
            }

            .registration-form {
                padding: 15px 10px;
                margin: 5px 2px;
                width: calc(100% - 4px);
                max-width: calc(100vw - 10px);
            }

            .parent {
                padding: 15px 10px;
                gap: 12px;
            }

            .form-title {
                font-size: 20px;
                margin-bottom: 15px;
            }

            .item input,
            .item select {
                padding: 10px 12px;
                font-size: 16px;
            }

            .item label {
                font-size: 14px;
                margin-bottom: 6px;
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
          <div class="student-regno">ID:                                                                                                                         <?php echo htmlspecialchars($teacher_data['faculty_id']); ?></div>
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
            <div class="form-content">
              <h2 class="form-title">
                <span class="material-symbols-outlined">event_note</span>
                Add Completed Event Record
              </h2>

              <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
              </div>

              <div class="parent">
                <div class="item div1">
                  <label>
                    Staff ID <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Your unique staff identification</span>
                    </div>
                  </label>
                  <input
                    type="text"
                    name="staffid"
                    placeholder="Enter Your Staff ID"
                    value="<?php echo isset($_POST['staffid']) ? htmlspecialchars($_POST['staffid']) : htmlspecialchars($teacher_data['faculty_id']); ?>"
                    required
                  />
                </div>

                <div class="item div2">
                  <label>
                    Full Name <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Your full name as registered</span>
                    </div>
                  </label>
                  <input
                    type="text"
                    name="name"
                    placeholder="Enter Your Full Name"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($teacher_data['name']); ?>"
                    required
                  />
                </div>

                <div class="item div3">
                  <label>
                    Department <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Your department affiliation</span>
                    </div>
                  </label>
                  <select name="department" required>
                    <option value="" disabled                                                                                           <?php echo ! isset($_POST['department']) ? 'selected' : ''; ?>>Select Your Department</option>
                    <option value="CSE"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSE') ? 'selected' : ''; ?>>Computer Science and Engineering (CSE)</option>
                    <option value="IT"                                                                             <?php echo(isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : ''; ?>>Information Technology (IT)</option>
                    <option value="ECE"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'ECE') ? 'selected' : ''; ?>>Electronics and Communication Engineering (ECE)</option>
                    <option value="EEE"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'EEE') ? 'selected' : ''; ?>>Electrical and Electronics Engineering (EEE)</option>
                    <option value="MECH"                                                                                 <?php echo(isset($_POST['department']) && $_POST['department'] == 'MECH') ? 'selected' : ''; ?>>Mechanical Engineering (MECH)</option>
                    <option value="CIVIL"                                                                                   <?php echo(isset($_POST['department']) && $_POST['department'] == 'CIVIL') ? 'selected' : ''; ?>>Civil Engineering (CIVIL)</option>
                    <option value="BME"                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'BME') ? 'selected' : ''; ?>>Biomedical Engineering (BME)</option>
                  </select>
                </div>

                <div class="item div4">
                  <label>
                    Event Date <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Date when the event took place (past date only)</span>
                    </div>
                  </label>
                  <input
                    type="date"
                    name="event-of-date"
                    max="<?php echo date('Y-m-d'); ?>"
                    value="<?php echo isset($_POST['event-of-date']) ? htmlspecialchars($_POST['event-of-date']) : ''; ?>"
                    required
                  />
                  <small>Select past date only</small>
                </div>                <div class="item div5">
                  <label>
                    Academic Year <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Academic year when event occurred</span>
                    </div>
                  </label>
                  <select name="academic" required>
                    <option value="" disabled                                                                                           <?php echo ! isset($_POST['academic']) ? 'selected' : ''; ?>>Select Academic Year</option>
                    <option value="2024-2025"                                                                                           <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2024-2025') ? 'selected' : ''; ?>>(2024-2025) - Current</option>
                    <option value="2025-2026"                                                                                           <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2025-2026') ? 'selected' : ''; ?>>(2025-2026) - ODD</option>
                    <option value="2026-2027"                                                                                           <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2026-2027') ? 'selected' : ''; ?>>(2026-2027) - EVEN</option>
                    <option value="2027-2028"                                                                                           <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2027-2028') ? 'selected' : ''; ?>>(2027-2028) - ODD</option>
                  </select>
                </div>

                <div class="item div6">
                  <label>
                    Event Type <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Type of professional development activity</span>
                    </div>
                  </label>
                  <select name="eventType" required>
                    <option value="" disabled                                                                                           <?php echo ! isset($_POST['eventType']) ? 'selected' : ''; ?>>Select Event Type</option>
                    <option value="FDP"                                                                               <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'FDP') ? 'selected' : ''; ?>>Faculty Development Program (FDP)</option>
                    <option value="Workshop"                                                                                         <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                    <option value="CONFERENCE"                                                                                             <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'CONFERENCE') ? 'selected' : ''; ?>>Conference</option>
                    <option value="INDUSTRIAL TRAINING"                                                                                                               <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'INDUSTRIAL TRAINING') ? 'selected' : ''; ?>>Industrial Training</option>
                    <option value="STTP"                                                                                 <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'STTP') ? 'selected' : ''; ?>>Short Term Training Program (STTP)</option>
                    <option value="REVIEWER"                                                                                         <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'REVIEWER') ? 'selected' : ''; ?>>Reviewer</option>
                  </select>
                </div>

                <div class="item div7">
                  <label>
                    Event Topic <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Professional development topic</span>
                    </div>
                  </label>
                  <input
                    type="text"
                    name="topic"
                    placeholder="Enter the Event Topic or Subject"
                    value="<?php echo isset($_POST['topic']) ? htmlspecialchars($_POST['topic']) : ''; ?>"
                    maxlength="100"
                    required
                  />
                  <div class="character-count">
                    <span id="topicCount">0</span>/100 characters
                  </div>
                  <small>Professional development event topic</small>
                </div>



                <div class="item div8">
                  <label>
                    Number of Days <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Duration of the event</span>
                    </div>
                  </label>
                  <select name="dates" required>
                    <option value="" disabled                                                                                           <?php echo ! isset($_POST['dates']) ? 'selected' : ''; ?>>Select Duration</option>
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                      <option value="<?php echo $i; ?>"<?php echo(isset($_POST['dates']) && $_POST['dates'] == $i) ? 'selected' : ''; ?>>
                        <?php echo $i; ?> Day<?php echo $i > 1 ? 's' : ''; ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>

                <div class="item div9">
                  <label>
                    Start Date <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Event start date (past date only)</span>
                    </div>
                  </label>
                  <input
                    type="date"
                    name="from"
                    max="<?php echo date('Y-m-d'); ?>"
                    value="<?php echo isset($_POST['from']) ? htmlspecialchars($_POST['from']) : ''; ?>"
                    required
                  />
                  <small>Select past date only</small>
                </div>

                <div class="item div10">
                  <label>
                    End Date <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Event end date (past date only)</span>
                    </div>
                  </label>
                  <input
                    type="date"
                    name="to"
                    max="<?php echo date('Y-m-d'); ?>"
                    value="<?php echo isset($_POST['to']) ? htmlspecialchars($_POST['to']) : ''; ?>"
                    required
                  />
                  <small>Select past date only</small>
                </div>                <div class="item div11">
                  <label>
                    Organized By <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Institution/Company organizing the event</span>
                    </div>
                  </label>
                  <input
                    type="text"
                    name="organisation"
                    placeholder="Enter the Organizing Institution/Company"
                    value="<?php echo isset($_POST['organisation']) ? htmlspecialchars($_POST['organisation']) : ''; ?>"
                    maxlength="80"
                    required
                  />
                  <div class="character-count">
                    <span id="organizationCount">0</span>/80 characters
                  </div>
                </div>

                <div class="item div12">
                  <label>
                    Sponsored By <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Event sponsor organization</span>
                    </div>
                  </label>
                  <select name="sponsors" required>
                    <option value="" disabled                                                                                           <?php echo ! isset($_POST['sponsors']) ? 'selected' : ''; ?>>Select Sponsor</option>
                    <option value="AICTE"                                                                                   <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'AICTE') ? 'selected' : ''; ?>>AICTE</option>
                    <option value="IBM"                                                                               <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IBM') ? 'selected' : ''; ?>>IBM</option>
                    <option value="INFOSYS SPRINGBOARD"                                                                                                               <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'INFOSYS SPRINGBOARD') ? 'selected' : ''; ?>>Infosys Springboard</option>
                    <option value="IEI"                                                                               <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IEI') ? 'selected' : ''; ?>>Institution of Engineers India (IEI)</option>
                    <option value="IEEE"                                                                                 <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IEEE') ? 'selected' : ''; ?>>Institute of Electrical and Electronics Engineers (IEEE)</option>
                  </select>
                </div>

                <div class="item div13">
                  <label>
                    Certificate <span class="required-asterisk">*</span>
                    <div class="tooltip">
                      <span class="material-symbols-outlined" style="font-size: 14px;">info</span>
                      <span class="tooltiptext">Upload your completion certificate</span>
                    </div>
                  </label>
                  <input type="file" name="certificates" accept=".pdf" required id="certificateFile"/>
                  <div class="file-preview" id="certificatePreview">
                    <div class="file-name" id="certificateName"></div>
                    <div class="file-size" id="certificateSize"></div>
                  </div>
                  <small>Only PDF files accepted (Max: 5MB)</small>
                </div>

                <div class="item div14">
                  <button type="submit" id="button">
                    Add Event Record
                  </button>
                </div>
              </div>
            </div>
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
        const fileInput = document.getElementById('certificateFile');
        const fileUploadButton = document.getElementById('fileUploadButton');
        const fileUploadText = document.getElementById('fileUploadText');
        const progressFill = document.getElementById('progressFill');
        const submitBtn = document.getElementById('submitBtn');

        // Form progress tracking
        function updateProgress() {
          const inputs = form.querySelectorAll('input[required], select[required]');
          let filledInputs = 0;

          inputs.forEach(input => {
            if (input.type === 'file') {
              if (input.files.length > 0) filledInputs++;
            } else if (input.value.trim() !== '') {
              filledInputs++;
            }
          });

          const progress = (filledInputs / inputs.length) * 100;
          progressFill.style.width = progress + '%';

          if (progress === 100) {
            progressFill.style.background = 'linear-gradient(90deg, #27ae60, #2ecc71)';
          } else {
            progressFill.style.background = 'linear-gradient(90deg, #3498db, #2ecc71)';
          }
        }

        // File upload enhancement
        fileInput.addEventListener('change', function(e) {
          const file = e.target.files[0];
          if (file) {
            if (file.type !== 'application/pdf') {
              alert('Please select a PDF file only.');
              this.value = '';
              return;
            }

            if (file.size > 5 * 1024 * 1024) { // 5MB limit
              alert('File size must be less than 5MB.');
              this.value = '';
              return;
            }

            fileUploadButton.classList.add('has-file');
            fileUploadText.textContent = file.name;
            fileUploadButton.querySelector('.material-symbols-outlined').textContent = 'check_circle';
          } else {
            fileUploadButton.classList.remove('has-file');
            fileUploadText.textContent = 'Choose Certificate File';
            fileUploadButton.querySelector('.material-symbols-outlined').textContent = 'cloud_upload';
          }
          updateProgress();
        });

        // Real-time validation and progress
        form.addEventListener('input', updateProgress);
        form.addEventListener('change', updateProgress);

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
        form.addEventListener('submit', function(e) {
          const originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = '<span class="material-symbols-outlined" style="animation: spin 1s linear infinite;">sync</span> Registering...';
          submitBtn.disabled = true;

          // Re-enable button after 3 seconds in case of errors
          setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
          }, 3000);
        });

        // Input animations
        const inputs = form.querySelectorAll('.form-input, .form-select');
        inputs.forEach(input => {
          input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
          });

          input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
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