<?php
    session_start();

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $message = "";

    // Handle password reset request
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
        $username = trim($_POST['username']);

        if (empty($username)) {
            $message = "<div class='error'>Please enter your username.</div>";
        } else {
            // Search for user in student_register table (only students have DOB)
            $sql  = "SELECT username, name, dob FROM student_register WHERE username=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                $dob       = $user_data['dob'];
                $name      = $user_data['name'];

                if (! empty($dob)) {
                    // Format DOB as password (remove dashes: 1999-05-15 becomes 19990515)
                    $new_password    = str_replace('-', '', $dob);
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password
                    $update_sql  = "UPDATE student_register SET password=? WHERE username=?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ss", $hashed_password, $username);

                    if ($update_stmt->execute()) {
                        $message = "<div class='success'>
                        <h3>✅ Password Reset Successful!</h3>
                        <p><strong>Hello $name,</strong></p>
                        <p>Your password has been reset to your <strong>Date of Birth</strong>.</p>
                        <div class='password-info'>
                            <p><strong>Your new password format:</strong> YYYYMMDD</p>
                            <p><em>Example: If your DOB is 15-May-1999, your password is: 19990515</em></p>
                        </div>
                        <p>Please login with your new password and consider changing it in your profile for security.</p>
                    </div>";
                    } else {
                        $message = "<div class='error'>Error updating password. Please try again.</div>";
                    }
                    $update_stmt->close();
                } else {
                    $message = "<div class='error'>Date of Birth not found in your profile. Please contact admin.</div>";
                }
            } else {
                // Check if username exists in teacher_register
                $teacher_sql  = "SELECT username, name FROM teacher_register WHERE username=?";
                $teacher_stmt = $conn->prepare($teacher_sql);
                $teacher_stmt->bind_param("s", $username);
                $teacher_stmt->execute();
                $teacher_result = $teacher_stmt->get_result();

                if ($teacher_result->num_rows > 0) {
                    $message = "<div class='error'>
                    Password reset by DOB is only available for <strong>Students</strong>.<br>
                    Teachers, please contact the administrator for password reset.
                </div>";
                } else {
                    $message = "<div class='error'>Username not found. Please check your username and try again.</div>";
                }
                $teacher_stmt->close();
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
    <title>Reset Password - Event Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="./asserts/images/Sona Logo.png" />
    <link rel="stylesheet" href="styles.css" />
    <style>
        .password-info {
            background: #e7f3ff;
            border: 2px solid #1e4276;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .success, .error {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            color: #856404;
        }

        .info-box h4 {
            color: #856404;
            margin-top: 0;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #1e4276;
            text-decoration: none;
            font-weight: 500;
            padding: 10px 15px;
            border: 1px solid #1e4276;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #1e4276;
            color: white;
        }

        .login-link {
            display: inline-block;
            margin-top: 10px;
            color: white;
            background: #28a745;
            text-decoration: none;
            font-weight: 500;
            padding: 12px 20px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .login-link:hover {
            background: #218838;
        }

        .form-container{
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            width: auto;
        }
        form {
        display: flex;
        flex-direction: column;
        align-items: center;
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

    <main class="forget-main">
        <div class="form-container">
            <h2 class="form-title">Reset Password</h2>

            <?php if (! empty($message)): ?>
                <?php echo $message; ?>

                <?php if (strpos($message, 'successful') !== false || strpos($message, 'Success') !== false): ?>
                    <div style="text-align: center;">
                        <a href="index.php" class="login-link">Go to Login Page</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <form method="POST">
                    <div class="item">
                        <label for="username">Enter Your Username:</label>
                        <input
                            type="text"
                            name="username"
                            placeholder="Enter your student username"
                            required
                            autocomplete="username"
                        />
                    </div>
                    <div class="item">
                        <input type="submit" name="reset_password" value="Reset Password" id="button" />
                    </div>
                </form>
            <?php endif; ?>

            <a href="index.php" class="back-link">← Back to Login</a>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Event Management System. All rights reserved.</p>
    </footer>

    <script>
        // Auto-focus username input
        document.addEventListener('DOMContentLoaded', function() {
            const usernameInput = document.querySelector('input[name="username"]');
            if (usernameInput) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>