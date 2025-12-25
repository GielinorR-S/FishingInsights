# Database Schema Specification

## Overview

SQLite database (`fishinginsights.db`) stores:
- Curated location data (Victoria MVP: 20-40 locations)
- Species rules and recommendations
- API response cache (weather, sun, tides)
- Rate limiting data (optional)

## Database File Location

### Preferred: Outside Web Root
- **Path**: `/home/username/data/fishinginsights.db` (or similar, outside `public_html`)
- **Permissions**: Database file and directory must be writable by PHP execution user
- **Suggested**: File `664` or `660`, Directory `775` or `770` (adjust based on hosting)

### Fallback: Inside Web Root (Restricted)
- **Path**: `/public_html/data/fishinginsights.db`
- **Protection**: `.htaccess` in `data/` directory with `Deny from all`
- **Permissions**: Same as above (writable by PHP execution user)
- **Security**: Database file not directly accessible via HTTP

### Configuration
- Database path set in `api/config.local.php` as constant: `DB_PATH`
- When `api/` is beside `data/`: `__DIR__ . '/../data/fishinginsights.db'`
- When outside web root: `/home/username/data/fishinginsights.db`
- **Verification**: Use `/api/health.php` to confirm write capability

## Schema Definition

### Table: `locations`

Stores curated fishing locations for Victoria (MVP).

```sql
CREATE TABLE locations (
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

CREATE INDEX idx_locations_region ON locations(region);
CREATE INDEX idx_locations_coords ON locations(latitude, longitude);
```

**Fields**:
- `id`: Primary key, auto-incrementing integer
- `name`: Location name (e.g., "Port Phillip Bay", "Gippsland Lakes")
- `region`: Victorian region (e.g., "Melbourne", "Gippsland", "Mornington Peninsula")
- `latitude`: Decimal degrees (-90 to 90)
- `longitude`: Decimal degrees (-180 to 180)
- `timezone`: IANA timezone string (default: `Australia/Melbourne` for MVP)
- `description`: Optional text description
- `created_at`: ISO 8601 timestamp (SQLite TEXT)
- `updated_at`: ISO 8601 timestamp (SQLite TEXT)

**Indexes**:
- `idx_locations_region`: Fast region filtering
- `idx_locations_coords`: Fast nearest-location queries (if geolocation used)

### Table: `species_rules`

Stores species-specific fishing rules and recommendations.

```sql
CREATE TABLE species_rules (
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

CREATE INDEX idx_species_rules_season ON species_rules(season_start_month, season_end_month);
```

**Fields**:
- `id`: Primary key
- `species_id`: Unique identifier (e.g., "snapper", "bream", "flathead")
- `common_name`: Display name (e.g., "Snapper", "Black Bream")
- `scientific_name`: Optional scientific name
- `season_start_month`: Best fishing season start (1-12)
- `season_end_month`: Best fishing season end (1-12)
- `preferred_water_temp_min`: Minimum preferred water temperature (Celsius)
- `preferred_water_temp_max`: Maximum preferred water temperature (Celsius)
- `preferred_wind_max`: Maximum preferred wind speed (km/h)
- `preferred_conditions`: Text description (e.g., "calm, clear")
- `preferred_tide_state`: Preferred tide state (e.g., "rising", "falling", "any")
- `gear_bait`: Recommended bait (comma-separated or JSON array as TEXT)
- `gear_lure`: Recommended lures (comma-separated or JSON array as TEXT)
- `gear_line_weight`: Recommended line weight (e.g., "8-15lb")
- `gear_leader`: Recommended leader weight (e.g., "10-20lb")
- `gear_rig`: Recommended rig type (e.g., "paternoster")
- `description`: Optional species description
- `created_at`: ISO 8601 timestamp
- `updated_at`: ISO 8601 timestamp

**Indexes**:
- `idx_species_rules_season`: Fast season-based queries

### Table: `api_cache`

Generic cache table for all API responses (weather, sun, tides).

```sql
CREATE TABLE api_cache (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL,
    cache_key TEXT NOT NULL,
    json_data TEXT NOT NULL,
    fetched_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT NOT NULL,
    UNIQUE(provider, cache_key)
);

CREATE INDEX idx_api_cache_lookup ON api_cache(provider, cache_key);
CREATE INDEX idx_api_cache_expires ON api_cache(expires_at);
```

**Fields**:
- `id`: Primary key
- `provider`: Provider identifier (e.g., "weather", "sun", "tides")
- `cache_key`: Unique cache key (format: `{lat}:{lng}:{start_date}:{days}`)
- `json_data`: Cached JSON response (TEXT, stored as JSON string)
- `fetched_at`: When cache entry was created (ISO 8601 timestamp)
- `expires_at`: When cache entry expires (ISO 8601 timestamp)

