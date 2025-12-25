<?php
/**
 * Health Check Endpoint
 * PHP 7.3.33 compatible
 */

require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/utils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    $dt = getTimezoneDateTime($timezone);
    
    $result = [
        'status' => 'ok',
        'php_version' => PHP_VERSION,
        'has_pdo' => extension_loaded('pdo'),
        'has_pdo_sqlite' => extension_loaded('pdo_sqlite'),
        'sqlite_db_path' => defined('DB_PATH') ? (strpos(DB_PATH, '/home/') === 0 || strpos(DB_PATH, 'C:\\') === 0 ? '[redacted]' : DB_PATH) : 'not configured',
        'can_write_db' => false,
        'can_write_cache' => false,
        'timestamp' => formatIso8601($dt),
        'timezone' => $timezone
    ];

    // Check PDO SQLite
    if (!$result['has_pdo'] || !$result['has_pdo_sqlite']) {
        sendError('PDO SQLite extension missing', 'HEALTH_CHECK_ERROR', [
            'missing_extension' => !$result['has_pdo'] ? 'pdo' : 'pdo_sqlite'
        ], 500);
    }

    // Try to connect and create schema
    try {
        $db = Database::getInstance();
        $result['can_write_db'] = $db->canWrite();
        
        // Test cache write
        $cacheDir = dirname(defined('DB_PATH') ? DB_PATH : __DIR__ . '/../data/fishinginsights.db');
        $testFile = $cacheDir . '/.test';
        $testWrite = @file_put_contents($testFile, 'test');
        if ($testWrite !== false) {
            @unlink($testFile);
            $result['can_write_cache'] = true;
        }
    } catch (Exception $e) {
        sendError('Database connection failed', 'HEALTH_CHECK_ERROR', [
            'message' => $e->getMessage()
        ], 500);
    }

    // If critical checks fail, return 500
    if (!$result['can_write_db']) {
        sendError('Database is not writable', 'HEALTH_CHECK_ERROR', [
            'can_write_db' => false
        ], 500);
    }

    sendJson($result);

} catch (Exception $e) {
    sendError('Health check failed', 'HEALTH_CHECK_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

