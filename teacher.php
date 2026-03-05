<?php
    require_once 'includes/db_config.php';
    $conn = get_db_connection();

    $success_message = "";
    $error_messages  = [];

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $name         = trim($_POST["name"]);
    $username     = trim($_POST["Username"]);
    $faculty_id   = trim($_POST["regno"]);
    $year_of_join = $_POST["batch"];
    $department   = $_POST["department"];
    $email        = trim($_POST["email"]);
    $password     = $_POST["password"];
    $re_password  = $_POST["re-password"];

    // Validation
    if (empty($name) || empty($username) || empty($faculty_id) || empty($year_of_join) ||
        empty($department) || empty($email) || empty($password) || empty($re_password)) {
        $error_messages[] = "Please fill all required fields.";
    }
    if ($password !== $re_password) {
        $error_messages[] = "Passwords do not match.";
    }
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_messages[] = "Invalid email format.";
    }
    if (strlen($password) < 6) {
        $error_messages[] = "Password must be at least 6 characters long.";
    }

    // Check username, faculty_id & email uniqueness
    if (empty($error_messages)) {
        $check_query = "SELECT id FROM teacher_register WHERE username=? OR faculty_id=? OR email=?";
        $stmt        = $conn->prepare($check_query);
        $stmt->bind_param("sss", $username, $faculty_id, $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_messages[] = "Username, Faculty ID, or email already exists.";
        }
        $stmt->close();
    }

    // Insert if no errors
    if (empty($error_messages)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql             = "INSERT INTO teacher_register
                (name, username, faculty_id, year_of_join, department, email, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssss",
            $name, $username, $faculty_id, $year_of_join, $department, $email, $hashed_password
        );
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
        $stmt->close();
    }
    }
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Teacher Registration</title>
    <link rel="icon" type="icon/png" sizes="32x32" href="./assets/images/Sona Logo.png" />
    <link rel="stylesheet" href="styles.css" />
    <style>
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
    </style>
    <script>
        function checkpassword() {
            var pass = document.getElementById('password').value;
            var re_pass = document.getElementById('re-password').value;
            if (pass !== re_pass) {
                alert("Passwords do not match.");
                event.preventDefault(); // Cancel form submission
            }
        }

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
    </script>
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
    <form action="" method="POST" class="registration-form" onsubmit="return checkpassword();">
        <div class="registration-container">
            <h2 class="form-title">Faculty Registration</h2>

            <?php
                if (! empty($success_message)) {
                    echo "<div style='color:green; font-weight:bold;'>$success_message</div>";
                }
                if (! empty($error_messages)) {
                    foreach ($error_messages as $err) {
                        echo "<div style='color:red;'>" . htmlspecialchars($err) . "</div>";
                    }
                }
            ?>

            <div class="parent">
                <div class="item div2">
                    <label for="name">Faculty Name:</label>
                    <input type="text" name="name" placeholder="Faculty Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required />
                </div>
                <div class="item div3">
                    <label for="Username">Username:</label>
                    <input type="text" name="Username" placeholder="Username" value="<?php echo isset($_POST['Username']) ? htmlspecialchars($_POST['Username']) : ''; ?>" required />
                </div>
                <div class="item div4">
                    <label for="regno">Faculty ID:</label>
                    <input type="text" name="regno"  placeholder="Faculty Id" value="<?php echo isset($_POST['regno']) ? htmlspecialchars($_POST['regno']) : ''; ?>" required />
                </div>
                <div class="item div5">
                    <label for="batch">Year of Join:</label>
                    <input type="date" name="batch" placeholder="Year of Join" value="<?php echo isset($_POST['batch']) ? htmlspecialchars($_POST['batch']) : ''; ?>" required />
                </div>
                <div class="item div6">
                    <label for="department">Department:</label>
                    <select name="department" required>
                        <option value="" disabled                                                                                                                                                                                                     <?php echo ! isset($_POST['department']) ? 'selected' : ''; ?>>Select The Department</option>
                        <option value="Information Technology"                                                                                                                                                                         <?php echo(isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : ''; ?>>Information Technology</option>
                        <option value="Computer Science and Engineering"                                                                                                                                                                             <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSE') ? 'selected' : ''; ?>>Computer Science and Engineering</option>
                        <option value="Electronics and Communication Engineering"                                                                                                                                                                             <?php echo(isset($_POST['department']) && $_POST['department'] == 'ECE') ? 'selected' : ''; ?>>Electronics and Communication Engineering</option>
                        <option value="Electrical and Electronics Engineering"                                                                                                                                                                             <?php echo(isset($_POST['department']) && $_POST['department'] == 'EEE') ? 'selected' : ''; ?>>Electrical and Electronics Engineering</option>
                        <option value="Mechanical Engineering"                                                                                                                                                                                 <?php echo(isset($_POST['department']) && $_POST['department'] == 'MECH') ? 'selected' : ''; ?>>Mechanical Engineering</option>
                        <option value="Civil Engineering"                                                                                                                                                                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'CIVIL') ? 'selected' : ''; ?>>Civil Engineering</option>
                        <option value="Artificial Intelligence and Machine Learning"                                                                                                                                                                                                                     <?php echo(isset($_POST['department']) && $_POST['department'] == 'AIML') ? 'selected' : ''; ?>>Artificial Intelligence and Machine Learning</option>
                        <option value="Artificial Intelligence and Data Science"                                                                                                                                                                                                              <?php echo(isset($_POST['department']) && $_POST['department'] == 'ADS') ? 'selected' : ''; ?>>Artificial Intelligence and Data Science</option>
                        <option value="Fashion Technology"                                                                                                                                                                                                       <?php echo(isset($_POST['department']) && $_POST['department'] == 'FT') ? 'selected' : ''; ?>>Fashion Technology</option>
                        <option value="EXE"                                                                                                                                                                                                              <?php echo(isset($_POST['department']) && $_POST['department'] == 'EXE') ? 'selected' : ''; ?>>EXE</option>
                        <option value="Computer Science and Design"                                                                                                                                                                                                              <?php echo(isset($_POST['department']) && $_POST['department'] == 'CSD') ? 'selected' : ''; ?>>Computer Science and Design</option>
                    </select>
                </div>
                <div class="item div7">
                    <label for="email">Email:</label>
                    <input type="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required />
                </div>
                <div class="item div8">
                    <label for="password">Password:</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Password" required />
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
                        <input type="password" name="re-password" id="re-password" placeholder="Re-enter Password" required />
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
</body>
</html>
