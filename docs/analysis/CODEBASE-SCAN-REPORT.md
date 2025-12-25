# FishingInsights Codebase Scan Report

**Generated:** 2025-01-XX  
**Purpose:** Comprehensive scan of PHP version assumptions, date/timezone handling, caching, API responses, and DEV/PROD differences

---

## 1. PHP Version Assumptions

### `api/forecast.php`
- Uses null coalescing operator `??` (PHP 7.0+)
- Uses `DateTime` class with `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::createFromFormat()` with timezone parameter (PHP 5.3+)
- Uses `clone` keyword (PHP 5.0+)
- Uses `usort()` with anonymous function (PHP 5.3+)
- Uses `array_slice()` (PHP 4.0+)
- Uses `implode()` (PHP 4.0+)
- Uses `round()` (PHP 4.0+)
- Uses `max()` and `min()` (PHP 4.0+)
- **No PHP 7.4+ features detected** (no arrow functions, typed properties, null coalescing assignment, union types, match expressions)

### `api/lib/Validator.php`
- Uses `filter_var()` with `FILTER_VALIDATE_FLOAT` and `FILTER_VALIDATE_INT` (PHP 5.2+)
- Uses `preg_match()` (PHP 4.0+)
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::createFromFormat()` (PHP 5.3+)
- **No PHP 7.4+ features detected**

### `api/lib/Cache.php`
- Uses `json_encode()` and `json_decode()` (PHP 5.2+)
- Uses `date()` and `time()` (PHP 4.0+)
- Uses PDO prepared statements (PHP 5.1+)
- Uses `INSERT OR REPLACE` SQLite syntax (SQLite 3.3.8+)
- **No PHP 7.4+ features detected**

### `api/lib/utils.php`
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::format()` with 'P' format (timezone offset) (PHP 5.1.3+)
- **No PHP 7.4+ features detected**

### `api/lib/ForecastData.php`
- Uses `date()` and `strtotime()` (PHP 4.0+)
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::modify()` (PHP 5.2+)
- Uses `DateTime::format()` (PHP 5.2+)
- **No PHP 7.4+ features detected**

### `api/lib/Scoring.php`
- Uses `DateTime` class (PHP 5.2+)
- Uses `DateTime::getTimestamp()` (PHP 5.3+)
- Uses `DateTime::modify()` (PHP 5.2+)
- **No PHP 7.4+ features detected**

### `api/lib/RateLimiter.php`
- Uses `date()` (PHP 4.0+)
- Uses PDO prepared statements (PHP 5.1+)
- Uses SQLite `datetime()` function
- **No PHP 7.4+ features detected**

### `api/config.example.php`
- Uses `php_sapi_name()` (PHP 4.0.1+)
- Uses `define()` (PHP 4.0+)
- Uses conditional `if (!defined())` pattern (PHP 4.0+)
- **No PHP 7.4+ features detected**

### `api/tides.php`
- Uses `date()` and `strtotime()` (PHP 4.0+)
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::modify()` (PHP 5.2+)
- **No PHP 7.4+ features detected**

### `api/weather.php`
- Uses `date()` and `strtotime()` (PHP 4.0+)
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- **No PHP 7.4+ features detected**

### `api/sun.php`
- Uses `date()` and `strtotime()` (PHP 4.0+)
- Uses `DateTime` and `DateTimeZone` (PHP 5.2+)
- Uses `DateTime::modify()` (PHP 5.2+)
- **No PHP 7.4+ features detected**

### `api/seed.php`
- Uses `file_get_contents()` (PHP 4.3+)
- Uses `explode()` and `trim()` (PHP 4.0+)
- Uses `strpos()` (PHP 4.0+)
- Uses `substr()` (PHP 4.0+)
- Uses PDO `exec()` and `query()` (PHP 5.1+)
- **No PHP 7.4+ features detected**

### `api/debug_db.php`
- Uses PDO `query()` and `fetchAll()` (PHP 5.1+)
- Uses `strpos()` (PHP 4.0+)
- **No PHP 7.4+ features detected**

