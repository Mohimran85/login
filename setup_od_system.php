<?php
// Database setup script for OD Request system
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "event_management_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create od_requests table
$create_table_sql = "CREATE TABLE IF NOT EXISTS od_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_regno VARCHAR(50) NOT NULL,
    counselor_id INT NOT NULL,
    event_name VARCHAR(255) NOT NULL,
    event_description TEXT NOT NULL,
    event_location VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    counselor_remarks TEXT DEFAULT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_date TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (student_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES teacher_register(id) ON DELETE CASCADE,
    INDEX idx_student_regno (student_regno),
    INDEX idx_counselor_id (counselor_id),
    INDEX idx_status (status)
)";

if ($conn->query($create_table_sql) === TRUE) {
    echo "✅ od_requests table created successfully<br>";
} else {
    echo "❌ Error creating od_requests table: " . $conn->error . "<br>";
}

// Check if counselor_assignments table exists, if not create it
$create_counselor_table_sql = "CREATE TABLE IF NOT EXISTS counselor_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    student_regno VARCHAR(50) NOT NULL,
    assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teacher_register(id) ON DELETE CASCADE,
    FOREIGN KEY (student_regno) REFERENCES student_register(regno) ON DELETE CASCADE,
    UNIQUE KEY unique_student_assignment (student_regno),
    INDEX idx_teacher_id (teacher_id)
)";

if ($conn->query($create_counselor_table_sql) === TRUE) {
    echo "✅ counselor_assignments table verified/created successfully<br>";
} else {
    echo "❌ Error creating counselor_assignments table: " . $conn->error . "<br>";
}

$conn->close();
echo "<br>🎉 Database setup completed! OD Request system is ready to use.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - OD Request System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .setup-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 600px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            cursor: pointer;
        }
        .btn:hover {
            background: #5a6fd8;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🗄️ Database Setup Complete</h1>
        <p>The OD Request system database tables have been set up successfully!</p>
        
        <div style="margin-top: 30px;">
            <a href="../admin/index.php" class="btn">Go to Admin Panel</a>
            <a href="../student/index.php" class="btn">Go to Student Portal</a>
            <a href="../teacher/index.php" class="btn">Go to Teacher Portal</a>
        </div>
        
        <div style="margin-top: 20px; font-size: 14px; color: #666;">
            <p><strong>Next Steps:</strong></p>
            <p>1. Assign students to class counselors in the admin panel</p>
            <p>2. Students can submit OD requests</p>
            <p>3. Counselors can approve/reject requests</p>
            <p>4. Students with approved OD can register for events</p>
        </div>
    </div>
</body>
</html>