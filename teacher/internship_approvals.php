<?php
    session_start();

    // Check if user is logged in as a teacher
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: ../index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get teacher data and check if they are a counselor
    $username     = $_SESSION['username'];
    $teacher_data = null;
    $is_counselor = false;

    $sql  = "SELECT * FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $is_counselor = ($teacher_data['status'] === 'counselor' || $teacher_data['status'] === 'admin');
    } else {
        header("Location: ../index.php");
        exit();
    }

    if (! $is_counselor) {
        $_SESSION['access_denied'] = 'Only counselors can access internship approvals. Your role is: ' . ucfirst($teacher_data['status']);
        header("Location: index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    // Handle internship approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_internship_status'])) {
        $internship_id     = intval($_POST['internship_id']);
        $new_status        = $_POST['new_status'];
        $counselor_remarks = trim($_POST['counselor_remarks']);

        // Validate status
        if (! in_array($new_status, ['pending', 'approved', 'rejected'])) {
            $message      = "Invalid status provided.";
            $message_type = 'error';
        } else {
            $update_sql  = "UPDATE internship_submissions SET approval_status = ?, counselor_remarks = ?, approved_by = ?, approval_date = CURRENT_TIMESTAMP WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssii", $new_status, $counselor_remarks, $teacher_data['id'], $internship_id);

            if ($update_stmt->execute()) {
                $message      = "Internship " . ucfirst($new_status) . " successfully!";
                $message_type = 'success';
            } else {
                $message      = "Error updating internship: " . $conn->error;
                $message_type = 'error';
            }
            $update_stmt->close();
        }
    }

    // Get assigned students for this counselor
    $assigned_students_sql = "SELECT student_regno FROM counselor_assignments
                             WHERE counselor_id = ? AND status = 'active'";
    $assigned_students_stmt = $conn->prepare($assigned_students_sql);
    $assigned_students_stmt->bind_param("i", $teacher_data['id']);
    $assigned_students_stmt->execute();
    $assigned_students_result = $assigned_students_stmt->get_result();

    $student_regnos = [];
    while ($row = $assigned_students_result->fetch_assoc()) {
        $student_regnos[] = $row['student_regno'];
    }
    $assigned_students_stmt->close();

    // Get internship submissions for assigned students
    if (! empty($student_regnos)) {
        $placeholders   = implode(',', array_fill(0, count($student_regnos), '?'));
        $internship_sql = "SELECT i.*, sr.name as student_name, sr.department, sr.year_of_join
                          FROM internship_submissions i
                          JOIN student_register sr ON i.regno = sr.regno
                          WHERE i.regno IN ($placeholders)
                          ORDER BY i.submission_date DESC";
        $internship_stmt = $conn->prepare($internship_sql);
        $internship_stmt->bind_param(str_repeat('s', count($student_regnos)), ...$student_regnos);
        $internship_stmt->execute();
        $internship_result = $internship_stmt->get_result();

        // Fetch all results into an array for reuse
        $internship_array = [];
        while ($row = $internship_result->fetch_assoc()) {
            $internship_array[] = $row;
        }
        $internship_stmt->close();
    } else {
        $internship_array = [];
    }

    // Get statistics
    if (! empty($student_regnos)) {
        $stats_sql = "SELECT
                        COUNT(*) as total_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'pending' THEN 1 ELSE 0 END) as pending_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'approved' THEN 1 ELSE 0 END) as approved_submissions,
                        SUM(CASE WHEN COALESCE(approval_status, 'pending') = 'rejected' THEN 1 ELSE 0 END) as rejected_submissions
                      FROM internship_submissions
                      WHERE regno IN ($placeholders)";
        $stats_stmt = $conn->prepare($stats_sql);
        $stats_stmt->bind_param(str_repeat('s', count($student_regnos)), ...$student_regnos);
        $stats_stmt->execute();
        $stats_result = $stats_stmt->get_result();
        $stats        = $stats_result->fetch_assoc();
        $stats_stmt->close();
    } else {
        $stats = [
            'total_submissions'    => 0,
            'pending_submissions'  => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
        ];
    }

    $stmt->close();
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Internship Approvals - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Prevent mobile zoom and overflow */
        * {
            box-sizing: border-box;
            max-width: 100%;
        }

        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }

        /* Override default margins and paddings for wider content */
        .main {
            padding: 15px !important;
            margin: 0 !important;
        }

        .grid-container {
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Sidebar width optimization */
        .sidebar {
            width: 250px !important;
            min-width: 250px !important;
        }

        /* Statistics grid full width */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            padding: 0 5px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-width: 150px;
        }

        .stat-card h3 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #0c3878;
        }

        /* Alert styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }

        /* Table styles */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #0c3878;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }

        table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
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
            background-color: rgba(0, 0, 0, 0.5);
            padding: 20px;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            margin: 0;
            color: #0c3878;
            font-size: 22px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: #0c3878;
            color: white;
            flex: 1;
        }

        .btn-primary:hover {
            background-color: #082553;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .certificate-link {
            color: #0c3878;
            text-decoration: none;
            font-weight: 500;
            border-bottom: 1px dashed #0c3878;
        }

        .certificate-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            table {
                font-size: 12px;
            }

            table th,
            table td {
                padding: 8px 10px;
            }

            .modal-content {
                padding: 20px;
            }

            .action-btns {
                flex-direction: column;
            }

            .btn-sm {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Sidebar Navigation -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Internship Approvals</h2>
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="assigned_students.php">Assigned Students</a></li>
                <li><a href="od_approvals.php">OD Approvals</a></li>
                <li class="active"><a href="internship_approvals.php">Internship Approvals</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="main">
            <div class="page-header">
                <h1>Internship Approvals</h1>
                <p>Review and approve internship submissions from assigned students</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Submissions</h3>
                    <div class="number"><?php echo $stats['total_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="number" style="color: #ff9800;"><?php echo $stats['pending_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Approved</h3>
                    <div class="number" style="color: #28a745;"><?php echo $stats['approved_submissions'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rejected</h3>
                    <div class="number" style="color: #dc3545;"><?php echo $stats['rejected_submissions'] ?? 0; ?></div>
                </div>
            </div>

            <!-- Internship Submissions Table -->
            <div class="table-container">
                <?php if (empty($internship_array)): ?>
                    <div style="padding: 40px; text-align: center; color: #666;">
                        <p>No internship submissions found for your assigned students.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Reg No</th>
                                <th>Company</th>
                                <th>Role</th>
                                <th>Domain</th>
                                <th>Duration</th>
                                <th>Certificate</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internship_array as $internship): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($internship['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['regno']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['role_title']); ?></td>
                                    <td><?php echo htmlspecialchars($internship['domain']); ?></td>
                                    <td>
                                        <?php
                                            $start = date('M d, Y', strtotime($internship['start_date']));
                                            $end   = date('M d, Y', strtotime($internship['end_date']));
                                            echo "$start - $end";
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($internship['internship_certificate']): ?>
                                            <a href="../uploads/<?php echo htmlspecialchars($internship['internship_certificate']); ?>"
                                               target="_blank" class="certificate-link">View</a>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $status = $internship['approval_status'] ?? 'pending'; ?>
                                        <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-sm btn-primary"
                                                    onclick="openModal(<?php echo $internship['id']; ?>, '<?php echo htmlspecialchars($internship['student_name']); ?>')">
                                                Review
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Review Internship Submission</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" id="internshipId" name="internship_id" value="">

                <div class="form-group">
                    <label for="statusSelect">Approval Status:</label>
                    <select id="statusSelect" name="new_status" required>
                        <option value="">-- Select Status --</option>
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="remarksInput">Counselor Remarks (Optional):</label>
                    <textarea id="remarksInput" name="counselor_remarks" placeholder="Add any remarks or feedback..."></textarea>
                </div>

                <input type="hidden" name="update_internship_status" value="1">

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Decision</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(internshipId, studentName) {
            document.getElementById('internshipId').value = internshipId;
            document.querySelector('.modal-header h2').textContent = `Review Internship: ${studentName}`;
            document.getElementById('reviewModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('reviewModal').classList.remove('show');
            document.getElementById('statusSelect').value = '';
            document.getElementById('remarksInput').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('reviewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
