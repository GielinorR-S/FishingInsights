# Decisions Locked & Implementation Milestones

## Decisions Locked

This document consolidates all architectural and product decisions made during documentation phase. These decisions are **LOCKED** and should not be changed without team approval.

### Technology Stack

1. **PHP Version**: 7.3.33 ONLY
   - No PHP 7.4+ syntax (no arrow functions, typed properties, null coalescing assignment, union types, match expressions)
   - Plain PHP, no Composer dependencies

2. **Database**: SQLite via PDO
   - Single file database (`fishinginsights.db`)
   - Preferred location: Outside web root
   - Fallback: Inside web root with `.htaccess` protection

3. **Frontend**: React + TypeScript + Vite + TailwindCSS
   - React Router for routing
   - Context API for state (no Redux)
   - PWA with service worker

4. **API Architecture**: Endpoint-per-file (no router framework)
   - Each endpoint is standalone PHP file
   - Shared lib files for common functionality

### API Endpoints

5. **Primary Endpoint**: `/api/forecast.php`
   - Frontend calls this endpoint exclusively for forecast data
   - Backend aggregates weather/sun/tides internally
   - Reduces frontend complexity and API round trips

6. **Supporting Endpoints**: `/api/weather.php`, `/api/sun.php`, `/api/tides.php`
   - Available for debugging/testing
   - Not used by frontend in normal operation

### Data Sources

7. **Weather**: Open-Meteo (free, no key)
   - Cache TTL: 1 hour (3600 seconds)

8. **Sunrise/Sunset**: Open-Meteo (same provider as weather)
   - Cache TTL: 7 days (604800 seconds)

9. **Tides**: WorldTides.info (paid, credit-based)
   - Cache TTL: 12 hours (43200 seconds)
   - Mock mode if API key missing or credits exhausted
   - Budget target: <$5 USD/month

### Timestamp Policy

10. **Timezone**: All times in `Australia/Melbourne` (IANA timezone)
    - ISO 8601 format with timezone offset: `YYYY-MM-DDTHH:mm:ss+TZ:TZ`
    - Example: `2024-01-15T06:15:00+11:00`
    - Include `timezone` field in all API responses

11. **Date Validation**: Allow `start=today`, disallow past dates (unless `DEV_MODE=true`)

### Caching Strategy

12. **Cache TTLs (LOCKED)**:
    - Weather: 1 hour
    - Sun: 7 days
    - Tides: 12 hours

13. **Cache Storage**: SQLite `api_cache` table
    - Generic cache table (provider, cache_key, json_data, expires_at)
    - Indexed for fast lookups

### Scoring Model

14. **Score Range**: 0-100
    - Composite of weather (35%), tides (30%), dawn/dusk (20%), seasonality (15%)
    - Weights sum to 1.0 (100%)

15. **Best Bite Windows**: Overlap of dawn/dusk with tide change windows
    - Dawn: 30 min before sunrise to 2 hours after
    - Dusk: 2 hours before sunset to 30 min after
    - Tide change: 1 hour before/after each high/low

### User Data

16. **Favourites Storage**: localStorage only (MVP)
    - No database storage for user preferences
    - Multi-device sync can be added later

### Core Features

17. **NO External Links**: All core features (weather, sun, tides) must run inside app
    - No "View on [provider]" links in forecast screens
    - References page may include external links for regulations/attribution only

### Security

18. **Secrets Management**: `config.local.php` (not committed)
    - API keys stored in `config.local.php`
    - Never exposed to frontend

19. **Rate Limiting**: IP-based, SQLite-backed
    - 60 requests/minute per IP
    - 1000 requests/hour per IP

20. **CORS**: Same-origin by default
    - No wildcard CORS
    - Specific origin allowed if subdomain needed

### Deployment

21. **Database Location**: Outside web root preferred
    - Fallback: Inside web root with `.htaccess` protection

22. **SPA Routing**: `.htaccess` rewrite rules
    - Fallback: Hash routing if mod_rewrite unavailable

### MVP Scope

23. **Locations**: Victoria-first (20-40 locations)
    - Expandable to Australia-wide without refactor

