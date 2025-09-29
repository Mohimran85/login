<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data for header profile
$username = $_SESSION['username'];
$user_data = null;
$user_type = "";
$tables = ['student_register', 'teacher_register'];

foreach ($tables as $table) {
    $sql = "SELECT name FROM $table WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $user_type = $table === 'student_register' ? 'student' : 'teacher';
        break;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Storage Test</title>
    <link rel="stylesheet" href="./CSS/styles.css">
    <style>
        .test-container {
            margin: 100px 20px 20px 270px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #1e4276;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.exists {
            background-color: #d4edda;
            color: #155724;
        }
        .status.missing {
            background-color: #f8d7da;
            color: #721c24;
        }
        .download-link {
            color: #1e4276;
            text-decoration: none;
            font-weight: bold;
        }
        .download-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="grid-container">
        <!-- Header -->
        <div class="header">
            <div class="menu-icon" onclick="openSidebar()">
                <span class="material-symbols-outlined">menu</span>
            </div>
            <div class="header-logo">
                <img class="logo" src="./asserts/sona_logo.jpg" alt="Sona College Logo" height="60px" width="200"/>
            </div>
            <div class="header-title">
                <p>Event Management Dashboard</p>
            </div>
            <div class="header-profile">
                <div class="profile-info" onclick="navigateToProfile()">
                    <span class="material-symbols-outlined">account_circle</span>
                    <div class="profile-details">
                        <span class="profile-name"><?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?></span>
                        <span class="profile-role"><?php echo ucfirst($user_type); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <aside id="sidebar">
            <div class="sidebar-title">
                <div class="sidebar-band">
                    <h2 style="color: white; padding: 10px">Admin Panel</h2>
                    <span class="material-symbols-outlined" onclick="closeSidebar()">close</span>
                </div>
                <ul class="sidebar-list">
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">dashboard</span>
                        <a href="index.php">Home</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">event</span>
                        <a href="add_event.php">Add Events</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">people</span>
                        <a href="participants.php">Participants</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">bar_chart</span>
                        <a href="reports.php">Reports</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">account_circle</span>
                        <a href="profile.php">Profile</a>
                    </li>
                    <li class="sidebar-list-item active">
                        <span class="material-symbols-outlined">bug_report</span>
                        <a href="test_pdf_storage.php">PDF Test</a>
                    </li>
                    <li class="sidebar-list-item">
                        <span class="material-symbols-outlined">logout</span>
                        <a href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="test-container">
            <h2>PDF Storage Test - Database vs File System</h2>
            <p>This page shows if PDF files are properly stored and accessible for download.</p>

            <?php
            // Query to get all event registrations with their PDF file paths
            $sql = "SELECT 
                        e.id, 
                        e.regno, 
                        s.name, 
                        e.event_name, 
                        e.event_poster, 
                        e.certificates,
                        e.attended_date
                    FROM student_event_register e
                    LEFT JOIN student_register s ON e.regno = s.regno
                    ORDER BY e.id DESC 
                    LIMIT 20";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                echo "<h3>Recent Event Registrations (Last 20)</h3>";
                echo "<table>";
                echo "<thead>";
                echo "<tr>";
                echo "<th>ID</th>";
                echo "<th>Reg No</th>";
                echo "<th>Student Name</th>";
                echo "<th>Event Name</th>";
                echo "<th>Event Poster</th>";
                echo "<th>Poster Status</th>";
                echo "<th>Certificate</th>";
                echo "<th>Certificate Status</th>";
                echo "<th>Date</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
                    
                    // Event Poster
                    if (!empty($row['event_poster'])) {
                        $posterPath = "../" . $row['event_poster'];
                        $posterExists = file_exists($posterPath);
                        echo "<td>";
                        if ($posterExists) {
                            echo "<a href='download.php?id=" . $row['id'] . "&type=poster' class='download-link' target='_blank'>Download</a>";
                        } else {
                            echo "<span style='color: red;'>File Missing</span>";
                        }
                        echo "<br><small>" . htmlspecialchars($row['event_poster']) . "</small>";
                        echo "</td>";
                        echo "<td><span class='status " . ($posterExists ? 'exists' : 'missing') . "'>" . ($posterExists ? 'EXISTS' : 'MISSING') . "</span></td>";
                    } else {
                        echo "<td><span style='color: gray;'>No Poster</span></td>";
                        echo "<td><span class='status missing'>NO FILE</span></td>";
                    }
                    
                    // Certificate
                    if (!empty($row['certificates'])) {
                        $certPath = "../" . $row['certificates'];
                        $certExists = file_exists($certPath);
                        echo "<td>";
                        if ($certExists) {
                            echo "<a href='download.php?id=" . $row['id'] . "&type=certificate' class='download-link' target='_blank'>Download</a>";
                        } else {
                            echo "<span style='color: red;'>File Missing</span>";
                        }
                        echo "<br><small>" . htmlspecialchars($row['certificates']) . "</small>";
                        echo "</td>";
                        echo "<td><span class='status " . ($certExists ? 'exists' : 'missing') . "'>" . ($certExists ? 'EXISTS' : 'MISSING') . "</span></td>";
                    } else {
                        echo "<td><span style='color: gray;'>No Certificate</span></td>";
                        echo "<td><span class='status missing'>NO FILE</span></td>";
                    }
                    
                    echo "<td>" . htmlspecialchars($row['attended_date']) . "</td>";
                    echo "</tr>";
                }
                
                echo "</tbody>";
                echo "</table>";
                
                // Summary statistics
                $stats_sql = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(event_poster) as records_with_poster,
                    COUNT(certificates) as records_with_certificate
                FROM student_event_register 
                WHERE event_poster IS NOT NULL OR certificates IS NOT NULL";
                
                $stats_result = $conn->query($stats_sql);
                if ($stats_result && $stats_row = $stats_result->fetch_assoc()) {
                    echo "<div style='margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;'>";
                    echo "<h4>Database Statistics:</h4>";
                    echo "<p><strong>Total Records:</strong> " . $stats_row['total_records'] . "</p>";
                    echo "<p><strong>Records with Event Poster:</strong> " . $stats_row['records_with_poster'] . "</p>";
                    echo "<p><strong>Records with Certificate:</strong> " . $stats_row['records_with_certificate'] . "</p>";
                    echo "</div>";
                }
                
            } else {
                echo "<p style='color: orange;'>No event registrations found in the database.</p>";
            }
            
            $conn->close();
            ?>
        </div>
    </div>

    <script src="./JS/scripts.js"></script>
    <script>
        function navigateToProfile() {
            window.location.href = 'profile.php';
        }
    </script>
</body>
</html>