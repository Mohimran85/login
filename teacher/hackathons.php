<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
    }

    require_once __DIR__ . '/../includes/db_config.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';
    $conn = get_db_connection();

    // Get teacher data and check hackathon coordinator access
    $username                 = $_SESSION['username'];
    $teacher_data             = null;
    $is_counselor             = false;
    $is_admin                 = false;
    $is_hackathon_coordinator = false;

    $sql  = "SELECT id, name, faculty_id as employee_id, COALESCE(status, 'teacher') as status, COALESCE(is_hackathon_coordinator, 0) as is_hackathon_coordinator FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
    $teacher_data             = $result->fetch_assoc();
    $is_counselor             = ($teacher_data['status'] === 'counselor' || $teacher_data['status'] === 'admin');
    $is_admin                 = ($teacher_data['status'] === 'admin');
    $is_hackathon_coordinator = (bool) $teacher_data['is_hackathon_coordinator'];
    } else {
    header("Location: ../index.php");
    exit();
    }
    $stmt->close();

    // Only allow admin or hackathon coordinators
    if (! $is_admin && ! $is_hackathon_coordinator) {
    $_SESSION['access_denied'] = 'Only hackathon coordinators can access this page.';
    header("Location: index.php");
    exit();
    }

    $conn->close();
    $db = DatabaseManager::getInstance();

    // Handle delete operation
    if (isset($_POST['delete_id']) && isset($_POST['csrf_token'])) {
    $delete_id = (int) $_POST['delete_id'];
    try {
        $db->executeQuery("DELETE FROM hackathon_posts WHERE id = ?", [$delete_id]);
        $success_message = "Hackathon deleted successfully!";
    } catch (Exception $e) {
        $error_message = "Error deleting hackathon: " . $e->getMessage();
    }
    }

    // Get filter parameters
    $filter_status    = isset($_GET['status']) ? $_GET['status'] : '';
    $search_query     = isset($_GET['search']) ? $_GET['search'] : '';
    $entries_param    = isset($_GET['entries']) ? $_GET['entries'] : '10';
    $entries_per_page = ($entries_param === 'all') ? PHP_INT_MAX : (int) $entries_param;
    $current_page     = isset($_GET['page']) ? (int) $_GET['page'] : 1;

    // Build WHERE clause
    $where_conditions = [];
    $params           = [];
    $param_types      = "";

    if (! empty($filter_status)) {
    $where_conditions[]  = "hp.status = ?";
    $params[]            = $filter_status;
    $param_types        .= "s";
    }

    if (! empty($search_query)) {
    $where_conditions[]  = "(hp.title LIKE ? OR hp.description LIKE ? OR hp.organizer LIKE ? OR hp.theme LIKE ? OR hp.tags LIKE ?)";
    $search_param        = "%$search_query%";
    $params              = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types        .= "sssss";
    }

    $where_clause  = ! empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Count total records
    $count_sql     = "SELECT COUNT(*) as total FROM hackathon_posts hp $where_clause";
    $count_result  = $db->executeQuery($count_sql, $params);
    $total_records = $count_result[0]['total'] ?? 0;
    $total_pages   = $entries_per_page == PHP_INT_MAX ? 1 : ceil($total_records / $entries_per_page);

    // Get hackathons with pagination
    $offset         = ($current_page - 1) * $entries_per_page;
    $hackathons_sql = "SELECT
    hp.*,
    tr.name as created_by_name,
    COUNT(DISTINCT ha.id) as total_applications,
    COUNT(DISTINCT CASE WHEN ha.status = 'confirmed' THEN ha.id END) as confirmed_applications
    FROM hackathon_posts hp
    LEFT JOIN teacher_register tr ON hp.created_by = tr.id
    LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
    $where_clause
    GROUP BY hp.id
    ORDER BY hp.created_at DESC
    LIMIT $entries_per_page OFFSET $offset";

    $hackathons = $db->executeQuery($hackathons_sql, $params);

    // Get statistics
    $stats_sql  = "SELECT
    COUNT(*) as total_hackathons,
    SUM(CASE WHEN status = 'upcoming' THEN 1 ELSE 0 END) as upcoming,
    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(view_count) as total_views,
    (SELECT COUNT(*) FROM hackathon_applications WHERE status = 'confirmed') as total_applications
    FROM hackathon_posts";
    $stats       = $db->executeQuery($stats_sql);
    $statistics  = $stats[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Hackathon Management - Teacher Dashboard</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../assets/images/favicon_io/site.webmanifest">
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; max-width: 100%; }
        html, body { overflow-x: hidden; width: 100%; position: relative; }

        /* Hackathon page styles */
        .page-header-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-title h1 { font-size: 24px; color: #0c3878; font-weight: 600; }
        .page-header-title .material-symbols-outlined { font-size: 32px; color: #0c3878; }

        .header-actions { display: flex; gap: 12px; flex-wrap: wrap; }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary { background: #0c3878; color: white; }
        .btn-primary:hover { background: #0a2d5f; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(12, 56, 120, 0.3); }
        .btn-secondary { background: #34a853; color: white; }
        .btn-secondary:hover { background: #2d8e47; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-3px); }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .stat-card-title { font-size: 13px; color: #666; font-weight: 500; }
        .stat-card-icon { font-size: 26px; }
        .stat-card-value { font-size: 28px; font-weight: 700; color: #0c3878; }

        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 13px; font-weight: 500; color: #555; }
        .form-group input, .form-group select {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #0c3878; }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead { background: #f8f9fa; }
        th { padding: 12px; text-align: left; font-weight: 600; color: #0c3878; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px; border-bottom: 1px solid #f0f0f0; color: #555; }
        tr:hover { background: #f8f9fa; }

        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; }
        .status-upcoming { background: #e3f2fd; color: #1976d2; }
        .status-ongoing { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #f3e5f5; color: #7b1fa2; }
        .status-draft { background: #eeeeee; color: #666; }
        .status-cancelled { background: #ffebee; color: #c62828; }

        .actions-cell { display: flex; gap: 8px; }
        .icon-btn { background: none; border: none; cursor: pointer; font-size: 18px; color: #666; transition: color 0.3s ease; }
        .icon-btn:hover { color: #0c3878; }
        .icon-btn.delete:hover { color: #ea4335; }

        .hackathon-poster { width: 45px; height: 45px; object-fit: cover; border-radius: 6px; }

        .view-count { display: flex; align-items: center; gap: 4px; color: #666; }
        .view-count:hover { color: #0c3878; }

        .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px; }
        .pagination button { padding: 7px 14px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer; font-family: 'Poppins', sans-serif; }
        .pagination button:hover:not(:disabled) { background: #0c3878; color: white; border-color: #0c3878; }
        .pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination .active { background: #0c3878; color: white; border-color: #0c3878; }

        .alert { padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 0; border-radius: 10px; width: 80%; max-width: 800px; max-height: 80vh; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .modal-header { background: #0c3878; color: white; padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .modal-close { color: white; font-size: 28px; cursor: pointer; border: none; background: none; }
        .modal-body { padding: 22px; max-height: 60vh; overflow-y: auto; }

        .view-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .view-stat-box { background: #f8f9fa; padding: 12px; border-radius: 8px; text-align: center; }
        .view-stat-box .stat-number { font-size: 24px; font-weight: 700; color: #0c3878; }
        .view-stat-box .stat-label { font-size: 11px; color: #666; margin-top: 4px; }

        .views-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .views-table th { padding: 10px; text-align: left; font-weight: 600; color: #0c3878; border-bottom: 2px solid #dee2e6; font-size: 12px; background: #f8f9fa; }
        .views-table td { padding: 10px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }

        .loading-spinner { text-align: center; padding: 30px; color: #999; }
        .loading-spinner .material-symbols-outlined { font-size: 48px; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .no-data { text-align: center; padding: 30px; color: #999; }

        @media (max-width: 768px) {
            .header-content { flex-direction: column; align-items: flex-start; }
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .main { padding: 15px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon" onclick="document.getElementById('sidebar').classList.toggle('active')">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200">
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
                        } elseif ($is_hackathon_coordinator) {
                            echo 'Coordinator Portal';
                        } else {
                            echo 'Teacher Portal';
                        }
                    ?>
                </div>
                <div class="close-sidebar" onclick="document.getElementById('sidebar').classList.remove('active')">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID: <?php echo htmlspecialchars($teacher_data['employee_id']); ?>
                    <?php
                        if ($is_admin) {
                            echo ' (Admin)';
                        } elseif ($is_counselor) {
                            echo ' (Counselor)';
                        } elseif ($is_hackathon_coordinator) {
                            echo ' (Coordinator)';
                        }
                    ?>
                </div>
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
                    <?php if ($is_counselor && ! $is_admin): ?>
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
                    <?php if ($is_hackathon_coordinator && ! $is_admin): ?>
                    <li class="nav-item">
                        <a href="hackathons.php" class="nav-link active">
                            <span class="material-symbols-outlined">workspace_premium</span>
                            Hackathons
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
            <!-- Page Header -->
            <div class="page-header-section">
                <div class="header-content">
                    <div class="page-header-title">
                        <span class="material-symbols-outlined">emoji_events</span>
                        <div>
                            <h1>Hackathon Management</h1>
                            <p style="color: #666; font-size: 13px; margin-top: 4px;">Manage hackathon posts and applications</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="../admin/create_hackathon.php" class="btn btn-primary">
                            <span class="material-symbols-outlined">add</span>
                            Create Hackathon
                        </a>
                        <a href="../admin/hackathon_applications.php" class="btn btn-secondary">
                            <span class="material-symbols-outlined">list_alt</span>
                            View Applications
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <span class="material-symbols-outlined">error</span>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Hackathons</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #0c3878;">emoji_events</span>
                    </div>
                    <div class="stat-card-value"><?php echo $statistics['total_hackathons']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Upcoming</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #1976d2;">schedule</span>
                    </div>
                    <div class="stat-card-value"><?php echo $statistics['upcoming']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Total Views</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #34a853;">visibility</span>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($statistics['total_views']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Applications</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #fbbc04;">groups</span>
                    </div>
                    <div class="stat-card-value"><?php echo $statistics['total_applications']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Ongoing</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #388e3c;">hourglass_top</span>
                    </div>
                    <div class="stat-card-value"><?php echo $statistics['ongoing']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">Completed</span>
                        <span class="material-symbols-outlined stat-card-icon" style="color: #7b1fa2;">check_circle</span>
                    </div>
                    <div class="stat-card-value"><?php echo $statistics['completed']; ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="" id="filterForm">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" placeholder="Search by title, organizer, theme..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="entries">Show Entries</label>
                            <select id="entries" name="entries">
                                <option value="10" <?php echo $entries_param === '10' ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $entries_param === '25' ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $entries_param === '50' ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $entries_param === '100' ? 'selected' : ''; ?>>100</option>
                                <option value="all" <?php echo $entries_param === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <span class="material-symbols-outlined">filter_alt</span>
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Poster</th>
                            <th>Title</th>
                            <th>Organizer</th>
                            <th>Dates</th>
                            <th>Deadline</th>
                            <th>Participants</th>
                            <th>Views</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hackathons)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 40px; color: #999;">
                                    <span class="material-symbols-outlined" style="font-size: 48px; display: block; margin-bottom: 10px;">inbox</span>
                                    No hackathons found. Create your first hackathon!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($hackathons as $hackathon): ?>
                                <tr>
                                    <td>#<?php echo $hackathon['id']; ?></td>
                                    <td>
                                        <?php if ($hackathon['poster_url']): ?>
                                            <img src="<?php echo htmlspecialchars($hackathon['poster_url']); ?>" alt="Poster" class="hackathon-poster">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined" style="font-size: 45px; color: #ddd;">image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($hackathon['title']); ?></strong>
                                        <?php if ($hackathon['theme']): ?>
                                            <br><small style="color: #999;">Theme: <?php echo htmlspecialchars($hackathon['theme']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($hackathon['organizer']); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($hackathon['start_date'])); ?><br>
                                        <small style="color: #999;">to <?php echo date('M d, Y', strtotime($hackathon['end_date'])); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($hackathon['registration_deadline'])); ?></td>
                                    <td><?php echo $hackathon['confirmed_applications']; ?></td>
                                    <td>
                                        <div class="view-count" onclick="showViewDetails(<?php echo (int) $hackathon['id']; ?>, <?php echo htmlspecialchars(json_encode($hackathon['title']), ENT_QUOTES, 'UTF-8'); ?>)" style="cursor: pointer;" title="Click to see view details">
                                            <span class="material-symbols-outlined" style="font-size: 16px;">visibility</span>
                                            <?php echo number_format($hackathon['view_count']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($hackathon['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo ucfirst($hackathon['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($hackathon['created_by_name']); ?></td>
                                    <td>
                                        <div class="actions-cell">
                                            <?php if (! empty($hackathon['hackathon_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($hackathon['hackathon_link']); ?>" class="icon-btn" title="External Link" target="_blank">
                                                    <span class="material-symbols-outlined">link</span>
                                                </a>
                                            <?php endif; ?>
                                            <a href="../admin/edit_hackathon.php?id=<?php echo $hackathon['id']; ?>" class="icon-btn" title="Edit">
                                                <span class="material-symbols-outlined">edit</span>
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this hackathon?')">
                                                <input type="hidden" name="delete_id" value="<?php echo $hackathon['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="icon-btn delete" title="Delete">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <button onclick="goToPage(<?php echo $current_page - 1; ?>)">Previous</button>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <button class="active"><?php echo $i; ?></button>
                            <?php elseif ($i == 1 || $i == $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)): ?>
                                <button onclick="goToPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                            <?php elseif ($i == $current_page - 3 || $i == $current_page + 3): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($current_page < $total_pages): ?>
                            <button onclick="goToPage(<?php echo $current_page + 1; ?>)">Next</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="viewDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <span class="material-symbols-outlined">visibility</span>
                    <span id="modalTitle">View Details</span>
                </h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="loading-spinner">
                    <span class="material-symbols-outlined">progress_activity</span>
                    <p>Loading view details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function goToPage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', page);
            window.location.search = urlParams.toString();
        }

        document.getElementById('status').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
        document.getElementById('entries').addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });

        function showViewDetails(hackathonId, hackathonTitle) {
            const modal = document.getElementById('viewDetailsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('viewModalBody');

            modalTitle.textContent = `View Details: ${hackathonTitle}`;
            modal.style.display = 'block';

            modalBody.innerHTML = `
                <div class="loading-spinner">
                    <span class="material-symbols-outlined">progress_activity</span>
                    <p>Loading view details...</p>
                </div>
            `;

            fetch(`../admin/ajax/get_hackathon_views.php?hackathon_id=${hackathonId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayViewDetails(data.data);
                    } else {
                        modalBody.innerHTML = `
                            <div class="no-data">
                                <span class="material-symbols-outlined" style="font-size: 48px; color: #ea4335;">error</span>
                                <p>${data.error || 'Failed to load view details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="no-data">
                            <span class="material-symbols-outlined" style="font-size: 48px; color: #ea4335;">error</span>
                            <p>Error loading view details. Please try again.</p>
                        </div>
                    `;
                });
        }

        function displayViewDetails(data) {
            const modalBody = document.getElementById('viewModalBody');

            if (!data.views || data.views.length === 0) {
                modalBody.innerHTML = `
                    <div class="no-data">
                        <span class="material-symbols-outlined" style="font-size: 48px;">visibility_off</span>
                        <p>No views recorded yet for this hackathon.</p>
                    </div>
                `;
                return;
            }

            let html = `
                <div class="view-stats">
                    <div class="view-stat-box">
                        <div class="stat-number">${data.total_views}</div>
                        <div class="stat-label">Total Views</div>
                    </div>
                    <div class="view-stat-box">
                        <div class="stat-number">${data.unique_viewers}</div>
                        <div class="stat-label">Unique Viewers</div>
                    </div>
                    <div class="view-stat-box">
                        <div class="stat-number">${data.avg_views_per_user}</div>
                        <div class="stat-label">Avg Views/User</div>
                    </div>
                </div>
                <h3 style="margin: 15px 0 8px 0; color: #0c3878; font-size: 15px;">View History</h3>
                <table class="views-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Register No</th>
                            <th>Department</th>
                            <th>First Viewed</th>
                            <th>Last Viewed</th>
                            <th>View Count</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.views.forEach(view => {
                html += `
                    <tr>
                        <td><strong>${view.student_name}</strong></td>
                        <td>${view.student_regno}</td>
                        <td>${view.department || 'N/A'}</td>
                        <td>${view.first_viewed_at}</td>
                        <td>${view.last_viewed_at}</td>
                        <td><span style="background: #e3f2fd; padding: 4px 10px; border-radius: 12px; font-weight: 600; color: #1976d2;">${view.view_count}</span></td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            modalBody.innerHTML = html;
        }

        function closeViewModal() {
            document.getElementById('viewDetailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('viewDetailsModal');
            if (event.target == modal) closeViewModal();
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeViewModal();
        });
    </script>
</body>
</html>
