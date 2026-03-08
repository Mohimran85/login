<?php
    session_start();
    require_once 'includes/db_config.php';
    require_once 'includes/csrf.php';
    require_once 'includes/TotpManager.php';

    $conn = get_db_connection();
    $totp = new TotpManager();

    $message = "";
    $step    = $_SESSION['reset_step'] ?? 1;

    // Handle cancel / start over
    if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_step'], $_SESSION['reset_username'], $_SESSION['reset_table'],
        $_SESSION['reset_name'], $_SESSION['reset_role'], $_SESSION['reset_2fa_attempts']);
    header("Location: forgot_password_dob.php");
    exit();
    }

    // ─── STEP 1: Look up user by email or username ───
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['find_user'])) {
    if (! validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='error'>Invalid request. Please try again.</div>";
    } else {
        $input = trim($_POST['username']);

        if (empty($input)) {
            $message = "<div class='error'>Please enter your username or email.</div>";
        } else {
            $found_user  = null;
            $found_table = null;

            // Search student_register (email column = personal_email)
            $sql  = "SELECT username, name FROM student_register WHERE username=? OR personal_email=? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $input, $input);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $found_user  = $result->fetch_assoc();
                $found_table = 'student_register';
            }
            $stmt->close();

            // If not found in students, search teacher_register (email column = email)
            if (! $found_user) {
                $sql  = "SELECT username, name FROM teacher_register WHERE username=? OR email=? LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $input, $input);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $found_user  = $result->fetch_assoc();
                    $found_table = 'teacher_register';
                }
                $stmt->close();
            }

            if ($found_user) {
                $username = $found_user['username'];
                $name     = $found_user['name'];
                $role     = ($found_table === 'student_register') ? 'student' : 'teacher';

                // Check if 2FA is enabled
                if ($totp->isEnabled($conn, $username, $found_table)) {
                    // Store info in session and move to step 2
                    $_SESSION['reset_step']         = 2;
                    $_SESSION['reset_username']     = $username;
                    $_SESSION['reset_table']        = $found_table;
                    $_SESSION['reset_name']         = $name;
                    $_SESSION['reset_role']         = $role;
                    $_SESSION['reset_2fa_attempts'] = 0;
                    $step                           = 2;
                } else {
                    $message = "<div class='error'>
                            <h3>⚠️ 2FA Not Enabled</h3>
                            <p>Two-Factor Authentication is not enabled on your account.</p>
                            <p>Password reset requires 2FA verification for security. Please contact the administrator to reset your password.</p>
                        </div>";
                }
            } else {
                $message = "<div class='error'>Account not found. Please check your username or email and try again.</div>";
            }
        }
    }
    }

    // ─── STEP 2: Verify 2FA code and reset password ───
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_2fa'])) {
    if (! validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='error'>Invalid request. Please try again.</div>";
    } elseif (! isset($_SESSION['reset_username'], $_SESSION['reset_table'])) {
        // Session expired
        unset($_SESSION['reset_step']);
        $step    = 1;
        $message = "<div class='error'>Session expired. Please start over.</div>";
    } else {
        $username = $_SESSION['reset_username'];
        $table    = $_SESSION['reset_table'];
        $name     = $_SESSION['reset_name'];
        $role     = $_SESSION['reset_role'];
        $code     = preg_replace('/\s+/', '', $_POST['totp_code'] ?? '');

        // Rate limit: max 5 attempts
        $max_attempts                   = 5;
        $_SESSION['reset_2fa_attempts'] = ($_SESSION['reset_2fa_attempts'] ?? 0) + 1;

        if ($_SESSION['reset_2fa_attempts'] > $max_attempts) {
            unset($_SESSION['reset_step'], $_SESSION['reset_username'], $_SESSION['reset_table'],
                $_SESSION['reset_name'], $_SESSION['reset_role'], $_SESSION['reset_2fa_attempts']);
            $step    = 1;
            $message = "<div class='error'>Too many failed attempts. Please try again later.</div>";
        } elseif (empty($code) || ! preg_match('/^\d{6}$/', $code)) {
            $step      = 2;
            $remaining = $max_attempts - $_SESSION['reset_2fa_attempts'];
            $message   = "<div class='error'>Please enter a valid 6-digit code. $remaining attempts remaining.</div>";
        } else {
            // Get and verify the TOTP secret
            $encryptedSecret = $totp->getSecret($conn, $username, $table);
            $secret          = $totp->decryptSecret($encryptedSecret);

            if ($secret && $totp->verifyCode($secret, $code)) {
                // 2FA verified — reset password
                if ($table === 'student_register') {
                    // Reset to DOB
                    $sql  = "SELECT dob FROM student_register WHERE username=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row    = $result->fetch_assoc();
                    $stmt->close();

                    if (! empty($row['dob'])) {
                        $new_password    = str_replace('-', '', $row['dob']);
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                        $update_stmt = $conn->prepare("UPDATE student_register SET password=? WHERE username=?");
                        $update_stmt->bind_param("ss", $hashed_password, $username);

                        if ($update_stmt->execute()) {
                            $escaped_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                            $message      = "<div class='success'>
                                    <h3>✅ Password Reset Successful!</h3>
                                    <p><strong>Hello $escaped_name,</strong></p>
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
                    // Teacher — reset to Faculty ID
                    $sql  = "SELECT faculty_id FROM teacher_register WHERE username=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row    = $result->fetch_assoc();
                    $stmt->close();

                    if (! empty($row['faculty_id'])) {
                        $hashed_password = password_hash($row['faculty_id'], PASSWORD_DEFAULT);

                        $update_stmt = $conn->prepare("UPDATE teacher_register SET password=? WHERE username=?");
                        $update_stmt->bind_param("ss", $hashed_password, $username);

                        if ($update_stmt->execute()) {
                            $escaped_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                            $message      = "<div class='success'>
                                    <h3>✅ Password Reset Successful!</h3>
                                    <p><strong>Hello $escaped_name,</strong></p>
                                    <p>Your password has been reset to your <strong>Faculty ID</strong>.</p>
                                    <p>Please login with your new password and consider changing it in your profile for security.</p>
                                </div>";
                        } else {
                            $message = "<div class='error'>Error updating password. Please try again.</div>";
                        }
                        $update_stmt->close();
                    } else {
                        $message = "<div class='error'>Faculty ID not found in your profile. Please contact admin.</div>";
                    }
                }

                // Clear reset session data
                unset($_SESSION['reset_step'], $_SESSION['reset_username'], $_SESSION['reset_table'],
                    $_SESSION['reset_name'], $_SESSION['reset_role'], $_SESSION['reset_2fa_attempts']);
                $step = 3; // Show success
            } else {
                // Invalid code
                $step      = 2;
                $remaining = $max_attempts - $_SESSION['reset_2fa_attempts'];
                $message   = "<div class='error'>Invalid verification code. $remaining attempts remaining.</div>";
            }
        }
    }
    }

    $csrf_token = generateCSRFToken();
    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Reset Password - Event Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/Sona Logo.png" />
    <link rel="stylesheet" href="styles.css" />
    <style>
        /* Background image styling */
        body {
            background-image: url("sona_login_img.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: relative;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(30, 66, 118, 0.5), rgba(255, 255, 255, 0.3));
            z-index: -1;
        }

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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            max-width: 500px;
            margin: 20px auto;
        }
        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

    </style>
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

      .header-logo img {
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

      .role-main {
        margin-top: 10px;
        padding-top: 20px;
      }

      @media (max-width: 768px) {
        body {
            background-image: url("sona_login_img.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            background-repeat: no-repeat;
            overflow-x: hidden;
        }

        body::before {
            background: linear-gradient(135deg, rgba(30, 66, 118, 0.6), rgba(255, 255, 255, 0.4));
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

        .header {
          justify-content: center;
          padding: 0 15px;
          height: 70px;
        }

        .header-title {
          font-size: 18px;
        }

        .header-logo {
          display: none;
        }
        .empty {
          display: none;
        }

        .form-container {
          width: 100%;
          max-width: 400px;
          margin: 0 auto;
          padding: 25px 20px;
          border-radius: 12px;
        }

        .form-container h2 {
          font-size: 22px;
          margin-bottom: 20px;
        }

        .info-box {
          padding: 15px;
          font-size: 14px;
          margin: 15px 0;
        }

        .form-container input[type="text"] {
          font-size: 16px;
          padding: 12px 15px;
        }

        .form-container input[type="submit"] {
          font-size: 16px;
          padding: 12px 20px;
        }

        .success, .error {
          padding: 15px;
          font-size: 14px;
          margin-bottom: 15px;
        }

        .back-link, .login-link {
          font-size: 14px;
          padding: 10px 15px;
        }
      }

      @media (max-width: 480px) {
        body {
            background-image: url("sona_login_img.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            background-repeat: no-repeat;
            overflow-x: hidden;
        }

        body::before {
            background: linear-gradient(135deg, rgba(30, 66, 118, 0.7), rgba(255, 255, 255, 0.5));
        }

        main {
          padding: 15px 10px;
          min-height: 100vh;
        }

        .header {
          justify-content: center;
          padding: 0 10px;
          height: 60px;
        }

        .header-title {
          font-size: 16px;
        }

        .header-logo {
          display: none;
        }
        .empty {
          display: none;
        }

        .form-container {
          max-width: 100%;
          padding: 20px 15px;
          border-radius: 10px;
        }

        .form-container h2 {
          font-size: 20px;
          margin-bottom: 15px;
        }

        .info-box {
          padding: 12px;
          font-size: 13px;
          margin: 12px 0;
        }

        .info-box h4 {
          font-size: 15px;
        }

        .form-container input[type="text"] {
          font-size: 16px;
          padding: 10px 12px;
        }

        .form-container input[type="submit"] {
          font-size: 14px;
          padding: 10px 15px;
        }

        .success, .error {
          padding: 12px;
          font-size: 13px;
        }

        .back-link, .login-link {
          font-size: 13px;
          padding: 8px 12px;
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
            width="200px"
          />
        </div>
        <div class="header-title">
          <p>Event Management Dashboard</p>
        </div>
        <div class="empty">
          <!-- empty -->
        </div>
    </div>

    <main class="forget-main">
        <div class="form-container">
            <h2 class="form-title">Reset Password</h2>

            <?php if (! empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>

            <?php if ($step === 3 || (strpos($message, 'Successful') !== false)): ?>
                <!-- Success — show login link -->
                <div style="text-align: center;">
                    <a href="index.php" class="login-link">Go to Login Page</a>
                </div>

            <?php elseif ($step === 2 && isset($_SESSION['reset_username'])): ?>
                <!-- Step 2: 2FA Verification -->
                <div class="info-box">
                    <h4>🔐 Two-Factor Authentication Required</h4>
                    <p>Enter the 6-digit code from your authenticator app to verify your identity.</p>
                </div>

                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" />
                    <div class="item">
                        <label for="totp_code">Enter 2FA Code:</label>
                        <input
                            type="text"
                            name="totp_code"
                            id="totp_code"
                            placeholder="Enter 6-digit code"
                            required
                            maxlength="6"
                            pattern="\d{6}"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            style="text-align: center; font-size: 16px; letter-spacing: 4px;"
                        />
                    </div>
                    <div class="item">
                        <input type="submit" name="verify_2fa" value="Verify & Reset Password" id="button" />
                    </div>
                </form>
                <a href="forgot_password_dob.php?cancel=1" class="back-link">← Start Over</a>

            <?php elseif ($step === 1): ?>
                <!-- Step 1: Enter username or email -->
                <div class="info-box">
                    <h4>📋 Password Reset Information</h4>
                    <p><strong>For Students:</strong> Password will be reset to your Date of Birth (YYYYMMDD format)</p>
                    <p><strong>For Teachers:</strong> Password will be reset to your Faculty ID</p>
                    <p style="margin-top: 10px;"><strong>Note:</strong> Two-Factor Authentication must be enabled on your account to reset your password.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>" />
                    <div class="item">
                        <label for="username">Enter Your Username or Email:</label>
                        <input
                            type="text"
                            name="username"
                            placeholder="Enter your username or email"
                            required
                            autocomplete="username"
                        />
                    </div>
                    <div class="item">
                        <input type="submit" name="find_user" value="Continue" id="button" />
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
        // Auto-focus the relevant input
        document.addEventListener('DOMContentLoaded', function() {
            const totpInput = document.getElementById('totp_code');
            const usernameInput = document.querySelector('input[name="username"]');
            if (totpInput) {
                totpInput.focus();
            } else if (usernameInput) {
                usernameInput.focus();
            }
        });
    </script>
</body>
</html>