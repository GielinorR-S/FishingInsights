# FishingInsights Performance & Safety Analysis

**Generated:** 2025-01-XX  
**Purpose:** Identify performance bottlenecks, API credit waste, error handling gaps, and missing guards  
**Assumption:** Breaking production is unacceptable

---

## 1. Performance Bottlenecks

### 🔴 **CRITICAL BOTTLENECKS**

#### `api/lib/LocationHelper.php` - `findNearestLocation()`
**Issue:** O(n) linear search through ALL locations on EVERY forecast request
- **Line 39-40:** Fetches ALL locations from database: `SELECT ... FROM locations` (no WHERE clause)
- **Line 45-52:** Loops through ALL locations, calculating haversine distance for each
- **Impact:** 
  - If 100 locations exist → 100 distance calculations per forecast request
  - No early exit optimization
  - No spatial indexing (SQLite doesn't have native spatial indexes)
- **Scalability:** Performance degrades linearly with number of locations
- **Current:** Works for ~24 locations, but will slow down as locations grow

#### `api/forecast.php` - N+1 Database Queries
**Issue:** Multiple database queries inside loop
- **Line 147:** `Scoring::calculateSeasonalityScore($db, $currentMonth)` - Called once per day (7 times for 7-day forecast)
  - Each call executes: `SELECT COUNT(*) FROM species_rules WHERE ...` (4 parameters)
  - **Impact:** 7 database queries for seasonality score (could be cached per month)
- **Line 154:** `getRecommendedSpecies($db, $currentMonth, $weather, $tides)` - Called once per day
  - Each call executes: `SELECT ... FROM species_rules WHERE ...` (4 parameters)
  - **Impact:** 7 database queries for species recommendations (could be cached per month)
- **Line 157:** `getGearSuggestions($db, $recommendedSpecies)` - Called once per day
  - Each call executes: `SELECT ... FROM species_rules WHERE species_id = ?`
  - **Impact:** Up to 7 database queries (one per day, even if same species)
- **Total:** 21+ database queries for a 7-day forecast (should be ~3-5)

#### `api/forecast.php` - Tides Array Search
**Issue:** O(n) linear search for tides per day
- **Line 119-124:** `foreach ($tidesData['tides'] as $tideDay)` - Searches entire tides array for each day
- **Impact:** For 7-day forecast, searches through all tide days 7 times
- **Better:** Index tides by date first, then O(1) lookup

#### `api/lib/LocationHelper.php` - No Database Index Usage
**Issue:** Full table scan for location lookup
- **Line 39:** `SELECT ... FROM locations` - No WHERE clause, no LIMIT
- **Impact:** Fetches all rows even if only need one
- **Better:** Could use bounding box query (lat/lng ranges) to reduce dataset

### 🟠 **MEDIUM BOTTLENECKS**

#### `api/lib/ForecastData.php` - Array Bounds in Loops
**Issue:** `count()` called in loop condition
- **Line 58:** `for ($i = 0; $i < count($dates) && $i < $days; $i++)`
- **Line 141:** `for ($i = 0; $i < count($dates) && $i < $days; $i++)`
- **Impact:** `count()` called on every iteration (minor, but unnecessary)
- **Better:** Cache count before loop

#### `api/forecast.php` - Array Access Without Bounds Check
**Issue:** Direct array access `[$i]` without verifying array length
- **Line 113:** `$weather = $weatherData['forecast'][$i] ?? null;`
- **Line 114:** `$sun = $sunData['times'][$i] ?? null;`
- **Impact:** If API returns fewer days than requested, may access out-of-bounds
- **Current:** Uses `?? null` which prevents errors, but may skip days silently

#### `api/lib/Cache.php` - No Cleanup on Read
**Issue:** Expired cache entries accumulate
- **Line 23:** `WHERE ... AND expires_at > datetime('now')` - Filters expired, but doesn't delete
- **Line 56:** `clearExpired()` method exists but never called automatically
- **Impact:** Database grows with expired cache entries
- **Better:** Periodic cleanup or cleanup on cache miss

#### `api/lib/RateLimiter.php` - No Automatic Cleanup
**Issue:** Old rate limit records accumulate
- **Line 75:** `cleanup()` method exists but never called
- **Impact:** `rate_limits` table grows indefinitely
- **Better:** Call cleanup periodically or on each request

---

## 2. API Credit Waste

### 🔴 **CRITICAL WASTE**

#### `api/tides.php` - WorldTides API Called Even When Cached
**Issue:** Cache check happens AFTER API key validation
- **Line 49-56:** Checks cache
- **Line 60-101:** If cache miss AND key exists, calls WorldTides API
- **Problem:** If cache expires, makes API call even if:
  - Mock mode would work fine
  - Cache just expired (could use stale data)
  - API is down (wastes credit on failed request)
- **Impact:** Unnecessary API calls when mock mode is acceptable
- **Better:** Check cache first, only call API if absolutely necessary

#### `api/tides.php` - No Retry Logic or Error Handling for API Calls
**Issue:** Single API call attempt, no retry on transient failures
- **Line 71-76:** Makes one curl request, checks HTTP 200
- **Problem:** Network hiccups, rate limits, temporary API issues all waste credits
- **Impact:** Credits wasted on transient failures
- **Better:** Retry with exponential backoff, or fall back to mock immediately

#### `api/lib/ForecastData.php` - Open-Meteo Calls Without Error Recovery
**Issue:** If Open-Meteo fails, returns null, causing forecast to fail
- **Line 32-41:** Makes curl request, returns null on failure
- **Problem:** No retry, no fallback to cached data, no graceful degradation
- **Impact:** Forecast endpoint fails entirely if Open-Meteo is down
- **Better:** Use stale cache if available, or return partial data

#### `api/forecast.php` - No Stale Cache Fallback
**Issue:** If cache expired and API fails, entire forecast fails
- **Line 78-79:** Calls `fetchWeatherData()` and `fetchSunData()`
- **Line 91-92:** If either returns null, sends 503 error
- **Problem:** Could use stale cache data if available
- **Impact:** Service unavailable when API is down, even if recent cache exists
- **Better:** Check for stale cache (e.g., < 24 hours old) as fallback

#### `api/tides.php` - WorldTides API Called for Each Request
**Issue:** No distinction between cache hit/miss in credit usage
- **Line 63-101:** If key exists and cache miss, calls API
- **Problem:** Multiple concurrent requests for same location all call API
- **Impact:** Race condition: 10 requests = 10 API calls (should be 1)
- **Better:** Lock mechanism or queue to prevent concurrent API calls

### 🟠 **MEDIUM WASTE**

#### `api/lib/ForecastData.php` - Separate Calls for Weather and Sun
**Issue:** Two separate API calls to Open-Meteo for same location/date range
- **Line 23-30:** Weather API call
- **Line 108-116:** Sun API call (separate request)
- **Problem:** Open-Meteo supports both in one request
- **Impact:** 2x API calls when 1 would suffice
- **Better:** Combine into single request with both daily parameters

#### `api/tides.php` - No Request Deduplication
**Issue:** Multiple requests for same location/date trigger multiple API calls
- **Line 49-56:** Cache check happens per request
- **Problem:** If 5 users request same forecast simultaneously, 5 API calls made
- **Impact:** Credits wasted on duplicate requests
- **Better:** Request queue or lock to deduplicate

---

## 3. Error Handling Gaps

### 🔴 **CRITICAL GAPS**

#### `app/src/pages/Forecast.tsx` - No Error Recovery
**Issue:** If API returns error, UI shows error message but no retry
- **Line 62-75:** Catches error, sets error state, shows message
- **Problem:** User must manually refresh or navigate away and back
- **Impact:** Poor UX, no automatic recovery
- **Better:** Retry button, auto-retry with backoff, or fallback to cached data

#### `app/src/pages/Forecast.tsx` - No Handling for Partial Data
**Issue:** If forecast array is shorter than expected, UI may break
- **Line 124:** `forecastDays.length > 0` check exists
- **Line 165:** `forecastDays.map((day, idx) => ...)` - Assumes all days present
- **Problem:** If API returns 5 days instead of 7, UI shows 5 days (OK), but if returns 0 days, may crash
- **Impact:** Empty forecast array causes blank screen
- **Better:** Check for empty array, show message

#### `api/forecast.php` - Silent Data Skipping
**Issue:** If weather/sun data missing for a day, day is skipped silently
- **Line 139-141:** `if (!$weather || !$sun) { continue; }`
- **Problem:** User expects 7 days, gets 6 (or fewer) with no explanation
- **Impact:** Confusing UX, missing days in forecast
- **Better:** Return day with error flag, or return partial data with warning

#### `api/lib/ForecastData.php` - No Error Details on API Failure
**Issue:** Returns null on API failure, no error details
- **Line 39-41:** `if ($httpCode !== 200 || $response === false) { return null; }`
- **Problem:** No logging, no error details, no distinction between network error vs API error
- **Impact:** Difficult to debug API issues
- **Better:** Log error details, return error structure, or throw exception with context

#### `api/tides.php` - No curl_error() Check
**Issue:** Checks HTTP code but not curl errors
- **Line 75:** `$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);`
- **Line 78:** Only checks `$httpCode === 200`
- **Problem:** Network errors (timeout, DNS failure) may return false but not checked
- **Impact:** Credits may be charged even on network failures
- **Better:** Check `curl_error($ch)` before checking HTTP code

#### `api/forecast.php` - No Validation of Fetched Data Structure
**Issue:** Assumes API responses have correct structure
- **Line 113:** `$weather = $weatherData['forecast'][$i] ?? null;`
- **Line 114:** `$sun = $sunData['times'][$i] ?? null;`
- **Problem:** If API returns different structure, null values used, days skipped
- **Impact:** Silent failures, missing forecast days
- **Better:** Validate structure, log mismatches, return error if critical

### 🟠 **MEDIUM GAPS**

#### `app/src/pages/Forecast.tsx` - No Network Error Distinction
**Issue:** All errors treated the same
- **Line 72:** `catch (err) { setError(err instanceof Error ? err.message : 'Failed to load forecast') }`
- **Problem:** Network errors vs API errors vs parsing errors all show same message
- **Impact:** User can't distinguish between network issue vs API issue
- **Better:** Different error messages for different error types

#### `api/forecast.php` - No Timeout on External API Calls
**Issue:** External API calls have timeout, but no overall request timeout
- **Line 78-83:** Calls `fetchWeatherData()`, `fetchSunData()`, `fetchTidesData()`
- **Problem:** If any call hangs, entire request hangs
- **Impact:** User waits indefinitely, server resources tied up
- **Better:** Overall request timeout, or async processing

#### `app/src/pages/Locations.tsx` - No Error Recovery
**Issue:** If locations API fails, shows error but no retry
- **Line 34-36:** Catches error, sets error state
- **Problem:** User must manually refresh
- **Impact:** Poor UX
- **Better:** Retry button or auto-retry

---

## 4. Missing Guards (Null Checks, Bounds, Invalid Inputs)

### 🔴 **CRITICAL MISSING GUARDS**

#### `api/forecast.php` - Array Access Without Bounds Check
**Issue:** Direct array access `[$i]` without verifying array length
- **Line 113:** `$weatherData['forecast'][$i]` - No check if `forecast` array has `$i` elements
- **Line 114:** `$sunData['times'][$i]` - No check if `times` array has `$i` elements
- **Line 295:** `$tides['change_windows'][0]` - No check if array is empty
- **Line 378:** `$species[0]['id']` - No check if array is empty (guarded by `if (empty($species))` but not for `$species[0]`)
- **Impact:** PHP warnings/errors if arrays are shorter than expected
- **Better:** Check array length before access, or use `??` operator

#### `api/lib/ForecastData.php` - Array Access in Loops
**Issue:** Array access `[$i]` without bounds check
- **Line 70:** `'date' => $dates[$i]` - No check if `$dates[$i]` exists
- **Line 71-76:** Multiple `[$i]` accesses with `isset()` checks (good), but `$dates[$i]` not checked
- **Impact:** May access undefined array index if API returns fewer dates
- **Better:** Check `isset($dates[$i])` before using

#### `api/lib/Scoring.php` - Division by Zero Risk
**Issue:** Division without checking for zero
- **Line 60:** `$tideChangesPerDay = count($events) / 2;`
- **Problem:** If `count($events)` is 0, result is 0 (OK), but if count is 1, result is 0.5 (unexpected)
- **Impact:** Incorrect score calculation for edge cases
- **Better:** Check for empty array, handle edge case explicitly

#### `api/lib/Scoring.php` - Empty Array Access
**Issue:** Array functions on potentially empty arrays
- **Line 70-72:** `array_map()` and `max()/min()` on `$heights` array
- **Line 73:** `count($heights) > 0` check exists (good)
- **Problem:** If `$events` is empty, `$heights` is empty, `max()/min()` may return false
- **Impact:** Incorrect score if no tide events
- **Better:** Explicit check for empty array before calculations

#### `api/forecast.php` - Null Reference in Function Calls
**Issue:** Functions called with potentially null parameters
- **Line 144:** `Scoring::calculateWeatherScore($weather)` - `$weather` could be null (guarded by `continue` on line 139)
- **Line 145:** `Scoring::calculateTideScore($tides)` - `$tides` could be null (uses `?:` operator)
- **Line 146:** `Scoring::calculateDawnDuskScore($sun, $tides)` - Both could be null
- **Problem:** Functions may not handle null gracefully
- **Impact:** PHP warnings/errors if functions don't check for null
- **Better:** Ensure functions handle null, or validate before calling

#### `api/lib/LocationHelper.php` - No Null Check on Database Fields
**Issue:** Assumes database fields are not null
- **Line 46:** `$location['latitude']` and `$location['longitude']` - No null check
- **Problem:** If database has NULL coordinates, haversine calculation fails
- **Impact:** PHP warnings/errors, incorrect distance calculations
- **Better:** Check for null before calculating distance

#### `api/forecast.php` - No Validation of Species Array Structure
**Issue:** Assumes species array has expected structure
- **Line 378:** `$species[0]['id']` - Assumes `$species[0]` exists and has `'id'` key
- **Line 342-347:** Assumes `$s['species_id']`, `$s['common_name']` exist
- **Problem:** If database returns unexpected structure, array access fails
- **Impact:** PHP warnings/errors
- **Better:** Validate structure before access

#### `api/lib/ForecastData.php` - No Validation of API Response Structure
**Issue:** Assumes Open-Meteo returns expected structure
- **Line 50-56:** Accesses `$data['daily']['time']`, `$data['daily']['temperature_2m_max']`, etc.
- **Problem:** If API changes structure or returns error, array access fails
- **Impact:** PHP warnings/errors, null data returned
- **Better:** Validate structure before processing

### 🟠 **MEDIUM MISSING GUARDS**

#### `app/src/pages/Forecast.tsx` - No Null Check on Nested Properties
**Issue:** Accesses nested properties without null checks
- **Line 190:** `day.weather.temperature_min` - No check if `day.weather` exists
- **Line 195:** `day.weather.wind_speed` - No check
- **Line 199:** `day.weather.precipitation` - No check
- **Line 203:** `day.weather.conditions` - No check
- **Problem:** If `day.weather` is null/undefined, JavaScript error
- **Impact:** UI crash, blank screen
- **Better:** Optional chaining (`day.weather?.temperature_min`) or null checks

#### `app/src/pages/Forecast.tsx` - Array Map Without Length Check
**Issue:** Maps arrays without checking if they exist
- **Line 165:** `forecastDays.map(...)` - Checks `forecastDays.length > 0` on line 124, but not before map
- **Line 213:** `day.best_bite_windows.map(...)` - Checks `length > 0` but not if array exists
- **Problem:** If array is null/undefined (not just empty), map fails
- **Impact:** JavaScript error, UI crash
- **Better:** Check for array existence before mapping

#### `api/forecast.php` - No Validation of DateTime Parsing
**Issue:** DateTime parsing may fail silently
- **Line 214:** `new DateTime($sun['sunrise'])` - No validation if string is valid
- **Line 220:** `new DateTime($sun['sunset'])` - No validation
- **Problem:** If API returns invalid date string, DateTime constructor may throw exception or create invalid object
- **Impact:** PHP errors, incorrect calculations
- **Better:** Validate date string format before parsing

#### `api/lib/Scoring.php` - No Validation of DateTime Objects
**Issue:** Assumes DateTime objects are valid
- **Line 103:** `new DateTime($sunrise)` - No validation
- **Line 110:** `new DateTime($sunset)` - No validation
- **Line 119-120:** `new DateTime($window['start'])` - No validation
- **Problem:** If invalid date strings passed, DateTime may be invalid
- **Impact:** Incorrect calculations, PHP errors
- **Better:** Validate or catch DateTime exceptions

#### `api/forecast.php` - No Bounds Check on Array Slicing
**Issue:** `array_slice()` may return fewer items than expected
- **Line 359:** `array_slice($result, 0, 3)` - Assumes at least 3 items exist
- **Problem:** If fewer than 3 species, returns fewer (OK), but no validation
- **Impact:** May return 0-2 species instead of expected 1-3
- **Better:** Validate result length, pad if needed

#### `api/lib/ForecastData.php` - No Validation of Date Array Length
**Issue:** Assumes date arrays match expected length
- **Line 58:** `for ($i = 0; $i < count($dates) && $i < $days; $i++)`
- **Problem:** If API returns fewer dates than requested, loop stops early (OK), but no validation
- **Impact:** Partial data returned without warning
- **Better:** Validate array length, log mismatch

#### `api/forecast.php` - No Validation of Mock Tides Return Value
**Issue:** Assumes `generateMockTides()` returns expected structure
- **Line 130:** `$mockTides = generateMockTides(...)`
- **Line 131:** `if (!empty($mockTides) && isset($mockTides[0]))`
- **Problem:** If function returns unexpected structure, access fails
- **Impact:** PHP warnings/errors
- **Better:** Validate return structure

---

## Summary

### Performance Bottlenecks (Priority Order)
1. **N+1 Database Queries** - 21+ queries for 7-day forecast (should be 3-5)
2. **Location Linear Search** - O(n) search through all locations
3. **Tides Array Search** - O(n) search for each day
4. **No Cache Cleanup** - Database grows with expired entries
5. **Array Count in Loops** - Minor, but unnecessary

### API Credit Waste (Priority Order)
1. **No Stale Cache Fallback** - Wastes credits when API is down
2. **Race Conditions** - Multiple concurrent requests = multiple API calls
3. **Separate Weather/Sun Calls** - 2x API calls when 1 would suffice
4. **No Retry Logic** - Credits wasted on transient failures
5. **No Request Deduplication** - Duplicate requests trigger duplicate API calls

### Error Handling Gaps (Priority Order)
1. **No Error Recovery in Frontend** - User must manually refresh
2. **Silent Data Skipping** - Days missing without explanation
3. **No curl_error() Check** - Network errors not detected
4. **No Partial Data Handling** - All-or-nothing approach
5. **No Error Details** - Difficult to debug API issues

### Missing Guards (Priority Order)
1. **Array Access Without Bounds** - Multiple locations, high crash risk
2. **Null Reference in Function Calls** - Functions may not handle null
3. **No Validation of API Response Structure** - Assumes structure is correct
4. **No Null Check on Nested Properties (Frontend)** - JavaScript errors
5. **Division/Array Operations on Empty Arrays** - Edge case failures

### Recommendations
1. **Cache seasonality/species queries** per month (reduce 14 queries to 1)
2. **Index tides by date** before loop (O(1) lookup instead of O(n))
3. **Add stale cache fallback** (use cache even if expired, if < 24h old)
4. **Add request locking** for API calls (prevent concurrent duplicate calls)
5. **Add comprehensive null checks** in frontend (optional chaining)
6. **Add array bounds validation** before access
7. **Add error recovery UI** (retry buttons, auto-retry)
8. **Add curl_error() checks** before processing responses
9. **Combine weather/sun API calls** into single request
10. **Add automatic cache cleanup** (periodic or on cache miss)

