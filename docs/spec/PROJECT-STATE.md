# FishingInsights: Project State Snapshot

**Last Updated:** 2025-12-26  
**Purpose:** Canonical snapshot for recovery and continuity  
**Status:** MVP Complete, Production-Ready, Android TWA Ready (HTTPS Pending)

---

## Project Overview

**FishingInsights** is a mobile-first Progressive Web App (PWA) providing fishing forecasts for Victorian anglers. The MVP is production-ready for web deployment and PWA-ready for Android TWA (Trusted Web Activity) after HTTPS configuration.

### Core Features

- **7-day fishing forecasts** with composite scores (0-100)
- **Best bite time windows** (dawn/dusk + tide change windows)
- **Recommended target species** based on season and conditions
- **Gear suggestions** (bait, lure, line, leader, rig)
- **Location-based forecasts** for 24+ Victorian locations
- **Offline support** with cached forecasts and friendly offline UX
- **Favourites** (localStorage-based)

---

## Tech Stack

### Backend
- **Language:** PHP 7.3.33 (strict compatibility, no 7.4+ features)
- **Architecture:** Plain PHP, endpoint-per-file, no frameworks
- **Database:** SQLite via PDO
- **Caching:** SQLite-backed `api_cache` table
- **Rate Limiting:** IP-based, SQLite-backed
- **Hosting:** Shared cPanel PHP 7.3.33 hosting (target)

### Frontend
- **Framework:** React 18.2 + TypeScript
- **Build Tool:** Vite 5.0
- **Styling:** TailwindCSS 3.3
- **PWA:** vite-plugin-pwa 0.17 (Workbox)
- **Routing:** React Router 6.20
- **State:** React Context API + localStorage

### Database
- **Type:** SQLite (PDO)
- **Location:** `data/fishinginsights.db` (relative to repo root)
- **Tables:**
  - `locations` - Curated Victorian fishing locations
  - `species_rules` - Species recommendation rules (season, conditions, gear)
  - `api_cache` - Generic API response cache (provider, cache_key, json, expires_at)
  - `rate_limits` - IP-based rate limiting

### Data Sources
- **Weather:** Open-Meteo (free, no key required)
- **Sunrise/Sunset:** Open-Meteo (free, no key required)
- **Tides:** WorldTides.info (paid, credit-based) OR mock mode (fallback)
- **Locations:** Curated SQLite dataset (24+ Victorian locations)

---

## Current Readiness by Platform

### ✅ Web/PWA: READY
- **Status:** Production-ready
- **PWA Features:**
  - ✅ Manifest with all required fields
  - ✅ Service worker registered and active
  - ✅ App shell precaching (12 entries)
  - ✅ Runtime API caching (NetworkFirst strategy)
  - ✅ Icons (192x192, 512x512, maskable 512x512)
  - ✅ Offline detection and friendly UX
  - ✅ Installable on mobile browsers
- **Deployment:** Ready for shared hosting (cPanel)
- **Blockers:** None

### ⚠️ Android (TWA): PWA-Ready, HTTPS Pending
- **Status:** PWA requirements met, HTTPS required for production
- **PWA Readiness:** ✅ All PWA blockers fixed
  - ✅ Icons created and included
  - ✅ Maskable icon with `purpose: "maskable"`
  - ✅ Offline UX implemented
  - ✅ Service worker active
- **Android TWA Requirements:**
  - ✅ PWA manifest valid
  - ✅ Service worker registered
  - ✅ Icons present (including maskable)
  - ⚠️ HTTPS required (not yet configured)
- **Next Steps:**
  1. Configure HTTPS certificate
  2. Scaffold Android TWA project
  3. Test TWA on device/emulator
  4. Submit to Google Play Console (testing track)

### ❌ iOS: Not Started
- **Status:** Not implemented
- **Requirements:** iOS-specific PWA enhancements (if needed)
- **Priority:** Low (web/PWA works on iOS Safari)

---

## What is COMPLETE

### ✅ API Contract Locked
- **Version:** 1.0
- **Documentation:** `docs/spec/API-CONTRACT.md`
- **Primary Endpoint:** `/api/forecast.php`
- **Contract Verification:** `scripts/verify_contract.php` validates against golden sample
- **Status:** LOCKED - Breaking changes require version bump

