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
        $this->appId      = $this->loadEnv("ONESIGNAL_APP_ID") ?: '';
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
    public function notifyNewHackathon($hackathonId, $title, $deadline, $description)
    {
        $notifTitle   = "🚀 New Hackathon: {$title}";
        $notifMessage = "Register before " . date("M d, Y", strtotime($deadline));
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            "hackathon_id" => $hackathonId,
            "type"         => "new_hackathon",
        ]);
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
     * Broadcast to all students
     */
    private function broadcastNotification($title, $message, $link, $data = [])
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
            "chrome_web_icon"   => "assets/images/logo.png",
        ];

        return $this->makeRequest("notifications", $payload);
    }

    /**
     * Send push notification to a single student
     * Tries targeted via external_id first, falls back to broadcast if no subscriber found
     */
    public function sendToStudent($studentRegno, $title, $message, $link, $playerIds = [])
    {
        if (! $this->restApiKey) {
            error_log("OneSignal API Key not configured");
            return ["status" => "error", "message" => "API Key missing"];
        }

        // Priority 1: target by stored OneSignal player ID (most reliable — direct device targeting)
        if (! empty($playerIds)) {
            $pidPayload = [
                "app_id"             => $this->appId,
                "include_player_ids" => array_values(array_map('strval', $playerIds)),
                "headings"           => ["en" => $title],
                "contents"           => ["en" => $message],
                "data"               => [
                    "link"         => $link,
                    "type"         => "certificate_reminder",
                    "target_regno" => strval($studentRegno),
                ],
                "chrome_web_icon"    => "assets/images/logo.png",
            ];
            $pidResult     = $this->makeRequest("notifications", $pidPayload);
            $pidRecipients = $pidResult['response']['recipients'] ?? 0;
            if ($pidRecipients > 0) {
                error_log("OneSignal: Delivered via player_id for {$studentRegno}, recipients: {$pidRecipients}");
                return $pidResult;
            }
            error_log("OneSignal: player_id send failed for {$studentRegno}, falling back to external_id");
        }

        // Priority 2: target by external_id
        $payload = [
            "app_id"          => $this->appId,
            "include_aliases" => ["external_id" => [strval($studentRegno)]],
            "target_channel"  => "push",
            "headings"        => ["en" => $title],
            "contents"        => ["en" => $message],
            "data"            => [
                "link"         => $link,
                "type"         => "certificate_reminder",
                "target_regno" => strval($studentRegno),
            ],
            "chrome_web_icon" => "assets/images/logo.png",
        ];

        $result = $this->makeRequest("notifications", $payload);

        $recipients = $result['response']['recipients'] ?? 0;
        if ($recipients == 0) {
            error_log("OneSignal: external_id not linked for {$studentRegno}, trying tag filter fallback");

            // Fallback: target by tag 'regno' (set via median.onesignal.tags.setTags)
            $tagPayload = [
                "app_id"          => $this->appId,
                "filters"         => [
                    ["field" => "tag", "key" => "regno", "relation" => "=", "value" => strval($studentRegno)],
                ],
                "headings"        => ["en" => $title],
                "contents"        => ["en" => $message],
                "data"            => [
                    "link"         => $link,
                    "type"         => "certificate_reminder",
                    "target_regno" => strval($studentRegno),
                ],
                "chrome_web_icon" => "assets/images/logo.png",
            ];
            $tagResult     = $this->makeRequest("notifications", $tagPayload);
            $tagRecipients = $tagResult['response']['recipients'] ?? 0;
            if ($tagRecipients > 0) {
                error_log("OneSignal: Tag fallback delivered for {$studentRegno}, recipients: {$tagRecipients}");
                return $tagResult;
            }
            error_log("OneSignal: Both methods failed for {$studentRegno} — student needs to open app once to link device");
        }

        return $result;
    }

    /**
     * Send to specific students (targeted via "regno" tag with OR filters)
     */
    private function sendToStudents($studentRegnos, $title, $message, $link)
    {
        if (! $this->restApiKey) {
            error_log("OneSignal API Key not configured");
            return ["status" => "error", "message" => "API Key missing"];
        }

        // Target specific students by external_id (matching OneSignal.login(regno))
        $externalIds = array_values(array_map('strval', (array) $studentRegnos));

        $payload = [
            "app_id"          => $this->appId,
            "include_aliases" => ["external_id" => $externalIds],
            "target_channel"  => "push",
            "headings"        => ["en" => $title],
            "contents"        => ["en" => $message],
            "data"            => ["link" => $link],
            "chrome_web_icon" => "assets/images/logo.png",
        ];

        return $this->makeRequest("notifications", $payload);
    }

    /**
     * Send hackathon deadline/start reminders (broadcast to all)
     */
    public function notifyReminder($hackathonId, $title, $deadline, $reminderType)
    {
        $typeLabels = [
            '1_day'           => '⏰ Last Day to Register',
            '3_days'          => '📢 3 Days Left',
            'starts_tomorrow' => '🚀 Starts Tomorrow',
            'starts_today'    => '🔥 Starting Today',
        ];

        $notifTitle   = ($typeLabels[$reminderType] ?? '⏰ Reminder') . ": {$title}";
        $notifMessage = "Deadline: " . date("M d, Y", strtotime($deadline));
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            "hackathon_id"  => $hackathonId,
            "type"          => "hackathon_reminder",
            "reminder_type" => $reminderType,
        ]);
    }

    /**
     * Send hackathon start reminders to students who applied (targeted push)
     */
    public function notifyAppliedReminder($hackathonId, $appliedStudents, $title, $reminderType, $startDate)
    {
        if (empty($appliedStudents)) {
            return ["status" => "no_recipients"];
        }

        $typeLabels = [
            'starts_tomorrow' => '🚀 Starts Tomorrow',
            'starts_today'    => '🔥 Starting Today',
        ];

        $notifTitle   = ($typeLabels[$reminderType] ?? '⏰ Reminder') . ": {$title}";
        $notifMessage = "The hackathon you registered for starts " . ($reminderType === 'starts_today' ? 'today' : 'tomorrow') . " (" . date("M d, Y", strtotime($startDate)) . ")!";
        $link         = "student/hackathons.php?id={$hackathonId}";

        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
    }

    private function makeRequest($endpoint, $data)
    {
        $url = "{$this->apiBaseUrl}/{$endpoint}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Basic " . $this->restApiKey,
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

        error_log("OneSignal HTTP {$httpCode}: " . $response);
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
