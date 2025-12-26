<?php
/**
 * Locations Endpoint
 * PHP 7.3.33 compatible
 */

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/RateLimiter.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $ip = getClientIp();
    $limitCheck = $rateLimiter->checkLimit($ip, 'locations');
    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $limitCheck['retry_after']);
        sendError('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', [], 429);
    }

    $db = Database::getInstance()->getPdo();
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    
    // Verify table exists (Database::getInstance() should create it, but check anyway)
    $tableCheck = $db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='locations'"
    )->fetch();
    
    if (!$tableCheck) {
        sendError('Locations table does not exist', 'TABLE_MISSING', [], 500);
    }
    
    // Build query
    $sql = "SELECT id, name, region, latitude, longitude, timezone, description, state, type, access, notes, safety FROM locations WHERE 1=1";
    $params = [];
    
    // State filter
    if (isset($_GET['state']) && !empty($_GET['state'])) {
        $sql .= " AND state = ?";
        $params[] = strtoupper(trim($_GET['state']));
    }
    
    // Search filter (q parameter for name/region/type search)
    if (isset($_GET['q']) && !empty($_GET['q'])) {
        $sql .= " AND (name LIKE ? OR region LIKE ? OR type LIKE ?)";
        $searchTerm = '%' . $_GET['q'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Legacy search filter (backward compatibility)
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $sql .= " AND (name LIKE ? OR region LIKE ?)";
        $searchTerm = '%' . $_GET['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Region filter
    if (isset($_GET['region']) && !empty($_GET['region'])) {
        $sql .= " AND region = ?";
        $params[] = $_GET['region'];
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $locations = [];
    foreach ($rows as $row) {
        $location = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'region' => $row['region'],
            'lat' => (float)$row['latitude'],
            'lng' => (float)$row['longitude'],
            'timezone' => $row['timezone'] ?: $timezone
        ];
        
        // Add optional fields if they exist
        if (isset($row['state']) && $row['state'] !== null) {
            $location['state'] = $row['state'];
        }
        if (isset($row['type']) && $row['type'] !== null) {
            $location['type'] = $row['type'];
        }
        if (isset($row['access']) && $row['access'] !== null) {
            $location['access'] = $row['access'];
        }
        if (isset($row['notes']) && $row['notes'] !== null) {
            $location['notes'] = $row['notes'];
        }
        if (isset($row['safety']) && $row['safety'] !== null) {
            $location['safety'] = $row['safety'];
        }
        
        $locations[] = $location;
    }
    
    sendJson([
        'error' => false,
        'data' => [
            'timezone' => $timezone,
            'locations' => $locations
        ]
    ]);

} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

