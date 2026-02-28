<?php
/**
 * Hackathon Reminder Cron Job
 * ============================
 * Sends "1 day to go", "3 days to go", "starts tomorrow", "starts today" notifications
 * Both in-app (notifications table) and push (OneSignal).
 *
 * SETUP: Run this script every hour via Task Scheduler (Windows) or cron (Linux):
 *
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\event_management_system\login\cron_reminders.php
 *   Trigger: Every 1 hour
 *
 * Linux Cron:
 *   0 * * * * /usr/bin/php /var/www/html/event_management_system/login/cron_reminders.php
 *
 * Or access via browser: http://localhost/event_management_system/login/cron_reminders.php
 */

// Allow running from CLI or browser
if (php_sapi_name() !== 'cli') {
    // Simple auth check for browser access
    session_start();
}

require_once __DIR__ . '/includes/DatabaseManager.php';
require_once __DIR__ . '/includes/OneSignalManager.php';

$db        = DatabaseManager::getInstance();
$oneSignal = new OneSignalManager();

$is_cli = php_sapi_name() === 'cli';
$log    = function ($msg) use ($is_cli) {
    $timestamp = date('Y-m-d H:i:s');
    if ($is_cli) {
        echo "[{$timestamp}] {$msg}\n";
    } else {
        echo "<p>[{$timestamp}] {$msg}</p>";
    }
    error_log("CronReminder: {$msg}");
};

if (! $is_cli) {
    echo "<!DOCTYPE html><html><head><title>Hackathon Reminders</title></head><body>";
    echo "<h2>🔔 Hackathon Reminder Processing</h2>";
}

$log("Starting reminder processing...");

// ============================================================
// STEP 1: Ensure reminders table exists
// ============================================================
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
} catch (Exception $e) {
    // Table likely already exists
}

// ============================================================
// STEP 2: Auto-schedule reminders for hackathons that don't have them
// ============================================================
$log("Checking for hackathons needing reminders...");

$active_hackathons = $db->executeQuery(
    "SELECT id, title, registration_deadline, start_date, status
     FROM hackathon_posts
     WHERE status IN ('upcoming', 'ongoing')
     AND (registration_deadline > NOW() OR start_date > NOW())"
);

foreach ($active_hackathons as $h) {
    $now = new DateTime();

    // Registration deadline - 1 day reminder
    if (! empty($h['registration_deadline'])) {
        $deadline_dt    = new DateTime($h['registration_deadline']);
        $one_day_before = clone $deadline_dt;
        $one_day_before->modify('-1 day');

        if ($one_day_before > $now) {
            try {
                $db->executeQuery(
                    "INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, '1_day_reg', ?)",
                    [$h['id'], $one_day_before->format('Y-m-d H:i:s')],
                    'is'
                );
            } catch (Exception $e) { /* Ignore duplicates */}
        }

        // 3-day reminder
        $three_days_before = clone $deadline_dt;
        $three_days_before->modify('-3 days');
        if ($three_days_before > $now) {
            try {
                $db->executeQuery(
                    "INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, '3_days_reg', ?)",
                    [$h['id'], $three_days_before->format('Y-m-d H:i:s')],
                    'is'
                );
            } catch (Exception $e) { /* Ignore duplicates */}
        }
    }

    // Start date reminders
    if (! empty($h['start_date'])) {
        $start_dt = new DateTime($h['start_date']);

        // Starts tomorrow
        $one_day_before_start = clone $start_dt;
        $one_day_before_start->modify('-1 day');
        if ($one_day_before_start > $now) {
            try {
                $db->executeQuery(
                    "INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, 'starts_tomorrow', ?)",
                    [$h['id'], $one_day_before_start->format('Y-m-d H:i:s')],
                    'is'
                );
            } catch (Exception $e) { /* Ignore duplicates */}
        }

        // Starts today
        if ($start_dt > $now) {
            try {
                $db->executeQuery(
                    "INSERT IGNORE INTO hackathon_reminders (hackathon_id, reminder_type, scheduled_for) VALUES (?, 'starts_today', ?)",
                    [$h['id'], $start_dt->format('Y-m-d H:i:s')],
                    'is'
                );
            } catch (Exception $e) { /* Ignore duplicates */}
        }
    }
}

$log("Auto-scheduled reminders for " . count($active_hackathons) . " active hackathons");

