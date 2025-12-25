# Architecture Specification

## Monorepo Structure

```
FishingInsights/
├── app/                    # React + TypeScript + Vite frontend
│   ├── src/
│   │   ├── components/     # Reusable UI components
│   │   ├── pages/          # Screen/page components
│   │   ├── hooks/          # Custom React hooks
│   │   ├── services/       # API client, localStorage utilities
│   │   ├── types/          # TypeScript type definitions
│   │   ├── utils/          # Helper functions
│   │   ├── App.tsx         # Root component, routing
│   │   └── main.tsx        # Entry point
│   ├── public/             # Static assets, manifest.json, icons
│   ├── index.html
│   ├── vite.config.ts
│   ├── tailwind.config.js
│   ├── tsconfig.json
│   └── package.json
├── api/                    # PHP 7.3.33 backend
│   ├── health.php          # Health check endpoint
│   ├── weather.php         # Weather data endpoint
│   ├── sun.php             # Sunrise/sunset endpoint
│   ├── tides.php           # Tides data endpoint
│   ├── forecast.php        # Aggregated forecast endpoint (optional)
│   ├── config.example.php  # Example config (committed)
│   ├── config.local.php    # Local config (NOT committed, .gitignore)
│   ├── lib/
│   │   ├── Cache.php       # SQLite cache wrapper
│   │   ├── Database.php    # PDO connection wrapper
│   │   ├── Validator.php   # Input validation
│   │   └── RateLimiter.php    # Simple IP-based rate limiting
│   └── .htaccess           # Apache config (routing, CORS, security)
├── docs/                   # Documentation (this directory)
├── data/                   # SQLite database + seed data (outside web root if possible)
│   ├── fishinginsights.db
│   └── seed.sql            # Initial locations, species rules
├── .gitignore
└── README.md
```

## Frontend Architecture

### Routing
- **Library**: React Router v6 (or v7 if stable)
- **Routes**:
  - `/` - Home screen
  - `/locations` - Location picker
  - `/forecast/:locationId` - Forecast screen
  - `/species/:speciesId` - Species detail (optional)
  - `/references` - References screen
- **SPA Fallback**: `.htaccess` rewrites all non-API routes to `index.html`

### State Management
- **Approach**: React Context API + `useState`/`useReducer` (no Redux for MVP)
- **Contexts**:
  - `FavouritesContext`: Manages favourite locations (persists to localStorage)
  - `LocationContext`: Current selected location
  - `ForecastContext`: Cached forecast data (optional, can use React Query if needed)
- **Local State**: Component-level state for UI (modals, expanded sections, form inputs)
- **Persistence**: localStorage for favourites, optional for cached forecasts (TTL-aware)

### Data Fetching
- **Strategy**: Custom hooks (`useForecast`, `useWeather`, etc.) wrapping `fetch()`
- **Error Handling**: Try-catch in hooks, error boundaries for component-level errors
- **Loading States**: Per-request loading flags in hooks
- **Caching**: 
  - API responses cached in SQLite (backend)
  - Optional: localStorage cache for forecasts (24h TTL) to reduce API calls
  - Service worker cache for static assets and API responses (offline support)

### PWA Configuration
- **Service Worker**: Vite PWA plugin (workbox)
- **Manifest**: `manifest.json` with icons, name, theme, display mode
- **Offline Strategy**: 
  - Cache shell (HTML, CSS, JS) on install
  - Cache API responses with network-first strategy
  - Show offline indicator when network unavailable
  - Display last cached forecast when offline

### Build & Deployment
- **Build Command**: `npm run build` (Vite)
- **Output**: `app/dist/` (static files)
- **Upload**: Upload `dist/` contents to web root (or subdirectory)

## Backend Architecture

### PHP Version Compatibility
- **Target**: PHP 7.3.33 ONLY
- **Forbidden Syntax**:
  - Arrow functions: `fn($x) => $x + 1` ❌
  - Typed properties: `public string $name;` ❌
  - Null coalescing assignment: `$x ??= $y;` ❌
  - Union types: `string|int $x` ❌
  - Match expressions: `match($x) { ... }` ❌
- **Allowed Syntax**:
  - Traditional functions: `function($x) { return $x + 1; }` ✅
  - Untyped properties: `public $name;` ✅
  - Null coalescing: `$x ?? $y` ✅
  - Type hints (PHP 7.0+): `function foo(string $x): int` ✅

### Routing Strategy
- **Decision**: Endpoint-per-file (no router framework)
- **Rationale**: 
  - Minimal dependencies (no Composer required for routing)
  - Easy to debug on shared hosting
  - Clear file-to-endpoint mapping
  - `.htaccess` handles URL rewriting if needed
