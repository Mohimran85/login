<?php
    session_start();
    require_once __DIR__ . '/../includes/security.php';
    require_once __DIR__ . '/../includes/DatabaseManager.php';

    // Prevent caching
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Require student authentication
    requireAuth('../index.php');

    $db       = DatabaseManager::getInstance();
    $username = $_SESSION['username'];

    // Get student regno
    $student_query = "SELECT regno, name FROM student_register WHERE username = ? LIMIT 1";
    $student_data  = $db->executeQuery($student_query, [$username]);
    $student_regno = $student_data[0]['regno'];
    $student_name  = $student_data[0]['name'];

    // Get status filter
    $status_filter = $_GET['status'] ?? 'all';
    $search        = $_GET['search'] ?? '';

    // Build query
    $where_conditions = ["ha.student_regno = ?"];
    $params           = [$student_regno];

    if ($status_filter !== 'all') {
    $where_conditions[] = "ha.status = ?";
    $params[]           = $status_filter;
    }

    if (! empty($search)) {
    $where_conditions[] = "(hp.title LIKE ? OR hp.theme LIKE ? OR hp.organizer LIKE ?)";
    $search_term        = "%$search%";
    $params[]           = $search_term;
    $params[]           = $search_term;
    $params[]           = $search_term;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get applications
    $sql = "SELECT
    ha.*,
    hp.title,
    hp.description,
    hp.organizer,
    hp.theme,
    hp.start_date,
    hp.end_date,
    hp.registration_deadline,
    hp.poster_url,
    hp.status as hackathon_status,
    hp.max_participants,
    hp.current_registrations,
    CASE WHEN hp.registration_deadline < NOW() THEN 1 ELSE 0 END as is_expired,
    CASE WHEN hp.max_participants > 0 AND hp.current_registrations >= hp.max_participants THEN 1 ELSE 0 END as is_full
    FROM hackathon_applications ha
    INNER JOIN hackathon_posts hp ON ha.hackathon_id = hp.id
    WHERE $where_clause
    ORDER BY ha.applied_at DESC";

    $applications = $db->executeQuery($sql, $params);

    // Get statistics
    $stats_sql = "SELECT
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'withdrawn' THEN 1 END) as withdrawn
    FROM hackathon_applications
    WHERE student_regno = ?";
    $stats = $db->executeQuery($stats_sql, [$student_regno])[0];

    // Handle withdrawal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $application_id = (int) $_POST['application_id'];

    // Verify ownership and check deadline
    $check_sql = "SELECT ha.id, hp.registration_deadline
                  FROM hackathon_applications ha
                  INNER JOIN hackathon_posts hp ON ha.hackathon_id = hp.id
                  WHERE ha.id = ? AND ha.student_regno = ? AND ha.status = 'confirmed'";
    $check_result = $db->executeQuery($check_sql, [$application_id, $student_regno]);

    if (! empty($check_result)) {
        $deadline = strtotime($check_result[0]['registration_deadline']);
        if (time() < $deadline) {
            $update_sql = "UPDATE hackathon_applications SET status = 'withdrawn' WHERE id = ?";
            $db->executeQuery($update_sql, [$application_id]);
            $_SESSION['success_message'] = "Application withdrawn successfully.";
        } else {
            $_SESSION['error_message'] = "Cannot withdraw after registration deadline.";
        }
    }

    header("Location: my_hackathons.php");
    exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Hackathon Applications</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7f6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }

        .header h1 {
            color: #1a408c;
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .back-button {
            background: #1a408c;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: none;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 64, 140, 0.4);
            background: #15306b;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #eee;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .stat-icon.total {
            background: #1a408c;
            color: white;
        }

        .stat-icon.confirmed {
            background: #28a745;
            color: white;
        }

        .stat-icon.withdrawn {
            background: #dc3545;
            color: white;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-info p {
            font-size: 14px;
            color: #666;
        }

        .filters {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }

        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #1a408c;
        }

        .filter-group input {
            flex: 1;
            min-width: 200px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
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
            box-shadow: 0 6px 20px rgba(26, 64, 140, 0.4);
            background: #15306b;
        }

        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .application-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border: 1px solid #eee;
        }

        .application-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            gap: 20px;
            padding: 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }

        .card-poster {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .card-info {
            flex: 1;
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .card-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #666;
        }

        .card-body {
            padding: 25px;
        }

        .application-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .detail-value {
            font-size: 14px;
            color: #2c3e50;
            font-weight: 500;
        }

        .team-members {
            margin-top: 10px;
            padding-left: 15px;
        }

        .team-members li {
            font-size: 13px;
            color: #555;
            margin: 5px 0;
        }

        .project-description {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border-left: 4px solid #1a408c;
        }

        .project-description h4 {
            font-size: 14px;
            color: #1a408c;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .project-description p {
            font-size: 14px;
            color: #555;
            line-height: 1.6;
            white-space: pre-line;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e9ecef;
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

        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .badge-withdrawn {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-team {
            background: #cfe2ff;
            color: #084298;
        }

        .badge-individual {
            background: #e2e3e5;
            color: #383d41;
        }

        .empty-state {
            background: white;
            border-radius: 15px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ee0979, #ff6a00);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(238, 9, 121, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: #1a408c;
            border: 2px solid #1a408c;
        }

        .btn-outline:hover {
            background: #1a408c;
            color: white;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-header {
                flex-direction: column;
            }

            .card-poster {
                width: 100%;
                height: 200px;
            }

            .application-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span class="material-symbols-outlined">folder_open</span>
                My Applications
            </h1>
            <a href="hackathons.php" class="back-button">
                <span class="material-symbols-outlined">arrow_back</span>
                Browse Hackathons
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
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

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?php echo $_SESSION['success_message'];unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <span class="material-symbols-outlined">error</span>
                <span><?php echo $_SESSION['error_message'];unset($_SESSION['error_message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-group">
                <input type="text" name="search" placeholder="Search by hackathon title..."
                       value="<?php echo htmlspecialchars($search); ?>">

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

        <!-- Applications List -->
        <div class="applications-list">
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined">inbox</span>
                    <h3>No Applications Found</h3>
                    <p>You haven't applied to any hackathons yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        <div class="card-header">
                            <?php if ($app['poster_url']): ?>
                                <img src="<?php echo htmlspecialchars($app['poster_url']); ?>"
                                     alt="Poster" class="card-poster">
                            <?php endif; ?>

                            <div class="card-info">
                                <h2 class="card-title"><?php echo htmlspecialchars($app['title']); ?></h2>

                                <div style="display: flex; gap: 8px; margin: 10px 0;">
                                    <span class="badge badge-<?php echo $app['status']; ?>">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">
                                            <?php echo $app['status'] === 'confirmed' ? 'check_circle' : 'cancel'; ?>
                                        </span>
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>

                                    <span class="badge badge-<?php echo $app['application_type']; ?>">
                                        <span class="material-symbols-outlined" style="font-size: 16px;">
                                            <?php echo $app['application_type'] === 'team' ? 'groups' : 'person'; ?>
                                        </span>
                                        <?php echo ucfirst($app['application_type']); ?>
                                    </span>
                                </div>

                                <div class="card-meta">
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">event</span>
                                        <?php echo date('M d, Y', strtotime($app['start_date'])); ?> -
                                        <?php echo date('M d, Y', strtotime($app['end_date'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">calendar_today</span>
                                        Applied: <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="application-details">
                                <div class="detail-item">
                                    <div class="detail-label">Organizer</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($app['organizer']); ?></div>
                                </div>

                                <?php if ($app['theme']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Theme</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($app['theme']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($app['application_type'] === 'team'): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Team Name</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($app['team_name']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <div class="detail-label">Registration Deadline</div>
                                    <div class="detail-value">
                                        <?php echo date('M d, Y h:i A', strtotime($app['registration_deadline'])); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($app['application_type'] === 'team' && $app['team_members']): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Team Members</div>
                                    <ul class="team-members">
                                        <?php
                                            $members = json_decode($app['team_members'], true);
                                            if ($members) {
                                                foreach ($members as $member) {
                                                    echo '<li>' . htmlspecialchars($member['name']);
                                                    if (! empty($member['regno'])) {
                                                        echo ' (' . htmlspecialchars($member['regno']) . ')';
                                                    }
                                                    echo '</li>';
                                                }
                                            }
                                        ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="project-description">
                                <h4>
                                    <span class="material-symbols-outlined" style="font-size: 18px;">description</span>
                                    Project Description
                                </h4>
                                <p><?php echo nl2br(htmlspecialchars($app['project_description'])); ?></p>
                            </div>

                            <div class="card-actions">
                                <a href="hackathon_details.php?id=<?php echo $app['hackathon_id']; ?>"
                                   class="btn btn-outline">
                                    <span class="material-symbols-outlined">visibility</span>
                                    View Hackathon
                                </a>

                                <?php if ($app['status'] === 'confirmed' && ! $app['is_expired']): ?>
                                    <form method="POST" style="display: inline;"
                                          onsubmit="return confirm('Are you sure you want to withdraw this application?');">
                                        <input type="hidden" name="action" value="withdraw">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <span class="material-symbols-outlined">cancel</span>
                                            Withdraw Application
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