**Indexes**:
- `idx_api_cache_lookup`: Fast cache lookups (provider + key)
- `idx_api_cache_expires`: Fast expired entry cleanup

**Cache Key Format** (provider stored in separate column, so key does NOT include provider prefix):
- Format: `{lat}:{lng}:{start_date}:{days}` (e.g., `-37.8:144.9:2024-01-15:7`)
- Provider column stores: `"weather"`, `"sun"`, or `"tides"`
- Lookup uses both `provider` and `cache_key` columns together

**TTL Calculation** (in seconds):
- Weather: 3600 (1 hour)
- Sun: 604800 (7 days)
- Tides: 43200 (12 hours)

### Table: `rate_limits` (Optional)

Stores IP-based rate limiting data.

```sql
CREATE TABLE rate_limits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip_address TEXT NOT NULL,
    endpoint TEXT NOT NULL,
    request_count INTEGER NOT NULL DEFAULT 1,
    window_start TEXT NOT NULL DEFAULT (datetime('now')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(ip_address, endpoint, window_start)
);

CREATE INDEX idx_rate_limits_lookup ON rate_limits(ip_address, endpoint, window_start);
CREATE INDEX idx_rate_limits_cleanup ON rate_limits(window_start);
```

**Fields**:
- `id`: Primary key
- `ip_address`: Client IP address (IPv4 or IPv6)
- `endpoint`: API endpoint (e.g., "forecast", "weather")
- `request_count`: Number of requests in current window
- `window_start`: Start of rate limit window (ISO 8601 timestamp)
- `created_at`: When record was created

**Indexes**:
- `idx_rate_limits_lookup`: Fast rate limit checks
- `idx_rate_limits_cleanup`: Fast cleanup of old windows

**Rate Limit Windows**:
- Per-minute: 60 requests (window: 60 seconds)
- Per-hour: 1000 requests (window: 3600 seconds)

## Database Initialization

### Seed Data

**Locations** (MVP: 20-40 Victorian locations):
- Populate via `data/seed.sql` or PHP migration script
- Include: name, region, lat/lng, timezone (all `Australia/Melbourne` for MVP)

**Species Rules** (MVP: 10-15 common Victorian species):
- Snapper, Bream, Flathead, Whiting, Salmon, Trevally, etc.
- Populate seasonality, preferred conditions, gear recommendations

### Migration Strategy

**Option 1: SQL File**
- `data/seed.sql` contains all `INSERT` statements
- Run manually via SQLite CLI or PHP script on first deployment

**Option 2: PHP Migration Script**
- `api/migrate.php` creates tables and seeds data
- Run once: `php api/migrate.php`
- Idempotent: checks if tables exist before creating

**Recommended**: Use PHP migration script for easier deployment automation.

## Database Permissions

### File Permissions
- **Database File**: Must be writable by PHP execution user
  - Suggested: `664` (read-write for owner/group) or `660` (read-write for owner/group only)
- **Database Directory**: Must be writable by PHP execution user
  - Suggested: `775` (read-write-execute for owner/group) or `770` (read-write-execute for owner/group only)
- **Verification**: Rely on `/api/health.php` to confirm write capability - this is the authoritative check
- Adjust permissions based on hosting setup (web server user must be able to write)

### Security Considerations
- Database file should NOT be world-writable (`666`)
- If database is inside web root, protect with `.htaccess`:
  ```apache
  <FilesMatch "\.db$">
      Order allow,deny
      Deny from all
  </FilesMatch>
  ```

## Maintenance

### Cache Cleanup
- Expired cache entries can be cleaned periodically
- Run cleanup on health check endpoint or via cron (if available)
- SQL: `DELETE FROM api_cache WHERE expires_at < datetime('now')`

### Rate Limit Cleanup
- Old rate limit windows can be cleaned periodically
- Keep last 24 hours of data
- SQL: `DELETE FROM rate_limits WHERE window_start < datetime('now', '-1 day')`

### Backup Strategy
- SQLite database can be backed up by copying `.db` file
- Recommended: Daily backup (if hosting supports cron)
- Backup location: Outside web root, compressed

## Open Questions

- Should we store user favourites in database or localStorage? **DECISION: localStorage only for MVP. Database storage can be added later for multi-device sync.**
- Should we track API usage/credits in database? **DECISION: No for MVP. Monitor via external WorldTides dashboard. Can add usage tracking table later.**
- Should we version the database schema? **DECISION: No for MVP. Schema is stable. Add versioning if schema changes are needed post-MVP.**

