<?php
/**
 * Tides Data Endpoint
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
    $limitCheck = $rateLimiter->checkLimit($ip, 'tides');
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
    $cached = $cache->get('tides', $cacheKey);
    if ($cached !== null) {
        $cached['cached'] = true;
        sendJson(['error' => false, 'data' => $cached]);
    }

    $mockTides = false;
    $apiKey = defined('WORLDTIDES_API_KEY') ? WORLDTIDES_API_KEY : '';
    
    // Try WorldTides API if key exists
    if (!empty($apiKey)) {
        $url = 'https://www.worldtides.info/api?' . http_build_query([
            'lat' => $lat,
            'lon' => $lng,
            'days' => $days,
            'key' => $apiKey
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if ($data && isset($data['heights'])) {
                // Process WorldTides response
                $tides = processWorldTidesResponse($data, $timezone, $start, $days);
                if ($tides) {
                    $dt = getTimezoneDateTime($timezone);
                    $result = [
                        'location' => ['lat' => $lat, 'lng' => $lng],
                        'timezone' => $timezone,
                        'tides' => $tides,
                        'cached' => false,
                        'cached_at' => formatIso8601($dt),
                        'mock_tides' => false
                    ];

                    // Cache result (12 hours)
                    $ttl = defined('CACHE_TTL_TIDES') ? CACHE_TTL_TIDES : 43200;
                    $cache->set('tides', $cacheKey, $result, $ttl);

                    sendJson(['error' => false, 'data' => $result]);
                }
            }
        }
        // If API fails, fall through to mock mode
        $mockTides = true;
    } else {
        $mockTides = true;
    }

    // Mock tides mode
    if ($mockTides) {
        $tides = generateMockTides($lat, $lng, $timezone, $start, $days);
        $dt = getTimezoneDateTime($timezone);
        $result = [
            'location' => ['lat' => $lat, 'lng' => $lng],
            'timezone' => $timezone,
            'tides' => $tides,
            'cached' => false,
            'cached_at' => formatIso8601($dt),
            'mock_tides' => true
        ];

        // Cache mock tides (12 hours)
        $ttl = defined('CACHE_TTL_TIDES') ? CACHE_TTL_TIDES : 43200;
        $cache->set('tides', $cacheKey, $result, $ttl);

        sendJson(['error' => false, 'data' => $result]);
    }

} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

function processWorldTidesResponse($data, $timezone, $startDate, $days) {
    $tz = new DateTimeZone($timezone);
    $tidesByDate = [];
    
    if (!isset($data['heights']) || !is_array($data['heights'])) {
        return null;
    }

    foreach ($data['heights'] as $height) {
        if (!isset($height['date']) || !isset($height['height'])) {
            continue;
        }

        $dt = new DateTime($height['date'], $tz);
        $date = $dt->format('Y-m-d');
        
        if (!isset($tidesByDate[$date])) {
            $tidesByDate[$date] = [
                'date' => $date,
                'events' => [],
                'change_windows' => []
            ];
        }

        // Determine if high or low (simplified: positive = high, negative = low)
        $type = $height['height'] > 0 ? 'high' : 'low';
        
        $tidesByDate[$date]['events'][] = [
            'time' => formatIso8601($dt),
            'type' => $type,
            'height' => abs($height['height'])
        ];
    }

    // Compute change windows: +/- 1 hour around EACH event
    $result = [];
    foreach ($tidesByDate as $date => $dayData) {
        $changeWindows = [];
        foreach ($dayData['events'] as $event) {
            $eventDt = new DateTime($event['time'], $tz);
            $windowStart = clone $eventDt;
            $windowStart->modify('-1 hour');
            $windowEnd = clone $eventDt;
            $windowEnd->modify('+1 hour');
            
            $changeWindows[] = [
                'start' => formatIso8601($windowStart),
                'end' => formatIso8601($windowEnd),
                'type' => $event['type'] === 'low' ? 'rising' : 'falling',
                'event_time' => $event['time'],
                'event_type' => $event['type']
            ];
        }
        
        $result[] = [
            'date' => $date,
            'events' => $dayData['events'],
            'change_windows' => $changeWindows
        ];
    }

    return $result;
}

function generateMockTides($lat, $lng, $timezone, $startDate, $days) {
    $tz = new DateTimeZone($timezone);
    $result = [];
    
    // Estimate amplitude based on location (1.0-2.0m for Victorian locations)
    $amplitude = 1.5;
    if ($lat < -38) {
        $amplitude = 1.8; // More southern = larger tides
    }

    $start = new DateTime($startDate, $tz);
    
    // Base times for first day (approximate 6.2 hour cycle between events)
    // Typical pattern: low ~2:00, high ~8:00, low ~14:00, high ~20:00
    $baseHour = 2.0 + (($lng - 144) * 0.1); // Adjust for longitude
    
    for ($day = 0; $day < $days; $day++) {
        $current = clone $start;
        $current->modify("+$day days");
        $date = $current->format('Y-m-d');
        
        $events = [];
        $changeWindows = [];
        
        // Generate 4 tide events per day (low/high/low/high)
        // Times shift slightly each day (~45 minutes)
        $dayOffset = $day * 0.75; // 45 minutes per day
        
        $eventTimes = [
            ['hour' => $baseHour + $dayOffset, 'type' => 'low', 'height' => 0.4 + (rand(0, 20) / 100)],
            ['hour' => $baseHour + $dayOffset + 6.2, 'type' => 'high', 'height' => $amplitude - 0.2 + (rand(0, 40) / 100)],
            ['hour' => $baseHour + $dayOffset + 12.4, 'type' => 'low', 'height' => 0.5 + (rand(0, 20) / 100)],
            ['hour' => $baseHour + $dayOffset + 18.6, 'type' => 'high', 'height' => $amplitude - 0.1 + (rand(0, 40) / 100)]
        ];
        
        foreach ($eventTimes as $eventData) {
            $hour = $eventData['hour'];
            $eventTime = clone $current;
            
            // Handle hour overflow
            if ($hour >= 24) {
                $hour = fmod($hour, 24);
            } elseif ($hour < 0) {
                $hour = 24 + fmod($hour, 24);
            }
            
            $hours = (int)$hour;
            $minutes = (int)(($hour - $hours) * 60);
            $eventTime->setTime($hours, $minutes, 0);
            
            $events[] = [
                'time' => formatIso8601($eventTime),
                'type' => $eventData['type'],
                'height' => round($eventData['height'], 2)
            ];
            
            // Compute change window: +/- 1 hour around event
            $windowStart = clone $eventTime;
            $windowStart->modify('-1 hour');
            $windowEnd = clone $eventTime;
            $windowEnd->modify('+1 hour');
            
            $changeWindows[] = [
                'start' => formatIso8601($windowStart),
                'end' => formatIso8601($windowEnd),
                'type' => $eventData['type'] === 'low' ? 'rising' : 'falling',
                'event_time' => formatIso8601($eventTime),
                'event_type' => $eventData['type']
            ];
        }
        
        // Sort events by time
        usort($events, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });
        
        // Sort change windows by start time
        usort($changeWindows, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });
        
        $result[] = [
            'date' => $date,
            'events' => $events,
            'change_windows' => $changeWindows
        ];
    }
    
    return $result;
}