- **Structure**: Each endpoint is a standalone PHP file that:
  1. Includes shared lib files (Database.php, Cache.php, etc.)
  2. Validates input
  3. Checks rate limits
  4. Executes logic
  5. Returns JSON response
  6. Handles errors consistently

### Input Validation
- **Approach**: Custom `Validator` class (no external library)
- **Methods**:
  - `validateLatLng($lat, $lng)`: Range checks (-90 to 90, -180 to 180)
  - `validateDays($days)`: Integer, 1-14 range
  - `validateDate($date)`: ISO 8601 format (YYYY-MM-DD), allows today, disallows past dates unless `DEV_MODE` constant is true
  - `sanitizeString($str)`: Basic XSS prevention (htmlspecialchars for output)
- **Error Response**: `400 Bad Request` with JSON error object

### Error Handling
- **Strategy**: Try-catch in each endpoint, consistent JSON error schema
- **Error Response Format**:
```json
{
  "error": true,
  "message": "Human-readable error message",
  "code": "ERROR_CODE",
  "details": {} // Optional additional context
}
```
- **HTTP Status Codes**:
  - `200`: Success
  - `400`: Bad Request (validation error)
  - `401`: Unauthorized (API key invalid, if applicable)
  - `429`: Too Many Requests (rate limit exceeded)
  - `500`: Internal Server Error (server-side issue)
  - `503`: Service Unavailable (external API down, cached data unavailable)

### Database Connection
- **File**: `lib/Database.php`
- **Pattern**: Singleton or static factory method
- **Connection**: PDO SQLite, persistent connection (reuse across requests)
- **Error Handling**: PDO exceptions caught, logged (if logging available), returned as 500 error
- **Path**: Configurable via `config.local.php` (default: `__DIR__ . '/../data/fishinginsights.db'`)

### Caching Strategy
- **File**: `lib/Cache.php`
- **Table**: `api_cache` (see database-schema.md)
- **Key Format**: `{lat}:{lng}:{start_date}:{days}` (e.g., `-37.8:144.9:2024-01-15:7`)
  - Provider is stored in separate `provider` column, so cache_key does NOT include provider prefix
- **TTL** (LOCKED to minimize paid API spend):
  - Weather: 1 hour (3600 seconds)
  - Sun: 7 days (604800 seconds)
  - Tides: 12 hours (43200 seconds)
- **Methods**:
  - `get($provider, $key)`: Returns cached JSON or `null` (looks up by provider + cache_key)
  - `set($provider, $key, $data, $ttl_seconds)`: Stores JSON with expiration
  - `clearExpired()`: Optional cleanup (run on health check or cron)

### Rate Limiting
- **File**: `lib/RateLimiter.php`
- **Strategy**: IP-based, in-memory or SQLite table
- **Limits**:
  - 60 requests per minute per IP
  - 1000 requests per hour per IP
- **Storage**: SQLite table `rate_limits` (ip, endpoint, count, window_start)
- **Response**: `429 Too Many Requests` with `Retry-After` header

## API Endpoints

### GET /api/health.php
**Purpose**: System health check and diagnostics

**Request**: No parameters

**Response** (200 OK):
```json
{
  "status": "ok",
  "php_version": "7.3.33",
  "has_pdo": true,
  "has_pdo_sqlite": true,
  "sqlite_db_path": "/path/to/db (redacted if sensitive)",
  "can_write_db": true,
  "can_write_cache": true,
  "timestamp": "2024-01-15T10:30:00+11:00",
  "timezone": "Australia/Melbourne"
}
```

**Error Response** (500):
```json
{
  "error": true,
  "message": "Health check failed",
  "code": "HEALTH_CHECK_ERROR",
  "details": {
    "missing_extension": "pdo_sqlite"
  }
}
```

**How to Interpret Health Results**:
- `status: "ok"` - All systems operational
- `php_version` - Must be 7.3.33 (or compatible 7.3.x)
- `has_pdo: false` - Critical: PDO extension missing, app will not work
- `has_pdo_sqlite: false` - Critical: SQLite support missing, app will not work
- `can_write_db: false` - Critical: Database is read-only, caching and writes will fail
- `can_write_cache: false` - Warning: Cache directory not writable, API responses won't be cached (performance impact)
- If any critical check fails, deployment is blocked until resolved

### GET /api/weather.php
**Purpose**: Fetch weather data for a location

**Parameters**:
- `lat` (required, float): Latitude (-90 to 90)
- `lng` (required, float): Longitude (-180 to 180)
- `start` (optional, string): Start date (ISO 8601, default: today)
- `days` (optional, int): Number of days (1-14, default: 7)

