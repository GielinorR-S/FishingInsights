# Scoring Model Specification

## Overview

The fishing score is a composite metric (0-100) that combines multiple factors to predict fishing conditions. Higher scores indicate better fishing conditions.

**Score Formula**:
```
score = (weather_score × weather_weight) + 
        (tide_score × tide_weight) + 
        (dawn_dusk_score × dawn_dusk_weight) + 
        (seasonality_score × seasonality_weight)
```

All weights sum to 1.0 (100%).

## Score Components

### 1. Weather Suitability (Weight: 0.35)

**Range**: 0-100 points

**Factors**:
- Wind speed (primary)
- Precipitation
- Cloud cover
- Temperature (secondary, for comfort)

**Scoring Logic**:

```php
// Wind speed (0-50 points)
if (wind_speed <= 10) {
    wind_points = 50;
} elseif (wind_speed <= 20) {
    wind_points = 40 - ((wind_speed - 10) * 1); // Linear: 40 to 30
} elseif (wind_speed <= 30) {
    wind_points = 30 - ((wind_speed - 20) * 1.5); // Linear: 30 to 15
} else {
    wind_points = max(0, 15 - ((wind_speed - 30) * 0.5)); // Linear: 15 to 0
}

// Precipitation (0-30 points)
if (precipitation == 0) {
    precip_points = 30;
} elseif (precipitation <= 2) {
    precip_points = 25; // Light rain acceptable
} elseif (precipitation <= 5) {
    precip_points = 15; // Moderate rain
} else {
    precip_points = 5; // Heavy rain
}

// Cloud cover (0-20 points)
if (cloud_cover <= 30) {
    cloud_points = 20; // Clear/partly cloudy
} elseif (cloud_cover <= 60) {
    cloud_points = 15; // Partly cloudy
} elseif (cloud_cover <= 80) {
    cloud_points = 10; // Mostly cloudy
} else {
    cloud_points = 5; // Overcast
}

weather_score = wind_points + precip_points + cloud_points;
```

**Justification**: Wind is the primary factor affecting fishing (safety and casting). Light winds (<10 km/h) are ideal. Precipitation and cloud cover are secondary but affect visibility and comfort.

### 2. Tide Quality (Weight: 0.30)

**Range**: 0-100 points

**Factors**:
- Tide change windows (rising/falling transitions)
- Number of tide changes per day
- Tide height range (amplitude)

**Scoring Logic**:

```php
// Base score from tide change frequency (0-60 points)
tide_changes_per_day = count(tide_events) / 2; // High + Low = 1 change cycle
if (tide_changes_per_day >= 2) {
    change_frequency_points = 60; // 2+ changes ideal
} elseif (tide_changes_per_day == 1) {
    change_frequency_points = 40; // 1 change acceptable
} else {
    change_frequency_points = 20; // Few changes
}

// Tide amplitude bonus (0-40 points)
tide_range = max(tide_heights) - min(tide_heights);
if (tide_range >= 1.5) {
    amplitude_points = 40; // Large range = more movement
} elseif (tide_range >= 1.0) {
    amplitude_points = 30;
} elseif (tide_range >= 0.5) {
    amplitude_points = 20;
} else {
    amplitude_points = 10; // Small range
}

tide_score = change_frequency_points + amplitude_points;
```

**Tide Change Windows**:
- **Canonical Rule**: Compute a window +/- 1 hour around EACH high/low tide event
- For each tide event (high or low):
  - Window start: 1 hour before event time
  - Window end: 1 hour after event time
  - Type: "rising" if event is low tide, "falling" if event is high tide
- Example: If low tide is at 02:30, window is 01:30-03:30 (type: "rising")
- Example: If high tide is at 08:45, window is 07:45-09:45 (type: "falling")
- Do NOT describe multi-hour spans (low->high or high->low) as a single "window"

**Justification**: Fish are more active during tide changes. More frequent changes and larger amplitude indicate better fishing conditions.

### 3. Dawn/Dusk Overlap (Weight: 0.20)

**Range**: 0-100 points

**Factors**:
- Overlap of dawn/dusk windows with tide change windows
- Duration of overlap

