<?php
session_start();
require_once 'config.php';

// Define prize constants
define('PRIZE_FIRST', 'First');
define('PRIZE_SECOND', 'Second');
define('PRIZE_THIRD', 'Third');

// Set secure cache headers
header("Cache-Control: private, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Verify role in session - teacher only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied. Teacher role required.");
}

// Require teacher role
require_teacher_role();

// Get database connection
$conn = get_db_connection();

// Get teacher data
$username = $_SESSION['username'];
$teacher_data = null;

$sql = "SELECT id, name, employee_id, email FROM teacher_register WHERE username=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $teacher_data = $result->fetch_assoc();
    $_SESSION['teacher_id'] = $teacher_data['id'];
} else {
    header("Location: ../index.php");
    exit();
}

$teacher_id = $teacher_data['id'];

// Get filter parameter
$prize_filter = isset($_GET['prize']) ? $_GET['prize'] : 'all';

// Build query to get students registered by this teacher
$students_query = "SELECT 
    sr.id,
    sr.name,
    sr.regno,
    sr.email,
    sr.department,
    COUNT(DISTINCT ser.event_id) as events_participated,
    GROUP_CONCAT(DISTINCT ser.prize ORDER BY ser.prize SEPARATOR ', ') as prizes_won
FROM student_register sr
LEFT JOIN student_event_register ser ON sr.id = ser.student_id
WHERE sr.counselor_id = ?";

// Add prize filter if specified
if ($prize_filter !== 'all') {
    $students_query .= " AND ser.prize = ?";
}

$students_query .= " GROUP BY sr.id ORDER BY sr.name ASC";

// Prepare and execute query
$students_stmt = $conn->prepare($students_query);
if ($prize_filter !== 'all') {
    $students_stmt->bind_param("is", $teacher_id, $prize_filter);
} else {
    $students_stmt->bind_param("i", $teacher_id);
}
$students_stmt->execute();
$students_result = $students_stmt->get_result();

