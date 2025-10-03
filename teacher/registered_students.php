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

    // Get teacher data
    $username     = $_SESSION['username'];
    $teacher_data = null;

    // Get teacher data from teacher_register table
    $sql  = "SELECT name, faculty_id as employee_id FROM teacher_register WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $teacher_data = $result->fetch_assoc();
    } else {
        // Fallback: use student data structure for now
        $sql  = "SELECT name, regno as employee_id FROM student_register WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $teacher_data = $result->fetch_assoc();
        } else {
            header("Location: ../index.php");
            exit();
        }
    }

    // Pagination settings
    $records_per_page = 15;
    $page             = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $offset           = ($page - 1) * $records_per_page;

    // Search and filter parameters
    $search            = isset($_GET['search']) ? trim($_GET['search']) : '';
    $event_type_filter = isset($_GET['event_type']) ? $_GET['event_type'] : '';
    $department_filter = isset($_GET['department']) ? $_GET['department'] : '';
    $prize_filter      = isset($_GET['prize']) ? $_GET['prize'] : '';

    // Build WHERE clause
    $where_conditions = [];
    $params           = [];
    $types            = '';

    if (! empty($search)) {
        $where_conditions[] = "(sr.name LIKE ? OR sr.regno LIKE ? OR ser.event_name LIKE ?)";
        $search_param       = "%$search%";
        $params[]           = $search_param;
        $params[]           = $search_param;
        $params[]           = $search_param;
        $types .= 'sss';
    }

    if (! empty($event_type_filter)) {
        $where_conditions[] = "ser.event_type = ?";
        $params[]           = $event_type_filter;
        $types .= 's';
    }

    if (! empty($department_filter)) {
        $where_conditions[] = "sr.department = ?";
        $params[]           = $department_filter;
        $types .= 's';
    }

    if (! empty($prize_filter)) {
        if ($prize_filter === 'winner') {
            $where_conditions[] = "ser.prize IN ('First', 'Second', 'Third')";
        } else {
            $where_conditions[] = "ser.prize = ?";
            $params[]           = $prize_filter;
            $types .= 's';
        }
    }

    $where_clause = ! empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    // Get total records for pagination
    $count_sql = "SELECT COUNT(*) as total
                  FROM student_register sr
                  JOIN student_event_register ser ON sr.regno = ser.regno
                  $where_clause";

    if (! empty($params)) {
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
    }

    $total_pages = ceil($total_records / $records_per_page);

    // Get registered students with pagination
    $students_sql = "SELECT sr.name, sr.regno, sr.department, sr.year_of_join, sr.personal_email as email, sr.regno as phone,
                           ser.event_name, ser.event_type, ser.attended_date, ser.prize,
                           ser.organisation as college, ser.state as position
                    FROM student_register sr
                    JOIN student_event_register ser ON sr.regno = ser.regno
                    $where_clause
                    ORDER BY ser.attended_date DESC, ser.id DESC
                    LIMIT ? OFFSET ?";

    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= 'ii';

    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param($types, ...$params);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();

    // Get filter options
    $event_types_result = $conn->query("SELECT DISTINCT event_type FROM student_event_register ORDER BY event_type");
    $departments_result = $conn->query("SELECT DISTINCT department FROM student_register ORDER BY department");

    $stmt->close();
    $students_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .students-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .prize-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .prize-first { background: #ffd700; color: #b8860b; }
        .prize-second { background: #c0c0c0; color: #666; }
        .prize-third { background: #cd7f32; color: #fff; }
        .prize-participation { background: #e8f4fd; color: #3498db; }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
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
                <div class="student-regno">ID:                                                                                             <?php echo htmlspecialchars($teacher_data['employee_id']); ?></div>
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
                        <a href="staff_event_reg.php" class="nav-link">
                            <span class="material-symbols-outlined">event_note</span>
                            Event Registration
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="my_events.php" class="nav-link">
                            <span class="material-symbols-outlined">calendar_month</span>
                            My Events
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
            <div class="welcome-section">
                <h1>👥 Registered Students</h1>
                <p>View and manage student event registrations</p>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $conn->query("SELECT COUNT(DISTINCT regno) FROM student_event_register")->fetch_assoc()['COUNT(DISTINCT regno)']; ?></div>
                    <div class="stat-label">Unique Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) FROM student_event_register WHERE prize IN ('First', 'Second', 'Third')")->fetch_assoc()['COUNT(*)']; ?></div>
                    <div class="stat-label">Prize Winners</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Regno, or Event">
                        </div>

                        <div class="filter-group">
                            <label for="event_type">Event Type</label>
                            <select id="event_type" name="event_type">
                                <option value="">All Event Types</option>
                                <?php while ($type = $event_types_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($type['event_type']); ?>"
                                            <?php echo $event_type_filter === $type['event_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['event_type']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="department">Department</label>
                            <select id="department" name="department">
                                <option value="">All Departments</option>
                                <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>"
                                            <?php echo $department_filter === $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="prize">Prize Filter</label>
                            <select id="prize" name="prize">
                                <option value="">All</option>
                                <option value="winner"                                                                                                             <?php echo $prize_filter === 'winner' ? 'selected' : ''; ?>>Prize Winners</option>
                                <option value="First"                                                                                                           <?php echo $prize_filter === 'First' ? 'selected' : ''; ?>>First Prize</option>
                                <option value="Second"                                                                                                             <?php echo $prize_filter === 'Second' ? 'selected' : ''; ?>>Second Prize</option>
                                <option value="Third"                                                                                                           <?php echo $prize_filter === 'Third' ? 'selected' : ''; ?>>Third Prize</option>
                                <option value="Participation"                                                                                                                           <?php echo $prize_filter === 'Participation' ? 'selected' : ''; ?>>Participation</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <span class="material-symbols-outlined">search</span>
                                Filter
                            </button>
                            <a href="registered_students.php" class="btn btn-secondary">
                                <span class="material-symbols-outlined">clear</span>
                                Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="students-table">
                <div class="table-header">
                    <h3>📋 Student Registrations (<?php echo $total_records; ?> total)</h3>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Info</th>
                                <th>Event Details</th>
                                <th>Date</th>
                                <th>Prize</th>
                                <th>Mode</th>
                                <th>College</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students_result->num_rows > 0): ?>
                                <?php while ($student = $students_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                            <small style="color: #666;">
                                                <?php echo htmlspecialchars($student['regno']); ?><br>
                                                <?php echo htmlspecialchars($student['department']); ?> - Joined<?php echo htmlspecialchars($student['year_of_join']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($student['event_name']); ?></strong><br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($student['event_type']); ?></small>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['attended_date'])); ?></td>
                                        <td>
                                            <?php
                                                $prize       = $student['prize'];
                                                $badge_class = '';
                                                switch (strtolower($prize)) {
                                                    case 'first':$badge_class = 'prize-first';
                                                        break;
                                                    case 'second':$badge_class = 'prize-second';
                                                        break;
                                                    case 'third':$badge_class = 'prize-third';
                                                        break;
                                                    default: $badge_class = 'prize-participation';
                                                }
                                            ?>
                                            <span class="prize-badge<?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($prize); ?>
                                            </span>
                                        </td>
                                        <td>Online/Offline</td>
                                        <td><?php echo htmlspecialchars($student['college']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px;">
                                        <span class="material-symbols-outlined" style="font-size: 48px; color: #ccc;">group_off</span>
                                        <p style="color: #666; margin: 10px 0;">No students found matching your criteria</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>">« Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&event_type=<?php echo urlencode($event_type_filter); ?>&department=<?php echo urlencode($department_filter); ?>&prize=<?php echo urlencode($prize_filter); ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
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
            if (headerMenuIcon) {
                headerMenuIcon.addEventListener('click', toggleSidebar);
            }

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
                    !headerMenuIcon.contains(event.target)) {
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