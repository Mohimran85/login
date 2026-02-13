<?php
session_start();
require_once 'config.php';

// Require teacher role
require_teacher_role();

$teacher_id = $_SESSION['teacher_id'] ?? null;

if (!$teacher_id) {
    // Get teacher ID from database if not in session
    $username = $_SESSION['username'];
    $conn = get_db_connection();
    $stmt = $conn->prepare("SELECT id FROM teacher_register WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_id = $result->fetch_assoc()['id'];
        $_SESSION['teacher_id'] = $teacher_id;
    } else {
        header("Location: ../index.php");
        exit();
    }
    $stmt->close();
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Handle status update via button name/value (non-JS form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $od_id = null;
    $new_status = null;
    
    // Read button name/value directly (format: update_od_status_{od_id}_{status})
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'update_od_status_') === 0) {
            $parts = explode('_', $key);
            if (count($parts) >= 5) {
                $od_id = isset($parts[3]) ? $parts[3] : null;
                $new_status = isset($parts[4]) ? $parts[4] : null;
            }
            break;
        }
    }
    
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } else {
        // Validate $od_id as integer
        $od_id = filter_var($od_id, FILTER_VALIDATE_INT);
        
        // Validate $new_status against whitelist
        $status_whitelist = ['approved', 'rejected', 'pending'];
        
        if ($od_id !== false && $od_id > 0 && in_array($new_status, $status_whitelist, true)) {
            try {
                $conn = get_db_connection();
                
                // Authorization check: verify counselor owns this OD request
                $check_stmt = $conn->prepare("
                    SELECT od.id 
                    FROM od_requests od
                    INNER JOIN students s ON od.student_id = s.id
                    WHERE od.id = ? AND s.assigned_counselor_id = ?
                ");
                $check_stmt->bind_param("ii", $od_id, $teacher_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $message = 'An error occurred. Please try again.';
                    $message_type = 'error';
                    error_log("Authorization failed: Teacher $teacher_id attempted to update OD request $od_id");
                } else {
                    // Update status
                    $update_stmt = $conn->prepare("UPDATE od_requests SET status = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $new_status, $od_id);
                    
                    if ($update_stmt->execute()) {
                        $message = 'OD status updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'An error occurred. Please try again.';
                        $message_type = 'error';
                        error_log("Database error updating OD status: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }
                $check_stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $message = 'An error occurred. Please try again.';
                $message_type = 'error';
                error_log("Exception in OD status update: " . $e->getMessage());
            }
        } else {
            $message = 'Invalid input. Please try again.';
            $message_type = 'error';
            error_log("Invalid OD update input - od_id: " . var_export($od_id, true) . ", status: " . var_export($new_status, true));
        }
    }
}

// Fetch OD requests
$od_requests = [];
try {
    $conn = get_db_connection();
    $stmt = $conn->prepare("
        SELECT 
            od.id,
            od.student_id,
            od.event_name,
            od.event_date,
            od.event_start_time,
            od.event_end_time,
            od.event_venue,
            od.event_organizer,
            od.event_poster,
            od.status,
            od.submitted_at,
            s.name as student_name,
            s.email as student_email,
            s.department,
            s.year
        FROM od_requests od
        INNER JOIN students s ON od.student_id = s.id
        WHERE s.assigned_counselor_id = ?
        ORDER BY od.submitted_at DESC
    ");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $od_requests[] = $row;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $message = 'An error occurred loading OD data.';
    $message_type = 'error';
    error_log("Exception fetching OD requests: " . $e->getMessage());
}

// Sanitize and validate poster paths
function get_safe_poster_path($poster_path) {
    if (empty($poster_path)) {
        return null;
    }
    
    // Use basename to prevent path traversal
    $safe_filename = basename($poster_path);
    
    // Validate extension against whitelist
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $extension = strtolower(pathinfo($safe_filename, PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_extensions, true)) {
        error_log("Invalid poster file extension: " . $extension);
        return null;
    }
    
    return $safe_filename;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OD Approvals - Counselor Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .message {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
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

        .od-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .od-table th,
        .od-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .od-table th {
            background-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .od-table tr:hover {
            background-color: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background-color: #ffc107;
            color: #000;
        }

        .status-approved {
            background-color: #28a745;
            color: white;
        }

        .status-rejected {
            background-color: #dc3545;
            color: white;
        }

        .action-form {
            display: inline-flex;
            gap: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .btn-approve {
            background-color: #28a745;
            color: white;
        }

        .btn-reject {
            background-color: #dc3545;
            color: white;
        }

        .btn-pending {
            background-color: #ffc107;
            color: #000;
        }

        .poster-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .poster-link:hover {
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow: auto;
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            max-width: 90%;
            max-height: 85vh;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .modal-header h2 {
            color: #333;
            font-size: 22px;
        }

        .close-btn {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            line-height: 1;
        }

        .close-btn:hover {
            color: #000;
        }

        .modal-body {
            text-align: center;
        }

        .modal-body img {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 22px;
            }

            .od-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .od-card {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                padding-bottom: 10px;
                border-bottom: 2px solid #667eea;
            }

            .card-title {
                font-weight: 600;
                color: #333;
                font-size: 16px;
            }

            .card-body {
                margin-bottom: 12px;
            }

            .card-row {
                margin-bottom: 8px;
                font-size: 14px;
            }

            .card-label {
                font-weight: 600;
                color: #555;
            }

            .card-value {
                color: #333;
            }

            .card-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            .card-actions .btn {
                flex: 1;
                min-width: 80px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 15px;
            }
        }

        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>
        
        <h1>OD (On Duty) Approvals</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($od_requests)): ?>
            <div class="no-data">
                No OD requests found.
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <table class="od-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Event Name</th>
                        <th>Event Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Organizer</th>
                        <th>Poster</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($od_requests as $od): ?>
                        <?php 
                        $safe_poster = get_safe_poster_path($od['event_poster']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($od['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($od['department']); ?></td>
                            <td><?php echo htmlspecialchars($od['year']); ?></td>
                            <td><?php echo htmlspecialchars($od['event_name']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($od['event_date']))); ?></td>
                            <td>
                                <?php 
                                echo htmlspecialchars($od['event_start_time']) . ' - ' . 
                                     htmlspecialchars($od['event_end_time']); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($od['event_venue']); ?></td>
                            <td><?php echo htmlspecialchars($od['event_organizer']); ?></td>
                            <td>
                                <?php if ($safe_poster): ?>
                                    <a href="#" class="poster-link" onclick="openPosterModal(<?php echo json_encode($safe_poster); ?>); return false;">
                                        View Poster
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($od['status']); ?>">
                                    <?php echo htmlspecialchars($od['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="action-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_approved" value="1" class="btn btn-approve">
                                        Approve
                                    </button>
                                    <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_rejected" value="1" class="btn btn-reject">
                                        Reject
                                    </button>
                                    <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_pending" value="1" class="btn btn-pending">
                                        Pending
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Mobile Cards View -->
            <div class="mobile-cards">
                <?php foreach ($od_requests as $od): ?>
                    <?php 
                    $safe_poster = get_safe_poster_path($od['event_poster']);
                    ?>
                    <div class="od-card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($od['student_name']); ?></div>
                            <span class="status-badge status-<?php echo htmlspecialchars($od['status']); ?>">
                                <?php echo htmlspecialchars($od['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-row">
                                <span class="card-label">Department:</span>
                                <span class="card-value"><?php echo htmlspecialchars($od['department']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Year:</span>
                                <span class="card-value"><?php echo htmlspecialchars($od['year']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Event:</span>
                                <span class="card-value"><?php echo htmlspecialchars($od['event_name']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Date:</span>
                                <span class="card-value"><?php echo htmlspecialchars(date('M d, Y', strtotime($od['event_date']))); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Time:</span>
                                <span class="card-value">
                                    <?php 
                                    echo htmlspecialchars($od['event_start_time']) . ' - ' . 
                                         htmlspecialchars($od['event_end_time']); 
                                    ?>
                                </span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Venue:</span>
                                <span class="card-value"><?php echo htmlspecialchars($od['event_venue']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Organizer:</span>
                                <span class="card-value"><?php echo htmlspecialchars($od['event_organizer']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Poster:</span>
                                <span class="card-value">
                                    <?php if ($safe_poster): ?>
                                        <a href="#" class="poster-link" onclick="openPosterModal(<?php echo json_encode($safe_poster); ?>); return false;">
                                            View
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <form method="POST" class="action-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_approved" value="1" class="btn btn-approve">
                                    Approve
                                </button>
                                <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_rejected" value="1" class="btn btn-reject">
                                    Reject
                                </button>
                                <button type="submit" name="update_od_status_<?php echo (int)$od['id']; ?>_pending" value="1" class="btn btn-pending">
                                    Pending
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Poster Modal -->
    <div id="posterModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Event Poster</h2>
                <button class="close-btn" onclick="closePosterModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="posterImage" src="" alt="Event Poster">
            </div>
        </div>
    </div>

    <script>
        function openPosterModal(posterFilename) {
            var modal = document.getElementById('posterModal');
            var img = document.getElementById('posterImage');
            
            // Use json_encode for safe JavaScript variable
            img.src = '../uploads/' + encodeURIComponent(posterFilename);
            modal.style.display = 'block';
        }

        function closePosterModal() {
            var modal = document.getElementById('posterModal');
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('posterModal');
            if (event.target === modal) {
                closePosterModal();
            }
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePosterModal();
            }
        });
    </script>
</body>
</html>
