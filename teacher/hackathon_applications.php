<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Require authentication
    requireAuth('../index.php');

    // Check if user is admin
    $username = $_SESSION['username'];
    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

    $teacher_status_sql = "SELECT COALESCE(status, 'teacher') as status, COALESCE(is_hackathon_coordinator, 0) as is_hackathon_coordinator FROM teacher_register WHERE username = ? LIMIT 1";
    $stmt               = $conn->prepare($teacher_status_sql);
    if (! $stmt) {
    die(json_encode(['success' => false, 'error' => 'Database error']));
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0 || ($row = $result->fetch_assoc()) && $row['status'] !== 'admin' && ! $row['is_hackathon_coordinator']) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
    }
    $stmt->close();
    $is_admin_user       = ($row['status'] === 'admin');
    $is_coordinator_user = (bool) $row['is_hackathon_coordinator'];
    $is_counselor_user   = ($row['status'] === 'counselor' || $row['status'] === 'admin');
    $conn->close();

    // Initialize database manager
    $db = DatabaseManager::getInstance();

    // Get user name for header
    $user_query = "SELECT name FROM teacher_register WHERE username = ? LIMIT 1";
    $user_data  = $db->executeQuery($user_query, [$username]);
    $user_name  = $user_data[0]['name'] ?? 'Admin';

    // Get filter parameters
    $hackathon_filter = isset($_GET['hackathon']) ? $_GET['hackathon'] : 'all';
    $type_filter      = isset($_GET['type']) ? $_GET['type'] : 'all';
    $status_filter    = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search           = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build WHERE clause
    $where_conditions = ["1=1"];
    $params           = [];

    if ($hackathon_filter !== 'all') {
    $where_conditions[] = "ha.hackathon_id = ?";
    $params[]           = $hackathon_filter;
    }

    if ($type_filter !== 'all') {
    $where_conditions[] = "ha.application_type = ?";
    $params[]           = $type_filter;
    }

    if ($status_filter !== 'all') {
    $where_conditions[] = "ha.status = ?";
    $params[]           = $status_filter;
    }

    if (! empty($search)) {
    $where_conditions[] = "(sr.regno LIKE ? OR sr.name LIKE ? OR hp.title LIKE ? OR ha.team_name LIKE ?)";
    $search_term        = "%$search%";
    $params[]           = $search_term;
    $params[]           = $search_term;
    $params[]           = $search_term;
    $params[]           = $search_term;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get applications
    $sql = "SELECT
    ha.*,
    hp.title as hackathon_title,
    hp.start_date,
    hp.end_date,
    sr.name as student_name,
    sr.department
    FROM hackathon_applications ha
    INNER JOIN hackathon_posts hp ON ha.hackathon_id = hp.id
    INNER JOIN student_register sr ON ha.student_regno = sr.regno
    WHERE $where_clause
    ORDER BY ha.applied_at DESC";

    $applications = $db->executeQuery($sql, empty($params) ? [] : $params);

    // Get hackathon list for filter dropdown
    $hackathons_sql = "SELECT id, title FROM hackathon_posts ORDER BY created_at DESC";
    $hackathons     = $db->executeQuery($hackathons_sql);

    // Get statistics
    $stats_sql = "SELECT
    COUNT(*) as total,
    COUNT(CASE WHEN application_type = 'individual' THEN 1 END) as individual,
    COUNT(CASE WHEN application_type = 'team' THEN 1 END) as team,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'withdrawn' THEN 1 END) as withdrawn
    FROM hackathon_applications";

    $stats = $db->executeQuery($stats_sql)[0];

    // Export to CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="hackathon_applications_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    fputcsv($output, [
        'Application ID',
        'Hackathon',
        'Student Name',
        'Regno',
        'Department',
        'Application Type',
        'Team Name',
        'Project Description',
        'Status',
        'Applied Date',
    ]);

    // Data
    foreach ($applications as $app) {
        fputcsv($output, [
            $app['id'],
            $app['hackathon_title'],
            $app['student_name'],
            $app['student_regno'],
            $app['department'],
            ucfirst($app['application_type']),
            $app['team_name'] ?? '-',
            substr($app['project_description'], 0, 100) . '...',
            ucfirst($app['status']),
            date('Y-m-d H:i:s', strtotime($app['applied_at'])),
        ]);
    }

    fclose($output);
    exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="theme-color" content="#0c3878">
    <meta name="color-scheme" content="light only">
    <title>Hackathon Applications - Admin Dashboard</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../assets/images/favicon_io/site.webmanifest">
    <!-- CSS -->
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        /* Page-specific styles */
        .page-header-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header-title h1 {
            font-size: 28px;
            color: #0c3878;
        }

        .page-header-title .material-symbols-outlined {
            font-size: 36px;
            color: #0c3878;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: #0c3878;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-primary:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .btn-success {
            background: #0c3878;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-success:hover {
            background: #0a2d5f;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(12, 56, 120, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-icon.total { background: #0c3878; }
        .stat-icon.individual { background: #1565C0; }
        .stat-icon.team { background: #1976D2; }
        .stat-icon.confirmed { background: #2E7D32; }
        .stat-icon.withdrawn { background: #D32F2F; }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #0c3878;
        }

        .stat-info p {
            font-size: 14px;
            color: #666;
        }

        .filters {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .filter-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: center;
        }

        .filter-row input,
        .filter-row select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .filter-row input:focus,
        .filter-row select:focus {
            outline: none;
            border-color: #0c3878;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #0c3878;
            color: white;
        }

        thead th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        tbody td {
            padding: 15px;
            font-size: 14px;
            color: #555;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-individual {
            background: #fff3cd;
            color: #856404;
        }

        .badge-team {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-confirmed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .badge-withdrawn {
            background: #f8d7da;
            color: #842029;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .view-details {
            color: #0c3878;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }

        .view-details:hover {
            color: #0a2d5f;
        }

        .truncate {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 1024px) {
            .filter-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- header -->
        <div class="header">
            <div class="menu-icon" onclick="document.getElementById('sidebar').classList.toggle('active')">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200" />
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
                        if ($is_admin_user) {
                            echo 'Admin Portal';
                        } elseif ($is_counselor_user) {
                            echo 'Counselor Portal';
                        } elseif ($is_coordinator_user) {
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
                <div class="student-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="student-regno">
                    <?php
                        if ($is_admin_user) {
                            echo '(Admin)';
                        } elseif ($is_counselor_user) {
                            echo '(Counselor)';
                        } else {
                            echo '(Coordinator)';
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
                    <?php if ($is_counselor_user && ! $is_admin_user): ?>
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
                    <?php if ($is_coordinator_user && ! $is_admin_user): ?>
                    <li class="nav-item">
                        <a href="hackathons.php" class="nav-link active">
                            <span class="material-symbols-outlined">workspace_premium</span>
                            Hackathons
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($is_admin_user): ?>
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

        <!-- main container -->
        <div class="main">
            <div class="page-header-section">
                <div class="page-header-title">
                    <span class="material-symbols-outlined">description</span>
                    <h1>Hackathon Applications</h1>
                </div>
                <div class="header-actions">
                    <a href="create_hackathon.php" class="btn btn-primary">
                        <span class="material-symbols-outlined">add</span>
                        Create Hackathon
                    </a>
                    <a href="?export=csv&<?php echo htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success">
                        <span class="material-symbols-outlined">download</span>
                        Export CSV
                    </a>
                    <a href="hackathons.php" class="btn btn-primary">
                        <span class="material-symbols-outlined">arrow_back</span>
                        Back to Hackathons
                    </a>
                </div>
            </div>

            <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>Total Applications</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon individual">
                    <span class="material-symbols-outlined">person</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['individual']; ?></h3>
                    <p>Individual</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon team">
                    <span class="material-symbols-outlined">groups</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['team']; ?></h3>
                    <p>Team</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon confirmed">
                    <span class="material-symbols-outlined">check_circle</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon withdrawn">
                    <span class="material-symbols-outlined">cancel</span>
                </div>
                <div class="stat-info">
                    <h3><?php echo $stats['withdrawn']; ?></h3>
                    <p>Withdrawn</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-row">
                <input type="text" name="search" placeholder="Search by student name, regno, team name..."
                       value="<?php echo htmlspecialchars($search); ?>">

                <select name="hackathon">
                    <option value="all">All Hackathons</option>
                    <?php foreach ($hackathons as $h): ?>
                        <option value="<?php echo $h['id']; ?>"
                                <?php echo $hackathon_filter == $h['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($h['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="type">
                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="individual" <?php echo $type_filter === 'individual' ? 'selected' : ''; ?>>Individual</option>
                    <option value="team" <?php echo $type_filter === 'team' ? 'selected' : ''; ?>>Team</option>
                </select>

                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="withdrawn" <?php echo $status_filter === 'withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined">search</span>
                    Filter
                </button>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="table-container">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined">inbox</span>
                    <h3>No Applications Found</h3>
                    <p>No applications match your current filters.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Hackathon</th>
                            <th>Type</th>
                            <th>Team/Project</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><strong>#<?php echo $app['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['student_name']); ?></strong><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($app['student_regno']); ?></small><br>
                                    <small style="color: #999;"><?php echo htmlspecialchars($app['department']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['hackathon_title']); ?></strong><br>
                                    <small style="color: #999;">
                                        <?php echo date('M d', strtotime($app['start_date'])); ?> -
                                        <?php echo date('M d, Y', strtotime($app['end_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($app['application_type'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">
                                            <?php echo $app['application_type'] === 'team' ? 'groups' : 'person'; ?>
                                        </span>
                                        <?php echo ucfirst($app['application_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($app['application_type'] === 'team'): ?>
                                        <strong><?php echo htmlspecialchars($app['team_name'] ?? 'N/A'); ?></strong><br>
                                        <?php
                                            if ($app['team_members']) {
                                                $members = json_decode($app['team_members'], true);
                                                if ($members) {
                                                    echo '<small style="color: #999;">' . count($members) . ' members</small>';
                                                }
                                            }
                                        ?>
                                    <?php else: ?>
                                        <div class="truncate" title="<?php echo htmlspecialchars($app['project_description']); ?>">
                                            <?php echo htmlspecialchars(substr($app['project_description'], 0, 50)) . '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($app['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">
                                            <?php echo $app['status'] === 'confirmed' ? 'check_circle' : 'cancel'; ?>
                                        </span>
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_at'])); ?></td>
                                <td>
                                    <a href="javascript:void(0)"
                                       onclick="viewDetails(<?php echo htmlspecialchars(json_encode($app)); ?>)"
                                       class="view-details">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        </main>
    </div>

    <!-- Modal for viewing details -->
    <div id="detailsModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:9999; padding:20px; overflow-y:auto;">
        <div style="max-width:800px; margin:50px auto; background:white; border-radius:20px; padding:40px; position:relative;">
            <button onclick="closeModal()" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:24px; cursor:pointer; color:#999;">✕</button>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        function viewDetails(app) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');

            let teamMembers = '';
            if (app.application_type === 'team' && app.team_members) {
                try {
                    const members = JSON.parse(app.team_members);
                    teamMembers = '<h3 style="margin-top:20px; color:#0c3878;">Team Members</h3><ul style="margin-top:10px; padding-left:20px;">';
                    members.forEach(member => {
                        teamMembers += `<li style="margin:5px 0;">${member.name}${member.regno ? ' (' + member.regno + ')' : ''}</li>`;
                    });
                    teamMembers += '</ul>';
                } catch(e) {}
            }

            content.innerHTML = `
                <h2 style="color:#0c3878; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
                    <span class="material-symbols-outlined">description</span>
                    Application Details
                </h2>

                <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:20px; margin-bottom:20px;">
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">APPLICATION ID</h4>
                        <p style="font-size:16px; font-weight:600;">#${app.id}</p>
                    </div>
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">STATUS</h4>
                        <p style="font-size:16px; font-weight:600; text-transform:capitalize;">${app.status}</p>
                    </div>
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">STUDENT NAME</h4>
                        <p style="font-size:16px; font-weight:600;">${app.student_name}</p>
                    </div>
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">REGNO</h4>
                        <p style="font-size:16px; font-weight:600;">${app.student_regno}</p>
                    </div>
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">DEPARTMENT</h4>
                        <p style="font-size:16px;">${app.department}</p>
                    </div>
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">APPLICATION TYPE</h4>
                        <p style="font-size:16px; font-weight:600; text-transform:capitalize;">${app.application_type}</p>
                    </div>
                    ${app.team_name ? `<div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">TEAM NAME</h4>
                        <p style="font-size:16px; font-weight:600;">${app.team_name}</p>
                    </div>` : ''}
                    <div>
                        <h4 style="color:#999; font-size:12px; margin-bottom:5px;">APPLIED DATE</h4>
                        <p style="font-size:16px;">${new Date(app.applied_at).toLocaleString()}</p>
                    </div>
                </div>

                ${teamMembers}

                <h3 style="margin-top:25px; color:#0c3878;">Project Description</h3>
                <div style="background:#f8f9fa; padding:20px; border-radius:10px; margin-top:10px; border-left:4px solid #0c3878;">
                    <p style="line-height:1.6; white-space:pre-line;">${app.project_description}</p>
                </div>

                <h3 style="margin-top:25px; color:#0c3878;">Hackathon Details</h3>
                <div style="background:#f8f9fa; padding:20px; border-radius:10px; margin-top:10px;">
                    <h4 style="font-size:18px; margin-bottom:10px;">${app.hackathon_title}</h4>
                    <p style="color:#666;">
                        <strong>Dates:</strong> ${new Date (app.start_date).toLocaleDateString()} - ${new Date(app.end_date).toLocaleDateString()}
                    </p>
                </div>
            `;

            modal.style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // Close on outside click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Prevent back button
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</body>
</html>
