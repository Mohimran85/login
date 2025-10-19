<?php
session_start();

// Check if user is logged in
if (! isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

if (! isset($_GET['id']) || ! isset($_GET['type'])) {
    die("Invalid request");
}

$id               = intval($_GET['id']);
$type             = $_GET['type'];                          // 'poster', 'certificate', 'teacher_poster', 'teacher_certificate'
$participant_type = $_GET['participant_type'] ?? 'student'; // 'student' or 'teacher'

// Validate type parameter
$valid_types = ['poster', 'certificate', 'teacher_poster', 'teacher_certificate'];
if (! in_array($type, $valid_types)) {
    die("Invalid file type");
}

$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the file path based on type and participant type
if ($participant_type === 'teacher') {
    if ($type == 'teacher_poster' || $type == 'poster') {
        // For teachers, we might not have separate poster field, but let's check if they have any file field
        $sql          = "SELECT certificate_path as file_path, topic as event_name, staff_id as reg_id FROM staff_event_reg WHERE id = ?";
        $type_display = 'poster';
    } else if ($type == 'teacher_certificate' || $type == 'certificate') {
        $sql          = "SELECT certificate_path as file_path, topic as event_name, staff_id as reg_id FROM staff_event_reg WHERE id = ?";
        $type_display = 'certificate';
    }
} else {
    // Student downloads
    if ($type == 'poster') {
        $sql          = "SELECT event_poster as file_path, event_name, regno as reg_id FROM student_event_register WHERE id = ?";
        $type_display = 'poster';
    } else if ($type == 'certificate') {
        $sql          = "SELECT certificates as file_path, event_name, regno as reg_id FROM student_event_register WHERE id = ?";
        $type_display = 'certificate';
    }
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $filePath  = $row['file_path'];
    $eventName = $row['event_name'];
    $regId     = $row['reg_id'];

    if ($filePath && file_exists("../" . $filePath)) {
        // Get the original filename and create a more descriptive download name
        $originalFilename = basename($filePath);
        $fileExtension    = pathinfo($originalFilename, PATHINFO_EXTENSION);

        // Create a descriptive filename
        $participantPrefix = $participant_type === 'teacher' ? 'STAFF' : 'STU';
        $downloadFilename  = $participantPrefix . "_" . $regId . "_" . str_replace(" ", "_", $eventName) . "_" . $type_display . "." . $fileExtension;

        // Get file size
        $fileSize = filesize("../" . $filePath);

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

        // Set headers for download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Output file content
        readfile("../" . $filePath);

    } else {
        if (! $filePath) {
            echo "<script>alert('No " . $type_display . " file found for this record.'); window.close();</script>";
        } else {
            echo "<script>alert('File not found on server: " . htmlspecialchars($filePath) . "'); window.close();</script>";
        }
    }
} else {
    echo "<script>alert('Record not found.'); window.close();</script>";
}

$stmt->close();
$conn->close();
exit();