// ============================================================
// STEP 3: Process due reminders
// ============================================================
$log("Processing due reminders...");

$due_reminders = $db->executeQuery(
    "SELECT r.*, hp.title, hp.registration_deadline, hp.start_date, hp.end_date, hp.organizer, hp.status as hackathon_status
     FROM hackathon_reminders r
     JOIN hackathon_posts hp ON r.hackathon_id = hp.id
     WHERE r.sent = 0
     AND r.scheduled_for <= NOW()
     AND hp.status IN ('upcoming', 'ongoing')
     ORDER BY r.scheduled_for ASC"
);

$sent_count = 0;

foreach ($due_reminders as $reminder) {
    $hackathon_id  = $reminder['hackathon_id'];
    $title         = $reminder['title'];
    $reminder_type = $reminder['reminder_type'];

    $log("Processing reminder: {$reminder_type} for '{$title}' (ID: {$hackathon_id})");

    // Map reminder types to OneSignal notification types
    $type_mapping = [
        '1_day_reg'       => '1_day',
        '3_days_reg'      => '3_days',
        'starts_tomorrow' => 'starts_tomorrow',
        'starts_today'    => 'starts_today',
    ];
    $onesignal_type = $type_mapping[$reminder_type] ?? $reminder_type;

    // Notification messages for in-app
    $notif_messages = [
        '1_day_reg'       => ['⏰ Last Day to Register: ' . $title, 'Registration closes tomorrow (' . date('M d, Y', strtotime($reminder['registration_deadline'])) . ')! Organized by ' . ($reminder['organizer'] ?? 'Unknown') . '. Don\'t miss out!'],
        '3_days_reg'      => ['📢 3 Days Left: ' . $title, 'Only 3 days left to register for ' . $title . '! Deadline: ' . date('M d, Y', strtotime($reminder['registration_deadline']))],
        'starts_tomorrow' => ['🚀 Starts Tomorrow: ' . $title, $title . ' starts tomorrow (' . date('M d, Y', strtotime($reminder['start_date'])) . ')! Ends: ' . date('M d, Y', strtotime($reminder['end_date'] ?? $reminder['start_date'])) . '. Get ready!'],
        'starts_today'    => ['🔥 Starting Today: ' . $title, $title . ' starts today! Runs until ' . date('M d, Y', strtotime($reminder['end_date'] ?? $reminder['start_date'])) . '. Good luck!'],
    ];

    $notif_title = $notif_messages[$reminder_type][0] ?? "⏰ Reminder: {$title}";
    $notif_msg   = $notif_messages[$reminder_type][1] ?? "Don't forget about {$title}!";

    try {
        // Get applied students
        $applied_students = $db->executeQuery(
            "SELECT DISTINCT student_regno FROM hackathon_applications WHERE hackathon_id = ?",
            [$hackathon_id],
            'i'
        );
        $applied_regnos = ! empty($applied_students) ? array_column($applied_students, 'student_regno') : [];

        // PUSH NOTIFICATION via OneSignal
        if (in_array($reminder_type, ['1_day_reg', '3_days_reg'])) {
            // Registration reminders → broadcast to ALL students
            $deadline = $reminder['registration_deadline'];
            $oneSignal->notifyReminder($hackathon_id, $title, $deadline, $onesignal_type);
            $log("  → Push notification broadcast to all students");

            // IN-APP notification for all students
            $all_students = $db->executeQuery("SELECT regno FROM student_register");
            foreach ($all_students as $student) {
                $db->executeQuery(
                    "INSERT INTO notifications (student_regno, notification_type, title, message, link, sent_at)
                     VALUES (?, 'hackathon', ?, ?, ?, NOW())",
                    [
                        $student['regno'],
                        $notif_title,
                        $notif_msg,
                        '/event_management_system/login/student/hackathons.php?id=' . $hackathon_id,
                    ],
                    'ssss'
                );
            }
            $log("  → In-app notification sent to " . count($all_students) . " students");

        } else {
            // Start date reminders → notify applied students + broadcast
            if (! empty($applied_regnos)) {
                $oneSignal->notifyAppliedReminder($hackathon_id, $applied_regnos, $title, $onesignal_type, $reminder['start_date']);
                $log("  → Push notification sent to " . count($applied_regnos) . " applied students");
            }

            // Also broadcast start reminders to all
            $oneSignal->notifyReminder($hackathon_id, $title, $reminder['start_date'], $onesignal_type);
            $log("  → Push notification broadcast to all");

            // IN-APP notification for all students
            $all_students = $db->executeQuery("SELECT regno FROM student_register");
            foreach ($all_students as $student) {
                $db->executeQuery(
                    "INSERT INTO notifications (student_regno, notification_type, title, message, link, sent_at)
                     VALUES (?, 'hackathon', ?, ?, ?, NOW())",
                    [
                        $student['regno'],
                        $notif_title,
                        $notif_msg,
                        '/event_management_system/login/student/hackathons.php?id=' . $hackathon_id,
                    ],
                    'ssss'
                );
            }
            $log("  → In-app notification sent to " . count($all_students) . " students");
        }

        // Mark reminder as sent
        $db->executeQuery(
            "UPDATE hackathon_reminders SET sent = 1, sent_at = NOW() WHERE id = ?",
            [$reminder['id']],
            'i'
        );

        $sent_count++;
        $log("  ✅ Reminder processed successfully");

    } catch (Exception $e) {
        $log("  ❌ Error processing reminder: " . $e->getMessage());
    }
}

