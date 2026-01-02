<?php
/**
 * Forecast Aggregator Endpoint (PRIMARY)
 * PHP 7.3.33 compatible
 * 
 * This is the PRIMARY endpoint for frontend.
 * Aggregates weather, sun, and tides internally.
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
require_once __DIR__ . '/lib/LocationHelper.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $ip = getClientIp();
    $limitCheck = $rateLimiter->checkLimit($ip, 'forecast');
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
    
    // Optional: target species (comma-separated list of species IDs)
    $targetSpecies = [];
    if (isset($_GET['target_species']) && !empty($_GET['target_species'])) {
        $targetSpeciesRaw = trim($_GET['target_species']);
        $targetSpecies = array_filter(array_map('trim', explode(',', $targetSpeciesRaw)));
    }
    
    if ($lat === false || $lng === false) {
        sendError('Invalid latitude or longitude. Both are required.', 'VALIDATION_ERROR', [], 400);
    }
    
    if ($days === false) {
        sendError('Invalid days parameter. Must be between 1 and 14.', 'VALIDATION_ERROR', [], 400);
    }
    
    if ($start === false && isset($_GET['start'])) {
        sendError('Invalid date format. Use YYYY-MM-DD (e.g., 2025-12-26).', 'VALIDATION_ERROR', [], 400);
    }
    
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'Australia/Melbourne';
    
    // Default start date to today in the specified timezone
    if ($start === false) {
        $dt = new DateTime('now', new DateTimeZone($timezone));
        $start = $dt->format('Y-m-d');
    }
    
    // Check for refresh bypass
    $refresh = isset($_GET['refresh']) && ($_GET['refresh'] === 'true' || $_GET['refresh'] === '1');
    
    // Build cache key: lat|lng|start|days|timezone|rules_version
    // rules_version: simple hash of species rules count (bumps when rules change)
    $db = Database::getInstance()->getPdo();
    $rulesCount = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    $rulesVersion = (string)(int)$rulesCount; // Simple version based on rules count
    $cacheKey = $lat . '|' . $lng . '|' . $start . '|' . $days . '|' . $timezone . '|' . $rulesVersion;
    
    // Check forecast-level cache (unless refresh requested)
    if (!$refresh) {
        $cache = new Cache();
        $cachedResponse = $cache->get('forecast', $cacheKey);
        if ($cachedResponse !== null) {
            // Return cached response immediately
            sendJson($cachedResponse);
        }
    }
    
    // Find nearest location using haversine distance (within 40km)
    $nearestLocation = findNearestLocation($db, $lat, $lng, 40);
    
    $locationName = 'Unknown Location';
    $locationRegion = null;
    $locationWarning = null;
    
    if ($nearestLocation) {
        $locationName = $nearestLocation['name'];
        $locationRegion = $nearestLocation['region'];
    } else {
        $locationWarning = 'No nearby saved location; using coordinates only';
    }

    // Opportunistic cache cleanup (probabilistic: ~5% of requests to avoid overhead)
    // Only delete expired entries, safe to run in background
    if (rand(1, 20) === 1) {
        try {
            $cache = new Cache();
            $cache->clearExpired();
        } catch (Exception $e) {
            // Silently fail - cleanup is opportunistic, not critical
        }
    }
    
    // Fetch data internally (not via HTTP)
    $weatherData = fetchWeatherData($lat, $lng, $start, $days, $timezone);
    $sunData = fetchSunData($lat, $lng, $start, $days, $timezone);
    
    // For tides, we need to call the tides endpoint logic
    // For MVP, we'll check cache and if not available, generate mock tides
    $tidesData = fetchTidesData($lat, $lng, $start, $days, $timezone);
    
    // If tides not cached, we'll generate mock tides in the loop
    if (!$tidesData) {
        // Will generate mock tides per day in the loop
        $tidesData = ['tides' => []];
    }
    
    if (!$weatherData || !$sunData) {
        sendError('Failed to fetch required data', 'DATA_FETCH_ERROR', [], 503);
    }

    // Build tides index by date for O(1) lookup (optimization: avoid O(n) search per day)
    $tidesIndex = [];
    if ($tidesData && isset($tidesData['tides']) && is_array($tidesData['tides'])) {
        foreach ($tidesData['tides'] as $tideDay) {
            if (isset($tideDay['date'])) {
                $tidesIndex[$tideDay['date']] = $tideDay;
            }
        }
    }

    // Build forecast array
    $forecast = [];
    
    // Get current month in the specified timezone
    $dtNow = new DateTime('now', new DateTimeZone($timezone));
    $currentMonth = (int)$dtNow->format('n');
    
    // Load species rules once per request (optimization: avoid N+1 queries)
    // This includes all columns needed for scoring, recommendations, and gear
    $stmt = $db->prepare(
        "SELECT species_id, common_name, preferred_tide_state, preferred_wind_max, preferred_conditions,
                gear_bait, gear_lure, gear_line_weight, gear_leader, gear_rig
         FROM species_rules 
         WHERE (season_start_month <= ? AND season_end_month >= ?)
         OR (season_start_month > season_end_month AND (season_start_month <= ? OR season_end_month >= ?))
         ORDER BY species_id"
    );
    $stmt->execute([$currentMonth, $currentMonth, $currentMonth, $currentMonth]);
    $allSpeciesRules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build gear index by species_id for O(1) lookup
    $gearIndex = [];
    foreach ($allSpeciesRules as $rule) {
        $gearIndex[$rule['species_id']] = $rule;
    }
    
    // Parse start date in the specified timezone
    $startDate = DateTime::createFromFormat('Y-m-d', $start, new DateTimeZone($timezone));
    if ($startDate === false) {
        sendError('Invalid start date', 'VALIDATION_ERROR', [], 400);
    }
    
    for ($i = 0; $i < $days; $i++) {
        $dateObj = clone $startDate;
        $dateObj->modify("+$i days");
        $date = $dateObj->format('Y-m-d');
        
        $weather = $weatherData['forecast'][$i] ?? null;
        $sun = $sunData['times'][$i] ?? null;
        
        // O(1) lookup instead of O(n) linear search
        $tides = isset($tidesIndex[$date]) ? $tidesIndex[$date] : null;
        
        // If no tides found for this date, generate mock tides for this day only
        if (!$tides || empty($tides['events'])) {
            // generateMockTides is available from ForecastData.php
            $mockTides = generateMockTides($lat, $lng, $timezone, $date, 1);
            if (!empty($mockTides) && isset($mockTides[0])) {
                $tides = $mockTides[0];
            } else {
                // Final fallback: minimal structure
                $tides = ['events' => [], 'change_windows' => []];
            }
        }
        
        if (!$weather || !$sun) {
            continue; // Skip if data missing
        }

        // Calculate scores
        $weatherScore = Scoring::calculateWeatherScore($weather);
        $tideScore = $tides ? Scoring::calculateTideScore($tides) : 50; // Default if no tides
        $dawnDuskScore = $tides ? Scoring::calculateDawnDuskScore($sun, $tides) : 20;
        $seasonalityScore = Scoring::calculateSeasonalityScore($allSpeciesRules);
        $score = Scoring::calculateScore($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore);

        // Calculate best bite windows
        $bestBiteWindows = calculateBestBiteWindows($sun, $tides);

        // Get recommended species (using cached rules)
        $recommendedSpecies = getRecommendedSpecies($allSpeciesRules, $weather, $tides);

        // Get gear suggestions - prioritize target species if provided
        $speciesForGear = !empty($targetSpecies) ? $targetSpecies : ($recommendedSpecies && !empty($recommendedSpecies) ? [$recommendedSpecies[0]['id']] : []);
        $gearSuggestions = getGearSuggestions($db, $gearIndex, $speciesForGear, $recommendedSpecies);

        // Generate reasons (always return 2-4 reasons)
        $reasons = generateReasons($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore, $weather, $tides, $sun, $tidesData);

        $forecast[] = [
            'date' => $date,
            'score' => $score,
            'weather' => $weather,
            'sun' => $sun,
            'tides' => $tides ?: ['events' => [], 'change_windows' => []],
            'best_bite_windows' => $bestBiteWindows,
            'recommended_species' => $recommendedSpecies,
            'gear_suggestions' => $gearSuggestions,
            'reasons' => $reasons
        ];
    }

    $dt = getTimezoneDateTime($timezone);
    $result = [
        'location' => [
            'lat' => $lat,
            'lng' => $lng,
            'name' => $locationName,
            'region' => $locationRegion
        ],
        'timezone' => $timezone,
        'forecast' => $forecast,
        'cached' => ($weatherData['cached'] ?? false) && ($sunData['cached'] ?? false),
        'cached_at' => formatIso8601($dt)
    ];
    
    // Add warning if no nearby location found
    if ($locationWarning) {
        $result['warning'] = $locationWarning;
    }

    // Build final response
    $response = ['error' => false, 'data' => $result];
    
    // Cache forecast response (15 minutes TTL - shorter than individual provider caches)
    $forecastTtl = defined('CACHE_TTL_FORECAST') ? CACHE_TTL_FORECAST : 900; // 15 minutes default
    $cache = new Cache();
    $cache->set('forecast', $cacheKey, $response, $forecastTtl);
    
    sendJson($response);

} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

function calculateBestBiteWindows($sun, $tides) {
    if (!$tides || !isset($tides['change_windows'])) {
        return [];
    }

    $windows = [];
    $changeWindows = $tides['change_windows'] ?? [];
    
    if (!isset($sun['dawn']) || !isset($sun['dusk']) || !isset($sun['sunrise']) || !isset($sun['sunset'])) {
        return [];
    }

    $sunriseDt = new DateTime($sun['sunrise']);
    $dawnStart = clone $sunriseDt;
    $dawnStart->modify('-30 minutes');
    $dawnEnd = clone $sunriseDt;
    $dawnEnd->modify('+2 hours');

    $sunsetDt = new DateTime($sun['sunset']);
    $duskStart = clone $sunsetDt;
    $duskStart->modify('-2 hours');
    $duskEnd = clone $sunsetDt;
    $duskEnd->modify('+30 minutes');

    foreach ($changeWindows as $tideWindow) {
        $tideStart = new DateTime($tideWindow['start']);
        $tideEnd = new DateTime($tideWindow['end']);

        // Check overlap with dawn
        $overlap = calculateOverlap($tideStart, $tideEnd, $dawnStart, $dawnEnd);
        if ($overlap > 0) {
            $overlapStart = $tideStart > $dawnStart ? $tideStart : $dawnStart;
            $overlapEnd = $tideEnd < $dawnEnd ? $tideEnd : $dawnEnd;
            $quality = $overlap >= 60 ? 'excellent' : ($overlap >= 30 ? 'good' : 'fair');
            $windows[] = [
                'start' => formatIso8601($overlapStart),
                'end' => formatIso8601($overlapEnd),
                'reason' => 'dawn + ' . $tideWindow['type'] . ' tide',
                'quality' => $quality
            ];
        }

        // Check overlap with dusk
        $overlap = calculateOverlap($tideStart, $tideEnd, $duskStart, $duskEnd);
        if ($overlap > 0) {
            $overlapStart = $tideStart > $duskStart ? $tideStart : $duskStart;
            $overlapEnd = $tideEnd < $duskEnd ? $tideEnd : $duskEnd;
            $quality = $overlap >= 60 ? 'excellent' : ($overlap >= 30 ? 'good' : 'fair');
            $windows[] = [
                'start' => formatIso8601($overlapStart),
                'end' => formatIso8601($overlapEnd),
                'reason' => 'dusk + ' . $tideWindow['type'] . ' tide',
                'quality' => $quality
            ];
        }
    }

    return $windows;
}

function calculateOverlap($start1, $end1, $start2, $end2) {
    $overlapStart = $start1 > $start2 ? $start1 : $start2;
    $overlapEnd = $end1 < $end2 ? $end1 : $end2;
    
    if ($overlapStart >= $overlapEnd) {
        return 0;
    }
    
    return (int)(($overlapEnd->getTimestamp() - $overlapStart->getTimestamp()) / 60);
}

function getRecommendedSpecies($allSpeciesRules, $weather, $tides) {
    // Use pre-loaded species rules (optimization: avoid query in loop)
    $species = $allSpeciesRules;

    if (empty($species)) {
        return [];
    }

    $windSpeed = $weather['wind_speed'] ?? 0;
    $precipitation = $weather['precipitation'] ?? 0;
    $tideType = null;
    
    // Get current tide state from first change window
    if ($tides && !empty($tides['change_windows'])) {
        $tideType = $tides['change_windows'][0]['type'] ?? null;
    }

    $result = [];
    foreach ($species as $s) {
        $confidence = 0.6; // Base confidence for being in season
        
        // Check wind preference
        $preferredWindMax = $s['preferred_wind_max'] ? (float)$s['preferred_wind_max'] : 30;
        if ($windSpeed <= $preferredWindMax) {
            $confidence += 0.15;
        } else {
            $confidence -= 0.2; // Reduce confidence for strong winds
        }
        
        // Check precipitation
        if ($precipitation <= 2) {
            $confidence += 0.1;
        } elseif ($precipitation > 5) {
            $confidence -= 0.15; // Heavy rain reduces confidence
        }
        
        // Check tide preference
        if ($tideType) {
            if ($s['preferred_tide_state'] === 'any') {
                $confidence += 0.05;
            } elseif ($s['preferred_tide_state'] === $tideType) {
                $confidence += 0.1;
            }
        }
        
        // Ensure confidence is in valid range
        $confidence = max(0.3, min(1.0, $confidence));
        
        // Build "why" explanation
        $whyParts = [];
        $whyParts[] = 'In season';
        if ($windSpeed <= $preferredWindMax) {
            $whyParts[] = 'wind conditions suitable';
        }
        if ($precipitation <= 2) {
            $whyParts[] = 'minimal rain';
        }
        if ($tideType && $s['preferred_tide_state'] === $tideType) {
            $whyParts[] = 'preferred tide state (' . $tideType . ')';
        }
        
        $result[] = [
            'id' => $s['species_id'],
            'name' => $s['common_name'],
            'confidence' => round($confidence, 2),
            'why' => implode(', ', $whyParts)
        ];
    }

    // Sort by confidence (highest first)
    usort($result, function($a, $b) {
        if ($a['confidence'] == $b['confidence']) {
            return 0;
        }
        return ($a['confidence'] > $b['confidence']) ? -1 : 1;
    });

    // Return top 3 species
    return array_slice($result, 0, 3);
}

function getGearSuggestions($db, $gearIndex, $targetSpeciesIds, $recommendedSpecies) {
    // Default fallback
    $defaultGear = [
        'bait' => [],
        'lure' => [],
        'line_weight' => '8-15lb',
        'leader' => '10-20lb',
        'rig' => 'paternoster or running sinker',
        'tackle' => []
    ];

    // Determine which species to use for tackle recommendations
    $speciesIdsToUse = [];
    if (!empty($targetSpeciesIds)) {
        // Use target species if provided
        $speciesIdsToUse = $targetSpeciesIds;
    } elseif (!empty($recommendedSpecies) && isset($recommendedSpecies[0]['id'])) {
        // Fall back to recommended species
        $speciesIdsToUse = [$recommendedSpecies[0]['id']];
    } else {
        // No species available, return default
        return $defaultGear;
    }

    // Load tackle items for target species from database
    $tackleItems = [];
    if (!empty($speciesIdsToUse)) {
        $placeholders = implode(',', array_fill(0, count($speciesIdsToUse), '?'));
        $stmt = $db->prepare("
            SELECT ti.id, ti.name, ti.category, ti.notes, st.priority
            FROM tackle_items ti
            INNER JOIN species_tackle st ON ti.id = st.tackle_item_id
            WHERE st.species_id IN ($placeholders)
            ORDER BY st.species_id, st.priority, ti.name
        ");
        $stmt->execute($speciesIdsToUse);
        $tackleRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by category and priority
        foreach ($tackleRows as $row) {
            $category = $row['category'];
            $priority = (int)$row['priority'];
            if (!isset($tackleItems[$category])) {
                $tackleItems[$category] = [];
            }
            if (!isset($tackleItems[$category][$priority])) {
                $tackleItems[$category][$priority] = [];
            }
            $tackleItems[$category][$priority][] = [
                'name' => $row['name'],
                'notes' => $row['notes']
            ];
        }
    }

    // Get legacy gear from species_rules (for backward compatibility)
    $legacyGear = null;
    $primarySpeciesId = $speciesIdsToUse[0];
    if (isset($gearIndex[$primarySpeciesId])) {
        $legacyGear = $gearIndex[$primarySpeciesId];
    }

    // Build response - prioritize tackle items, fall back to legacy gear
    $result = [
        'bait' => [],
        'lure' => [],
        'line_weight' => $legacyGear['gear_line_weight'] ?? '8-15lb',
        'leader' => $legacyGear['gear_leader'] ?? '10-20lb',
        'rig' => $legacyGear['gear_rig'] ?? 'paternoster or running sinker',
        'tackle' => []
    ];

    // Extract bait from tackle items (priority 1 items)
    if (isset($tackleItems['bait'][1])) {
        $result['bait'] = array_column($tackleItems['bait'][1], 'name');
    } elseif ($legacyGear && $legacyGear['gear_bait']) {
        $result['bait'] = array_map('trim', explode(',', $legacyGear['gear_bait']));
    }

    // Extract lures from tackle items (soft_plastics, metal_lures, poppers, hardbody_lures, squid_jigs)
    $lureCategories = ['soft_plastics', 'metal_lures', 'poppers', 'hardbody_lures', 'squid_jigs'];
    foreach ($lureCategories as $cat) {
        if (isset($tackleItems[$cat][1])) {
            $result['lure'] = array_merge($result['lure'], array_column($tackleItems[$cat][1], 'name'));
        }
    }
    if (empty($result['lure']) && $legacyGear && $legacyGear['gear_lure']) {
        $result['lure'] = array_map('trim', explode(',', $legacyGear['gear_lure']));
    }

    // Build tackle array by category (for frontend display)
    foreach ($tackleItems as $category => $priorities) {
        $categoryItems = [];
        // Flatten priorities (1 = essential, 2 = recommended, 3 = optional)
        ksort($priorities);
        foreach ($priorities as $priority => $items) {
            foreach ($items as $item) {
                $categoryItems[] = [
                    'name' => $item['name'],
                    'priority' => $priority,
                    'notes' => $item['notes']
                ];
            }
        }
        if (!empty($categoryItems)) {
            $result['tackle'][] = [
                'category' => $category,
                'items' => $categoryItems
            ];
        }
    }

    return $result;
}

function generateReasons($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore, $weather, $tides, $sun, $tidesData = null) {
    $reasons = [];
    $windSpeed = $weather['wind_speed'] ?? 0;
    $precipitation = $weather['precipitation'] ?? 0;
    $cloudCover = $weather['cloud_cover'] ?? 0;
    $isMockTides = ($tidesData && isset($tidesData['mock_tides']) && $tidesData['mock_tides']) ? true : false;

    // Weather reason (always include)
    if ($weatherScore >= 70) {
        $reasons[] = [
            'title' => 'Excellent weather conditions',
            'detail' => 'Light winds (' . round($windSpeed, 1) . ' km/h), ' . ($precipitation == 0 ? 'no precipitation' : 'minimal rain') . ', ' . ($cloudCover < 30 ? 'clear skies' : 'partly cloudy'),
            'contribution_points' => (int)($weatherScore * 0.35),
            'severity' => 'positive',
            'category' => 'weather'
        ];
    } elseif ($weatherScore >= 50) {
        $reasons[] = [
            'title' => 'Moderate weather conditions',
            'detail' => 'Wind speed ' . round($windSpeed, 1) . ' km/h, ' . ($precipitation > 0 ? round($precipitation, 1) . 'mm rain' : 'no precipitation'),
            'contribution_points' => (int)($weatherScore * 0.35),
            'severity' => 'neutral',
            'category' => 'weather'
        ];
    } else {
        $reasons[] = [
            'title' => 'Poor weather conditions',
            'detail' => 'Strong winds (' . round($windSpeed, 1) . ' km/h)' . ($precipitation > 5 ? ' and heavy rain (' . round($precipitation, 1) . 'mm)' : '') . ' may affect fishing',
            'contribution_points' => (int)($weatherScore * 0.35),
            'severity' => 'negative',
            'category' => 'weather'
        ];
    }

    // Tide reason (always include)
    $tideEventCount = isset($tides['events']) ? count($tides['events']) : 0;
    if ($tideScore >= 70) {
        $reasons[] = [
            'title' => 'Strong tide activity',
            'detail' => $tideEventCount . ' tide changes today with good range' . ($isMockTides ? ' (estimated tides)' : ''),
            'contribution_points' => (int)($tideScore * 0.30),
            'severity' => 'positive',
            'category' => 'tide'
        ];
    } elseif ($tideScore >= 50) {
        $reasons[] = [
            'title' => 'Moderate tide activity',
            'detail' => $tideEventCount . ' tide changes expected' . ($isMockTides ? ' (estimated tides)' : ''),
            'contribution_points' => (int)($tideScore * 0.30),
            'severity' => 'neutral',
            'category' => 'tide'
        ];
    } else {
        $reasons[] = [
            'title' => 'Limited tide activity',
            'detail' => 'Fewer tide changes may reduce fish activity' . ($isMockTides ? ' (estimated tides)' : ''),
            'contribution_points' => (int)($tideScore * 0.30),
            'severity' => 'negative',
            'category' => 'tide'
        ];
    }

    // Dawn/dusk reason (if score is meaningful)
    if ($dawnDuskScore >= 50) {
        $reasons[] = [
            'title' => 'Dawn/dusk-tide overlap',
            'detail' => 'Optimal feeding windows during dawn or dusk periods',
            'contribution_points' => (int)($dawnDuskScore * 0.20),
            'severity' => 'positive',
            'category' => 'dawn_dusk'
        ];
    }

    // Seasonality/species reason (always include)
    if ($seasonalityScore >= 60) {
        $reasons[] = [
            'title' => 'Peak season for multiple species',
            'detail' => 'Several target species are in their peak season this month',
            'contribution_points' => (int)($seasonalityScore * 0.15),
            'severity' => 'positive',
            'category' => 'seasonality'
        ];
    } elseif ($seasonalityScore >= 40) {
        $reasons[] = [
            'title' => 'Some species in season',
            'detail' => 'A few target species are active this month',
            'contribution_points' => (int)($seasonalityScore * 0.15),
            'severity' => 'neutral',
            'category' => 'seasonality'
        ];
    } else {
        $reasons[] = [
            'title' => 'Off-season for most species',
            'detail' => 'Fewer species are in peak season, but fishing is still possible',
            'contribution_points' => (int)($seasonalityScore * 0.15),
            'severity' => 'negative',
            'category' => 'seasonality'
        ];
    }

    // Ensure we have at least 2 reasons (we should have 3-4)
    return $reasons;
}