24. **Species**: 10-15 common Victorian species
    - Snapper, Bream, Flathead, Whiting, Salmon, Trevally, etc.

25. **Species Screen**: Optional enhancement
    - Accessible from forecast but not required for core flow

26. **Historical Data**: No (MVP focuses on forward-looking forecasts only)

## Day 1-7 Milestone Checklist

### Day 1: Foundation & Setup
- [ ] Initialize React app (Vite + TypeScript + TailwindCSS)
- [ ] Set up project structure (`/app`, `/api`, `/docs`, `/data`)
- [ ] Create PHP backend skeleton (`/api` directory structure)
- [ ] Set up SQLite database schema (create tables)
- [ ] Implement health check endpoint (`/api/health.php`)
- [ ] Test health check locally (if PHP 7.3 available)
- [ ] Create `.gitignore` (exclude `config.local.php`, `node_modules`, `dist`, `.db` files)

**Deliverable**: Project structure, health check working, database schema created

---

### Day 2: Backend Core
- [ ] Implement `lib/Database.php` (PDO SQLite wrapper)
- [ ] Implement `lib/Cache.php` (SQLite cache wrapper)
- [ ] Implement `lib/Validator.php` (input validation)
- [ ] Implement `lib/RateLimiter.php` (IP-based rate limiting)
- [ ] Create `config.example.php` with placeholders
- [ ] Implement `/api/weather.php` (Open-Meteo integration)
- [ ] Test weather endpoint with caching

**Deliverable**: Core backend libraries, weather endpoint working with cache

---

### Day 3: Backend APIs
- [ ] Implement `/api/sun.php` (Open-Meteo sunrise/sunset)
- [ ] Implement `/api/tides.php` (WorldTides integration + mock mode)
- [ ] Implement `/api/forecast.php` (aggregated endpoint)
- [ ] Test all endpoints with proper timezone handling
- [ ] Verify ISO 8601 timestamp format with timezone offset
- [ ] Test mock tides mode (when API key missing)

**Deliverable**: All API endpoints working, proper timestamp formatting

---

### Day 4: Scoring & Data
- [ ] Implement scoring model (weather, tides, dawn/dusk, seasonality)
- [ ] Implement best bite windows calculation
- [ ] Implement species recommendation logic
- [ ] Implement gear suggestions
- [ ] Seed database with 20-40 Victorian locations
- [ ] Seed database with 10-15 species rules
- [ ] Test forecast endpoint returns complete forecast with scores

**Deliverable**: Scoring model working, database seeded, forecast endpoint complete

---

