<?php
/**
 * API Response Cache
 * PHP 7.3.33 compatible
 */

class Cache {
    private $db = null;

    public function __construct() {
        $this->db = Database::getInstance()->getPdo();
    }

    /**
     * Get cached data
     * @param string $provider Provider name (weather, sun, tides)
     * @param string $key Cache key (format: {lat}:{lng}:{start_date}:{days})
     * @return array|null Cached JSON data or null if not found/expired
     */
    public function get($provider, $key) {
        $stmt = $this->db->prepare(
            "SELECT json_data FROM api_cache 
             WHERE provider = ? AND cache_key = ? AND expires_at > datetime('now')"
        );
        $stmt->execute([$provider, $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            return json_decode($row['json_data'], true);
        }
        return null;
    }

    /**
     * Set cached data
     * @param string $provider Provider name
     * @param string $key Cache key
     * @param array $data Data to cache (will be JSON encoded)
     * @param int $ttlSeconds Time to live in seconds
     */
    public function set($provider, $key, $data, $ttlSeconds) {
        $json = json_encode($data);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
        
        $stmt = $this->db->prepare(
            "INSERT OR REPLACE INTO api_cache (provider, cache_key, json_data, fetched_at, expires_at)
             VALUES (?, ?, ?, datetime('now'), ?)"
        );
        $stmt->execute([$provider, $key, $json, $expiresAt]);
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpired() {
        $this->db->exec("DELETE FROM api_cache WHERE expires_at < datetime('now')");
    }
}

