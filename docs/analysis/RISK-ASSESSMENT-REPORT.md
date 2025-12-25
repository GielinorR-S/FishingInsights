# FishingInsights Risk Assessment Report

**Generated:** 2025-01-XX  
**Purpose:** Identify high-risk vs safe-to-change files and implicit contracts  
**Assumption:** Breaking production is unacceptable

---

## 1. HIGH-RISK FILES (Critical Logic / High Coupling)

### 🔴 **CRITICAL - DO NOT MODIFY WITHOUT EXTENSIVE TESTING**

#### `api/lib/Database.php`
**Risk Level:** 🔴🔴🔴 **CRITICAL**
- **Why:** Singleton pattern, creates ALL database tables, used by EVERY endpoint
- **Dependencies:** 
  - Required by: `forecast.php`, `seed.php`, `debug_db.php`, `locations.php`, `tides.php`, `weather.php`, `sun.php`, `health.php`, `Cache.php`, `RateLimiter.php`
  - Creates schema for: `locations`, `species_rules`, `api_cache`, `rate_limits`
- **Breaking Changes:**
  - Schema changes break ALL endpoints
  - `getInstance()` pattern change breaks everything
  - `DB_PATH` constant dependency
- **Impact:** Entire application fails if broken

#### `api/forecast.php`
**Risk Level:** 🔴🔴🔴 **CRITICAL**
- **Why:** PRIMARY endpoint, complex aggregation logic, frontend depends on exact response structure
- **Dependencies:**
  - Uses: `Database`, `Cache`, `Validator`, `RateLimiter`, `Scoring`, `ForecastData`, `LocationHelper`, `utils`
  - Called by: Frontend `app/src/services/api.ts` → `app/src/pages/Forecast.tsx`
- **Breaking Changes:**
  - Response structure changes break frontend
  - Field name changes break TypeScript interfaces
  - Date/timezone logic changes break date calculations
  - Score calculation changes affect user experience
- **Implicit Contracts:**
  - Frontend expects `forecast[].date` to be YYYY-MM-DD
  - Frontend expects `forecast[].score` to be 0-100 integer
  - Frontend expects `forecast[].weather.temperature_min/max` to exist
  - Frontend expects `forecast[].tides.events[]` and `change_windows[]` arrays
  - Frontend expects `forecast[].recommended_species[]` array with `id`, `name`, `confidence`, `why`
  - Frontend expects `forecast[].gear_suggestions.bait/lure/line_weight/leader/rig`
  - Frontend expects `forecast[].reasons[]` array with `title`, `detail`, `severity`, `category`
  - Frontend expects `data.location.name` and `data.location.region` (nullable)
  - Frontend expects `data.warning` to be optional string
- **Impact:** Frontend breaks, primary user-facing feature fails

#### `api/lib/utils.php`
**Risk Level:** 🔴🔴 **HIGH**
- **Why:** Used by ALL endpoints for JSON responses and timezone handling
- **Dependencies:**
  - Required by: `forecast.php`, `seed.php`, `debug_db.php`, `locations.php`, `tides.php`, `weather.php`, `sun.php`, `health.php`, `ForecastData.php`
- **Functions:**
  - `sendJson()` - ALL endpoints use this for responses
  - `sendError()` - ALL endpoints use this for errors
  - `getTimezoneDateTime()` - Used for timestamps
  - `formatIso8601()` - Used for ALL ISO 8601 timestamps
- **Breaking Changes:**
  - Response format changes break ALL API consumers
  - Error format changes break error handling
  - Timezone format changes break date parsing
- **Impact:** All endpoints fail or return invalid data

#### `api/lib/Scoring.php`
**Risk Level:** 🔴🔴 **HIGH**
- **Why:** Core business logic, score calculation affects user experience
- **Dependencies:**
  - Used by: `forecast.php` (primary endpoint)
- **Methods:**
  - `calculateWeatherScore()` - Weather component (0-100)
  - `calculateTideScore()` - Tide component (0-100)
  - `calculateDawnDuskScore()` - Dawn/dusk overlap (0-100)
  - `calculateSeasonalityScore()` - Species seasonality (0-100)
  - `calculateScore()` - Composite weighted score (0-100)
- **Breaking Changes:**
  - Weight changes affect all scores
  - Algorithm changes affect user trust
  - Score range changes break frontend expectations (expects 0-100)
- **Implicit Contracts:**
  - Frontend expects `score` to be integer 0-100
  - Score calculation weights are hardcoded (0.35, 0.30, 0.20, 0.15)
- **Impact:** Scores become meaningless, user experience degrades

