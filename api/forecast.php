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
    
    // Find nearest location using haversine distance
    $db = Database::getInstance()->getPdo();
    $nearestLocation = findNearestLocation($db, $lat, $lng, 30);
    
    $locationName = 'Unknown Location';
    $locationRegion = null;
    $locationWarning = null;
    
    if ($nearestLocation) {
        $locationName = $nearestLocation['name'];
        $locationRegion = $nearestLocation['region'];
    } else {
        $locationWarning = 'No nearby saved location; using coordinates only';
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

    // Build forecast array
    $forecast = [];
    $currentMonth = (int)date('n');
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime($start . " +$i days"));
        
        $weather = $weatherData['forecast'][$i] ?? null;
        $sun = $sunData['times'][$i] ?? null;
        $tides = null;
        
        // Find tides for this date
        if ($tidesData && isset($tidesData['tides']) && is_array($tidesData['tides'])) {
            foreach ($tidesData['tides'] as $tideDay) {
                if (isset($tideDay['date']) && $tideDay['date'] === $date) {
                    $tides = $tideDay;
                    break;
                }
            }
        }
        
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
        $seasonalityScore = Scoring::calculateSeasonalityScore($db, $currentMonth);
        $score = Scoring::calculateScore($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore);

        // Calculate best bite windows
        $bestBiteWindows = calculateBestBiteWindows($sun, $tides);

        // Get recommended species
        $recommendedSpecies = getRecommendedSpecies($db, $currentMonth, $weather, $tides);

        // Get gear suggestions (from top recommended species)
        $gearSuggestions = getGearSuggestions($db, $recommendedSpecies);

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

    sendJson(['error' => false, 'data' => $result]);

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

function getRecommendedSpecies($db, $currentMonth, $weather, $tides) {
    // Get all species rules
    $stmt = $db->prepare(
        "SELECT species_id, common_name, preferred_tide_state, preferred_wind_max, preferred_conditions
         FROM species_rules 
         WHERE (season_start_month <= ? AND season_end_month >= ?)
         OR (season_start_month > season_end_month AND (season_start_month <= ? OR season_end_month >= ?))
         ORDER BY species_id"
    );
    $stmt->execute([$currentMonth, $currentMonth, $currentMonth, $currentMonth]);
    $species = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

function getGearSuggestions($db, $species) {
    if (empty($species)) {
        return [
            'bait' => [],
            'lure' => [],
            'line_weight' => '8-15lb',
            'leader' => '10-20lb',
            'rig' => 'paternoster or running sinker'
        ];
    }

    // Get gear from top recommended species
    $stmt = $db->prepare(
        "SELECT gear_bait, gear_lure, gear_line_weight, gear_leader, gear_rig 
         FROM species_rules WHERE species_id = ? LIMIT 1"
    );
    $stmt->execute([$species[0]['id']]);
    $gear = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($gear) {
        $bait = $gear['gear_bait'] ? array_map('trim', explode(',', $gear['gear_bait'])) : [];
        $lure = $gear['gear_lure'] ? array_map('trim', explode(',', $gear['gear_lure'])) : [];
        
        return [
            'bait' => $bait,
            'lure' => $lure,
            'line_weight' => $gear['gear_line_weight'] ?: '8-15lb',
            'leader' => $gear['gear_leader'] ?: '10-20lb',
            'rig' => $gear['gear_rig'] ?: 'paternoster or running sinker'
        ];
    }

    return [
        'bait' => [],
        'lure' => [],
        'line_weight' => '8-15lb',
        'leader' => '10-20lb',
        'rig' => 'paternoster or running sinker'
    ];
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

