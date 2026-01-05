<?php
    // Check if user is already logged in
    session_start();
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Redirect based on user role
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
            header("Location: student/index.php");
        } else {
            header("Location: admin/index.php");
        }
        exit();
    }

    $servername  = "localhost";
    $db_username = "root";
    $db_password = "";
    $dbname      = "event_management_system";

    $conn = new mysqli($servername, $db_username, $db_password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Function to safely check and add status column
    function ensureStatusColumn($conn, $table_name)
    {
        $check_column  = "SHOW COLUMNS FROM $table_name LIKE 'status'";
        $column_result = $conn->query($check_column);

        if ($column_result->num_rows == 0) {
            $add_column = "ALTER TABLE $table_name ADD COLUMN status VARCHAR(20) DEFAULT 'active'";
            $conn->query($add_column);
        }
    }

    // Ensure status column exists in teacher table
    ensureStatusColumn($conn, 'teacher_register');

    $error_message = "";

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['name']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error_message = "Please enter both username and password.";
        } else {
            // Search in both tables: student_register and teacher_register
            $tables          = ['student_register', 'teacher_register'];
            $user_found      = false;
            $hashed_password = "";

            foreach ($tables as $table) {
                // We consider username or personal_email/email for login
                $column_username = $table === 'student_register' ? 'username' : 'username';
                $column_email    = $table === 'student_register' ? 'personal_email' : 'email';

                $sql  = "SELECT username, password FROM $table WHERE $column_username=? OR $column_email=? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $user_found      = true;
                    $actual_username = "";
                    $stmt->bind_result($actual_username, $hashed_password);
                    $stmt->fetch();
                    $stmt->close();
                    break;
                }
                $stmt->close();
            }

            if ($user_found) {
                if (password_verify($password, $hashed_password)) {
                    // Password correct: user logged in successfully
                    // Start a session and save user info
                    session_start();
                    $_SESSION['username']  = $actual_username; // Use actual username, not the login input
                    $_SESSION['role']      = ($table === 'student_register') ? 'student' : 'teacher';
                    $_SESSION['logged_in'] = true;

                    // Redirect based on user role and status
                    if ($_SESSION['role'] === 'student') {
                        header("Location: student/index.php");
                    } else {
                        // For teachers, check their role/status before allowing admin access
                        $teacher_status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
                        $teacher_status_stmt = $conn->prepare($teacher_status_sql);
                        $teacher_status_stmt->bind_param("s", $actual_username); // Use actual username
                        $teacher_status_stmt->execute();
                        $teacher_status_result = $teacher_status_stmt->get_result();

                        $teacher_status = 'teacher'; // Default status
                        if ($teacher_status_result->num_rows > 0) {
                            $status_data    = $teacher_status_result->fetch_assoc();
                            $teacher_status = $status_data['status'];
                        }
                        $teacher_status_stmt->close();

                        // Redirect based on teacher role/status
                        if ($teacher_status === 'inactive') {
                            $_SESSION['access_denied'] = 'Your account is inactive. Please contact an administrator to restore access.';
                            header("Location: teacher/index.php");
                        } elseif ($teacher_status === 'admin') {
                            header("Location: admin/index.php");
                        } else {
                            // Regular teachers go to teacher dashboard
                            header("Location: teacher/index.php");
                        }
                    }
                    exit();
                } else {
                    $error_message = "Invalid username or password.";
                }
            } else {
                $error_message = "Invalid username or password.";
            }
        }
    }

    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Event Management - Login</title>
    <link rel="icon" type="image/png" sizes="32x32" href="./asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="./asserts/images/favicon_io/site.webmanifest">
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
  box-sizing: border-box;
}
.header-logo {
   margin-left: 10px;
}
img{
   width: 200px;
   height: 60px;
   border-radius: 5px;
}
.header-title {
   font-size: 24px;
   font-weight: 400;
   flex: 1;
   text-align: center;
}
.empty {
   flex-shrink: 0;
   width: 200px;
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
         url("sona_login_img.jpg");
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: scroll;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
   }

   main {
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 20px 15px;
      box-sizing: border-box;
      width: 100%;
      margin: 0;
   }

   form {
      width: 100%;
      max-width: 400px;
      margin: 0 auto;
   }

   .form-container {
      width: 100%;
      margin: 0 auto;
      padding: 30px 20px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(15px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
   }

   .header{
      justify-content: center;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 0 15px;
      height: 70px;
   }

   .header-logo {
      display: none;
   }

   .header-title {
      font-size: 18px;
      text-align: center;
   }

   .empty{
      display: none;
   }

   .form-title {
      font-size: 24px;
      margin-bottom: 25px;
      text-align: center;
   }

   /* Enhanced input styling for mobile transparency */
   .form-container input[type="text"],
   .form-container input[type="password"] {
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(5px);
      font-size: 16px;
      padding: 12px 15px;
   }

   .form-container input[type="text"]:focus,
   .form-container input[type="password"]:focus {
      background: rgba(255, 255, 255, 1);
      border: 1px solid rgba(30, 66, 118, 0.5);
      box-shadow: 0 0 10px rgba(30, 66, 118, 0.2);
   }

   /* Submit button transparency */
   .form-container input[type="submit"] {
      background: rgba(30, 66, 118, 0.9);
      backdrop-filter: blur(5px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      font-size: 16px;
      padding: 12px 20px;
   }

   .form-container input[type="submit"]:hover {
      background: rgba(30, 66, 118, 1);
      transform: translateY(-1px);
      box-shadow: 0 4px 15px rgba(30, 66, 118, 0.3);
   }

   .form-container p {
      font-size: 14px;
      text-align: center;
      margin-top: 15px;
   }
 }

/* Extra small mobile devices */
@media (max-width: 480px) {
   main {
      padding: 15px 10px;
      min-height: 100vh;
   }

   form {
      max-width: 100%;
   }

   .form-container {
      padding: 25px 15px;
      border-radius: 12px;
   }

   .header {
      height: 60px;
      padding: 0 10px;
   }

   .header-title {
      font-size: 16px;
   }

   .form-title {
      font-size: 20px;
      margin-bottom: 20px;
   }

   .form-container input[type="text"],
   .form-container input[type="password"] {
      font-size: 16px;
      padding: 10px 12px;
   }

   .form-container input[type="submit"] {
      font-size: 14px;
      padding: 10px 15px;
   }

   .form-container p {
      font-size: 13px;
   }
}

/* Background image for laptop/desktop screens only */
@media (min-width: 1024px) {
   body {
      background-image:linear-gradient(135deg,
             rgba(30, 66, 118, 0.8) 0%,
             rgba(45, 90, 160, 0.6) 25%,
             rgba(30, 66, 118, 0.5) 50%,
             rgba(45, 90, 160, 0.6) 75%,
             rgba(30, 66, 118, 0.8) 100%), url('./sona_login_img.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
      position: relative;
   }

   body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(45deg,
         rgba(30, 66, 118, 0.1) 0%,
         transparent 25%,
         transparent 75%,
         rgba(30, 66, 118, 0.1) 100%);
      z-index: -1;
      pointer-events: none;
   }

   main {
      position: relative;
      z-index: 1;
   }

   /* Enhanced glass effect for login form on desktop */
   .registration-main {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(25px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 25px 45px rgba(0, 0, 0, 0.0);
      border-radius: 20px;
      padding: 40px;
   }
}
    </style>
</head>
<body>
<main>
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

    <form action="index.php" method="POST">
        <div class="form-container">
            <h2 class="form-title">Login</h2>

            <div class="form-group">
                <input type="text" name="name" placeholder="Username or Email" required />
            </div>
            <div class="form-group" style="position: relative;">
                <input type="password" name="password" id="password" placeholder="Password" required />
                <button type="button" id="togglePassword" style="
                    position: absolute;
                    right: 15px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: none;
                    border: none;
                    cursor: pointer;
                    color: #666;
                    font-size: 18px;
                    padding: 0;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: color 0.3s ease;
                " title="Show/Hide Password">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>

            <?php
                if (! empty($error_message)) {
                    echo "<div class='message error-message'>$error_message</div>";
                }

                // Check for logout success message
                if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
                    echo "<div class='message success-message'>You have been logged out successfully.</div>";
                }
            ?>
            <div class="form-group">
                <input type="submit" value="Login" id="button" />
            </div>
            <p>Not Yet Registered?<a href="role.html"> Signup</a></p>
            <p><a href="forgot_password_dob.php">Forgot Password?</a></p>
        </div>
    </form>
</main>



<script>
// Prevent back button after logout
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

window.addEventListener('popstate', function(event) {
    window.history.replaceState(null, null, window.location.href);
});

// Password visibility toggle
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    // Define SVG icons
    const eyeOpenIcon = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
    `;

    const eyeClosedIcon = `
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        </svg>
    `;

    if (passwordField && toggleButton) {
        toggleButton.addEventListener('click', function() {
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.innerHTML = eyeClosedIcon;
                toggleButton.title = 'Hide Password';
                toggleButton.style.color = '#0c3878';
            } else {
                passwordField.type = 'password';
                toggleButton.innerHTML = eyeOpenIcon;
                toggleButton.title = 'Show Password';
                toggleButton.style.color = '#666';
            }
        });

        // Add hover effect
        toggleButton.addEventListener('mouseenter', function() {
            if (passwordField.type === 'password') {
                this.style.color = '#0c3878';
            } else {
                this.style.color = '#333';
            }
        });

        toggleButton.addEventListener('mouseleave', function() {
            if (passwordField.type === 'password') {
                this.style.color = '#666';
            } else {
                this.style.color = '#0c3878';
            }
        });
    }
});
</script>
</body>
</html>
