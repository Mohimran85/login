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

    // Get student info
    $student_query = "SELECT regno, name, department FROM student_register WHERE username = ? LIMIT 1";
    $student_data  = $db->executeQuery($student_query, [$username]);

    if (empty($student_data)) {
    $_SESSION['error_message'] = "Student information not found.";
    header("Location: ../index.php");
    exit();
    }

    $student_regno = $student_data[0]['regno'];
    $student_name  = $student_data[0]['name'];
    $student_dept  = $student_data[0]['department'];

    // Get hackathon ID
    $hackathon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (! $hackathon_id) {
    header("Location: hackathons.php");
    exit();
    }

    // Get hackathon details
    $hackathon_sql = "SELECT hp.*, tr.name as created_by_name,
    COUNT(DISTINCT ha.id) as total_applications,
    COUNT(DISTINCT CASE WHEN ha.status = 'confirmed' THEN ha.id END) as confirmed_applications,
    CASE WHEN EXISTS (
        SELECT 1 FROM hackathon_applications
        WHERE hackathon_id = hp.id AND student_regno = ?
    ) THEN 1 ELSE 0 END as has_applied
    FROM hackathon_posts hp
    LEFT JOIN teacher_register tr ON hp.created_by = tr.id
    LEFT JOIN hackathon_applications ha ON hp.id = ha.hackathon_id
    WHERE hp.id = ? AND hp.status IN ('upcoming', 'ongoing')
    GROUP BY hp.id";

    $hackathons = $db->executeQuery($hackathon_sql, [$student_regno, $hackathon_id]);

    if (empty($hackathons)) {
    $_SESSION['error_message'] = "Hackathon not found or not available.";
    header("Location: hackathons.php");
    exit();
    }

    $hackathon = $hackathons[0];

    // Track view (insert into hackathon_views and increment view_count)
    try {
    // Check if already viewed in this session
    $session_key = 'viewed_hackathon_' . $hackathon_id;
    if (! isset($_SESSION[$session_key])) {
        // Insert view record
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $db->executeQuery(
            "INSERT INTO hackathon_views (hackathon_id, student_regno, ip_address, user_agent)
             VALUES (?, ?, ?, ?)",
            [$hackathon_id, $student_regno, $ip_address, $user_agent]
        );

        // Increment view count
        $db->executeQuery(
            "UPDATE hackathon_posts SET view_count = view_count + 1 WHERE id = ?",
            [$hackathon_id]
        );

        // Mark as viewed in session
        $_SESSION[$session_key] = true;
    }
    } catch (Exception $e) {
    error_log("Failed to track hackathon view: " . $e->getMessage());
    }

    // Calculate deadline info
    $deadline          = strtotime($hackathon['registration_deadline']);
    $now               = time();
    $days_left         = ceil(($deadline - $now) / 86400);
    $is_deadline_close = $days_left <= 3 && $days_left >= 0;
    $is_expired        = $deadline < $now;
    $is_full           = $hackathon['max_participants'] && $hackathon['confirmed_applications'] >= $hackathon['max_participants'];
    $can_apply         = ! $is_expired && ! $is_full && ! $hackathon['has_applied'];

    // Get user's application if exists
    $user_application = null;
    if ($hackathon['has_applied']) {
    $app_query        = "SELECT * FROM hackathon_applications WHERE hackathon_id = ? AND student_regno = ? LIMIT 1";
    $app_result       = $db->executeQuery($app_query, [$hackathon_id, $student_regno]);
    $user_application = $app_result[0] ?? null;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hackathon['title']); ?> - Event Management System</title>
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

        .back-button {
            background: #1a408c;
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
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

        .hackathon-container {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #eee;
        }

        .hackathon-header {
            position: relative;
            height: 400px;
            background: #1a408c;
        }

        .header-poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .header-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 40px;
            color: white;
        }

        .header-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-meta {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 14px;
            opacity: 0.9;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            backdrop-filter: blur(10px);
        }

        .status-upcoming {
            background: rgba(25, 118, 210, 0.9);
            color: white;
        }

        .status-ongoing {
            background: rgba(56, 142, 60, 0.9);
            color: white;
        }

        .hackathon-content {
            padding: 40px;
        }

        .deadline-alert {
            background: #fff3e0;
            border-left: 4px solid #f57c00;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .deadline-alert.success {
            background: #e8f5e950;
            border-left-color: #388e3c;
        }

        .deadline-alert.error {
            background: #ffebee;
            border-left-color: #c62828;
        }

        .deadline-alert .material-symbols-outlined {
            font-size: 32px;
            color: #f57c00;
        }

        .deadline-alert.success .material-symbols-outlined {
            color: #388e3c;
        }

        .deadline-alert.error .material-symbols-outlined {
            color: #c62828;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a408c;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title .material-symbols-outlined {
            color: #1a408c;
        }

        .description {
            color: #555;
            line-height: 1.8;
            font-size: 15px;
            white-space: pre-wrap;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
            font-size: 14px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tag {
            padding: 6px 15px;
            background: white;
            border: 2px solid #1a408c;
            color: #1a408c;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .apply-section {
            background: #1a408c;
            padding: 30px;
            border-radius: 15px;
            color: white;
            text-align: center;
        }

        .apply-section h3 {
            font-size: 24px;
            margin-bottom: 15px;
        }

        .apply-section p {
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .btn {
            padding: 15px 40px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: white;
            color: #1a408c;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
        }

        .application-status {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 15px;
            border: 2px solid #28a745;
        }

        .application-status h4 {
            color: #28a745;
            margin-bottom: 10px;
        }

        .download-link {
            color: #1a408c;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .download-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .header-title {
                font-size: 28px;
            }

            .hackathon-header {
                height: 300px;
            }

            .hackathon-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="hackathons.php" class="back-button">
            <span class="material-symbols-outlined">arrow_back</span>
            Back to Hackathons
        </a>

        <div class="hackathon-container">
            <!-- Header with Poster -->
            <div class="hackathon-header">
                <?php if ($hackathon['poster_url']): ?>
                    <img src="<?php echo htmlspecialchars('../' . $hackathon['poster_url']); ?>" alt="<?php echo htmlspecialchars($hackathon['title']); ?>" class="header-poster" onerror="this.style.display='none'">
                <?php endif; ?>

                <span class="status-badge status-<?php echo $hackathon['status']; ?>">
                    <?php echo ucfirst($hackathon['status']); ?>
                </span>

                <div class="header-overlay">
                    <h1 class="header-title"><?php echo htmlspecialchars($hackathon['title']); ?></h1>
                    <div class="header-meta">
                        <div class="meta-item">
                            <span class="material-symbols-outlined">business</span>
                            <?php echo htmlspecialchars($hackathon['organizer']); ?>
                        </div>
                        <div class="meta-item">
                            <span class="material-symbols-outlined">visibility</span>
                            <?php echo number_format($hackathon['view_count'] + 1); ?> views
                        </div>
                        <div class="meta-item">
                            <span class="material-symbols-outlined">groups</span>
                            <?php echo $hackathon['confirmed_applications']; ?> registered
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="hackathon-content">
                <!-- Alerts -->
                <?php if ($hackathon['has_applied']): ?>
                    <div class="deadline-alert success">
                        <span class="material-symbols-outlined">check_circle</span>
                        <div>
                            <strong>Application Submitted!</strong>
                            <p style="margin: 5px 0 0 0; opacity: 0.8;">You have successfully applied for this hackathon.</p>
                        </div>
                    </div>
                <?php elseif ($is_expired): ?>
                    <div class="deadline-alert error">
                        <span class="material-symbols-outlined">event_busy</span>
                        <div>
                            <strong>Registration Closed</strong>
                            <p style="margin: 5px 0 0 0; opacity: 0.8;">The registration deadline has passed.</p>
                        </div>
                    </div>
                <?php elseif ($is_full): ?>
                    <div class="deadline-alert error">
                        <span class="material-symbols-outlined">group_off</span>
                        <div>
                            <strong>Registrations Full</strong>
                            <p style="margin: 5px 0 0 0; opacity: 0.8;">Maximum participant limit reached.</p>
                        </div>
                    </div>
                <?php elseif ($is_deadline_close): ?>
                    <div class="deadline-alert">
                        <span class="material-symbols-outlined">schedule</span>
                        <div>
                            <strong>Deadline Approaching!</strong>
                            <p style="margin: 5px 0 0 0; opacity: 0.8;">
                                Only <?php echo $days_left; ?> day<?php echo $days_left != 1 ? 's' : ''; ?> left to register. Apply now!
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Content Grid -->
                <div class="content-grid">
                    <div class="main-content">
                        <!-- Description -->
                        <div class="section">
                            <h2 class="section-title">
                                <span class="material-symbols-outlined">description</span>
                                About This Hackathon
                            </h2>
                            <div class="description">
                                <?php echo nl2br(htmlspecialchars($hackathon['description'])); ?>
                            </div>
                        </div>

                        <!-- Rules PDF -->
                        <?php if ($hackathon['rules_pdf']): ?>
                            <div class="section">
                                <h2 class="section-title">
                                    <span class="material-symbols-outlined">gavel</span>
                                    Rules & Guidelines
                                </h2>
                                <p style="margin-bottom: 15px; color: #666;">
                                    Download the detailed rules and guidelines document:
                                </p>
                                <a href="<?php echo htmlspecialchars($hackathon['rules_pdf']); ?>"
                                   target="_blank"
                                   class="download-link">
                                    <span class="material-symbols-outlined">download</span>
                                    Download Rules PDF
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Your Application -->
                        <?php if ($user_application): ?>
                            <div class="section">
                                <h2 class="section-title">
                                    <span class="material-symbols-outlined">task_alt</span>
                                    Your Application
                                </h2>
                                <div class="application-status">
                                    <h4>Application Type: <?php echo ucfirst($user_application['application_type']); ?></h4>
                                    <?php if ($user_application['application_type'] === 'team'): ?>
                                        <p><strong>Team Name:</strong> <?php echo htmlspecialchars($user_application['team_name']); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Applied on:</strong> <?php echo date('M d, Y H:i', strtotime($user_application['applied_at'])); ?></p>
                                    <p><strong>Status:</strong> <?php echo ucfirst($user_application['status']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="sidebar">
                        <!-- Key Details -->
                        <div class="info-card">
                            <h3 class="section-title">
                                <span class="material-symbols-outlined">info</span>
                                Key Details
                            </h3>
                            <div class="info-row">
                                <span class="info-label">Start Date</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($hackathon['start_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">End Date</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($hackathon['end_date'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Registration Deadline</span>
                                <span class="info-value"><?php echo date('M d, Y H:i', strtotime($hackathon['registration_deadline'])); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Organizer</span>
                                <span class="info-value"><?php echo htmlspecialchars($hackathon['organizer']); ?></span>
                            </div>
                            <?php if ($hackathon['max_participants']): ?>
                                <div class="info-row">
                                    <span class="info-label">Max Participants</span>
                                    <span class="info-value"><?php echo $hackathon['max_participants']; ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($hackathon['theme']): ?>
                                <div class="info-row">
                                    <span class="info-label">Theme</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hackathon['theme']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- External Link -->
                        <?php if (! empty($hackathon['hackathon_link'])): ?>
                            <div class="info-card" style="text-align: center; background: linear-gradient(135deg, #0c3878 0%, #0a2d5f 100%);">
                                <a href="<?php echo htmlspecialchars($hackathon['hackathon_link']); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; color: white; text-decoration: none; font-weight: 600; font-size: 16px;">
                                    <span class="material-symbols-outlined">open_in_new</span>
                                    Visit External Link
                                </a>
                            </div>
                        <?php endif; ?>
                        <!-- Tags -->
                        <?php if ($hackathon['tags']): ?>
                            <div class="info-card">
                                <h3 class="section-title">
                                    <span class="material-symbols-outlined">local_offer</span>
                                    Tags
                                </h3>
                                <div class="tags-container">
                                    <?php foreach (explode(',', $hackathon['tags']) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Apply Section -->
                        <?php if ($can_apply): ?>
                            <div class="apply-section">
                                <h3>Ready to Participate?</h3>
                                <p>Apply now to secure your spot in this exciting hackathon!</p>
                                <a href="apply_hackathon.php?id=<?php echo $hackathon_id; ?>" class="btn btn-primary">
                                    <span class="material-symbols-outlined">rocket_launch</span>
                                    Apply Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