**Response** (200 OK):
```json
{
  "error": false,
  "data": {
    "location": { "lat": -37.8, "lng": 144.9 },
    "timezone": "Australia/Melbourne",
    "forecast": [
      {
        "date": "2024-01-15",
        "temperature_max": 25.5,
        "temperature_min": 18.2,
        "wind_speed": 15.3,
        "wind_direction": 180,
        "precipitation": 0.0,
        "cloud_cover": 20,
        "conditions": "clear"
      }
    ],
    "cached": true,
    "cached_at": "2024-01-15T10:00:00+11:00"
  }
}
```

**Error Response** (400/500):
```json
{
  "error": true,
  "message": "Invalid latitude",
  "code": "VALIDATION_ERROR"
}
```

### GET /api/sun.php
**Purpose**: Fetch sunrise/sunset times

**Parameters**: Same as weather.php

**Response** (200 OK):
```json
{
  "error": false,
  "data": {
    "location": { "lat": -37.8, "lng": 144.9 },
    "timezone": "Australia/Melbourne",
    "times": [
      {
        "date": "2024-01-15",
        "sunrise": "2024-01-15T06:15:00+11:00",
        "sunset": "2024-01-15T20:30:00+11:00",
        "dawn": "2024-01-15T05:45:00+11:00",
        "dusk": "2024-01-15T21:00:00+11:00"
      }
    ],
    "cached": true,
    "cached_at": "2024-01-15T10:00:00+11:00"
  }
}
```

### GET /api/tides.php
**Purpose**: Fetch tide data

**Parameters**: Same as weather.php

**Response** (200 OK):
```json
{
  "error": false,
  "data": {
    "location": { "lat": -37.8, "lng": 144.9 },
    "timezone": "Australia/Melbourne",
    "tides": [
      {
        "date": "2024-01-15",
        "events": [
          { "time": "2024-01-15T02:30:00+11:00", "type": "low", "height": 0.5 },
          { "time": "2024-01-15T08:45:00+11:00", "type": "high", "height": 2.1 },
          { "time": "2024-01-15T14:20:00+11:00", "type": "low", "height": 0.6 },
          { "time": "2024-01-15T20:10:00+11:00", "type": "high", "height": 2.0 }
        ],
        "change_windows": [
          { "start": "2024-01-15T01:30:00+11:00", "end": "2024-01-15T03:30:00+11:00", "type": "rising", "event_time": "2024-01-15T02:30:00+11:00", "event_type": "low" },
          { "start": "2024-01-15T07:45:00+11:00", "end": "2024-01-15T09:45:00+11:00", "type": "falling", "event_time": "2024-01-15T08:45:00+11:00", "event_type": "high" },
          { "start": "2024-01-15T13:20:00+11:00", "end": "2024-01-15T15:20:00+11:00", "type": "rising", "event_time": "2024-01-15T14:20:00+11:00", "event_type": "low" },
          { "start": "2024-01-15T19:10:00+11:00", "end": "2024-01-15T21:10:00+11:00", "type": "falling", "event_time": "2024-01-15T20:10:00+11:00", "event_type": "high" }
        ]
      }
    ],
    "cached": true,
    "cached_at": "2024-01-15T10:00:00+11:00",
    "mock_tides": false
  }
}
```

**Fallback Mode**: If WorldTides API fails or credits exhausted, return mock tides (documented in response):
```json
{
  "error": false,
  "data": { ... },
  "mock_tides": true,
  "message": "Using estimated tide data"
}
```

### GET /api/forecast.php
**Purpose**: Aggregated forecast endpoint (recommended for frontend)

**Parameters**: Same as weather.php

**Response** (200 OK):
```json
{
  "error": false,
  "data": {
    "location": { "lat": -37.8, "lng": 144.9, "name": "Port Phillip Bay" },
    "timezone": "Australia/Melbourne",
    "forecast": [
      {
        "date": "2024-01-15",
        "score": 85,
        "weather": { ... }, // Full weather object (from /api/weather.php schema)
        "sun": { ... },     // Full sun object (from /api/sun.php schema)
        "tides": { ... },   // Full tides object (from /api/tides.php schema)
        "best_bite_windows": [
          { "start": "2024-01-15T06:15:00+11:00", "end": "2024-01-15T08:00:00+11:00", "reason": "dawn + rising tide" },
          { "start": "2024-01-15T19:00:00+11:00", "end": "2024-01-15T20:30:00+11:00", "reason": "dusk + falling tide" }
        ],
        "recommended_species": [
          { "id": "snapper", "name": "Snapper", "confidence": 0.8 }
        ],
        "gear_suggestions": {
          "bait": ["pilchards", "squid"],
          "lure": ["soft plastics", "metal lures"],
          "line_weight": "8-15lb",
          "leader": "10-20lb",
          "rig": "paternoster or running sinker"
        },
        "reasons": [
          {
            "title": "Excellent weather",
            "detail": "Light winds, clear skies",
            "contribution_points": 25,
            "severity": "positive",
            "category": "weather"
          }
        ]
      }
    ],
    "cached": true,
    "cached_at": "2024-01-15T10:00:00+11:00"
  }
}
```