### Day 5: Frontend Core
- [ ] Set up React Router (routes: Home, Locations, Forecast, References)
- [ ] Create base layout components (Header, Footer, Navigation)
- [ ] Implement Home screen (Today's Best, Favourites)
- [ ] Implement Location Picker screen (search, list, geolocation)
- [ ] Create API client service (fetch wrapper)
- [ ] Implement FavouritesContext (localStorage persistence)

**Deliverable**: Core screens implemented, routing working, favourites persist

---

### Day 6: Frontend Forecast
- [ ] Implement Forecast screen (7-day cards)
- [ ] Implement Day Detail view (expandable or modal)
- [ ] Display score, best bite windows, species, gear
- [ ] Implement "Why this score" expandable section
- [ ] Add loading states and error handling
- [ ] Add cached data indicators
- [ ] Test offline mode (service worker)

**Deliverable**: Forecast screen complete, offline mode working

---

### Day 7: Polish & Deploy
- [ ] Implement References screen (attribution, regulations, disclaimer)
- [ ] Implement Species screen (optional enhancement)
- [ ] PWA configuration (manifest.json, service worker)
- [ ] Accessibility improvements (ARIA labels, keyboard nav, contrast)
- [ ] Build React app (`npm run build`)
- [ ] Deploy to cPanel hosting
- [ ] Configure `.htaccess` (SPA routing, security)
- [ ] Set up `config.local.php` with API keys
- [ ] Run health check on production
- [ ] Test all features on production
- [ ] Verify PWA installable

**Deliverable**: Production deployment, all features working, PWA installable

---

## Immediate Next Actions (Once Docs Approved)

1. **Initialize Project**
   - Create monorepo structure
   - Initialize React app with Vite
   - Create `/api` directory structure
   - Create `/docs` directory (this documentation)

2. **Set Up Development Environment**
   - Install Node.js dependencies (`npm install` in `/app`)
   - Set up PHP 7.3.33 locally (if possible) or use Docker
   - Create SQLite database file
   - Set up `.gitignore`

3. **Start Day 1 Tasks**
   - Begin with health check endpoint
   - Create database schema
   - Test locally before proceeding

4. **Obtain API Keys**
   - Sign up for WorldTides account (if not done)
   - Generate API key
   - Store in `config.local.php` (not committed)

5. **Prepare Seed Data**
   - Research 20-40 Victorian fishing locations
   - Research 10-15 common species and their rules
   - Prepare SQL seed file or migration script

## Quality Gates

Before moving to next day:
- [ ] All Day X tasks completed
- [ ] Code tested locally (if possible)
- [ ] No PHP 7.4+ syntax used
- [ ] Health check passes
- [ ] API endpoints return proper JSON with timezone
- [ ] Frontend displays data correctly
- [ ] No critical errors in browser console

## Open Questions (Should be Zero)

All questions resolved during documentation phase. If new questions arise during implementation:
1. Document the question
2. Propose a decision
3. Update relevant documentation
4. Proceed with implementation

---

**Documentation Complete**: All ambiguity removed. Ready for implementation.

## Doc Consistency Checklist

This section summarizes canonical rules to ensure consistency across all documentation:

### Timestamps
- **Format**: ISO 8601 with timezone offset: `YYYY-MM-DDTHH:mm:ss+TZ:TZ` (e.g., `2024-01-15T06:15:00+11:00`)
- **Timezone**: All times in `Australia/Melbourne` (IANA timezone) for Victoria MVP
- **Response Field**: All API responses must include `"timezone": "Australia/Melbourne"`
- **No Invalid Times**: Never use invalid time formats like `"20:60:00"` - always use full ISO 8601 timestamps

### Tide Change Windows
- **Canonical Rule**: Compute a window +/- 1 hour around EACH high/low tide event
- For each tide event:
  - Window start: 1 hour before event time
  - Window end: 1 hour after event time
  - Type: "rising" if event is low tide, "falling" if event is high tide
- **Do NOT** describe multi-hour spans (low->high or high->low) as a single "window"
- Each high and each low gets its own 2-hour window (1 hour before + 1 hour after)

### Cache Keys (api_cache table)
- **Provider Column**: Stores `"weather"`, `"sun"`, or `"tides"` as separate column
- **Cache Key Format**: `{lat}:{lng}:{start_date}:{days}` (e.g., `-37.8:144.9:2024-01-15:7`)
- **No Provider Prefix**: Cache key does NOT include provider prefix (provider is in separate column)
- **Lookup**: Uses both `provider` and `cache_key` columns together

### Cache TTLs (LOCKED)
- **Weather**: 1 hour (3600 seconds)
- **Sun**: 7 days (604800 seconds) - consistent everywhere
- **Tides**: 12 hours (43200 seconds)

### SQLite Permissions
- **Database File**: Must be writable by PHP execution user
  - Suggested: `664` (read-write for owner/group) or `660` (read-write for owner/group only)
- **Directory**: Must be writable by PHP execution user
  - Suggested: `775` (read-write-execute for owner/group) or `770` (read-write-execute for owner/group only)
- **Verification**: Rely on `/api/health.php` to confirm write capability - this is authoritative
- **No Contradictory Claims**: Remove any claims like "644 but writable" - permissions must allow writes

### Database Path
- **When api/ is beside data/**: `__DIR__ . '/../data/fishinginsights.db'`
- **When outside web root**: `/home/username/data/fishinginsights.db`
- **Config**: Set in `api/config.local.php` as `DB_PATH` constant

### Primary Endpoint
- **Frontend uses**: `/api/forecast.php` exclusively for forecast data
- **Supporting endpoints**: `/api/weather.php`, `/api/sun.php`, `/api/tides.php` available for debugging/testing only

