# FishingInsights - Complete State Report

**Generated:** 2025-01-XX  
**Purpose:** Single source of truth for server setup, URLs, and routing

---

## 1. Repo Structure Overview

```
FishingInsights/
├── app/                    # React + TypeScript + Vite frontend
│   ├── src/
│   │   ├── services/api.ts # API client (uses /api base URL)
│   │   ├── pages/          # React pages (Locations, Forecast, Home, References)
│   │   └── components/      # React components
│   ├── package.json        # Frontend dependencies & scripts
│   └── vite.config.ts     # Vite config with proxy to :8001
│
├── api/                    # PHP 7.3.33 backend
│   ├── *.php              # Endpoint files (see section 4)
│   ├── lib/               # Shared PHP libraries
│   ├── config.example.php # Default config (committed)
│   └── config.local.php   # Local overrides (NOT committed)
│
├── data/                   # SQLite database & seed data
│   ├── fishinginsights.db # SQLite database file
│   └── seed.sql          # Seed data SQL
│
├── docs/                   # Documentation
└── scripts/                # Utility scripts
    └── smoke_test.php     # Backend smoke test
```

**Key Points:**
- Frontend lives in `/app` directory
- Backend PHP endpoints live in `/api` directory
- Database lives in `/data` directory
- **NO PHP files at repo root** - all endpoints are under `/api/`

---

## 2. Dev Commands & Scripts

### Frontend (Vite Dev Server)

**Location:** `app/package.json`

**Scripts:**
```json
{
  "dev": "vite",           # Starts Vite dev server on port 3000
  "build": "tsc && vite build",
  "preview": "vite preview",
  "lint": "eslint . --ext ts,tsx --report-unused-disable-directives --max-warnings 0"
}
```

**Command to run:**
```bash
cd app
npm install  # First time only
npm run dev
```

**Result:** Frontend runs on `http://localhost:3000`

### Backend (PHP Built-in Server)

**Location:** `README.md` (lines 57-60)

**Command to run (from repo root):**
```bash
php -S 127.0.0.1:8001 -t .
```

**Result:** PHP server runs on `http://127.0.0.1:8001`, serving from repo root

**Why `-t .` (repo root)?**
- PHP endpoints are at `/api/*.php`
- When serving from root, `/api/health.php` resolves to `api/health.php`
- If serving from `-t api`, you'd need `/health.php` (no `/api/` prefix)

### Smoke Test

**Location:** `scripts/smoke_test.php`

**Command to run:**
```bash
php scripts/smoke_test.php
```

**Tests:**
- Health endpoint at `http://127.0.0.1:8001/api/health.php`
- Forecast endpoint at `http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7`

---

## 3. API Base URL (Frontend)

### Source: `app/src/services/api.ts`

```typescript
const API_BASE = '/api'
```

**All API calls use relative paths:**
- `fetch(`${API_BASE}/forecast.php?${params}`)` → `/api/forecast.php`
- `fetch(`${API_BASE}/health.php`)` → `/api/health.php`
- `fetch(`${API_BASE}/locations.php?...`)` → `/api/locations.php`

### Vite Proxy Configuration

**Source:** `app/vite.config.ts` (lines 40-47)

```typescript
server: {
  port: 3000,
  proxy: {
    "/api": {
      target: "http://127.0.0.1:8001",
      changeOrigin: true,
    },
  },
},
```

**How it works:**
1. Frontend runs on `http://localhost:3000`
2. Frontend makes request to `/api/forecast.php`
3. Vite proxy intercepts `/api/*` requests
4. Proxy forwards to `http://127.0.0.1:8001/api/forecast.php`
5. PHP server responds
6. Vite proxy returns response to frontend

**Result:** Frontend never needs to know about `:8001` - it just uses `/api/*` paths.

---

## 4. PHP Endpoints (All Under `/api/`)

### Public Endpoints (Production)

| Endpoint | File | Purpose | Status |
|----------|------|---------|--------|
| `/api/health.php` | `api/health.php` | Health check, DB connectivity | ✅ Active |
| `/api/locations.php` | `api/locations.php` | Get list of fishing locations | ✅ Active |
| `/api/forecast.php` | `api/forecast.php` | **PRIMARY** - 7-day forecast | ✅ Active |

### Debug/Internal Endpoints (Used by forecast.php internally)

| Endpoint | File | Purpose | Status |
|----------|------|---------|--------|
| `/api/weather.php` | `api/weather.php` | Weather data (Open-Meteo) | ✅ Active |
| `/api/sun.php` | `api/sun.php` | Sunrise/sunset data | ✅ Active |
| `/api/tides.php` | `api/tides.php` | Tide data (WorldTides or mock) | ✅ Active |

