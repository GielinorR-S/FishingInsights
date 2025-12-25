<?php
/**
 * Database Seeding Endpoint (DEV ONLY)
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
    sendError('Seeding is only available in DEV_MODE', 'DEV_MODE_REQUIRED', [], 403);
}

try {
    // Ensure schema exists (Database::getInstance() creates it)
    $db = Database::getInstance()->getPdo();
    
    // Verify DB_PATH
    $dbPath = defined('DB_PATH') ? DB_PATH : 'not defined';
    
    $seedFile = __DIR__ . '/../data/seed.sql';
    
    if (!file_exists($seedFile)) {
        sendError('Seed file not found', 'FILE_NOT_FOUND', ['path' => $seedFile], 404);
    }

    $sql = file_get_contents($seedFile);
    if ($sql === false) {
        sendError('Failed to read seed file', 'FILE_READ_ERROR', [], 500);
    }

    // Count existing records before seeding
    $locationsBefore = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesBefore = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];

    // Execute SQL statements
    // Remove comments and split by semicolon
    $lines = explode("\n", $sql);
    $cleanSql = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        // Skip comment lines
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        $cleanSql .= $line . "\n";
    }
    
    $statements = explode(';', $cleanSql);
    $executed = 0;
    $errors = [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) {
            continue;
        }

        try {
            $db->exec($stmt);
            $executed++;
        } catch (PDOException $e) {
            // Ignore "already exists" errors for INSERT OR IGNORE and CREATE INDEX IF NOT EXISTS
            $msg = $e->getMessage();
            if (strpos($msg, 'UNIQUE constraint') === false && 
                strpos($msg, 'already exists') === false &&
                strpos($msg, 'duplicate column name') === false) {
                $errors[] = $msg . ' (Statement: ' . substr($stmt, 0, 100) . '...)';
            }
        }
    }

    // Count records after seeding
    $locationsAfter = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesAfter = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    
    $locationsInserted = $locationsAfter - $locationsBefore;
    $speciesInserted = $speciesAfter - $speciesBefore;

    $dt = getTimezoneDateTime(defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC');
    $timezone = defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : 'UTC';
    
    // Redact DB path if sensitive
    $dbPathDisplay = $dbPath;
    if (strpos($dbPath, '/home/') === 0 || strpos($dbPath, 'C:\\') === 0) {
        $dbPathDisplay = '[redacted]';
    }
    
    sendJson([
        'ok' => true,
        'seeded' => true,
        'locations_count' => (int)$locationsAfter,
        'species_rules_count' => (int)$speciesAfter,
        'locations_inserted' => (int)$locationsInserted,
        'species_rules_inserted' => (int)$speciesInserted,
        'db_path' => $dbPathDisplay,
        'timestamp' => formatIso8601($dt),
        'timezone' => $timezone
    ]);

} catch (Exception $e) {
    sendError('Seeding failed', 'SEED_ERROR', [
        'message' => $e->getMessage()
    ], 500);
}

