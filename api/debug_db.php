<?php
/**
 * Database Debug Endpoint (DEV ONLY)
 * PHP 7.3.33 compatible
 * 
 * Only runs if DEV_MODE=true
 */

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

// Only allow in DEV_MODE
$devMode = defined('DEV_MODE') ? DEV_MODE : false;
if (!$devMode) {
    sendError('Debug endpoint is only available in DEV_MODE', 'DEV_MODE_REQUIRED', [], 403);
}

try {
    $db = Database::getInstance()->getPdo();
    
    // Get DB path (redacted if sensitive)
    $dbPath = defined('DB_PATH') ? DB_PATH : 'not defined';
    $dbPathDisplay = $dbPath;
    if (strpos($dbPath, '/home/') === 0 || strpos($dbPath, 'C:\\') === 0) {
        $dbPathDisplay = '[redacted]';
    }
    
    // Get counts
    $locationsCount = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesCount = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    $cacheCount = $db->query("SELECT COUNT(*) as count FROM api_cache")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get sample locations
    $sampleLocations = $db->query(
        "SELECT id, name, region, latitude, longitude FROM locations ORDER BY id LIMIT 3"
    )->fetchAll(PDO::FETCH_ASSOC);
    
    $samples = [];
    foreach ($sampleLocations as $loc) {
        $samples[] = [
            'id' => (int)$loc['id'],
            'name' => $loc['name'],
            'region' => $loc['region'],
            'lat' => (float)$loc['latitude'],
            'lng' => (float)$loc['longitude']
        ];
    }
    
    $dt = getTimezoneDateTime(defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC');
    
    sendJson([
        'ok' => true,
        'db_path' => $dbPathDisplay,
        'db_path_resolved' => $dbPathDisplay,
        'counts' => [
            'locations_count' => (int)$locationsCount,
            'species_rules_count' => (int)$speciesCount,
            'api_cache_count' => (int)$cacheCount
        ],
        'sample_locations' => $samples,
        'timestamp' => formatIso8601($dt),
        'timezone' => defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC'
    ]);

} catch (Exception $e) {
    sendError('Debug query failed', 'DEBUG_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