#### `api/lib/Cache.php`
**Risk Level:** 🔴🔴 **HIGH**
- **Why:** Used by all data-fetching endpoints, cache key format is critical
- **Dependencies:**
  - Used by: `forecast.php`, `tides.php`, `weather.php`, `sun.php`, `ForecastData.php`
  - Depends on: `Database` singleton
- **Breaking Changes:**
  - Cache key format changes invalidate all cached data
  - TTL calculation changes affect cache behavior
  - Table structure changes break cache lookups
- **Implicit Contracts:**
  - Cache key format: `{lat}:{lng}:{start_date}:{days}` (documented but not enforced)
  - Provider names: `'weather'`, `'sun'`, `'tides'` (hardcoded strings)
- **Impact:** Cache invalidation, performance degradation, API cost increases

#### `api/lib/Validator.php`
**Risk Level:** 🔴🔴 **HIGH**
- **Why:** Input validation for ALL endpoints, security-critical
- **Dependencies:**
  - Used by: `forecast.php`, `locations.php`, `tides.php`, `weather.php`, `sun.php`
- **Breaking Changes:**
  - Validation rule changes allow invalid data
  - Date validation changes break timezone handling
  - Range validation changes break API contracts
- **Implicit Contracts:**
  - `validateDays()` returns 1-14 (frontend may assume this)
  - `validateDate()` allows past dates only in DEV_MODE (not documented in API)
- **Impact:** Invalid data accepted, security vulnerabilities, data corruption

### 🟠 **HIGH RISK - MODIFY WITH CAUTION**

#### `api/lib/ForecastData.php`
**Risk Level:** 🟠🟠 **MEDIUM-HIGH**
- **Why:** Internal data fetching, used by primary endpoint
- **Dependencies:**
  - Used by: `forecast.php` (PRIMARY endpoint)
  - Uses: `Cache`, `utils`
  - Functions: `fetchWeatherData()`, `fetchSunData()`, `fetchTidesData()`, `generateMockTides()`
- **Breaking Changes:**
  - Data structure changes break forecast aggregation
  - Mock tide generation changes affect fallback behavior
  - Cache key format must match `Cache.php` expectations
- **Implicit Contracts:**
  - Returns arrays with `'cached'` and `'cached_at'` fields
  - Weather data structure expected by `Scoring::calculateWeatherScore()`
  - Sun data structure expected by `Scoring::calculateDawnDuskScore()`
  - Tides data structure expected by `Scoring::calculateTideScore()`
- **Impact:** Forecast endpoint returns invalid or missing data

#### `api/lib/LocationHelper.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Used by primary endpoint for location resolution
- **Dependencies:**
  - Used by: `forecast.php` (PRIMARY endpoint)
- **Breaking Changes:**
  - Haversine distance calculation changes affect location matching
  - Max distance (40km) is hardcoded in `forecast.php`
- **Implicit Contracts:**
  - Returns location with `name` and `region` fields (used by frontend)
  - Distance threshold (40km) not configurable
- **Impact:** Location resolution fails, "Unknown Location" appears

#### `api/config.example.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Default configuration, auto-detects DEV_MODE
- **Dependencies:**
  - Loaded by: ALL endpoints
- **Breaking Changes:**
  - Constant name changes break all endpoints
  - DEV_MODE detection logic affects seed/debug endpoints
  - Default timezone affects all date calculations
- **Implicit Contracts:**
  - `DEV_MODE` auto-detection based on `php_sapi_name() === 'cli-server'`
  - `DEFAULT_TIMEZONE` defaults to `'Australia/Melbourne'`
  - Cache TTL constants must match usage in endpoints
- **Impact:** Configuration breaks, endpoints fail to load

#### `api/lib/RateLimiter.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Security-critical, used by all public endpoints
- **Dependencies:**
  - Used by: `forecast.php`, `locations.php`, `tides.php`, `weather.php`, `sun.php`
  - Depends on: `Database` singleton
- **Breaking Changes:**
  - Rate limit logic changes affect API availability
  - Window calculation changes affect limit enforcement
- **Implicit Contracts:**
  - Limits: 60/min, 1000/hour (hardcoded defaults)
  - Returns `['allowed' => bool, 'retry_after' => int|null]`
- **Impact:** API abuse, service unavailability, or false positives

---

## 2. SAFE-TO-CHANGE FILES (UI-Only / Isolated Logic)

### 🟢 **LOW RISK - SAFE TO MODIFY**

