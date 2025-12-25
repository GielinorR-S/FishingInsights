<?php
/**
 * Weather Data Endpoint
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
    $limitCheck = $rateLimiter->checkLimit($ip, 'weather');
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
    $cached = $cache->get('weather', $cacheKey);
    if ($cached !== null) {
        $cached['cached'] = true;
        sendJson(['error' => false, 'data' => $cached]);
    }

    // Fetch from Open-Meteo
    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $lat,
        'longitude' => $lng,
        'daily' => 'temperature_2m_max,temperature_2m_min,windspeed_10m_max,winddirection_10m_dominant,precipitation_sum,cloudcover_mean',
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
        sendError('Weather API request failed', 'EXTERNAL_API_ERROR', [], 503);
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['daily'])) {
        sendError('Invalid weather API response', 'EXTERNAL_API_ERROR', [], 503);
    }

    // Normalize response
    $forecast = [];
    $dates = $data['daily']['time'] ?? [];
    $tempMax = $data['daily']['temperature_2m_max'] ?? [];
    $tempMin = $data['daily']['temperature_2m_min'] ?? [];
    $windSpeed = $data['daily']['windspeed_10m_max'] ?? [];
    $windDir = $data['daily']['winddirection_10m_dominant'] ?? [];
    $precip = $data['daily']['precipitation_sum'] ?? [];
    $cloudCover = $data['daily']['cloudcover_mean'] ?? [];

    for ($i = 0; $i < count($dates) && $i < $days; $i++) {
        $cloud = isset($cloudCover[$i]) ? (int)$cloudCover[$i] : 0;
        $conditions = 'clear';
        if ($cloud > 80) {
            $conditions = 'overcast';
        } elseif ($cloud > 60) {
            $conditions = 'mostly_cloudy';
        } elseif ($cloud > 30) {
            $conditions = 'partly_cloudy';
        }

        $forecast[] = [
            'date' => $dates[$i],
            'temperature_max' => isset($tempMax[$i]) ? (float)$tempMax[$i] : 0,
            'temperature_min' => isset($tempMin[$i]) ? (float)$tempMin[$i] : 0,
            'wind_speed' => isset($windSpeed[$i]) ? (float)$windSpeed[$i] : 0,
            'wind_direction' => isset($windDir[$i]) ? (int)$windDir[$i] : 0,
            'precipitation' => isset($precip[$i]) ? (float)$precip[$i] : 0,
            'cloud_cover' => $cloud,
            'conditions' => $conditions
        ];
    }

    $dt = getTimezoneDateTime($timezone);
    $result = [
        'location' => ['lat' => $lat, 'lng' => $lng],
        'timezone' => $timezone,
        'forecast' => $forecast,
        'cached' => false,
        'cached_at' => formatIso8601($dt)
    ];

    // Cache result
    $ttl = defined('CACHE_TTL_WEATHER') ? CACHE_TTL_WEATHER : 3600;
    $cache->set('weather', $cacheKey, $result, $ttl);

    sendJson(['error' => false, 'data' => $result]);

} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