### Development-Only Endpoints (DEV_MODE required)

| Endpoint | File | Purpose | Status |
|----------|------|---------|--------|
| `/api/seed.php` | `api/seed.php` | Seed database with locations/species | 🔒 DEV_MODE |
| `/api/debug_db.php` | `api/debug_db.php` | Debug DB contents, counts, samples | 🔒 DEV_MODE |

**Total PHP Endpoints:** 8 files in `/api/` directory

---

## 5. Endpoint Testing Results

**Note:** These tests assume PHP server is running on `:8001`. If server is not running, all will fail.

### Expected Results (if server is running):

#### Test 1: `/api/health.php`
```bash
curl http://127.0.0.1:8001/api/health.php
```

**Expected Response (200 OK):**
```json
{
  "status": "ok",
  "php_version": "7.3.33",
  "has_pdo": true,
  "has_pdo_sqlite": true,
  "sqlite_db_path": "[redacted]",
  "can_write_db": true,
  "can_write_cache": true,
  "timestamp": "2025-01-XX...",
  "timezone": "Australia/Melbourne"
}
```

#### Test 2: `/api/locations.php`
```bash
curl http://127.0.0.1:8001/api/locations.php
```

**Expected Response (200 OK):**
```json
{
  "error": false,
  "data": {
    "timezone": "Australia/Melbourne",
    "locations": [
      {"id": 1, "name": "Port Phillip Bay", "region": "Melbourne", ...},
      ...
    ]
  }
}
```

#### Test 3: `/api/seed.php` (DEV_MODE only)
```bash
curl http://127.0.0.1:8001/api/seed.php
```

**Expected Response (200 OK if DEV_MODE=true, 403 if false):**
```json
{
  "ok": true,
  "seeded": true,
  "locations_count": 24,
  "species_rules_count": 6,
  "db_path": "[redacted]",
  "timestamp": "...",
  "timezone": "Australia/Melbourne"
}
```

#### Test 4: `/api/forecast.php` (Primary endpoint)
```bash
curl "http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7"
```

**Expected Response (200 OK):**
```json
{
  "error": false,
  "data": {
    "location": {
      "lat": -37.8,
      "lng": 144.9,
      "name": "Port Phillip Bay",
      "region": "Melbourne"
    },
    "timezone": "Australia/Melbourne",
    "forecast": [
      {
        "date": "2025-01-XX",
        "score": 75,
        "weather": {...},
        "sun": {...},
        "tides": {...},
        "best_bite_windows": [...],
        "recommended_species": [...],
        "gear_suggestions": {...},
        "reasons": [...]
      },
      ... (7 days)
    ]
  }
}
```

**Actual Test Status:** ⚠️ **Cannot verify** - Server may not be running. Use `php scripts/smoke_test.php` to test.

---

## 6. Config Files & DEV_MODE

### Config File Hierarchy

1. **`api/config.example.php`** (committed)
   - Default values
   - Auto-detects local dev: `DEV_MODE = (php_sapi_name() === 'cli-server')`
   - If running PHP built-in server → `DEV_MODE = true`
   - Otherwise → `DEV_MODE = false`

2. **`api/config.local.php`** (NOT committed, in `.gitignore`)
   - Local overrides
   - Currently sets: `DEV_MODE = true` (line 55)
   - Sets: `DB_PATH = __DIR__ . '/../data/fishinginsights.db'`
   - Sets: `WORLDTIDES_API_KEY = ''` (empty = mock mode)

### Config Loading Pattern

**Every PHP endpoint loads config like this:**
```php
require_once __DIR__ . '/config.example.php';
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}
```

**Result:**
- `config.local.php` overrides `config.example.php` if it exists
- If `config.local.php` doesn't exist, defaults from `config.example.php` are used
- Auto-detection in `config.example.php` means `DEV_MODE` works even without `config.local.php` when using PHP built-in server

### DEV_MODE Detection Logic

**Source:** `api/config.example.php` (lines 52-57)

```php
if (!defined('DEV_MODE')) {
    $isLocalDev = (php_sapi_name() === 'cli-server');
    define('DEV_MODE', $isLocalDev);
}
```

**Behavior:**
- `php_sapi_name() === 'cli-server'` → PHP built-in server → `DEV_MODE = true`
- Apache/Nginx → `DEV_MODE = false` (production)
- `config.local.php` can override this if needed

### Environment Variables