**Scoring Logic**:

```php
// Dawn window: 30 minutes before sunrise to 2 hours after sunrise
dawn_start = sunrise - 30 minutes;
dawn_end = sunrise + 2 hours;

// Dusk window: 2 hours before sunset to 30 minutes after sunset
dusk_start = sunset - 2 hours;
dusk_end = sunset + 30 minutes;

// Calculate overlap with tide change windows
overlap_minutes = 0;
foreach (tide_change_windows as window) {
    overlap_minutes += calculate_overlap(window, dawn_window);
    overlap_minutes += calculate_overlap(window, dusk_window);
}

// Score based on total overlap (0-100 points)
if (overlap_minutes >= 120) {
    dawn_dusk_score = 100; // 2+ hours overlap
} elseif (overlap_minutes >= 60) {
    dawn_dusk_score = 80; // 1-2 hours
} elseif (overlap_minutes >= 30) {
    dawn_dusk_score = 60; // 30-60 minutes
} elseif (overlap_minutes >= 15) {
    dawn_dusk_score = 40; // 15-30 minutes
} else {
    dawn_dusk_score = 20; // <15 minutes or no overlap
}
```

**Justification**: Fish are most active during dawn and dusk. When these periods overlap with tide changes, conditions are optimal.

### 4. Seasonality/Species Suitability (Weight: 0.15)

**Range**: 0-100 points

**Factors**:
- Current month vs. species season
- Number of species in season
- Species confidence scores

**Scoring Logic**:

```php
current_month = date('n'); // 1-12

// Find species in season
species_in_season = [];
foreach (all_species as species) {
    if (is_in_season(species, current_month)) {
        species_in_season[] = species;
    }
}

// Score based on number of species in season (0-60 points)
species_count = count(species_in_season);
if (species_count >= 5) {
    species_points = 60;
} elseif (species_count >= 3) {
    species_points = 45;
} elseif (species_count >= 2) {
    species_points = 30;
} elseif (species_count >= 1) {
    species_points = 20;
} else {
    species_points = 10; // Off-season
}

// Bonus for high-confidence species matches (0-40 points)
confidence_bonus = 0;
foreach (species_in_season as species) {
    if (species.confidence >= 0.8) {
        confidence_bonus += 10; // Max 40 points
    }
}
confidence_bonus = min(40, confidence_bonus);

seasonality_score = species_points + confidence_bonus;
```

**Justification**: Seasonality ensures recommendations are relevant. More species in season = more fishing opportunities. High-confidence matches indicate ideal conditions for specific species.

## Weight Justification

**Total Weights**: 0.35 + 0.30 + 0.20 + 0.15 = 1.0

- **Weather (35%)**: Most critical for safety and casting ability. Bad weather can make fishing impossible.
- **Tides (30%)**: Critical for fish activity. Tide changes drive feeding behavior.
- **Dawn/Dusk (20%)**: Important but secondary to tides. Fish are naturally more active during these times.
- **Seasonality (15%)**: Ensures relevance but less critical than conditions. Can fish year-round, but some species are better in season.

**Rationale**: Weather and tides are the most actionable factors. Dawn/dusk and seasonality enhance but don't override poor conditions.

## Reasons Structure

Each score includes a list of "reasons" that explain the score calculation.

**Schema**:
```json
{
  "title": "Short reason title",
  "detail": "Detailed explanation of why this factor contributes to the score",
  "contribution_points": 25,
  "severity": "positive|negative|neutral",
  "category": "weather|tide|dawn_dusk|seasonality"
}
```

**Example Reasons**:

```json
[
  {
    "title": "Excellent weather conditions",
    "detail": "Light winds (8 km/h), no precipitation, clear skies (20% cloud cover)",
    "contribution_points": 30,
    "severity": "positive",
    "category": "weather"
  },
  {
    "title": "Strong tide activity",
    "detail": "4 tide changes today with 1.8m range, providing optimal feeding windows",
    "contribution_points": 28,
    "severity": "positive",
    "category": "tide"
  },
  {
    "title": "Dawn-tide overlap",
    "detail": "Dawn window (05:45-08:15) overlaps with rising tide change (06:00-07:00) for 60 minutes",
    "contribution_points": 18,
    "severity": "positive",
    "category": "dawn_dusk"
  },
  {
    "title": "Peak season for Snapper",
    "detail": "Snapper is in peak season (Nov-Mar) with high confidence match (0.85)",
    "contribution_points": 12,
    "severity": "positive",
    "category": "seasonality"
  }
]
```

