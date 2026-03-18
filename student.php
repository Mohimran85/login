<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Student Registration</title>
      <link rel="icon" type="icon/png" sizes="32x32" href="./assets/images/Sona Logo.png" />
      <link rel="stylesheet" href="styles.css" />
      <style>
         .error { color: red; margin: 5px 0; }
         .success { color: green; font-weight: bold; margin: 5px 0; }

         /* Password toggle styles */
         .password-container {
            position: relative;
            display: flex;
            align-items: center;
         }

         .password-container input {
            padding-right: 45px !important;
         }

         .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            z-index: 10;
         }

         .password-toggle:hover {
            color: #1e4276;
         }

         .password-toggle svg {
            width: 20px;
            height: 20px;
         }
         .header {
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
  padding: 0 20px;
  box-sizing: border-box;
}
.header-logo {
   flex-shrink: 0;
}
.header-logo img{
   width: 200px;
   height: 60px;
   object-fit: contain;
   display: block;
}
.header-title {
   font-size: 24px;
   font-weight: 400;
   flex: 1;
   text-align: center;
}
.header-title p {
   margin: 0;
}
.empty {
   flex-shrink: 0;
   width: 200px;
}
/* Mobile Responsive Improvements */
.registration-main {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  min-height: calc(100vh - 80px);
  padding: 20px 10px;
  margin-top: 100px;
  width: 100%;
  box-sizing: border-box;
}

.registration-form {
  width: 100%;
  max-width: 900px;
  margin: 0 auto;
}

.registration-container {
  background: #fff;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
  width: 100%;
  padding: 30px 25px;
  border-radius: 15px;
  margin: 0 auto;
  box-sizing: border-box;
}

.parent {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px 15px;
  width: 100%;
}

.item {
  width: 100%;
  box-sizing: border-box;
}

.item input, .item select {
  width: 100%;
  box-sizing: border-box;
}

@media (max-width: 768px) {
  .header {
    justify-content: center;
    padding: 0 15px;
  }

  .header-logo {
    display: none;
  }

  .empty {
    display: none;
  }

  .registration-main {
    margin-top: 90px;
    padding: 15px 10px;
    min-height: calc(100vh - 90px);
  }

  .registration-container {
    padding: 25px 20px;
    margin: 0;
    border-radius: 12px;
  }

  .parent {
    grid-template-columns: 1fr;
    gap: 15px;
  }

  .form-title {
    font-size: 24px;
    margin-bottom: 20px;
  }
}

@media (max-width: 480px) {
  .header {
    justify-content: center;
    padding: 0 10px;
    height: 70px;
  }

  .header-logo {
    display: none;
  }

  .empty {
    display: none;
  }

  .registration-main {
    margin-top: 80px;
    padding: 10px 5px;
    min-height: calc(100vh - 80px);
  }

  .registration-container {
    padding: 20px 15px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
  }

  .parent {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .form-title {
    font-size: 20px;
    margin-bottom: 15px;
  }

  .item input, .item select {
    padding: 12px 15px;
    font-size: 16px;
    border-radius: 8px;
  }

  .item label {
    font-size: 14px;
    margin-bottom: 5px;
  }

  #button {
    padding: 15px;
    font-size: 16px;
    border-radius: 8px;
  }
}

/* Global body styling with fallback */
body {
  background-color: #f0f4f8 !important;
  background-image: url("sona_login_img.jpg") !important;
  background-size: cover !important;
  background-position: center !important;
  background-repeat: no-repeat !important;
  background-attachment: scroll !important;
  min-height: 100vh;
}

/* Mobile and Tablet Styles with Background Image */
@media (max-width: 1023px) {
  body {
    background: linear-gradient(135deg,
       rgba(30, 66, 118, 0.8) 0%,
       rgba(45, 90, 160, 0.6) 25%,
       rgba(30, 66, 118, 0.5) 50%,
       rgba(45, 90, 160, 0.6) 75%,
       rgba(30, 66, 118, 0.8) 100%),
       url("sona_login_img.jpg") !important;
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
    background-attachment: scroll !important;
    min-height: 100vh;
    position: relative;
  }

  .header {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
  }

  .registration-container {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
  }

  /* Enhanced input styling for mobile */
  .registration-container input[type="text"],
  .registration-container input[type="email"],
  .registration-container input[type="password"],
  .registration-container input[type="date"],
  .registration-container select {
    background: #ffffff !important;
    border: 1px solid #ddd !important;
  }

  .registration-container input:focus,
  .registration-container select:focus {
    background: #ffffff !important;
    border: 1px solid rgba(30, 66, 118, 0.5) !important;
    box-shadow: 0 0 10px rgba(30, 66, 118, 0.2);
  }

  /* Submit button transparency */
  .registration-container #button {
    background: rgba(30, 66, 118, 0.9) !important;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: white !important;
  }

  .registration-container #button:hover {
    background: rgba(30, 66, 118, 1) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(30, 66, 118, 0.3);
  }
}