### `api/health.php`
- Uses `extension_loaded()` (PHP 4.0+)
- Uses `file_exists()` and `is_writable()` (PHP 4.0+)
- Uses `dirname()` (PHP 4.0+)
- Uses `fopen()`, `fwrite()`, `fclose()` (PHP 4.0+)
- **No PHP 7.4+ features detected**

### `api/locations.php`
- Uses PDO prepared statements (PHP 5.1+)
- Uses `isset()` and `empty()` (PHP 4.0+)
- **No PHP 7.4+ features detected**

### `api/lib/Database.php`
- Uses PDO (PHP 5.1+)
- Uses SQLite-specific syntax (`CREATE TABLE IF NOT EXISTS`, `AUTOINCREMENT`)
- Uses `file_exists()` and `is_writable()` (PHP 4.0+)
- Uses `dirname()` (PHP 4.0+)
- **No PHP 7.4+ features detected**

### `api/lib/LocationHelper.php`
- Uses `deg2rad()`, `sin()`, `cos()`, `atan2()`, `sqrt()` (PHP 4.0+)
- Uses PDO `query()` and `fetchAll()` (PHP 5.1+)
- **No PHP 7.4+ features detected**

---

## 2. Date/Timezone Calculations and Transformations

### `api/forecast.php`
- **Line 54**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'Australia/Melbourne'`
- **Line 58**: Creates `DateTime` with timezone for default start date
- **Line 99**: Gets current month using timezone-aware `DateTime`
- **Line 103**: Parses start date with timezone using `DateTime::createFromFormat()`
- **Line 110**: Uses `DateTime::modify()` to add days (timezone-aware)
- **Line 175**: Gets current timestamp in timezone using `getTimezoneDateTime()`
- **Line 186**: Formats timestamp as ISO 8601 with timezone offset
- **Line 214, 220**: Parses sunrise/sunset strings as `DateTime` objects
- **Line 227-228**: Parses tide window start/end as `DateTime` objects
- **Line 237, 251**: Formats overlap windows as ISO 8601 with timezone

### `api/lib/Validator.php`
- **Line 78**: Gets timezone from `DEFAULT_TIMEZONE` or defaults to `'Australia/Melbourne'`
- **Line 79**: Creates `DateTimeZone` object
- **Line 81**: Parses date with timezone using `DateTime::createFromFormat()`
- **Line 87**: Gets "today" in specified timezone using `DateTime('today', $tz)`
- **Line 88**: Compares dates as strings (YYYY-MM-DD format)

### `api/lib/utils.php`
- **Line 59-63**: `getTimezoneDateTime()` - Creates `DateTime('now')` with specified timezone
- **Line 71-72**: `formatIso8601()` - Formats `DateTime` as ISO 8601 with timezone offset (format: 'Y-m-d\TH:i:sP')

### `api/lib/ForecastData.php`
- **Line 29**: Uses `strtotime()` for end_date calculation (timezone-agnostic, uses server timezone - **POTENTIAL ISSUE**)
- **Line 81**: Gets timezone-aware timestamp using `getTimezoneDateTime()`
- **Line 87**: Formats cached_at as ISO 8601
- **Line 114**: Uses `strtotime()` for end_date calculation (timezone-agnostic - **POTENTIAL ISSUE**)
- **Line 139**: Creates `DateTimeZone` object
- **Line 146-147**: Parses sunrise/sunset strings as `DateTime` with timezone
- **Line 156-159**: Formats sunrise/sunset/dawn/dusk as ISO 8601
- **Line 163**: Gets timezone-aware timestamp
- **Line 169**: Formats cached_at as ISO 8601
- **Line 211**: Creates `DateTimeZone` object
- **Line 219**: Parses start date with timezone
- **Line 222-223**: Uses `DateTime::modify()` to add days (timezone-aware)
- **Line 249, 261, 262, 268**: Formats tide event times as ISO 8601

### `api/tides.php`
- **Line 45**: Uses `date('Y-m-d')` for default start (uses server timezone - **POTENTIAL ISSUE**)
- **Line 48**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'UTC'`
- **Line 84**: Gets timezone-aware timestamp
- **Line 87**: Formats cached_at as ISO 8601
- **Line 111**: Gets timezone-aware timestamp
- **Line 117**: Formats cached_at as ISO 8601
- **Line 135**: Creates `DateTimeZone` object
- **Line 147**: Parses height date with timezone
- **Line 162**: Formats event time as ISO 8601
- **Line 173**: Parses event time with timezone
- **Line 180-181**: Formats window start/end as ISO 8601
- **Line 199**: Creates `DateTimeZone` object
- **Line 208**: Parses start date with timezone
- **Line 249, 261, 262, 264**: Formats tide times as ISO 8601

