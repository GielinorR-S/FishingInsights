<?php
/**
 * IP-based Rate Limiting
 * PHP 7.3.33 compatible
 */

class RateLimiter {
    private $db = null;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Check if request is within rate limits
     * @param string $ipAddress
     * @param string $endpoint
     * @return array ['allowed' => bool, 'retry_after' => int|null]
     */
    public function checkLimit($ipAddress, $endpoint) {
        $minuteLimit = defined('RATE_LIMIT_PER_MINUTE') ? RATE_LIMIT_PER_MINUTE : 60;
        $hourLimit = defined('RATE_LIMIT_PER_HOUR') ? RATE_LIMIT_PER_HOUR : 1000;

        // Check per-minute limit
        $minuteWindow = date('Y-m-d H:i:00');
        $minuteResult = $this->checkWindow($ipAddress, $endpoint, $minuteWindow, $minuteLimit, 60);
        if (!$minuteResult['allowed']) {
            return $minuteResult;
        }

        // Check per-hour limit
        $hourWindow = date('Y-m-d H:00:00');
        $hourResult = $this->checkWindow($ipAddress, $endpoint, $hourWindow, $hourLimit, 3600);
        return $hourResult;
    }

    private function checkWindow($ipAddress, $endpoint, $windowStart, $limit, $windowSeconds) {
        $stmt = $this->db->prepare(
            "SELECT request_count FROM rate_limits 
             WHERE ip_address = ? AND endpoint = ? AND window_start = ?"
        );
        $stmt->execute([$ipAddress, $endpoint, $windowStart]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $count = $row['request_count'];
            if ($count >= $limit) {
                return [
                    'allowed' => false,
                    'retry_after' => $windowSeconds - (time() % $windowSeconds)
                ];
            }
            // Increment count
            $stmt = $this->db->prepare(
                "UPDATE rate_limits SET request_count = request_count + 1 
                 WHERE ip_address = ? AND endpoint = ? AND window_start = ?"
            );
            $stmt->execute([$ipAddress, $endpoint, $windowStart]);
        } else {
            // Create new window
            $stmt = $this->db->prepare(
                "INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start)
                 VALUES (?, ?, 1, ?)"
            );
            $stmt->execute([$ipAddress, $endpoint, $windowStart]);
        }

        return ['allowed' => true, 'retry_after' => null];
    }

    /**
     * Clean up old rate limit windows (keep last 24 hours)
     */
    public function cleanup() {
        $this->db->exec("DELETE FROM rate_limits WHERE window_start < datetime('now', '-1 day')");
    }
}

