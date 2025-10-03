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

    // Get teacher's registered events
    $events_sql = "SELECT ser.topic as event_name, ser.event_type, ser.event_date as attended_date,
                          'Participation' as prize, ser.organisation, ser.sponsors
                   FROM staff_event_reg ser
                   WHERE ser.staff_id = ? OR ser.name = ?
                   ORDER BY ser.event_date DESC";
    $events_stmt = $conn->prepare($events_sql);
    $events_stmt->bind_param("ss", $teacher_data['employee_id'], $teacher_data['name']);
    $events_stmt->execute();
    $events_result = $events_stmt->get_result();

    $stmt->close();
    $events_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - Teacher Dashboard</title>
    <link rel="stylesheet" href="../student/student_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .event-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .event-card:hover {
            transform: translateY(-2px);
        }

        .event-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .event-icon {
            width: 40px;
            height: 40px;
            background: #3498db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .event-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .event-details {
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: #333;
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

        .no-events {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-events .material-symbols-outlined {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .register-btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
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
                        <a href="my_events.php" class="nav-link active">
                            <span class="material-symbols-outlined">calendar_month</span>
                            My Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="registered_students.php" class="nav-link">
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
                <h1>📅 My Events</h1>
                <p>Your registered events and participation history</p>
            </div>

            <?php if ($events_result->num_rows > 0): ?>
                <div class="events-grid">
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <div class="event-card">
                            <div class="event-header">
                                <div class="event-icon">
                                    <span class="material-symbols-outlined">event</span>
                                </div>
                                <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            </div>

                            <div class="event-details">
                                <div class="detail-row">
                                    <span class="detail-label">Type:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($event['attended_date'])); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Mode:</span>
                                    <span class="detail-value">Professional Development</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Organization:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($event['organisation']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Sponsors:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($event['sponsors']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Achievement:</span>
                                    <span class="detail-value">
                                        <?php
                                            $prize       = $event['prize'];
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
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <span class="material-symbols-outlined">event_busy</span>
                    <h3>No Events Registered</h3>
                    <p>You haven't registered for any events yet.</p>
                    <a href="staff_event_reg.php" class="register-btn">
                        <span class="material-symbols-outlined">add</span>
                        Register for Events
                    </a>
                </div>
            <?php endif; ?>
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