# Deployment Guide

## Overview

This guide covers step-by-step deployment to shared cPanel PHP hosting (PHP 7.3.33). Assumes access to cPanel File Manager and ability to upload files.

## Prerequisites

- cPanel hosting account with PHP 7.3.33
- FTP/SFTP access or cPanel File Manager
- SQLite extension enabled (verify via `phpinfo()` or health check)
- Write permissions for database directory
- WorldTides API key (optional, for tides; app works with mock mode if missing)

## Pre-Deployment Checklist

- [ ] React app built (`npm run build` in `app/` directory)
- [ ] PHP files tested locally (if possible)
- [ ] `config.local.php` prepared (with API keys)
- [ ] Database schema ready (`data/seed.sql` or migration script)
- [ ] `.htaccess` files prepared
- [ ] Health check endpoint tested

## Step-by-Step Deployment

### Step 1: Build React Application

**Local Machine**:
```bash
cd app
npm install
npm run build
```

**Output**: `app/dist/` directory contains:
- `index.html`
- `assets/` (JS, CSS, images)
- `manifest.json`
- `service-worker.js` (if PWA plugin configured)

**Verify**: Check that `dist/` contains all static files.

### Step 2: Upload Frontend Files

**Method**: FTP/SFTP or cPanel File Manager

**Destination**: Web root (typically `public_html/` or `www/`)

**Files to Upload**:
- Upload **contents** of `app/dist/` to web root
- Do NOT upload `dist/` folder itself
- Structure should be:
  ```
  public_html/
  ├── index.html
  ├── assets/
  ├── manifest.json
  └── service-worker.js
  ```

**Verify**: Access `https://yourdomain.com/` - should see React app (may show errors if API not configured yet).

### Step 3: Create API Directory

**In Web Root**:
- Create `api/` directory: `public_html/api/`

**Upload PHP Files**:
- Upload all files from `api/` directory:
  - `health.php`
  - `weather.php`
  - `sun.php`
  - `tides.php`
  - `forecast.php`
  - `lib/` directory (with all PHP library files)
  - `config.example.php` (for reference)

**Structure**:
```
public_html/
├── api/
│   ├── health.php
│   ├── weather.php
│   ├── sun.php
│   ├── tides.php
│   ├── forecast.php
│   ├── lib/
│   │   ├── Cache.php
│   │   ├── Database.php
│   │   ├── Validator.php
│   │   └── RateLimiter.php
│   └── config.example.php
```

### Step 4: Create Data Directory and Database

**Option A: Outside Web Root (Preferred)**

1. Create directory: `/home/username/data/` (outside `public_html`)
2. Create database file: `fishinginsights.db`
3. Set permissions (must be writable by PHP execution user):
   - Directory: `775` (or `770` if group-only access)
   - Database file: `664` (or `660` if group-only access)

**Option B: Inside Web Root (Fallback)**

1. Create directory: `public_html/data/`
2. Create `.htaccess` in `data/`:
   ```apache
   Order allow,deny
   Deny from all
   ```
3. Create database file: `fishinginsights.db`
4. Set permissions (must be writable by PHP execution user):
   - Directory: `775` (or `770` if group-only access)
   - Database file: `664` (or `660` if group-only access)

**Initialize Database**:

**Method 1: PHP Migration Script**
- Upload `api/migrate.php` (if created)
- Run via browser: `https://yourdomain.com/api/migrate.php`
- Or run via SSH: `php /path/to/api/migrate.php`

**Method 2: SQL File**
- Upload `data/seed.sql`
- Run via cPanel phpMyAdmin (if SQLite supported) or SSH:
  ```bash
  sqlite3 /path/to/fishinginsights.db < /path/to/seed.sql
  ```

**Verify**: Database file exists and is writable.

### Step 5: Configure .htaccess

**Root .htaccess** (for SPA routing):
- Upload `.htaccess` to `public_html/`
- Content (see architecture.md for full version):
  ```apache
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
  ```

**API .htaccess** (optional, for CORS if needed):
- Create `public_html/api/.htaccess` if CORS required:
  ```apache
  Header set Access-Control-Allow-Origin "https://yourdomain.com"
  Header set Access-Control-Allow-Methods "GET, OPTIONS"
  ```

**Data Directory .htaccess** (if inside web root):
- Create `public_html/data/.htaccess`:
  ```apache
  Order allow,deny
  Deny from all
  ```

### Step 6: Configure API (config.local.php)

**Create Config File**:
- In `public_html/api/`, create `config.local.php`
- Do NOT commit this file (should be in `.gitignore`)

