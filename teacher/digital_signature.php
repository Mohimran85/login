<?php
    session_start();

    // Include file compression utility
    require_once '../includes/FileCompressor.php';

    // Check if user is logged in as a teacher
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;
    $is_admin     = false;
    $is_counselor = false;

    $sql  = "SELECT *, faculty_id as employee_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $is_admin     = ($teacher_data['status'] === 'admin');
        $is_counselor = ($teacher_data['status'] === 'counselor' || $is_admin);
    } else {
        header("Location: ../index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    // Check if teacher_signatures table exists first
    $table_exists    = false;
    $check_table_sql = "SHOW TABLES LIKE 'teacher_signatures'";
    $table_result    = $conn->query($check_table_sql);
    if ($table_result && $table_result->num_rows > 0) {
        $table_exists = true;
    }

    // Handle signature upload/creation only if table exists
    if ($table_exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['upload_signature']) && isset($_FILES['signature_file'])) {
            // Handle file upload signature
            $upload_dir = 'signatures/';
            if (! file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file          = $_FILES['signature_file'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($file['type'], $allowed_types) && $file['size'] <= 2097152) { // 2MB limit
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $base_filename  = $upload_dir . 'signature_' . $teacher_data['id'] . '_' . time();

                // Compress and save signature (90% quality for signatures)
                $compression_result = FileCompressor::compressUploadedFile(
                    $file['tmp_name'],
                    $base_filename,
                    $file_extension,
                    90
                );

                if ($compression_result['success']) {
                    $file_path = $compression_result['path'];
                    // Deactivate old signatures
                    $deactivate_sql  = "UPDATE teacher_signatures SET is_active = FALSE WHERE teacher_id = ?";
                    $deactivate_stmt = $conn->prepare($deactivate_sql);
                    $deactivate_stmt->bind_param("i", $teacher_data['id']);
                    $deactivate_stmt->execute();
                    $deactivate_stmt->close();

                    // Create signature hash for security
                    $signature_hash = hash('sha256', file_get_contents($file_path) . $teacher_data['id'] . time());

                    // Insert new signature
                    $insert_sql  = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash) VALUES (?, 'upload', ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iss", $teacher_data['id'], $file_path, $signature_hash);

                    if ($insert_stmt->execute()) {
                        $message      = "Signature uploaded successfully!";
                        $message_type = "success";
                    } else {
                        $message      = "Error saving signature to database.";
                        $message_type = "error";
                    }
                    $insert_stmt->close();
                } else {
                    $message      = "Error uploading file.";
                    $message_type = "error";
                }
            } else {
                $message      = "Invalid file type or size. Please upload a JPEG, PNG, or GIF image under 2MB.";
                $message_type = "error";
            }
        } elseif (isset($_POST['save_drawn_signature'])) {
            // Handle drawn signature
            $signature_data = $_POST['signature_data'];

            if (! empty($signature_data)) {
                // Deactivate old signatures
                $deactivate_sql  = "UPDATE teacher_signatures SET is_active = FALSE WHERE teacher_id = ?";
                $deactivate_stmt = $conn->prepare($deactivate_sql);
                $deactivate_stmt->bind_param("i", $teacher_data['id']);
                $deactivate_stmt->execute();
                $deactivate_stmt->close();

                // Create signature hash
                $signature_hash = hash('sha256', $signature_data . $teacher_data['id'] . time());

                // Insert new signature
                $insert_sql  = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash) VALUES (?, 'drawn', ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iss", $teacher_data['id'], $signature_data, $signature_hash);

                if ($insert_stmt->execute()) {
                    $message      = "Digital signature saved successfully!";
                    $message_type = "success";
                } else {
                    $message      = "Error saving signature to database.";
                    $message_type = "error";
                }
                $insert_stmt->close();
            } else {
                $message      = "Please draw your signature before saving.";
                $message_type = "error";
            }
        } elseif (isset($_POST['save_text_signature'])) {
            // Handle text signature
            $signature_text = trim($_POST['signature_text']);
            $font_family    = $_POST['font_family'];

            if (! empty($signature_text)) {
                $signature_data = json_encode([
                    'text'      => $signature_text,
                    'font'      => $font_family,
                    'timestamp' => time(),
                ]);

                // Deactivate old signatures
                $deactivate_sql  = "UPDATE teacher_signatures SET is_active = FALSE WHERE teacher_id = ?";
                $deactivate_stmt = $conn->prepare($deactivate_sql);
                $deactivate_stmt->bind_param("i", $teacher_data['id']);
                $deactivate_stmt->execute();
                $deactivate_stmt->close();

                // Create signature hash
                $signature_hash = hash('sha256', $signature_data . $teacher_data['id'] . time());

                // Insert new signature
                $insert_sql  = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash) VALUES (?, 'text', ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iss", $teacher_data['id'], $signature_data, $signature_hash);

                if ($insert_stmt->execute()) {
                    $message      = "Text signature saved successfully!";
                    $message_type = "success";
                } else {
                    $message      = "Error saving signature to database.";
                    $message_type = "error";
                }
                $insert_stmt->close();
            } else {
                $message      = "Please enter your signature text.";
                $message_type = "error";
            }
        }
    } elseif (! $table_exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message      = "Database setup required. Please set up the digital signature tables first.";
        $message_type = "error";
    }

    // Get current active signature only if table exists
    $current_signature = null;
    if ($table_exists) {
        $signature_sql  = "SELECT * FROM teacher_signatures WHERE teacher_id = ? AND is_active = TRUE ORDER BY created_at DESC LIMIT 1";
        $signature_stmt = $conn->prepare($signature_sql);
        $signature_stmt->bind_param("i", $teacher_data['id']);
        $signature_stmt->execute();
        $signature_result = $signature_stmt->get_result();

        if ($signature_result->num_rows > 0) {
            $current_signature = $signature_result->fetch_assoc();
        }
        $signature_stmt->close();
    }

    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Digital Signature Management - Teacher Portal</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .signature-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 20px 0;
        }

        .signature-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            background: #0c3878;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .signature-canvas {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            cursor: crosshair;
            display: block;
            margin: 0 auto 20px;
            background: white;
        }

        .canvas-controls {
            text-align: center;
            margin-bottom: 20px;
        }

        .canvas-controls button {
            margin: 0 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .clear-btn {
            background: #dc3545;
            color: white;
        }

        .clear-btn:hover {
            background: #c82333;
        }

        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #0c3878;
            background: #f0f8ff;
        }

        .upload-area.dragover {
            border-color: #0c3878;
            background: #e3f2fd;
        }

        .text-signature-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-size: 24px;
            text-align: center;
            margin-bottom: 20px;
        }

        .font-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .font-option {
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .font-option:hover,
        .font-option.selected {
            border-color: #0c3878;
            background: #f0f8ff;
        }

        .current-signature {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0fff0 100%);
            border: 2px solid #28a745;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }

        .current-signature h3 {
            color: #28a745;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .signature-preview {
            max-width: 300px;
            max-height: 150px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin: 0 auto;
            background: white;
            padding: 10px;
        }

        .save-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        .save-btn:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea080 100%);
            transform: translateY(-2px);
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .security-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }

        .security-info h4 {
            color: #1976d2;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .signature-container {
                padding: 20px;
                margin: 10px;
            }

            .tab-button {
                font-size: 14px;
                padding: 12px 10px;
            }

            .signature-canvas {
                width: 100%;
                height: 200px;
            }

            .font-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px"
            width="200">
            </div>
            <div class="header-title">
                <p>Digital Signature Management</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Teacher Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID:                                                                                             <?php echo htmlspecialchars($teacher_data['employee_id']); ?> <?php if ($is_admin) {echo ' (Admin)';} elseif ($is_counselor) {echo ' (Counselor)';}?></div>
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
                        <a href="registered_students.php" class="nav-link">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
                        </a>
                    </li>
                    <?php if ($is_counselor): ?>
                    <li class="nav-item">
                        <a href="assigned_students.php" class="nav-link">
                            <span class="material-symbols-outlined">supervisor_account</span>
                            My Assigned Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_approvals.php" class="nav-link">
                            <span class="material-symbols-outlined">approval</span>
                            OD Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="internship_approvals.php" class="nav-link">
                            <span class="material-symbols-outlined">school</span>
                            Internship Approvals
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="verify_events.php" class="nav-link">
                            <span class="material-symbols-outlined">card_giftcard</span>
                            Event Certificate Validation
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($is_admin): ?>
                    <li class="nav-item">
                        <a href="../admin/index.php" class="nav-link">
                            <span class="material-symbols-outlined">admin_panel_settings</span>
                            Admin Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/user_management.php" class="nav-link">
                            <span class="material-symbols-outlined">manage_accounts</span>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/participants.php" class="nav-link">
                            <span class="material-symbols-outlined">people</span>
                            Participants
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../admin/reports.php" class="nav-link">
                            <span class="material-symbols-outlined">bar_chart</span>
                            Reports
                        </a>
                    </li>
                    <?php endif; ?>
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

        <!-- Main Content -->
        <div class="main">
            <div class="welcome-section">
                <h1>🖋️ Digital Signature Management</h1>
                <p>Create and manage your secure digital signature for OD letters</p>
            </div>

            <?php if ($message): ?>
                <div class="message<?php echo $message_type; ?>">
                    <span class="material-symbols-outlined">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (! $table_exists): ?>
            <div class="message error">
                <span class="material-symbols-outlined">warning</span>
                <strong>Database Setup Required:</strong> Digital signature tables are not set up yet.
                <a href="../setup_digital_signature.php" style="color: #721c24; text-decoration: underline; font-weight: bold;">
                    Click here to set up the database
                </a>
                before creating signatures.
            </div>
            <?php endif; ?>

            <?php if ($current_signature && $table_exists): ?>
            <div class="current-signature">
                <h3>
                    <span class="material-symbols-outlined">verified</span>
                    Current Active Signature
                </h3>
                <div class="signature-preview">
                    <?php if ($current_signature['signature_type'] === 'upload'): ?>
                        <img src="<?php echo htmlspecialchars($current_signature['signature_data']); ?>"
                             alt="Current Signature" style="max-width: 100%; max-height: 100px;">
                    <?php elseif ($current_signature['signature_type'] === 'drawn'): ?>
                        <img src="<?php echo htmlspecialchars($current_signature['signature_data']); ?>"
                             alt="Current Signature" style="max-width: 100%; max-height: 100px;">
                    <?php elseif ($current_signature['signature_type'] === 'text'): ?>
                        <?php
                            $text_data = json_decode($current_signature['signature_data'], true);
                        ?>
                        <div style="font-family:<?php echo htmlspecialchars($text_data['font']); ?>; font-size: 24px; color: #0c3878;">
                            <?php echo htmlspecialchars($text_data['text']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    Created:                                                                                                                                                                                                     <?php echo date('M d, Y h:i A', strtotime($current_signature['created_at'])); ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($table_exists): ?>
            <div class="signature-container">
                <h2 style="margin-bottom: 20px; color: #0c3878;">Create/Update Your Digital Signature</h2>

                <div class="signature-tabs">
                    <button class="tab-button active" onclick="showTab('upload')">📁 Upload Image</button>
                    <button class="tab-button" onclick="showTab('draw')">🖊️ Draw Signature</button>
                    <button class="tab-button" onclick="showTab('text')">📝 Text Signature</button>
                </div>

                <!-- Upload Tab -->
                <div id="upload-tab" class="tab-content active">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="upload-area" id="upload-area">
                            <span class="material-symbols-outlined" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;">cloud_upload</span>
                            <h3>Upload Signature Image</h3>
                            <p>Drag and drop an image file here, or click to browse</p>
                            <input type="file" name="signature_file" id="signature_file" accept="image/*" style="display: none;" onchange="handleFileSelect(this)">
                            <button type="button" onclick="document.getElementById('signature_file').click()" style="margin-top: 15px; padding: 10px 20px; background: #0c3878; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Choose File
                            </button>
                        </div>
                        <div id="file-preview" style="text-align: center; margin-top: 20px;"></div>
                        <button type="submit" name="upload_signature" class="save-btn">
                            <span class="material-symbols-outlined">save</span>
                            Save Uploaded Signature
                        </button>
                    </form>
                </div>

                <!-- Draw Tab -->
                <div id="draw-tab" class="tab-content">
                    <form method="POST" id="draw-form">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h3>Draw Your Signature</h3>
                            <p>Use your mouse or finger to draw your signature below</p>
                        </div>
                        <canvas id="signature-canvas" class="signature-canvas" width="600" height="200"></canvas>
                        <div class="canvas-controls">
                            <button type="button" class="clear-btn" onclick="clearCanvas()">
                                <span class="material-symbols-outlined">clear</span>
                                Clear
                            </button>
                        </div>
                        <input type="hidden" name="signature_data" id="signature-data">
                        <button type="submit" name="save_drawn_signature" class="save-btn" onclick="saveDrawnSignature()">
                            <span class="material-symbols-outlined">save</span>
                            Save Drawn Signature
                        </button>
                    </form>
                </div>

                <!-- Text Tab -->
                <div id="text-tab" class="tab-content">
                    <form method="POST">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h3>Create Text Signature</h3>
                            <p>Type your name and choose a font style</p>
                        </div>

                        <input type="text" name="signature_text" class="text-signature-input"
                               placeholder="Enter your name" value="<?php echo htmlspecialchars($teacher_data['name']); ?>"
                               oninput="updateTextPreview()">

                        <div class="font-selector">
                            <div class="font-option selected" data-font="cursive" onclick="selectFont('cursive')">
                                <div style="font-family: cursive; font-size: 24px;">Cursive Font</div>
                                <small>Elegant script style</small>
                            </div>
                            <div class="font-option" data-font="Georgia, serif" onclick="selectFont('Georgia, serif')">
                                <div style="font-family: Georgia, serif; font-size: 24px;">Georgia Serif</div>
                                <small>Professional serif</small>
                            </div>
                            <div class="font-option" data-font="'Times New Roman', serif" onclick="selectFont('Times New Roman, serif')">
                                <div style="font-family: 'Times New Roman', serif; font-size: 24px;">Times Roman</div>
                                <small>Classic formal</small>
                            </div>
                            <div class="font-option" data-font="'Brush Script MT', cursive" onclick="selectFont('Brush Script MT, cursive')">
                                <div style="font-family: 'Brush Script MT', cursive; font-size: 24px;">Brush Script</div>
                                <small>Handwritten style</small>
                            </div>
                        </div>

                        <div style="text-align: center; padding: 20px; border: 1px solid #dee2e6; border-radius: 10px; margin-bottom: 20px; background: white;">
                            <h4>Preview:</h4>
                            <div id="text-preview" style="font-family: cursive; font-size: 32px; color: #0c3878; margin-top: 10px;">
                                <?php echo htmlspecialchars($teacher_data['name']); ?>
                            </div>
                        </div>

                        <input type="hidden" name="font_family" id="font_family" value="cursive">
                        <button type="submit" name="save_text_signature" class="save-btn">
                            <span class="material-symbols-outlined">save</span>
                            Save Text Signature
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="security-info">
                <h4>
                    <span class="material-symbols-outlined">security</span>
                    Security & Privacy Information
                </h4>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Your digital signature is encrypted and securely stored</li>
                    <li>Each signature is linked to your unique teacher ID</li>
                    <li>Signatures are timestamped for authenticity verification</li>
                    <li>Only you can create or update your signature</li>
                    <li>Signatures are automatically applied to approved OD letters</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }

        // Canvas drawing functionality
        let canvas = document.getElementById('signature-canvas');
        let ctx = canvas.getContext('2d');
        let isDrawing = false;

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch events for mobile
        canvas.addEventListener('touchstart', handleTouch);
        canvas.addEventListener('touchmove', handleTouch);
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;

            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function stopDrawing() {
            if (!isDrawing) return;
            isDrawing = false;
            ctx.beginPath();
        }

        function handleTouch(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' :
                                            e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }

        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        function saveDrawnSignature() {
            const dataURL = canvas.toDataURL();
            document.getElementById('signature-data').value = dataURL;
        }

        // File upload functionality
        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('file-preview');
                    preview.innerHTML = `
                        <h4>Preview:</h4>
                        <img src="${e.target.result}" style="max-width: 300px; max-height: 150px; border: 1px solid #dee2e6; border-radius: 8px;">
                    `;
                };
                reader.readAsDataURL(file);
            }
        }

        // Drag and drop functionality
        const uploadArea = document.getElementById('upload-area');
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('signature_file').files = files;
                handleFileSelect(document.getElementById('signature_file'));
            }
        });

        // Text signature functionality
        function selectFont(fontFamily) {
            document.querySelectorAll('.font-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('font_family').value = fontFamily;
            updateTextPreview();
        }

        function updateTextPreview() {
            const text = document.querySelector('input[name="signature_text"]').value;
            const font = document.getElementById('font_family').value;
            document.getElementById('text-preview').style.fontFamily = font;
            document.getElementById('text-preview').textContent = text || 'Your name here';
        }

        // Mobile sidebar functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const body = document.body;

            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-open');
            } else {
                sidebar.classList.add('active');
                body.classList.add('sidebar-open');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }
        });
    </script>
</body>
</html>