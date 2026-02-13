<?php
session_start();
require_once 'config.php';

// Require teacher role
require_teacher_role();

// Get database connection
$conn = get_db_connection();

// Get teacher data
$username = $_SESSION['username'];
$teacher_id = null;

$sql = "SELECT id, name, employee_id FROM teacher_register WHERE username=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $teacher_data = $result->fetch_assoc();
    $teacher_id = $teacher_data['id'];
} else {
    header("Location: ../index.php");
    exit();
}
$stmt->close();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Handle file upload signature
        if ($action === 'upload_signature' && isset($_FILES['signature_file'])) {
            $file = $_FILES['signature_file'];
            
            // Allowed types and size limit (2MB)
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
            $max_size = 2 * 1024 * 1024;
            
            // Server-side MIME type validation using finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            // Additional validation using getimagesize
            $image_info = @getimagesize($file['tmp_name']);
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = 'File upload error. Please try again.';
                $message_type = 'error';
            } elseif (!in_array($detected_type, $allowed_types) || $image_info === false) {
                $message = 'Invalid file type. Only PNG and JPEG images are allowed.';
                $message_type = 'error';
            } elseif ($file['size'] > $max_size) {
                $message = 'File size must not exceed 2MB.';
                $message_type = 'error';
            } else {
                // Generate unique filename
                $extension = ($detected_type === 'image/png') ? 'png' : 'jpg';
                $filename = 'signature_' . $teacher_id . '_' . time() . '.' . $extension';
                $upload_dir = '../uploads/signatures/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $target_path = $upload_dir . $filename;
                
                // Use transaction for atomic update
                $conn->begin_transaction();
                
                try {
                    // Deactivate old signatures
                    $deactivate_sql = "UPDATE teacher_signatures SET is_active = 0 WHERE teacher_id = ?";
                    $deactivate_stmt = $conn->prepare($deactivate_sql);
                    $deactivate_stmt->bind_param("i", $teacher_id);
                    $deactivate_stmt->execute();
                    $deactivate_stmt->close();
                    
                    // Move uploaded file
                    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                        throw new Exception('Failed to save signature file.');
                    }
                    
                    // Insert new signature
                    $signature_hash = hash('sha256', $filename . time());
                    $signature_type = 'upload';
                    $insert_sql = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("isss", $teacher_id, $signature_type, $filename, $signature_hash);
                    
                    if (!$insert_stmt->execute()) {
                        throw new Exception('Failed to save signature to database.');
                    }
                    $insert_stmt->close();
                    
                    $conn->commit();
                    $message = 'Signature uploaded successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log('Signature upload error for teacher ' . $teacher_id . ': ' . $e->getMessage());
                    $message = 'Failed to save signature. Please try again.';
                    $message_type = 'error';
                    
                    // Clean up uploaded file if exists
                    if (file_exists($target_path)) {
                        @unlink($target_path);
                    }
                }
            }
        }
        
        // Handle drawn signature
        elseif ($action === 'save_drawn_signature' && isset($_POST['signature_data'])) {
            $signature_data = $_POST['signature_data'];
            
            // Validate data URL pattern
            if (!preg_match('/^data:image\/(png|jpeg);base64,([A-Za-z0-9+\/=]+)$/', $signature_data, $matches)) {
                $message = 'Invalid signature data format.';
                $message_type = 'error';
            } else {
                $image_type = $matches[1];
                $base64_data = $matches[2];
                
                // Decode and validate base64 data
                $decoded_data = base64_decode($base64_data, true);
                
                if ($decoded_data === false) {
                    $message = 'Invalid signature data encoding.';
                    $message_type = 'error';
                } else {
                    // Check size limits (2MB)
                    $data_size = strlen($decoded_data);
                    $max_size = 2 * 1024 * 1024;
                    
                    if ($data_size > $max_size) {
                        $message = 'Signature data is too large. Please try again.';
                        $message_type = 'error';
                    } else {
                        // Verify it's valid image data
                        $temp_file = tempnam(sys_get_temp_dir(), 'sig');
                        file_put_contents($temp_file, $decoded_data);
                        $image_info = @getimagesize($temp_file);
                        unlink($temp_file);
                        
                        if ($image_info === false) {
                            $message = 'Invalid image data.';
                            $message_type = 'error';
                        } else {
                            // Use transaction for atomic update
                            $conn->begin_transaction();
                            
                            try {
                                // Deactivate old signatures
                                $deactivate_sql = "UPDATE teacher_signatures SET is_active = 0 WHERE teacher_id = ?";
                                $deactivate_stmt = $conn->prepare($deactivate_sql);
                                $deactivate_stmt->bind_param("i", $teacher_id);
                                $deactivate_stmt->execute();
                                $deactivate_stmt->close();
                                
                                // Insert new signature
                                $signature_hash = hash('sha256', $signature_data . time());
                                $signature_type = 'drawn';
                                $insert_sql = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
                                $insert_stmt = $conn->prepare($insert_sql);
                                $insert_stmt->bind_param("isss", $teacher_id, $signature_type, $signature_data, $signature_hash);
                                
                                if (!$insert_stmt->execute()) {
                                    throw new Exception('Failed to save signature.');
                                }
                                $insert_stmt->close();
                                
                                $conn->commit();
                                $message = 'Signature saved successfully!';
                                $message_type = 'success';
                            } catch (Exception $e) {
                                $conn->rollback();
                                error_log('Drawn signature save error for teacher ' . $teacher_id . ': ' . $e->getMessage());
                                $message = 'Failed to save signature. Please try again.';
                                $message_type = 'error';
                            }
                        }
                    }
                }
            }
        }
        
        // Handle text signature
        elseif ($action === 'save_text_signature' && isset($_POST['signature_text']) && isset($_POST['font_family'])) {
            $signature_text = trim($_POST['signature_text']);
            $font_family = $_POST['font_family'];
            
            // Validate font family against whitelist
            $allowed_fonts = ['Arial', 'Times New Roman', 'Courier New', 'Georgia', 'Verdana', 'Brush Script MT', 'Lucida Handwriting'];
            
            if (!in_array($font_family, $allowed_fonts)) {
                // Use default font if invalid
                $font_family = 'Arial';
            }
            
            if (empty($signature_text)) {
                $message = 'Please enter signature text.';
                $message_type = 'error';
            } elseif (strlen($signature_text) > 100) {
                $message = 'Signature text is too long (max 100 characters).';
                $message_type = 'error';
            } else {
                // Build signature data JSON
                $signature_data = json_encode([
                    'text' => $signature_text,
                    'font' => $font_family
                ]);
                
                // Use transaction for atomic update
                $conn->begin_transaction();
                
                try {
                    // Deactivate old signatures
                    $deactivate_sql = "UPDATE teacher_signatures SET is_active = 0 WHERE teacher_id = ?";
                    $deactivate_stmt = $conn->prepare($deactivate_sql);
                    $deactivate_stmt->bind_param("i", $teacher_id);
                    $deactivate_stmt->execute();
                    $deactivate_stmt->close();
                    
                    // Insert new signature
                    $signature_hash = hash('sha256', $signature_data . time());
                    $signature_type = 'text';
                    $insert_sql = "INSERT INTO teacher_signatures (teacher_id, signature_type, signature_data, signature_hash, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("isss", $teacher_id, $signature_type, $signature_data, $signature_hash);
                    
                    if (!$insert_stmt->execute()) {
                        throw new Exception('Failed to save signature.');
                    }
                    $insert_stmt->close();
                    
                    $conn->commit();
                    $message = 'Text signature saved successfully!';
                    $message_type = 'success';
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log('Text signature save error for teacher ' . $teacher_id . ': ' . $e->getMessage());
                    $message = 'Failed to save signature. Please try again.';
                    $message_type = 'error';
                }
            }
        }
    }
}

