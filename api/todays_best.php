<?php
/**
 * Today's Best Locations Endpoint
 * PHP 7.3.33 compatible
 * 
 * Returns top N locations by today's forecast score
 * Cached per (date, state, region, species_id) for 30-60 minutes
 */

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Cache.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/RateLimiter.php';
require_once __DIR__ . '/lib/Scoring.php';
require_once __DIR__ . '/lib/ForecastData.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $ip = getClientIp();
    $limitCheck = $rateLimiter->checkLimit($ip, 'todays_best');
    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $limitCheck['retry_after']);
        sendError('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', [], 429);
    }

    $db = Database::getInstance()->getPdo();
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Australia/Melbourne';
    
    // Get today's date in the specified timezone
    $dtNow = new DateTime('now', new DateTimeZone($timezone));
    $today = $dtNow->format('Y-m-d');
    
    // Validate query params
    $state = isset($_GET['state']) ? strtoupper(trim($_GET['state'])) : 'VIC';
    $region = isset($_GET['region']) ? trim($_GET['region']) : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $speciesId = isset($_GET['species_id']) ? trim($_GET['species_id']) : null;
    
    // Validate limit
    if ($limit < 1 || $limit > 20) {
        $limit = 5;
    }
    
    // Build cache key
    $cacheKeyParts = [$today, $state];
    if ($region) {
        $cacheKeyParts[] = $region;
    }
    if ($speciesId) {
        $cacheKeyParts[] = $speciesId;
    }
    $cacheKey = 'todays_best:' . implode(':', $cacheKeyParts);
    
    // Check cache (30-60 minutes TTL)
    $cache = new Cache();
    $cachedResult = $cache->get('todays_best', $cacheKey);
    if ($cachedResult !== null) {
        sendJson([
            'error' => false,
            'data' => [
                'date' => $today,
                'timezone' => $timezone,
                'locations' => $cachedResult,
                'cached' => true,
                'cached_at' => formatIso8601($dtNow)
            ]
        ]);
        return;
    }
    
    // Query locations filtered by state/region
    $sql = "SELECT id, name, region, latitude, longitude, timezone FROM locations WHERE state = ?";
    $params = [$state];
    
    if ($region) {
        $sql .= " AND region = ?";
        $params[] = $region;
    }
    
    $sql .= " ORDER BY name LIMIT 200"; // Limit to 200 for performance
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($locations)) {
        sendJson([
            'error' => false,
            'data' => [
                'date' => $today,
                'timezone' => $timezone,
                'locations' => [],
                'cached' => false
            ]
        ]);
        return;
    }
    
    // Get current month for species rules
    $currentMonth = (int)$dtNow->format('n');
    
    // Load species rules once (optimization)
    $stmt = $db->prepare(
        "SELECT species_id, common_name, preferred_tide_state, preferred_wind_max, preferred_conditions
         FROM species_rules 
         WHERE (season_start_month <= ? AND season_end_month >= ?)
         OR (season_start_month > season_end_month AND (season_start_month <= ? OR season_end_month >= ?))
         ORDER BY species_id"
    );
    $stmt->execute([$currentMonth, $currentMonth, $currentMonth, $currentMonth]);
    $allSpeciesRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter by species_id if provided
    if ($speciesId) {
        $allSpeciesRules = array_filter($allSpeciesRules, function($rule) use ($speciesId) {
            return $rule['species_id'] === $speciesId;
        });
    }
    
    // Calculate scores for each location
    $scoredLocations = [];
    
    foreach ($locations as $location) {
        $lat = (float)$location['latitude'];
        $lng = (float)$location['longitude'];
        $locTimezone = $location['timezone'] ?: $timezone;
        
        // Fetch weather and sun data for today only
        $weatherData = fetchWeatherData($lat, $lng, $today, 1, $locTimezone);
        $sunData = fetchSunData($lat, $lng, $today, 1, $locTimezone);
        $tidesData = fetchTidesData($lat, $lng, $today, 1, $locTimezone);
        
        if (!$weatherData || !$sunData) {
            continue; // Skip if data unavailable
        }
        
        $weather = $weatherData['forecast'][0] ?? null;
        $sun = $sunData['times'][0] ?? null;
        
        if (!$weather || !$sun) {
            continue;
        }
        
        // Get tides for today
        $tides = null;
        if ($tidesData && isset($tidesData['tides']) && is_array($tidesData['tides']) && !empty($tidesData['tides'])) {
            $tides = $tidesData['tides'][0] ?? null;
        }
        
        // Generate mock tides if not available
        if (!$tides || empty($tides['events'])) {
            $mockTides = generateMockTides($lat, $lng, $locTimezone, $today, 1);
            if (!empty($mockTides) && isset($mockTides[0])) {
                $tides = $mockTides[0];
            } else {
                $tides = ['events' => [], 'change_windows' => []];
            }
        }
        
        // Calculate scores
        $weatherScore = Scoring::calculateWeatherScore($weather);
        $tideScore = $tides ? Scoring::calculateTideScore($tides) : 50;
        $dawnDuskScore = $tides ? Scoring::calculateDawnDuskScore($sun, $tides) : 20;
        $seasonalityScore = Scoring::calculateSeasonalityScore($allSpeciesRules);
        $score = Scoring::calculateScore($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore);
        
        // Generate short reason string
        $reasons = [];
        if ($weatherScore >= 70) {
            $reasons[] = 'excellent weather';
        } elseif ($weatherScore >= 50) {
            $reasons[] = 'good weather';
        }
        if ($tideScore >= 70) {
            $reasons[] = 'favorable tides';
        }
        if ($dawnDuskScore >= 50) {
            $reasons[] = 'good bite windows';
        }
        if ($seasonalityScore >= 60) {
            $reasons[] = 'species in season';
        }
        
        $whyString = !empty($reasons) ? implode(', ', $reasons) : 'decent conditions';
        
        $scoredLocations[] = [
            'id' => (int)$location['id'],
            'name' => $location['name'],
            'region' => $location['region'],
            'lat' => $lat,
            'lng' => $lng,
            'score' => $score,
            'why' => $whyString
        ];
    }
    
    // Sort by score (highest first)
    usort($scoredLocations, function($a, $b) {
        if ($a['score'] == $b['score']) {
            return 0;
        }
        return ($a['score'] > $b['score']) ? -1 : 1;
    });
    
    // Take top N
    $topLocations = array_slice($scoredLocations, 0, $limit);
    
    // Cache result (45 minutes TTL - between 30-60 as requested)
    $cacheTtl = 45 * 60; // 45 minutes
    $cache->set('todays_best', $cacheKey, $topLocations, $cacheTtl);
    
    sendJson([
        'error' => false,
        'data' => [
            'date' => $today,
            'timezone' => $timezone,
            'locations' => $topLocations,
            'cached' => false,
            'cached_at' => formatIso8601($dtNow)
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

