<?php
/**
 * Scoring Model Implementation
 * PHP 7.3.33 compatible
 */

class Scoring {
    /**
     * Calculate weather score (0-100)
     */
    public static function calculateWeatherScore($weather) {
        $windSpeed = $weather['wind_speed'] ?? 0;
        $precipitation = $weather['precipitation'] ?? 0;
        $cloudCover = $weather['cloud_cover'] ?? 0;

        // Wind speed (0-50 points)
        if ($windSpeed <= 10) {
            $windPoints = 50;
        } elseif ($windSpeed <= 20) {
            $windPoints = 40 - (($windSpeed - 10) * 1);
        } elseif ($windSpeed <= 30) {
            $windPoints = 30 - (($windSpeed - 20) * 1.5);
        } else {
            $windPoints = max(0, 15 - (($windSpeed - 30) * 0.5));
        }

        // Precipitation (0-30 points)
        if ($precipitation == 0) {
            $precipPoints = 30;
        } elseif ($precipitation <= 2) {
            $precipPoints = 25;
        } elseif ($precipitation <= 5) {
            $precipPoints = 15;
        } else {
            $precipPoints = 5;
        }

        // Cloud cover (0-20 points)
        if ($cloudCover <= 30) {
            $cloudPoints = 20;
        } elseif ($cloudCover <= 60) {
            $cloudPoints = 15;
        } elseif ($cloudCover <= 80) {
            $cloudPoints = 10;
        } else {
            $cloudPoints = 5;
        }

        return $windPoints + $precipPoints + $cloudPoints;
    }

    /**
     * Calculate tide score (0-100)
     */
    public static function calculateTideScore($tides) {
        $events = $tides['events'] ?? [];
        $changeWindows = $tides['change_windows'] ?? [];

        // Base score from tide change frequency (0-60 points)
        $tideChangesPerDay = count($events) / 2;
        if ($tideChangesPerDay >= 2) {
            $changeFrequencyPoints = 60;
        } elseif ($tideChangesPerDay == 1) {
            $changeFrequencyPoints = 40;
        } else {
            $changeFrequencyPoints = 20;
        }

        // Tide amplitude bonus (0-40 points)
        $heights = array_map(function($event) {
            return $event['height'] ?? 0;
        }, $events);
        $tideRange = count($heights) > 0 ? (max($heights) - min($heights)) : 0;
        
        if ($tideRange >= 1.5) {
            $amplitudePoints = 40;
        } elseif ($tideRange >= 1.0) {
            $amplitudePoints = 30;
        } elseif ($tideRange >= 0.5) {
            $amplitudePoints = 20;
        } else {
            $amplitudePoints = 10;
        }

        return $changeFrequencyPoints + $amplitudePoints;
    }

    /**
     * Calculate dawn/dusk overlap score (0-100)
     */
    public static function calculateDawnDuskScore($sun, $tides) {
        $dawn = $sun['dawn'] ?? null;
        $dusk = $sun['dusk'] ?? null;
        $sunrise = $sun['sunrise'] ?? null;
        $sunset = $sun['sunset'] ?? null;
        $changeWindows = $tides['change_windows'] ?? [];

        if (!$dawn || !$dusk || !$sunrise || !$sunset) {
            return 20; // Default low score
        }

        // Dawn window: 30 min before sunrise to 2 hours after
        $sunriseDt = new DateTime($sunrise);
        $dawnStart = clone $sunriseDt;
        $dawnStart->modify('-30 minutes');
        $dawnEnd = clone $sunriseDt;
        $dawnEnd->modify('+2 hours');

        // Dusk window: 2 hours before sunset to 30 min after
        $sunsetDt = new DateTime($sunset);
        $duskStart = clone $sunsetDt;
        $duskStart->modify('-2 hours');
        $duskEnd = clone $sunsetDt;
        $duskEnd->modify('+30 minutes');

        // Calculate overlap with tide change windows
        $overlapMinutes = 0;
        foreach ($changeWindows as $window) {
            $windowStart = new DateTime($window['start']);
            $windowEnd = new DateTime($window['end']);

            // Overlap with dawn window
            $overlapMinutes += self::calculateOverlap($windowStart, $windowEnd, $dawnStart, $dawnEnd);
            // Overlap with dusk window
            $overlapMinutes += self::calculateOverlap($windowStart, $windowEnd, $duskStart, $duskEnd);
        }

        // Score based on total overlap (0-100 points)
        if ($overlapMinutes >= 120) {
            return 100;
        } elseif ($overlapMinutes >= 60) {
            return 80;
        } elseif ($overlapMinutes >= 30) {
            return 60;
        } elseif ($overlapMinutes >= 15) {
            return 40;
        } else {
            return 20;
        }
    }

    /**
     * Calculate overlap in minutes between two time ranges
     */
    private static function calculateOverlap($start1, $end1, $start2, $end2) {
        $overlapStart = $start1 > $start2 ? $start1 : $start2;
        $overlapEnd = $end1 < $end2 ? $end1 : $end2;
        
        if ($overlapStart >= $overlapEnd) {
            return 0;
        }
        
        $diff = $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
        return (int)($diff / 60); // Return minutes
    }

    /**
     * Calculate seasonality score (0-100)
     * Simplified for MVP - checks if any species are in season
     * @param array $allSpeciesRules Pre-loaded species rules array (optimization: avoid query in loop)
     */
    public static function calculateSeasonalityScore($allSpeciesRules) {
        // Use pre-loaded rules instead of querying (optimization: avoid N+1 queries)
        $speciesCount = is_array($allSpeciesRules) ? count($allSpeciesRules) : 0;

        // Score based on number of species in season (0-60 points)
        if ($speciesCount >= 5) {
            $speciesPoints = 60;
        } elseif ($speciesCount >= 3) {
            $speciesPoints = 45;
        } elseif ($speciesCount >= 2) {
            $speciesPoints = 30;
        } elseif ($speciesCount >= 1) {
            $speciesPoints = 20;
        } else {
            $speciesPoints = 10;
        }

        // Bonus for high-confidence matches (0-40 points) - simplified for MVP
        $confidenceBonus = min(40, $speciesCount * 8);

        return $speciesPoints + $confidenceBonus;
    }

    /**
     * Calculate composite score (0-100)
     */
    public static function calculateScore($weatherScore, $tideScore, $dawnDuskScore, $seasonalityScore) {
        $weatherWeight = 0.35;
        $tideWeight = 0.30;
        $dawnDuskWeight = 0.20;
        $seasonalityWeight = 0.15;

        $score = ($weatherScore * $weatherWeight) +
                 ($tideScore * $tideWeight) +
                 ($dawnDuskScore * $dawnDuskWeight) +
                 ($seasonalityScore * $seasonalityWeight);

        return (int)round($score);
    }
}