### `api/weather.php`
- **Line 45**: Uses `date('Y-m-d')` for default start (uses server timezone - **POTENTIAL ISSUE**)
- **Line 48**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'UTC'`
- **Line 66**: Uses `strtotime()` for end_date calculation (timezone-agnostic - **POTENTIAL ISSUE**)
- **Line 118**: Gets timezone-aware timestamp
- **Line 124**: Formats cached_at as ISO 8601

### `api/sun.php`
- **Line 45**: Uses `date('Y-m-d')` for default start (uses server timezone - **POTENTIAL ISSUE**)
- **Line 48**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'UTC'`
- **Line 66**: Uses `strtotime()` for end_date calculation (timezone-agnostic - **POTENTIAL ISSUE**)
- **Line 91**: Creates `DateTimeZone` object
- **Line 99-100**: Parses sunrise/sunset strings as `DateTime` with timezone
- **Line 110-113**: Formats sunrise/sunset/dawn/dusk as ISO 8601
- **Line 117**: Gets timezone-aware timestamp
- **Line 123**: Formats cached_at as ISO 8601

### `api/lib/Scoring.php`
- **Line 103, 110**: Parses sunrise/sunset strings as `DateTime` objects (no timezone specified - uses default)
- **Line 119-120**: Parses tide window start/end as `DateTime` objects (no timezone specified - uses default)

### `api/lib/RateLimiter.php`
- **Line 25**: Uses `date('Y-m-d H:i:00')` for minute window (uses server timezone)
- **Line 32**: Uses `date('Y-m-d H:00:00')` for hour window (uses server timezone)
- **Line 75**: Uses SQLite `datetime('now', '-1 day')` for cleanup

