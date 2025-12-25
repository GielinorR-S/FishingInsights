# Risk Register

## Overview

This document identifies top risks for FishingInsights MVP deployment and provides mitigations and fallbacks.

## Risk Assessment Matrix

**Likelihood**: Low | Medium | High
**Impact**: Low | Medium | High | Critical

## Top Risks

### 1. PHP 7.3 Limitations

**Description**: PHP 7.3.33 is locked on hosting. Cannot use modern PHP features (arrow functions, typed properties, etc.). Risk of accidentally using incompatible syntax.

**Likelihood**: Medium
**Impact**: High (blocks deployment)

**Mitigation**:
- Strict code review for PHP 7.3 compatibility
- Use PHP 7.3 syntax checker in CI/CD (if available)
- Document forbidden syntax in architecture.md
- Test on PHP 7.3.33 locally before deployment

**Fallback**:
- If incompatible syntax used, refactor to PHP 7.3-compatible code
- Use traditional functions instead of arrow functions
- Use untyped properties instead of typed properties
- Use null coalescing (`??`) instead of null coalescing assignment (`??=`)

**Status**: Mitigated (documentation enforces compatibility)

---

### 2. SQLite Extension Missing

**Description**: Hosting provider may not have `pdo_sqlite` extension enabled. App cannot function without SQLite.

**Likelihood**: Low (most shared hosting includes SQLite)
**Impact**: Critical (app cannot function)

**Mitigation**:
- Verify SQLite support before deployment (health check)
- Contact hosting provider to enable extension if missing
- Document requirement in deployment guide
- Health check endpoint verifies extension availability

**Fallback**:
- If SQLite unavailable, request hosting provider enable it
- If provider refuses, consider alternative hosting
- No code fallback (SQLite is core requirement)

**Status**: Mitigated (health check detects issue early)

---

### 3. Write Permissions on Database

**Description**: Web server may not have write permissions on database file or directory. Caching and data writes will fail.

**Likelihood**: Medium (common on shared hosting)
**Impact**: High (caching fails, app degraded)

**Mitigation**:
- Health check verifies write capability (authoritative check)
- Document required permissions: File `664`/`660`, Directory `775`/`770` (writable by PHP execution user)
- Test write permissions during deployment
- Use outside web root if possible (better security)
- Rely on `/api/health.php` to confirm write capability

**Fallback**:
- If write fails, app can run read-only (no caching)
- Display warning to user: "Caching disabled, responses may be slower"
- Contact hosting provider to fix permissions
- Fallback to in-memory cache (not persistent, but better than nothing)

**Status**: Mitigated (health check + permission documentation)

---

### 4. HTTPS/PWA/Geolocation Limitations

**Description**: PWA requires HTTPS. Geolocation API requires HTTPS or localhost. Some features may not work on HTTP.

**Likelihood**: Low (most hosting provides HTTPS)
**Impact**: Medium (PWA features degraded)

**Mitigation**:
- Deploy on HTTPS (required for PWA)
- Test geolocation on HTTPS
- Graceful degradation: App works without geolocation (search still available)
- Document HTTPS requirement

**Fallback**:
- If HTTPS unavailable, PWA install may not work (but app still functional)
- Geolocation falls back to manual location search
- Service worker may not register (offline mode degraded)
- App remains functional, just without PWA features

**Status**: Mitigated (HTTPS is standard on modern hosting)

---

### 5. API Credits Burn (WorldTides)

**Description**: WorldTides API is credit-based. If caching fails or requests are excessive, credits may be exhausted quickly, exceeding $5/month budget.

**Likelihood**: Medium (if caching not working properly)
**Impact**: Medium (tides data unavailable, mock mode activated)

**Mitigation**:
- Aggressive caching: 12-hour TTL for tides
- Monitor credit usage via WorldTides dashboard
- Health check verifies cache is working
- Use `/api/forecast.php` as primary endpoint (reduces round trips)
- Mock tides mode if credits exhausted (no frontend change)

**Fallback**:
- If credits exhausted, enable mock tides mode automatically
- Mock mode generates estimated tides (clearly indicated in response)
- Frontend displays "Estimated tide data" indicator
- User experience degraded but app remains functional
- Purchase more credits if needed

**Status**: Mitigated (aggressive caching + mock mode)

---

### 6. Caching Correctness

**Description**: Stale cache data may be returned, or cache may not be working correctly. Users see outdated forecasts.

**Likelihood**: Low (if implemented correctly)
**Impact**: Medium (poor user experience, incorrect recommendations)

**Mitigation**:
- Implement proper TTL logic (check `expires_at` before returning cache)
- Health check verifies cache write capability
- Clear expired entries periodically
- Include `cached_at` timestamp in responses
- Frontend displays "Last updated: X minutes ago"

