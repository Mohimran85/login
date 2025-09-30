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

                $sql  = "SELECT password FROM $table WHERE $column_username=? OR $column_email=? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $user_found = true;
                    $stmt->bind_result($hashed_password);
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
                    $_SESSION['username']  = $username;
                    $_SESSION['role']      = ($table === 'student_register') ? 'student' : 'teacher';
                    $_SESSION['logged_in'] = true;

                    // Redirect based on user role
                    if ($_SESSION['role'] === 'student') {
                        header("Location: student/index.php");
                    } else {
                        header("Location: admin/index.php"); 
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Event Management - Login</title>
    <link rel="icon" type="icon/png" sizes="32x32" href="./asserts/images/Sona Logo.png" />
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
<main>
    <div class="login-header">
        <!-- <div class="icon">
            <img src="./asserts/images/Sona Logo.png" alt="Sona College Logo" />
        </div> -->
        <h1>Event Management System</h1>
    </div>

    <form action="index.php" method="POST">
        <div class="form-container">
            <h2 class="form-title">Login</h2>

            <div class="form-group">
                <input type="text" name="name" placeholder="Username or Email" required />
            </div>
            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required />
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

<footer>
    <p>&copy; 2025 Event Management System. All rights reserved.</p>
</footer>

<script>
// Prevent back button after logout
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

window.addEventListener('popstate', function(event) {
    window.history.replaceState(null, null, window.location.href);
});
</script>
</body>
</html>
