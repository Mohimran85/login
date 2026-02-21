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
    $conn     = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
    die("Database connection failed");
    }

    $teacher_status_sql = "SELECT COALESCE(status, 'teacher') as status FROM teacher_register WHERE username = ? LIMIT 1";
    $stmt               = $conn->prepare($teacher_status_sql);
    if (! $stmt) {
    die("Database error");
    }
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0 || ($row = $result->fetch_assoc()) && $row['status'] !== 'admin') {
    http_response_code(403);
    die("Unauthorized access");
    }
    $stmt->close();
    $conn->close();

    // Initialize database manager
    $db = DatabaseManager::getInstance();

    // Get hackathon ID from URL
    $hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($hackathon_id <= 0) {
    $_SESSION['error_message'] = "Invalid hackathon ID.";
    header("Location: hackathons.php");
    exit();
    }

    // Get hackathon details
    $hackathon_sql = "SELECT hp.*, tr.name as created_by_name,
    COUNT(DISTINCT ha.id) as total_applications,
    COUNT(DISTINCT CASE WHEN ha.status = 'confirmed' THEN ha.id END) as confirmed_applications
    FROM hackathon_posts hp
    LEFT JOIN teacher_register tr ON hp.created_by = tr.id
    LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
    WHERE hp.id = ?
    GROUP BY hp.id";

    $hackathons = $db->executeQuery($hackathon_sql, [$hackathon_id]);

    if (empty($hackathons)) {
    $_SESSION['error_message'] = "Hackathon not found.";
    header("Location: hackathons.php");
    exit();
    }

    $hackathon = $hackathons[0];

    // Get applications for this hackathon
    $apps_sql = "SELECT ha.*, sr.name as student_name, sr.regno as student_regno, sr.department
    FROM hackathon_applications ha
    LEFT JOIN student_register sr ON ha.student_regno = sr.regno
    WHERE ha.hackathon_id = ?
    ORDER BY ha.applied_at DESC";

    $applications = $db->executeQuery($apps_sql, [$hackathon_id]);
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
    <title>View Hackathon - Admin Dashboard</title>
    <!-- Favicon and App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="../asserts/images/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../asserts/images/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../asserts/images/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="../asserts/images/favicon_io/site.webmanifest">
    <!-- CSS -->
    <link rel="stylesheet" href="./CSS/styles.css">
    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
        }

        .view-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #0c3878;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #f0f0f0;
        }

        .hackathon-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .header-image {
            width: 300px;
            height: 200px;
            border-radius: 10px;
            object-fit: cover;
            background: #f0f0f0;
        }

        .header-info {
            flex: 1;
        }

        .header-info h1 {
            margin: 0 0 10px 0;
            color: #0c3878;
            font-size: 32px;
        }

        .header-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .meta-item {
            font-size: 14px;
            color: #666;
        }

        .meta-label {
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 3px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 10px;
        }

        .status-upcoming {
            background: #f0f4f8;
            color: #0c3878;
        }

        .status-ongoing {
            background: #fff3cd;
            color: #ff9800;
        }

        .status-draft {
            background: #f0f0f0;
            color: #666;
        }

        .description-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .description-section h2 {
            color: #0c3878;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .description-section p {
            line-height: 1.6;
            color: #555;
            white-space: pre-wrap;
        }

        .applications-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .applications-section h2 {
            color: #0c3878;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
            border-bottom: 1px solid #eee;
        }

        tbody tr:hover {
            background: #f9f9f9;
        }

        tbody td {
            padding: 12px 15px;
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state .material-symbols-outlined {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="view-container">
        <a href="hackathons.php" class="back-btn">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Hackathons
        </a>

        <!-- Hackathon Header -->
        <div class="hackathon-header">
            <div class="header-top">
                <?php if ($hackathon['poster_url']): ?>
                    <img src="<?php echo htmlspecialchars('../' . $hackathon['poster_url']); ?>" alt="Poster" class="header-image" onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="header-image" style="display: flex; align-items: center; justify-content: center; background: #ddd;">
                        <span class="material-symbols-outlined" style="font-size: 80px; color: #999;">emoji_events</span>
                    </div>
                <?php endif; ?>

                <div class="header-info">
                    <h1><?php echo htmlspecialchars($hackathon['title']); ?></h1>
                    <span class="status-badge status-<?php echo $hackathon['status']; ?>">
                        <?php echo ucfirst($hackathon['status']); ?>
                    </span>

                    <div class="header-meta">
                        <div class="meta-item">
                            <span class="meta-label">Organizer</span>
                            <?php echo htmlspecialchars($hackathon['organizer']); ?>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Theme</span>
                            <?php echo htmlspecialchars($hackathon['theme'] ?? 'N/A'); ?>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Start Date</span>
                            <?php echo date('M d, Y', strtotime($hackathon['start_date'])); ?>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">End Date</span>
                            <?php echo date('M d, Y', strtotime($hackathon['end_date'])); ?>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Registration Deadline</span>
                            <?php echo date('M d, Y H:i', strtotime($hackathon['registration_deadline'])); ?>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Applications</span>
                            <?php echo $hackathon['confirmed_applications']; ?>
                            <?php if ($hackathon['max_participants']): ?>
                                / <?php echo $hackathon['max_participants']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="description-section">
            <h2>
                <span class="material-symbols-outlined">description</span>
                Description
            </h2>
            <p><?php echo htmlspecialchars($hackathon['description']); ?></p>
        </div>

        <!-- Applications -->
        <div class="applications-section">
            <h2>
                <span class="material-symbols-outlined">people</span>
                Applications (<?php echo count($applications); ?>)
            </h2>

            <?php if (! empty($applications)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Reg. No.</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Applied On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($app['student_regno'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($app['department'] ?? 'N/A'); ?></td>
                                <td><?php echo ucfirst($app['application_type']); ?></td>
                                <td><?php echo ucfirst($app['status']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($app['applied_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined">inbox</span>
                    <p>No applications yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
