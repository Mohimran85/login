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
    $is_admin     = false;

    $sql  = "SELECT * FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
        $is_counselor = ($teacher_data['status'] === 'counselor' || $teacher_data['status'] === 'admin');
        $is_admin     = ($teacher_data['status'] === 'admin');
    } else {
        header("Location: ../index.php");
        exit();
    }

    if (! $is_counselor) {
        $_SESSION['access_denied'] = 'Only counselors can access OD approvals. Your role is: ' . ucfirst($teacher_data['status']);
        header("Location: index.php");
        exit();
    }

    $message      = '';
    $message_type = '';

    // Handle OD request approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_od_status'])) {
        $od_id             = $_POST['od_id'];
        $new_status        = $_POST['new_status'];
        $counselor_remarks = trim($_POST['counselor_remarks']);

        $update_sql  = "UPDATE od_requests SET status = ?, counselor_remarks = ?, response_date = CURRENT_TIMESTAMP WHERE id = ? AND counselor_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssii", $new_status, $counselor_remarks, $od_id, $teacher_data['id']);

        if ($update_stmt->execute()) {
            $message      = "OD request " . ucfirst($new_status) . " successfully!";
            $message_type = 'success';
        } else {
            $message      = "Error updating OD request: " . $conn->error;
            $message_type = 'error';
        }
        $update_stmt->close();
    }

    // Get OD requests for this counselor
    $od_requests_sql = "SELECT od.*, sr.name as student_name, sr.department, sr.year_of_join, od.group_members
                        FROM od_requests od
                        JOIN student_register sr ON od.student_regno = sr.regno
                        WHERE od.counselor_id = ?
                        ORDER BY od.request_date DESC";
    $od_requests_stmt = $conn->prepare($od_requests_sql);
    $od_requests_stmt->bind_param("i", $teacher_data['id']);
    $od_requests_stmt->execute();
    $od_requests_result = $od_requests_stmt->get_result();

    // Fetch all results into an array for reuse
    $od_requests_array = [];
    while ($row = $od_requests_result->fetch_assoc()) {
        $od_requests_array[] = $row;
    }

    // Get statistics
    $stats_sql = "SELECT
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
                  FROM od_requests WHERE counselor_id = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("i", $teacher_data['id']);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats        = $stats_result->fetch_assoc();

    $stmt->close();
    $od_requests_stmt->close();
    $stats_stmt->close();
    // Keep connection open for group members queries
    // $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>OD Approvals - Teacher Dashboard</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
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

        /* Header */
        .header {
            grid-area: header;
            background-color: #fff;
            height: 80px;
            display: flex;
            font-size: 15px;
            font-weight: 100;
            align-items: center;
            justify-content: space-between;
            box-shadow: rgba(50, 50, 93, 0.25) 0px 6px 12px -2px, rgba(0, 0, 0, 0.3) 0px 3px 7px -3px;
            color: #1e4276;
            position: fixed;
            width: 100%;
            z-index: 1001;
            top: 0;
            left: 0;
            padding: 0 30px;
        }

        /* Override default margins and paddings for wider content */
        .main {
            padding: 20px;
            min-height: calc(100vh - 80px);
        }

        .grid-container {
            display: grid;
            grid-template-areas: "sidebar main";
            grid-template-columns: 280px 1fr;
            grid-template-rows: 1fr;
            min-height: 100vh;
            padding-top: 80px;
            transition: all 0.3s ease;
        }

        /* Sidebar width optimization */
        .sidebar {
            grid-area: sidebar;
            background: #ffffff;
            border-right: 1px solid #e9ecef;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            position: fixed;
            width: 280px;
            top: 80px;
            left: 0;
            height: calc(100vh - 80px);
            overflow-y: auto;
            transform: translateX(0);
            transition: transform 0.3s ease;
            z-index: 1000;
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            text-align: center;
        }

        .stat-card.pending { border-left-color: #ffc107; }
        .stat-card.approved { border-left-color: #28a745; }
        .stat-card.rejected { border-left-color: #dc3545; }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .od-requests-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 0;
            width: 100%;
            max-width: none;
        }

        .section-card {
            width: 100%;
            max-width: 100%;
            margin-bottom: 25px;
            margin-top: 25px;
            border-radius: 12px;
            border: 2px solid #f0f0f0;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            box-sizing: border-box;
        }

        .section-card:hover {
            border-color: #0c3878;
            box-shadow: 0 4px 15px rgba(12, 56, 120, 0.1);
        }

        .section-header {
            background: linear-gradient(135deg, #0c3878 0%, #2d5aa0 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            border-radius: 12px 12px 0 0;
        }

        .section-header h4 {
            margin: 0;
            font-size: 16px;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .section-header .material-symbols-outlined {
            font-size: 22px;
        }

        .od-request-item {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 40px;
            border-left: 6px solid #0c3878;
            display: none;
            flex-direction: column;
            gap: 20px;
            width: 100%;
            max-width: 100%;
            position: relative;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
        }

        .od-request-item.visible {
            display: flex;
        }

        .od-request-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0c3878, #2d5aa0, #0c3878);
            border-radius: 12px 12px 0 0;
        }

        .od-request-item.pending {
            border-left-color: #ffc107;
            animation: subtle-pulse 3s ease-in-out infinite;
            background: linear-gradient(135deg, #fff9e6 0%, #fffbf0 100%);
        }

        .od-request-item.pending::before {
            background: linear-gradient(90deg, #ffc107, #ffeb3b, #ffc107);
        }

        .od-request-item.approved {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #f0fff4 0%, #f8fff9 100%);
        }

        .od-request-item.approved::before {
            background: linear-gradient(90deg, #28a745, #20c997, #28a745);
        }

        .od-request-item.rejected {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5 0%, #fffafa 100%);
        }

        .od-request-item.rejected::before {
            background: linear-gradient(90deg, #dc3545, #e74c3c, #dc3545);
        }

        @keyframes subtle-pulse {
            0%, 100% {
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
                transform: scale(1);
            }
            50% {
                box-shadow: 0 8px 25px rgba(255, 193, 7, 0.2);
                transform: scale(1.01);
            }
        }

        /* Request Header with Student Info */
        .request-header-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(12, 56, 120, 0.1);
            border: 2px solid #e3f2fd;
            position: relative;
            overflow: hidden;
        }

        .request-header-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #0c3878, #2d5aa0, #0c3878);
        }

        .student-info-grid {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .student-info-grid {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 15px;
            }
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0c3878, #2d5aa0);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(12, 56, 120, 0.3);
        }

        .student-details-enhanced {
            flex: 1;
        }

        .student-name-large {
            font-size: 24px;
            font-weight: 700;
            color: #0c3878;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .student-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 14px;
            color: #6c757d;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .meta-item .material-symbols-outlined {
            font-size: 16px;
            color: #0c3878;
        }

        .student-info {
            text-align: center;
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: 1px solid #e9ecef;
        }

        .group-members-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 4px solid #17a2b8;
        }

        .group-members-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #0c3878;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .group-members-header .material-symbols-outlined {
            font-size: 22px;
            color: #17a2b8;
        }

        .group-members-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .group-member-item {
            background: white;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .group-member-item:hover {
            border-color: #17a2b8;
            box-shadow: 0 2px 8px rgba(23, 162, 184, 0.2);
            transform: translateY(-2px);
        }

        .group-member-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #17a2b8, #138496);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .group-member-details {
            flex: 1;
        }

        .group-member-regno {
            font-weight: 600;
            color: #0c3878;
            font-size: 13px;
        }

        .group-member-name {
            font-size: 12px;
            color: #6c757d;
            margin-top: 2px;
        }

        @media (max-width: 768px) {
            .group-members-list {
                grid-template-columns: 1fr;
            }
        }

        .student-info h3 {
            color: #ffffffff;
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 600;
        }

        .od-status {
            padding: 10px 25px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            align-self: center;
            min-width: 120px;
            text-align: center;
            margin-top: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .od-status.pending {
            background: linear-gradient(135deg, #ffc107 0%, #ffeb3b 100%);
            color: #856404;
            border: 2px solid #ffc107;
        }
        .od-status.approved {
            background: linear-gradient(135deg, #28a745 0%, #4caf50 100%);
            color: white;
            border: 2px solid #28a745;
        }
        .od-status.rejected {
            background: linear-gradient(135deg, #dc3545 0%, #f44336 100%);
            color: white;
            border: 2px solid #dc3545;
        }

        .event-details {
            display: flex;
            flex-direction: column;
            gap: 0;
            padding: 0;
            background: white;
            width: 100%;
            box-sizing: border-box;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }

        .event-detail-item {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            background: white;
            transition: background 0.2s ease;
        }

        .event-detail-item:hover {
            background: #f8f9fa;
        }

        .event-detail-item:last-child {
            border-bottom: none;
        }

        .event-detail-label {
            font-weight: 700;
            color: #0c3878;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 140px;
            flex-shrink: 0;
        }

        .event-detail-value {
            color: #2c3e50;
            font-size: 15px;
            font-weight: 500;
            flex: 1;
            word-wrap: break-word;
        }

        @media (max-width: 768px) {
            .event-detail-item {
                flex-direction: column;
                gap: 5px;
                padding: 12px 15px;
            }

            .event-detail-label {
                min-width: auto;
                width: 100%;
            }

            .event-detail-value {
                width: 100%;
            }
        }

        .description-box {
            background: white;
            padding: 20px;
            line-height: 1.8;
            font-size: 15px;
            color: #2c3e50;
            width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            border-radius: 0 0 12px 12px;
        }

        .approval-form {
            background: white;
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
        }

        .approval-form h4 {
            color: #0c3878;
            margin-bottom: 15px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            width: 100%;
            box-sizing: border-box;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .form-textarea {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            min-height: 80px;
            resize: vertical;
            transition: border-color 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            width: 100%;
        }

        .btn {
            padding: 15px 24px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #218838 0%, #1ea080 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333 0%, #c0392b 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

            @media (max-width: 768px) {
                .filter-section {
                    padding: 15px;
                    margin-bottom: 15px;
                    border-radius: 10px;
                }
            }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 600;
            color: #0c3878;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-input, .filter-select {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #0c3878;
            box-shadow: 0 0 0 3px rgba(12, 56, 120, 0.1);
        }

        .filter-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-btn-primary {
            background: linear-gradient(135deg, #0c3878 0%, #2d5aa0 100%);
            color: white;
        }

        .filter-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(12, 56, 120, 0.3);
        }

        .filter-btn-secondary {
            background: #6c757d;
            color: white;
        }

        .filter-btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Poster Modal Styles */
        .poster-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            animation: fadeIn 0.3s ease;
        }

        .poster-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            max-width: 90%;
            max-height: 90%;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            z-index: 2001;
        }

        .poster-modal img {
            width: 100%;
            height: auto;
            max-height: 70vh;
            object-fit: contain;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .poster-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
            background: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 2002;
        }

        .poster-modal-close:hover {
            color: #000;
            background: #f0f0f0;
        }

        .poster-controls {
            position: absolute;
            top: 15px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 2002;
        }

        .control-btn {
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            color: #333;
            transition: all 0.3s ease;
        }

        .control-btn:hover {
            background: #f0f0f0;
            transform: scale(1.1);
        }

        .control-btn .material-symbols-outlined {
            font-size: 20px;
        }

        .poster-modal-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            font-size: 12px;
            color: #6c757d;
        }

        /* OD Details Modal */
        .od-details-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }

        .od-details-modal-content {
            position: relative;
            background: white;
            margin: 30px auto;
            padding: 0;
            width: 90%;
            max-width: 1000px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .od-details-modal-header {
            background: linear-gradient(135deg, #0c3878 0%, #2d5aa0 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .od-details-modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
        }

        .od-details-modal-close {
            cursor: pointer;
            font-size: 28px;
            font-weight: bold;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .od-details-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .od-details-modal-body {
            padding: 25px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        /* Vertical list layout for modal content */
        .od-details-modal-body .od-request-item {
            display: flex !important;
            flex-direction: column;
            gap: 20px;
            padding: 0;
            background: transparent;
            box-shadow: none;
            border: none;
            margin: 0;
        }

        .od-details-modal-body .od-request-item::before {
            display: none;
        }

        .od-details-modal-body .section-card {
            margin: 0 0 15px 0;
            width: 100%;
            display: block;
        }

        .od-details-modal-body .request-header-card {
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .od-details-modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .od-details-modal-header {
                padding: 15px 20px;
            }

            .od-details-modal-header h3 {
                font-size: 16px;
            }

            .od-details-modal-body {
                padding: 15px;
                max-height: calc(100vh - 150px);
            }

            .od-request-item.visible {
                margin-bottom: 0;
            }
        }

        @media (max-width: 480px) {
            .od-details-modal-content {
                width: 98%;
                margin: 10px auto;
            }

            .od-details-modal-header {
                padding: 12px 15px;
            }

            .od-details-modal-body {
                padding: 12px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Table Responsive Styles */
        table {
            font-size: 14px;
        }

        table th, table td {
            white-space: nowrap;
        }

        @media (max-width: 1024px) {
            table {
                font-size: 13px;
            }

            table th, table td {
                padding: 12px 10px !important;
            }
        }

        @media (max-width: 768px) {
            /* Hide table, show mobile cards */
            table thead {
                display: none;
            }

            table, table tbody, table tr, table td {
                display: block;
                width: 100%;
            }

            table tr {
                margin-bottom: 20px;
                border: 2px solid #e9ecef;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                background: white;
                padding: 15px 15px 10px 15px;
            }

            table tr:hover {
                background: white !important;
            }

            table td {
                text-align: left !important;
                padding: 12px 0 !important;
                position: relative;
                border-bottom: 1px solid #f0f0f0;
                display: flex;
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            table td:first-child {
                padding-top: 0 !important;
            }

            table td:last-child {
                border-bottom: none;
                padding-bottom: 0 !important;
            }

            table td:before {
                content: attr(data-label);
                font-weight: 700;
                color: #0c3878;
                text-transform: uppercase;
                font-size: 10px;
                letter-spacing: 0.8px;
                display: block;
                width: 100%;
                margin-bottom: 5px;
                padding-bottom: 3px;
                border-bottom: 1px solid #e3f2fd;
            }

            table td > * {
                width: 100%;
                text-align: left;
                display: block;
            }

            table td div {
                width: 100%;
            }

            table td span {
                display: inline-block;
            }

            /* Fix nested content styling */
            table td > div {
                max-width: 100% !important;
                overflow: visible !important;
                text-overflow: clip !important;
            }

            table td > div > div {
                font-size: 12px;
                color: #6c757d;
                margin-top: 3px;
            }

            table td[data-label="Student"] > div,
            table td[data-label="Event Name"] > div,
            table td[data-label="Location"] > div {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            /* Make buttons full width on mobile */
            table td button,
            table td a {
                width: 100% !important;
                max-width: 100% !important;
                justify-content: center !important;
                margin-top: 5px;
                display: flex !important;
                align-items: center;
                gap: 5px;
                padding: 10px 16px !important;
                font-size: 14px !important;
            }

            .od-status {
                display: inline-block !important;
                margin: 5px 0 0 0 !important;
                width: auto !important;
                text-align: center;
                align-self: flex-start !important;
            }
        }

        /* Mobile sidebar overlay */
        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr !important;
                grid-template-areas: "main" !important;
                padding-top: 80px !important;
            }

            .main {
                padding: 15px !important;
                margin: 0 !important;
                width: 100% !important;
                grid-area: main !important;
            }

            .od-requests-container {
                padding: 15px;
                margin: 0;
                width: 100%;
                box-sizing: border-box;
            }

            .od-request-item.visible {
                display: flex !important;
                flex-direction: column !important;
            }

            .section-card {
                margin-bottom: 15px;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .section-header {
                padding: 10px 12px;
                font-size: 14px;
            }

            .section-header h4 {
                font-size: 14px;
            }

            .od-request-item {
                padding: 15px;
                margin-bottom: 20px;
            }

            .request-header-card {
                padding: 15px;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .event-details {
                padding: 0;
                width: 100% !important;
                display: flex;
                flex-direction: column;
                gap: 0;
            }

            .description-box, .approval-form {
                padding: 15px !important;
                width: 100% !important;
                font-size: 14px;
                line-height: 1.6;
            }

            .event-detail-item {
                padding: 12px 15px;
                margin-bottom: 0;
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .event-detail-label {
                width: 100%;
                text-align: left;
                font-size: 11px;
            }

            .event-detail-value {
                width: 100%;
                text-align: left;
                font-size: 14px;
            }

            .btn {
                padding: 16px 20px;
                font-size: 15px;
                font-weight: 700;
                width: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }

            .form-textarea {
                min-height: 60px;
                font-size: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 0;
                margin-bottom: 15px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-number {
                font-size: 1.8rem;
            }

            /* Mobile-friendly student info grid */
            .student-info-grid {
                display: flex;
                flex-direction: column;
                gap: 15px;
                align-items: center;
                text-align: center;
            }

            .student-avatar {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .student-details-enhanced {
                width: 100%;
            }

            .od-status {
                width: auto;
                margin-top: 10px;
                padding: 8px 20px;
                font-size: 12px;
            }

            .student-name-large {
                font-size: 18px;
            }

            .student-meta {
                flex-direction: column;
                gap: 8px;
                align-items: center;
            }

            .meta-item {
                font-size: 13px;
            }

            .sidebar {
                width: 100vw !important;
                min-width: 100vw !important;
                height: 100vh !important;
                min-height: 100vh !important;
                max-height: 100vh !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                z-index: 9999 !important;
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
                background: #ffffff !important;
                box-shadow: none !important;
                border-right: none !important;
                padding: 0 !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 10000 !important;
                background: #ffffff !important;
            }

            .sidebar.active::after {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(255, 255, 255, 1);
                z-index: -1;
                backdrop-filter: blur(2px);
            }

            /* Ensure close button is visible and functional */
            .close-sidebar {
                display: flex !important;
                position: absolute !important;
                top: 15px !important;
                right: 15px !important;
                z-index: 10001 !important;
            }

            /* Ensure sidebar header has proper spacing */
            .sidebar-header {
                padding: 60px 20px 20px 20px !important;
                position: relative;
                background: #ffffff !important;
            }

            /* Fix body when sidebar is open */
            body.sidebar-open {
                overflow: hidden !important;
                position: fixed !important;
                width: 100% !important;
                height: 100% !important;
            }

            /* Prevent scrolling issues on mobile */
            .grid-container {
                overflow-x: hidden !important;
            }

            /* Reduce content width to prevent zoom */
            .od-request-item {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                padding: 12px !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            .section-card {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .request-header-card {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }

            .event-details,
            .description-box,
            .approval-form {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                padding: 12px !important;
            }

            .form-textarea,
            .btn {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
        }

        /* Force white background for all sidebar components */
        .sidebar {
            background: #ffffff !important;
        }

        .sidebar-header {
            background: #ffffff !important;
        }

        .sidebar nav {
            background: #ffffff !important;
        }

        .nav-menu {
            background: #ffffff !important;
        }

        /* Tablet responsive */
        @media (min-width: 769px) and (max-width: 1024px) {
            .main {
                padding: 12px !important;
            }

            .od-requests-container {
                padding: 18px;
                margin: 0 8px;
            }

            .btn {
                padding: 14px 20px;
                font-size: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                padding: 0 8px;
            }
        }

        /* Large screens optimization */
        @media (min-width: 1200px) {
            .od-requests-container {
                padding: 25px;
                margin: 0 10px;
            }

            .od-request-item {
                padding: 25px;
            }
        }

        /* Extra small screens */
        @media (max-width: 480px) {
            .main {
                padding: 8px !important;
            }

            .od-requests-container {
                padding: 8px;
                margin: 0 2px;
            }

            .od-request-item {
                padding: 12px !important;
                margin-bottom: 15px;
                width: 100% !important;
                max-width: 100% !important;
            }

            .request-header-card {
                padding: 10px;
            }

            .student-name-large {
                font-size: 16px;
            }

            .student-avatar {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .section-header {
                padding: 8px 10px;
                font-size: 13px;
            }

            .section-header h4 {
                font-size: 13px;
            }

            .event-details, .description-box, .approval-form {
                padding: 10px !important;
                width: 100% !important;
            }

            .event-detail-item {
                padding: 6px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .btn {
                padding: 14px 16px;
                font-size: 14px;
                width: 100% !important;
            }

            .form-textarea {
                font-size: 14px;
                padding: 10px;
                width: 100% !important;
            }

            .od-status {
                padding: 6px 12px;
                font-size: 11px;
            }

            .meta-item {
                font-size: 12px;
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
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <?php
                        if ($is_admin) {
                            echo 'Admin Portal';
                        } elseif ($is_counselor) {
                            echo 'Counselor Portal';
                        } else {
                            echo 'Teacher Portal';
                        }
                    ?>
                </div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info"  style="color: white;">
                <div class="student-name" style="color:white;"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID:                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo htmlspecialchars($teacher_data['faculty_id']); ?> (Counselor)</div>
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
                        <a href="index.php#assigned-students" class="nav-link">
                            <span class="material-symbols-outlined">supervisor_account</span>
                            My Assigned Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_approvals.php" class="nav-link active">
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
                    <?php if ($teacher_data['status'] === 'admin'): ?>
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
                        <a href="../admin/manage_counselors.php" class="nav-link">
                            <span class="material-symbols-outlined">school</span>
                            Manage Counselors
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
            <?php if ($message): ?>
                <div class="message<?php echo $message_type; ?>">
                    <span class="material-symbols-outlined">
                        <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                    </span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_requests'] ?? 0; ?></div>
                    <div class="stat-label">Total</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending_requests'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-number"><?php echo $stats['approved_requests'] ?? 0; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo $stats['rejected_requests'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <?php if (($stats['total_requests'] ?? 0) > 0): ?>
            <!-- Progress Overview -->
            <div style="background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <h3 style="margin: 0 0 15px 0; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined">trending_up</span>
                    Review Progress
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div style="text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #ffc107;">
                            <?php echo round((($stats['pending_requests'] ?? 0) / ($stats['total_requests'] ?? 1)) * 100); ?>%
                        </div>
                        <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Needs Review</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #28a745;">
                            <?php echo round((($stats['approved_requests'] ?? 0) / ($stats['total_requests'] ?? 1)) * 100); ?>%
                        </div>
                        <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Approved</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; color: #dc3545;">
                            <?php echo round((($stats['rejected_requests'] ?? 0) / ($stats['total_requests'] ?? 1)) * 100); ?>%
                        </div>
                        <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Rejected</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- OD Requests -->
            <div class="od-requests-container">
                <h2 style="margin-bottom: 30px; color: var(--primary-color); display: flex; align-items: center; gap: 10px;">
                    <span class="material-symbols-outlined">approval</span>
                    Student OD Requests
                </h2>

                <?php if (count($od_requests_array) > 0): ?>
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">person_search</span>
                                    Search by Name
                                </label>
                                <input type="text" id="filterName" class="filter-input" placeholder="Enter student name...">
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">filter_alt</span>
                                    Filter by Status
                                </label>
                                <select id="filterStatus" class="filter-select">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="filter-group" style="display: flex; flex-direction: row; gap: 10px;">
                                <button onclick="applyFilters()" class="filter-btn filter-btn-primary" style="flex: 1;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">search</span>
                                    Apply
                                </button>
                                <button onclick="clearFilters()" class="filter-btn filter-btn-secondary" style="flex: 1;">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">close</span>
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table View -->
                    <div style="overflow-x: auto; margin-bottom: 30px;">
                        <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #0c3878 0%, #2d5aa0 100%); color: white;">
                                    <th style="padding: 15px; text-align: left; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Student</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Register No</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Event Name</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Event Date</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Location</th>
                                    <th style="padding: 15px; text-align: center; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Poster</th>
                                    <th style="padding: 15px; text-align: center; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Status</th>
                                    <th style="padding: 15px; text-align: center; font-weight: 600; border-bottom: 3px solid #f0f0f0;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($od_requests_array as $request): ?>
                                <tr style="border-bottom: 1px solid #f0f0f0; transition: background 0.3s ease;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">
                                    <td data-label="Student" style="padding: 15px; font-weight: 500; color: #2c3e50;">
                                        <div>
                                            <?php echo htmlspecialchars($request['student_name']); ?>
                                            <div style="font-size: 12px; color: #6c757d; margin-top: 3px;">
                                                <?php echo htmlspecialchars($request['department']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Register No" style="padding: 15px; color: #495057; font-family: 'Courier New', monospace;">
                                        <span><?php echo htmlspecialchars($request['student_regno']); ?></span>
                                    </td>
                                    <td data-label="Event Name" style="padding: 15px; color: #495057;">
                                        <div>
                                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($request['event_name']); ?>">
                                                <?php echo htmlspecialchars($request['event_name']); ?>
                                            </div>
                                            <div style="font-size: 11px; color: #6c757d; margin-top: 3px;">
                                                <?php echo date('h:i A', strtotime($request['event_time'])); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Event Date" style="padding: 15px; color: #495057;">
                                        <span><?php echo date('M d, Y', strtotime($request['event_date'])); ?></span>
                                    </td>
                                    <td data-label="Location" style="padding: 15px; color: #495057;">
                                        <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="<?php
                                                                                                                             if (! empty($request['event_state']) && ! empty($request['event_district'])) {
                                                                                                                                 echo htmlspecialchars($request['event_district'] . ', ' . $request['event_state']);
                                                                                                                             } elseif (! empty($request['event_location'])) {
                                                                                                                                 echo htmlspecialchars($request['event_location']);
                                                                                                                             } else {
                                                                                                                                 echo 'Not specified';
                                                                                                                         }
                                                                                                                         ?>">
                                            <?php
                                                if (! empty($request['event_state']) && ! empty($request['event_district'])) {
                                                    echo htmlspecialchars($request['event_district'] . ', ' . $request['event_state']);
                                                } elseif (! empty($request['event_location'])) {
                                                    echo htmlspecialchars($request['event_location']);
                                                } else {
                                                    echo '<em style="color: #999;">Not specified</em>';
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td data-label="Poster" style="padding: 15px; text-align: center;">
                                        <?php if (! empty($request['event_poster'])): ?>
                                            <?php
                                                $poster_path    = '../student/uploads/posters/' . $request['event_poster'];
                                                $file_extension = strtolower(pathinfo($request['event_poster'], PATHINFO_EXTENSION));
                                            ?>
                                            <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && file_exists($poster_path)): ?>
                                                <button onclick="openPosterModal('<?php echo htmlspecialchars($poster_path); ?>')"
                                                        style="background: #0c3878; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s ease;"
                                                        onmouseover="this.style.background='#2d5aa0'; this.style.transform='scale(1.05)'"
                                                        onmouseout="this.style.background='#0c3878'; this.style.transform='scale(1)'">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">image</span>
                                                    View
                                                </button>
                                            <?php else: ?>
                                                <a href="<?php echo htmlspecialchars($poster_path); ?>"
                                                   target="_blank"
                                                   style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; transition: all 0.3s ease;">
                                                    <span class="material-symbols-outlined" style="font-size: 18px;">download</span>
                                                    PDF
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: 12px;">No poster</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status" style="padding: 15px; text-align: center;">
                                        <span class="od-status                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     <?php echo $request['status']; ?>" style="display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; margin: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action" style="padding: 15px; text-align: center;">
                                        <button onclick="viewDetails(<?php echo $request['id']; ?>)"
                                                style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; font-weight: 600; transition: all 0.3s ease;"
                                                onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(23,162,184,0.3)'"
                                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                                            <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                            Details
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Detailed View (Hidden by default) -->
                    <?php foreach ($od_requests_array as $request): ?>
                    <div class="od-request-item<?php echo $request['status']; ?>" id="details-<?php echo $request['id']; ?>" style="display: none;">
                        <!-- Enhanced Request Header -->
                        <div class="request-header-card">
                            <div class="student-info-grid">
                                <div class="student-avatar">
                                    <?php echo strtoupper(substr($request['student_name'], 0, 1)); ?>
                                </div>
                                <div class="student-details-enhanced">
                                    <div class="student-name-large"><?php echo htmlspecialchars($request['student_name']); ?></div>
                                    <div class="student-meta">
                                        <div class="meta-item">
                                            <span class="material-symbols-outlined">badge</span>
                                            <span><?php echo htmlspecialchars($request['student_regno']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="material-symbols-outlined">school</span>
                                            <span><?php echo htmlspecialchars($request['department']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="material-symbols-outlined">calendar_today</span>
                                            <span>Year                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php echo htmlspecialchars($request['year_of_join']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="material-symbols-outlined">schedule</span>
                                            <span><?php echo date('M d, Y h:i A', strtotime($request['request_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="od-status                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   <?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Group Members Section (if group OD) -->
                        <?php if (! empty($request['group_members'])): ?>
                        <?php
                            // Fetch group members details
                            $group_regnos          = array_filter(array_map('trim', explode(',', $request['group_members'])));
                            $group_members_details = [];

                            if (! empty($group_regnos)) {
                                // Escape each regno for SQL safety
                                $escaped_regnos = array_map(function ($regno) use ($conn) {
                                    return "'" . $conn->real_escape_string($regno) . "'";
                                }, $group_regnos);

                                $regnos_list  = implode(',', $escaped_regnos);
                                $group_sql    = "SELECT regno, name FROM student_register WHERE regno IN ($regnos_list)";
                                $group_result = $conn->query($group_sql);

                                if ($group_result) {
                                    while ($member = $group_result->fetch_assoc()) {
                                        $group_members_details[] = $member;
                                    }
                                }
                            }
                        ?>
                        <div class="section-card">
                            <div class="section-header">
                                <span class="material-symbols-outlined">group</span>
                                <h4>Group Members (<?php echo count($group_members_details); ?>)</h4>
                            </div>
                            <div class="group-members-section">
                                <div class="group-members-list">
                                    <?php foreach ($group_members_details as $index => $member): ?>
                                    <div class="group-member-item">
                                        <div class="group-member-icon">
                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                        </div>
                                        <div class="group-member-details">
                                            <div class="group-member-regno"><?php echo htmlspecialchars($member['regno']); ?></div>
                                            <div class="group-member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 2: Event Details -->
                        <div class="section-card event-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">event</span>
                                <h4>Event Details</h4>
                            </div>
                            <div class="event-details">
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Name:</span>
                                    <span class="event-detail-value"><?php echo htmlspecialchars($request['event_name']); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Location:</span>
                                    <span class="event-detail-value">
                                        <?php
                                            // Display state and district if available, otherwise show event_location
                                            if (! empty($request['event_state']) && ! empty($request['event_district'])) {
                                                echo htmlspecialchars($request['event_district']) . ', ' . htmlspecialchars($request['event_state']);
                                            } elseif (! empty($request['event_location'])) {
                                                echo htmlspecialchars($request['event_location']);
                                            } else {
                                                echo '<em style="color: #999;">Not specified</em>';
                                            }
                                        ?>
                                    </span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Date:</span>
                                    <span class="event-detail-value"><?php echo date('M d, Y', strtotime($request['event_date'])); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Event Time:</span>
                                    <span class="event-detail-value"><?php echo date('h:i A', strtotime($request['event_time'])); ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Duration:</span>
                                    <span class="event-detail-value"><?php echo isset($request['event_days']) ? htmlspecialchars($request['event_days']) . ' day(s)' : '1 day'; ?></span>
                                </div>
                                <div class="event-detail-item">
                                    <span class="event-detail-label">Requested:</span>
                                    <span class="event-detail-value"><?php echo date('M d, Y h:i A', strtotime($request['request_date'])); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Event Description -->
                        <div class="section-card description-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">description</span>
                                <h4>Event Description</h4>
                            </div>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($request['event_description'])); ?>
                            </div>
                        </div>

                        <!-- Section 4: Event Poster (if available) -->
                        <?php if (! empty($request['event_poster'])): ?>
                        <div class="section-card poster-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">image</span>
                                <h4>Event Poster</h4>
                            </div>
                            <div class="description-box" style="border-left-color: #17a2b8;">
                                <?php
                                    $poster_path    = '../student/uploads/posters/' . $request['event_poster'];
                                    $file_extension = strtolower(pathinfo($request['event_poster'], PATHINFO_EXTENSION));
                                ?>

                                <div style="display: flex; flex-direction: column; gap: 15px;">
                                    <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png']) && file_exists($poster_path)): ?>
                                    <div style="text-align: center;">
                                        <img src="<?php echo htmlspecialchars($poster_path); ?>"
                                             alt="Event Poster"
                                             style="width: 100%; max-width: 300px; height: auto; border-radius: 12px; border: 3px solid #e9ecef; cursor: pointer; transition: transform 0.3s ease;"
                                             onclick="openPosterModal('<?php echo htmlspecialchars($poster_path); ?>')"
                                             onmouseover="this.style.transform='scale(1.02)'"
                                             onmouseout="this.style.transform='scale(1)'">
                                    </div>
                                    <?php endif; ?>

                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <a href="../student/view_poster.php?poster=<?php echo urlencode($request['event_poster']); ?>"
                                           target="_blank"
                                           style="color: white; background: #0c3878; text-decoration: none; font-weight: 500; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s ease;">
                                            <span class="material-symbols-outlined">visibility</span>
                                            View Full Poster
                                        </a>
                                        <a href="<?php echo htmlspecialchars($poster_path); ?>"
                                           download
                                           style="color: white; background: #28a745; text-decoration: none; font-weight: 500; padding: 12px 20px; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.3s ease;">
                                            <span class="material-symbols-outlined">download</span>
                                            Download Poster
                                        </a>
                                        <div style="text-align: center; font-size: 12px; color: #6c757d; padding: 8px; background: #f8f9fa; border-radius: 6px;">
                                            <strong>File:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo htmlspecialchars(basename($request['event_poster'])); ?><br>
                                            <strong>Type:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo strtoupper($file_extension); ?> •
                                            <strong>Size:</strong>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo file_exists($poster_path) ? round(filesize($poster_path) / 1024, 1) . ' KB' : 'Unknown'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 5: Reason for OD -->
                        <div class="section-card reason-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">help</span>
                                <h4>Reason for OD</h4>
                            </div>
                            <div class="description-box">
                                <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                            </div>
                        </div>

                        <!-- Section 6: Previous Remarks (if any) -->
                        <?php if ($request['counselor_remarks']): ?>
                        <div class="section-card remarks-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">comment</span>
                                <h4>Your Previous Remarks</h4>
                            </div>
                            <div class="description-box" style="border-left-color: #28a745;">
                                <?php echo nl2br(htmlspecialchars($request['counselor_remarks'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Section 7: Review & Approval (if pending) -->
                        <?php if ($request['status'] === 'pending'): ?>
                        <div class="section-card approval-section">
                            <div class="section-header">
                                <span class="material-symbols-outlined">approval</span>
                                <h4>Review & Approval Decision</h4>
                            </div>
                            <div class="approval-form">
                                <form method="POST" action="">
                                    <input type="hidden" name="od_id" value="<?php echo $request['id']; ?>">

                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Remarks (Optional)</label>
                                            <textarea name="counselor_remarks" class="form-textarea"
                                                      placeholder="Add your remarks or feedback..." rows="3"></textarea>
                                        </div>

                                        <div class="action-buttons">
                                            <button type="submit" name="update_od_status" value="approved"
                                                    onclick="this.form.new_status.value='approved'" class="btn btn-approve">
                                                <span class="material-symbols-outlined">check_circle</span>
                                                Approve
                                            </button>
                                            <button type="submit" name="update_od_status" value="rejected"
                                                    onclick="this.form.new_status.value='rejected'" class="btn btn-reject">
                                                <span class="material-symbols-outlined">cancel</span>
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="new_status" value="">
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">description</span>
                        <h3>No OD Requests</h3>
                        <p>No students have submitted OD requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- OD Details Modal -->
    <div id="odDetailsModal" class="od-details-modal">
        <div class="od-details-modal-content">
            <div class="od-details-modal-header">
                <h3>
                    <span class="material-symbols-outlined">description</span>
                    OD Request Details
                </h3>
                <span class="od-details-modal-close" onclick="closeOdDetailsModal()">&times;</span>
            </div>
            <div class="od-details-modal-body" id="odDetailsModalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Poster Modal -->
    <div id="posterModal" class="poster-modal">
        <div class="poster-modal-content">
            <div class="poster-controls">
                <button class="control-btn" onclick="rotatePoster(-90)" title="Rotate Left">
                    <span class="material-symbols-outlined">rotate_left</span>
                </button>
                <button class="control-btn" onclick="rotatePoster(90)" title="Rotate Right">
                    <span class="material-symbols-outlined">rotate_right</span>
                </button>
                <button class="control-btn" onclick="resetPosterRotation()" title="Reset Rotation">
                    <span class="material-symbols-outlined">refresh</span>
                </button>
            </div>
            <span class="poster-modal-close" onclick="closePosterModal()">&times;</span>
            <img id="posterModalImage" src="" alt="Event Poster">
            <div class="poster-modal-footer">
                <span>📷 <strong>Controls:</strong> Click buttons above or use keyboard: <strong>A/←</strong> rotate left, <strong>D/→</strong> rotate right, <strong>R</strong> reset, <strong>ESC</strong> close</span>
            </div>
        </div>
    </div>

    <script>
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

        // Close sidebar when clicking outside
        function closeSidebarOnOutsideClick(event) {
            const sidebar = document.getElementById('sidebar');
            const menuIcon = document.querySelector('.header .menu-icon');

            if (sidebar.classList.contains('active') &&
                !sidebar.contains(event.target) &&
                !menuIcon.contains(event.target)) {
                toggleSidebar();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            // Add event listener for outside clicks
            document.addEventListener('click', closeSidebarOnOutsideClick);

            // Auto-hide success messages
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 300);
                }, 5000);
            }

            // Add filter event listeners
            const filterName = document.getElementById('filterName');
            const filterStatus = document.getElementById('filterStatus');

            if (filterName) {
                filterName.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        applyFilters();
                    }
                });
            }
        });

        // Filter Functions
        function applyFilters() {
            const nameFilter = document.getElementById('filterName').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
            const tableRows = document.querySelectorAll('table tbody tr');
            let visibleCount = 0;

            tableRows.forEach(row => {
                const studentName = row.querySelector('td[data-label="Student"]').textContent.toLowerCase();
                const statusElement = row.querySelector('.od-status');
                const status = statusElement.textContent.toLowerCase().trim();

                const nameMatch = nameFilter === '' || studentName.includes(nameFilter);
                const statusMatch = statusFilter === '' || status === statusFilter;

                if (nameMatch && statusMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show message if no results
            updateFilterMessage(visibleCount);
        }

        function clearFilters() {
            document.getElementById('filterName').value = '';
            document.getElementById('filterStatus').value = '';
            applyFilters();
        }

        function updateFilterMessage(count) {
            let messageDiv = document.getElementById('filterMessage');

            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.id = 'filterMessage';
                messageDiv.style.cssText = 'padding: 15px; text-align: center; background: #fff3cd; color: #856404; border-radius: 8px; margin: 15px 0; border: 1px solid #ffeaa7;';
                const table = document.querySelector('table');
                table.parentNode.insertBefore(messageDiv, table);
            }

            if (count === 0) {
                messageDiv.innerHTML = '<span class="material-symbols-outlined" style="vertical-align: middle;">info</span> No OD requests match your filter criteria.';
                messageDiv.style.display = 'block';
            } else {
                messageDiv.style.display = 'none';
            }
        }

        // View Details Function
        function viewDetails(odId) {
            const detailsDiv = document.getElementById('details-' + odId);
            if (!detailsDiv) return;

            const modal = document.getElementById('odDetailsModal');
            const modalBody = document.getElementById('odDetailsModalBody');

            // Clone the details content
            const clonedContent = detailsDiv.cloneNode(true);
            clonedContent.id = 'modal-details-' + odId;
            clonedContent.classList.add('visible');

            // Force vertical layout with inline styles
            clonedContent.style.display = 'flex';
            clonedContent.style.flexDirection = 'column';
            clonedContent.style.gap = '20px';
            clonedContent.style.margin = '0';
            clonedContent.style.padding = '0';
            clonedContent.style.background = 'transparent';
            clonedContent.style.border = 'none';
            clonedContent.style.boxShadow = 'none';

            // Ensure all section cards display as block
            const sectionCards = clonedContent.querySelectorAll('.section-card');
            sectionCards.forEach(card => {
                card.style.display = 'block';
                card.style.width = '100%';
                card.style.marginBottom = '15px';
            });

            // Clear previous content and add new
            modalBody.innerHTML = '';
            modalBody.appendChild(clonedContent);

            // Show modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeOdDetailsModal() {
            const modal = document.getElementById('odDetailsModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('odDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOdDetailsModal();
            }
        });

        // Poster Modal Functions
        let currentRotation = 0;

        function openPosterModal(posterSrc) {
            const modal = document.getElementById('posterModal');
            const modalImage = document.getElementById('posterModalImage');
            modalImage.src = posterSrc;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent background scrolling

            // Reset rotation when opening modal
            currentRotation = 0;
            modalImage.style.transform = 'rotate(0deg)';
        }

        function closePosterModal() {
            const modal = document.getElementById('posterModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling

            // Reset rotation when closing modal
            currentRotation = 0;
        }

        function rotatePoster(degrees) {
            currentRotation += degrees;
            const modalImage = document.getElementById('posterModalImage');
            modalImage.style.transform = `rotate(${currentRotation}deg)`;
        }

        function resetPosterRotation() {
            currentRotation = 0;
            const modalImage = document.getElementById('posterModalImage');
            modalImage.style.transform = 'rotate(0deg)';
        }

        // Close modal when clicking outside the image
        document.getElementById('posterModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePosterModal();
            }
        });

        // Close modal with Escape key and add rotation shortcuts
        document.addEventListener('keydown', function(e) {
            const posterModal = document.getElementById('posterModal');
            const odDetailsModal = document.getElementById('odDetailsModal');

            if (e.key === 'Escape') {
                if (posterModal.style.display === 'block') {
                    closePosterModal();
                }
                if (odDetailsModal.style.display === 'block') {
                    closeOdDetailsModal();
                }
            }

            if (posterModal.style.display === 'block') {
                switch(e.key) {
                    case 'ArrowLeft':
                    case 'a':
                    case 'A':
                        e.preventDefault();
                        rotatePoster(-90);
                        break;
                    case 'ArrowRight':
                    case 'd':
                    case 'D':
                        e.preventDefault();
                        rotatePoster(90);
                        break;
                    case 'r':
                    case 'R':
                        e.preventDefault();
                        resetPosterRotation();
                        break;
                }
            }
        });
    </script>
    <?php
        // Close database connection
        $conn->close();
    ?>
</body>
</html>