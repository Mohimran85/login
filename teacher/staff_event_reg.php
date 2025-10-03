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

    $sql  = "SELECT name, faculty_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
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
        }
    }

    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Event Registration</title>
    <link
      rel="icon"
      type="icon/png"
      sizes="32x32"
      href="./asserts/images/Sona Logo.png"
    />
    <link rel="stylesheet" href="../styles.css" />
    <style>
input[type="file"]::file-selector-button {
    background-color: #0c3878;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}


input[type="file"]::file-selector-button:hover {
    background-color: #0c3878;
}
</style>
  </head>
  <body>
    <header>
      <div class="container">
        <div class="icon">
          <img src="./asserts/images/Sona Logo.png" alt="Sona Logo" />
        </div>
        <div class="title">
          <h1>Event Management System</h1>
        </div>
      </div>
    </header>
    <main class="registration-main">
      <?php if (! empty($success_message)): ?>
        <div class="alert alert-success" style="background-color: #d4edda; color: #155724; padding: 15px; margin: 20px auto; border-radius: 5px; max-width: 800px; text-align: center;">
          <?php echo htmlspecialchars($success_message); ?>
        </div>
      <?php endif; ?>

      <?php if (! empty($error_message)): ?>
        <div class="alert alert-error" style="background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px auto; border-radius: 5px; max-width: 800px; text-align: center;">
          <?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" class="registration-form">
        <div class="registration-container">
          <h2 class="form-title">Staff Event Registration</h2>

          <div class="parent">

            <div class="item div2">
              <label for="staffid">Staff ID:</label>
              <input
                type="text"
                id="staffid"
                name="staffid"
                placeholder="Enter Your Staff ID"
                value="<?php echo isset($_POST['staffid']) ? htmlspecialchars($_POST['staffid']) : ($teacher_data ? htmlspecialchars($teacher_data['faculty_id']) : ''); ?>"
                required
              />
            </div>

            <div class="item div3">
              <label for="name">Your Name:</label>
              <input
                type="text"
                id="name"
                name="name"
                placeholder="Enter Your Name"
                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ($teacher_data ? htmlspecialchars($teacher_data['name']) : ''); ?>"
                required
              />
            </div>

            <div class="item div4">
              <label for="department">Department:</label>
              <select id="department" name="department" required>
                <option value="" disabled                                          <?php echo ! isset($_POST['department']) ? 'selected' : ''; ?>>Select Your Department</option>
                <option value="CSE"                                    <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSE') ? 'selected' : ''; ?>>Computer Science and Engineering (CSE)</option>
                <option value="IT"                                   <?php echo(isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : ''; ?>>Information Technology (IT)</option>
                <option value="ECE"                                    <?php echo(isset($_POST['department']) && $_POST['department'] == 'ECE') ? 'selected' : ''; ?>>Electronics and Communication Engineering (ECE)</option>
                <option value="EEE"                                    <?php echo(isset($_POST['department']) && $_POST['department'] == 'EEE') ? 'selected' : ''; ?>>Electrical and Electronics Engineering (EEE)</option>
                <option value="MECH"                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'MECH') ? 'selected' : ''; ?>>Mechanical Engineering (MECH)</option>
                <option value="CIVIL"                                      <?php echo(isset($_POST['department']) && $_POST['department'] == 'CIVIL') ? 'selected' : ''; ?>>Civil Engineering (CIVIL)</option>
                <option value="BME"                                    <?php echo(isset($_POST['department']) && $_POST['department'] == 'BME') ? 'selected' : ''; ?>>Biomedical Engineering (BME)</option>
              </select>
            </div>

            <div class="item div5">
              <label for="event-of-date">Event of Date:</label>
              <input type="date" id="event-of-date" name="event-of-date"
                     value="<?php echo isset($_POST['event-of-date']) ? htmlspecialchars($_POST['event-of-date']) : ''; ?>"
                     required />
            </div>

            <div class="item div6">
              <label for="academic">Academic Year:</label>
              <select id="academic" name="academic" required>
                <option value="" disabled                                          <?php echo ! isset($_POST['academic']) ? 'selected' : ''; ?>>Select Academic Year</option>
                <option value="2025-2026"                                          <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2025-2026') ? 'selected' : ''; ?>>(2025-2026)- ODD</option>
                <option value="2026-2027"                                          <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2026-2027') ? 'selected' : ''; ?>>(2026-2027)-EVEN</option>
                <option value="2027-2028"                                          <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2027-2028') ? 'selected' : ''; ?>>(2027-2028)-ODD</option>
                <option value="2028-2029"                                          <?php echo(isset($_POST['academic']) && $_POST['academic'] == '2028-2029') ? 'selected' : ''; ?>>(2028-2029)-EVEN</option>
              </select>
            </div>

            <div class="item div7">
              <label for="eventType">Event Type:</label>
              <select id="eventType" name="eventType" required>
                <option value="" disabled                                          <?php echo ! isset($_POST['eventType']) ? 'selected' : ''; ?>>Select Event Type</option>
                <option value="FDP"                                    <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'FDP') ? 'selected' : ''; ?>>FDP</option>
                <option value="Workshop"                                         <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'Workshop') ? 'selected' : ''; ?>>Workshop</option>
                <option value="CONFERENCE"                                           <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'CONFERENCE') ? 'selected' : ''; ?>>CONFERENCE</option>
                <option value="INDUSTRIAL TRAINING"                                                    <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'INDUSTRIAL TRAINING') ? 'selected' : ''; ?>>INDUSTRIAL TRAINING</option>
                <option value="STTP"                                     <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'STTP') ? 'selected' : ''; ?>>STTP</option>
                <option value="REVIEWER"                                         <?php echo(isset($_POST['eventType']) && $_POST['eventType'] == 'REVIEWER') ? 'selected' : ''; ?>>REVIEWER</option>
              </select>
            </div>

            <div class="item div8">
              <label for="topic">Topic:</label>
              <input
                type="text"
                id="topic"
                name="topic"
                placeholder="Enter the Topic"
                value="<?php echo isset($_POST['topic']) ? htmlspecialchars($_POST['topic']) : ''; ?>"
                required
              />
            </div>

            <div class="item div9">
              <label for="dates">No of Dates:</label>
              <select id="dates" name="dates" required>
                <option value="" disabled                                          <?php echo ! isset($_POST['dates']) ? 'selected' : ''; ?>>Select No of Dates</option>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                  <option value="<?php echo $i; ?>"<?php echo(isset($_POST['dates']) && $_POST['dates'] == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                <?php endfor; ?>
              </select>
            </div>

            <div class="item div10">
              <label for="from">From:</label>
              <input type="date" id="from" name="from"
                     value="<?php echo isset($_POST['from']) ? htmlspecialchars($_POST['from']) : ''; ?>"
                     required />
            </div>

            <div class="item div11">
              <label for="to">To:</label>
              <input type="date" id="to" name="to"
                     value="<?php echo isset($_POST['to']) ? htmlspecialchars($_POST['to']) : ''; ?>"
                     required />
            </div>

            <div class="item div12">
              <label for="organisation">Organisation By:</label>
              <input
                type="text"
                id="organisation"
                name="organisation"
                placeholder="Enter the Organisation Name"
                value="<?php echo isset($_POST['organisation']) ? htmlspecialchars($_POST['organisation']) : ''; ?>"
                required
              />
            </div>

            <div class="item div13">
              <label for="sponsors">Sponsors by:</label>
              <select id="sponsors" name="sponsors" required>
                <option value="" disabled                                          <?php echo ! isset($_POST['sponsors']) ? 'selected' : ''; ?>>Select The Sponsors</option>
                <option value="AICTE"                                      <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'AICTE') ? 'selected' : ''; ?>>AICTE</option>
                <option value="IBM"                                    <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IBM') ? 'selected' : ''; ?>>IBM</option>
                <option value="INFOSYS SPRINGBOARD"                                                    <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'INFOSYS SPRINGBOARD') ? 'selected' : ''; ?>>INFOSYS SPRINGBOARD</option>
                <option value="IEI"                                    <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IEI') ? 'selected' : ''; ?>>IEI</option>
                <option value="IEEE"                                     <?php echo(isset($_POST['sponsors']) && $_POST['sponsors'] == 'IEEE') ? 'selected' : ''; ?>>IEEE</option>
              </select>
            </div>

            <div class="item div14">
              <label for="certificates">Upload Certificates:</label>
              <input type="file" id="certificates" name="certificates" accept=".pdf" required/>
              <small>Allowed file types: PDF Only</small>
            </div>

            <div class="item div15">
              <input type="submit" value="Register" id="button"/>
            </div>
          </div>
        </div>
      </form>
    </main>
    <footer>
      <p>&copy; 2025 Event Management System. All rights reserved.</p>
    </footer>
    <script src="scripts.js"></script>
  </body>
</html>