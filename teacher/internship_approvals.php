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

// Generate CSRF token using config function
$csrf_token = generate_csrf_token();

$message = '';
$message_type = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_internship_status') {
    // CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'error';
    } else {
        $internship_id = filter_input(INPUT_POST, 'internship_id', FILTER_VALIDATE_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        
        if ($internship_id && in_array($status, ['pending', 'approved', 'rejected'])) {
            try {
                $conn = get_db_connection();
                
                // Authorization check: verify counselor owns this internship
                $check_stmt = $conn->prepare("
                    SELECT i.id 
                    FROM internships i
                    INNER JOIN students s ON i.student_id = s.id
                    WHERE i.id = ? AND s.assigned_counselor_id = ?
                ");
                $check_stmt->bind_param("ii", $internship_id, $teacher_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 0) {
                    $message = 'An error occurred. Please try again.';
                    $message_type = 'error';
                    error_log("Authorization failed: Teacher $teacher_id attempted to update internship $internship_id");
                } else {
                    // Update status
                    $update_stmt = $conn->prepare("UPDATE internships SET status = ? WHERE id = ?");
                    $update_stmt->bind_param("si", $status, $internship_id);
                    
                    if ($update_stmt->execute()) {
                        $message = 'Internship status updated successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'An error occurred. Please try again.';
                        $message_type = 'error';
                        error_log("Database error updating internship status: " . $update_stmt->error);
                    }
                    $update_stmt->close();
                }
                $check_stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $message = 'An error occurred. Please try again.';
                $message_type = 'error';
                error_log("Exception in internship status update: " . $e->getMessage());
            }
        } else {
            $message = 'Invalid input. Please try again.';
            $message_type = 'error';
        }
    }
}

// Fetch internship applications
$internships = [];
try {
    $conn = get_db_connection();
    $stmt = $conn->prepare("
        SELECT 
            i.id,
            i.student_id,
            i.company_name,
            i.start_date,
            i.end_date,
            i.certificate_path,
            i.status,
            i.submitted_at,
            s.name as student_name,
            s.email as student_email,
            s.department
        FROM internships i
        INNER JOIN students s ON i.student_id = s.id
        WHERE s.assigned_counselor_id = ?
        ORDER BY i.submitted_at DESC
    ");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $internships[] = $row;
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $message = 'An error occurred loading internship data.';
    $message_type = 'error';
    error_log("Exception fetching internships: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Approvals - Counselor Dashboard</title>
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
            max-width: 1200px;
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

        .internships-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .internships-table th,
        .internships-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .internships-table th {
            background-color: #667eea;
            color: white;
            font-weight: 600;
        }

        .internships-table tr:hover {
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

        .action-buttons {
            display: flex;
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

        .certificate-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .certificate-link:hover {
            text-decoration: underline;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 22px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .info-row {
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }

        .info-value {
            color: #333;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-close {
            background-color: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 22px;
            }

            .internships-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .internship-card {
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
                margin: 20% auto;
                padding: 20px;
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
        
        <h1>Internship Approvals</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($internships)): ?>
            <div class="no-data">
                No internship applications found.
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <table class="internships-table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Department</th>
                        <th>Company</th>
                        <th>Duration</th>
                        <th>Certificate</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($internships as $internship): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($internship['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($internship['department']); ?></td>
                            <td><?php echo htmlspecialchars($internship['company_name']); ?></td>
                            <td>
                                <?php 
                                echo htmlspecialchars(date('M d, Y', strtotime($internship['start_date']))) . ' - ' . 
                                     htmlspecialchars(date('M d, Y', strtotime($internship['end_date']))); 
                                ?>
                            </td>
                            <td id="cert-cell-<?php echo (int)$internship['id']; ?>">
                                <!-- Certificate link will be inserted via DOM APIs -->
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($internship['status']); ?>">
                                    <?php echo htmlspecialchars($internship['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button 
                                        class="btn btn-approve" 
                                        onclick="updateStatus(<?php echo json_encode([
                                            'id' => (int)$internship['id'],
                                            'status' => 'approved',
                                            'student' => $internship['student_name'],
                                            'company' => $internship['company_name']
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                        Approve
                                    </button>
                                    <button 
                                        class="btn btn-reject" 
                                        onclick="updateStatus(<?php echo json_encode([
                                            'id' => (int)$internship['id'],
                                            'status' => 'rejected',
                                            'student' => $internship['student_name'],
                                            'company' => $internship['company_name']
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                        Reject
                                    </button>
                                    <button 
                                        class="btn btn-pending" 
                                        onclick="updateStatus(<?php echo json_encode([
                                            'id' => (int)$internship['id'],
                                            'status' => 'pending',
                                            'student' => $internship['student_name'],
                                            'company' => $internship['company_name']
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                        Pending
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Mobile Cards View -->
            <div class="mobile-cards">
                <?php foreach ($internships as $internship): ?>
                    <div class="internship-card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($internship['student_name']); ?></div>
                            <span class="status-badge status-<?php echo htmlspecialchars($internship['status']); ?>">
                                <?php echo htmlspecialchars($internship['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="card-row">
                                <span class="card-label">Department:</span>
                                <span class="card-value"><?php echo htmlspecialchars($internship['department']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Company:</span>
                                <span class="card-value"><?php echo htmlspecialchars($internship['company_name']); ?></span>
                            </div>
                            <div class="card-row">
                                <span class="card-label">Duration:</span>
                                <span class="card-value">
                                    <?php 
                                    echo htmlspecialchars(date('M d, Y', strtotime($internship['start_date']))) . ' - ' . 
                                         htmlspecialchars(date('M d, Y', strtotime($internship['end_date']))); 
                                    ?>
                                </span>
                            </div>
                            <div class="card-row" id="cert-mobile-<?php echo (int)$internship['id']; ?>">
                                <!-- Certificate link will be inserted via DOM APIs -->
                            </div>
                        </div>
                        <div class="card-actions">
                            <button 
                                class="btn btn-approve" 
                                onclick="updateStatus(<?php echo json_encode([
                                    'id' => (int)$internship['id'],
                                    'status' => 'approved',
                                    'student' => $internship['student_name'],
                                    'company' => $internship['company_name']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                Approve
                            </button>
                            <button 
                                class="btn btn-reject" 
                                onclick="updateStatus(<?php echo json_encode([
                                    'id' => (int)$internship['id'],
                                    'status' => 'rejected',
                                    'student' => $internship['student_name'],
                                    'company' => $internship['company_name']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                Reject
                            </button>
                            <button 
                                class="btn btn-pending" 
                                onclick="updateStatus(<?php echo json_encode([
                                    'id' => (int)$internship['id'],
                                    'status' => 'pending',
                                    'student' => $internship['student_name'],
                                    'company' => $internship['company_name']
                                ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                Pending
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Status Change</h2>
            </div>
            <div class="modal-body">
                <div class="info-row">
                    <div class="info-label">Student:</div>
                    <div class="info-value" id="modal-student"></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Company:</div>
                    <div class="info-value" id="modal-company"></div>
                </div>
                <div class="info-row">
                    <div class="info-label">New Status:</div>
                    <div class="info-value" id="modal-status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-close" onclick="closeModal()">Cancel</button>
                <button class="btn btn-approve" onclick="confirmUpdate()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for status update -->
    <form id="statusUpdateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_internship_status">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <input type="hidden" name="internship_id" id="form-internship-id">
        <input type="hidden" name="status" id="form-status">
    </form>

    <script>
        // Certificate data preparation
        const certificateData = <?php echo json_encode(array_map(function($internship) {
            return [
                'id' => (int)$internship['id'],
                'path' => $internship['certificate_path'] ? basename($internship['certificate_path']) : null
            ];
        }, $internships), JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Safely insert certificate links using DOM APIs
        document.addEventListener('DOMContentLoaded', function() {
            certificateData.forEach(function(cert) {
                if (cert.path) {
                    // Desktop view
                    const desktopCell = document.getElementById('cert-cell-' + cert.id);
                    if (desktopCell) {
                        const link = document.createElement('a');
                        link.href = '../uploads/' + encodeURIComponent(cert.path);
                        link.className = 'certificate-link';
                        link.target = '_blank';
                        link.textContent = 'View Certificate';
                        desktopCell.appendChild(link);
                    }

                    // Mobile view
                    const mobileCell = document.getElementById('cert-mobile-' + cert.id);
                    if (mobileCell) {
                        const label = document.createElement('span');
                        label.className = 'card-label';
                        label.textContent = 'Certificate: ';
                        
                        const link = document.createElement('a');
                        link.href = '../uploads/' + encodeURIComponent(cert.path);
                        link.className = 'certificate-link';
                        link.target = '_blank';
                        link.textContent = 'View';
                        
                        mobileCell.appendChild(label);
                        mobileCell.appendChild(link);
                    }
                } else {
                    // Desktop view
                    const desktopCell = document.getElementById('cert-cell-' + cert.id);
                    if (desktopCell) {
                        desktopCell.textContent = 'N/A';
                    }

                    // Mobile view
                    const mobileCell = document.getElementById('cert-mobile-' + cert.id);
                    if (mobileCell) {
                        const label = document.createElement('span');
                        label.className = 'card-label';
                        label.textContent = 'Certificate: ';
                        
                        const value = document.createElement('span');
                        value.className = 'card-value';
                        value.textContent = 'N/A';
                        
                        mobileCell.appendChild(label);
                        mobileCell.appendChild(value);
                    }
                }
            });
        });

        let currentUpdate = null;

        function updateStatus(data) {
            currentUpdate = data;
            document.getElementById('modal-student').textContent = data.student;
            document.getElementById('modal-company').textContent = data.company;
            document.getElementById('modal-status').textContent = data.status.toUpperCase();
            document.getElementById('confirmModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
            currentUpdate = null;
        }

        function confirmUpdate() {
            if (currentUpdate) {
                document.getElementById('form-internship-id').value = currentUpdate.id;
                document.getElementById('form-status').value = currentUpdate.status;
                document.getElementById('statusUpdateForm').submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
