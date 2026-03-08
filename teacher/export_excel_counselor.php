<?php
session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Verify user is a counselor
$username = $_SESSION['username'];
$sql      = "SELECT id, COALESCE(status, 'teacher') as status FROM teacher_register WHERE username=?";
$stmt     = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0 || $result->fetch_assoc()['status'] !== 'counselor') {
    header("Location: index.php");
    exit();
}
$stmt->close();

// Re-fetch to get ID (result was consumed)
$stmt = $conn->prepare("SELECT id FROM teacher_register WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$counselor_id = $stmt->get_result()->fetch_assoc()['id'];
$stmt->close();

// Get filter parameters from POST
$year       = isset($_POST['year']) && $_POST['year'] !== '' ? $_POST['year'] : null;
$department = isset($_POST['department']) && $_POST['department'] !== '' ? $_POST['department'] : null;
$semester   = isset($_POST['semester']) && $_POST['semester'] !== '' ? $_POST['semester'] : null;
$event_type = isset($_POST['event_type']) && $_POST['event_type'] !== '' ? $_POST['event_type'] : null;
$location   = isset($_POST['location']) && $_POST['location'] !== '' ? $_POST['location'] : null;

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=counselor_reports_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Build dynamic WHERE clause - scoped to counselor's assigned students
$where_conditions = [
    "e.verification_status = 'Approved'",
    "ca.counselor_id = ?",
    "ca.status = 'active'",
];
$bind_types  = "i";
$bind_values = [$counselor_id];

// Add year filter if selected
if ($year !== null) {
    $year_patterns = [$year];
    if (strpos($year, '-') !== false) {
        $year_parts = explode('-', $year);
        if (count($year_parts) == 2) {
            $short_year      = $year_parts[0] . '-' . substr($year_parts[1], -2);
            $year_patterns[] = $short_year;
        }
    }
    $year_conditions    = implode(' OR ', array_fill(0, count($year_patterns), 'e.current_year = ?'));
    $where_conditions[] = "($year_conditions)";
    foreach ($year_patterns as $pattern) {
        $bind_types    .= 's';
        $bind_values[]  = $pattern;
    }
}

// Add department filter if selected
if ($department !== null) {
    $where_conditions[]  = "e.department = ?";
    $bind_types         .= 's';
    $bind_values[]       = $department;
}

// Add semester filter if selected
if ($semester !== null) {
    $where_conditions[]  = "e.semester = ?";
    $bind_types         .= 's';
    $bind_values[]       = $semester;
}

// Add event type filter if selected
if ($event_type !== null) {
    $where_conditions[]  = "e.event_type = ?";
    $bind_types         .= 's';
    $bind_values[]       = $event_type;
}

// Add location filter if selected
if ($location !== null) {
    if ($location === 'tamilnadu') {
        $where_conditions[] = "(LOWER(e.state) = 'tamil nadu' OR LOWER(e.state) = 'tamilnadu')";
    } else {
        $where_conditions[] = "(LOWER(e.state) != 'tamil nadu' AND LOWER(e.state) != 'tamilnadu' AND e.state IS NOT NULL AND e.state != '')";
    }
}

// Build final SQL query with counselor JOIN
$where_clause = implode(' AND ', $where_conditions);
$sql          = "SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
             e.state, e.district, e.event_type, e.event_name, e.start_date, e.end_date, e.no_of_days,
             e.organisation, e.prize, e.prize_amount
       FROM student_event_register e
       JOIN student_register s ON e.regno = s.regno
       INNER JOIN counselor_assignments ca ON e.regno = ca.student_regno
       WHERE $where_clause";

$stmt = $conn->prepare($sql);
$stmt->bind_param($bind_types, ...$bind_values);
$stmt->execute();
$result = $stmt->get_result();

// Output Excel table
echo "<table border='1'>";
echo "<tr>";
echo "<th>S.No</th>";
echo "<th>Reg No</th>";
echo "<th>Name</th>";
echo "<th>Academic Year</th>";
echo "<th>Semester</th>";
echo "<th>Department</th>";
echo "<th>State</th>";
echo "<th>District</th>";
echo "<th>Event Type</th>";
echo "<th>Event Name</th>";
echo "<th>Start Date</th>";
echo "<th>End Date</th>";
echo "<th>No of Days</th>";
echo "<th>Organisation</th>";
echo "<th>Prize</th>";
echo "<th>Prize Amount</th>";
echo "</tr>";

$sno  = 1;
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
    echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
    echo "<td>" . htmlspecialchars($row['no_of_days']) . "</td>";
    echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
    echo "<td>" . htmlspecialchars($row['prize']) . "</td>";
    echo "<td>" . htmlspecialchars($row['prize_amount']) . "</td>";
    echo "</tr>";
}

echo "</table>";

$stmt->close();
$conn->close();
