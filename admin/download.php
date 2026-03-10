<?php
// Clean any output buffer to prevent file corruption
ob_start();

session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_end_clean();
    header("Location: ../index.php");
    exit();
}

if (! isset($_GET['id']) || ! isset($_GET['type'])) {
    ob_end_clean();
    die("Invalid request");
}

$id               = intval($_GET['id']);
$type             = $_GET['type'];                          // 'poster', 'certificate', 'teacher_poster', 'teacher_certificate'
$participant_type = $_GET['participant_type'] ?? 'student'; // 'student' or 'teacher'

// Validate participant_type
$valid_participant_types = ['student', 'teacher'];
if (! in_array($participant_type, $valid_participant_types)) {
    $participant_type = 'student';
}

// Validate type parameter
$valid_types = ['poster', 'certificate', 'teacher_poster', 'teacher_certificate'];
if (! in_array($type, $valid_types)) {
    ob_end_clean();
    die("Invalid file type");
}

require_once __DIR__ . '/../includes/db_config.php';
$conn = get_db_connection();

// Select the file data based on type and participant type
if ($participant_type === 'teacher') {
    if ($type == 'teacher_poster' || $type == 'poster') {
        $sql          = "SELECT certificate_path as file_data, topic as event_name, staff_id as reg_id FROM staff_event_reg WHERE id = ?";
        $type_display = 'poster';
        $is_blob      = false; // teacher files might be paths
    } else if ($type == 'teacher_certificate' || $type == 'certificate') {
        $sql          = "SELECT certificate_path as file_data, topic as event_name, staff_id as reg_id FROM staff_event_reg WHERE id = ?";
        $type_display = 'certificate';
        $is_blob      = false;
    }
} else {
    // Student downloads - stored as BLOBs
    if ($type == 'poster') {
        $sql          = "SELECT event_poster as file_data, event_name, regno as reg_id FROM student_event_register WHERE id = ?";
        $type_display = 'poster';
        $is_blob      = true;
    } else if ($type == 'certificate') {
        $sql          = "SELECT certificates as file_data, event_name, regno as reg_id FROM student_event_register WHERE id = ?";
        $type_display = 'certificate';
        $is_blob      = true;
    }
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $fileData  = $row['file_data'];
    $eventName = $row['event_name'];
    $regId     = $row['reg_id'];

    if ($fileData) {
        if ($is_blob) {
            // Handle BLOB data (student certificates and posters)
            $finfo       = new finfo(FILEINFO_MIME_TYPE);
            $contentType = $finfo->buffer($fileData);

                                // Determine file extension from mime type
            $extension = 'pdf'; // default
            if (strpos($contentType, 'image/jpeg') !== false) {
                $extension = 'jpg';
            } elseif (strpos($contentType, 'image/png') !== false) {
                $extension = 'png';
            } elseif (strpos($contentType, 'application/pdf') !== false) {
                $extension = 'pdf';
            }

            // Create a descriptive filename
            $participantPrefix = $participant_type === 'teacher' ? 'STAFF' : 'STU';
            $downloadFilename  = $participantPrefix . "_" . $regId . "_" . str_replace(" ", "_", $eventName) . "_" . $type_display . "." . $extension;

            // Clean output buffer before sending file
            ob_end_clean();

            // Set headers for download
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
            header('Content-Length: ' . strlen($fileData));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');

            // Output file content
            echo $fileData;
            exit();

        } else {
            // Handle file path (teacher files stored as paths)
            if (file_exists("../" . $fileData)) {
                $originalFilename = basename($fileData);
                $fileExtension    = pathinfo($originalFilename, PATHINFO_EXTENSION);

                // Create a descriptive filename
                $participantPrefix = $participant_type === 'teacher' ? 'STAFF' : 'STU';
                $downloadFilename  = $participantPrefix . "_" . $regId . "_" . str_replace(" ", "_", $eventName) . "_" . $type_display . "." . $fileExtension;

                // Get file size
                $fileSize = filesize("../" . $fileData);

                                                           // Determine content type based on file extension
                $contentType = 'application/octet-stream'; // default
                switch (strtolower($fileExtension)) {
                    case 'pdf':
                        $contentType = 'application/pdf';
                        break;
                    case 'jpg':
                    case 'jpeg':
                        $contentType = 'image/jpeg';
                        break;
                    case 'png':
                        $contentType = 'image/png';
                        break;
                    case 'gif':
                        $contentType = 'image/gif';
                        break;
                    case 'doc':
                        $contentType = 'application/msword';
                        break;
                    case 'docx':
                        $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        break;
                }

                // Clean output buffer before sending file
                ob_end_clean();

                // Set headers for download
                header('Content-Type: ' . $contentType);
                header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
                header('Content-Length: ' . $fileSize);
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');

                // Output file content
                readfile("../" . $fileData);
                exit();
            } else {
                ob_end_clean();
                echo "<script>alert('File not found on server: ' + " . json_encode(basename($fileData)) . "); window.close();</script>";
                exit();
            }
        }
    } else {
        ob_end_clean();
        echo "<script>alert('No " . $type_display . " file found for this record.'); window.close();</script>";
        exit();
    }
} else {
    ob_end_clean();
    echo "<script>alert('Record not found.'); window.close();</script>";
    exit();
}

$stmt->close();
$conn->close();
exit();
