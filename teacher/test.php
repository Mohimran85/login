<?php
// Simple test to verify database structure and connections
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Database Connection Test</h2>";
echo "<p>✅ Connected to database successfully!</p>";

echo "<h3>Testing student_register table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM student_register");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<p>✅ student_register table has {$count} records</p>";
} else {
    echo "<p>❌ Error accessing student_register table: " . $conn->error . "</p>";
}

echo "<h3>Testing student_event_register table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM student_event_register");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<p>✅ student_event_register table has {$count} records</p>";
} else {
    echo "<p>❌ Error accessing student_event_register table: " . $conn->error . "</p>";
}

echo "<h3>Testing staff_event_reg table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM staff_event_reg");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<p>✅ staff_event_reg table has {$count} records</p>";
} else {
    echo "<p>❌ Error accessing staff_event_reg table: " . $conn->error . "</p>";
}

echo "<h3>Testing teacher_register table:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM teacher_register");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "<p>✅ teacher_register table has {$count} records</p>";
} else {
    echo "<p>❌ Error accessing teacher_register table: " . $conn->error . "</p>";
}

echo "<h3>Testing JOIN query (student registrations):</h3>";
$result = $conn->query("SELECT sr.name, sr.regno, sr.department, sr.year_of_join,
                              ser.event_name, ser.event_type, ser.attended_date, ser.prize, ser.organisation
                       FROM student_register sr
                       JOIN student_event_register ser ON sr.regno = ser.regno
                       LIMIT 3");
if ($result) {
    echo "<p>✅ JOIN query successful! Sample data:</p>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Name</th><th>Regno</th><th>Department</th><th>Year</th><th>Event</th><th>Prize</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['year_of_join']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Error with JOIN query: " . $conn->error . "</p>";
}

$conn->close();
echo "<p style='margin-top: 20px; color: green;'><strong>✅ All tests completed!</strong></p>";
echo "<p><a href='index.php'>🏠 Go to Teacher Dashboard</a></p>";
