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
            "chrome_icon"       => "asserts/images/logo.png",
            "chrome_web_icon"   => "asserts/images/logo.png",
        ];

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
