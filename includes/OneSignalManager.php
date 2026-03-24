<?php
/**
 * OneSignal Manager - Automatic Notifications
 * Sends push notifications when hackathons are created/edited
 */
class OneSignalManager
{
    private $appId;
    private $restApiKey;
    private $apiBaseUrl = 'https://onesignal.com/api/v1';

    public function __construct()
    {
        // Load from .env file
        $this->appId      = $this->loadEnv('ONESIGNAL_APP_ID') ?: '';
        $this->restApiKey = $this->loadEnv('ONESIGNAL_REST_API_KEY');

        // Log for debugging
        if (empty($this->appId)) {
            error_log('OneSignal: APP ID not found');
        }
        if (empty($this->restApiKey)) {
            error_log('OneSignal: REST API KEY not found');
        }
    }

    /**
     * Notify all students about new hackathon
     */
    public function notifyNewHackathon($hackathonId, $title, $deadline, $description, $posterUrl = null)
    {
        $notifTitle   = '🚀 New Hackathon: ' . $title;
        $notifMessage = 'Registration deadline: ' . date('M d, Y', strtotime($deadline)) . '. Register now!';
        $link         = 'student/hackathons.php?id=' . $hackathonId;

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            'hackathon_id' => $hackathonId,
            'type'         => 'new_hackathon',
        ], $posterUrl);
    }

    /**
     * Notify about status changes (Draft -> Upcoming, etc.)
     */
    public function notifyStatusChange($hackathonId, $appliedStudents, $title, $oldStatus, $newStatus, $posterUrl = null)
    {
        $statusLabels = [
            'upcoming'  => '📅 Reopened',
            'ongoing'   => '🔥 Now Live',
            'completed' => '🏆 Completed',
            'cancelled' => '❌ Cancelled',
        ];

        $notifTitle   = ($statusLabels[$newStatus] ?? '📢 Status Update') . ': ' . $title;
        $notifMessage = 'Status changed - ' . ucfirst($oldStatus) . ' → ' . ucfirst($newStatus);
        $link         = 'student/hackathons.php?id=' . $hackathonId;

        // Major status changes broadcast to ALL students
        if (in_array($newStatus, ['ongoing', 'completed', 'cancelled'])) {
            return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
                'hackathon_id' => $hackathonId,
                'type'         => 'status_change',
                'status'       => $newStatus,
            ], $posterUrl);
        }

        // Minor changes only to applied students
        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link, $posterUrl);
    }

    /**
     * Notify only students who applied about update
     */
    public function notifyAppliedStudents($hackathonId, $appliedStudents, $title)
    {
        if (empty($appliedStudents)) {
            return ['status' => 'no_recipients'];
        }

        $notifTitle   = '📝 Updated: ' . $title;
        $notifMessage = 'Hackathon details have been updated. Check the latest changes!';
        $link         = 'student/hackathons.php?id=' . $hackathonId;

        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
    }

    /**
     * Broadcast to all students
     * Tries segment-based broadcast first, falls back to direct player targeting
     */
    private function broadcastNotification($title, $message, $link, $data = [], $posterUrl = null)
    {
        if (! $this->restApiKey) {
            error_log('OneSignal API Key not configured');
            return ['status' => 'error', 'message' => 'API Key missing'];
        }

        $notifData = array_merge(['link' => $link, 'targetUrl' => $link, 'url' => $link], $data);

        $payload = [
            'app_id'            => $this->appId,
            'included_segments' => ['Subscribed Users'],
            'target_channel'    => 'push',
            'headings'          => ['en' => $title],
            'contents'          => ['en' => $message],
            'data'              => $notifData,
        ];

        if ($posterUrl) {
            $payload['android_big_picture'] = $posterUrl;
            $payload['big_picture']         = $posterUrl;
        }

        $result = $this->makeRequest('notifications', $payload);

        // If segment broadcast succeeded, return it
        $hasErrors  = ! empty($result['response']['errors']);
        $recipients = $result['response']['recipients'] ?? 0;
        if ($recipients > 0 && ! $hasErrors) {
            return $result;
        }

        // Fallback: fetch valid player IDs from OneSignal and target directly
        error_log('OneSignal: Segment broadcast failed, trying direct player targeting');
        $validPlayerIds = $this->fetchValidPlayerIds();

        if (empty($validPlayerIds)) {
            error_log('OneSignal: No valid players found');
            return $result;
        }

        // OneSignal allows max 2000 player IDs per request
        $chunks     = array_chunk($validPlayerIds, 2000);
        $lastResult = null;
        foreach ($chunks as $chunk) {
            $directPayload = [
                'app_id'             => $this->appId,
                'include_player_ids' => $chunk,
                'headings'           => ['en' => $title],
                'contents'           => ['en' => $message],
                'data'               => $notifData,
            ];
            if ($posterUrl) {
                $directPayload['android_big_picture'] = $posterUrl;
                $directPayload['big_picture']         = $posterUrl;
            }
            $lastResult = $this->makeRequest('notifications', $directPayload);
        }

        return $lastResult;
    }

    /**
     * Fetch valid (non-invalid) player IDs from OneSignal API
     */
    private function fetchValidPlayerIds()
    {
        $url     = $this->apiBaseUrl . "/players?app_id={$this->appId}&limit=300";
        $headers = [
            "Authorization: Basic {$this->restApiKey}",
            "Content-Type: application/json",
        ];

        $response = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            curl_close($ch);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'header'        => implode("\r\n", $headers),
                    'method'        => 'GET',
                    'ignore_errors' => true,
                    'timeout'       => 15,
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $response = @file_get_contents($url, false, $ctx);
        }

        if (! $response) {
            return [];
        }

        $data     = json_decode($response, true);
        $validIds = [];
        if (! empty($data['players'])) {
            foreach ($data['players'] as $player) {
                if (empty($player['invalid_identifier'])) {
                    $validIds[] = $player['id'];
                }
            }
        }
        error_log('OneSignal: Found ' . count($validIds) . ' valid players out of ' . ($data['total_count'] ?? 0));
        return $validIds;
    }

    /**
     * Send push notification to a single student
     */
    public function sendToStudent($studentRegno, $title, $message, $link, $playerIds = [])
    {
        if (! $this->restApiKey) {
            error_log('OneSignal API Key not configured');
            return ['status' => 'error', 'message' => 'API Key missing'];
        }

        if (empty($playerIds)) {
            require_once __DIR__ . '/db_config.php';
            $conn = get_db_connection();
            if ($conn) {
                $sql  = "SELECT onesignal_player_id FROM student_register WHERE regno = ? AND onesignal_player_id IS NOT NULL AND onesignal_player_id != ''";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $reg = strval($studentRegno);
                    $stmt->bind_param('s', $reg);
                    $stmt->execute();
                    $stmt->bind_result($pid);
                    while ($stmt->fetch()) {
                        $playerIds[] = $pid;
                    }
                    $stmt->close();
                }
            }
        }

        if (! empty($playerIds)) {
            $pidPayload = [
                'app_id'             => $this->appId,
                'include_player_ids' => array_values(array_map('strval', $playerIds)),
                'headings'           => ['en' => $title],
                'contents'           => ['en' => $message],
                'data'               => [
                    'link'         => $link,
                    'targetUrl'    => $link,
                    'url'          => $link,
                    'type'         => 'certificate_reminder',
                    'target_regno' => strval($studentRegno),
                ],
            ];
            $pidResult     = $this->makeRequest('notifications', $pidPayload);
            $pidRecipients = $pidResult['response']['recipients'] ?? 0;
            if ($pidRecipients > 0) {
                return $pidResult;
            }
        }

        $payload = [
            'app_id'          => $this->appId,
            'include_aliases' => ['external_id' => [strval($studentRegno)]],
            'target_channel'  => 'push',
            'headings'        => ['en' => $title],
            'contents'        => ['en' => $message],
            'data'            => [
                'link'         => $link,
                'targetUrl'    => $link,
                'url'          => $link,
                'type'         => 'certificate_reminder',
                'target_regno' => strval($studentRegno),
            ],
        ];

        $result     = $this->makeRequest('notifications', $payload);
        $recipients = $result['response']['recipients'] ?? 0;
        if ($recipients == 0) {
            $tagPayload = [
                'app_id'   => $this->appId,
                'filters'  => [
                    ['field' => 'tag', 'key' => 'regno', 'relation' => '=', 'value' => strval($studentRegno)],
                ],
                'headings' => ['en' => $title],
                'contents' => ['en' => $message],
                'data'     => [
                    'link'         => $link,
                    'targetUrl'    => $link,
                    'url'          => $link,
                    'type'         => 'certificate_reminder',
                    'target_regno' => strval($studentRegno),
                ],
            ];
            return $this->makeRequest('notifications', $tagPayload);
        }

        return $result;
    }

    /**
     * Send to specific students
     */
    private function sendToStudents($studentRegnos, $title, $message, $link, $posterUrl = null)
    {
        if (! $this->restApiKey) {
            error_log('OneSignal API Key not configured');
            return ['status' => 'error', 'message' => 'API Key missing'];
        }

        $externalIds = array_values(array_map('strval', (array) $studentRegnos));

        if (empty($externalIds)) {
            return ['status' => 'error', 'message' => 'No recipients'];
        }

        $playerIds    = [];
        $mappedRegnos = [];
        require_once __DIR__ . '/db_config.php';
        $conn = get_db_connection();
        if ($conn) {
            $placeholders = implode(',', array_fill(0, count($externalIds), '?'));
            $sql          = "SELECT regno, onesignal_player_id FROM student_register WHERE regno IN ($placeholders) AND onesignal_player_id IS NOT NULL AND onesignal_player_id != ''";
            $stmt         = $conn->prepare($sql);
            if ($stmt) {
                $types = str_repeat('s', count($externalIds));
                $stmt->bind_param($types, ...$externalIds);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $playerIds[]    = $row['onesignal_player_id'];
                    $mappedRegnos[] = strval($row['regno']);
                }
                $stmt->close();
            }
        }

        $basePayload = [
            'app_id'         => $this->appId,
            'target_channel' => 'push',
            'headings'       => ['en' => $title],
            'contents'       => ['en' => $message],
            'data'           => ['link' => $link, 'targetUrl' => $link, 'url' => $link],
        ];

        if ($posterUrl) {
            $basePayload['android_big_picture'] = $posterUrl;
            $basePayload['big_picture']         = $posterUrl;
        }

        $lastResult = ['status' => 'error', 'message' => 'No valid recipients'];

        // 1. Send to devices that have a direct Player ID
        if (! empty($playerIds)) {
            $pidPayload                       = $basePayload;
            $pidPayload['include_player_ids'] = array_values(array_unique(array_map('strval', $playerIds)));
            $lastResult                       = $this->makeRequest('notifications', $pidPayload);
        }

        // 2. Fallback for valid students who don't have a mapped Player ID yet
        $unmappedRegnos = array_diff($externalIds, $mappedRegnos);
        if (! empty($unmappedRegnos)) {
            $aliasPayload                    = $basePayload;
            $aliasPayload['include_aliases'] = ['external_id' => array_values($unmappedRegnos)];
            $aliasResult                     = $this->makeRequest('notifications', $aliasPayload);
            // If primary PID fail/didn't exist, we return this result
            if (empty($playerIds)) {
                $lastResult = $aliasResult;
            }
        }

        return $lastResult;
    }

    /**
     * Send reminder
     */
    public function notifyReminder($hackathonId, $title, $deadline, $reminderType)
    {
        $typeLabels = [
            '1_day'           => '⏰ Last Day to Register',
            '3_days'          => '📅 3 Days Left to Register',
            'starts_tomorrow' => '🚀 Starting Tomorrow',
            'starts_today'    => '🔥 Starting Today',
        ];

        $notifTitle   = ($typeLabels[$reminderType] ?? '⏰ Reminder') . ': ' . $title;
        $notifMessage = 'Registration deadline: ' . date('M d, Y h:i A', strtotime($deadline));
        $link         = 'student/hackathons.php?id=' . $hackathonId;

        return $this->broadcastNotification($notifTitle, $notifMessage, $link, [
            'hackathon_id'  => $hackathonId,
            'type'          => 'hackathon_reminder',
            'reminder_type' => $reminderType,
        ]);
    }

    /**
     * Send reminder applied
     */
    public function notifyAppliedReminder($hackathonId, $appliedStudents, $title, $reminderType, $startDate)
    {
        if (empty($appliedStudents)) {
            return ['status' => 'no_recipients'];
        }

        $typeLabels = [
            'starts_tomorrow' => '🚀 Starting Tomorrow',
            'starts_today'    => '🔥 Starting Today',
        ];

        $notifTitle   = ($typeLabels[$reminderType] ?? '⏰ Reminder') . ': ' . $title;
        $notifMessage = 'The hackathon you registered for starts ' . ($reminderType === 'starts_today' ? 'today' : 'tomorrow') . ' at ' . date('M d, Y h:i A', strtotime($startDate)) . '. Get ready!';
        $link         = 'student/hackathons.php?id=' . $hackathonId;

        return $this->sendToStudents($appliedStudents, $notifTitle, $notifMessage, $link);
    }

    private function makeRequest($endpoint, $data)
    {
        $url = $this->apiBaseUrl . '/' . $endpoint;

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $this->restApiKey,
        ];

        $payload = json_encode($data);

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log('OneSignal Error: ' . $error);
                return ['status' => 'error', 'message' => $error];
            }
        } else {
            // Fallback if cURL is not enabled
            $options = [
                'http' => [
                    'header'        => implode("\r\n", $headers),
                    'method'        => 'POST',
                    'content'       => $payload,
                    'ignore_errors' => true,
                    'timeout'       => 30,
                ],
            ];
            $context  = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log('OneSignal Error: file_get_contents failed');
                return ['status' => 'error', 'message' => 'Network request failed'];
            }

            $httpCode = 200;
            if (isset($http_response_header) && count($http_response_header) > 0) {
                preg_match('#HTTP/[\d\.]+\s+(\d+)#i', $http_response_header[0], $matches);
                if (isset($matches[1])) {
                    $httpCode = (int) $matches[1];
                }
            }
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('OneSignal HTTP Error (' . $httpCode . '): ' . $response);
            return ['status' => 'error', 'code' => $httpCode, 'message' => $response];
        }

        return ['status' => 'success', 'code' => $httpCode, 'response' => json_decode($response, true)];
    }

    private function loadEnv($key)
    {
        $possiblePaths = [
            __DIR__ . '/../.env',
            __DIR__ . '/../../.env',
            $_SERVER['DOCUMENT_ROOT'] . '/event_management_system/login/.env',
            realpath(__DIR__ . '/..') . '/.env',
        ];

        $envFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envFile = $path;
                break;
            }
        }

        if (! $envFile) {
            return '';
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            if (trim($name) === $key) {
                return trim($value);
            }
        }
        return '';
    }
}
