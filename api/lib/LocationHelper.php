<?php
/**
 * Location Helper Functions
 * PHP 7.3.33 compatible
 */

/**
 * Calculate haversine distance between two points in kilometers
 * @param float $lat1
 * @param float $lng1
 * @param float $lat2
 * @param float $lng2
 * @return float Distance in kilometers
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371; // Earth radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

/**
 * Find nearest location to given coordinates
 * @param PDO $db Database connection
 * @param float $lat Latitude
 * @param float $lng Longitude
 * @param float $maxKm Maximum distance in kilometers (default 30)
 * @return array|null Location data or null if none found
 */
function findNearestLocation($db, $lat, $lng, $maxKm = 30) {
    $stmt = $db->query("SELECT id, name, region, latitude, longitude, timezone, description FROM locations");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $nearest = null;
    $minDistance = $maxKm;
    
    foreach ($locations as $location) {
        $distance = haversineDistance($lat, $lng, $location['latitude'], $location['longitude']);
        if ($distance < $minDistance) {
            $minDistance = $distance;
            $nearest = $location;
            $nearest['distance_km'] = round($distance, 1);
        }
    }
    
    return $nearest;
}

