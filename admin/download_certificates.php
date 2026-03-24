<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Check if ZipArchive class is available
if (! class_exists('ZipArchive')) {
    die('Error: ZIP extension is not enabled in PHP. Please enable it in php.ini by uncommenting "extension=zip" and restart Apache.');
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Get filter parameters from POST
$year        = isset($_POST['year']) && $_POST['year'] !== '' ? $_POST['year'] : null;
$department  = isset($_POST['department']) && $_POST['department'] !== '' ? $_POST['department'] : null;
$semester    = isset($_POST['semester']) && $_POST['semester'] !== '' ? $_POST['semester'] : null;
$event_type  = isset($_POST['event_type']) && $_POST['event_type'] !== '' ? $_POST['event_type'] : null;
$location    = isset($_POST['location']) && $_POST['location'] !== '' ? $_POST['location'] : null;
$start_month = isset($_POST['start_month']) && $_POST['start_month'] !== '' ? $_POST['start_month'] : null;
$end_month   = isset($_POST['end_month']) && $_POST['end_month'] !== '' ? $_POST['end_month'] : null;

// Build dynamic WHERE clause (same logic as reports.php)
$where_conditions = ["e.verification_status = 'Approved'"];
$bind_types       = "";
$bind_values      = [];

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

// Add month filter if selected
if ($start_month !== null && $end_month !== null) {
    $where_conditions[]  = "MONTH(e.start_date) BETWEEN ? AND ?";
    $bind_types         .= 'ii';
    $bind_values[]       = $start_month;
    $bind_values[]       = $end_month;
} elseif ($start_month !== null) {
    $where_conditions[]  = "MONTH(e.start_date) = ?";
    $bind_types         .= 'i';
    $bind_values[]       = $start_month;
} elseif ($end_month !== null) {
    $where_conditions[]  = "MONTH(e.start_date) = ?";
    $bind_types         .= 'i';
    $bind_values[]       = $end_month;
}

// Build final SQL query - only get records with certificates
$where_clause = implode(' AND ', $where_conditions);
$sql          = "SELECT e.id, e.regno, s.name, e.event_name, e.certificates
       FROM student_event_register e
       JOIN student_register s ON e.regno = s.regno
       WHERE $where_clause AND e.certificates IS NOT NULL AND e.certificates != ''";

$stmt = $conn->prepare($sql);

// Bind parameters only if there are any
if (! empty($bind_values)) {
    $stmt->bind_param($bind_types, ...$bind_values);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Create a temporary directory for storing files
    $temp_dir = sys_get_temp_dir() . '/certificates_' . bin2hex(random_bytes(8));
    if (! mkdir($temp_dir, 0700, true)) {
        echo "Error: Could not create temporary directory";
        exit();
    }

    $file_count = 0;

    while ($row = $result->fetch_assoc()) {
        // Clean the name and regno for filename (remove special characters)
        $clean_name  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['name']);
        $clean_regno = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['regno']);
        $clean_event = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['event_name']);

        $certData = $row['certificates'];

        // Check if it's a file path or BLOB data
        $isFilePath = (strlen($certData) < 500 && preg_match('/^[a-zA-Z0-9_\/\.\-]+$/', $certData));

        if ($isFilePath) {
            // It's a file path - try multiple possible locations
            $possiblePaths = [
                "../" . $certData,
                "../student/" . $certData,
                "../../" . $certData,
            ];

            $actualFile = null;
            foreach ($possiblePaths as $testPath) {
                if (file_exists($testPath)) {
                    $actualFile = $testPath;
                    break;
                }
            }

            if (! $actualFile) {
                continue; // Skip this file if not found
            }

            $fileData  = file_get_contents($actualFile);
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($actualFile);
            $extension = pathinfo($actualFile, PATHINFO_EXTENSION);

        } else {
            // It's BLOB data
            $fileData  = $certData;
            $finfo     = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($fileData);

            $extension = 'pdf'; // default
            if (strpos($mime_type, 'image/jpeg') !== false) {
                $extension = 'jpg';
            } elseif (strpos($mime_type, 'image/png') !== false) {
                $extension = 'png';
            } elseif (strpos($mime_type, 'application/pdf') !== false) {
                $extension = 'pdf';
            }
        }

        // Create filename: Name_RegNo_EventName.extension (unique for each event)
        $filename = $clean_name . '_' . $clean_regno . '_' . $clean_event . '.' . $extension;
        $filepath = $temp_dir . '/' . $filename;

        // Save the certificate to file
        file_put_contents($filepath, $fileData);
        $file_count++;
    }

    if ($file_count > 0) {
        // Create ZIP file
        $zip_filename = 'certificates_' . date('Y-m-d_His') . '.zip';
        $zip_filepath = sys_get_temp_dir() . '/' . $zip_filename;

        $zip = new ZipArchive();
        if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add all files to ZIP
            $files = scandir($temp_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $zip->addFile($temp_dir . '/' . $file, $file);
                }
            }
            $zip->close();

            // Send ZIP file to browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_filepath));
            readfile($zip_filepath);

            // Clean up temporary files
            $files = scandir($temp_dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    unlink($temp_dir . '/' . $file);
                }
            }
            rmdir($temp_dir);
            unlink($zip_filepath);
        } else {
            echo "Error: Could not create ZIP file";
        }
    } else {
        echo "No certificates found for the selected filters.";
    }
} else {
    echo "No records with certificates found for the selected filters.";
}

$stmt->close();
$conn->close();