### ✅ Forecast Caching
- **Forecast-level cache:** 15-minute TTL (keyed by lat|lng|start|days|timezone|rules_version)
- **Provider caches:**
  - Weather: 1 hour TTL
  - Sun: 7 days TTL
  - Tides: 12 hours TTL
- **Cache cleanup:** Opportunistic cleanup on health.php (always) and forecast.php (probabilistic ~5%)
- **Refresh bypass:** `?refresh=true` parameter

### ✅ Performance Optimizations
- **Backend Performance:**
  - **Tides lookup:** O(n) → O(1) via date-keyed index
  - **N+1 queries eliminated:** Species rules loaded once per request (21 queries → 1 query)
  - **Gear suggestions:** In-memory index for O(1) lookup
  - **Result:** ~95% reduction in database queries per forecast request
- **Frontend Performance:**
  - **Memoized expensive renders:** Forecast day cards use `day.date` as key, location/starred state memoized
  - **Request deduplication:** Prevents duplicate API calls for same location/date combination
  - **Debounced search:** 300ms debounce on Locations page search input
  - **Memoized filtering/sorting:** Locations list filtered/sorted only when dependencies change
  - **Result:** Smoother UI interactions, reduced unnecessary re-renders and API calls

### ✅ PWA Icons + Maskable
- **Icons created:**
  - `app/public/pwa-192x192.png` (192x192)
  - `app/public/pwa-512x512.png` (512x512)
  - `app/public/pwa-512x512-maskable.png` (512x512, maskable)
- **Generation:** `app/scripts/generate-icons.js` (uses sharp library)
- **Manifest:** All icons included with `purpose: "maskable"` for maskable icon
- **Status:** Icons copied to `dist/` on build, included in precache

### ✅ Offline UX
- **Offline detection:** `navigator.onLine` + online/offline event listeners
- **Friendly messages:** "You're offline" banner with clear messaging
- **Cached data display:** "Showing last saved forecast" label
- **Retry functionality:** Retry button when connection restored
- **Network error detection:** Distinguishes network errors from API errors

### ✅ Visual Polish & Design System
- **Consistent iconography:** Reusable icon components (`app/src/components/icons.tsx`)
  - All inline SVGs replaced with consistent icon components
  - Icons: Home, MapPin, Info, Star, ChevronRight, Refresh, Search, X, Offline, AlertCircle, BarChart
  - Supports `className` and `size` props for flexibility
- **Improved spacing & typography:**
  - Enhanced line-heights (1.6 for body, 1.3 for titles)
  - Improved letter-spacing for headings (-0.02em titles, 0.08-0.1em section headings)
  - Consistent spacing scale using CSS variables
- **BottomNav enhancements:**
  - Active state: Scale transform (110%), indicator dot, font weight change
  - Safe area padding: iOS notch/home indicator support via `env(safe-area-inset-bottom)`
  - Smooth transitions and hover states
- **Mobile-first design:** Consistent card styles, badges, banners, buttons across all pages

### ✅ Smoke Tests + Contract Verification
- **Smoke test:** `scripts/smoke_test.php`
  - Tests `/api/health.php` (status, PDO, write permissions)
  - Tests `/api/forecast.php` (response structure, forecast length)
  - Runs contract verification automatically
- **Contract verification:** `scripts/verify_contract.php`
  - Validates against `tests/golden/forecast.sample.json`
  - Checks required keys, types, score range, date correctness
  - Exit code 0 on pass, non-zero on fail
- **Automated reporting:** `scripts/run_checks_and_report.php`
  - Runs all checks and generates `docs/analysis/LAST_RUN_REPORT.md`
  - Captures git info (branch, commit, status, recent commits)
  - Exits non-zero if any check fails

---

## What is BLOCKED

### 🔴 Production Domain + HTTPS Certificate
- **Requirement:** HTTPS mandatory for Android TWA production and PWA installation
- **Current State:** Development uses HTTP (localhost:3000, 127.0.0.1:8001)
- **Options:**
  1. **Temporary HTTPS for testing:** Use ngrok, localtunnel, or similar for TWA testing
  2. **Final domain + SSL certificate:** Let's Encrypt (free) or commercial certificate
- **Impact:** 
  - Blocks Android TWA production deployment
  - Blocks PWA installation on non-localhost
  - Service workers require HTTPS (except localhost)
- **Priority:** **CRITICAL** (required for production deployment)
- **Next Action:** Choose approach (temporary for testing OR final domain setup)

