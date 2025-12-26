# API Contract: /api/forecast.php

**Version:** 1.0  
**Last Updated:** 2025-12-26  
**Status:** LOCKED - Breaking changes require version bump

---

## Base URL Assumptions

### Development (Local)
- **Base URL:** `http://127.0.0.1:8001`
- **Full Endpoint:** `http://127.0.0.1:8001/api/forecast.php`
- **Server:** PHP built-in server (`php -S 127.0.0.1:8001 -t .`)

### Production
- **Base URL:** Determined by hosting provider (e.g., `https://example.com`)
- **Full Endpoint:** `{BASE_URL}/api/forecast.php`
- **Server:** Shared cPanel PHP 7.3.33 hosting

---

## Query Parameters

### Required Parameters

| Parameter | Type | Range/Format | Description |
|-----------|------|--------------|-------------|
| `lat` | float | -90.0 to 90.0 | Latitude in decimal degrees |
| `lng` | float | -180.0 to 180.0 | Longitude in decimal degrees |

### Optional Parameters

| Parameter | Type | Range/Format | Default | Description |
|-----------|------|--------------|---------|-------------|
| `days` | integer | 1 to 14 | `7` | Number of forecast days to return |
| `start` | string | YYYY-MM-DD | Today (Melbourne) | Start date for forecast (must be today or future, unless DEV_MODE) |
| `refresh` | string | `true` or `1` | `false` | If `true` or `1`, bypasses forecast-level cache |

### Parameter Validation

- **Invalid `lat`/`lng`:** Returns `400 Bad Request` with error message
- **Invalid `days`:** Returns `400 Bad Request` if outside 1-14 range
- **Invalid `start`:** Returns `400 Bad Request` if format is wrong or date is in past (unless DEV_MODE)
- **Missing `lat`/`lng`:** Returns `400 Bad Request` with error message

---

## Response Schema

### Top-Level Response

```json
{
  "error": boolean,        // REQUIRED: false on success, true on error
  "data": {               // REQUIRED: Present when error=false
    "location": {...},
    "timezone": string,   // REQUIRED
    "forecast": [...],    // REQUIRED: Array of day objects
    "cached": boolean,    // REQUIRED: Indicates if underlying provider data was cached
    "cached_at": string,  // REQUIRED: ISO 8601 timestamp of when data was fetched
    "warning": string     // OPTIONAL: Present only if no nearby location found
  }
}
```

### Location Object

```json
{
  "lat": number,          // REQUIRED: Requested latitude
  "lng": number,          // REQUIRED: Requested longitude
  "name": string,         // REQUIRED: Location name (or "Unknown Location")
  "region": string|null   // REQUIRED: Region name or null
}
```

### Forecast Day Object

Each element in the `forecast` array represents one day:

```json
{
  "date": string,                    // REQUIRED: YYYY-MM-DD format
  "score": number,                   // REQUIRED: 0-100 fishing score
  "weather": {...},                  // REQUIRED: Weather conditions object
  "sun": {...},                      // REQUIRED: Sun times object
  "tides": {...},                    // REQUIRED: Tides data object
  "best_bite_windows": [...],        // REQUIRED: Array of bite window objects (may be empty)
  "recommended_species": [...],       // REQUIRED: Array of species objects (may be empty)
  "gear_suggestions": {...},         // REQUIRED: Gear recommendations object
  "reasons": [...]                   // REQUIRED: Array of reason objects (2-4 items)
}
```

### Weather Object

```json
{
  "date": string,              // REQUIRED: YYYY-MM-DD
  "temperature_max": number,   // REQUIRED: Maximum temperature in Celsius
  "temperature_min": number,   // REQUIRED: Minimum temperature in Celsius
  "wind_speed": number,        // REQUIRED: Wind speed in km/h
  "wind_direction": number,    // REQUIRED: Wind direction in degrees (0-360)
  "precipitation": number,     // REQUIRED: Precipitation in mm
  "cloud_cover": number,        // REQUIRED: Cloud cover percentage (0-100)
  "conditions": string         // REQUIRED: One of: "clear", "partly_cloudy", "mostly_cloudy", "overcast"
}
```

### Sun Object

```json
{
  "date": string,              // REQUIRED: YYYY-MM-DD
  "sunrise": string,           // REQUIRED: ISO 8601 timestamp with timezone offset
  "sunset": string,            // REQUIRED: ISO 8601 timestamp with timezone offset
  "dawn": string,              // REQUIRED: ISO 8601 timestamp (sunrise - 30 minutes)
  "dusk": string               // REQUIRED: ISO 8601 timestamp (sunset + 30 minutes)
}
```

### Tides Object

```json
{
  "date": string,              // REQUIRED: YYYY-MM-DD
  "events": [...],             // REQUIRED: Array of tide event objects (typically 4 per day)
  "change_windows": [...]      // REQUIRED: Array of change window objects (typically 4 per day)
}
```

**Tide Event Object:**
```json
{
  "time": string,              // REQUIRED: ISO 8601 timestamp with timezone offset
  "type": string,              // REQUIRED: "low" or "high"
  "height": number             // REQUIRED: Tide height in meters
}
```

**Change Window Object:**
```json
{
  "start": string,             // REQUIRED: ISO 8601 timestamp (event_time - 1 hour)
  "end": string,               // REQUIRED: ISO 8601 timestamp (event_time + 1 hour)
  "type": string,              // REQUIRED: "rising" or "falling"
  "event_time": string,        // REQUIRED: ISO 8601 timestamp of the tide event
  "event_type": string         // REQUIRED: "low" or "high"
}
```