/* Background image for laptop/desktop screens only */
@media (min-width: 1024px) {
  body {
    background: linear-gradient(
        135deg,
        rgba(30, 66, 118, 0.7) 0%,
        rgba(45, 90, 160, 0.5) 25%,
        rgba(30, 66, 118, 0.4) 50%,
        rgba(45, 90, 160, 0.5) 75%,
        rgba(30, 66, 118, 0.7) 100%
      ),
      url("sona_login_img.jpg") !important;
    background-size: cover !important;
    background-position: center !important;
    background-repeat: no-repeat !important;
    background-attachment: fixed !important;
    position: relative;
    min-height: 100vh;
  }

  body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
      45deg,
      rgba(30, 66, 118, 0.1) 0%,
      transparent 25%,
      transparent 75%,
      rgba(30, 66, 118, 0.1) 100%
    );
    z-index: -1;
    pointer-events: none;
  }

  main {
    position: relative;
    z-index: 1;
  }

  /* Enhanced form styling for desktop */
  .registration-container {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(25px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 25px 45px rgba(0, 0, 0, 0.15);
  }
}
      </style>
   </head>
   <body>
      <div class="header">
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
          <p>Event Management System</p>
        </div>
        <div class="empty">
          <!-- empty -->
        </div>
      </div>
      <main class="registration-main">
<?php
    require_once 'includes/db_config.php';
    $conn = get_db_connection();

    $success_message = "";
    $error_messages  = [];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $name           = trim($_POST["name"]);
    $dob            = $_POST["dob"];
    $username       = trim($_POST["username"]);
    $regno          = trim($_POST["regno"]);
    $year_of_join   = $_POST["batch"];
    $degree         = $_POST["degree"];
    $department     = $_POST["department"];
    $personal_email = trim($_POST["personal_email"]);
    $password       = $_POST["password"];
    $re_password    = $_POST["re-password"];

    // Auto-calculate semester from year_of_join
    // Sem 1: Jul-Dec of join year, 6 months each, 8 semesters total
    $join_yr      = (int) $year_of_join;
    $now          = new DateTime();
    $reg_yr       = (int) $now->format('Y');
    $reg_mo       = (int) $now->format('n');
    $months_since = ($reg_yr - $join_yr) * 12 + ($reg_mo - 7);
    $semester     = ($months_since < 0) ? 1 : min(max((int) floor($months_since / 6) + 1, 1), 8);

    // Validation
    if (empty($name) || empty($dob) || empty($username) || empty($regno) || empty($year_of_join) ||
        empty($degree) || empty($department) || empty($personal_email) || empty($password) || empty($re_password)) {
        $error_messages[] = "Please fill all required fields.";
    }
    if ($password !== $re_password) {
        $error_messages[] = "Passwords do not match.";
    }
    if (! filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Invalid email format.";
    }
    if (strlen($password) < 6) {
        $error_messages[] = "Password must be at least 6 characters long.";
    }
    // Stronger password policy
    if (! preg_match("/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&]).{6,}$/", $password)) {
        $error_messages[] = "Password must include uppercase, lowercase, number, and special character.";
    }

    // Check username & email uniqueness
    if (empty($error_messages)) {
        $check_query = "SELECT id FROM student_register WHERE username=? OR personal_email=?";
        $stmt        = $conn->prepare($check_query);
        $stmt->bind_param("ss", $username, $personal_email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_messages[] = "Username or email already exists.";
        }
        $stmt->close();
    }

    // Insert if no errors
    if (empty($error_messages)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql             = "INSERT INTO student_register
                (name, dob, username, regno, year_of_join, degree, department, semester, personal_email, password, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'student')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssss",
            $name, $dob, $username, $regno, $year_of_join, $degree, $department, $semester, $personal_email, $hashed_password
        );

        try {
            if ($stmt->execute()) {
                // Set success message and redirect after showing it
                $success_message = "Registration Successful! Redirecting to login page...";
                echo "<script>
                    // Show success popup
                    alert('Registration Successful!');
                    // Wait 2 seconds then redirect
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 2000);
                </script>";
            } else {
                $error_messages[] = "Database error: Registration failed.";
            }
        } catch (mysqli_sql_exception $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == 1062) {
                // Check which field is duplicate
                if (strpos($e->getMessage(), 'regno') !== false) {
                    $error_messages[] = "Registration number already exists! A student with registration number '$regno' is already registered in the system.";
                } elseif (strpos($e->getMessage(), 'username') !== false) {
                    $error_messages[] = "Username already exists! Please choose a different username.";
                } elseif (strpos($e->getMessage(), 'personal_email') !== false) {
                    $error_messages[] = "Email already exists! This email is already registered.";
                } else {
                    $error_messages[] = "This user already exists in the system. Please check your registration details.";
                }
            } else {
                $error_messages[] = "Database error: Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
    }
    $conn->close();
