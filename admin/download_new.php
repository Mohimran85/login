<?php
session_start();

if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die("Unauthorized");
}

if (! isset($_GET['id']) || ! isset($_GET['type'])) {
    die("Invalid request");
}

$id   = intval($_GET['id']);
$type = $_GET['type'];

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Get BLOB data
$sql  = "SELECT certificates, event_name, regno FROM student_event_register WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $certData = $row['certificates'];

    if (! $certData) {
        die("No certificate found");
    }

    // Check if it's a file path (short string) or BLOB data (long binary)
    $isFilePath = (strlen($certData) < 500 && preg_match('/^[a-zA-Z0-9_\/\.\-]+$/', $certData));

    if ($isFilePath) {
        // It's a file path - try multiple possible locations
        $possiblePaths = [
            "../" . $certData,         // From admin folder
            "../student/" . $certData, // From admin to student folder
            "../../" . $certData,      // Two levels up
        ];

        $filePath = null;
        foreach ($possiblePaths as $testPath) {
            if (file_exists($testPath)) {
                $filePath = $testPath;
                break;
            }
        }

        if (! $filePath) {
            die("Certificate file not found. Searched in: " . implode(", ", $possiblePaths));
        }

        $fileData = file_get_contents($filePath);
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        $ext      = pathinfo($filePath, PATHINFO_EXTENSION);

    } else {
        // It's BLOB data
        $fileData = $certData;
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileData);

        $ext = 'pdf';
        if (strpos($mimeType, 'jpeg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($mimeType, 'png') !== false) {
            $ext = 'png';
        }
    }

    // Create filename
    $filename = "STU_" . $row['regno'] . "_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['event_name']) . "_certificate." . $ext;

    // Clear any output
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Send headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($fileData));
    header('Cache-Control: no-cache');
    header('Pragma: no-cache');

    // Output the file
    echo $fileData;
} else {
    die("Record not found");
}

$stmt->close();
$conn->close();