### Best Bite Window Object

```json
{
  "start": string,             // REQUIRED: ISO 8601 timestamp
  "end": string,               // REQUIRED: ISO 8601 timestamp
  "reason": string,            // REQUIRED: Human-readable explanation
  "quality": string            // REQUIRED: One of: "excellent", "good", "fair"
}
```

### Recommended Species Object

```json
{
  "id": string,                // REQUIRED: Species identifier
  "name": string,              // REQUIRED: Common name
  "confidence": number,        // REQUIRED: 0.0 to 1.0 (confidence score)
  "why": string               // REQUIRED: Human-readable explanation
}
```

### Gear Suggestions Object

```json
{
  "bait": string[],            // REQUIRED: Array of bait suggestions (may be empty)
  "lure": string[],            // REQUIRED: Array of lure suggestions (may be empty)
  "line_weight": string,       // REQUIRED: Line weight recommendation (e.g., "8-15lb")
  "leader": string,            // REQUIRED: Leader recommendation (e.g., "10-20lb")
  "rig": string               // REQUIRED: Rig recommendation (e.g., "paternoster")
}
```

### Reason Object

```json
{
  "title": string,             // REQUIRED: Short title
  "detail": string,            // REQUIRED: Detailed explanation
  "contribution_points": number, // REQUIRED: Points contributed to score (0-100)
  "severity": string,          // REQUIRED: One of: "positive", "negative", "neutral"
  "category": string          // REQUIRED: One of: "weather", "tide", "dawn_dusk", "seasonality"
}
```

---

## Explicit Invariants

### Timezone
- **REQUIRED:** `data.timezone` MUST equal `"Australia/Melbourne"`
- **REQUIRED:** All ISO 8601 timestamps MUST include timezone offset (e.g., `+11:00` or `+10:00`)
- **REQUIRED:** All date calculations MUST use `Australia/Melbourne` timezone

### Date Correctness
- **REQUIRED:** When `start` parameter is omitted or set to today's date in Melbourne, `forecast[0].date` MUST equal today's date in `Australia/Melbourne` timezone (format: `YYYY-MM-DD`)
- **REQUIRED:** `forecast` array MUST contain exactly `days` elements (unless data fetch fails for specific days)
- **REQUIRED:** Dates in `forecast` array MUST be consecutive, starting from `start` date

### Score Range
- **REQUIRED:** `forecast[].score` MUST be an integer or float between 0 and 100 (inclusive)
- **REQUIRED:** Score represents composite fishing quality (higher = better)

### Cache Metadata
- **REQUIRED:** `data.cached` MUST be a boolean
  - `true`: At least one underlying provider data (weather/sun) was served from cache
  - `false`: All data was freshly fetched
- **REQUIRED:** `data.cached_at` MUST be an ISO 8601 timestamp with timezone offset
  - Represents when the underlying provider data was fetched (not when forecast-level cache was created)

### Array Lengths
- **REQUIRED:** `forecast` array length MUST equal `days` parameter (unless validation fails)
- **REQUIRED:** `best_bite_windows` array MAY be empty (no good windows) or contain 0-4 items
- **REQUIRED:** `recommended_species` array MUST contain 0-3 items
- **REQUIRED:** `reasons` array MUST contain 2-4 items
- **REQUIRED:** `tides.events` array MUST contain at least 0 items (typically 4 per day)
- **REQUIRED:** `tides.change_windows` array MUST contain at least 0 items (typically 4 per day)

---

## Error Format

### Error Response Schema

```json
{
  "error": true,              // REQUIRED: Always true for errors
  "message": string,          // REQUIRED: Human-readable error message
  "code": string,             // REQUIRED: Machine-readable error code
  "details": object          // OPTIONAL: Additional error details (may be empty object)
}
```

### HTTP Status Codes

| Status Code | Error Code | Description |
|-------------|------------|-------------|
| `400 Bad Request` | `VALIDATION_ERROR` | Invalid parameters (lat/lng/days/start) |
| `429 Too Many Requests` | `RATE_LIMIT_EXCEEDED` | Rate limit exceeded (60/min or 1000/hour) |
| `500 Internal Server Error` | `INTERNAL_ERROR` | Server-side error (database, API failure, etc.) |
| `503 Service Unavailable` | `DATA_FETCH_ERROR` | Failed to fetch required data (weather/sun) |

### Error Code Reference

- **`VALIDATION_ERROR`:** Invalid input parameters (lat, lng, days, start)
- **`RATE_LIMIT_EXCEEDED`:** IP-based rate limit exceeded
- **`INTERNAL_ERROR`:** Unexpected server error (database connection, etc.)
- **`DATA_FETCH_ERROR`:** Failed to fetch weather or sun data (tides may fall back to mock)

---

## Success Response Example

See `tests/golden/forecast.sample.json` for a complete example response.

---

## Versioning

This contract is **LOCKED** for version 1.0. Breaking changes require:
1. Version bump (e.g., `/api/forecast.php?v=2`)
2. Update to this document
3. Migration guide for consumers

---

## Notes

- **Forecast-level caching:** Responses are cached for 15 minutes (configurable via `CACHE_TTL_FORECAST`)
- **Cache bypass:** Use `?refresh=true` or `?refresh=1` to force fresh data
- **Mock tides:** If WorldTides API is unavailable, mock tides are generated (indicated in response if applicable)
- **Location resolution:** If no nearby location is found within 40km, `location.name` is "Unknown Location" and `warning` field is present