// ============================================================
// STEP 4: Clean up old sent reminders (older than 30 days)
// ============================================================
try {
    $db->executeQuery("DELETE FROM hackathon_reminders WHERE sent = 1 AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
} catch (Exception $e) {
    // Ignore cleanup errors
}

// ============================================================
// SUMMARY
// ============================================================
$log("=== SUMMARY ===");
$log("Active hackathons checked: " . count($active_hackathons));
$log("Due reminders found: " . count($due_reminders));
$log("Reminders sent: {$sent_count}");
$log("Processing complete!");

if (! $is_cli) {
    // Show scheduled reminders status
    $upcoming_reminders = $db->executeQuery(
        "SELECT r.*, hp.title
         FROM hackathon_reminders r
         JOIN hackathon_posts hp ON r.hackathon_id = hp.id
         WHERE r.sent = 0
         ORDER BY r.scheduled_for ASC
         LIMIT 20"
    );

    if (! empty($upcoming_reminders)) {
        echo "<h3>📅 Upcoming Scheduled Reminders</h3>";
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background:#0c3878;color:white;'><th>Hackathon</th><th>Reminder Type</th><th>Scheduled For</th><th>Status</th></tr>";
        foreach ($upcoming_reminders as $r) {
            $type_labels = [
                '1_day_reg'       => '⏰ 1 Day Before Registration',
                '3_days_reg'      => '📢 3 Days Before Registration',
                'starts_tomorrow' => '🚀 Starts Tomorrow',
                'starts_today'    => '🔥 Starts Today',
            ];
            $type_label = $type_labels[$r['reminder_type']] ?? $r['reminder_type'];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['title']) . "</td>";
            echo "<td>{$type_label}</td>";
            echo "<td>" . date('M d, Y h:i A', strtotime($r['scheduled_for'])) . "</td>";
            echo "<td>⏳ Pending</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No pending reminders.</p>";
    }

    // Show recent sent reminders
    $recent_sent = $db->executeQuery(
        "SELECT r.*, hp.title
         FROM hackathon_reminders r
         JOIN hackathon_posts hp ON r.hackathon_id = hp.id
         WHERE r.sent = 1
         ORDER BY r.sent_at DESC
         LIMIT 10"
    );

    if (! empty($recent_sent)) {
        echo "<h3>✅ Recently Sent Reminders</h3>";
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background:#28a745;color:white;'><th>Hackathon</th><th>Type</th><th>Sent At</th></tr>";
        foreach ($recent_sent as $r) {
            $type_labels = [
                '1_day_reg'       => '⏰ 1 Day Before Reg',
                '3_days_reg'      => '📢 3 Days Before Reg',
                'starts_tomorrow' => '🚀 Starts Tomorrow',
                'starts_today'    => '🔥 Starts Today',
            ];
            echo "<tr>";
            echo "<td>" . htmlspecialchars($r['title']) . "</td>";
            echo "<td>" . ($type_labels[$r['reminder_type']] ?? $r['reminder_type']) . "</td>";
            echo "<td>" . date('M d, Y h:i A', strtotime($r['sent_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<hr><p><strong>Setup:</strong> Schedule this script to run every hour via Windows Task Scheduler:</p>";
    echo "<code>C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\event_management_system\\login\\cron_reminders.php</code>";
    echo "</body></html>";
}