**No `.env` files found.** All config is in PHP files.

---

## 7. Why `/health.php` and `/locations.php` Return 404 at Root

### The Problem

If you try to access:
- `http://127.0.0.1:8001/health.php` → **404 Not Found**
- `http://127.0.0.1:8001/locations.php` → **404 Not Found**

### The Explanation

**All PHP endpoints are under `/api/` directory:**

```
FishingInsights/
├── api/
│   ├── health.php      ← Endpoint is HERE
│   ├── locations.php   ← Endpoint is HERE
│   └── ...
└── (no PHP files at root)
```

**Correct URLs:**
- ✅ `http://127.0.0.1:8001/api/health.php`
- ✅ `http://127.0.0.1:8001/api/locations.php`

**Why this structure?**
- Clean separation: frontend (`/app`), backend (`/api`), data (`/data`)
- Matches production hosting where `/api` is a subdirectory
- Frontend uses `/api/*` paths, so backend must match

### No Router/Proxy at Root

**There is NO router or proxy at the repo root.** The PHP built-in server serves files directly:
- Request to `/api/health.php` → looks for `api/health.php` file → ✅ Found
- Request to `/health.php` → looks for `health.php` at root → ❌ Not found

**Solution:** Always use `/api/` prefix for backend endpoints.

---

## 8. Single Source of Truth

### FRONTEND_URL
```
http://localhost:3000
```
- Vite dev server
- Started with: `cd app && npm run dev`
- Serves React app
- Proxies `/api/*` to backend

### API_BASE_URL (Frontend Perspective)
```
/api
```
- Relative path used in frontend code
- Vite proxy forwards to `http://127.0.0.1:8001/api`
- **Frontend never hardcodes `:8001`**

### BACKEND_URL (Direct Access)
```
http://127.0.0.1:8001
```
- PHP built-in server
- Started with: `php -S 127.0.0.1:8001 -t .` (from repo root)
- Serves files from repo root
- Endpoints accessible at `/api/*.php`

### Working Endpoints

**Public Endpoints:**
- `GET /api/health.php` - Health check
- `GET /api/locations.php?search=&region=` - List locations
- `GET /api/forecast.php?lat=&lng=&days=7&start=` - **PRIMARY** forecast endpoint

**Debug Endpoints (internal use):**
- `GET /api/weather.php?lat=&lng=&start=&days=7`
- `GET /api/sun.php?lat=&lng=&start=&days=7`
- `GET /api/tides.php?lat=&lng=&start=&days=7`

**DEV_MODE Only:**
- `GET /api/seed.php` - Seed database
- `GET /api/debug_db.php` - Debug database

### How to Run Everything

**Terminal 1 - Backend:**
```bash
cd C:\Users\Cini9\Desktop\Portfolio-2026\FishingInsights
php -S 127.0.0.1:8001 -t .
```

**Terminal 2 - Frontend:**
```bash
cd C:\Users\Cini9\Desktop\Portfolio-2026\FishingInsights\app
npm run dev
```

**Verify Backend:**
```bash
# Option 1: Use smoke test
php scripts/smoke_test.php

# Option 2: Manual curl
curl http://127.0.0.1:8001/api/health.php
```

**Access Frontend:**
- Open browser: `http://localhost:3000`
- Frontend will proxy API calls to `:8001` automatically

### Quick Reference

| What | URL | Notes |
|------|-----|-------|
| Frontend | `http://localhost:3000` | Vite dev server |
| Backend (direct) | `http://127.0.0.1:8001` | PHP built-in server |
| Health Check | `http://127.0.0.1:8001/api/health.php` | Or via proxy: `http://localhost:3000/api/health.php` |
| Locations | `http://127.0.0.1:8001/api/locations.php` | Or via proxy: `http://localhost:3000/api/locations.php` |
| Forecast | `http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7` | Primary endpoint |
| Seed (DEV) | `http://127.0.0.1:8001/api/seed.php` | Requires DEV_MODE=true |

---

## Summary

✅ **Frontend:** React app on `:3000`, uses `/api` relative paths  
✅ **Backend:** PHP server on `:8001`, endpoints under `/api/` directory  
✅ **Proxy:** Vite forwards `/api/*` from `:3000` to `:8001`  
✅ **Config:** Auto-detects local dev via `php_sapi_name() === 'cli-server'`  
✅ **Structure:** Clean separation - no PHP files at root, all under `/api/`  

**Key Insight:** The `/api/` prefix is **required** because all PHP endpoints live in the `api/` directory. There are no endpoints at the repo root.

