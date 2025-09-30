<?php
    session_start();

    // Check if user is logged in
    if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: index.php");
        exit();
    }

    $conn = new mysqli("localhost", "root", "", "event_management_system");
    if ($conn->connect_error) {
        die("Connection failed: " . htmlspecialchars($conn->connect_error));
    }

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
    $sort_by           = isset($_GET['sort']) ? $_GET['sort'] : 'attended_date';
    $sort_order        = isset($_GET['order']) ? $_GET['order'] : 'DESC';

    // Build query with filters
    $where_conditions = ["regno = ?"];
    $params           = [$regno];
    $param_types      = "s";

    if (! empty($search)) {
        $where_conditions[] = "(event_name LIKE ? OR organisation LIKE ?)";
        $params[]           = "%$search%";
        $params[]           = "%$search%";
        $param_types .= "ss";
    }

    if (! empty($event_type_filter)) {
        $where_conditions[] = "event_type = ?";
        $params[]           = $event_type_filter;
        $param_types .= "s";
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
    $allowed_sorts = ['attended_date', 'event_name', 'event_type', 'prize'];
    if (! in_array($sort_by, $allowed_sorts)) {
        $sort_by = 'attended_date';
    }
    $sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

    // Get participations
    $sql  = "SELECT * FROM student_event_register WHERE $where_clause ORDER BY $sort_by $sort_order";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $participations = $stmt->get_result();

    // Get statistics
    $stats_sql = "SELECT
    COUNT(*) as total_events,
    COUNT(CASE WHEN prize IS NOT NULL AND prize != '' AND prize != 'Participation' THEN 1 END) as events_won,
    COUNT(CASE WHEN prize = 'First' THEN 1 END) as first_prizes,
    COUNT(CASE WHEN prize = 'Second' THEN 1 END) as second_prizes,
    COUNT(CASE WHEN prize = 'Third' THEN 1 END) as third_prizes
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

    $user_stmt->close();
    $stmt->close();
    $stats_stmt->close();
    $types_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Participations - Event Management System</title>
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

        .participations-title {
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
            border-color: var(--primary-color);
        }

        .filter-btn {
            background: var(--primary-color);
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
            background: var(--secondary-color);
        }

        .participations-list {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
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
            color: var(--primary-color);
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
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #856404;
        }

        .prize-second {
            background: linear-gradient(135deg, #c0c0c0 0%, #e8e8e8 100%);
            color: #495057;
        }

        .prize-third {
            background: linear-gradient(135deg, #cd7f32 0%, #daa520 100%);
            color: #fff;
        }

        .prize-participation {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
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
            background: #28a745;
            color: white;
        }

        .btn-download:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #007bff;
            color: white;
        }

        .btn-view:hover {
            background: #0056b3;
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
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .empty-action:hover {
            background: var(--secondary-color);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .main {
                width: 100% !important;
                padding: 20px 15px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .event-details {
                grid-template-columns: 1fr;
            }

            .participations-header {
                padding: 20px;
            }

            .participations-title {
                font-size: 24px;
            }

            .participation-item {
                padding: 20px 15px;
            }

            .filters-section {
                padding: 20px 15px;
            }

            .event-meta {
                flex-direction: column;
                gap: 10px;
            }

            .actions-section {
                flex-direction: column;
                gap: 10px;
            }

            .action-btn {
                text-align: center;
                justify-content: center;
                display: flex;
                align-items: center;
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
                <img src="../asserts/images/Sona Logo.png" alt="Sona College Logo">
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
                                <option value="won"                                                                                                                                                          <?php echo($prize_filter === 'won') ? 'selected' : ''; ?>>Events Won</option>
                                <option value="participation"                                                                                                                                                                                        <?php echo($prize_filter === 'participation') ? 'selected' : ''; ?>>Participation Only</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort" class="filter-select">
                                <option value="attended_date"                                                                                                                                                                                        <?php echo($sort_by === 'attended_date') ? 'selected' : ''; ?>>Date</option>
                                <option value="event_name"                                                                                                                                                                               <?php echo($sort_by === 'event_name') ? 'selected' : ''; ?>>Event Name</option>
                                <option value="event_type"                                                                                                                                                                               <?php echo($sort_by === 'event_type') ? 'selected' : ''; ?>>Event Type</option>
                                <option value="prize"                                                                                                                                                                <?php echo($sort_by === 'prize') ? 'selected' : ''; ?>>Prize</option>
                            </select>
                        </div>

                        <button type="submit" class="filter-btn">
                            <span class="material-symbols-outlined">search</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Participations List -->
            <div class="participations-list">
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
                                    <?php echo date('M d, Y', strtotime($participation['attended_date'])); ?>
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
                                        🏆                                                                                                                                     <?php echo htmlspecialchars($participation['prize']); ?>
                                        <?php if (! empty($participation['prize_amount'])): ?>
                                            - ₹<?php echo htmlspecialchars($participation['prize_amount']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="event-details">
                                <div class="detail-group">
                                    <div class="detail-label">Department</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($participation['department']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Year & Semester</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($participation['current_year'] . ' - ' . $participation['semester']); ?></div>
                                </div>
                                <div class="detail-group">
                                    <div class="detail-label">Location</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($participation['district'] . ', ' . $participation['state']); ?></div>
                                </div>
                            </div>

                            <div class="actions-section">
                                <?php if (! empty($participation['certificates'])): ?>
                                    <a href="<?php echo htmlspecialchars($participation['certificates']); ?>"
                                       class="action-btn btn-download" target="_blank">
                                        <span class="material-symbols-outlined">download</span>
                                        Certificate
                                    </a>
                                <?php endif; ?>
                                <?php if (! empty($participation['event_poster'])): ?>
                                    <a href="<?php echo htmlspecialchars($participation['event_poster']); ?>"
                                       class="action-btn btn-view" target="_blank">
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
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>