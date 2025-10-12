<?php
$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
            se.event_poster,
            se.certificates,
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
            '' as event_poster,
            te.certificate_path as certificates,
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
            se.event_poster,
            se.certificates,
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
            '' as event_poster,
            te.certificate_path as certificates,
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
        <th>Has Poster</th>
        <th>Has Certificate</th>
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

        // File availability
        $has_poster      = ! empty($row['event_poster']) ? 'Yes' : 'No';
        $has_certificate = ! empty($row['certificates']) ? 'Yes' : 'No';
        echo "<td>" . $has_poster . "</td>";
        echo "<td>" . $has_certificate . "</td>";

        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='13'>No records found</td></tr>";
}

echo "</table>";

$conn->close();
