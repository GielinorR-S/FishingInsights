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

/**
 * Generate unique key for a location (normalized name + region + rounded coords)
 */
function getLocationUniqueKey($name, $region, $lat, $lng) {
    // Normalize name: lowercase, trim, remove extra spaces
    $normalizedName = strtolower(trim($name));
    $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
    
    // Round coordinates to 4 decimal places (~11 meters precision)
    $roundedLat = round((float)$lat, 4);
    $roundedLng = round((float)$lng, 4);
    
    // Combine into unique key
    return $normalizedName . '|' . strtolower(trim($region)) . '|' . $roundedLat . '|' . $roundedLng;
}

/**
 * Check if location already exists using unique key
 */
function locationExists($db, $name, $region, $lat, $lng) {
    $key = getLocationUniqueKey($name, $region, $lat, $lng);
    $normalizedName = strtolower(trim($name));
    $normalizedName = preg_replace('/\s+/', ' ', $normalizedName);
    $normalizedRegion = strtolower(trim($region));
    $roundedLat = round((float)$lat, 4);
    $roundedLng = round((float)$lng, 4);
    
    // Check for existing location with same normalized name, region, and rounded coordinates
    $stmt = $db->prepare("
        SELECT id FROM locations 
        WHERE LOWER(TRIM(REPLACE(name, '  ', ' '))) = ? 
        AND LOWER(TRIM(region)) = ? 
        AND ROUND(latitude, 4) = ? 
        AND ROUND(longitude, 4) = ?
    ");
    $stmt->execute([$normalizedName, $normalizedRegion, $roundedLat, $roundedLng]);
    return $stmt->fetch() !== false;
}

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
    
    // Count existing records before seeding
    $locationsBefore = $db->query("SELECT COUNT(*) as count FROM locations")->fetch(PDO::FETCH_ASSOC)['count'];
    $speciesBefore = $db->query("SELECT COUNT(*) as count FROM species_rules")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Process locations.csv first (if exists)
    $csvFile = __DIR__ . '/../data/locations.csv';
    $locationsInsertedFromCsv = 0;
    $locationsSkippedFromCsv = 0;
    
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        if ($handle !== false) {
            $header = fgetcsv($handle); // Read header row
            $expectedHeader = ['name', 'lat', 'lng', 'state', 'region', 'type', 'access', 'notes', 'safety'];
            
            if ($header === $expectedHeader) {
                $insertStmt = $db->prepare("
                    INSERT INTO locations (name, latitude, longitude, state, region, type, access, notes, safety, timezone, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) === count($expectedHeader)) {
                        $name = trim($data[0]);
                        $lat = (float)$data[1];
                        $lng = (float)$data[2];
                        $state = trim($data[3]);
                        $region = trim($data[4]);
                        $type = trim($data[5]);
                        $access = trim($data[6]);
                        $notes = trim($data[7]);
                        $safety = trim($data[8]);
                        
                        // Use notes as description if available
                        $description = !empty($notes) ? $notes : '';
                        
                        // Check if location already exists using unique key
                        if (locationExists($db, $name, $region, $lat, $lng)) {
                            $locationsSkippedFromCsv++;
                            continue;
                        }
                        
                        try {
                            $insertStmt->execute([
                                $name, $lat, $lng, $state, $region, $type, $access, $notes, $safety,
                                'Australia/Melbourne', $description
                            ]);
                            $locationsInsertedFromCsv++;
                        } catch (PDOException $e) {
                            // Skip on error (likely duplicate or constraint violation)
                            $locationsSkippedFromCsv++;
                        }
                    }
                }
            }
            fclose($handle);
        }
    }
    
    // Then process seed.sql (for species rules and any legacy locations)
    $seedFile = __DIR__ . '/../data/seed.sql';
    
    if (!file_exists($seedFile)) {
        sendError('Seed file not found', 'FILE_NOT_FOUND', ['path' => $seedFile], 404);
    }

    $sql = file_get_contents($seedFile);
    if ($sql === false) {
        sendError('Failed to read seed file', 'FILE_READ_ERROR', [], 500);
    }

    // Process seed.sql with idempotent location insertion
    // Parse INSERT statements and check for duplicates before inserting
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
    $locationsSkipped = 0;

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) {
            continue;
        }

        // Handle location INSERT statements with duplicate checking
        if (stripos($stmt, 'INSERT') === 0 && stripos($stmt, 'locations') !== false) {
            // Parse INSERT statement to extract location data
            // Pattern: INSERT OR IGNORE INTO locations (name, region, latitude, longitude, timezone, description) VALUES
            // ('Name', 'Region', lat, lng, 'timezone', 'description'),
            // Match: ('Name', 'Region', -37.8500, 144.9500, 'Australia/Melbourne', 'Description'),
            if (preg_match_all("/\('([^']+)',\s*'([^']+)',\s*([-\d.]+),\s*([-\d.]+),\s*'([^']+)',\s*'([^']*)'\)/", $stmt, $matches, PREG_SET_ORDER)) {
                $insertStmt = $db->prepare("
                    INSERT INTO locations (name, region, latitude, longitude, timezone, description) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($matches as $match) {
                    $name = $match[1];
                    $region = $match[2];
                    $lat = (float)$match[3];
                    $lng = (float)$match[4];
                    $timezone = $match[5];
                    $description = $match[6];
                    
                    // Check if location already exists using unique key
                    if (locationExists($db, $name, $region, $lat, $lng)) {
                        $locationsSkipped++;
                        continue;
                    }
                    
                    try {
                        $insertStmt->execute([$name, $region, $lat, $lng, $timezone, $description]);
                        $executed++;
                    } catch (PDOException $e) {
                        $errors[] = $e->getMessage() . ' (Location: ' . $name . ')';
                    }
                }
            } else {
                // If regex doesn't match, skip INSERT OR IGNORE for locations (don't execute as-is)
                // This prevents duplicates from INSERT OR IGNORE which doesn't work without UNIQUE constraint
                if (stripos($stmt, 'INSERT OR IGNORE') !== false && stripos($stmt, 'locations') !== false) {
                    // Skip this statement - we handle location inserts above with duplicate checking
                    continue;
                }
                
                // Execute other statements (species_rules, etc.) as-is
                try {
                    $db->exec($stmt);
                    $executed++;
                } catch (PDOException $e) {
                    $msg = $e->getMessage();
                    if (strpos($msg, 'UNIQUE constraint') === false && 
                        strpos($msg, 'already exists') === false &&
                        strpos($msg, 'duplicate column name') === false) {
                        $errors[] = $msg . ' (Statement: ' . substr($stmt, 0, 100) . '...)';
                    }
                }
            }
        } else {
            // Execute other statements (species_rules, etc.) as-is
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

    // Clean up existing duplicates (keep first occurrence, delete others)
    $cleanupStmt = $db->query("
        SELECT id, name, region, latitude, longitude 
        FROM locations 
        ORDER BY id
    ");
    $allLocations = $cleanupStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $seenKeys = [];
    $duplicateIds = [];
    foreach ($allLocations as $loc) {
        $key = getLocationUniqueKey($loc['name'], $loc['region'], $loc['latitude'], $loc['longitude']);
        if (isset($seenKeys[$key])) {
            // This is a duplicate - mark for deletion
            $duplicateIds[] = $loc['id'];
        } else {
            $seenKeys[$key] = true;
        }
    }
    
    $duplicatesDeleted = 0;
    if (!empty($duplicateIds)) {
        $placeholders = implode(',', array_fill(0, count($duplicateIds), '?'));
        $deleteStmt = $db->prepare("DELETE FROM locations WHERE id IN ($placeholders)");
        $deleteStmt->execute($duplicateIds);
        $duplicatesDeleted = $deleteStmt->rowCount();
    }
    
    // Count records after seeding and cleanup
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
        'locations_skipped' => (int)$locationsSkipped,
        'duplicates_deleted' => (int)$duplicatesDeleted,
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

