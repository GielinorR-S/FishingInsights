# Data Sources and Attribution

## Overview

All core features (weather, sun, tides) must run inside the app. No external links for data display. References page may include external citations for regulations and attribution.

## Data Providers

### 1. Weather: Open-Meteo

**Provider**: Open-Meteo (https://open-meteo.com)

**API Details**:
- **Endpoint**: `https://api.open-meteo.com/v1/forecast`
- **Authentication**: None required (free, no API key)
- **Rate Limits**: No official limit, but reasonable use expected
- **Cost**: Free

**Fields Used**:
- `latitude`, `longitude`: Location coordinates
- `daily.temperature_2m_max`: Maximum daily temperature (°C)
- `daily.temperature_2m_min`: Minimum daily temperature (°C)
- `daily.windspeed_10m_max`: Maximum wind speed (km/h)
- `daily.winddirection_10m_dominant`: Dominant wind direction (degrees)
- `daily.precipitation_sum`: Daily precipitation (mm)
- `daily.cloudcover_mean`: Mean cloud cover (%)

**Request Example**:
```
GET https://api.open-meteo.com/v1/forecast?latitude=-37.8&longitude=144.9&daily=temperature_2m_max,temperature_2m_min,windspeed_10m_max,winddirection_10m_dominant,precipitation_sum,cloudcover_mean&timezone=Australia/Melbourne
```

**Response Processing**:
- Convert wind speed from km/h to match our scoring model
- Map cloud cover percentage to conditions (clear/partly cloudy/overcast)
- Extract precipitation for scoring

**Attribution Requirements**:
- **Required**: Display "Weather data by Open-Meteo" on References page
- **Link**: https://open-meteo.com (optional, but recommended)
- **License**: Open-Meteo is free for non-commercial use (check current license)

**Usage Limits**:
- No official rate limit, but use responsibly
- Cache responses for 1 hour to minimize requests
- If rate limited, return cached data with indicator

**Error Handling**:
- If API fails, return cached data if available
- If no cache, return error with message: "Weather data temporarily unavailable"

### 2. Sunrise/Sunset: TimeAPI.io (Alternative: Open-Meteo)

**Primary Provider**: TimeAPI.io (https://timeapi.io)

**API Details**:
- **Endpoint**: `https://timeapi.io/api/Time/current/coordinate?latitude={lat}&longitude={lng}`
- **Sunrise/Sunset**: `https://timeapi.io/api/Time/sunrise-sunset?latitude={lat}&longitude={lng}&date={date}`
- **Authentication**: None required (free tier available)
- **Rate Limits**: Generous free tier (check current limits)
- **Cost**: Free (with optional paid tier)

**Alternative Provider**: Open-Meteo (if TimeAPI.io unavailable)
- Open-Meteo also provides sunrise/sunset: `daily.sunrise`, `daily.sunset`
- Use same endpoint as weather, add `daily=sunrise,sunset` parameter

**Fields Used**:
- `sunrise`: Sunrise time (ISO 8601)
- `sunset`: Sunset time (ISO 8601)
- Calculate `dawn` = sunrise - 30 minutes
- Calculate `dusk` = sunset + 30 minutes

**Request Example** (TimeAPI.io):
```
GET https://timeapi.io/api/Time/sunrise-sunset?latitude=-37.8&longitude=144.9&date=2024-01-15
```

**Request Example** (Open-Meteo fallback):
```
GET https://api.open-meteo.com/v1/forecast?latitude=-37.8&longitude=144.9&daily=sunrise,sunset&timezone=Australia/Melbourne
```

**Response Processing**:
- Parse sunrise/sunset times
- Convert to `Australia/Melbourne` timezone
- Calculate dawn (sunrise - 30 min) and dusk (sunset + 30 min)
- Return ISO 8601 timestamps with timezone offset

**Attribution Requirements**:
- **Required**: Display "Sunrise/sunset data by TimeAPI.io" (or "Open-Meteo" if using fallback) on References page
- **Link**: https://timeapi.io (optional)
- **License**: Check current license (typically free for non-commercial use)

**Usage Limits**:
- Cache responses for 7 days (604800 seconds) - sunrise/sunset change slowly
- If rate limited, return cached data

**Error Handling**:
- If primary provider fails, try Open-Meteo fallback
- If both fail, return cached data
- If no cache, return error

**DECISION**: Use Open-Meteo for sunrise/sunset to reduce API dependencies. Single provider for weather + sun simplifies implementation.

### 3. Tides: WorldTides.info

**Provider**: WorldTides.info (https://www.worldtides.info)

**API Details**:
- **Endpoint**: `https://www.worldtides.info/api`
- **Authentication**: API key required (paid, credit-based)
- **Rate Limits**: Based on credit consumption
- **Cost**: Low-cost, credit-based (target: <$5 USD/month with caching)

**Fields Used**:
- `lat`, `lon`: Location coordinates
- `days`: Number of days (1-7 for MVP)
- `key`: API key (stored in `config.local.php`)

**Request Example**:
```
GET https://www.worldtides.info/api?lat=-37.8&lon=144.9&days=7&key={API_KEY}
```

**Response Processing**:
- Parse tide events (high/low times and heights)
- Calculate tide change windows (1 hour before/after each event)
- Determine rising/falling state
- Return ISO 8601 timestamps with timezone offset

**Attribution Requirements**:
- **Required**: Display "Tide data by WorldTides.info" on References page
- **Link**: https://www.worldtides.info (optional)
- **License**: Commercial use allowed with API key

**Usage Limits**:
- Cache responses for 12 hours to minimize credit consumption
- Monitor credit usage via WorldTides dashboard
- If credits exhausted, enable mock tides mode

**Error Handling**:
- If API key missing: Enable mock tides mode (return estimated tides)
- If credits exhausted: Enable mock tides mode
- If API fails: Return cached data if available
- Mock tides mode: Return estimated tide events based on location and date (documented in response)

**Mock Tides Mode**:
- Generate estimated high/low tides based on:
  - Location (coastal vs. bay)
  - Date (approximate 12.5-hour cycle)
  - Estimated amplitude (1.0-2.0m for Victorian locations)
- Clearly indicate in response: `"mock_tides": true`
- Frontend displays: "Estimated tide data" indicator

### 4. Victorian Fisheries Data (Curated)

**Source**: Manual curation (not API)

**Data**:
- Location list (20-40 Victorian locations)
- Species rules (seasonality, preferred conditions, gear)
- Fishing regulations (links to official sources)

**Attribution**:
- Display "Location and species data curated from Victorian Fisheries Authority resources"
- Link to official regulations: https://vfa.vic.gov.au (external link, References page only)

**Usage**:
- Stored in SQLite database (`locations`, `species_rules` tables)
- No external API calls required
- Updated manually as needed

## In-App References Page Specification

**Purpose**: Display data sources, attribution, regulations, disclaimers.

**Content Sections**:

### 1. Data Sources
```
Weather Data
Provided by Open-Meteo
https://open-meteo.com

Sunrise/Sunset Data
Provided by Open-Meteo
https://open-meteo.com

Tide Data
Provided by WorldTides.info
https://www.worldtides.info
(Using estimated data if API unavailable)
```

### 2. Fishing Regulations
```
Important: Always check current fishing regulations before fishing.

Victorian Fisheries Authority
Official regulations and bag limits
https://vfa.vic.gov.au
[External Link]

Fishing Licenses
Required for most fishing in Victoria
https://vfa.vic.gov.au/recreational-fishing/fishing-licence
[External Link]
```

### 3. Safety Disclaimer
```
Safety Notice
- Check weather conditions before heading out
- Inform someone of your plans
- Carry safety equipment
- Be aware of local hazards
- This app provides informational forecasts only, not safety advice
```

### 4. App Information
```
FishingInsights v1.0
Last data update: [timestamp from cache]
Built for Victorian anglers
```

**Design**:
- Scrollable page
- External links clearly marked (open in new tab)
- Attribution in small text at bottom
- No external links for core features (weather/sun/tides data displayed in-app)

## Core Features: No External Links

**Requirement**: All core forecast features (weather, sun, tides) must display data within the app. No "View on [provider website]" links in forecast screens.

**Allowed External Links**:
- References page: Regulations, attribution links
- Disclaimer: Official fisheries authority links

**Implementation**:
- All data fetched via backend API
- Data displayed in React components
- No `<a>` tags linking to external weather/tide sites in forecast views
- References page is exception (informational only)

## Open Questions

- Should we support multiple tide providers as fallback? **DECISION: No for MVP. Use WorldTides with mock mode fallback. Can add providers later.**
- Should we cache attribution text in database? **DECISION: No, hardcode in References page component. Attribution is static.**
- Should we track API usage/credits in app? **DECISION: No for MVP. Monitor via external dashboards. Can add usage tracking later.**

