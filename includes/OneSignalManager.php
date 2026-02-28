<?php
/**
 * OneSignal Manager - Automatic Notifications
 * Sends push notifications when hackathons are created/edited
 */
class OneSignalManager
{
    private $appId;
    private $restApiKey;
    private $apiBaseUrl = "https://onesignal.com/api/v1";

    public function __construct()
    {
        // Load from .env file
        $this->appId      = $this->loadEnv("ONESIGNAL_APP_ID") ?: "29fbebb0-954f-41f3-8f31-c3f57f61740b";
        $this->restApiKey = $this->loadEnv("ONESIGNAL_REST_API_KEY");

        // Log for debugging
        if (empty($this->appId)) {
            error_log("OneSignal: APP ID not found");
        }
        if (empty($this->restApiKey)) {
            error_log("OneSignal: REST API KEY not found");
        }
    }

    /**
     * Notify all students about new hackathon
     */
    public function notifyNewHackathon($hackathonId, $title, $deadline, $description, $posterUrl = null)
    {
        $notifTitle   = "🚀 New Hackathon: {$title}";
        $notifMessage = "Register before " . date("M d, Y", strtotime($deadline));
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            "hackathon_id" => $hackathonId,
            "type"         => "new_hackathon",
        ], $posterUrl);
    }

    /**
     * Notify only students who applied about update
     */
    public function notifyAppliedStudents($hackathonId, $appliedStudents, $title)
    {
        if (empty($appliedStudents)) {
            return ["status" => "no_recipients"];
        }

        $notifTitle   = "📢 Update: {$title}";
        $notifMessage = "Check the latest updates for this hackathon";
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
    }

    /**
     * Notify about hackathon status change (ongoing, completed, cancelled)
     */
    public function notifyStatusChange($hackathonId, $appliedStudents, $title, $oldStatus, $newStatus, $posterUrl = null)
    {
        $statusMessages = [
            'ongoing' => ['🔥 Hackathon Started!', "{$title} has started! Good luck to all participants!"],
            'completed' => ['🏆 Hackathon Completed!', "{$title} is now completed. Check results!"],
            'cancelled' => ['❌ Hackathon Cancelled', "{$title} has been cancelled."],
            'upcoming' => ['📅 Hackathon Reopened', "{$title} is now accepting registrations again!"],
        ];

        $notifTitle   = $statusMessages[$newStatus][0] ?? "📢 {$title} Status Updated";
        $notifMessage = $statusMessages[$newStatus][1] ?? "{$title} status changed to {$newStatus}";
        $link         = "student/hackathons.php?id={$hackathonId}";

        // For status changes, notify applied students + broadcast for major changes
        if (in_array($newStatus, ['ongoing', 'completed', 'cancelled'])) {
            // Notify applied students specifically
            if (! empty($appliedStudents)) {
                $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
            }
            // Also broadcast for major status changes
            return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
                'hackathon_id' => $hackathonId,
                'type'         => 'status_change',
                'new_status'   => $newStatus,
            ], $posterUrl);
        }

        // For other changes, just notify applied students
        if (! empty($appliedStudents)) {
            return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
        }

        return ['status' => 'no_recipients'];
    }

    /**
     * Send reminder notification (e.g., "1 day to go before registration closes")
     */
    public function notifyReminder($hackathonId, $title, $deadline, $reminderType = '1_day')
    {
        $messages = [
            '1_day' => ['⏰ Last Day to Register!', "Only 1 day left to register for {$title}! Deadline: " . date('M d, Y h:i A', strtotime($deadline))],
            '3_days' => ['📢 3 Days Left!', "Only 3 days left to register for {$title}! Don't miss out!"],
            '7_days' => ['📅 1 Week Left!', "{$title} registration closes in 1 week. Register now!"],
            'starts_tomorrow' => ['🚀 Starts Tomorrow!', "{$title} starts tomorrow! Get ready!"],
            'starts_today' => ['🔥 Starting Today!', "{$title} starts today! Don't miss it!"],
        ];

        $notifTitle   = $messages[$reminderType][0] ?? "⏰ Reminder: {$title}";
        $notifMessage = $messages[$reminderType][1] ?? "Don't forget about {$title}!";
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            'hackathon_id'  => $hackathonId,
            'type'          => 'reminder',
            'reminder_type' => $reminderType,
        ]);
    }

    /**
     * Send reminder to specific applied students
     */
    public function notifyAppliedReminder($hackathonId, $appliedStudents, $title, $reminderType, $eventDate)
    {
        if (empty($appliedStudents)) {
            return ['status' => 'no_recipients'];
        }

        $messages = [
            'starts_tomorrow' => ['🚀 Starts Tomorrow!', "{$title} starts tomorrow! Get ready!"],
            'starts_today' => ['🔥 Starting Today!', "{$title} starts today at " . date('h:i A', strtotime($eventDate)) . "!"],
        ];

        $notifTitle   = $messages[$reminderType][0] ?? "⏰ Reminder: {$title}";
        $notifMessage = $messages[$reminderType][1] ?? "Don't forget about {$title}!";
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
    }

    /**
     * Broadcast to all students
     */
    private function broadcastNotification($title, $message, $link, $data = [], $imageUrl = null)
    {
        if (! $this->restApiKey) {
            error_log("OneSignal API Key not configured");
            return ["status" => "error", "message" => "API Key missing"];
        }

        $payload = [
            "app_id"            => $this->appId,
            "included_segments" => ["All"],
            "headings"          => ["en" => $title],
            "contents"          => ["en" => $message],
            "data"              => array_merge(["link" => $link], $data),
            "chrome_icon"       => "asserts/images/logo.png",
            "chrome_web_icon"   => "asserts/images/logo.png",
        ];

        // Add poster image if available
        if (! empty($imageUrl)) {
            // Build full URL for the image
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . '/event_management_system/login/';
            $fullImageUrl                = $baseUrl . ltrim($imageUrl, '/');
            $payload['big_picture']      = $fullImageUrl;               // Android
            $payload['ios_attachments']  = ['poster' => $fullImageUrl]; // iOS
            $payload['chrome_web_image'] = $fullImageUrl;               // Web (Chrome)
        }

        return $this->makeRequest("notifications", $payload);
    }

    /**
     * Send to specific students
     */
    private function sendToStudents($studentRegnos, $title, $message, $link)
    {
        if (! $this->restApiKey) {
            return ["status" => "error", "message" => "API Key missing"];
        }

        $payload = [
            "app_id"                    => $this->appId,
            "include_external_user_ids" => (array) $studentRegnos,
            "headings"                  => ["en" => $title],
            "contents"                  => ["en" => $message],
            "data"                      => ["link" => $link],
        ];

        return $this->makeRequest("notifications", $payload);
    }

    /**
     * Make API request to OneSignal
     */
    private function makeRequest($endpoint, $data)
    {
        $url = "{$this->apiBaseUrl}/{$endpoint}";

        // Determine authentication type based on key format
        // User Auth Keys (v2) start with "os_v2_" and use Bearer authentication
        // Legacy REST API Keys use Basic authentication
        $authHeader = (strpos($this->restApiKey, 'os_v2_') === 0)
            ? "Authorization: Bearer " . $this->restApiKey
            : "Authorization: Basic " . $this->restApiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=utf-8",
            $authHeader,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("OneSignal Error: " . $error);
            return ["status" => "error", "message" => $error];
        }

        return ["status" => $httpCode, "response" => json_decode($response, true)];
    }

    /**
     * Load environment variable from .env file
     */
    private function loadEnv($key)
    {
        // Try different possible paths for .env file
        $possiblePaths = [
            __DIR__ . "/../.env",                                              // Relative to includes/
            __DIR__ . "/../../.env",                                           // Relative to admin/
            $_SERVER['DOCUMENT_ROOT'] . "/event_management_system/login/.env", // Absolute path
            realpath(__DIR__ . "/..") . "/.env",                               // Real path resolution
        ];

        $envFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envFile = $path;
                break;
            }
        }

        if (! $envFile) {
            error_log("OneSignal: .env file not found in any of these locations: " . json_encode($possiblePaths));
            return "";
        }

        $content = file_get_contents($envFile);

        // Split by lines
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            // Trim whitespace and newlines
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || substr($line, 0, 1) === "#") {
                continue;
            }

            // Split by = sign
            if (strpos($line, "=") !== false) {
                $parts    = explode("=", $line, 2);
                $envKey   = trim($parts[0]);
                $envValue = trim($parts[1]);

                // Remove quotes if present
                $envValue = trim($envValue, '"\'');

                if ($envKey === $key) {
                    return $envValue;
                }
            }
        }

        error_log("OneSignal: Key '$key' not found in .env file at: " . $envFile);
        return "";
    }
}