#### `app/src/pages/Home.tsx`
**Risk Level:** 🟢 **LOW**
- **Why:** UI-only, no API dependencies, placeholder content
- **Dependencies:** None (static content)
- **Safe Changes:** Styling, layout, text content, navigation

#### `app/src/pages/References.tsx`
**Risk Level:** 🟢 **LOW**
- **Why:** UI-only, no API dependencies, static content
- **Dependencies:** None
- **Safe Changes:** Content, links, styling

#### `app/src/components/BottomNav.tsx`
**Risk Level:** 🟢 **LOW**
- **Why:** UI-only navigation component
- **Dependencies:** React Router (stable)
- **Safe Changes:** Navigation items, styling, icons

#### `app/src/contexts/FavouritesContext.tsx`
**Risk Level:** 🟢 **LOW**
- **Why:** Frontend-only state management, localStorage-based
- **Dependencies:** None (self-contained)
- **Safe Changes:** Storage key name, data structure (if frontend-only)

#### `app/src/pages/Locations.tsx`
**Risk Level:** 🟢🟠 **LOW-MEDIUM**
- **Why:** UI component, but depends on API response structure
- **Dependencies:** `api.ts` → `/api/locations.php`
- **Safe Changes:** Styling, layout, search UI
- **Risky Changes:** Field access (`location.name`, `location.region`, `location.lat`, `location.lng`)
- **Implicit Contracts:**
  - Expects `locations[]` array with `id`, `name`, `region`, `lat`, `lng`
  - Expects `error: false` for success case
- **Note:** Safe for UI changes, but field access must match API

#### `app/src/pages/Forecast.tsx`
**Risk Level:** 🟠 **MEDIUM** (UI is safe, but tightly coupled to API)
- **Why:** UI component, but heavily depends on exact API response structure
- **Dependencies:** `api.ts` → `/api/forecast.php`
- **Safe Changes:** Styling, layout, date picker UI, display formatting
- **Risky Changes:** Any field access (see implicit contracts below)
- **Implicit Contracts:** (See section 3)

#### `app/src/services/api.ts`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** API client, but TypeScript interfaces enforce contracts
- **Dependencies:** Backend API endpoints
- **Safe Changes:** Helper functions, error handling improvements
- **Risky Changes:** Interface definitions (must match backend)
- **Note:** TypeScript provides some protection, but interfaces must match backend

#### `api/seed.php`
**Risk Level:** 🟠 **MEDIUM** (DEV-only, but affects data integrity)
- **Why:** DEV-only endpoint, but seeds critical data
- **Dependencies:** `Database`, `utils`, `data/seed.sql`
- **Safe Changes:** Error messages, logging
- **Risky Changes:** SQL execution logic, data validation
- **Impact:** Broken seed data affects all endpoints

#### `api/debug_db.php`
**Risk Level:** 🟢 **LOW** (DEV-only)
- **Why:** DEV-only endpoint, debugging tool
- **Dependencies:** `Database`, `utils`
- **Safe Changes:** Output format, additional debug info
- **Impact:** None (DEV-only, not used in production)

#### `api/health.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Health check endpoint, used for monitoring
- **Dependencies:** `Database`, `utils`
- **Safe Changes:** Additional health checks, output format
- **Risky Changes:** Response structure (if monitored externally)
- **Implicit Contracts:**
  - Monitoring tools may expect `status: 'ok'`
  - May expect `has_pdo_sqlite: true`, `can_write_db: true`

#### `api/weather.php`, `api/sun.php`, `api/tides.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Individual endpoints, but used internally by forecast
- **Dependencies:** `Database`, `Cache`, `Validator`, `RateLimiter`, `utils`
- **Safe Changes:** Error messages, logging
- **Risky Changes:** Response structure (if used by external tools)
- **Note:** `forecast.php` uses internal functions, not these endpoints

#### `api/locations.php`
**Risk Level:** 🟠 **MEDIUM**
- **Why:** Public endpoint, used by frontend
- **Dependencies:** `Database`, `Validator`, `RateLimiter`, `utils`
- **Safe Changes:** Error messages, additional filters
- **Risky Changes:** Response structure (frontend depends on it)
- **Implicit Contracts:**
  - Frontend expects `data.locations[]` array
  - Frontend expects `error: false` for success
  - Frontend expects fields: `id`, `name`, `region`, `lat`, `lng`, `timezone`

---

## 3. IMPLICIT CONTRACTS (Assumptions Not Documented)

### Frontend-Backend API Contracts

