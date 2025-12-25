<?php
/**
 * Utility Functions
 * PHP 7.3.33 compatible
 */

/**
 * Get client IP address
 * @return string
 */
function getClientIp() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Send JSON response
 * @param array $data
 * @param int $statusCode
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 * @param string $message
 * @param string $code
 * @param array $details
 * @param int $statusCode
 */
function sendError($message, $code = 'ERROR', $details = [], $statusCode = 500) {
    sendJson([
        'error' => true,
        'message' => $message,
        'code' => $code,
        'details' => $details
    ], $statusCode);
}

/**
 * Get timezone-aware DateTime
 * @param string $timezone
 * @return DateTime
 */
function getTimezoneDateTime($timezone = null) {
    if ($timezone === null) {
        $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    }
    return new DateTime('now', new DateTimeZone($timezone));
}

/**
 * Format ISO 8601 timestamp with timezone offset
 * @param DateTime $dateTime
 * @return string
 */
function formatIso8601($dateTime) {
    return $dateTime->format('Y-m-d\TH:i:sP');
}

