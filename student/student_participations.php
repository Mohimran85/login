<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
    }

    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

    // Get logged-in user's data
    $username     = $_SESSION['username'];
    $student_data = null;
    $regno        = '';

    $user_sql  = "SELECT name, regno FROM student_register WHERE username=?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("s", $username);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {
    $student_data = $user_result->fetch_assoc();
    $regno        = $student_data['regno'];
    } else {
    header("Location: index.php");
    exit();
    }

    // Handle search and filters
    $search            = isset($_GET['search']) ? trim($_GET['search']) : '';
    $event_type_filter = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $prize_filter      = isset($_GET['prize']) ? $_GET['prize'] : '';
    $sort_by           = isset($_GET['sort']) ? $_GET['sort'] : 'start_date';
    $sort_order        = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    // Build query with filters
    $where_conditions = ["regno = ?"];
    $params           = [$regno];
    $param_types      = "s";

    if (! empty($search)) {
    $where_conditions[]  = "(event_name LIKE ? OR organisation LIKE ?)";
    $params[]            = "%$search%";
    $params[]            = "%$search%";
    $param_types        .= "ss";
    }

    if (! empty($event_type_filter)) {
    $where_conditions[]  = "event_type = ?";
    $params[]            = $event_type_filter;
    $param_types        .= "s";
    }

    if (! empty($prize_filter)) {
    if ($prize_filter === 'won') {
        $where_conditions[] = "prize IS NOT NULL AND prize != '' AND prize != 'Participation'";
    } elseif ($prize_filter === 'participation') {
        $where_conditions[] = "prize = 'Participation'";
    }
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Validate sort columns
    $allowed_sorts = ['start_date', 'event_name', 'event_type', 'prize'];
    if (! in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'start_date';
    }
    $sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

    // Get participations
    $sql  = "SELECT *, COALESCE(verification_status, 'Pending') as verification_status FROM student_event_register WHERE $where_clause ORDER BY $sort_by $sort_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $participations = $stmt->get_result();

    // Get statistics
    $stats_sql = "SELECT
    COUNT(*) as total_events,
    COUNT(CASE WHEN prize IS NOT NULL AND prize != '' AND prize != 'Participation' THEN 1 END) as events_won,
    COUNT(CASE WHEN prize = 'first' THEN 1 END) as first_prizes,
    COUNT(CASE WHEN prize = 'second' THEN 1 END) as second_prizes,
    COUNT(CASE WHEN prize = 'third' THEN 1 END) as third_prizes
    FROM student_event_register WHERE regno = ?";
    $stats_stmt = $conn->prepare($stats_sql);
    $stats_stmt->bind_param("s", $regno);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();

    // Get unique event types for filter
    $types_sql  = "SELECT DISTINCT event_type FROM student_event_register WHERE regno = ? ORDER BY event_type";
    $types_stmt = $conn->prepare($types_sql);
    $types_stmt->bind_param("s", $regno);
    $types_stmt->execute();
    $event_types = $types_stmt->get_result();

    // Get internship submissions
    $internship_sql  = "SELECT * FROM internship_submissions WHERE regno = ? ORDER BY submission_date DESC";
    $internship_stmt = $conn->prepare($internship_sql);
    $internship_stmt->bind_param("s", $regno);
    $internship_stmt->execute();
    $internships = $internship_stmt->get_result();

    // Get internship count
    $internship_count = $internships->num_rows;

    $user_stmt->close();
    $stmt->close();
    $stats_stmt->close();
    $types_stmt->close();
    $internship_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>My Participations - Event Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <!-- Web App Manifest for Push Notifications -->
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        .participations-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

fd3s        .participations-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .participations-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .filter-input, .filter-select {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #0066cc;
        }

        .filter-btn {
            background: #0066cc;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .filter-btn:hover {
            background: #004499;
        }

        .participations-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        /* Hide mobile cards on desktop by default */
        .mobile-card-table {
            display: none;
        }

        .participation-item {
            padding: 25px;
            border-bottom: 1px solid #f8f9fa;
            transition: background 0.3s ease;
        }

        .participation-item:hover {
            background: #f8f9fa;
        }

        .participation-item:last-child {
            border-bottom: none;
        }

        .participation-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .event-name {
            font-size: 18px;
            font-weight: 600;
            color: #0066cc;
            margin-bottom: 8px;
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #6c757d;
        }

        .meta-item .material-symbols-outlined {
            font-size: 18px;
        }

        .event-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-group {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .detail-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 500;
        }

        .detail-value {
            font-size: 14px;
            color: #495057;
            font-weight: 500;
        }

        .prize-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .prize-first {
            background: linear-gradient(135deg, rgba(255, 218, 9, 1) 0%, #ddff04ff 100%);
            color: white;
        }

        .prize-second {
            background: linear-gradient(135deg, #454545ff 0%, #5b5b5bff 100%);
            color: #0066cc;
            border: 2px solid #0066cc;
        }

        .prize-third {
            background: linear-gradient(135deg, #a77700ff 0%, #f7b31fff 100%);
            color: #004499;
        }

        .prize-participation {
            background: linear-gradient(135deg, #e6f3ff 0%, #cce7ff 100%);
            color: #0066cc;
            border: 1px solid #0066cc;
        }

        /* Verification Status Badges */
        .verification-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .verification-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .verification-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #28a745;
        }

        .verification-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #dc3545;
        }

        .actions-section {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-download {
            background: #0066cc;
            color: white;
        }

        .btn-download:hover {
            background: #004499;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #ffffff;
            color: #0066cc;
            border: 2px solid #0066cc;
        }

        .btn-view:hover {
            background: #f0f8ff;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        .empty-action {
            background: #0066cc;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .empty-action:hover {
            background: #004499;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }

            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas: "main";
                min-height: 100vh;
                width: 100%;
                max-width: 100vw;
            }

            .header {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1002;
                width: 100%;
            }

            .sidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                min-height: 100vh !important;
                max-height: 100vh !important;
                transform: translateX(-100%) !important;
                z-index: 10000 !important;
                background: #ffffff !important;
                box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15) !important;
                transition: transform 0.3s ease !important;
                overflow-y: auto !important;
            }

            .sidebar.active {
                transform: translateX(0) !important;
                z-index: 10001 !important;
            }

            body.sidebar-open {
                overflow: hidden;
                position: fixed;
                width: 100%;
                height: 100%;
            }

            .main {
                width: 100% !important;
                max-width: 100vw;
                padding: 10px 15px 20px 15px;
                margin: 0 !important;
                grid-area: main;
                box-sizing: border-box;
                overflow-x: hidden;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .participations-header {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .participations-title {
                font-size: 24px;
            }

            .filters-section {
                padding: 20px 15px;
                margin-bottom: 15px;
            }

            /* Mobile Table-like Card Design */
            .participation-item {
                background: white;
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                border-left: 4px solid #0066cc;
                border: 1px solid #e9ecef;
            }

            .participation-item:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            }

            .event-name {
                font-size: 17px;
                font-weight: 600;
                color: #0066cc;
                margin-bottom: 12px;
                border-bottom: 1px solid #f0f0f0;
                padding-bottom: 8px;
            }

            .event-meta {
                display: none; /* Hide meta section in mobile */
            }

            .event-details {
                display: grid;
                grid-template-columns: 1fr;
                gap: 8px;
                margin-bottom: 15px;
            }

            .detail-group {
                background: transparent;
                padding: 8px 0;
                border-bottom: 1px solid #f8f9fa;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .detail-group:last-child {
                border-bottom: none;
            }

            .detail-label {
                font-size: 14px;
                color: #0066cc;
                font-weight: 600;
                margin-bottom: 0;
                text-transform: none;
                min-width: 100px;
                margin-right: 10px;
            }

            .detail-value {
                font-size: 14px;
                color: #666;
                font-weight: 500;
                text-align: right;
                flex: 1;
            }

            .actions-section {
                flex-direction: row;
                gap: 8px;
                margin-top: 12px;
                border-top: 1px solid #f0f0f0;
                padding-top: 12px;
            }

            .action-btn {
                text-align: center;
                justify-content: center;
                display: flex;
                align-items: center;
                font-size: 12px;
                padding: 8px 12px;
                flex: 1;
            }

            /* Prize badge positioning */
            .prize-badge {
                font-size: 11px;
                padding: 4px 8px;
                border-radius: 12px;
                font-weight: 600;
                margin-top: 8px;
                display: inline-block;
            }
        }

        @media (max-width: 480px) {
            .main {
                padding: 0px 10px 15px 10px;
            }

            .participations-header {
                padding: 15px 10px;
                border-radius: 12px;
                margin-bottom: 20px;
            }

            .participations-title {
                font-size: 20px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                margin-bottom: 15px;
            }

            .filters-section {
                padding: 15px 10px;
                border-radius: 12px;
                margin-bottom: 15px;
            }

            /* Modern Table Design for Mobile */
            .mobile-card-table {
                display: block;
            }

            .desktop-table {
                display: none;
            }

            .participation-card {
                background: #fff;
                border-radius: 16px;
                padding: 20px;
                margin-bottom: 16px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                border: none;
                position: relative;
                overflow: hidden;
            }

            .participation-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #0066cc 0%, #004499 100%);
            }

            .participation-card h4 {
                margin: 0 0 16px 0;
                color: #0066cc;
                font-size: 18px;
                font-weight: 700;
                line-height: 1.2;
                border-bottom: none;
                padding-bottom: 0;
            }

            /* Modern Table Layout */
            .participation-info {
                background: #f8fafc;
                border-radius: 12px;
                padding: 16px;
                margin-bottom: 16px;
            }

            .info-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #e2e8f0;
                transition: background-color 0.2s ease;
            }

            .info-row:hover {
                background-color: rgba(0, 102, 204, 0.05);
                margin: 0 -8px;
                padding: 12px 8px;
                border-radius: 8px;
            }

            .info-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }

            .info-label {
                font-size: 14px;
                font-weight: 600;
                color: #4a5568;
                min-width: 100px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .info-label::before {
                content: '';
                width: 6px;
                height: 6px;
                background: #0066cc;
                border-radius: 50%;
            }

            .info-value {
                font-size: 14px;
                color: #2d3748;
                font-weight: 500;
                text-align: right;
                flex: 1;
                margin-left: 12px;
            }

            /* Prize Badge Redesign */
            .prize-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .prize-first {
                background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
                color: white;
                box-shadow: 0 2px 8px rgba(0, 102, 204, 0.3);
            }

            .prize-second {
                background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
                color: #0066cc;
                border: 2px solid #0066cc;
                box-shadow: 0 2px 8px rgba(0, 102, 204, 0.2);
            }

            .prize-third {
                background: linear-gradient(135deg, #cce7ff 0%, #99d6ff 100%);
                color: #004499;
                box-shadow: 0 2px 8px rgba(0, 102, 204, 0.2);
            }

            .prize-participation {
                background: linear-gradient(135deg, #e6f3ff 0%, #cce7ff 100%);
                color: #0066cc;
                border: 1px solid #0066cc;
                box-shadow: 0 2px 8px rgba(0, 102, 204, 0.2);
            }

            /* Action Buttons Redesign */
            .actions-section {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 12px;
                margin-top: 0;
                border-top: none;
                padding-top: 0;
            }

            .action-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 16px;
                border-radius: 12px;
                text-decoration: none;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.3s ease;
                text-align: center;
            }

            .btn-download {
                background: linear-gradient(135deg, #00458bff 0%, #004499 100%);
                color: white;

            }

            .btn-download:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
            }

            .btn-view {
                background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
                color: #0066cc;
                border: 2px solid #09529bff;

            }

            .btn-view:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 102, 204, 0.3);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 60px 20px;
                background: #f8fafc;
                border-radius: 16px;
                margin: 20px 0;
            }

            .empty-state .material-symbols-outlined {
                font-size: 64px;
                color: #cbd5e1;
                margin-bottom: 16px;
            }

            .empty-state h3 {
                color: #4a5568;
                font-size: 18px;
                margin-bottom: 8px;
            }

            .empty-state p {
                color: #718096;
                margin-bottom: 20px;
            }

            /* Hide the original participation items on mobile */
            .participations-list .participation-item {
                display: none;
            }
        }

        /* Error Popup Modal */
        .error-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .error-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
            position: relative;
        }

        .error-icon {
            font-size: 64px;
            color: #0066cc;
            margin-bottom: 20px;
        }

        .error-title {
            font-size: 24px;
            font-weight: 700;
            color: #0066cc;
            margin-bottom: 15px;
        }

        .error-message {
            font-size: 16px;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .error-close-btn {
            background: #0066cc;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .error-close-btn:hover {
            background: #004499;
        }

        .close-x {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-x:hover {
            color: #0066cc;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Ensure no horizontal overflow */
        * {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Notification Bell Styles */
        .notification-bell-container {
            position: absolute;
            top: 12px;
            right: 20px;
            display: flex;
            align-items: center;
            z-index: 1001;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid #1a408c;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            transition: all 0.3s ease;
            margin: 0;
        }

        .notification-bell:hover {
            background: #f0f4f8;
            transform: scale(1.05);
        }

        .notification-bell .material-symbols-outlined {
            font-size: 24px;
            color: #1a408c;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            min-width: 24px;
        }

        .notification-badge.hidden {
            display: none;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border: 1px solid #eee;
            width: 350px;
            max-height: 500px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1a408c;
        }

        .notification-header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .notification-header .mark-all,
        .notification-header .clear-all {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 12px;
            text-decoration: underline;
            padding: 0;
            transition: all 0.3s ease;
        }

        .notification-header .mark-all {
            color: #1a408c;
        }

        .notification-header .mark-all:hover {
            color: #15306b;
        }

        .notification-header .clear-all {
            color: #dc3545;
        }

        .notification-header .clear-all:hover {
            color: #a71d2a;
        }

        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            gap: 12px;
        }

        .notification-item:hover {
            background: #f9f9f9;
        }

        .notification-item.unread {
            background: #f0f4f8;
        }

        .notification-item-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: #1a408c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .notification-item-content {
            flex: 1;
            min-width: 0;
        }

        .notification-item-content h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .notification-item-content p {
            margin: 0 0 5px 0;
            font-size: 13px;
            color: #666;
            line-height: 1.4;
        }

        .notification-item-time {
            font-size: 12px;
            color: #999;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #999;
        }

        .notification-empty-icon {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }

        .notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: none;
            z-index: 999;
        }

        .notification-overlay.show {
            display: block;
        }

        @media (max-width: 768px) {
            .notification-bell-container {
                position: absolute;
                top: 8px;
                right: 10px;
            }

            .notification-dropdown {
                position: fixed;
                top: auto;
                right: 10px;
                left: 10px;
                bottom: 80px;
                width: auto;
                max-height: 300px;
            }

            .notification-bell {
                width: 40px;
                height: 40px;
                margin: 0;
            }

            .notification-bell .material-symbols-outlined {
                font-size: 20px;
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
                <img src="sona_logo.jpg"
                alt="Sona College Logo"
                height="60px"
                width="200">
            </div>
            <div class="notification-bell-container">
                <div class="notification-bell" id="notificationBell">
                    <span class="material-symbols-outlined">notifications</span>
                    <span class="notification-badge hidden" id="notificationBadge">0</span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <div class="notification-header-actions">
                            <button class="mark-all" onclick="markAllNotificationsAsRead()">Mark all as read</button>
                            <button class="clear-all" onclick="clearAllNotifications()">Clear all</button>
                        </div>
                    </div>
                    <ul class="notification-list" id="notificationList">
                        <li class="notification-empty">
                            <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                            <p>No notifications</p>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar" >
            <div class="sidebar-header">
                <div class="sidebar-title">Student Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student_data['name']); ?></div>
                <div class="student-regno"><?php echo htmlspecialchars($student_data['regno']); ?></div>
            </div>

            <nav>
                <ul class="nav-menu">
                    <li class="nav-item" >
                        <a href="index.php" class="nav-link">
                            <span class="material-symbols-outlined">dashboard</span>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="student_register.php" class="nav-link">
                            <span class="material-symbols-outlined">add_circle</span>
                            Register Event
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="student_participations.php" class="nav-link active">
                            <span class="material-symbols-outlined">event_note</span>
                            My Participations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="internship_submission.php" class="nav-link">
                            <span class="material-symbols-outlined">work</span>
                            Internship Submission
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_request.php" class="nav-link">
                            <span class="material-symbols-outlined">person_raised_hand</span>
                            OD Request
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="hackathons.php" class="nav-link">
                            <span class="material-symbols-outlined">emoji_events</span>
                            Hackathons
                        </a>
                    </li>
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
            <!-- Header Section -->
            <div class="participations-header">
                <div class="participations-title">My Event Participations</div>
                <div class="participations-subtitle">Track all your event participations and achievements</div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_events']; ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['events_won']; ?></div>
                        <div class="stat-label">Events Won</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['first_prizes']; ?></div>
                        <div class="stat-label">First Prizes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['second_prizes']; ?></div>
                        <div class="stat-label">Second Prizes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['third_prizes']; ?></div>
                        <div class="stat-label">Third Prizes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $internship_count; ?></div>
                        <div class="stat-label">Internships</div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search Events</label>
                            <input type="text" name="search" class="filter-input"
                                   placeholder="Search by event name or organization..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Event Type</label>
                            <select name="event_type" class="filter-select">
                                <option value="">All Types</option>
                                <?php while ($type = $event_types->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['event_type']); ?>"
                                            <?php echo($event_type_filter === $type['event_type']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['event_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Prize Filter</label>
                            <select name="prize" class="filter-select">
                                <option value="">All Prizes</option>
                                <option value="won"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          <?php echo($prize_filter === 'won') ? 'selected' : ''; ?>>Events Won</option>
                                <option value="participation"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <?php echo($prize_filter === 'participation') ? 'selected' : ''; ?>>Participation Only</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort" class="filter-select">
                                <option value="start_date"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($sort_by === 'start_date') ? 'selected' : ''; ?>>Date</option>
                                <option value="event_name"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($sort_by === 'event_name') ? 'selected' : ''; ?>>Event Name</option>
                                <option value="event_type"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               <?php echo($sort_by === 'event_type') ? 'selected' : ''; ?>>Event Type</option>
                                <option value="prize"                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php echo($sort_by === 'prize') ? 'selected' : ''; ?>>Prize</option>
                            </select>
                        </div>

                        <button type="submit" class="filter-btn">
                            <span class="material-symbols-outlined">search</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Event Participations Header -->
            <div class="participations-header" style="margin-bottom: 20px;">
                <div class="participations-title">My Event Participations</div>
                <div class="participations-subtitle">Track all your event participations and achievements</div>
            </div>

            <!-- Participations List -->
            <div class="participations-list">
                <!-- Desktop View -->
                <div class="desktop-table">
                    <?php if ($participations->num_rows > 0): ?>
                        <?php while ($participation = $participations->fetch_assoc()): ?>
                            <div class="participation-item">
                                <div class="event-name"><?php echo htmlspecialchars($participation['event_name']); ?></div>

                                <div class="event-meta">
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined">category</span>
                                        <?php echo htmlspecialchars($participation['event_type']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined">schedule</span>
                                        <?php
                                            if ($participation['start_date'] === $participation['end_date']) {
                                                echo date('M d, Y', strtotime($participation['start_date']));
                                            } else {
                                                echo date('M d', strtotime($participation['start_date'])) . ' - ' . date('M d, Y', strtotime($participation['end_date']));
                                            }
                                        ?>
                                        (<?php echo $participation['no_of_days']; ?> day<?php echo $participation['no_of_days'] > 1 ? 's' : ''; ?>)
                                    </div>
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined">business</span>
                                        <?php echo htmlspecialchars($participation['organisation']); ?>
                                    </div>
                                    <?php if (! empty($participation['prize']) && $participation['prize'] !== 'No Prize'): ?>
                                        <div class="prize-badge<?php
                                                                   echo match ($participation['prize']) {
                                                                       'First'  => 'prize-first',
                                                                       'Second' => 'prize-second',
                                                                       'Third'  => 'prize-third',
                                                                       default  => 'prize-participation'
                                                               };
                                                               ?>">
                                            <?php echo htmlspecialchars($participation['prize']); ?>
                                            <?php if (! empty($participation['prize_amount'])): ?>
                                                - ₹<?php echo htmlspecialchars($participation['prize_amount']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="verification-badge verification-<?php echo strtolower($participation['verification_status']); ?>">
                                        <?php
                                            $status = $participation['verification_status'];
                                            $icon   = match ($status) {
                                                'Approved' => '',
                                                'Rejected' => '',
                                                default    => ''
                                            };
                                            echo $icon . ' ' . htmlspecialchars($status);
                                        ?>
                                    </div>
                                </div>

                                <div class="event-details">
                                    <div class="detail-group">
                                        <div class="detail-label">Event Type:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($participation['event_type']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Date:</div>
                                        <div class="detail-value">
                                            <?php
                                                if ($participation['start_date'] === $participation['end_date']) {
                                                    echo date('M d, Y', strtotime($participation['start_date']));
                                                } else {
                                                    echo date('M d', strtotime($participation['start_date'])) . ' - ' . date('M d, Y', strtotime($participation['end_date']));
                                                }
                                            ?>
                                            (<?php echo $participation['no_of_days']; ?> day<?php echo $participation['no_of_days'] > 1 ? 's' : ''; ?>)
                                        </div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Organization:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($participation['organisation']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Department:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($participation['department']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Year & Semester:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($participation['current_year'] . ' - ' . $participation['semester']); ?></div>
                                    </div>
                                    <div class="detail-group">
                                        <div class="detail-label">Location:</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($participation['district'] . ', ' . $participation['state']); ?></div>
                                    </div>
                                    <?php if (! empty($participation['prize']) && $participation['prize'] !== 'No Prize'): ?>
                                    <div class="detail-group">
                                        <div class="detail-label">Prize:</div>
                                        <div class="detail-value">
                                            <span class="prize-badge                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             <?php
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     echo match ($participation['prize']) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         'First'  => 'prize-first',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         'Second' => 'prize-second',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         'Third'  => 'prize-third',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         default  => 'prize-participation'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 };
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 ?>">
                                                <?php echo htmlspecialchars($participation['prize']); ?>
                                                <?php if (! empty($participation['prize_amount'])): ?>
                                                    - ₹<?php echo htmlspecialchars($participation['prize_amount']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="actions-section">
                                    <?php if (! empty($participation['certificates'])): ?>
                                        <a href="javascript:void(0)"
                                           onclick="checkFileAndOpen('<?php echo htmlspecialchars($participation['certificates']); ?>', 'Certificate')"
                                           class="action-btn btn-download">
                                            <span class="material-symbols-outlined">download</span>
                                            Certificate
                                        </a>
                                    <?php endif; ?>
                                    <?php if (! empty($participation['event_poster'])): ?>
                                        <a href="javascript:void(0)"
                                           onclick="checkFileAndOpen('<?php echo htmlspecialchars($participation['event_poster']); ?>', 'Event Poster')"
                                           class="action-btn btn-view">
                                            <span class="material-symbols-outlined">visibility</span>
                                            Event Poster
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined">event_busy</span>
                            <h3>No Event Participations Found</h3>
                            <p>You haven't participated in any events yet or no events match your search criteria.</p>
                            <a href="student_register.php" class="empty-action">Register Your First Event</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile Card Layout -->
                <div class="mobile-card-table">
                    <?php
                        // Re-execute query for mobile view
                        $mobile_stmt = $conn->prepare($sql);
                        $mobile_stmt->bind_param($param_types, ...$params);
                        $mobile_stmt->execute();
                        $mobile_participations = $mobile_stmt->get_result();
                    ?>
                    <?php if ($mobile_participations->num_rows > 0): ?>
                        <?php while ($participation = $mobile_participations->fetch_assoc()): ?>
                            <div class="participation-card">
                                <h4><?php echo htmlspecialchars($participation['event_name']); ?></h4>

                                <div class="participation-info">
                                    <div class="info-row">
                                        <div class="info-label">Event Type</div>
                                        <div class="info-value"><?php echo htmlspecialchars($participation['event_type']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Date</div>
                                        <div class="info-value">
                                            <?php
                                                if ($participation['start_date'] === $participation['end_date']) {
                                                    echo date('M d, Y', strtotime($participation['start_date']));
                                                } else {
                                                    echo date('M d', strtotime($participation['start_date'])) . ' - ' . date('M d, Y', strtotime($participation['end_date']));
                                                }
                                            ?>
                                            (<?php echo $participation['no_of_days']; ?> day<?php echo $participation['no_of_days'] > 1 ? 's' : ''; ?>)
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Organization</div>
                                        <div class="info-value"><?php echo htmlspecialchars($participation['organisation']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Department</div>
                                        <div class="info-value"><?php echo htmlspecialchars($participation['department']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Year & Semester</div>
                                        <div class="info-value"><?php echo htmlspecialchars($participation['current_year'] . ' - ' . $participation['semester']); ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Location</div>
                                        <div class="info-value"><?php echo htmlspecialchars($participation['district'] . ', ' . $participation['state']); ?></div>
                                    </div>
                                    <?php if (! empty($participation['prize']) && $participation['prize'] !== 'No Prize'): ?>
                                    <div class="info-row">
                                        <div class="info-label">Prize</div>
                                        <div class="info-value">
                                            <span class="prize-badge                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         <?php
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 echo match ($participation['prize']) {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     'First'  => 'prize-first',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     'Second' => 'prize-second',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     'Third'  => 'prize-third',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     default  => 'prize-participation'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             };
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             ?>">
                                                <?php echo htmlspecialchars($participation['prize']); ?>
                                                <?php if (! empty($participation['prize_amount'])): ?>
                                                    - ₹<?php echo htmlspecialchars($participation['prize_amount']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (! empty($participation['certificates']) || ! empty($participation['event_poster'])): ?>
                                <div class="actions-section">
                                    <?php if (! empty($participation['certificates'])): ?>
                                        <a href="javascript:void(0)"
                                           onclick="checkFileAndOpen('<?php echo htmlspecialchars($participation['certificates']); ?>', 'Certificate')"
                                           class="action-btn btn-download">
                                            <span class="material-symbols-outlined">download</span>
                                            Certificate
                                        </a>
                                    <?php endif; ?>
                                    <?php if (! empty($participation['event_poster'])): ?>
                                        <a href="javascript:void(0)"
                                           onclick="checkFileAndOpen('<?php echo htmlspecialchars($participation['event_poster']); ?>', 'Event Poster')"
                                           class="action-btn btn-view">
                                            <span class="material-symbols-outlined">visibility</span>
                                            Event Poster
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                        <?php $mobile_stmt->close(); ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="material-symbols-outlined">event_busy</span>
                            <h3>No Event Participations Found</h3>
                            <p>You haven't participated in any events yet or no events match your search criteria.</p>
                            <a href="student_register.php" class="empty-action">Register Your First Event</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Internships Section -->
            <?php if ($internship_count > 0): ?>
            <div class="participations-header" style="margin-bottom: 20px; margin-top: 30px;">
                <div class="participations-title">My Internships</div>
                <div class="participations-subtitle">View your internship submissions and details</div>
            </div>

            <div class="participations-list" style="margin-bottom: 30px;">
                <div class="desktop-table">
                    <?php
                        // Re-fetch internships for display
                        $internship_display_stmt = $conn->prepare($internship_sql);
                        $internship_display_stmt->bind_param("s", $regno);
                        $internship_display_stmt->execute();
                        $internships_display = $internship_display_stmt->get_result();

                        while ($internship = $internships_display->fetch_assoc()):
                    ?>

                            <div class="event-meta">
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">work</span>
                                    <?php echo htmlspecialchars($internship['role_title']); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">domain</span>
                                    <?php echo htmlspecialchars($internship['domain']); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">schedule</span>
                                    <?php
                                        $start    = new DateTime($internship['start_date']);
                                        $end      = new DateTime($internship['end_date']);
                                        $duration = $start->diff($end)->days + 1;
                                        echo $duration . ' days';
                                    ?>
                                </div>
                            </div>

                            <div class="event-details">
                                <div class="detail-group">
                                    <div class="detail-label">Duration:</div>
                                    <div class="detail-value">
                                        <?php echo date('M d, Y', strtotime($internship['start_date'])) . ' - ' . date('M d, Y', strtotime($internship['end_date'])); ?>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Mode:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($internship['mode']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Stipend:</div>
                                    <div class="detail-value">₹<?php echo number_format($internship['stipend_amount']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Supervisor:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($internship['supervisor_name']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Company Website:</div>
                                    <div class="detail-value">
                                        <a href="<?php echo htmlspecialchars($internship['company_website']); ?>" target="_blank" style="color: #0066cc; text-decoration: none;">
                                            Visit
                                        </a>
                                    </div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Submitted:</div>
                                    <div class="detail-value"><?php echo date('M d, Y', strtotime($internship['submission_date'])); ?></div>
                                </div>
                            </div>

                            <div class="actions-section">
                                <?php if (! empty($internship['internship_certificate'])): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($internship['internship_certificate']); ?>"
                                       target="_blank" class="action-btn btn-download">
                                        <span class="material-symbols-outlined">download</span>
                                        Certificate
                                    </a>
                                <?php endif; ?>
                                <?php if (! empty($internship['offer_letter'])): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($internship['offer_letter']); ?>"
                                       target="_blank" class="action-btn btn-view">
                                        <span class="material-symbols-outlined">description</span>
                                        Offer Letter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php $internship_display_stmt->close(); ?>
                </div>

                <!-- Mobile Card Layout for Internships -->
                <div class="mobile-card-table">
                    <?php
                        // Re-fetch for mobile view
                        $internship_mobile_stmt = $conn->prepare($internship_sql);
                        $internship_mobile_stmt->bind_param("s", $regno);
                        $internship_mobile_stmt->execute();
                        $internships_mobile = $internship_mobile_stmt->get_result();

                        while ($internship = $internships_mobile->fetch_assoc()):
                    ?>
                        <div class="participation-card">
                            <h4><?php echo htmlspecialchars($internship['company_name']); ?></h4>

                            <div class="participation-info">
                                <div class="info-row">
                                    <div class="info-label">Role</div>
                                    <div class="info-value"><?php echo htmlspecialchars($internship['role_title']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Domain</div>
                                    <div class="info-value"><?php echo htmlspecialchars($internship['domain']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Duration</div>
                                    <div class="info-value">
                                        <?php echo date('M d, Y', strtotime($internship['start_date'])) . ' - ' . date('M d, Y', strtotime($internship['end_date'])); ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Mode</div>
                                    <div class="info-value"><?php echo htmlspecialchars($internship['mode']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Stipend</div>
                                    <div class="info-value">₹<?php echo number_format($internship['stipend_amount']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Supervisor</div>
                                    <div class="info-value"><?php echo htmlspecialchars($internship['supervisor_name']); ?></div>
                                </div>
                            </div>

                            <div class="actions-section">
                                <?php if (! empty($internship['internship_certificate'])): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($internship['internship_certificate']); ?>"
                                       target="_blank" class="action-btn btn-download">
                                        <span class="material-symbols-outlined">download</span>
                                        Certificate
                                    </a>
                                <?php endif; ?>
                                <?php if (! empty($internship['offer_letter'])): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($internship['offer_letter']); ?>"
                                       target="_blank" class="action-btn btn-view">
                                        <span class="material-symbols-outlined">description</span>
                                        Offer Letter
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php $internship_mobile_stmt->close(); ?>
                </div>
            </div>
            <?php endif; ?>
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

        document.addEventListener('DOMContentLoaded', function() {
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            const sidebar = document.getElementById('sidebar');

            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    !headerMenuIcon.contains(event.target)) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });

            // ============================================================================
            // NOTIFICATION SYSTEM
            // ============================================================================

            const notificationBell = document.getElementById('notificationBell');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationOverlay = document.createElement('div');
            notificationOverlay.className = 'notification-overlay';
            document.body.appendChild(notificationOverlay);

            // Fetch notifications when page loads
            function loadNotifications() {
                fetch('ajax/get_notifications.php?action=get_notifications')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayNotifications(data.notifications, data.unread_count);
                        }
                    })
                    .catch(error => console.log('Error loading notifications:', error));
            }

            function displayNotifications(notifications, unreadCount) {
                const notificationList = document.getElementById('notificationList');
                const notificationBadge = document.getElementById('notificationBadge');

                // Update badge
                if (unreadCount > 0) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }

                // Clear list
                notificationList.innerHTML = '';

                if (notifications.length === 0) {
                    notificationList.innerHTML = `
                        <li class="notification-empty">
                            <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                            <p>No notifications</p>
                        </li>
                    `;
                    return;
                }

                // Add notifications
                notifications.forEach(notification => {
                    const date = new Date(notification.created_at);
                    const timeString = getTimeString(date);

                    const li = document.createElement('li');
                    li.className = `notification-item ${(notification.is_read == 0 || notification.is_read === null) ? 'unread' : ''}`;
                    li.innerHTML = `
                        <div class="notification-item-icon">
                            <span class="material-symbols-outlined">emoji_events</span>
                        </div>
                        <div class="notification-item-content">
                            <h4>${escapeHtml(notification.title || notification.hackathon_title)}</h4>
                            <p>${escapeHtml(notification.message)}</p>
                            <span class="notification-item-time">${timeString}</span>
                        </div>
                    `;
                    li.style.cursor = 'pointer';
                    li.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Notification clicked:', notification.id, notification.link);
                        handleNotificationClick(notification.id, notification.link);
                    };
                    notificationList.appendChild(li);
                });
            }

            function resolveNotificationLink(link) {
                if (!link) return '/event_management_system/login/student/hackathons.php';
                if (link.startsWith('/event_management_system/')) return link;
                if (link.startsWith('/student/')) return '/event_management_system/login' + link;
                if (link.startsWith('student/')) return '/event_management_system/login/' + link;
                if (!link.startsWith('/') && !link.startsWith('http')) return '/event_management_system/login/student/' + link;
                return link;
            }

            function handleNotificationClick(notificationId, link) {
                // Close dropdown immediately
                notificationDropdown.classList.remove('show');
                notificationOverlay.classList.remove('show');

                const fullLink = resolveNotificationLink(link);

                // Mark as read then redirect (always redirect regardless of mark_as_read result)
                fetch(`ajax/get_notifications.php?action=mark_as_read&id=${notificationId}`)
                    .finally(() => {
                        window.location.href = fullLink;
                    });
            }

            window.clearAllNotifications = function() {
                if (!confirm('Are you sure you want to clear all notifications?')) return;

                const notificationList = document.getElementById('notificationList');
                notificationList.innerHTML = `
                    <li class="notification-empty">
                        <span class="notification-empty-icon material-symbols-outlined">notifications_none</span>
                        <p>No notifications</p>
                    </li>
                `;
                const notificationBadge = document.getElementById('notificationBadge');
                notificationBadge.classList.add('hidden');
                notificationBadge.textContent = '0';

                fetch('ajax/get_notifications.php?action=clear_all')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            loadNotifications();
                        }
                    })
                    .catch(error => console.log('Error clearing notifications:', error));
            };

            window.markAllNotificationsAsRead = function() {
                const notificationItems = document.querySelectorAll('#notificationList .notification-item.unread');
                notificationItems.forEach(item => item.classList.remove('unread'));
                const notificationBadge = document.getElementById('notificationBadge');
                notificationBadge.classList.add('hidden');
                notificationBadge.textContent = '0';

                fetch('ajax/get_notifications.php?action=mark_all_read')
                    .then(response => {
                        if (!response.ok) throw new Error('Network response was not ok');
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            loadNotifications();
                        }
                    })
                    .catch(error => console.log('Error marking all notifications as read:', error));
            };

            function getTimeString(date) {
                const now = new Date();
                const diff = now - date;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);

                if (minutes < 1) return 'just now';
                if (minutes < 60) return `${minutes}m ago`;
                if (hours < 24) return `${hours}h ago`;
                if (days < 7) return `${days}d ago`;

                return date.toLocaleDateString();
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Toggle notification dropdown
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                notificationOverlay.classList.toggle('show');
            });

            // Close dropdown when clicking overlay
            notificationOverlay.addEventListener('click', function() {
                notificationDropdown.classList.remove('show');
                notificationOverlay.classList.remove('show');
            });

            // Close dropdown when clicking outside (except on bell)
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                    notificationOverlay.classList.remove('show');
                }
            });

            // Load notifications on page load
            loadNotifications();
            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);
        });

        // File error handling
        function showErrorModal(message) {
            const modal = document.getElementById('errorModal');
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = message;
            modal.style.display = 'block';
        }

        function closeErrorModal() {
            document.getElementById('errorModal').style.display = 'none';
        }

        function checkFileAndOpen(url, type) {
            // Create a temporary link to test if file exists
            const tempLink = document.createElement('a');
            tempLink.href = url;

            // Try to fetch the file to check if it exists
            fetch(url, { method: 'HEAD' })
                .then(response => {
                    if (response.ok) {
                        // File exists, open it
                        window.open(url, '_blank');
                    } else {
                        // File not found
                        showErrorModal(`${type} file not found. The file may have been moved or deleted.`);
                    }
                })
                .catch(error => {
                    // Network error or file not accessible
                    showErrorModal(`Unable to access ${type.toLowerCase()} file. Please check your internet connection and try again.`);
                });
        }

        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('errorModal');
            if (event.target === modal) {
                closeErrorModal();
            }
        });
    </script>

    <!-- Error Modal -->
    <div id="errorModal" class="error-modal">
        <div class="error-modal-content">
            <span class="close-x" onclick="closeErrorModal()">&times;</span>
            <div class="error-icon">
                <span class="material-symbols-outlined">error</span>
            </div>
            <div class="error-title">File Not Found</div>
            <div class="error-message" id="errorMessage">The requested file could not be found.</div>
            <button class="error-close-btn" onclick="closeErrorModal()">OK</button>
        </div>
    </div>
    <!-- Push Notifications Manager for Median.co -->
</body>
</html>

<?php
$conn->close();
?>