### 🔴 Google Play Console Testing & Submission
- **Requirement:** Android TWA project + APK/AAB upload + Play Console setup
- **Current State:** PWA ready, Android project not scaffolded
- **Dependencies:**
  1. HTTPS certificate (see above) - **BLOCKER**
  2. Android TWA project scaffolding (not started)
  3. TWA asset links verification (Digital Asset Links JSON)
  4. Google Play Console account setup
- **Impact:** Blocks Play Store distribution
- **Priority:** **HIGH** (after HTTPS)
- **Testing Track Options:**
  - Internal testing (up to 100 testers)
  - Closed testing (specific testers)
  - Open testing (public beta)

---

## What is NEXT (Ordered)

### 1. Commit & Push Latest Changes
- **Status:** Performance optimizations and visual polish complete, ready to commit
- **Files Changed:**
  - `app/src/components/icons.tsx` (new - consistent iconography)
  - `app/src/components/BottomNav.tsx` (active state, safe area)
  - `app/src/pages/Forecast.tsx` (performance: memoization, request deduplication)
  - `app/src/pages/Locations.tsx` (performance: debounced search, memoized filtering)
  - `app/src/pages/Home.tsx` (icon consistency)
  - `app/src/index.css` (spacing, typography improvements)
  - `app/src/App.tsx` (safe area padding)
  - `docs/spec/PROJECT-STATE.md` (this file - updated)
- **Action:** Commit with message "Performance: memoization, debouncing, visual polish"

### 2. Production Domain + HTTPS Certificate (CRITICAL BLOCKER)
- **Status:** Not started - **BLOCKS ALL PRODUCTION DEPLOYMENT**
- **Options:**
  - **Option A (Quick Testing):** Use ngrok/localtunnel for temporary HTTPS
    - Pros: Fast setup, good for TWA testing
    - Cons: Temporary URL, not suitable for production
  - **Option B (Production):** Configure final domain with SSL certificate
    - Pros: Permanent solution, production-ready
    - Cons: Requires domain purchase/configuration
- **Recommendation:** 
  - Start with Option A for Android TWA testing
  - Move to Option B for production deployment
- **Action:** Choose approach and configure HTTPS
- **Dependencies:** None (can proceed immediately)

### 3. Android TWA Project Scaffolding
- **Status:** Not started
- **Tasks:**
  - Create Android Studio project
  - Configure TWA (Trusted Web Activity) using `android-browser-helper`
  - Set up asset links (Digital Asset Links JSON at `/.well-known/assetlinks.json`)
  - Test TWA on device/emulator
  - Verify PWA loads correctly in TWA
