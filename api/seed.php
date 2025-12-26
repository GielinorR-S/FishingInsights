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
    $csvFile = __DIR__ . '/../data/locations.csv';
    
    // Count existing records before seeding
    $locationsBefore = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesBefore = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    
    $locationsInserted = 0;
    $speciesInserted = 0;
    $errors = [];

    // Import locations from CSV if file exists
    if (file_exists($csvFile)) {
        try {
            $csvHandle = fopen($csvFile, 'r');
            if ($csvHandle !== false) {
                // Read header row
                $headers = fgetcsv($csvHandle);
                if ($headers !== false) {
                    // Expected columns: name,lat,lng,state,region,type,access,notes,safety
                    $expectedColumns = ['name', 'lat', 'lng', 'state', 'region', 'type', 'access', 'notes', 'safety'];
                    $headerMap = [];
                    foreach ($expectedColumns as $col) {
                        $idx = array_search($col, $headers);
                        if ($idx !== false) {
                            $headerMap[$col] = $idx;
                        }
                    }
                    
                    // Check required columns
                    $required = ['name', 'lat', 'lng', 'region'];
                    $missing = [];
                    foreach ($required as $req) {
                        if (!isset($headerMap[$req])) {
                            $missing[] = $req;
                        }
                    }
                    
                    if (empty($missing)) {
                        // Insert locations from CSV
                        $insertStmt = $db->prepare("
                            INSERT OR IGNORE INTO locations 
                            (name, region, latitude, longitude, timezone, description, state, type, access, notes, safety)
                            VALUES (?, ?, ?, ?, 'Australia/Melbourne', ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $rowCount = 0;
                        while (($row = fgetcsv($csvHandle)) !== false) {
                            if (count($row) < count($headers)) {
                                continue; // Skip incomplete rows
                            }
                            
                            $name = isset($headerMap['name']) ? trim($row[$headerMap['name']]) : '';
                            $lat = isset($headerMap['lat']) ? (float)trim($row[$headerMap['lat']]) : 0;
                            $lng = isset($headerMap['lng']) ? (float)trim($row[$headerMap['lng']]) : 0;
                            $region = isset($headerMap['region']) ? trim($row[$headerMap['region']]) : '';
                            $state = isset($headerMap['state']) ? trim($row[$headerMap['state']]) : null;
                            $type = isset($headerMap['type']) ? trim($row[$headerMap['type']]) : null;
                            $access = isset($headerMap['access']) ? trim($row[$headerMap['access']]) : null;
                            $notes = isset($headerMap['notes']) ? trim($row[$headerMap['notes']]) : null;
                            $safety = isset($headerMap['safety']) ? trim($row[$headerMap['safety']]) : null;
                            
                            // Use notes as description if description column not in CSV
                            $description = $notes;
                            
                            if (!empty($name) && !empty($region) && $lat != 0 && $lng != 0) {
                                try {
                                    $insertStmt->execute([
                                        $name, $region, $lat, $lng, $description, 
                                        $state, $type, $access, $notes, $safety
                                    ]);
                                    if ($insertStmt->rowCount() > 0) {
                                        $locationsInserted++;
                                    }
                                    $rowCount++;
                                } catch (PDOException $e) {
                                    $errors[] = "CSV row " . ($rowCount + 1) . ": " . $e->getMessage();
                                }
                            }
                        }
                        fclose($csvHandle);
                    } else {
                        fclose($csvHandle);
                        $errors[] = "CSV missing required columns: " . implode(', ', $missing);
                    }
                } else {
                    fclose($csvHandle);
                    $errors[] = "Failed to read CSV header";
                }
            }
        } catch (Exception $e) {
            $errors[] = "CSV import error: " . $e->getMessage();
        }
    }
    
    // Also import from seed.sql for species rules and any locations not in CSV
    if (file_exists($seedFile)) {
        $sql = file_get_contents($seedFile);
        if ($sql !== false) {
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
        }
    }

    // Count records after seeding
    $locationsAfter = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesAfter = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Calculate species inserted (from seed.sql)
    $speciesInserted = $speciesAfter - $speciesBefore;
    
    // Locations inserted already counted from CSV import above

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

