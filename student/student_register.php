<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    // Get logged-in user's registration number and student data
    $logged_in_regno = '';
    $student_data    = null;
    if (isset($_SESSION['username'])) {
        $conn_user = new mysqli("localhost", "root", "", "event_management_system");
        if ($conn_user->connect_error) {
            die("Connection failed: " . htmlspecialchars($conn_user->connect_error));
        }

        $username  = $_SESSION['username'];
        $user_sql  = "SELECT name, regno FROM student_register WHERE username=?";
        $user_stmt = $conn_user->prepare($user_sql);
        $user_stmt->bind_param("s", $username);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result->num_rows > 0) {
            $student_data    = $user_result->fetch_assoc();
            $logged_in_regno = $student_data['regno'];
        }

        $user_stmt->close();
        $conn_user->close();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $servername = "localhost";
        $username   = "root";
        $password   = "";
        $dbname     = "event_management_system";
        $conn       = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("Connection failed: " . htmlspecialchars($conn->connect_error));
        }

        // Sanitize inputs
        $regno         = isset($_POST['regno']) ? trim($_POST['regno']) : '';
        $current_year  = isset($_POST['year']) ? trim($_POST['year']) : '';
        $semester      = isset($_POST['semester']) ? trim($_POST['semester']) : '';
        $department    = isset($_POST['department']) ? trim($_POST['department']) : '';
        $state         = isset($_POST['state']) ? trim($_POST['state']) : '';
        $district      = isset($_POST['district']) ? trim($_POST['district']) : '';
        $event_type    = isset($_POST['eventType']) ? trim($_POST['eventType']) : '';
        $event_name    = isset($_POST['eventname']) ? trim($_POST['eventname']) : '';
        $attended_date = isset($_POST['attendedDate']) ? $_POST['attendedDate'] : '';
        $organisation  = isset($_POST['organisation']) ? trim($_POST['organisation']) : '';
        $prize         = isset($_POST['prize']) ? trim($_POST['prize']) : '';
        $prize_amount  = isset($_POST['prizeamount']) ? trim($_POST['prizeamount']) : '';

        $target_dir = "uploads/";
        if (! is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Validate PDF file
        function valid_pdf($file)
        {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file);
            finfo_close($finfo);
            return ($mime === 'application/pdf');
        }

        $event_poster_path = null;
        $certificate_path  = null;

        // Handle uploaded event poster
        if (isset($_FILES['eventposter']) && $_FILES['eventposter']['error'] === UPLOAD_ERR_OK) {
            $event_poster      = basename($_FILES['eventposter']['name']);
            $event_poster_sane = preg_replace('/[^A-Za-z0-9_\.-]/', '', $event_poster);
            $target_path       = $target_dir . uniqid('poster_') . '_' . $event_poster_sane;
            if (valid_pdf($_FILES["eventposter"]["tmp_name"])) {
                move_uploaded_file($_FILES["eventposter"]["tmp_name"], $target_path);
                $event_poster_path = $target_path;
            } else {
                echo "<p style='color:red;'>❌ Event poster must be a PDF file.</p>";
                $conn->close();exit;
            }
        }
        // Handle uploaded certificate
        if (isset($_FILES['certificates']) && $_FILES['certificates']['error'] === UPLOAD_ERR_OK) {
            $certificate      = basename($_FILES['certificates']['name']);
            $certificate_sane = preg_replace('/[^A-Za-z0-9_\.-]/', '', $certificate);
            $target_path      = $target_dir . uniqid('cert_') . '_' . $certificate_sane;
            if (valid_pdf($_FILES["certificates"]["tmp_name"])) {
                move_uploaded_file($_FILES["certificates"]["tmp_name"], $target_path);
                $certificate_path = $target_path;
            } else {
                echo "<p style='color:red;'>❌ Certificate must be a PDF file.</p>";
                $conn->close();exit;
            }
        }

        // Duplicate registration check
        $check_sql  = "SELECT id FROM student_event_register WHERE regno = ? AND event_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $regno, $event_name);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            echo "<p style='color:orange;'>⚠️ You have already registered for this event.</p>";
            $check_stmt->close();
            $conn->close();
            exit;
        }
        $check_stmt->close();

        // Insert registration
        $sql = "INSERT INTO student_event_register
        (regno, current_year, semester, state, district, department, event_type, event_name, attended_date, organisation, prize, prize_amount, event_poster, certificates)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }

        $stmt->bind_param(
            "ssssssssssssss",
            $regno,
            $current_year,
            $semester,
            $state,
            $district,
            $department,
            $event_type,
            $event_name,
            $attended_date,
            $organisation,
            $prize,
            $prize_amount,
            $event_poster_path,
            $certificate_path
        );

        if ($stmt->execute()) {
            header("Location: thankyou.php");
            $stmt->close();
            $conn->close();
            exit;
        } else {
            echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($stmt->error) . "</p>";
            $stmt->close();
            $conn->close();
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Student Event Registration</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="student_dashboard.css"/>
  <link rel="stylesheet" href="student_reg.css"/>
  <!-- google icons -->
  <link
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined"
    rel="stylesheet"
  />
  <!-- google fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
    rel="stylesheet"
  />
  <style>
    /* Enhanced form validation and UX improvements */
    .form-field-wrapper {
      position: relative;
    }

    .field-error {
      color: #dc3545;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .field-success {
      color: #28a745;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    .required-asterisk {
      color: #dc3545;
      margin-left: 3px;
    }

    .progress-bar {
      position: fixed;
      top: 0;
      left: 0;
      height: 4px;
      background: linear-gradient(135deg, #1e4276 0%, #2d5aa0 100%);
      z-index: 1000;
      transition: width 0.3s ease;
      width: 0%;
    }

    .form-step-indicator {
      text-align: center;
      margin-bottom: 30px;
      color: #6c757d;
      font-size: 14px;
    }

    .tooltip {
      position: relative;
      display: inline-block;
      cursor: help;
    }

    .tooltip .tooltiptext {
      visibility: hidden;
      width: 200px;
      background-color: #555;
      color: #fff;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      margin-left: -100px;
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 12px;
    }

    .tooltip:hover .tooltiptext {
      visibility: visible;
      opacity: 1;
    }

    .character-count {
      font-size: 11px;
      color: #6c757d;
      text-align: right;
      margin-top: 2px;
    }

    .file-preview {
      margin-top: 10px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      display: none;
    }

    .file-preview.show {
      display: block;
    }

    .file-name {
      font-size: 13px;
      color: #495057;
      font-weight: 500;
    }

    .file-size {
      font-size: 11px;
      color: #6c757d;
    }
  </style>
</head>
<body>
<div class="progress-bar" id="progressBar"></div>

<div class="grid-container">
  <!-- header -->
  <div class="header">
    <div class="menu-icon">
      <span class="material-symbols-outlined">menu</span>
    </div>
    <div class="icon">
      <img
        src="../asserts/images/Sona Logo.png"
        alt="Sona College Logo"
      />
    </div>
    <div class="header-title">
      <p>Event Management System</p>
    </div>

  </div>

  <!-- sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-title">Student Portal</div>
      <div class="close-sidebar" onclick="toggleSidebar()">
        <span class="material-symbols-outlined">close</span>
      </div>
    </div>

    <div class="student-info">
      <div class="student-name"><?php echo $student_data ? htmlspecialchars($student_data['name']) : 'Student'; ?></div>
      <div class="student-regno"><?php echo $student_data ? htmlspecialchars($student_data['regno']) : ''; ?></div>
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
          <a href="student_register.php" class="nav-link active">
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

  <!-- main container -->
  <div class="main">
    <main class="registration-main">
  <form action="" method="POST" enctype="multipart/form-data" class="registration-form">
    <div class="registration-container">
      <h2 class="form-title">Student Event Registration</h2>
      <div class="form-step-indicator">
        Fill out all required fields marked with <span class="required-asterisk">*</span>
      </div>
      <div class="parent">
        <div class="item div13">
          <label for="regno">Registration Number:<span class="required-asterisk">*</span></label>
          <input type="text" id="regno" name="regno" value="<?php echo htmlspecialchars($logged_in_regno); ?>"
                 placeholder="Auto-filled from your profile"
                 pattern="[0-9]{2}[A-Z]{2,4}[0-9]{3}" title="Format: 23CS001"
                 maxlength="10" readonly style="background-color: #f8f9fa; cursor: not-allowed;" required />
        </div>
        <div class="item div5">
          <label for="year">Current Year:</label>
          <select id="year" name="year" required>
            <option value="" disabled selected>Select Your Year</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
          </select>
        </div>
        <div class="item div6">
          <label for="department">Department:</label>
          <select id="department" name="department" required>
            <option value="" disabled selected>Select Your Department</option>
            <option value="CSE">Computer Science and Engineering (CSE)</option>
            <option value="IT">Information Technology (IT)</option>
            <option value="ECE">Electronics and Communication Engineering (ECE)</option>
            <option value="EEE">Electrical and Electronics Engineering (EEE)</option>
            <option value="MECH">Mechanical Engineering (MECH)</option>
            <option value="CIVIL">Civil Engineering (CIVIL)</option>
            <option value="BME">Biomedical Engineering (BME)</option>
          </select>
        </div>
        <div class="item div22">
          <label for="semester">Semester:</label>
          <select id="semester" name="semester" required>
            <option value="" disabled selected>Select Your Semester</option>
            <option value="first semester">First Semester</option>
            <option value="second semester">Second Semester</option>
            <option value="third semester">Third Semester</option>
            <option value="fourth semester">Fourth Semester</option>
            <option value="fifth semester">Fifth Semester</option>
            <option value="sixth semester">Sixth Semester</option>
            <option value="seventh semester">Seventh Semester</option>
            <option value="eighth semester">Eighth Semester</option>
          </select>
        </div>
        <div class="item div17">
          <label for="state">State:</label>
          <select id="state" name="state" required>
            <option value="" disabled selected>Select State</option>
            <option value="tamilnadu">Tamil Nadu</option>
            <option value="kerala">Kerala</option>
            <option value="karnataka">Karnataka</option>
          </select>
        </div>
        <div class="item div18">
          <label for="district">District:</label>
          <select id="district" name="district" required>
            <option value="" disabled selected>Select District</option>
            <option value="salem">Salem</option>
            <option value="chennai">Chennai</option>
            <option value="coimbatore">Coimbatore</option>
          </select>
        </div>
        <div class="item div7">
          <label for="eventType">Event Type:</label>
          <select id="eventType" name="eventType" required>
            <option value="" disabled selected>Select The Event</option>
            <option value="Hackathon">Hackathon</option>
            <option value="Workshop">Workshop</option>
            <option value="Symposium">Symposium</option>
            <option value="Technical">Technical</option>
            <option value="Non-Technical">Non-Technical</option>
          </select>
        </div>
        <div class="item div8">
          <label for="eventname">Event Name:<span class="required-asterisk">*</span></label>
          <input type="text" id="eventname" name="eventname" placeholder="Enter the Event Name"
                 maxlength="100" required />
          <div class="character-count"><span id="eventname-count">0</span>/100</div>
        </div>
        <div class="item div9">
          <label for="attendedDate">Attended Date:</label>
          <input type="date" id="attendedDate" name="attendedDate" required />
        </div>
        <div class="item div10">
          <label for="organisation">Organisation By:<span class="required-asterisk">*</span></label>
          <input type="text" id="organisation" name="organisation" placeholder="Enter the Organisation Name"
                 maxlength="80" required />
          <div class="character-count"><span id="organisation-count">0</span>/80</div>
        </div>
        <div class="item div12">
          <label for="prize">Prize:</label>
          <select id="prize" name="prize" required>
            <option value="" disabled selected>Select The Prize</option>
            <option value="First">First</option>
            <option value="Second">Second</option>
            <option value="Third">Third</option>
            <option value="Participation">Participation</option>
          </select>
        </div>
        <div class="item div13">
          <label for="prizeamount">Prize Amount (Optional):</label>
          <input type="text" id="prizeamount" name="prizeamount"
                 inputmode="numeric" pattern="\d*"
                 oninput="this.value = this.value.replace(/\D/g, '');"
                 placeholder="Enter the Prize Amount" />
        </div>
        <div class="item div14">
          <label for="eventposter">Upload Event Poster:<span class="required-asterisk">*</span>
            <span class="tooltip">ℹ️
              <span class="tooltiptext">Upload the official event poster/flyer in PDF format (Max: 5MB)</span>
            </span>
          </label>
          <input type="file" id="eventposter" name="eventposter" accept=".pdf" required />
          <small>Allowed file types: PDF Only (Max size: 5MB)</small>
          <div class="file-preview" id="poster-preview">
            <div class="file-name" id="poster-name"></div>
            <div class="file-size" id="poster-size"></div>
          </div>
        </div>
        <div class="item div15">
          <label for="certificates">Upload Certificates:<span class="required-asterisk">*</span>
            <span class="tooltip">ℹ️
              <span class="tooltiptext">Upload your participation/achievement certificate in PDF format (Max: 5MB)</span>
            </span>
          </label>
          <input type="file" id="certificates" name="certificates" accept=".pdf" required />
          <small>Allowed file types: PDF Only (Max size: 5MB)</small>
          <div class="file-preview" id="cert-preview">
            <div class="file-name" id="cert-name"></div>
            <div class="file-size" id="cert-size"></div>
          </div>
        </div>
        <div class="item div16">
          <input type="submit" value="Register" id="button" />
        </div>
      </div>
    </div>
  </form>
</main>
    </div>
  </div>
</div>

<footer>
  <p>&copy; 2025 Event Management System. All rights reserved.</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Progress bar functionality
    const progressBar = document.getElementById('progressBar');
    const formFields = document.querySelectorAll('input[required], select[required]');

    function updateProgress() {
        let filledFields = 0;
        formFields.forEach(field => {
            if (field.value.trim() !== '') {
                filledFields++;
            }
        });
        const progress = (filledFields / formFields.length) * 100;
        progressBar.style.width = progress + '%';
    }

    // Add event listeners for progress tracking
    formFields.forEach(field => {
        field.addEventListener('input', updateProgress);
        field.addEventListener('change', updateProgress);
    });

    // Character counters
    function setupCharacterCounter(fieldId, maxLength) {
        const field = document.getElementById(fieldId);
        const counter = document.getElementById(fieldId + '-count');

        if (field && counter) {
            field.addEventListener('input', function() {
                const currentLength = this.value.length;
                counter.textContent = currentLength;

                if (currentLength > maxLength * 0.9) {
                    counter.style.color = '#ffc107';
                } else if (currentLength === maxLength) {
                    counter.style.color = '#dc3545';
                } else {
                    counter.style.color = '#ffffffff';
                }
            });
        }
    }

    // Setup character counters
    setupCharacterCounter('regno', 10);
    setupCharacterCounter('eventname', 100);
    setupCharacterCounter('organisation', 80);

    // Registration number is auto-filled and readonly
    const regnoField = document.getElementById('regno');

    // Since regno is readonly and auto-filled, we don't need input validation
    // Just ensure it's always valid for form submission
    if (regnoField && regnoField.value) {
        regnoField.setCustomValidity('');
    }    // File upload enhancements
    function setupFilePreview(inputId, previewId, nameId, sizeId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const nameElement = document.getElementById(nameId);
        const sizeElement = document.getElementById(sizeId);

        if (input && preview) {
            input.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileSize = (file.size / 1024 / 1024).toFixed(2); // Size in MB

                    // Check file size (5MB limit)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size should not exceed 5MB');
                        this.value = '';
                        preview.classList.remove('show');
                        return;
                    }

                    nameElement.textContent = file.name;
                    sizeElement.textContent = `Size: ${fileSize} MB`;
                    preview.classList.add('show');
                } else {
                    preview.classList.remove('show');
                }
            });
        }
    }

    setupFilePreview('eventposter', 'poster-preview', 'poster-name', 'poster-size');
    setupFilePreview('certificates', 'cert-preview', 'cert-name', 'cert-size');

    // Form submission enhancement
    const form = document.querySelector('.registration-form');
    const submitButton = document.getElementById('button');

    form.addEventListener('submit', function(e) {
        // Add loading state to submit button
        submitButton.classList.add('loading');
        submitButton.disabled = true;

        // Basic validation check
        let isValid = true;
        formFields.forEach(field => {
            if (!field.checkValidity()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!isValid) {
            e.preventDefault();
            submitButton.classList.remove('loading');
            submitButton.disabled = false;
            alert('Please fill in all required fields correctly.');
            return;
        }

        // Show progress completion
        progressBar.style.width = '100%';
    });

    // Smooth scrolling for form validation errors
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('invalid', function() {
            this.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // Auto-save form data to localStorage (basic implementation)
    function saveFormData() {
        const formData = {};
        formFields.forEach(field => {
            if (field.type !== 'file' && !field.readOnly) {
                formData[field.name] = field.value;
            }
        });
        localStorage.setItem('studentRegistrationForm', JSON.stringify(formData));
    }

    function loadFormData() {
        const savedData = localStorage.getItem('studentRegistrationForm');
        if (savedData) {
            const formData = JSON.parse(savedData);
            Object.keys(formData).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field && field.type !== 'file' && !field.readOnly) {
                    field.value = formData[key];
                }
            });
        }
    }    // Load saved form data on page load
    loadFormData();

    // Save form data on input (exclude readonly fields)
    formFields.forEach(field => {
        if (field.type !== 'file' && !field.readOnly) {
            field.addEventListener('input', saveFormData);
            field.addEventListener('change', saveFormData);
        }
    });

    // Clear saved data on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem('studentRegistrationForm');
    });

    // Mobile sidebar functionality
    const sidebar = document.getElementById('sidebar');

    // Mobile menu toggle function
    window.toggleSidebar = function() {
        const body = document.body;

        if (sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            body.classList.remove('sidebar-open');
        } else {
            sidebar.classList.add('active');
            body.classList.add('sidebar-open');
        }
    };

    // Header menu icon functionality
    const headerMenuIcon = document.querySelector('.header .menu-icon');
    if (headerMenuIcon) {
        headerMenuIcon.addEventListener('click', function(e) {
            e.preventDefault();
            window.toggleSidebar();
        });
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
});
</script>
</body>
</html>