**Template**:
```php
<?php
// Database configuration
// If api/ is beside data/ directory:
define('DB_PATH', __DIR__ . '/../data/fishinginsights.db');
// Or if outside web root:
// define('DB_PATH', '/home/username/data/fishinginsights.db');

// WorldTides API (optional - app works with mock mode if missing)
define('WORLDTIDES_API_KEY', 'your-api-key-here');
// Leave empty to use mock tides:
// define('WORLDTIDES_API_KEY', '');

// Timezone (Victoria)
define('DEFAULT_TIMEZONE', 'Australia/Melbourne');

// Rate limiting
define('RATE_LIMIT_PER_MINUTE', 60);
define('RATE_LIMIT_PER_HOUR', 1000);

// Cache TTLs (seconds)
define('CACHE_TTL_WEATHER', 3600);    // 1 hour
define('CACHE_TTL_SUN', 604800);      // 7 days
define('CACHE_TTL_TIDES', 43200);     // 12 hours

// Development mode (set to false in production)
define('DEV_MODE', false);
```

**Set Permissions**:
- `config.local.php`: `600` (read-write for owner only)
- Or `644` if group read needed (but never world-writable)

### Step 7: Verify Health Check

**Test Endpoint**:
- Open browser: `https://yourdomain.com/api/health.php`
- Should return JSON:
  ```json
  {
    "status": "ok",
    "php_version": "7.3.33",
    "has_pdo": true,
    "has_pdo_sqlite": true,
    "can_write_db": true,
    "can_write_cache": true,
    ...
  }
  ```

**Troubleshooting**:
- If `has_pdo_sqlite: false`: Contact hosting provider to enable SQLite extension
- If `can_write_db: false`: Check database file permissions
- If `php_version` is wrong: Contact hosting provider (may need to set PHP version in cPanel)

### Step 8: Seed Initial Data

**Locations**:
- Run migration script or SQL file to populate `locations` table
- Verify: Check database has 20-40 locations

**Species Rules**:
- Populate `species_rules` table with Victorian species data
- Verify: Check database has species entries

### Step 9: Test Frontend-Backend Integration

**Test Forecast Endpoint**:
- Open: `https://yourdomain.com/api/forecast.php?lat=-37.8&lng=144.9&days=7`
- Should return forecast JSON

**Test Frontend**:
- Open: `https://yourdomain.com/`
- Navigate to location picker
- Select a location
- Verify forecast displays

**Check Browser Console**:
- No CORS errors
- API calls succeed
- Service worker registers (if PWA configured)

### Step 10: Final Verification

- [ ] Health check returns all green
- [ ] Forecast endpoint returns data
- [ ] Frontend displays forecast
- [ ] Favourites persist (localStorage)
- [ ] PWA installable (if service worker configured)
- [ ] Offline mode works (shows cached data)

## Rollback Plan

### If Deployment Fails

**Step 1: Restore Previous Version**
- If previous version exists, restore via:
  - FTP: Upload previous files
  - cPanel Backup: Restore from backup
  - Git: `git checkout previous-commit` (if using version control)

**Step 2: Verify Health Check**
- Check `/api/health.php` returns errors
- Fix issues before re-deploying

**Step 3: Database Rollback**
- If database schema changed, restore from backup:
  ```bash
  cp fishinginsights.db.backup fishinginsights.db
  ```

**Step 4: Re-deploy**
- Fix issues identified
- Re-deploy following steps above

### Rollback Checklist

- [ ] Previous files restored
- [ ] Database restored (if changed)
- [ ] Health check passes
- [ ] App functional
- [ ] Document what went wrong for next deployment

## API Key Rotation

### WorldTides API Key Rotation

**Step 1: Obtain New Key**
- Log into WorldTides account
- Generate new API key
- Verify key works (test endpoint)

**Step 2: Update Config**
- Edit `api/config.local.php`
- Update `WORLDTIDES_API_KEY` constant
- Save file

**Step 3: Verify**
- Test `/api/tides.php` endpoint
- Check response includes real tide data (not mock)
- Monitor credit usage

**Step 4: Revoke Old Key** (optional)
- If old key compromised, revoke in WorldTides dashboard
- Old key will stop working immediately

### Security Best Practices

- **Never commit** `config.local.php` to version control
- **Rotate keys** if exposed or compromised
- **Monitor usage** via provider dashboards
- **Use environment variables** if hosting supports (alternative to `config.local.php`)

## Maintenance

### Regular Tasks

**Weekly**:
- Check health endpoint
- Monitor API credit usage (WorldTides)
- Review error logs (if available)

**Monthly**:
- Backup database
- Review cache hit rates
- Check for PHP/security updates (hosting provider)

**As Needed**:
- Update location data
- Update species rules
- Rotate API keys

### Database Backup

**Manual Backup**:
```bash
cp /path/to/fishinginsights.db /path/to/backup/fishinginsights.db.$(date +%Y%m%d)
```

**Automated Backup** (if cron available):
- Create backup script
- Schedule daily/weekly via cPanel cron jobs

## Open Questions

- Should we use environment variables instead of `config.local.php`? **DECISION: Use `config.local.php` for MVP. Environment variables can be added if hosting supports them.**
- Should we version the deployment? **DECISION: Use Git tags for versioning. Include version in health check response.**
- How to handle database migrations post-MVP? **DECISION: Create migration scripts. Run via browser or SSH. Document schema changes.**

