<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';

    // Prevent caching with stronger headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
    header("Last-Modified: " . gmdate('D, d M Y H:i:s') . " GMT");
    header("ETag: " . md5(microtime()));

    // Require student authentication
    requireAuth('../index.php');

    // Verify user is a student
    $username      = $_SESSION['username'];
    $student_query = "SELECT regno, name FROM student_register WHERE username = ? LIMIT 1";
    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

    $stmt = $conn->prepare($student_query);
    if (! $stmt) {
    error_log('Hackathons page DB error: ' . $conn->error);
    die('An error occurred. Please try again later.');
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized access']));
    }

    $student_row   = $result->fetch_assoc();
    $student_regno = $student_row['regno'];
    $student_name  = $student_row['name'];
    $stmt->close();
    $conn->close();

    // Initialize database manager
    $db = DatabaseManager::getInstance();

    // Get filter parameters
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
    $search_query  = isset($_GET['search']) ? $_GET['search'] : '';

    // Build WHERE clause - show upcoming, ongoing, and completed hackathons (not drafts or cancelled)
    $where_conditions = ["hp.status IN ('upcoming', 'ongoing', 'completed')"];
    $params           = [];

    if (! empty($filter_status) && $filter_status !== 'all') {
    $where_conditions[0] = "hp.status = ?";
    $params[]            = $filter_status;
    }

    if (! empty($search_query)) {
    $where_conditions[] = "(hp.title LIKE ? OR hp.description LIKE ? OR hp.theme LIKE ? OR hp.tags LIKE ?)";
    $search_param       = "%$search_query%";
    $params             = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Get hackathons
    $hackathons_sql = "SELECT
    hp.*,
    COUNT(DISTINCT ha.id) as total_applications,
    COUNT(DISTINCT CASE WHEN ha.status = 'confirmed' THEN ha.id END) as confirmed_applications,
    CASE
        WHEN EXISTS (SELECT 1 FROM hackathon_applications WHERE hackathon_id = hp.id AND student_regno = ?)
        THEN 1 ELSE 0
    END as has_applied
    FROM hackathon_posts hp
    LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
    $where_clause
    GROUP BY hp.id
    ORDER BY hp.registration_deadline ASC, hp.start_date ASC";

    $hackathons = $db->executeQuery($hackathons_sql, array_merge([$student_regno], $params));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hackathons - Event Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <link rel="stylesheet" href="student_dashboard.css">
    <link rel="manifest" href="../manifest.json">
    <!-- OneSignal Web Push Notifications -->
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js"></script>
    <script>
      const studentRegno = <?php echo json_encode($student_regno); ?>;
      if (navigator.userAgent.indexOf('median') > -1 || navigator.userAgent.indexOf('gonative') > -1) {
        if (studentRegno && window.median) {
          median.onesignal.externalUserId.set(studentRegno);
          median.onesignal.tags.setTags({"regno": studentRegno});
          console.log('Median OneSignal: Set external ID ' + studentRegno);
        }
      } else {
        window.OneSignalDeferred = window.OneSignalDeferred || [];
        OneSignalDeferred.push(async function(OneSignal) {
          await OneSignal.init({ appId: <?php echo json_encode(getenv('ONESIGNAL_APP_ID') ?: ''); ?>, allowLocalhostAsSecureOrigin: true });
          if (studentRegno) {
            OneSignal.login(studentRegno);
            OneSignal.User.addTags({"regno": studentRegno});
            console.log('OneSignal Web: Logged in as ' + studentRegno);
          }
          OneSignal.Notifications.requestPermission();
        });
      }
    </script>
    <style>
        /* Main Content */
        .main {
            grid-area: main;
            padding: 30px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid #eee;
        }

        .page-header .header-content {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 30px;
            flex-wrap: nowrap;
            width: 100%;
        }

        .page-header .header-title {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            justify-content: flex-start;
            flex: 0 1 auto;
            min-width: auto;
        }

        .page-header .header-title div {
            text-align: left;
            flex: 1;
            width: 100%;
        }

        .page-header .header-title h1 {
            font-size: 32px;
            color: #1a408c;
            margin: 0;
        }

        .page-header .header-title p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
            position: relative;
            z-index: 10;
            white-space: nowrap;
        }

        .page-header .header-title .material-symbols-outlined {
            font-size: 40px;
            color: #1a408c;
            flex-shrink: 0;
        }

        .page-header .header-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
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
            background: #1a408c;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 64, 140, 0.4);
            background: #15306b;
        }

        .btn-secondary {
            background: white;
            color: #1a408c;
            border: 2px solid #1a408c;
        }

        .btn-secondary:hover {
            background: #1a408c;
            color: white;
        }

        /* Filters */
        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border: 1px solid #eee;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1a408c;
        }

        /* Hackathon Cards Grid */
        .hackathons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .hackathon-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            border: 1px solid #eee;
        }

        .hackathon-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .card-poster-placeholder {
            width: 100%;
            height: 100%;
            background: #1a408c;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            margin: 0;
            padding: 0;
        }

        .hackathon-card > div:first-child {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #1a408c;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-content {
            padding: 25px;
        }

        .card-header {
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .card-organizer {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-description {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 15px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
        }

        .meta-item .material-symbols-outlined {
            font-size: 18px;
            color: #1a408c;
        }

        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .tag {
            padding: 4px 12px;
            background: #f0f4f8;
            border-radius: 15px;
            font-size: 12px;
            color: #1a408c;
            font-weight: 500;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .participants-info {
            font-size: 13px;
            color: #666;
        }

        .card-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .card-action.apply {
            background: #1a408c;
            color: white;
        }

        .card-action.applied {
            background: #e8f5e9;
            color: #28a745;
            cursor: default;
        }

        .card-action.view {
            background: #f0f4f8;
            color: #1a408c;
        }

        .card-action.view:hover {
            background: #1a408c;
            color: white;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-upcoming {
            background: #f0f4f8;
            color: #1a408c;
        }

        .status-ongoing {
            background: #e8f5e9;
            color: #28a745;
        }

        .deadline-warning {
            background: #fff3e0;
            color: #f57c00;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }

        @media (max-width:768px) {
            .hackathons-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                padding: 20px;
                margin-bottom: 20px;
            }

            .page-header .header-content {
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .page-header .header-title {
                width: 100%;
                flex: none;
            }

            .page-header .header-title div {
                text-align: left;
                flex: 1;
                width: 100%;
            }

            .page-header .header-title h1 {
                font-size: 24px;
            }

            .page-header .header-title p {
                white-space: normal;
            }

            .page-header .header-title .material-symbols-outlined {
                font-size: 32px;
            }

            .page-header .header-actions {
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: 100%;
                flex-wrap: wrap;
            }

            .page-header .header-actions .btn {
                width: 100%;
                justify-content: center;
                padding: 12px 16px;
                font-size: 14px;
            }

            .notification-bell {
                position: absolute;
                top: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
            }

            .notification-bell .material-symbols-outlined {
                font-size: 20px;
            }

            .notification-dropdown {
                position: fixed;
                top: 70px;
                right: 0;
                left: 0;
                bottom: 0;
                width: calc(100% - 40px);
                max-height: 80vh;
                margin: 0 20px;
                border-radius: 15px 15px 0 0;
                box-shadow: 0 -8px 25px rgba(0,0,0,0.15);
            }

            .main {
                padding: 20px 20px 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- header -->
        <div class="header">
            <div class="menu-icon">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="icon">
                <img src="sona_logo.jpg" alt="Sona College Logo" height="60px" width="200" />
            </div>
            <div class="header-title">
                <p>Event Management System</p>
            </div>
        </div>

        <!-- sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Student Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($student_name); ?></div>
                <div class="student-regno"><?php echo htmlspecialchars($student_regno); ?></div>
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
                        <a href="student_register.php" class="nav-link">
                            <span class="material-symbols-outlined">add_circle</span>
                            Register Event
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="student_participations.php" class="nav-link">
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
                        <a href="hackathons.php" class="nav-link active">
                            <span class="material-symbols-outlined">emoji_events</span>
                            Hackathons
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="od_request.php" class="nav-link">
                            <span class="material-symbols-outlined">person_raised_hand</span>
                            OD Request
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

        <!-- main container -->
        <div class="main">
            <div class="container">
                <!-- Header -->
                <div class="page-header">
                    <div class="header-content">
                        <div class="headertitle">

                            <div>
                                <h1>Hackathons</h1>
                                <p style="color: #666; font-size: 14px; margin-top: 5px;">
                                    Explore and participate in exciting hackathons
                                </p>
                            </div>
                        </div>
                        <div class="header-actions">
                            <a href="my_hackathons.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">list_alt</span>
                                My Applications
                            </a>
                            <a href="index.php" class="btn btn-primary">
                                <span class="material-symbols-outlined">home</span>
                                Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="" id="filterForm">
                        <div class="filters-grid">
                            <div class="form-group">
                                <label for="search"><span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">search</span> Search</label>
                                <input type="text" id="search" name="search"
                                       placeholder="Search by title, theme, tags..."
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>

                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" onchange="document.getElementById('filterForm').submit()">
                                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">filter_alt</span>
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Hackathons Grid -->
                <?php if (empty($hackathons)): ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">event_busy</span>
                        <h3>No Hackathons Found</h3>
                        <p>There are no hackathons available at the moment. Check back soon!</p>
                    </div>
        <?php else: ?>
            <div class="hackathons-grid">
                <?php foreach ($hackathons as $hackathon): ?>
                    <?php
                        $deadline          = strtotime($hackathon['registration_deadline']);
                        $now               = time();
                        $days_left         = ceil(($deadline - $now) / 86400);
                        $is_deadline_close = $days_left <= 3 && $days_left >= 0;
                        $is_expired        = $deadline < $now;
                    ?>
                    <div class="hackathon-card" onclick="window.location.href='hackathon_details.php?id=<?php echo (int) $hackathon['id']; ?>'">
                        <div style="position: relative; width: 100%; height: 200px; background: #1a408c; overflow: hidden;">
                            <?php if ($hackathon['poster_url']): ?>
                                <img src="<?php echo htmlspecialchars('../' . $hackathon['poster_url']); ?>"
                                     alt="<?php echo htmlspecialchars($hackathon['title']); ?>"
                                     class="card-poster">
                            <?php else: ?>
                                <div class="card-poster-placeholder">
                                    <span class="material-symbols-outlined">emoji_events</span>
                                </div>
                            <?php endif; ?>
                            <span class="status-badge status-<?php echo htmlspecialchars($hackathon['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(ucfirst($hackathon['status'])); ?>
                            </span>
                        </div>

                        <div class="card-content">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo htmlspecialchars($hackathon['title']); ?></h3>
                                <div class="card-organizer">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">business</span>
                                    <?php echo htmlspecialchars($hackathon['organizer']); ?>
                                </div>
                            </div>

                            <?php if ($is_deadline_close && ! $is_expired && ! $hackathon['has_applied']): ?>
                                <div class="deadline-warning">
                                    <span class="material-symbols-outlined" style="font-size: 16px;">warning</span>
                                    <strong>Deadline in <?php echo $days_left; ?> day<?php echo $days_left != 1 ? 's' : ''; ?>!</strong>
                                </div>
                            <?php endif; ?>

                            <p class="card-description">
                                <?php echo htmlspecialchars(substr($hackathon['description'], 0, 150)) . '...'; ?>
                            </p>

                            <div class="card-meta">
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">calendar_today</span>
                                    <?php echo date('M d', strtotime($hackathon['start_date'])) . ' - ' . date('M d, Y', strtotime($hackathon['end_date'])); ?>
                                </div>
                                <div class="meta-item">
                                    <span class="material-symbols-outlined">schedule</span>
                                    Register by: <?php echo date('M d, Y', strtotime($hackathon['registration_deadline'])); ?>
                                </div>
                                <?php if ($hackathon['theme']): ?>
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined">lightbulb</span>
                                        Theme: <?php echo htmlspecialchars($hackathon['theme']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($hackathon['tags']): ?>
                                <div class="card-tags">
                                    <?php foreach (explode(',', $hackathon['tags']) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="card-footer">
                                <div class="participants-info">
                                    <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 18px;">groups</span>
                                    <?php echo $hackathon['confirmed_applications']; ?>
                                    registered
                                </div>
                                <?php if ($hackathon['has_applied']): ?>
                                    <span class="card-action applied">
                                        <span class="material-symbols-outlined" style="vertical-align: middle; font-size: 16px;">check_circle</span>
                                        Applied
                                    </span>
                                <?php elseif ($is_expired): ?>
                                    <span class="card-action" style="background: #ffebee; color: #c62828;">
                                        Closed
                                    </span>
                                <?php else: ?>
                                    <a href="hackathon_details.php?id=<?php echo $hackathon['id']; ?>"
                                       class="card-action view"
                                       onclick="event.stopPropagation()">
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle function
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

        // Wait for DOM to load
        document.addEventListener('DOMContentLoaded', function() {
            // Header menu icon functionality
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            // Close sidebar button functionality
            const closeSidebarBtn = document.querySelector('.close-sidebar');
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth <= 768 &&
                    sidebar &&
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(event.target) &&
                    (!headerMenuIcon || !headerMenuIcon.contains(event.target))) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                const sidebar = document.getElementById('sidebar');
                if (window.innerWidth > 768 && sidebar) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('sidebar-open');
                }
            });
        });

        // Register service worker for push notifications
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => console.log('Service Worker registered'))
                .catch(error => console.log('Service Worker registration failed:', error));
        }
    </script>
    <!-- Push Notifications Manager for Median.co -->
</body>
</html>