**DECISION LOCKED**: `/api/forecast.php` is the PRIMARY endpoint for frontend. Frontend should call this endpoint exclusively for forecast data. Backend aggregates weather/sun/tides internally. This reduces frontend complexity, minimizes API round trips, and ensures consistent data aggregation.

**Timestamp Policy**:
- All timestamps MUST be ISO 8601 format with timezone offset: `YYYY-MM-DDTHH:mm:ss+TZ:TZ` (e.g., `2024-01-15T06:15:00+11:00`)
- All times MUST be in the location's IANA timezone. For Victoria (MVP), use `Australia/Melbourne` (UTC+10 in winter, UTC+11 in summer with DST).
- Include `timezone` field in all responses: `"timezone": "Australia/Melbourne"`
- Frontend converts to user's local timezone for display if needed.

## Security Model

### Secrets Management
- **Config Files**:
  - `config.example.php`: Committed to repo, contains placeholder values
  - `config.local.php`: NOT committed (`.gitignore`), contains real API keys
- **API Keys**: Stored in `config.local.php` as PHP constants or array
- **Never Exposed**: API keys never sent to frontend, all external API calls server-side only

### CORS Rules
- **Default**: Same-origin only (no CORS headers unless needed)
- **If Subdomain**: Allow specific origin via `.htaccess` or PHP header
- **No Wildcard**: Never use `Access-Control-Allow-Origin: *` for authenticated endpoints

### Input Sanitization
- **Validation**: All input validated before use (lat/lng ranges, date formats)
- **SQL Injection**: PDO prepared statements (no string concatenation in queries)
- **XSS**: `htmlspecialchars()` for any user input displayed in HTML (frontend responsibility, but backend should sanitize if returning HTML)

### Rate Limiting
- **Implementation**: IP-based, SQLite-backed
- **Limits**: 60/min, 1000/hour per IP
- **Bypass**: Optional whitelist for health check endpoint

## Hosting Constraints Plan

### SQLite Database Location
- **Preferred**: Outside web root (e.g., `/home/username/data/fishinginsights.db`)
- **Fallback**: Inside web root with restricted access:
  - `.htaccess` in data directory: `Deny from all`
  - Database file and directory must be writable by PHP execution user
- **Config**: Path set in `config.local.php`
  - When `api/` is beside `data/`: `__DIR__ . '/../data/fishinginsights.db'`
  - When outside web root: `/home/username/data/fishinginsights.db`

### Writable Directories
- **Required**: Directory containing SQLite DB must be writable by PHP execution user
- **Permissions**: 
  - Database file: `664` (read-write for owner/group) or `660` (read-write for owner/group only)
  - Directory: `775` (read-write-execute for owner/group) or `770` (read-write-execute for owner/group only)
  - Adjust based on hosting setup (web server user must be able to write)
- **Check**: Health endpoint (`/api/health.php`) verifies write capability - rely on this to confirm permissions are correct

### SPA Routing (.htaccess)
```apache
# Rewrite all non-API routes to index.html
RewriteEngine On
RewriteBase /

# Don't rewrite API endpoints
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.html [L]

# Security: Deny access to config files
<FilesMatch "^(config\.local\.php|\.env)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Security: Deny access to data directory if inside web root
<IfModule mod_rewrite.c>
    RewriteRule ^data/ - [F,L]
</IfModule>
```

### PHP Configuration
- **Required Extensions**: `pdo`, `pdo_sqlite`
- **Memory Limit**: 128M minimum (for API aggregation)
- **Max Execution Time**: 30 seconds (for external API calls)

## Open Questions

- Should we use Composer for any dependencies? **DECISION: No, plain PHP only. If absolutely necessary, single-file libraries (e.g., single-file JSON validator) can be included directly.**
- Should health check be public or protected? **DECISION: Public (no auth), but rate-limited. Useful for monitoring.**
- How to handle timezone for date/time responses? **DECISION LOCKED: All times in ISO 8601 with timezone offset, using IANA timezone `Australia/Melbourne` for Victoria. Frontend converts to user's local timezone for display.**
- Should we support batch requests (multiple locations)? **DECISION: No, MVP = one location per request. Can be added later without breaking changes.**