#### `/api/forecast.php` Response Structure
**Critical Assumptions:**
1. **Top-level structure:**
   - `error: boolean` - Frontend checks `if (forecast.error)` 
   - `data: object` - Frontend accesses `forecast.data`
   - `data.warning?: string` - Frontend conditionally displays: `{forecast.data.warning && <div>...}`

2. **Location object:**
   - `data.location.name: string` - Frontend displays: `{location.name}`
   - `data.location.region: string | null` - Frontend conditionally displays: `{location.region && <p>...}`
   - **NOT documented:** `region` can be null, frontend handles this

3. **Forecast array:**
   - `data.forecast: Array` - Frontend maps: `forecastDays.map((day, idx) => ...)`
   - **NOT documented:** Array length matches `days` parameter (assumed 7)

4. **Per-day structure:**
   - `forecast[].date: string` - Frontend expects YYYY-MM-DD format, uses `new Date(day.date)`
   - `forecast[].score: number` - Frontend expects 0-100, displays: `{Math.round(day.score)}`
   - **NOT documented:** Score is integer, frontend rounds it

5. **Weather object:**
   - `forecast[].weather.temperature_min: number` - Frontend displays: `{day.weather.temperature_min}°`
   - `forecast[].weather.temperature_max: number` - Frontend displays: `{day.weather.temperature_max}°`
   - `forecast[].weather.wind_speed: number` - Frontend displays: `{day.weather.wind_speed} km/h`
   - `forecast[].weather.precipitation: number` - Frontend displays: `{day.weather.precipitation} mm`
   - `forecast[].weather.conditions: string` - Frontend capitalizes: `capitalize({day.weather.conditions})`
   - **NOT documented:** All fields are required, no nulls

6. **Tides object:**
   - `forecast[].tides.events: Array` - Frontend checks: `day.tides.events && day.tides.events.length > 0`
   - `forecast[].tides.change_windows: Array` - Frontend checks: `day.tides.change_windows && ...`
   - **NOT documented:** Can be empty arrays `[]`, frontend handles this
   - **NOT documented:** Fallback to `['events' => [], 'change_windows' => []]` if null

7. **Best bite windows:**
   - `forecast[].best_bite_windows: Array` - Frontend conditionally renders: `{day.best_bite_windows && day.best_bite_windows.length > 0 && ...}`
   - `forecast[].best_bite_windows[].start: string` - Frontend formats as time: `formatTime(window.start)`
   - `forecast[].best_bite_windows[].end: string` - Frontend formats as time
   - `forecast[].best_bite_windows[].quality: 'excellent' | 'good' | 'fair'` - Frontend uses for styling
   - `forecast[].best_bite_windows[].reason: string` - Frontend displays as text
   - **NOT documented:** Can be empty array, frontend handles gracefully

8. **Recommended species:**
   - `forecast[].recommended_species: Array` - Frontend conditionally renders: `{day.recommended_species && day.recommended_species.length > 0 && ...}`
   - `forecast[].recommended_species[].id: string` - Frontend uses as React key
   - `forecast[].recommended_species[].name: string` - Frontend displays
   - `forecast[].recommended_species[].confidence: number` - Frontend converts to percentage: `{Math.round(species.confidence * 100)}%`
   - `forecast[].recommended_species[].why: string` - Frontend displays
   - **NOT documented:** Confidence is 0.0-1.0, frontend multiplies by 100

9. **Gear suggestions:**
   - `forecast[].gear_suggestions.bait: string[]` - Frontend checks: `day.gear_suggestions.bait && day.gear_suggestions.bait.length > 0`
   - `forecast[].gear_suggestions.lure: string[]` - Frontend checks length
   - `forecast[].gear_suggestions.line_weight: string` - Frontend displays
   - `forecast[].gear_suggestions.leader: string` - Frontend displays
   - `forecast[].gear_suggestions.rig: string` - Frontend displays
   - **NOT documented:** Arrays can be empty, strings can be empty, frontend handles all cases

10. **Reasons:**
    - `forecast[].reasons: Array` - Frontend maps and displays
    - `forecast[].reasons[].title: string` - Frontend displays with styling
    - `forecast[].reasons[].detail: string` - Frontend displays
    - `forecast[].reasons[].severity: 'positive' | 'negative' | 'neutral'` - Frontend uses for color coding
    - `forecast[].reasons[].category: string` - Frontend doesn't use (but present)
    - **NOT documented:** Array can be empty, frontend handles gracefully

#### `/api/locations.php` Response Structure
**Critical Assumptions:**
1. **Top-level:**
   - `error: boolean` - Frontend checks: `if (response.error) { setError(...) }`
   - `data: object` - Frontend accesses: `response.data.locations`