?>
         <form action="" method="POST" class="registration-form">
            <div class="registration-container">
               <h2 class="form-title">Student Registration</h2>

<?php
    if (! empty($success_message)) {
    echo "<div class='success'>$success_message</div>";
    }
    if (! empty($error_messages)) {
    foreach ($error_messages as $err) {
        echo "<div class='error'>" . htmlspecialchars($err) . "</div>";
    }
    }
?>

               <div class="parent">
                  <div class="item div2">
                     <label for="student-name">Name:</label>
                     <input type="text" id="student-name" name="name" placeholder="Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required />
                  </div>
                  <div class="item div3">
                     <label for="dob">Date Of Birth:</label>
                     <input type="date" name="dob" id="dob" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>" required />
                  </div>
                  <div class="item div3">
                     <label for="username">Username:</label>
                     <input type="text" id="username" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required />
                  </div>
                  <div class="item div4">
                     <label for="registration-number">Registration Number:</label>
                     <input type="text" id="registration-number" inputmode="numeric" pattern="\d{14}" title="Enter 12-digit reg no" name="regno" placeholder="Registration No" value="<?php echo isset($_POST['regno']) ? htmlspecialchars($_POST['regno']) : ''; ?>" required />
                  </div>
                  <div class="item div5">
                     <label for="year_of_join">Year of Join:</label>
                     <select name="batch" id="year_of_join" required>
                        <option value="" disabled                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo ! isset($_POST['batch']) ? 'selected' : ''; ?>>Select The year</option>
                        <option value="2020"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2020') ? 'selected' : ''; ?>>2020-24</option>
                        <option value="2021"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2021') ? 'selected' : ''; ?>>2021-25</option>
                        <option value="2022"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2022') ? 'selected' : ''; ?>>2022-26</option>
                        <option value="2023"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2023') ? 'selected' : ''; ?>>2023-27</option>
                        <option value="2024"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2024') ? 'selected' : ''; ?>>2024-28</option>
                        <option value="2025"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2025') ? 'selected' : ''; ?>>2025-29</option>
                        <option value="2026"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['batch']) && $_POST['batch'] == '2026') ? 'selected' : ''; ?>>2026-30</option>
                     </select>
                  </div>
                  <div class="item div5">
                     <label for="degree">Degree</label>
                     <select name="degree" id="degree" required>
                        <option value="" disabled                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo ! isset($_POST['degree']) ? 'selected' : ''; ?>>Select The Degree</option>
                        <option value="b.tech"                                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'b.tech') ? 'selected' : ''; ?>>B.Tech</option>
                        <option value="m.tech"                                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'm.tech') ? 'selected' : ''; ?>>M.Tech</option>
                        <option value="B.E"                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'B.E') ? 'selected' : ''; ?>>B.E</option>
                        <option value="M.E"                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'M.E') ? 'selected' : ''; ?>>M.E</option>
                        <option value="bba"                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'bba') ? 'selected' : ''; ?>>BBA</option>
                        <option value="mba"                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['degree']) && $_POST['degree'] == 'mba') ? 'selected' : ''; ?>>MBA</option>
                     </select>
                  </div>
                  <div class="item div5">
                     <label for="department">Department</label>
                     <select name="department" id="department" required>
                        <option value="" disabled                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo ! isset($_POST['department']) ? 'selected' : ''; ?>>Select the Department</option>
                        <option value="Information Technology"                                                                                                                                                                                                                                                                                                                                                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : ''; ?>>Information Technology</option>
                        <option value="Computer Science and Engineering"                                                                                                                                                                                                                                                                                                                                                                                      <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSE') ? 'selected' : ''; ?>>Computer Science and Engineering</option>
                        <option value="Electronics and Communication Engineering"                                                                                                                                                                                                                                                                                                                                                                                               <?php echo(isset($_POST['department']) && $_POST['department'] == 'ECE') ? 'selected' : ''; ?>>Electronics and Communication Engineering</option>
                        <option value="Electrical and Electronics Engineering"                                                                                                                                                                                                                                                                                                                                                                                            <?php echo(isset($_POST['department']) && $_POST['department'] == 'EEE') ? 'selected' : ''; ?>>Electrical and Electronics Engineering</option>
                        <option value="Mechanical Engineering"                                                                                                                                                                                                                                                                                                                                                                                   <?php echo(isset($_POST['department']) && $_POST['department'] == 'MECH') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                        <option value="Civil Engineering"                                                                                                                                                                                                                                                                                                                                                                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'CIVIL') ? 'selected' : ''; ?>>Civil Engineering</option>
                        <option value="Artificial Intelligence and Machine Learning"                                                                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['department']) && $_POST['department'] == 'AIML') ? 'selected' : ''; ?>>Artificial Intelligence and Machine Learning</option>
                        <option value="Artificial Intelligence and Data Science"                                                                                                                                                                                                                                                                                                                                                                                              <?php echo(isset($_POST['department']) && $_POST['department'] == 'ADS') ? 'selected' : ''; ?>>Artificial Intelligence and Data Science</option>
                        <option value="Fashion Technology"                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['department']) && $_POST['department'] == 'FT') ? 'selected' : ''; ?>>Fashion Technology</option>
                        <option value="EXE"                                                                                                                                                                                                                                                                                                                                                         <?php echo(isset($_POST['department']) && $_POST['department'] == 'EXE') ? 'selected' : ''; ?>>EXE</option>
                        <option value="Computer Science and Design"                                                                                                                                                                                                                                                                                                                                                                                 <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSD') ? 'selected' : ''; ?>>Computer Science and Design</option>
                     </select>
                  </div>
                  <div class="item div5">
                     <label for="semester">Semester (Auto-calculated)</label>
                     <input type="text" id="semester" name="semester_display" placeholder="Select batch year first" readonly style="background:#f5f5f5; cursor:default;" />
                  </div>
                  <div class="item div7">
                     <label for="email">Personal Email:</label>
                     <input type="email" inputmode="email" id="email" name="personal_email" placeholder="Personal Email" value="<?php echo isset($_POST['personal_email']) ? htmlspecialchars($_POST['personal_email']) : ''; ?>" required />
                  </div>
                  <div class="item div8">
                     <label for="password">Password:</label>
                     <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Password" minlength="6" required />
                        <span class="password-toggle" onclick="togglePassword('password')">
                           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                              <circle cx="12" cy="12" r="3"></circle>
                           </svg>
                        </span>
                     </div>
                  </div>
                  <div class="item div9">
                     <label for="re-password">Re Enter Password:</label>
                     <div class="password-container">
                        <input type="password" name="re-password" id="re-password" placeholder="Re Enter Password" minlength="6" required />
                        <span class="password-toggle" onclick="togglePassword('re-password')">
                           <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                              <circle cx="12" cy="12" r="3"></circle>
                           </svg>
                        </span>
                     </div>
                  </div>
                  <div class="item div11">
                     <input type="submit" value="Register" id="button" />
                  </div>
               </div>
            </div>
         </form>
      </main>
      <footer>
         <p>&copy; 2025 Event Management System. All rights reserved.</p>
      </footer>
      <script src="scripts.js"></script>
      <script>
         function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = passwordField.parentElement.querySelector('.password-toggle svg');

            if (passwordField.type === 'password') {
               passwordField.type = 'text';
               // Change to eye-off icon
               toggleIcon.innerHTML = `
                  <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                  <line x1="1" y1="1" x2="23" y2="23"></line>
               `;
            } else {
               passwordField.type = 'password';
               // Change back to eye icon
               toggleIcon.innerHTML = `
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
               `;
            }
         }

         // Auto-calculate semester from batch year
         function updateSemesterDisplay() {
            const joinYear = parseInt(document.getElementById('year_of_join').value);
            const semField = document.getElementById('semester');
            if (!joinYear) { semField.value = ''; return; }
            const now = new Date();
            const curYr = now.getFullYear();
            const curMo = now.getMonth() + 1; // 1-based
            const monthsSince = (curYr - joinYear) * 12 + (curMo - 7);
            const sem = monthsSince < 0 ? 1 : Math.min(Math.max(Math.floor(monthsSince / 6) + 1, 1), 8);
            semField.value = 'Semester ' + sem;
         }
         document.addEventListener('DOMContentLoaded', function () {
            const batchSelect = document.getElementById('year_of_join');
            if (batchSelect) {
               batchSelect.addEventListener('change', updateSemesterDisplay);
               updateSemesterDisplay();
            }
         });
      </script>
   </body>
</html>
