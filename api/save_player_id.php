<?php
    session_start();
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    // Function to log messages to a file
    function log_message($message)
    {
    $logFile   = __DIR__ . '/../logs/player_id.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] " . $message . "\n", FILE_APPEND);
    }

    header('Content-Type: application/json');

    log_message("--- New request to save_player_id.php ---");

    // 1. Check if a session exists and a student is logged in
    // Login sets $_SESSION['role'], not $_SESSION['user_role']
    if (! isset($_SESSION['username']) || ! isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    log_message("Error: No active student session found. Session data: " . json_encode($_SESSION));
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
    }
    $student_regno = $_SESSION['username'];
    log_message("Authenticated student: {$student_regno}");

    // 2. Check if player_id is received from the POST request
    // JS sends JSON body (Content-Type: application/json), which PHP does NOT
    // auto-parse into $_POST. Read from php://input first, fall back to $_POST.
    $raw_input = file_get_contents('php://input');
    $json_data = json_decode($raw_input, true);
    $player_id_raw = null;
    if (is_array($json_data) && isset($json_data['player_id'])) {
        $player_id_raw = $json_data['player_id'];
    } elseif (isset($_POST['player_id'])) {
        $player_id_raw = $_POST['player_id'];
    }

    if ($player_id_raw === null) {
    log_message("Error: player_id not found in POST/JSON data. Raw input: " . $raw_input);
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Player ID is missing.']);
    exit();
    }
    $player_id = trim($player_id_raw);
    log_message("Received Player ID: {$player_id}");

    if (empty($player_id)) {
    log_message("Error: Received empty Player ID.");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Player ID cannot be empty.']);
    exit();
    }

    require_once __DIR__ . '/../includes/db_config.php';
    $conn = get_db_connection();

    if (! $conn) {
    log_message("FATAL: Database connection failed.");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error.']);
    exit();
    }

    // 3. Check if the onesignal_player_id column exists, if not, create it.
    $check_column_sql = "SHOW COLUMNS FROM `student_register` LIKE 'onesignal_player_id'";
    $result           = $conn->query($check_column_sql);
    if ($result->num_rows == 0) {
    log_message("Column 'onesignal_player_id' not found. Attempting to create it.");
    $add_column_sql = "ALTER TABLE `student_register` ADD `onesignal_player_id` VARCHAR(255) NULL DEFAULT NULL AFTER `email`";
    if ($conn->query($add_column_sql) === true) {
        log_message("Column 'onesignal_player_id' created successfully.");
    } else {
        log_message("FATAL: Failed to create 'onesignal_player_id' column. Error: " . $conn->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to prepare database.']);
        exit();
    }
    }

    // 4. Update the database with the new Player ID
    $sql  = "UPDATE student_register SET onesignal_player_id = ? WHERE regno = ?";
    $stmt = $conn->prepare($sql);

    if (! $stmt) {
    log_message("Error preparing statement: " . $conn->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database statement error.']);
    exit();
    }

    $stmt->bind_param("ss", $player_id, $student_regno);

    if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        log_message("Successfully updated Player ID for {$student_regno} to {$player_id}.");
        echo json_encode(['success' => true, 'message' => 'Player ID updated.']);
    } else {
        // This can happen if the player ID was already the same value, which is not an error.
        log_message("Player ID for {$student_regno} was already set to {$player_id}. No update needed.");
        echo json_encode(['success' => true, 'message' => 'Player ID is already up to date.']);
    }
    } else {
    log_message("Error executing statement: " . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save Player ID.']);
    }

    $stmt->close();
$conn->close();