### `api/lib/Cache.php`
- **Line 43**: Uses `date('Y-m-d H:i:s', time() + $ttlSeconds)` for expires_at (uses server timezone - **POTENTIAL ISSUE**)
- **Line 23, 47, 56**: Uses SQLite `datetime('now')` function (SQLite's current time, not PHP timezone)

### `api/seed.php`
- **Line 90**: Gets timezone-aware timestamp using `getTimezoneDateTime()` with `DEFAULT_TIMEZONE` or `'UTC'`
- **Line 107**: Formats timestamp as ISO 8601

### `api/debug_db.php`
- **Line 55**: Gets timezone-aware timestamp using `getTimezoneDateTime()` with `DEFAULT_TIMEZONE` or `'UTC'`
- **Line 67**: Formats timestamp as ISO 8601

### `api/health.php`
- **Line 17**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'UTC'`
- **Line 18**: Gets timezone-aware timestamp
- **Line 28**: Formats timestamp as ISO 8601

### `api/locations.php`
- **Line 30**: Sets timezone to `DEFAULT_TIMEZONE` or defaults to `'UTC'`
- **Line 73**: Uses location's timezone from DB or falls back to default
- **Line 80**: Returns timezone in response

### `api/lib/Database.php`
- **Line 58**: Sets default timezone to `'Australia/Melbourne'` in locations table schema
- **Line 60, 61, 85, 86, 96, 109, 110**: Uses SQLite `datetime('now')` for created_at/updated_at timestamps

---

## 3. Cache-Related Logic

### `api/lib/Cache.php`
- **Class**: `Cache` - SQLite-backed cache implementation
- **Table**: `api_cache` with columns: `provider`, `cache_key`, `json_data`, `fetched_at`, `expires_at`
- **Method `get()`**: Queries cache with `expires_at > datetime('now')` check
- **Method `set()`**: Inserts/updates cache with TTL calculation using `date('Y-m-d H:i:s', time() + $ttlSeconds)`
- **Method `clearExpired()`**: Deletes expired entries using `expires_at < datetime('now')`
- **Cache key format**: `{lat}:{lng}:{start_date}:{days}`

### `api/config.example.php`
- **Line 42-43**: `CACHE_TTL_WEATHER` = 3600 seconds (1 hour)
- **Line 45-46**: `CACHE_TTL_SUN` = 604800 seconds (7 days)
- **Line 48-49**: `CACHE_TTL_TIDES` = 43200 seconds (12 hours)

### `api/lib/ForecastData.php`
- **Line 13-18**: `fetchWeatherData()` - Checks cache before fetching, caches with `CACHE_TTL_WEATHER`
- **Line 91-92**: Sets cache with TTL from config or default 3600
- **Line 98-104**: `fetchSunData()` - Checks cache before fetching, caches with `CACHE_TTL_SUN`
- **Line 173-174**: Sets cache with TTL from config or default 604800
- **Line 180-185**: `fetchTidesData()` - Checks cache before fetching
- **Line 203-204**: Sets cache with TTL from config or default 43200

### `api/weather.php`
- **Line 49-56**: Checks cache before fetching from API
- **Line 128-129**: Caches result with `CACHE_TTL_WEATHER` (1 hour)

### `api/sun.php`
- **Line 49-56**: Checks cache before fetching from API
- **Line 127-128**: Caches result with `CACHE_TTL_SUN` (7 days)

### `api/tides.php`
- **Line 49-56**: Checks cache before fetching from API
- **Line 95-96**: Caches real tide data with `CACHE_TTL_TIDES` (12 hours)
- **Line 122-123**: Caches mock tide data with `CACHE_TTL_TIDES` (12 hours)

### `api/forecast.php`
- **Line 185**: Sets `cached` flag based on weather and sun data cache status (tides not included in flag)
- **Line 186**: Sets `cached_at` timestamp

### `api/lib/Database.php`
- **Line 90-101**: Creates `api_cache` table schema with indexes on `(provider, cache_key)` and `expires_at`

### `api/health.php`
- **Line 27, 45-50**: Tests cache directory write permissions

### `api/debug_db.php`
- **Line 37**: Counts entries in `api_cache` table

---

## 4. API Response Fields - `/api/forecast.php`

### Top-Level Response Structure
```php
{
  'error': boolean,
  'data': {
    'location': {
      'lat': float,
      'lng': float,
      'name': string,
      'region': string|null
    },
    'timezone': string,  // e.g., 'Australia/Melbourne'
    'forecast': array,   // Array of day objects (see below)
    'cached': boolean,   // true if weather AND sun data were cached
    'cached_at': string  // ISO 8601 timestamp with timezone offset
  },
  'warning': string|null  // Optional warning message
}
```

### Forecast Array Item Structure (per day)
```php
{
  'date': string,  // YYYY-MM-DD format
  'score': int,    // 0-100 fishing score
  'weather': {
    'temperature_max': float,
    'temperature_min': float,
    'wind_speed': float,
    'wind_direction': int,  // 0-360 degrees
    'precipitation': float,  // mm
    'cloud_cover': int,  // 0-100 percentage
    'conditions': string  // e.g., 'clear', 'partly_cloudy', 'overcast', 'rain'
  },
  'sun': {
    'sunrise': string,  // ISO 8601 timestamp
    'sunset': string,   // ISO 8601 timestamp
    'dawn': string,     // ISO 8601 timestamp (sunrise - 30 min)
    'dusk': string      // ISO 8601 timestamp (sunset + 2 hours)
  },
  'tides': {
    'events': [
      {
        'time': string,    // ISO 8601 timestamp
        'type': string,    // 'high' or 'low'
        'height': float    // meters
      }
    ],
    'change_windows': [
      {
        'start': string,      // ISO 8601 timestamp
        'end': string,        // ISO 8601 timestamp
        'type': string,       // 'rising' or 'falling'
        'event_time': string, // ISO 8601 timestamp
        'event_type': string  // 'high' or 'low'
      }
    ]
  },
  'best_bite_windows': [
    {
      'start': string,   // ISO 8601 timestamp
      'end': string,     // ISO 8601 timestamp
      'reason': string,  // Explanation text
      'quality': string  // 'excellent', 'good', or 'fair'
    }
  ],
  'recommended_species': [
    {
      'id': string,         // Species ID from database
      'name': string,       // Common name
      'confidence': float,  // 0.0-1.0
      'why': string         // Explanation text
    }
  ],
  'gear_suggestions': {
    'bait': array,        // Array of bait strings
    'lure': array,        // Array of lure strings
    'line_weight': string,  // e.g., '8-15lb'
    'leader': string,     // e.g., '10-20lb'
    'rig': string         // e.g., 'paternoster'
  },
  'reasons': [
    {
      'title': string,              // Short title
      'detail': string,             // Detailed explanation
      'contribution_points': int,   // Points contributed to score
      'severity': string,           // 'positive', 'negative', or 'neutral'
      'category': string           // 'weather', 'tide', 'dawn_dusk', or 'seasonality'
    }
  ]
}
```

---

## 5. DEV vs PROD Code Paths

### `api/config.example.php`
- **Line 54-56**: Auto-detects DEV_MODE based on `php_sapi_name() === 'cli-server'`
  - If PHP built-in server → `DEV_MODE = true`
  - Otherwise → `DEV_MODE = false`
- **Line 53**: Comment says "set to false in production"

### `api/lib/Validator.php`
- **Line 88-93**: Date validation allows past dates if `DEV_MODE = true`
  - Production: Past dates rejected
  - DEV: Past dates allowed (for testing)

### `api/seed.php`
- **Line 3, 6**: Comment says "DEV ONLY"
- **Line 18-22**: Entire endpoint gated by `DEV_MODE`
  - Production: Returns 403 error
  - DEV: Allows database seeding

### `api/debug_db.php`
- **Line 3, 6**: Comment says "DEV ONLY"
- **Line 18-22**: Entire endpoint gated by `DEV_MODE`
  - Production: Returns 403 error
  - DEV: Allows database inspection

### `api/config.local.php` (if exists)
- **Line 54-56**: Can override `DEV_MODE` to `true` for local development
- This file is NOT committed (in `.gitignore`)

### No Other DEV/PROD Differences Found
- All other endpoints behave identically in DEV and PROD
- Cache logic is the same
- Rate limiting is the same
- Error handling is the same
- API responses have the same structure

---

## Summary

### PHP Version Compatibility
✅ **All code is PHP 7.3.33 compatible** - No PHP 7.4+ features detected

### Date/Timezone Issues Found
⚠️ **Potential Issues:**
- `api/weather.php` line 45, 66: Uses `date()` and `strtotime()` (server timezone)
- `api/sun.php` line 45, 66: Uses `date()` and `strtotime()` (server timezone)
- `api/tides.php` line 45: Uses `date()` (server timezone)
- `api/lib/ForecastData.php` line 29, 114: Uses `strtotime()` (server timezone)
- `api/lib/Cache.php` line 43: Uses `date()` for expires_at (server timezone)
- `api/lib/RateLimiter.php` line 25, 32: Uses `date()` (server timezone)
- `api/lib/Scoring.php` line 103, 110, 119, 120: `DateTime` created without timezone (uses default)

### Cache Implementation
✅ **Consistent caching** across weather, sun, and tides endpoints
- Weather: 1 hour TTL
- Sun: 7 days TTL
- Tides: 12 hours TTL
- Cache key format: `{lat}:{lng}:{start_date}:{days}`
- Provider-based separation in `api_cache` table

### API Response
✅ **Well-structured** with all required fields
- Location info, timezone, forecast array, cache status
- Each day has complete weather, sun, tides, species, gear, and reasons

### DEV/PROD Differences
✅ **Minimal and well-controlled**
- Only 2 endpoints gated: `seed.php` and `debug_db.php`
- Date validation allows past dates in DEV only
- Auto-detection of local dev environment