// Get current active signature
$current_signature = null;
$sig_sql = "SELECT * FROM teacher_signatures WHERE teacher_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1";
$sig_stmt = $conn->prepare($sig_sql);
$sig_stmt->bind_param("i", $teacher_id);
$sig_stmt->execute();
$sig_result = $sig_stmt->get_result();
if ($sig_result->num_rows > 0) {
    $current_signature = $sig_result->fetch_assoc();
}
$sig_stmt->close();

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Signature - Teacher Portal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .signature-options {
            margin: 20px 0;
        }
        .signature-option {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        canvas {
            border: 1px solid #000;
            cursor: crosshair;
        }
        button {
            padding: 10px 20px;
            margin: 5px;
            cursor: pointer;
        }
        .current-signature {
            margin: 20px 0;
            padding: 20px;
            border: 2px solid #28a745;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1>Digital Signature Management</h1>
    
    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($current_signature): ?>
        <div class="current-signature">
            <h3>Current Active Signature</h3>
            <p>Type: <?php echo htmlspecialchars($current_signature['signature_type']); ?></p>
            <?php if ($current_signature['signature_type'] === 'upload'): ?>
                <img src="../uploads/signatures/<?php echo htmlspecialchars($current_signature['signature_data']); ?>" alt="Signature" style="max-width: 300px;">
            <?php elseif ($current_signature['signature_type'] === 'drawn'): ?>
                <img src="<?php echo htmlspecialchars($current_signature['signature_data']); ?>" alt="Signature" style="max-width: 300px;">
            <?php elseif ($current_signature['signature_type'] === 'text'): ?>
                <?php
                    $text_data = json_decode($current_signature['signature_data'], true);
                    if ($text_data && isset($text_data['text']) && isset($text_data['font'])) {
                        echo '<p style="font-family: ' . htmlspecialchars($text_data['font']) . '; font-size: 24px;">' . htmlspecialchars($text_data['text']) . '</p>';
                    }
                ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="signature-options">
        <div class="signature-option">
            <h3>Upload Signature Image</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="upload_signature">
                <input type="file" name="signature_file" accept="image/png,image/jpeg" required>
                <button type="submit">Upload Signature</button>
            </form>
            <p><small>Supported formats: PNG, JPEG. Max size: 2MB.</small></p>
        </div>
        
        <div class="signature-option">
            <h3>Draw Signature</h3>
            <canvas id="signatureCanvas" width="400" height="200"></canvas>
            <br>
            <button onclick="clearCanvas()">Clear</button>
            <button onclick="saveDrawnSignature()">Save Signature</button>
            <form id="drawnSignatureForm" method="POST" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="save_drawn_signature">
                <input type="hidden" name="signature_data" id="signatureData">
            </form>
        </div>
        
        <div class="signature-option">
            <h3>Text Signature</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="save_text_signature">
                <input type="text" name="signature_text" placeholder="Enter your name" maxlength="100" required>
                <select name="font_family">
                    <option value="Arial">Arial</option>
                    <option value="Times New Roman">Times New Roman</option>
                    <option value="Courier New">Courier New</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Verdana">Verdana</option>
                    <option value="Brush Script MT">Brush Script MT</option>
                    <option value="Lucida Handwriting">Lucida Handwriting</option>
                </select>
                <button type="submit">Save Text Signature</button>
            </form>
        </div>
    </div>
    
    <script>
        // Canvas drawing functionality
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let drawing = false;
        
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        function startDrawing(e) {
            drawing = true;
            ctx.beginPath();
            ctx.moveTo(e.offsetX, e.offsetY);
        }
        
        function draw(e) {
            if (!drawing) return;
            ctx.lineTo(e.offsetX, e.offsetY);
            ctx.stroke();
        }
        
        function stopDrawing() {
            drawing = false;
        }
        
        function clearCanvas() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
        
        function saveDrawnSignature() {
            const dataURL = canvas.toDataURL('image/png');
            document.getElementById('signatureData').value = dataURL;
            document.getElementById('drawnSignatureForm').submit();
        }
    </script>
    
    <br><br>
    <a href="index.php">Back to Dashboard</a>
</body>
</html>
