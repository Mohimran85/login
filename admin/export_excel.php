<?php
session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters from POST (for reports.php) or GET (for participants.php)
// Check if this is a reports export (POST with year, department, semester, event_type)
$is_report_export = isset($_POST['year']) && isset($_POST['department']) && isset($_POST['semester']) && isset($_POST['event_type']);

if ($is_report_export) {
    // Handle reports export
    $year       = $_POST['year'];
    $department = $_POST['department'];
    $semester   = $_POST['semester'];
    $event_type = $_POST['event_type'];
    $location   = isset($_POST['location']) ? $_POST['location'] : '';

    // Validate location is selected
    if (empty($location)) {
        die("Error: Location filter must be selected");
    }

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=reports_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // For academic year format like "2024-2025", we need to map it back to database values
    $year_patterns = [$year];
    if (strpos($year, '-') !== false) {
        $year_parts = explode('-', $year);
        if (count($year_parts) == 2) {
            // Add short format like "2024-25"
            $short_year      = $year_parts[0] . '-' . substr($year_parts[1], -2);
            $year_patterns[] = $short_year;
        }
    }

    // Build the query with OR conditions for year patterns
    $year_conditions = implode(' OR ', array_fill(0, count($year_patterns), 'e.current_year = ?'));

    // Build location filter condition
    if ($location === 'tamilnadu') {
        $location_condition = " AND e.state = 'Tamil Nadu'";
    } else { // outside
        $location_condition = " AND e.state != 'Tamil Nadu'";
    }

    $stmt = $conn->prepare("SELECT e.id, e.regno, s.name, e.current_year, e.semester, e.department,
                                         e.state, e.district, e.event_type, e.event_name, e.start_date, e.end_date, e.no_of_days,
                                         e.organisation, e.prize, e.prize_amount
                                   FROM student_event_register e
                                   JOIN student_register s ON e.regno = s.regno
                                   WHERE ($year_conditions) AND e.department=? AND e.semester=? AND e.event_type=?$location_condition");

    // Bind parameters: all year patterns + department + semester + event_type
    $bind_types  = str_repeat('s', count($year_patterns)) . 'sss';
    $bind_values = array_merge($year_patterns, [$department, $semester, $event_type]);
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

    $sno = 1;
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
    exit();
}

// Get filter parameters from GET (same as participants.php)
$filter_event_type       = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$filter_department       = isset($_GET['department']) ? $_GET['department'] : '';
$filter_year             = isset($_GET['year']) ? $_GET['year'] : '';
$filter_prize            = isset($_GET['prize']) ? $_GET['prize'] : '';
$filter_participant_type = isset($_GET['participant_type']) ? $_GET['participant_type'] : 'all';
$search_query            = isset($_GET['search']) ? $_GET['search'] : '';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=participants_report_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Build WHERE clause based on filters - same logic as participants.php
$student_where_conditions = [];
$teacher_where_conditions = [];
$params                   = [];
$param_types              = "";

// Build conditions for students
if (! empty($filter_event_type)) {
    $student_where_conditions[] = "se.event_type = ?";
    $teacher_where_conditions[] = "te.event_type = ?";
    $params[]                   = $filter_event_type;
    $params[]                   = $filter_event_type;
    $param_types .= "ss";
}

if (! empty($filter_department)) {
    $student_where_conditions[] = "se.department = ?";
    $teacher_where_conditions[] = "te.department = ?";
    $params[]                   = $filter_department;
    $params[]                   = $filter_department;
    $param_types .= "ss";
}

if (! empty($filter_year)) {
    $student_where_conditions[] = "se.current_year = ?";
    // Skip year filter for teachers as they don't have current_year
    $params[] = $filter_year;
    $param_types .= "s";
}

if (! empty($filter_prize) && $filter_prize !== 'all') {
    if ($filter_prize === 'no_prize') {
        $student_where_conditions[] = "(se.prize IS NULL OR se.prize = '' OR se.prize = 'No Prize')";
    } else {
        $student_where_conditions[] = "se.prize = ?";
        $params[]                   = $filter_prize;
        $param_types .= "s";
    }
}

if (! empty($search_query)) {
    $student_where_conditions[] = "(sr.name LIKE ? OR se.regno LIKE ? OR se.event_name LIKE ?)";
    $teacher_where_conditions[] = "(tr.name LIKE ? OR te.staff_id LIKE ? OR te.topic LIKE ?)";
    $search_param               = "%$search_query%";
    $params[]                   = $search_param;
    $params[]                   = $search_param;
    $params[]                   = $search_param;
    $params[]                   = $search_param;
    $params[]                   = $search_param;
    $params[]                   = $search_param;
    $param_types .= "ssssss";
}

$student_where_clause = ! empty($student_where_conditions) ? "WHERE " . implode(" AND ", $student_where_conditions) : "";
$teacher_where_clause = ! empty($teacher_where_conditions) ? "WHERE " . implode(" AND ", $teacher_where_conditions) : "";

// Build the UNION query based on participant type filter
if ($filter_participant_type === 'student') {
    // Only students
    $sql = "SELECT
            se.id,
            se.regno as reg_id,
            sr.name,
            se.current_year as year_info,
            se.semester,
            se.department,
            se.event_type,
            se.event_name as event_title,
            se.attended_date as event_date,
            se.organisation,
            se.prize,
            se.prize_amount,
            'student' as participant_type
        FROM student_event_register se
        LEFT JOIN student_register sr ON se.regno = sr.regno
        $student_where_clause
        ORDER BY se.attended_date DESC, se.id DESC";
} elseif ($filter_participant_type === 'teacher') {
    // Only teachers
    $sql = "SELECT
            te.id,
            te.staff_id as reg_id,
            tr.name,
            '' as year_info,
            '' as semester,
            te.department,
            te.event_type,
            te.topic as event_title,
            te.event_date,
            te.organisation,
            '' as prize,
            '' as prize_amount,
            'teacher' as participant_type
        FROM staff_event_reg te
        LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
        $teacher_where_clause
        ORDER BY te.event_date DESC, te.id DESC";
} else {
    // Both students and teachers (UNION)
    $sql = "SELECT * FROM (
        SELECT
            se.id,
            se.regno as reg_id,
            sr.name,
            se.current_year as year_info,
            se.semester,
            se.department,
            se.event_type,
            se.event_name as event_title,
            se.attended_date as event_date,
            se.organisation,
            se.prize,
            se.prize_amount,
            'student' as participant_type
        FROM student_event_register se
        LEFT JOIN student_register sr ON se.regno = sr.regno
        $student_where_clause

        UNION ALL

        SELECT
            te.id,
            te.staff_id as reg_id,
            tr.name,
            '' as year_info,
            '' as semester,
            te.department,
            te.event_type,
            te.topic as event_title,
            te.event_date,
            te.organisation,
            '' as prize,
            '' as prize_amount,
            'teacher' as participant_type
        FROM staff_event_reg te
        LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
        $teacher_where_clause
    ) as combined_results
    ORDER BY event_date DESC, id DESC";
}

$stmt = $conn->prepare($sql);
if (! empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1'>";
echo "<tr>
        <th>S.No</th>
        <th>Participant Type</th>
        <th>ID</th>
        <th>Name</th>
        <th>Year</th>
        <th>Department</th>
        <th>Event Type</th>
        <th>Event Name</th>
        <th>Event Date</th>
        <th>Organisation</th>
        <th>Prize</th>
      </tr>";

$sno = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $sno++ . "</td>";

        // Participant type
        $participant_type = ($row['participant_type'] === 'teacher') ? 'Teacher' : 'Student';
        echo "<td>" . htmlspecialchars($participant_type) . "</td>";

        echo "<td>" . htmlspecialchars($row['reg_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['year_info'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['department']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_title']) . "</td>";
        echo "<td>" . htmlspecialchars(date('d-M-Y', strtotime($row['event_date']))) . "</td>";
        echo "<td>" . htmlspecialchars($row['organisation']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prize'] ?: 'No Prize') . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='11'>No records found</td></tr>";
}

echo "</table>";

$conn->close();
