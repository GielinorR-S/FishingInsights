<?php
/**
 * Internal Data Fetchers for Forecast Aggregation
 * PHP 7.3.33 compatible
 * 
 * These functions fetch data internally without HTTP calls
 */

require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/utils.php';

function fetchWeatherData($lat, $lng, $start, $days, $timezone) {
    $cache = new Cache();
    $cacheKey = $lat . ':' . $lng . ':' . $start . ':' . $days;
    
    // Check cache
    $cached = $cache->get('weather', $cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Fetch from Open-Meteo (same logic as weather.php)
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
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['daily'])) {
        return null;
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

    return $result;
}

function fetchSunData($lat, $lng, $start, $days, $timezone) {
    $cache = new Cache();
    $cacheKey = $lat . ':' . $lng . ':' . $start . ':' . $days;
    
    // Check cache
    $cached = $cache->get('sun', $cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Fetch from Open-Meteo (same logic as sun.php)
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
        return null;
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['daily'])) {
        return null;
    }

    // Normalize response (same as sun.php)
    $times = [];
    $dates = $data['daily']['time'] ?? [];
    $sunrises = $data['daily']['sunrise'] ?? [];
    $sunsets = $data['daily']['sunset'] ?? [];

    $tz = new DateTimeZone($timezone);

    for ($i = 0; $i < count($dates) && $i < $days; $i++) {
        if (!isset($sunrises[$i]) || !isset($sunsets[$i])) {
            continue;
        }

        $sunriseDt = new DateTime($sunrises[$i], $tz);
        $sunsetDt = new DateTime($sunsets[$i], $tz);
        
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

    return $result;
}

function fetchTidesData($lat, $lng, $start, $days, $timezone) {
    $cache = new Cache();
    $cacheKey = $lat . ':' . $lng . ':' . $start . ':' . $days;
    
    $cached = $cache->get('tides', $cacheKey);
    if ($cached !== null && isset($cached['tides']) && is_array($cached['tides'])) {
        return $cached;
    }

    // If not cached, generate mock tides
    require_once __DIR__ . '/utils.php';
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

    return $result;
}

// Include generateMockTides from tides.php
function generateMockTides($lat, $lng, $timezone, $startDate, $days) {
    $tz = new DateTimeZone($timezone);
    $result = [];
    
    $amplitude = 1.5;
    if ($lat < -38) {
        $amplitude = 1.8;
    }

    $start = new DateTime($startDate, $tz);
    $baseHour = 2.0 + (($lng - 144) * 0.1);
    
    for ($day = 0; $day < $days; $day++) {
        $current = clone $start;
        $current->modify("+$day days");
        $date = $current->format('Y-m-d');
        
        $events = [];
        $changeWindows = [];
        
        $dayOffset = $day * 0.75;
        
        $eventTimes = [
            ['hour' => $baseHour + $dayOffset, 'type' => 'low', 'height' => 0.4 + (rand(0, 20) / 100)],
            ['hour' => $baseHour + $dayOffset + 6.2, 'type' => 'high', 'height' => $amplitude - 0.2 + (rand(0, 40) / 100)],
            ['hour' => $baseHour + $dayOffset + 12.4, 'type' => 'low', 'height' => 0.5 + (rand(0, 20) / 100)],
            ['hour' => $baseHour + $dayOffset + 18.6, 'type' => 'high', 'height' => $amplitude - 0.1 + (rand(0, 40) / 100)]
        ];
        
        foreach ($eventTimes as $eventData) {
            $hour = $eventData['hour'];
            $eventTime = clone $current;
            
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
        
        usort($events, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });
        
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

