<?php
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$year = $_POST['year'];
$department = $_POST['department'];
$semester = $_POST['semester'];
$event_type = $_POST['event_type'];

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=event_report.xls");
header("Pragma: no-cache");
header("Expires: 0");

$sql = "SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
               e.state, e.district, e.event_type, e.event_name, e.attended_date,
               e.organisation, e.prize, e.prize_amount, e.event_poster, e.certificates
        FROM student_event_register e
        JOIN student_register s ON e.regno = s.regno
        WHERE e.current_year='$year' 
          AND e.department='$department' 
          AND e.semester='$semester' 
          AND e.event_type='$event_type'";

$result = $conn->query($sql);

echo "<table border='1'>";
echo "<tr>
        <th>S.No</th>
        <th>Reg No</th>
        <th>Name</th>
        <th>Current Year</th>
        <th>Semester</th>
        <th>Department</th>
        <th>State</th>
        <th>District</th>
        <th>Event Type</th>
        <th>Event Name</th>
        <th>Attended Date</th>
        <th>Organisation</th>
        <th>Prize</th>
        <th>Prize Amount</th>
        <th>Event Poster</th>
        <th>Certificates</th>
      </tr>";

$sno = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $sno++ . "</td>";
        echo "<td>" . htmlspecialchars($row['regno']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['current_year']) . "</td>";
        echo "<td>" . htmlspecialchars($row['semester']) . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['state']) . "</td>";
        echo "<td>" . htmlspecialchars($row['district']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['attended_date']) . "</td>";
        echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prize_amount']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_poster']) . "</td>";
        echo "<td>" . htmlspecialchars($row['certificates']) . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='16'>No records found</td></tr>";
}

echo "</table>";

$conn->close();
?>
