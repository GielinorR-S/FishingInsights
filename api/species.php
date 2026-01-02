<?php
/**
 * Species Endpoint
 * PHP 7.3.33 compatible
 * 
 * Returns fishing species filtered by state, region, or search query
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
    $limitCheck = $rateLimiter->checkLimit($ip, 'species');
    if (!$limitCheck['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $limitCheck['retry_after']);
        sendError('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', [], 429);
    }

    $db = Database::getInstance()->getPdo();
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    
    // Verify table exists
    $tableCheck = $db->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='species'"
    )->fetch();
    
    if (!$tableCheck) {
        sendError('Species table does not exist', 'TABLE_MISSING', [], 500);
    }
    
    // Build query
    $sql = "SELECT id, name, common_name, state, region, seasonality, methods, notes FROM species WHERE 1=1";
    $params = [];
    
    // State filter
    if (isset($_GET['state']) && !empty($_GET['state'])) {
        $sql .= " AND state = ?";
        $params[] = strtoupper(trim($_GET['state']));
    }
    
    // Region filter
    if (isset($_GET['region']) && !empty($_GET['region'])) {
        $sql .= " AND region = ?";
        $params[] = trim($_GET['region']);
    }
    
    // Search filter (q)
    if (isset($_GET['q']) && !empty($_GET['q'])) {
        $sql .= " AND (name LIKE ? OR common_name LIKE ? OR notes LIKE ?)";
        $searchTerm = '%' . trim($_GET['q']) . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY common_name, name, region";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build response
    $species = [];
    foreach ($rows as $row) {
        $species[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'common_name' => $row['common_name'] ?: $row['name'],
            'state' => $row['state'],
            'region' => $row['region'],
            'seasonality' => $row['seasonality'],
            'methods' => $row['methods'],
            'notes' => $row['notes']
        ];
    }
    
    sendJson([
        'error' => false,
        'data' => [
            'timezone' => $timezone,
            'species' => $species
        ]
    ]);
    
} catch (Exception $e) {
    sendError('Internal server error', 'INTERNAL_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

