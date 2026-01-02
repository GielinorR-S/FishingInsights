<?php
/**
 * Database Connection and Schema Management
 * PHP 7.3.33 compatible
 */

class Database {
    private static $instance = null;
    private $pdo = null;

    private function __construct() {
        $this->connect();
        $this->createSchema();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        if (!defined('DB_PATH')) {
            throw new Exception('DB_PATH not defined in config. Ensure config.example.php and config.local.php are loaded.');
        }

        $dbPath = DB_PATH;
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            if (!mkdir($dbDir, 0775, true)) {
                throw new Exception('Cannot create database directory: ' . $dbDir);
            }
        }

        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage() . ' (DB_PATH: ' . $dbPath . ')');
        }
    }

    public function getPdo() {
        return $this->pdo;
    }

    private function createSchema() {
        $tables = [
            'locations' => "
                CREATE TABLE IF NOT EXISTS locations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    region TEXT NOT NULL,
                    latitude REAL NOT NULL,
                    longitude REAL NOT NULL,
                    timezone TEXT NOT NULL DEFAULT 'Australia/Melbourne',
                    description TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
                CREATE INDEX IF NOT EXISTS idx_locations_region ON locations(region);
                CREATE INDEX IF NOT EXISTS idx_locations_coords ON locations(latitude, longitude);
                -- Note: No UNIQUE constraint to allow flexibility, but seed.php handles duplicates via application logic
            ",
            'species_rules' => "
                CREATE TABLE IF NOT EXISTS species_rules (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    species_id TEXT NOT NULL UNIQUE,
                    common_name TEXT NOT NULL,
                    scientific_name TEXT,
                    season_start_month INTEGER NOT NULL CHECK(season_start_month >= 1 AND season_start_month <= 12),
                    season_end_month INTEGER NOT NULL CHECK(season_end_month >= 1 AND season_end_month <= 12),
                    preferred_water_temp_min REAL,
                    preferred_water_temp_max REAL,
                    preferred_wind_max REAL,
                    preferred_conditions TEXT,
                    preferred_tide_state TEXT,
                    gear_bait TEXT,
                    gear_lure TEXT,
                    gear_line_weight TEXT,
                    gear_leader TEXT,
                    gear_rig TEXT,
                    description TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
                CREATE INDEX IF NOT EXISTS idx_species_rules_season ON species_rules(season_start_month, season_end_month);
            ",
            'species' => "
                CREATE TABLE IF NOT EXISTS species (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    common_name TEXT,
                    state TEXT NOT NULL,
                    region TEXT,
                    seasonality TEXT,
                    methods TEXT,
                    notes TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
                CREATE INDEX IF NOT EXISTS idx_species_state ON species(state);
                CREATE INDEX IF NOT EXISTS idx_species_region ON species(region);
            ",
            'tackle_items' => "
                CREATE TABLE IF NOT EXISTS tackle_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    category TEXT NOT NULL,
                    notes TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
                );
                CREATE INDEX IF NOT EXISTS idx_tackle_items_category ON tackle_items(category);
            ",
            'species_tackle' => "
                CREATE TABLE IF NOT EXISTS species_tackle (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    species_id TEXT NOT NULL,
                    tackle_item_id INTEGER NOT NULL,
                    priority INTEGER NOT NULL DEFAULT 1,
                    conditions_json TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                    FOREIGN KEY (tackle_item_id) REFERENCES tackle_items(id) ON DELETE CASCADE
                );
                CREATE INDEX IF NOT EXISTS idx_species_tackle_species ON species_tackle(species_id);
                CREATE INDEX IF NOT EXISTS idx_species_tackle_priority ON species_tackle(species_id, priority);
            ",
            'api_cache' => "
                CREATE TABLE IF NOT EXISTS api_cache (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    provider TEXT NOT NULL,
                    cache_key TEXT NOT NULL,
                    json_data TEXT NOT NULL,
                    fetched_at TEXT NOT NULL DEFAULT (datetime('now')),
                    expires_at TEXT NOT NULL,
                    UNIQUE(provider, cache_key)
                );
                CREATE INDEX IF NOT EXISTS idx_api_cache_lookup ON api_cache(provider, cache_key);
                CREATE INDEX IF NOT EXISTS idx_api_cache_expires ON api_cache(expires_at);
            ",
            'rate_limits' => "
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    endpoint TEXT NOT NULL,
                    request_count INTEGER NOT NULL DEFAULT 1,
                    window_start TEXT NOT NULL DEFAULT (datetime('now')),
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    UNIQUE(ip_address, endpoint, window_start)
                );
                CREATE INDEX IF NOT EXISTS idx_rate_limits_lookup ON rate_limits(ip_address, endpoint, window_start);
                CREATE INDEX IF NOT EXISTS idx_rate_limits_cleanup ON rate_limits(window_start);
            "
        ];

        foreach ($tables as $name => $sql) {
            $statements = explode(';', $sql);
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    try {
                        $this->pdo->exec($stmt);
                    } catch (PDOException $e) {
                        // Ignore "already exists" errors for CREATE INDEX IF NOT EXISTS
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            error_log("Schema creation error for $name: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    public function canWrite() {
        try {
            $testFile = DB_PATH . '.test';
            $result = @file_put_contents($testFile, 'test');
            if ($result !== false) {
                @unlink($testFile);
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}

