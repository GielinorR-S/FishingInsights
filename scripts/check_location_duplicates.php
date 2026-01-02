<?php
/**
 * Check for duplicate locations in the database
 * PHP 7.3.33 compatible
 * 
 * Uses unique key: normalized(name) + region + rounded(lat,4) + rounded(lng,4)
 */

require_once __DIR__ . '/../api/config.example.php';
if (file_exists(__DIR__ . '/../api/config.local.php')) {
    require_once __DIR__ . '/../api/config.local.php';
}
require_once __DIR__ . '/../api/lib/Database.php';

/**
 * Generate unique key for a location
 */
function getLocationUniqueKey($name, $region, $lat, $lng) {
    $normalizedName = strtolower(trim($name));
    $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
    $roundedLat = round((float)$lat, 4);
    $roundedLng = round((float)$lng, 4);
    return $normalizedName . '|' . strtolower(trim($region)) . '|' . $roundedLat . '|' . $roundedLng;
}

try {
    $db = Database::getInstance()->getPdo();
    
    // Get all locations
    $stmt = $db->query("SELECT id, name, region, latitude, longitude FROM locations ORDER BY name, region");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by unique key
    $keyGroups = [];
    foreach ($rows as $row) {
        $key = getLocationUniqueKey($row['name'], $row['region'], $row['latitude'], $row['longitude']);
        if (!isset($keyGroups[$key])) {
            $keyGroups[$key] = [];
        }
        $keyGroups[$key][] = $row;
    }
    
    // Find duplicates
    $duplicates = [];
    foreach ($keyGroups as $key => $group) {
        if (count($group) > 1) {
            $duplicates[$key] = $group;
        }
    }
    
    // Output results
    echo "FishingInsights Location Duplicate Check\n";
    echo "========================================\n\n";
    
    if (empty($duplicates)) {
        echo "✅ No duplicates found!\n";
        echo "Total locations: " . count($rows) . "\n";
        exit(0);
    }
    
    echo "⚠️  Found " . count($duplicates) . " duplicate group(s):\n\n";
    
    $totalDuplicates = 0;
    foreach ($duplicates as $key => $group) {
        $count = count($group);
        $totalDuplicates += ($count - 1); // Subtract 1 because we keep one
        
        $first = $group[0];
        echo "Key: " . $key . "\n";
        echo "  Count: " . $count . " duplicate(s)\n";
        echo "  Location: " . $first['name'] . " (" . $first['region'] . ")\n";
        echo "  Coordinates: " . round((float)$first['latitude'], 4) . ", " . round((float)$first['longitude'], 4) . "\n";
        echo "  IDs: ";
        $ids = [];
        foreach ($group as $item) {
            $ids[] = $item['id'];
        }
        echo implode(', ', $ids) . "\n";
        echo "\n";
    }
    
    echo "Total duplicate records: " . $totalDuplicates . "\n";
    echo "Recommendation: Run seed.php to clean up duplicates (it will skip existing locations)\n";
    
    exit(1);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
