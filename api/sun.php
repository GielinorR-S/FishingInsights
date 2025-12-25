<?php
/**
 * Sunrise/Sunset Data Endpoint
 * PHP 7.3.33 compatible
 */

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Cache.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/RateLimiter.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $ip = getClientIp();
    $limitCheck = $rateLimiter->checkLimit($ip, 'sun');
    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $limitCheck['retry_after']);
        sendError('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', [], 429);
    }

    // Validate input
    $lat = Validator::validateLat($_GET['lat'] ?? null);
    $lng = Validator::validateLng($_GET['lng'] ?? null);
    $days = Validator::validateDays($_GET['days'] ?? 7);
    $start = Validator::validateDate($_GET['start'] ?? null);
    
    if ($lat === false || $lng === false) {
        sendError('Invalid latitude or longitude', 'VALIDATION_ERROR', [], 400);
    }
    
    if ($start === false && isset($_GET['start'])) {
        sendError('Invalid date format (use YYYY-MM-DD)', 'VALIDATION_ERROR', [], 400);
    }
    
    if ($start === false) {
        $start = date('Y-m-d');
    }

    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    $cache = new Cache();
    $cacheKey = $lat . ':' . $lng . ':' . $start . ':' . $days;
    
    // Check cache
    $cached = $cache->get('sun', $cacheKey);
    if ($cached !== null) {
        $cached['cached'] = true;
        sendJson(['error' => false, 'data' => $cached]);
    }

    // Fetch from Open-Meteo
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $lat,
        'longitude' => $lng,
        'daily' => 'sunrise,sunset',
        'timezone' => $timezone,
        'start_date' => $start,
        'end_date' => date('Y-m-d', strtotime($start . ' +' . ($days - 1) . ' days'))
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        sendError('Sun API request failed', 'EXTERNAL_API_ERROR', [], 503);
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['daily'])) {
        sendError('Invalid sun API response', 'EXTERNAL_API_ERROR', [], 503);
    }

    // Normalize response
    $times = [];
    $dates = $data['daily']['time'] ?? [];
    $sunrises = $data['daily']['sunrise'] ?? [];
    $sunsets = $data['daily']['sunset'] ?? [];

    $tz = new DateTimeZone($timezone);

    for ($i = 0; $i < count($dates) && $i < $days; $i++) {
        if (!isset($sunrises[$i]) || !isset($sunsets[$i])) {
            continue;
        }

        // Parse sunrise/sunset (Open-Meteo returns ISO 8601)
        $sunriseDt = new DateTime($sunrises[$i], $tz);
        $sunsetDt = new DateTime($sunsets[$i], $tz);
        
        // Calculate dawn (30 min before sunrise) and dusk (30 min after sunset)
        $dawnDt = clone $sunriseDt;
        $dawnDt->modify('-30 minutes');
        $duskDt = clone $sunsetDt;
        $duskDt->modify('+30 minutes');

        $times[] = [
            'date' => $dates[$i],
            'sunrise' => formatIso8601($sunriseDt),
            'sunset' => formatIso8601($sunsetDt),
            'dawn' => formatIso8601($dawnDt),
            'dusk' => formatIso8601($duskDt)
        ];
    }

    $dt = getTimezoneDateTime($timezone);
    $result = [
        'location' => ['lat' => $lat, 'lng' => $lng],
        'timezone' => $timezone,
        'times' => $times,
        'cached' => false,
        'cached_at' => formatIso8601($dt)
    ];

    // Cache result (7 days)
    $ttl = defined('CACHE_TTL_SUN') ? CACHE_TTL_SUN : 604800;
    $cache->set('sun', $cacheKey, $result, $ttl);

    sendJson(['error' => false, 'data' => $result]);

} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

