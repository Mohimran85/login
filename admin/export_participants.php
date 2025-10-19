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

// Get export format
$format      = isset($_GET['format']) ? $_GET['format'] : 'csv';
$export_type = isset($_GET['export_type']) ? $_GET['export_type'] : 'detailed';

// Get filter parameters (same as participants.php)
$filter_event_type       = isset($_GET['event_type']) ? $_GET['event_type'] : '';
$filter_department       = isset($_GET['department']) ? $_GET['department'] : '';
$filter_year             = isset($_GET['year']) ? $_GET['year'] : '';
$filter_prize            = isset($_GET['prize']) ? $_GET['prize'] : '';
$filter_participant_type = isset($_GET['participant_type']) ? $_GET['participant_type'] : 'all';
$search_query            = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause based on filters
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
    $params[]                   = $filter_year;
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
            sr.personal_email,
            sr.degree,
            'student' as participant_type
        FROM student_event_register se
        LEFT JOIN student_register sr ON se.regno = sr.regno
        $student_where_clause
        ORDER BY se.attended_date DESC, se.id DESC";
} elseif ($filter_participant_type === 'teacher') {
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
            tr.email as personal_email,
            '' as degree,
            'teacher' as participant_type
        FROM staff_event_reg te
        LEFT JOIN teacher_register tr ON te.staff_id = tr.faculty_id
        $teacher_where_clause
        ORDER BY te.event_date DESC, te.id DESC";
} else {
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
            sr.personal_email,
            sr.degree,
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
            tr.email as personal_email,
            '' as degree,
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

// Set appropriate headers based on format
if ($format === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=participants_export_" . date('Y-m-d_H-i-s') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Start HTML table for Excel
    echo "<table border='1'>";
} else {
    // CSV format
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="participants_export_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    $output = fopen('php://output', 'w');
}

// Prepare headers based on export type
if ($export_type === 'summary') {
    $headers = ['S.No', 'Participant Type', 'ID', 'Name', 'Department', 'Event Type', 'Event Name', 'Event Date', 'Prize'];
} else {
    // Detailed export
    $headers = ['S.No', 'Participant Type', 'ID', 'Name', 'Email', 'Year/Role', 'Department', 'Event Type', 'Event Name', 'Event Date', 'Organisation', 'Prize', 'Prize Amount', 'Has Poster', 'Has Certificate'];
}

// Output headers
if ($format === 'excel') {
    echo "<tr>";
    foreach ($headers as $header) {
        echo "<th>" . htmlspecialchars($header) . "</th>";
    }
    echo "</tr>";
} else {
    fputcsv($output, $headers);
}

// Output data
$sno = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($export_type === 'summary') {
            $data = [
                $sno++,
                ($row['participant_type'] === 'teacher') ? 'Teacher' : 'Student',
                $row['reg_id'],
                $row['name'] ?? 'N/A',
                $row['department'],
                $row['event_type'],
                $row['event_title'],
                date('d-M-Y', strtotime($row['event_date'])),
                $row['prize'] ?: 'No Prize',
            ];
        } else {
            // Detailed export
            $data = [
                $sno++,
                ($row['participant_type'] === 'teacher') ? 'Teacher' : 'Student',
                $row['reg_id'],
                $row['name'] ?? 'N/A',
                $row['personal_email'] ?? 'N/A',
                $row['year_info'] ?: ($row['participant_type'] === 'teacher' ? 'Faculty' : 'N/A'),
                $row['department'],
                $row['event_type'],
                $row['event_title'],
                date('d-M-Y', strtotime($row['event_date'])),
                $row['organisation'],
                $row['prize'] ?: 'No Prize',
                $row['prize_amount'] ?: '',
                ! empty($row['event_poster']) ? 'Yes' : 'No',
                ! empty($row['certificates']) ? 'Yes' : 'No',
            ];
        }

        if ($format === 'excel') {
            echo "<tr>";
            foreach ($data as $cell) {
                echo "<td>" . htmlspecialchars($cell) . "</td>";
            }
            echo "</tr>";
        } else {
            fputcsv($output, $data);
        }
    }
} else {
    if ($format === 'excel') {
        echo "<tr><td colspan='" . count($headers) . "'>No records found</td></tr>";
    } else {
        fputcsv($output, array_fill(0, count($headers), 'No records found'));
    }
}

// Add export metadata
if ($format === 'csv') {
    fputcsv($output, []);
    fputcsv($output, ['=== EXPORT METADATA ===']);
    fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Export Type', $export_type]);
    fputcsv($output, ['Format', $format]);
    fputcsv($output, ['Filters Applied', ! empty(array_filter([$filter_event_type, $filter_department, $filter_year, $filter_prize, $search_query])) ? 'Yes' : 'No']);
    if (! empty($search_query)) {
        fputcsv($output, ['Search Query', $search_query]);
    }
}

// Close output
if ($format === 'excel') {
    echo "</table>";
} else {
    fclose($output);
}

$conn->close();
exit();
