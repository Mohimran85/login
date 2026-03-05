<?php
    session_start();

    // Must be fully logged in to set up 2FA
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
    }

    require_once 'includes/db_config.php';
    require_once 'includes/TotpManager.php';

    $conn = get_db_connection();
    $totp = new TotpManager();

    $username   = $_SESSION['username'];
    $role       = $_SESSION['role'];
    $table      = ($role === 'student') ? 'student_register' : 'teacher_register';
    $is_enabled = $totp->isEnabled($conn, $username, $table);

    $message                = '';
    $message_type           = '';
    $step                   = isset($_GET['step']) ? (int) $_GET['step'] : 1;
    $recovery_codes_display = [];

    // Handle actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ─── ACTION: Generate new secret (Step 1 → Step 2) ───
    if (isset($_POST['action']) && $_POST['action'] === 'generate') {
        $secret                       = $totp->generateSecret();
        $_SESSION['2fa_setup_secret'] = $secret;
        $step                         = 2;
    }

    // ─── ACTION: Verify code and enable 2FA (Step 2 → Step 3) ───
    elseif (isset($_POST['action']) && $_POST['action'] === 'verify_setup') {
        $code   = preg_replace('/\s+/', '', $_POST['verify_code'] ?? '');
        $secret = $_SESSION['2fa_setup_secret'] ?? '';

        if (empty($secret)) {
            $message      = "Setup session expired. Please start over.";
            $message_type = "error";
            $step         = 1;
        } elseif (empty($code) || ! preg_match('/^\d{6}$/', $code)) {
            $message      = "Please enter a valid 6-digit code.";
            $message_type = "error";
            $step         = 2;
        } elseif ($totp->verifyCode($secret, $code)) {
            // Code valid — generate recovery codes and enable 2FA
            $recoveryCodes = $totp->generateRecoveryCodes();
            $totp->enable($conn, $username, $table, $secret, $recoveryCodes);

            // Store codes for display (one-time only)
            $_SESSION['2fa_recovery_codes'] = $recoveryCodes;

            // Clean up setup secret
            unset($_SESSION['2fa_setup_secret']);

            $step                   = 3;
            $is_enabled             = true;
            $recovery_codes_display = $recoveryCodes;
        } else {
            $message      = "Invalid code. Make sure you scanned the correct QR code and try again.";
            $message_type = "error";
            $step         = 2;
        }
    }

    // ─── ACTION: Disable 2FA ───
    elseif (isset($_POST['action']) && $_POST['action'] === 'disable') {
        $password = $_POST['current_password'] ?? '';
        $code     = preg_replace('/\s+/', '', $_POST['disable_code'] ?? '');

        // Verify current password
        $pwd_sql  = "SELECT password FROM $table WHERE username = ?";
        $pwd_stmt = $conn->prepare($pwd_sql);
        $pwd_stmt->bind_param("s", $username);
        $pwd_stmt->execute();
        $pwd_result = $pwd_stmt->get_result();
        $user_row   = $pwd_result->fetch_assoc();
        $pwd_stmt->close();

        if (! $user_row || ! password_verify($password, $user_row['password'])) {
            $message      = "Incorrect password.";
            $message_type = "error";
        } elseif (empty($code) || ! preg_match('/^\d{6}$/', $code)) {
            $message      = "Please enter a valid 6-digit code from your authenticator app.";
            $message_type = "error";
        } else {
            $encSecret = $totp->getSecret($conn, $username, $table);
            $secret    = $totp->decryptSecret($encSecret);

            if ($secret && $totp->verifyCode($secret, $code)) {
                $totp->disable($conn, $username, $table);
                $is_enabled   = false;
                $message      = "Two-factor authentication has been disabled.";
                $message_type = "success";
                // Also clear 2fa_verified from session since it's no longer needed
                unset($_SESSION['2fa_verified']);
            } else {
                $message      = "Invalid authenticator code.";
                $message_type = "error";
            }
        }
    }

    // ─── ACTION: Done viewing recovery codes ───
    elseif (isset($_POST['action']) && $_POST['action'] === 'done') {
        unset($_SESSION['2fa_recovery_codes']);
        // Redirect back to the profile page based on role
        if ($role === 'student') {
            header("Location: student/profile.php?2fa=enabled");
        } else {
            // Check if admin or teacher
            $status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
            $status_stmt = $conn->prepare($status_sql);
            $status_stmt->bind_param("s", $username);
            $status_stmt->execute();
            $status_result = $status_stmt->get_result();
            $status_row    = $status_result->fetch_assoc();
            $status_stmt->close();

            if ($status_row && $status_row['status'] === 'admin') {
                header("Location: admin/profile.php?2fa=enabled");
            } else {
                header("Location: teacher/profile.php?2fa=enabled");
            }
        }
        exit();
    }
    }

    // If showing step 3, get recovery codes from session
    if ($step === 3 && isset($_SESSION['2fa_recovery_codes'])) {
    $recovery_codes_display = $_SESSION['2fa_recovery_codes'];
    }

    // Get QR code data if on step 2
    $qr_data_uri  = '';
    $setup_secret = '';
    if ($step === 2 && isset($_SESSION['2fa_setup_secret'])) {
    $setup_secret = $_SESSION['2fa_setup_secret'];
    $qr_data_uri  = $totp->getQRCodeDataUri($username, $setup_secret);
    }

    // Determine back URL
    if ($role === 'student') {
    $back_url = 'student/profile.php';
    } else {
    $back_url    = 'teacher/profile.php';
    $status_sql  = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("s", $username);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    $status_row    = $status_result->fetch_assoc();
    $status_stmt->close();
    if ($status_row && $status_row['status'] === 'admin') {
        $back_url = 'admin/profile.php';
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
    <title>Setup Two-Factor Authentication</title>
    <link rel="icon" type="image/png" sizes="32x32" href="./assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./assets/images/favicon_io/favicon-16x16.png">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        .top-bar {
            background: linear-gradient(135deg, #1e4276, #2d5aa0);
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }

        .top-bar a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .top-bar h1 {
            font-size: 18px;
            font-weight: 500;
        }

        .content {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* ── Steps indicator ── */
        .steps {
            display: flex;
            justify-content: center;
            gap: 0;
            margin-bottom: 30px;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            border: 2px solid #ddd;
            color: #999;
            background: white;
            transition: all 0.3s;
        }

        .step-item.active .step-circle {
            background: #1e4276;
            border-color: #1e4276;
            color: white;
        }

        .step-item.done .step-circle {
            background: #27ae60;
            border-color: #27ae60;
            color: white;
        }

        .step-label {
            font-size: 13px;
            color: #999;
            font-weight: 500;
        }

        .step-item.active .step-label,
        .step-item.done .step-label {
            color: #333;
        }

        .step-line {
            width: 40px;
            height: 2px;
            background: #ddd;
            margin: 0 8px;
            align-self: center;
        }

        .step-line.done {
            background: #27ae60;
        }

        /* ── Card ── */
        .setup-card {
            background: white;
            border-radius: 14px;
            padding: 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .setup-card h2 {
            color: #1e4276;
            font-size: 20px;
            margin-bottom: 8px;
        }

        .setup-card .desc {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* ── QR section ── */
        .qr-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .qr-section img {
            border: 4px solid #f0f2f5;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }

        .secret-key {
            background: #f8f9fa;
            border: 1px dashed #ccc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 16px;
            text-align: center;
        }

        .secret-key label {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 4px;
        }

        .secret-key code {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            font-weight: 600;
            color: #1e4276;
            letter-spacing: 2px;
            word-break: break-all;
        }

        .copy-btn {
            background: none;
            border: 1px solid #1e4276;
            color: #1e4276;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background: #1e4276;
            color: white;
        }

        /* ── Verification input ── */
        .verify-input-group {
            margin-bottom: 24px;
        }

        .verify-input-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .verify-input-group input {
            width: 100%;
            padding: 14px 16px;
            font-size: 22px;
            font-family: 'Courier New', monospace;
            text-align: center;
            letter-spacing: 8px;
            border: 2px solid #ddd;
            border-radius: 10px;
            outline: none;
            transition: all 0.2s;
        }

        .verify-input-group input:focus {
            border-color: #1e4276;
            box-shadow: 0 0 0 3px rgba(30,66,118,0.1);
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-primary {
            flex: 1;
            padding: 14px;
            background: linear-gradient(135deg, #1e4276, #2d5aa0);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(30,66,118,0.3);
        }

        .btn-secondary {
            padding: 14px 20px;
            background: #f0f2f5;
            color: #666;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }

        .btn-secondary:hover {
            background: #e0e3e8;
            color: #333;
        }

        .btn-danger {
            padding: 14px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* ── Recovery codes ── */
        .recovery-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 20px 0;
        }

        .recovery-code {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 15px;
            font-weight: 600;
            color: #1e4276;
            letter-spacing: 1px;
        }

        .recovery-warning {
            background: #fff8e1;
            border: 1px solid #ffe0b2;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        .recovery-warning strong {
            color: #e65100;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .recovery-warning p {
            color: #bf360c;
            font-size: 13px;
            line-height: 1.5;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: none;
            border: 1px solid #1e4276;
            color: #1e4276;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 8px;
        }

        .download-btn:hover {
            background: #1e4276;
            color: white;
        }

        /* ── Messages ── */
        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .msg-error {
            background: #fff2f2;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }

        .msg-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-enabled {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-disabled {
            background: #fff8e1;
            color: #e65100;
        }

        /* ── Disable section ── */
        .disable-section {
            margin-top: 30px;
            padding-top: 24px;
            border-top: 2px solid #f0f0f0;
        }

        .disable-section h3 {
            color: #c0392b;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .disable-section .desc {
            color: #888;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .disable-group {
            margin-bottom: 14px;
        }

        .disable-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            margin-bottom: 6px;
        }

        .disable-group input {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
        }

        .disable-group input:focus {
            border-color: #e74c3c;
        }

        @media (max-width: 480px) {
            .content { margin: 16px auto; }
            .setup-card { padding: 24px 18px; }
            .steps { gap: 0; }
            .step-label { display: none; }
            .step-line { width: 24px; }
            .recovery-grid { grid-template-columns: 1fr; }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="<?php echo htmlspecialchars($back_url); ?>">
            <span class="material-symbols-outlined">arrow_back</span>
        </a>
        <h1>Two-Factor Authentication Setup</h1>
    </div>

    <div class="content">

        <?php if (! empty($message)): ?>
            <div class="msg msg-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($is_enabled && $step !== 3): ?>
            <!-- ═══ 2FA IS ALREADY ENABLED ═══ -->
            <div class="setup-card">
                <span class="status-badge status-enabled">
                    <span class="material-symbols-outlined" style="font-size: 18px;">verified_user</span>
                    2FA Enabled
                </span>

                <h2>Two-Factor Authentication is Active</h2>
                <p class="desc">
                    Your account is protected with two-factor authentication.
                    You'll be asked for a verification code each time you sign in.
                </p>

                <div class="btn-row">
                    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn-secondary">
                        &#8592; Back to Profile
                    </a>
                </div>

                <!-- Disable 2FA Section -->
                <div class="disable-section">
                    <h3>Disable Two-Factor Authentication</h3>
                    <p class="desc">
                        This will remove the extra security layer from your account.
                        You'll need your current password and a code from your authenticator app.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="disable">

                        <div class="disable-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required placeholder="Enter your password">
                        </div>

                        <div class="disable-group">
                            <label>Authenticator Code</label>
                            <input type="text" name="disable_code" required
                                   placeholder="6-digit code" maxlength="6"
                                   inputmode="numeric" pattern="\d{6}" autocomplete="off">
                        </div>

                        <button type="submit" class="btn-danger" onclick="return confirm('Are you sure you want to disable 2FA?')">
                            Disable 2FA
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($step === 1): ?>
            <!-- ═══ STEP 1: Introduction ═══ -->
            <div class="steps">
                <div class="step-item active">
                    <div class="step-circle">1</div>
                    <span class="step-label">Start</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item">
                    <div class="step-circle">2</div>
                    <span class="step-label">Scan QR</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item">
                    <div class="step-circle">3</div>
                    <span class="step-label">Save Codes</span>
                </div>
            </div>

            <div class="setup-card">
                <h2>Secure Your Account</h2>
                <p class="desc">
                    Two-factor authentication adds an extra layer of security to your account.
                    In addition to your password, you'll need to enter a code from an authenticator app
                    on your phone each time you sign in.
                </p>

                <p class="desc"><strong>You'll need:</strong></p>
                <ul style="color: #555; font-size: 14px; line-height: 2; margin-bottom: 24px; padding-left: 20px;">
                    <li>An authenticator app on your phone</li>
                    <li style="font-size: 12px; color: #888; list-style: none; margin-left: -20px; padding-left: 20px;">
                        (e.g., Google Authenticator, Microsoft Authenticator, or Authy)
                    </li>
                </ul>

                <div class="btn-row">
                    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn-secondary">Cancel</a>
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="action" value="generate">
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            Get Started
                        </button>
                    </form>
                </div>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- ═══ STEP 2: Scan QR Code ═══ -->
            <div class="steps">
                <div class="step-item done">
                    <div class="step-circle">&#10003;</div>
                    <span class="step-label">Start</span>
                </div>
                <div class="step-line done"></div>
                <div class="step-item active">
                    <div class="step-circle">2</div>
                    <span class="step-label">Scan QR</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item">
                    <div class="step-circle">3</div>
                    <span class="step-label">Save Codes</span>
                </div>
            </div>

            <div class="setup-card">
                <h2>Scan QR Code</h2>
                <p class="desc">
                    Open your authenticator app and scan the QR code below.
                    If you can't scan the code, enter the secret key manually.
                </p>

                <div class="qr-section">
                    <img src="<?php echo $qr_data_uri; ?>" alt="QR Code for 2FA setup" width="200" height="200" />

                    <div class="secret-key">
                        <label>Or enter this key manually:</label>
                        <code id="secretKey"><?php echo htmlspecialchars($setup_secret); ?></code>
                        <br>
                        <button type="button" class="copy-btn" onclick="copySecret()">Copy Key</button>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="verify_setup">

                    <div class="verify-input-group">
                        <label>Enter the 6-digit code from your app to verify:</label>
                        <input type="text" name="verify_code" maxlength="6" inputmode="numeric"
                               pattern="\d{6}" autocomplete="off" autofocus required
                               placeholder="000000">
                    </div>

                    <div class="btn-row">
                        <a href="setup_2fa.php" class="btn-secondary">&#8592; Back</a>
                        <button type="submit" class="btn-primary">Verify &amp; Enable</button>
                    </div>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- ═══ STEP 3: Recovery Codes ═══ -->
            <div class="steps">
                <div class="step-item done">
                    <div class="step-circle">&#10003;</div>
                    <span class="step-label">Start</span>
                </div>
                <div class="step-line done"></div>
                <div class="step-item done">
                    <div class="step-circle">&#10003;</div>
                    <span class="step-label">Scan QR</span>
                </div>
                <div class="step-line done"></div>
                <div class="step-item active">
                    <div class="step-circle">3</div>
                    <span class="step-label">Save Codes</span>
                </div>
            </div>

            <div class="setup-card">
                <h2 style="color: #27ae60;">&#10003; Two-Factor Authentication Enabled!</h2>
                <p class="desc">
                    Your account is now protected with 2FA. Save these recovery codes in a safe place.
                    Each code can only be used once.
                </p>

                <div class="recovery-warning">
                    <strong>
                        <span class="material-symbols-outlined" style="font-size: 18px;">warning</span>
                        Important — Save These Codes Now
                    </strong>
                    <p>
                        These recovery codes will <strong>only be shown once</strong>.
                        If you lose access to your authenticator app, you can use these codes to sign in.
                        Store them somewhere safe (password manager, printed copy, etc.)
                    </p>
                </div>

                <div class="recovery-grid" id="recoveryCodes">
                    <?php foreach ($recovery_codes_display as $i => $code): ?>
                        <div class="recovery-code"><?php echo htmlspecialchars($code); ?></div>
                    <?php endforeach; ?>
                </div>

                <div style="margin: 16px 0; text-align: center;">
                    <button type="button" class="download-btn" onclick="downloadCodes()">
                        <span class="material-symbols-outlined" style="font-size: 16px;">download</span>
                        Download Codes
                    </button>
                    <button type="button" class="download-btn" onclick="copyCodes()">
                        <span class="material-symbols-outlined" style="font-size: 16px;">content_copy</span>
                        Copy to Clipboard
                    </button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="done">
                    <div class="btn-row">
                        <button type="submit" class="btn-primary" style="width: 100%;">
                            I've Saved My Codes — Continue
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function copySecret() {
        const key = document.getElementById('secretKey').textContent;
        navigator.clipboard.writeText(key).then(() => {
            const btn = document.querySelector('.copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(() => btn.textContent = 'Copy Key', 2000);
        });
    }

    function copyCodes() {
        const codes = [];
        document.querySelectorAll('.recovery-code').forEach(el => codes.push(el.textContent.trim()));
        const text = "Sona Event Management - 2FA Recovery Codes\n" +
                     "Generated: <?php echo date('Y-m-d H:i'); ?>\n" +
                     "Username: <?php echo htmlspecialchars($username); ?>\n\n" +
                     codes.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            const btns = document.querySelectorAll('.download-btn');
            btns[1].innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">done</span> Copied!';
            setTimeout(() => {
                btns[1].innerHTML = '<span class="material-symbols-outlined" style="font-size: 16px;">content_copy</span> Copy to Clipboard';
            }, 2000);
        });
    }

    function downloadCodes() {
        const codes = [];
        document.querySelectorAll('.recovery-code').forEach(el => codes.push(el.textContent.trim()));
        const text = "Sona Event Management - 2FA Recovery Codes\n" +
                     "Generated: <?php echo date('Y-m-d H:i'); ?>\n" +
                     "Username: <?php echo htmlspecialchars($username); ?>\n" +
                     "=========================================\n\n" +
                     codes.map((c, i) => `${i + 1}. ${c}`).join('\n') +
                     "\n\n=========================================\n" +
                     "Each code can only be used once.\n" +
                     "Store these in a safe place.";

        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sona-2fa-recovery-codes.txt';
        a.click();
        URL.revokeObjectURL(url);
    }
    </script>
</body>
</html>