2. **Locations array:**
   - `data.locations: Array` - Frontend maps: `locations.map((location) => ...)`
   - `data.locations[].id: number` - Frontend uses as React key and for navigation
   - `data.locations[].name: string` - Frontend displays
   - `data.locations[].region: string` - Frontend displays (can be null, handled)
   - `data.locations[].lat: number` - Frontend passes to forecast: `?lat=${location.lat}`
   - `data.locations[].lng: number` - Frontend passes to forecast: `&lng=${location.lng}`
   - **NOT documented:** `region` can be null, frontend conditionally renders

#### Error Response Format
**Critical Assumptions:**
1. **Error structure:**
   - `error: true` - Frontend checks this
   - `message: string` - Frontend may display (not consistently used)
   - `code: string` - Frontend doesn't use (but present)
   - **NOT documented:** HTTP status codes (400, 403, 429, 500, 503) are used but frontend only checks `response.ok`

2. **Success structure:**
   - `error: false` - Frontend assumes this for success
   - **NOT documented:** HTTP 200 is expected, but frontend doesn't verify

### Internal Backend Contracts

#### Cache Key Format
**Implicit Contract:**
- Format: `{lat}:{lng}:{start_date}:{days}`
- **NOT enforced:** No validation, relies on consistent usage
- **Risk:** If format changes, all cache invalidated
- **Used by:** `ForecastData.php`, `weather.php`, `sun.php`, `tides.php`

#### Provider Names
**Implicit Contract:**
- Provider strings: `'weather'`, `'sun'`, `'tides'`
- **NOT enforced:** Hardcoded strings, typos break caching
- **Used by:** `Cache.php`, all data-fetching endpoints

#### Database Schema
**Implicit Contract:**
- Table names: `locations`, `species_rules`, `api_cache`, `rate_limits`
- Column names: Assumed by all queries
- **NOT enforced:** No migrations, schema changes break everything
- **Risk:** Schema changes require coordinated updates

#### Scoring Weights
**Implicit Contract:**
- Weather: 0.35, Tide: 0.30, Dawn/Dusk: 0.20, Seasonality: 0.15
- **NOT documented:** Hardcoded in `Scoring::calculateScore()`
- **Risk:** Weight changes affect all scores, user experience

#### Date/Timezone Handling
**Implicit Contract:**
- Default timezone: `'Australia/Melbourne'` (from `DEFAULT_TIMEZONE`)
- Date format: YYYY-MM-DD for `start` parameter
- ISO 8601 format: `'Y-m-d\TH:i:sP'` for timestamps
- **NOT documented:** Some endpoints use server timezone (see scan report)
- **Risk:** Timezone inconsistencies cause date shifts

#### Rate Limiting
**Implicit Contract:**
- Limits: 60/min, 1000/hour (defaults)
- Window calculation: Uses `date('Y-m-d H:i:00')` and `date('Y-m-d H:00:00')`
- **NOT documented:** Window calculation uses server timezone
- **Risk:** Timezone changes affect rate limit windows

---

## Summary

### Most Risky Files (DO NOT MODIFY)
1. `api/lib/Database.php` - Core infrastructure
2. `api/forecast.php` - Primary endpoint, complex logic
3. `api/lib/utils.php` - Used by all endpoints
4. `api/lib/Scoring.php` - Core business logic
5. `api/lib/Cache.php` - Performance-critical
6. `api/lib/Validator.php` - Security-critical

### Safe-to-Change Files
1. `app/src/pages/Home.tsx` - UI-only
2. `app/src/pages/References.tsx` - UI-only
3. `app/src/components/BottomNav.tsx` - UI-only
4. `app/src/contexts/FavouritesContext.tsx` - Frontend-only
5. `api/debug_db.php` - DEV-only

### Critical Implicit Contracts
1. **Forecast response structure** - 50+ field dependencies
2. **Cache key format** - `{lat}:{lng}:{start}:{days}`
3. **Provider names** - `'weather'`, `'sun'`, `'tides'`
4. **Database schema** - All table/column names
5. **Scoring weights** - Hardcoded in `Scoring.php`
6. **Date formats** - YYYY-MM-DD for dates, ISO 8601 for timestamps
7. **Error response format** - `error: boolean`, `message: string`

### Recommendations
1. **Document all API contracts** in `docs/architecture.md`
2. **Add TypeScript types** for all API responses (partially done)
3. **Add validation** for cache key format
4. **Add constants** for provider names
5. **Add migrations** for database schema changes
6. **Add tests** for response structure contracts
7. **Document scoring weights** in `docs/scoring-model.md`

