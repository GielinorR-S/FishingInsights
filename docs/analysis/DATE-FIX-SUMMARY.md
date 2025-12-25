# Forecast Date Range Fix - Summary

## Problem
Forecast was returning 7 days starting from yesterday instead of today (Australia/Melbourne timezone).

## Root Cause
1. Backend used `date('Y-m-d')` which uses server timezone, not Australia/Melbourne
2. Date calculations used `strtotime()` which also uses server timezone
3. Frontend didn't explicitly pass today's date in Melbourne timezone

## Files Changed

### Backend

1. **`api/forecast.php`**
   - Fixed default start date to use `DateTime` with `Australia/Melbourne` timezone
   - Fixed date loop to use `DateTime` with timezone instead of `strtotime()`
   - Added validation error messages for days parameter
   - Ensures forecast dates start exactly at the provided start date

2. **`api/lib/Validator.php`**
   - Updated `validateDate()` to use `Australia/Melbourne` timezone for "today" comparison
   - Ensures date validation is timezone-aware

### Frontend

3. **`app/src/services/api.ts`**
   - Added `getTodayInMelbourne()` helper function
   - Modified `getForecast()` to always pass `start` parameter (defaults to today in Melbourne)

4. **`app/src/pages/Forecast.tsx`**
   - Added date picker UI for selecting forecast start date
   - Shows actual start date label
   - Automatically uses today in Melbourne timezone if no date selected
   - Refetches forecast when date changes

### Testing

5. **`scripts/test_forecast_dates.php`** (NEW)
   - Test script to verify date range fixes
   - Tests default start (should be today)
   - Tests explicit start date (should use exactly as provided)

## Key Changes

### Backend Date Handling
```php
// OLD (wrong timezone):
$start = date('Y-m-d');
$date = date('Y-m-d', strtotime($start . " +$i days"));

// NEW (correct timezone):
$dt = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
$start = $dt->format('Y-m-d');
$dateObj = clone $startDate;
$dateObj->modify("+$i days");
$date = $dateObj->format('Y-m-d');
```

### Frontend Date Handling
```typescript
// NEW: Always pass start date in Melbourne timezone
function getTodayInMelbourne(): string {
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Australia/Melbourne',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  return formatter.format(new Date())
}
```

## Validation Rules

- `start` parameter: Must be YYYY-MM-DD format
- `days` parameter: Must be 1-14
- `lat`/`lng`: Required, must be valid coordinates
- Past dates: Only allowed in DEV_MODE
- Date comparison: Uses Australia/Melbourne timezone

## Testing Commands

### Test 1: Default start (should be today)
```bash
curl "http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7"
```
Expected: First `forecast[0].date` should be today in Australia/Melbourne

### Test 2: Explicit start date
```bash
curl "http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7&start=2025-12-26"
```
Expected: First `forecast[0].date` should be exactly `2025-12-26`

### Test 3: Run automated test script
```bash
php scripts/test_forecast_dates.php
```
Expected: All tests pass

## Verification Checklist

- [x] Default start date is today in Australia/Melbourne
- [x] Explicit start date is used exactly (no timezone shifting)
- [x] Forecast array starts at the start date
- [x] Dates are sequential (start, start+1, start+2, ...)
- [x] Frontend shows date picker
- [x] Frontend passes today in Melbourne timezone by default
- [x] Validation errors are clear and helpful

## Notes

- All date operations now use `DateTime` with explicit timezone
- Frontend uses `Intl.DateTimeFormat` for timezone-aware date formatting
- No breaking changes to API contract
- Backward compatible (existing calls work, just with correct dates now)