**Fallback**:
- If cache stale, force refresh (bypass cache for one request)
- Frontend can request fresh data with `?refresh=true` parameter
- Manual cache clear via admin endpoint (if created)
- Worst case: Disable caching temporarily (performance impact)

**Status**: Mitigated (TTL logic + cache verification)

---

### 7. Rate Limiting False Positives

**Description**: IP-based rate limiting may block legitimate users (shared IPs, VPNs, etc.). Legitimate users get 429 errors.

**Likelihood**: Low (limits are generous: 60/min, 1000/hour)
**Impact**: Low (user temporarily blocked, can retry)

**Mitigation**:
- Set generous rate limits (60/min, 1000/hour per IP)
- Health check endpoint bypasses rate limiting
- Return `Retry-After` header with 429 response
- Log rate limit hits for monitoring

**Fallback**:
- If false positives occur, increase rate limits
- Whitelist specific IPs if needed (admin feature)
- Disable rate limiting temporarily if causing issues
- User can retry after `Retry-After` period

**Status**: Mitigated (generous limits + retry logic)

---

### 8. External API Downtime

**Description**: Open-Meteo or WorldTides API may be down. App cannot fetch fresh data.

**Likelihood**: Low (APIs are generally reliable)
**Impact**: Medium (fresh data unavailable)

**Mitigation**:
- Aggressive caching (1 hour weather, 7 days sun, 12 hours tides)
- Return cached data if API fails
- Health check monitors API availability (optional)
- Mock tides mode if WorldTides down

**Fallback**:
- Return cached data with "Data may be outdated" indicator
- If no cache available, return error with retry option
- Frontend displays error state with "Retry" button
- App remains functional with stale data

**Status**: Mitigated (caching + error handling)

---

### 9. SPA Routing Not Working

**Description**: `.htaccess` rewrite rules may not work on hosting. Direct URLs (e.g., `/forecast/123`) return 404.

**Likelihood**: Low (Apache mod_rewrite is standard)
**Impact**: Medium (deep linking broken, but app still works)

**Mitigation**:
- Test `.htaccess` rules during deployment
- Verify mod_rewrite is enabled (check via health check or phpinfo)
- Document fallback: Hash routing (if mod_rewrite unavailable)
- Use React Router hash mode as fallback

**Fallback**:
- Switch to hash routing (`#/forecast/123` instead of `/forecast/123`)
- Update React Router config to use hash mode
- App works, but URLs are less clean

**Status**: Mitigated (test during deployment)

---

### 10. Service Worker/PWA Issues

**Description**: Service worker may not register, or PWA install prompt may not appear. Offline mode may not work.

**Likelihood**: Low (if configured correctly)
**Impact**: Low (app works, but PWA features degraded)

**Mitigation**:
- Test service worker registration in browser console
- Verify `manifest.json` is accessible
- Test PWA install on Chrome/Edge
- Check HTTPS requirement

**Fallback**:
- If service worker fails, app still works (just no offline mode)
- If install prompt doesn't appear, users can still bookmark
- App remains fully functional, just not installable

**Status**: Mitigated (PWA is enhancement, not critical)

---

## Risk Summary

| Risk | Likelihood | Impact | Status |
|------|-----------|--------|--------|
| PHP 7.3 Limitations | Medium | High | Mitigated |
| SQLite Extension Missing | Low | Critical | Mitigated |
| Write Permissions | Medium | High | Mitigated |
| HTTPS/PWA/Geolocation | Low | Medium | Mitigated |
| API Credits Burn | Medium | Medium | Mitigated |
| Caching Correctness | Low | Medium | Mitigated |
| Rate Limiting False Positives | Low | Low | Mitigated |
| External API Downtime | Low | Medium | Mitigated |
| SPA Routing | Low | Medium | Mitigated |
| Service Worker/PWA | Low | Low | Mitigated |

## Monitoring and Response

### Health Check Monitoring

**Regular Checks**:
- Monitor `/api/health.php` endpoint
- Alert if any critical check fails
- Log health check results (if logging available)

### API Usage Monitoring

**WorldTides**:
- Check credit usage weekly
- Set up alerts if credits < 20% remaining
- Monitor request frequency

### Error Monitoring

**If Available**:
- Enable PHP error logging
- Monitor 500 errors
- Track rate limit hits
- Monitor cache hit rates

## Open Questions

- Should we implement automated health check monitoring? **DECISION: No for MVP. Manual checks sufficient. Can add automated monitoring post-MVP.**
- Should we create admin dashboard for monitoring? **DECISION: No for MVP. Use health check endpoint and external dashboards. Can add admin panel later.**
- How to handle database corruption? **DECISION: Regular backups. If corruption detected, restore from backup. Health check can verify database integrity.**