**Negative Reasons** (reduce score):
```json
{
  "title": "Strong winds expected",
  "detail": "Wind speeds of 35 km/h will make casting difficult and conditions unsafe",
  "contribution_points": -15,
  "severity": "negative",
  "category": "weather"
}
```

## Best Bite Windows

**Definition**: Time windows when fishing conditions are optimal (combining dawn/dusk with tide changes).

**Algorithm**:
1. For each tide event (high/low), compute change window: +/- 1 hour around event time
2. Identify dawn window (30 min before sunrise to 2 hours after)
3. Identify dusk window (2 hours before sunset to 30 min after)
4. Find overlaps between each tide change window and dawn/dusk windows
5. Merge overlapping windows if within 30 minutes
6. Sort by start time

**Output Format**:
```json
{
  "start": "2024-01-15T06:15:00+11:00",
  "end": "2024-01-15T08:00:00+11:00",
  "reason": "dawn + rising tide",
  "quality": "excellent|good|fair"
}
```

**Quality Levels**:
- **Excellent**: Overlap of 60+ minutes
- **Good**: Overlap of 30-60 minutes
- **Fair**: Overlap of 15-30 minutes

## Species Recommendations

**Algorithm**:
1. Filter species by season (current month in season range)
2. Score each species based on:
   - Weather match (wind, conditions)
   - Tide state preference (rising/falling/any)
   - Water temperature (if available)
3. Sort by confidence score (0.0-1.0)
4. Return top 3-5 species

**Output Format**:
```json
{
  "id": "snapper",
  "name": "Snapper",
  "confidence": 0.85,
  "reason": "Peak season, ideal weather conditions, rising tide preference matches"
}
```

**Confidence Calculation**:
```php
confidence = (season_match * 0.4) + 
             (weather_match * 0.3) + 
             (tide_match * 0.2) + 
             (temp_match * 0.1);
```

## Gear Suggestions

**Scope (MVP-Safe)**: Simple, confident recommendations only.

**Rules**:
1. **Bait**: From species rules, filtered by season/conditions
2. **Lure**: From species rules, filtered by conditions (wind affects lure choice)
3. **Line Weight**: From species rules, with safety margin
4. **Leader**: From species rules, typically 20-30% stronger than main line
5. **Rig**: From species rules, basic rigs only (paternoster, running sinker, float)

**Output Format**:
```json
{
  "bait": ["pilchards", "squid", "garfish"],
  "lure": ["soft plastics", "metal lures"],
  "line_weight": "8-15lb",
  "leader": "10-20lb",
  "rig": "paternoster or running sinker"
}
```

**MVP Species List (Victoria)**:
1. Snapper (Pagrus auratus)
2. Black Bream (Acanthopagrus butcheri)
3. Flathead (Platycephalus spp.)
4. King George Whiting (Sillaginodes punctatus)
5. Australian Salmon (Arripis trutta)
6. Trevally (Pseudocaranx spp.)
7. Mulloway (Argyrosomus japonicus)
8. Gummy Shark (Mustelus antarcticus)
9. Pink Snapper (Chrysophrys auratus)
10. Leatherjacket (Monacanthidae)

## Open Questions

- Should we include moon phase in scoring? **DECISION: No for MVP. Can be added later as enhancement.**
- Should we weight recent weather trends (improving vs. deteriorating)? **DECISION: No for MVP. Focus on forecast only.**
- Should we adjust scores based on location type (beach vs. bay vs. river)? **DECISION: No for MVP. Use same scoring model for all locations. Location-specific rules can be added later.**
- Should we include barometric pressure? **DECISION: No for MVP. Weather API may not provide it. Can be added if data available.**

