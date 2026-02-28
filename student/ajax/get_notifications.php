<?php
session_start();
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/DatabaseManager.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Verify student authentication
if (! isset($_SESSION['username']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$db = DatabaseManager::getInstance();

// Get student regno
$student_query = "SELECT regno FROM student_register WHERE username = ? LIMIT 1";
$result        = $db->executeQuery($student_query, [$_SESSION['username']]);

if (! $result || count($result) === 0) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Student not found']));
}

$student_regno = $result[0]['regno'];

// Auto-process reminders (poor man's cron - runs at most once every 5 minutes)
$lock_file     = __DIR__ . '/../../cache/reminder_last_run.txt';
$run_reminders = false;
if (! file_exists($lock_file) || (time() - filemtime($lock_file)) > 300) {
    @file_put_contents($lock_file, date('Y-m-d H:i:s'));
    $run_reminders = true;
}
if ($run_reminders) {
    try {
        require_once __DIR__ . '/../../includes/OneSignalManager.php';
        $oneSignal = new OneSignalManager();

        // Auto-schedule reminders for active hackathons
        $active_hackathons = $db->executeQuery(
            "SELECT id, title, registration_deadline, start_date, status
             FROM hackathon_posts
             WHERE status IN ('upcoming', 'ongoing')
             AND (registration_deadline > NOW() OR start_date > NOW())"
        );

        // Ensure reminders table exists
        try {
            $db->executeQuery("CREATE TABLE IF NOT EXISTS hackathon_reminders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                hackathon_id INT NOT NULL,
                reminder_type VARCHAR(50) NOT NULL,
                scheduled_for DATETIME NOT NULL,
                sent TINYINT(1) DEFAULT 0,
                sent_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_reminder (hackathon_id, reminder_type),
                INDEX idx_scheduled (scheduled_for, sent)
            )");
        } catch (Exception $e) { /* Table exists */}

        foreach ($active_hackathons as $h) {
            $now = new DateTime();
            if (! empty($h['registration_deadline'])) {
                $deadline_dt = new DateTime($h['registration_deadline']);
                $one_day     = clone $deadline_dt;
                $one_day->modify('-1 day');
                if ($one_day > $now) {
                    try { $db->executeQuery("INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, '1_day_reg', ?)", [$h['id'], $one_day->format('Y-m-d H:i:s')], 'is');} catch (Exception $e) {}
                }
                $three_days = clone $deadline_dt;
                $three_days->modify('-3 days');
                if ($three_days > $now) {
                    try { $db->executeQuery("INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, '3_days_reg', ?)", [$h['id'], $three_days->format('Y-m-d H:i:s')], 'is');} catch (Exception $e) {}
                }
            }
            if (! empty($h['start_date'])) {
                $start_dt      = new DateTime($h['start_date']);
                $one_day_start = clone $start_dt;
                $one_day_start->modify('-1 day');
                if ($one_day_start > $now) {
                    try { $db->executeQuery("INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, 'starts_tomorrow', ?)", [$h['id'], $one_day_start->format('Y-m-d H:i:s')], 'is');} catch (Exception $e) {}
                }
                if ($start_dt > $now) {
                    try { $db->executeQuery("INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, 'starts_today', ?)", [$h['id'], $start_dt->format('Y-m-d H:i:s')], 'is');} catch (Exception $e) {}
                }
            }
        }

        // Process due reminders
        $due_reminders = $db->executeQuery(
            "SELECT r.*, hp.title, hp.registration_deadline, hp.start_date, hp.end_date, hp.organizer, hp.status as hackathon_status
             FROM hackathon_reminders r
             JOIN hackathon_posts hp ON r.hackathon_id = hp.id
             WHERE r.sent = 0
             AND r.scheduled_for <= NOW()
             AND hp.status IN ('upcoming', 'ongoing')
             ORDER BY r.scheduled_for ASC"
        );

        foreach ($due_reminders as $reminder) {
            $h_id    = $reminder['hackathon_id'];
            $h_title = $reminder['title'];
            $r_type  = $reminder['reminder_type'];

            $notif_messages = [
                '1_day_reg'       => ['⏰ Last Day to Register: ' . $h_title, 'Registration closes tomorrow (' . date('M d, Y', strtotime($reminder['registration_deadline'])) . ')! Organized by ' . $reminder['organizer'] . '. Don\'t miss out!'],
                '3_days_reg'      => ['📢 3 Days Left: ' . $h_title, 'Only 3 days left to register for ' . $h_title . '! Deadline: ' . date('M d, Y', strtotime($reminder['registration_deadline']))],
                'starts_tomorrow' => ['🚀 Starts Tomorrow: ' . $h_title, $h_title . ' starts tomorrow (' . date('M d, Y', strtotime($reminder['start_date'])) . ')! Ends: ' . date('M d, Y', strtotime($reminder['end_date'])) . '. Get ready!'],
                'starts_today'    => ['🔥 Starting Today: ' . $h_title, $h_title . ' starts today! Runs until ' . date('M d, Y', strtotime($reminder['end_date'])) . '. Good luck!'],
            ];

            $n_title = $notif_messages[$r_type][0] ?? '⏰ Reminder: ' . $h_title;
            $n_msg   = $notif_messages[$r_type][1] ?? 'Don\'t forget about ' . $h_title . '!';

            try {
                $all_students = $db->executeQuery("SELECT regno FROM student_register");
                foreach ($all_students as $s) {
                    $db->executeQuery(
                        "INSERT INTO notifications (student_regno, notification_type, title, message, link, sent_at) VALUES (?, 'reminder', ?, ?, ?, NOW())",
                        [$s['regno'], $n_title, $n_msg, '/event_management_system/login/student/hackathons.php?id=' . $h_id],
                        'ssss'
                    );
                }

                // Send push notification
                $type_map = ['1_day_reg' => '1_day', '3_days_reg' => '3_days', 'starts_tomorrow' => 'starts_tomorrow', 'starts_today' => 'starts_today'];
                $os_type  = $type_map[$r_type] ?? $r_type;
                $oneSignal->notifyReminder($h_id, $h_title, $reminder['registration_deadline'] ?? $reminder['start_date'], $os_type);

                $db->executeQuery("UPDATE hackathon_reminders SET sent = 1, sent_at = NOW() WHERE id = ?", [$reminder['id']], 'i');
            } catch (Exception $e) {
                error_log("Reminder error: " . $e->getMessage());
            }
        }

        // Cleanup old reminders
        try { $db->executeQuery("DELETE FROM hackathon_reminders WHERE sent = 1 AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");} catch (Exception $e) {}

    } catch (Exception $e) {
        error_log("Auto-reminder processing error: " . $e->getMessage());
    }
}

// Determine action
$action = isset($_GET['action']) ? $_GET['action'] : 'get_notifications';

if ($action === 'get_notifications') {
    // Get all notifications for the student ordered by newest first
    $notifications_sql = "SELECT
        n.id,
        n.hackathon_id,
        n.title,
        n.message,
        n.notification_type,
        n.link,
        n.is_read,
        n.created_at,
        COALESCE(NULLIF(n.hackathon_title, ''), hp.title) as hackathon_title
    FROM notifications n
    LEFT JOIN hackathon_posts hp ON n.hackathon_id = hp.id
    WHERE n.student_regno = ?
    ORDER BY n.created_at DESC
    LIMIT 20";

    $notifications = $db->executeQuery($notifications_sql, [$student_regno]);

    // Count unread notifications
    $unread_count_sql = "SELECT COUNT(*) as count FROM notifications WHERE student_regno = ? AND (is_read = 0 OR is_read IS NULL)";
    $unread_result    = $db->executeQuery($unread_count_sql, [$student_regno]);
    $unread_count     = $unread_result[0]['count'] ?? 0;

    echo json_encode([
        'success'       => true,
        'notifications' => $notifications ?? [],
        'unread_count'  => $unread_count,
    ]);

} elseif ($action === 'mark_as_read') {
    $notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($notification_id <= 0) {
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Invalid notification ID']));
    }

    // Mark notification as read
    $update_sql = "UPDATE notifications
                   SET is_read = 1
                   WHERE id = ? AND student_regno = ?";

    $db->executeQuery($update_sql, [$notification_id, $student_regno]);

    echo json_encode([
        'success'         => true,
        'notification_id' => $notification_id,
    ]);

} elseif ($action === 'mark_all_read') {
    // Mark all notifications as read for the student
    $update_sql = "UPDATE notifications
                   SET is_read = 1
                   WHERE student_regno = ? AND (is_read = 0 OR is_read IS NULL)";

    $db->executeQuery($update_sql, [$student_regno]);

    echo json_encode(['success' => true]);

} elseif ($action === 'clear_all') {
    // Delete all notifications for the student
    $delete_sql = "DELETE FROM notifications WHERE student_regno = ?";
    $db->executeQuery($delete_sql, [$student_regno]);

    echo json_encode(['success' => true]);

} else {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid action']));
}