$stmt->close();
$students_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students - Teacher Dashboard</title>
    <!-- google icons -->
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #1e4276;
            --secondary-color: #2d5aa0;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white: #ffffff;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --font-family: "Poppins", sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: #ffffff;
            min-height: 100vh;
            line-height: 1.6;
            color: #333;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        /* Grid Layout */
        .grid-container {
            display: grid;
            grid-template-areas: "sidebar main";
            grid-template-columns: 280px 1fr;
            grid-template-rows: 1fr;
            min-height: 100vh;
            padding-top: 80px;
            transition: all 0.3s ease;
        }

        /* Header */
        .header {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1002;
        }

        .header .menu-icon {
            display: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .header .menu-icon:hover {
            background-color: var(--light-color);
        }

        .header .icon img {
            height: 75px;
            width: 200px;
            border-radius: 10px;
        }

        .header-title {
            flex: 1;
            text-align: center;
        }

        .header-title p {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        /* Sidebar */
        .sidebar {
            grid-area: sidebar;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
            padding: 25px 0;
            position: fixed;
            top: 80px;
            left: 0;
            bottom: 0;
            width: 280px;
            overflow-y: auto;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            z-index: 1001;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--white);
        }

        .close-sidebar {
            display: none;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .close-sidebar:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .student-info {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .student-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .student-regno {
            font-size: 14px;
            opacity: 0.9;
        }

        .nav-menu {
            list-style: none;
            padding: 20px 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: var(--white);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 30px;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left: 4px solid var(--white);
        }

        .nav-link .material-symbols-outlined {
            font-size: 24px;
        }

        /* Main Content */
        .main {
            grid-area: main;
            padding: 30px;
            background: #f8f9fa;
            min-height: calc(100vh - 80px);
        }

        .page-header {
            background: var(--white);
            padding: 25px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #6c757d;
            font-size: 16px;
        }

        /* Filter Section */
        .filter-section {
            background: var(--white);
            padding: 20px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .filter-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 2px solid var(--primary-color);
            background: var(--white);
            color: var(--primary-color);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .filter-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: var(--white);
        }

        /* Students Table */
        .students-container {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .students-table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: var(--white);
        }

        .students-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .students-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .students-table tbody tr {
            transition: all 0.3s ease;
        }

        .students-table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .prize-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px;
        }

        .badge-first {
            background: #ffd700;
            color: #856404;
        }

        .badge-second {
            background: #c0c0c0;
            color: #383d41;
        }

        .badge-third {
            background: #cd7f32;
            color: #fff;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state .material-symbols-outlined {
            font-size: 80px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 10px;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
                grid-template-areas: "main";
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .header .menu-icon {
                display: block;
            }

            .close-sidebar {
                display: block;
            }

            .main {
                padding: 20px;
            }

            .page-header {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .filter-section {
                padding: 15px 20px;
            }

            .students-container {
                padding: 20px;
                overflow-x: auto;
            }

            .students-table {
                font-size: 14px;
            }

            .students-table th,
            .students-table td {
                padding: 10px;
            }
        }

        @media screen and (max-width: 480px) {
            .header-title p {
                font-size: 16px;
            }

            .students-table {
                font-size: 12px;
            }

            .students-table th,
            .students-table td {
                padding: 8px;
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
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-title">Teacher Portal</div>
                <div class="close-sidebar">
                    <span class="material-symbols-outlined">close</span>
                </div>
            </div>

            <div class="student-info">
                <div class="student-name"><?php echo htmlspecialchars($teacher_data['name']); ?></div>
                <div class="student-regno">ID: <?php echo htmlspecialchars($teacher_data['employee_id']); ?></div>
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
                        <a href="digital_signature.php" class="nav-link">
                            <span class="material-symbols-outlined">draw</span>
                            Digital Signature
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link active">
                            <span class="material-symbols-outlined">group</span>
                            Registered Students
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
            <!-- Page Header -->
            <div class="page-header">
                <h1>Registered Students</h1>
                <p>View and manage students assigned to you</p>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <span class="filter-label">Filter by Prize:</span>
                <div class="filter-buttons">
                    <a href="registered_students.php?prize=all" class="filter-btn <?php echo $prize_filter === 'all' ? 'active' : ''; ?>">
                        All Students
                    </a>
                    <a href="registered_students.php?prize=<?php echo PRIZE_FIRST; ?>" class="filter-btn <?php echo $prize_filter === PRIZE_FIRST ? 'active' : ''; ?>">
                        First Prize
                    </a>
                    <a href="registered_students.php?prize=<?php echo PRIZE_SECOND; ?>" class="filter-btn <?php echo $prize_filter === PRIZE_SECOND ? 'active' : ''; ?>">
                        Second Prize
                    </a>
                    <a href="registered_students.php?prize=<?php echo PRIZE_THIRD; ?>" class="filter-btn <?php echo $prize_filter === PRIZE_THIRD ? 'active' : ''; ?>">
                        Third Prize
                    </a>
                </div>
            </div>

            <!-- Students Table -->
            <div class="students-container">
                <h2>Student List</h2>
                <?php if ($students_result->num_rows > 0): ?>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Reg No</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Events</th>
                                <th>Prizes Won</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['regno']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['department']); ?></td>
                                    <td><?php echo $student['events_participated']; ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($student['prizes_won'])) {
                                            $prizes = explode(', ', $student['prizes_won']);
                                            foreach (array_unique($prizes) as $prize) {
                                                $prize = trim($prize);
                                                $badge_class = '';
                                                if ($prize === PRIZE_FIRST) {
                                                    $badge_class = 'badge-first';
                                                } elseif ($prize === PRIZE_SECOND) {
                                                    $badge_class = 'badge-second';
                                                } elseif ($prize === PRIZE_THIRD) {
                                                    $badge_class = 'badge-third';
                                                }
                                                if ($badge_class) {
                                                    echo '<span class="prize-badge ' . $badge_class . '">' . htmlspecialchars($prize) . '</span>';
                                                }
                                            }
                                        } else {
                                            echo '<span style="color: #6c757d;">No prizes</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="material-symbols-outlined">group_off</span>
                        <p>No students found</p>
                        <p style="font-size: 14px; color: #adb5bd;">
                            <?php echo $prize_filter !== 'all' ? 'Try changing the filter or' : ''; ?> No students are currently assigned to you.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Toggle sidebar function
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
            const sidebar = document.getElementById('sidebar');
            const headerMenuIcon = document.querySelector('.header .menu-icon');
            const closeSidebarBtn = document.querySelector('.close-sidebar');

            // Header menu icon functionality
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

            // Close sidebar button functionality
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
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
