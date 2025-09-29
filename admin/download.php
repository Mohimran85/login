<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Invalid request");
}

$id = intval($_GET['id']);
$type = $_GET['type']; // 'poster' or 'certificate'

// Validate type parameter
if (!in_array($type, ['poster', 'certificate'])) {
    die("Invalid file type");
}

$conn = new mysqli("localhost", "root", "", "event_management_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the file path based on type
if ($type == 'poster') {
    $sql = "SELECT event_poster as file_path, event_name, regno FROM student_event_register WHERE id = ?";
} else if ($type == 'certificate') {
    $sql = "SELECT certificates as file_path, event_name, regno FROM student_event_register WHERE id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $filePath = $row['file_path'];
    $eventName = $row['event_name'];
    $regno = $row['regno'];
    
    if ($filePath && file_exists("../" . $filePath)) {
        // Get the original filename and create a more descriptive download name
        $originalFilename = basename($filePath);
        $fileExtension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        
        // Create a descriptive filename
        $downloadFilename = $regno . "_" . str_replace(" ", "_", $eventName) . "_" . $type . "." . $fileExtension;
        
        // Get file size
        $fileSize = filesize("../" . $filePath);
        
        // Set headers for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        // Output file content
        readfile("../" . $filePath);
        
    } else {
        if (!$filePath) {
            echo "<script>alert('No " . $type . " file found for this record.'); window.close();</script>";
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
?>