- **Documentation:** Follow [Android TWA setup guide](https://developer.chrome.com/docs/android/trusted-web-activity/)
- **Dependencies:** HTTPS certificate (can use temporary for testing)
- **Priority:** High (after HTTPS setup)

### 4. Play Console Setup & Testing Track Upload
- **Status:** Not started
- **Tasks:**
  - Create Google Play Console account (if needed)
  - Create app listing (draft)
  - Upload TWA APK/AAB to internal testing track
  - Test with internal testers (up to 100)
  - Verify TWA behavior on real devices
  - Promote to closed/open testing (if desired)
- **Dependencies:** 
  - Android TWA project (step 3)
  - HTTPS certificate (step 2)
- **Priority:** Medium (after TWA project scaffolding)

---

## Non-Negotiable Invariants

### Australia/Melbourne Timezone
- **Requirement:** All date/time calculations use `Australia/Melbourne` timezone
- **Implementation:**
  - Backend: `DEFAULT_TIMEZONE = 'Australia/Melbourne'` in config
  - Frontend: `Intl.DateTimeFormat` with `timeZone: 'Australia/Melbourne'`
  - API Responses: ISO 8601 timestamps with timezone offset
- **Invariant:** `forecast[0].date` must equal Melbourne "today" when `start` is omitted or set to today
- **Rationale:** Victoria-first MVP scope

### PHP 7.3 Compatibility
- **Requirement:** Backend MUST be compatible with PHP 7.3.33 ONLY
- **Prohibited Features:**
  - Arrow functions `fn()`
  - Typed properties
  - Null coalescing assignment `??=`
  - Union types
  - Match expressions
  - Any PHP 7.4+ syntax/features
- **Rationale:** Shared hosting locked to PHP 7.3.33

### /api/forecast.php Contract v1.0
- **Requirement:** API contract is LOCKED (version 1.0)
- **Breaking Changes:** Require version bump (e.g., v1.1, v2.0)
- **Verification:** `scripts/verify_contract.php` validates against golden sample
- **Documentation:** `docs/spec/API-CONTRACT.md`
- **Rationale:** Frontend depends on stable contract

---

## NEW CHAT HANDOFF

### Quick Start
1. **Read this file** (`docs/spec/PROJECT-STATE.md`) for current state
2. **Run checks:** `php scripts/run_checks_and_report.php`
3. **Start local dev:** See "Local Development Setup" below

### Repository Information
- **Repo URL:** `C:\Users\Cini9\Desktop\Portfolio-2026\FishingInsights`
- **Current Branch:** `main`
- **Latest Commit:** `a6caac5` - "UI baseline: mobile-first layout, cards, banners, nav polish"
- **Status:** Uncommitted changes present (performance + visual polish work)

### Local Development Commands
- **Backend:** `php -S 127.0.0.1:8001 -t .` (from repo root)
- **Frontend:** `cd app && npm run dev` (Vite on port 3000)
- **Test URLs:**
  - Health: http://127.0.0.1:8001/api/health.php
  - Forecast: http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7
  - Frontend: http://localhost:3000

### Check Command
```bash
php scripts/run_checks_and_report.php
```
- Runs contract verification + smoke tests
- Generates `docs/analysis/LAST_RUN_REPORT.md`
- Exit code 0 = all pass, non-zero = failures

### Non-Negotiables
1. **PHP 7.3.33 compatibility** - No 7.4+ features
2. **Australia/Melbourne timezone** - All dates/times use this
3. **API Contract v1.0** - LOCKED, breaking changes require version bump
4. **Plain PHP backend** - No frameworks, endpoint-per-file

### Files to Upload to New Chat (if needed)
- `docs/spec/PROJECT-STATE.md` (this file)
- `docs/analysis/LAST_RUN_REPORT.md` (latest test results)
- `docs/spec/API-CONTRACT.md` (API specification)
- `docs/spec/PWA-REQUIREMENTS.md` (PWA status)

### Next Task
**CRITICAL:** Configure HTTPS certificate (domain/SSL) - **BLOCKS ALL PRODUCTION DEPLOYMENT**
- Options: Temporary (ngrok/localtunnel) for testing OR final domain + SSL
- Required for: Android TWA, PWA installation, service workers

---

## How to Resume if Chat Resets

### Step 1: Read PROJECT-STATE.md
- **Purpose:** Understand current project state, what's complete, what's blocked, what's next
- **Location:** `docs/spec/PROJECT-STATE.md` (this file)
- **Key Sections:**
  - Current Readiness by Platform
  - What is COMPLETE
  - What is BLOCKED
  - What is NEXT
  - NEW CHAT HANDOFF (above)

### Step 2: Read LAST_RUN_REPORT.md
- **Purpose:** Understand recent changes, files modified, test results
- **Location:** `docs/analysis/LAST_RUN_REPORT.md`
- **Key Sections:**
  - Files Changed
  - Commands Run
  - Test Results
  - Build Output

### Step 3: Run Automated Checks and Report
- **Purpose:** Run all checks and generate LAST_RUN_REPORT.md with git info and test results
- **Command:** `php scripts/run_checks_and_report.php`
- **What It Does:**
  - Runs contract verification (`scripts/verify_contract.php`)
  - Runs smoke tests (`scripts/smoke_test.php`)
  - Captures git information (branch, commit hash, status, last 5 commits)
  - Generates `docs/analysis/LAST_RUN_REPORT.md` with all results
  - Exits with code 0 if all checks pass, non-zero if any fail
- **Expected Output:**
  - Contract verification: PASS
  - Smoke tests: PASS
  - Report generated successfully
  - Exit code: 0
- **If Tests Fail:**
  - Check PHP version: `php -v` (should be 7.3.x)
  - Check API server: Ensure `php -S 127.0.0.1:8001 -t .` is running
  - Review generated report for specific error details
- **Alternative:** Run individual tests:
  - `php scripts/smoke_test.php` (includes contract verification)
  - `php scripts/verify_contract.php` (contract only)

### Step 4: Verify Local Development Setup
- **Backend:** 
  - Option A (from repo root): `php -S 127.0.0.1:8001 -t .` → endpoints at `/api/health.php`
  - Option B (from api dir): `php -S 127.0.0.1:8001 -t api` → endpoints at `/health.php`
- **Frontend:** `cd app && npm run dev` (Vite dev server on port 3000)
- **Test URLs (Option A - repo root):**
  - Health: http://127.0.0.1:8001/api/health.php
  - Forecast: http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7
- **Test URLs (Option B - api dir):**
  - Health: http://127.0.0.1:8001/health.php
  - Forecast: http://127.0.0.1:8001/forecast.php?lat=-37.8&lng=144.9&days=7
- **Frontend:** http://localhost:3000
- **Scripts Auto-Detection:** Test scripts automatically detect the correct base path (`""` or `"/api"`) by probing `/health.php`
- **Environment Variables (optional):**
  - `FISHINGINSIGHTS_BASE_URL` - Override base URL (default: `http://127.0.0.1:8001`)
  - `FISHINGINSIGHTS_BASE_PATH` - Override base path (default: auto-detected, or `""` or `"/api"`)

### Step 5: Check Documentation Index
- **Location:** `docs/README.md`
- **Purpose:** Find authoritative spec documents vs analysis documents
- **Key Distinction:**
  - `/docs/spec/` = Authoritative (must follow)
  - `/docs/analysis/` = Analysis-only (insights, not requirements)

---

## Project Structure

```
FishingInsights/
├── app/                    # React frontend
│   ├── public/            # Static assets (icons, etc.)
│   ├── src/               # React source code
│   │   ├── pages/        # Page components
│   │   ├── components/   # Reusable components
│   │   ├── contexts/     # React Context providers
│   │   └── services/     # API client
│   ├── scripts/          # Build scripts (generate-icons.js)
│   └── dist/             # Build output (generated)
├── api/                   # PHP backend
│   ├── lib/              # Shared PHP libraries
│   ├── *.php             # Endpoint files
│   └── config.example.php # Config template
├── data/                  # Database and seed data
│   ├── fishinginsights.db # SQLite database (generated)
│   └── seed.sql          # Seed data SQL
├── docs/                  # Documentation
│   ├── spec/             # Authoritative specifications
│   └── analysis/         # Analysis reports
├── scripts/              # Utility scripts
│   ├── smoke_test.php    # Backend smoke test
│   └── verify_contract.php # API contract verification
└── tests/                # Test files
    └── golden/           # Golden samples (forecast.sample.json)
```

---

## Key Files Reference

### Configuration
- **Backend Config:** `api/config.example.php` (template), `api/config.local.php` (local, not committed)
- **Frontend Config:** `app/vite.config.ts` (Vite + PWA config)
- **Database Path:** `data/fishinginsights.db` (relative to repo root)

### Documentation
- **Project State:** `docs/spec/PROJECT-STATE.md` (this file)
- **API Contract:** `docs/spec/API-CONTRACT.md`
- **PWA Requirements:** `docs/spec/PWA-REQUIREMENTS.md`
- **Last Run Report:** `docs/analysis/LAST_RUN_REPORT.md`
- **Documentation Index:** `docs/README.md`

### Testing
- **Smoke Test:** `scripts/smoke_test.php`
- **Contract Verification:** `scripts/verify_contract.php`
- **Golden Sample:** `tests/golden/forecast.sample.json`

### Build Output
- **Frontend Build:** `app/dist/` (generated on `npm run build`)
- **PWA Files:** `app/dist/manifest.webmanifest`, `app/dist/sw.js`

---

## Version Information

- **Project Version:** MVP 1.0
- **API Contract Version:** 1.0 (LOCKED)
- **PHP Version:** 7.3.33 (strict compatibility)
- **Node Version:** 22.16.0 (development)
- **React Version:** 18.2.0
- **Vite Version:** 5.0.0

---

## Notes

- **Development Mode:** Auto-detected when `php_sapi_name() === 'cli-server'` (PHP built-in server)
- **Database Seeding:** Available via `/api/seed.php` (DEV_MODE only)
- **Cache Strategy:** Multi-level (forecast-level + provider-level) with opportunistic cleanup
- **Error Handling:** Graceful degradation (mock tides if API fails, offline UX if network fails)
- **Performance:** Optimized for low-latency (O(1) lookups, single-query species rules, forecast caching)

---

**Last Updated:** 2025-12-26  
**Next Review:** After HTTPS configuration or Android TWA scaffolding

