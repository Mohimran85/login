<?php
    session_start();

    // Only accessible when 2FA verification is pending
    if (! isset($_SESSION['2fa_pending']) || $_SESSION['2fa_pending'] !== true) {
    header("Location: index.php");
    exit();
    }

    require_once 'includes/db_config.php';
    require_once 'includes/TotpManager.php';

    $conn = get_db_connection();
    $totp = new TotpManager();

    $error_message     = '';
    $success_message   = '';
    $is_locked_out     = false;
    $lockout_remaining = 0;
    $use_recovery      = isset($_GET['recovery']) && $_GET['recovery'] === '1';

    // Check for lockout (5 failed attempts = 5-minute lockout)
    $max_attempts    = 5;
    $lockout_seconds = 300; // 5 minutes

    if (isset($_SESSION['2fa_lockout_time'])) {
    $elapsed = time() - $_SESSION['2fa_lockout_time'];
    if ($elapsed < $lockout_seconds) {
        $is_locked_out     = true;
        $lockout_remaining = $lockout_seconds - $elapsed;
    } else {
        // Lockout expired, reset
        unset($_SESSION['2fa_lockout_time']);
        $_SESSION['2fa_attempts'] = 0;
    }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ! $is_locked_out) {
    $username = $_SESSION['2fa_username'];
    $table    = $_SESSION['2fa_table'];
    $role     = $_SESSION['2fa_role'];

    if ($use_recovery && isset($_POST['recovery_code'])) {
        // Recovery code verification
        $recovery_code = trim($_POST['recovery_code']);

        if (empty($recovery_code)) {
            $error_message = "Please enter a recovery code.";
        } else {
            $storedCodes = $totp->getRecoveryCodes($conn, $username, $table);

            if ($storedCodes) {
                $result = $totp->verifyRecoveryCode($recovery_code, $storedCodes);

                if ($result['valid']) {
                    // Update remaining codes in DB
                    $totp->updateRecoveryCodes($conn, $username, $table, $result['remaining_codes']);

                    // Count remaining codes
                    $remaining       = json_decode($result['remaining_codes'], true);
                    $remaining_count = is_array($remaining) ? count($remaining) : 0;

                    // Complete login
                    completeLogin($conn, $username, $role, $table);
                } else {
                    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

                    if ($_SESSION['2fa_attempts'] >= $max_attempts) {
                        $_SESSION['2fa_lockout_time'] = time();
                        $is_locked_out                = true;
                        $lockout_remaining            = $lockout_seconds;
                        $error_message                = "Too many failed attempts. Please wait 5 minutes.";
                    } else {
                        $remaining_tries = $max_attempts - $_SESSION['2fa_attempts'];
                        $error_message   = "Invalid recovery code. $remaining_tries attempts remaining.";
                    }
                }
            } else {
                $error_message = "No recovery codes found. Please contact an administrator.";
            }
        }
    } elseif (isset($_POST['totp_code'])) {
                                                                // TOTP code verification
        $code = preg_replace('/\s+/', '', $_POST['totp_code']); // Remove any spaces

        if (empty($code) || ! preg_match('/^\d{6}$/', $code)) {
            $error_message = "Please enter a valid 6-digit code.";
        } else {
            $encryptedSecret = $totp->getSecret($conn, $username, $table);

            if ($encryptedSecret) {
                $secret = $totp->decryptSecret($encryptedSecret);

                if ($secret && $totp->verifyCode($secret, $code)) {
                    // Code valid — complete login
                    completeLogin($conn, $username, $role, $table);
                } else {
                    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

                    if ($_SESSION['2fa_attempts'] >= $max_attempts) {
                        $_SESSION['2fa_lockout_time'] = time();
                        $is_locked_out                = true;
                        $lockout_remaining            = $lockout_seconds;
                        $error_message                = "Too many failed attempts. Please wait 5 minutes.";
                    } else {
                        $remaining_tries = $max_attempts - $_SESSION['2fa_attempts'];
                        $error_message   = "Invalid code. $remaining_tries attempts remaining.";
                    }
                }
            } else {
                $error_message = "2FA configuration error. Please contact an administrator.";
            }
        }
    }
    }

    // Handle cancel — go back to login
    if (isset($_GET['cancel'])) {
    // Clear 2FA session data
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_username'], $_SESSION['2fa_role'],
        $_SESSION['2fa_table'], $_SESSION['2fa_attempts'], $_SESSION['2fa_lockout_time'],
        $_SESSION['2fa_time']);
    header("Location: index.php");
    exit();
    }

    /**
 * Complete the login after successful 2FA verification
 */
    function completeLogin($conn, $username, $role, $table)
    {
    session_regenerate_id(true);

    // Generate single-device session token and persist it to DB
    $session_token = bin2hex(random_bytes(32));
    $tok_table     = ($role === 'student') ? 'student_register' : 'teacher_register';
    $tok_stmt      = $conn->prepare("UPDATE `$tok_table` SET session_token = ? WHERE username = ?");
    if ($tok_stmt) {
        $tok_stmt->bind_param("ss", $session_token, $username);
        $tok_stmt->execute();
        $tok_stmt->close();
    }

    // Set full session
    $_SESSION['username']      = $username;
    $_SESSION['role']          = $role;
    $_SESSION['logged_in']     = true;
    $_SESSION['last_activity'] = time();
    $_SESSION['2fa_verified']  = true;
    $_SESSION['session_token'] = $session_token;

    // Clear 2FA pending data
    unset($_SESSION['2fa_pending'], $_SESSION['2fa_username'], $_SESSION['2fa_role'],
        $_SESSION['2fa_table'], $_SESSION['2fa_attempts'], $_SESSION['2fa_lockout_time'],
        $_SESSION['2fa_time']);

    // Determine redirect
    if ($role === 'student') {
        $redirect = 'student/index.php';
    } else {
        // Check teacher status for admin redirect
        $status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
        $status_stmt = $conn->prepare($status_sql);
        $status_stmt->bind_param("s", $username);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();

        $teacher_status = 'teacher';
        if ($status_result->num_rows > 0) {
            $status_data    = $status_result->fetch_assoc();
            $teacher_status = $status_data['status'];
        }
        $status_stmt->close();

        if ($teacher_status === 'inactive') {
            $_SESSION['access_denied'] = 'Your account is inactive. Please contact an administrator.';
            $redirect                  = 'teacher/index.php';
        } elseif ($teacher_status === 'admin') {
            $_SESSION['role'] = 'admin';
            $redirect         = 'admin/index.php';
        } else {
            $redirect = 'teacher/index.php';
        }
    }

    $conn->close();
    header("Location: $redirect");
    exit();
    }

    $conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#0c3878">
    <title>Two-Factor Authentication - Event Management</title>
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./assets/images/favicon_io/apple-touch-icon.png">
    <link rel="stylesheet" href="styles.css" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg,
                rgba(30, 66, 118, 0.85) 0%,
                rgba(45, 90, 160, 0.7) 50%,
                rgba(30, 66, 118, 0.85) 100%),
                url('sona_login_img.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header-bar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .header-bar h1 {
            color: #1e4276;
            font-size: 18px;
            font-weight: 500;
        }

        .verify-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 80px 20px 20px;
        }

        .verify-card {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            text-align: center;
        }

        .shield-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #1e4276, #2d5aa0);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .shield-icon svg {
            width: 32px;
            height: 32px;
            fill: white;
        }

        .verify-card h2 {
            color: #1e4276;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .verify-card .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .code-inputs {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 24px;
        }

        .code-inputs input {
            width: 48px;
            height: 56px;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            border: 2px solid #ddd;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s;
            color: #1e4276;
            background: #f8f9fa;
        }

        .code-inputs input:focus {
            border-color: #1e4276;
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 66, 118, 0.1);
        }

        .code-inputs .separator {
            display: flex;
            align-items: center;
            font-size: 24px;
            color: #999;
            padding: 0 2px;
        }

        .recovery-input {
            margin-bottom: 24px;
        }

        .recovery-input input {
            width: 100%;
            padding: 14px 16px;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            border: 2px solid #ddd;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s;
            color: #1e4276;
            background: #f8f9fa;
        }

        .recovery-input input:focus {
            border-color: #1e4276;
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 66, 118, 0.1);
        }

        .verify-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e4276, #2d5aa0);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 16px;
        }

        .verify-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(30, 66, 118, 0.35);
        }

        .verify-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .alt-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }

        .alt-links a {
            color: #2d5aa0;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.2s;
        }

        .alt-links a:hover {
            color: #1e4276;
            text-decoration: underline;
        }

        .error-msg {
            background: #fff2f2;
            color: #c0392b;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .lockout-msg {
            background: #fff8e1;
            color: #e65100;
            padding: 14px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #ffe0b2;
        }

        .lockout-msg .timer {
            font-weight: 700;
            font-size: 18px;
            display: block;
            margin-top: 6px;
        }

        /* Hidden input for form submission */
        .hidden-code { position: absolute; opacity: 0; pointer-events: none; }

        @media (max-width: 480px) {
            .verify-card {
                padding: 30px 20px;
                border-radius: 12px;
            }

            .code-inputs input {
                width: 42px;
                height: 50px;
                font-size: 20px;
            }

            .verify-card h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header-bar">
        <h1>Event Management System</h1>
    </div>

    <div class="verify-container">
        <div class="verify-card">
            <div class="shield-icon">
                <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
            </div>

            <h2>Two-Factor Authentication</h2>

            <?php if ($use_recovery): ?>
                <p class="subtitle">Enter one of your recovery codes to sign in.</p>
            <?php else: ?>
                <p class="subtitle">Enter the 6-digit code from your authenticator app.</p>
            <?php endif; ?>

            <?php if ($is_locked_out): ?>
                <div class="lockout-msg">
                    Too many failed attempts. Please wait before trying again.
                    <span class="timer" id="lockout-timer"><?php echo gmdate("i:s", $lockout_remaining); ?></span>
                </div>
            <?php elseif (! empty($error_message)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($use_recovery): ?>
                <!-- Recovery Code Form -->
                <form method="POST" action="verify_2fa.php?recovery=1" id="recoveryForm">
                    <div class="recovery-input">
                        <input type="text"
                               name="recovery_code"
                               id="recoveryCode"
                               placeholder="XXXX-XXXX"
                               maxlength="9"
                               autocomplete="off"
                               autofocus
                               <?php echo $is_locked_out ? 'disabled' : ''; ?>
                        />
                    </div>
                    <button type="submit" class="verify-btn" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                        Verify Recovery Code
                    </button>
                </form>

                <div class="alt-links">
                    <a href="verify_2fa.php">&#8592; Use authenticator code instead</a>
                    <a href="verify_2fa.php?cancel=1">&#8592; Back to login</a>
                </div>
            <?php else: ?>
                <!-- TOTP Code Form -->
                <form method="POST" action="verify_2fa.php" id="totpForm">
                    <input type="hidden" name="totp_code" id="totpCodeHidden" value="">

                    <div class="code-inputs" id="codeInputs">
                        <input type="text" inputmode="numeric" maxlength="1" data-index="0" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> autofocus />
                        <input type="text" inputmode="numeric" maxlength="1" data-index="1" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> />
                        <input type="text" inputmode="numeric" maxlength="1" data-index="2" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> />
                        <span class="separator">&middot;</span>
                        <input type="text" inputmode="numeric" maxlength="1" data-index="3" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> />
                        <input type="text" inputmode="numeric" maxlength="1" data-index="4" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> />
                        <input type="text" inputmode="numeric" maxlength="1" data-index="5" autocomplete="off" <?php echo $is_locked_out ? 'disabled' : ''; ?> />
                    </div>

                    <button type="submit" class="verify-btn" id="verifyBtn" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                        Verify &amp; Sign In
                    </button>
                </form>

                <div class="alt-links">
                    <a href="verify_2fa.php?recovery=1">Use a recovery code instead</a>
                    <a href="verify_2fa.php?cancel=1">&#8592; Back to login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // ─── TOTP 6-digit input logic ───
        const codeInputs = document.querySelectorAll('#codeInputs input[type="text"]');
        const totpForm   = document.getElementById('totpForm');
        const hiddenCode = document.getElementById('totpCodeHidden');

        if (codeInputs.length) {
            codeInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const val = this.value.replace(/\D/g, '');
                    this.value = val;

                    if (val && index < codeInputs.length - 1) {
                        codeInputs[index + 1].focus();
                    }

                    // Auto-submit when all 6 filled
                    updateHiddenCode();
                    const fullCode = getFullCode();
                    if (fullCode.length === 6) {
                        totpForm.submit();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        codeInputs[index - 1].focus();
                        codeInputs[index - 1].value = '';
                    }
                });

                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (pasted.length >= 6) {
                        for (let i = 0; i < 6; i++) {
                            codeInputs[i].value = pasted[i] || '';
                        }
                        updateHiddenCode();
                        totpForm.submit();
                    }
                });
            });
        }

        function getFullCode() {
            return Array.from(codeInputs).map(i => i.value).join('');
        }

        function updateHiddenCode() {
            if (hiddenCode) hiddenCode.value = getFullCode();
        }

        if (totpForm) {
            totpForm.addEventListener('submit', function() {
                updateHiddenCode();
            });
        }

        // ─── Lockout countdown timer ───
        const timerEl = document.getElementById('lockout-timer');
        if (timerEl) {
            let remaining = <?php echo (int) $lockout_remaining; ?>;
            const interval = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(interval);
                    window.location.reload();
                    return;
                }
                const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                const s = (remaining % 60).toString().padStart(2, '0');
                timerEl.textContent = m + ':' + s;
            }, 1000);
        }
    });
    </script>
</body>
</html>
