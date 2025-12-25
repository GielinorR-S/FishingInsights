<?php
/**
 * Configuration Example
 * 
 * Copy this file to config.local.php and update with your actual values.
 * config.local.php is NOT committed to version control.
 */

// Database configuration
// When api/ is beside data/ directory:
if (!defined('DB_PATH')) {
    define('DB_PATH', __DIR__ . '/../data/fishinginsights.db');
}
// Or if outside web root:
// if (!defined('DB_PATH')) {
//     define('DB_PATH', '/home/username/data/fishinginsights.db');
// }

// WorldTides API (optional - app works with mock mode if missing)
if (!defined('WORLDTIDES_API_KEY')) {
    define('WORLDTIDES_API_KEY', '');
}
// Leave empty to use mock tides:
// if (!defined('WORLDTIDES_API_KEY')) {
//     define('WORLDTIDES_API_KEY', '');
// }

// Timezone (Victoria)
if (!defined('DEFAULT_TIMEZONE')) {
    define('DEFAULT_TIMEZONE', 'Australia/Melbourne');
}

// Rate limiting
if (!defined('RATE_LIMIT_PER_MINUTE')) {
    define('RATE_LIMIT_PER_MINUTE', 60);
}
if (!defined('RATE_LIMIT_PER_HOUR')) {
    define('RATE_LIMIT_PER_HOUR', 1000);
}

// Cache TTLs (seconds)
if (!defined('CACHE_TTL_WEATHER')) {
    define('CACHE_TTL_WEATHER', 3600);    // 1 hour
}
if (!defined('CACHE_TTL_SUN')) {
    define('CACHE_TTL_SUN', 604800);      // 7 days
}
if (!defined('CACHE_TTL_TIDES')) {
    define('CACHE_TTL_TIDES', 43200);     // 12 hours
}

// Development mode (set to false in production)
if (!defined('DEV_MODE')) {
    define('DEV_MODE', false);
}

