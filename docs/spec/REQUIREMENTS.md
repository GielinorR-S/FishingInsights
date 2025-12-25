# Requirements Specification

## Product Overview

FishingInsights is a mobile-first Progressive Web App (PWA) that provides personalized fishing forecasts for Victorian anglers. The app combines weather conditions, tide data, sunrise/sunset times, and species-specific knowledge to generate daily fishing scores (0-100) and actionable recommendations.

**Core Value Proposition**: Eliminate guesswork by providing data-driven fishing recommendations that help anglers plan trips, choose locations, and select appropriate gear—all without leaving the app.

**MVP Scope**: Victoria-first with 20-40 curated locations, expandable to Australia-wide without architectural refactor.

## User Personas

### Primary Persona: "Weekend Warrior" (Mike, 35)

- **Profile**: Recreational angler, fishes 2-4 times per month, primarily weekends
- **Goals**: Maximize catch rate, avoid wasted trips, learn new spots
- **Pain Points**: Unpredictable conditions, lack of local knowledge, time constraints
- **Tech Comfort**: Moderate (uses smartphone apps regularly)
- **Key Needs**: Quick forecasts, location recommendations, gear suggestions

### Secondary Persona: "Experienced Local" (Sarah, 48)

- **Profile**: Seasoned angler, knows many spots, fishes weekly
- **Goals**: Optimize timing, target specific species, share knowledge
- **Pain Points**: Weather/tide data scattered across multiple sources
- **Tech Comfort**: High
- **Key Needs**: Detailed tide windows, species-specific insights, historical patterns

### Tertiary Persona: "Casual Explorer" (James, 28)

- **Profile**: New to fishing or new to Victoria, fishes occasionally
- **Goals**: Discover good spots, learn basics, avoid bad conditions
- **Pain Points**: Overwhelmed by options, doesn't know where to start
- **Tech Comfort**: High
- **Key Needs**: Simple scores, clear explanations, location discovery

## Screens/Pages List

### 1. Home Screen

**Purpose**: Quick overview and access to favourites

**Acceptance Criteria**:

- [ ] Displays "Today's Best" location (highest score for current day)
- [ ] Shows 3-5 favourite locations as cards (if any saved)
- [ ] "Browse Locations" button/CTA
- [ ] "Use My Location" quick action (if geolocation permission granted)
- [ ] Offline indicator when no network (shows last cached data if available)
- [ ] Loading state during initial data fetch
- [ ] Empty state if no favourites: "Add locations to favourites to see them here"

**Layout**:

- Header: App title/logo
- Hero section: "Today's Best" card (location name, score, brief reason)
- Favourites section: Horizontal scrollable cards or grid (max 2 columns on mobile)
- Footer: Navigation to other sections

### 2. Location Picker Screen

**Purpose**: Search, browse, and select fishing locations

**Acceptance Criteria**:

- [ ] Search bar (filters by location name as user types)
- [ ] List/grid of all available locations (20-40 in MVP)
- [ ] Each location card shows: name, region, current score (if available)
- [ ] Favourite toggle (heart icon) on each card
- [ ] "Use My Location" button (requests geolocation, finds nearest location)
- [ ] Geolocation error handling (permission denied, timeout, unsupported)
- [ ] Selected location navigates to Forecast screen
- [ ] Favourites persist across sessions (localStorage)
- [ ] Search is case-insensitive and matches partial names

**Layout**:

- Header: "Select Location" with back button
- Search bar (sticky on scroll)
- Location list (virtualized if >30 items for performance)
- Floating action button: "Use My Location" (if geolocation available)

### 3. Forecast Screen

**Purpose**: 7-day forecast for selected location with detailed daily breakdowns

**Acceptance Criteria**:

- [ ] Location name and region displayed at top
- [ ] 7-day forecast cards (horizontal scroll or vertical stack)
- [ ] Each day card shows: date, score (0-100), best bite windows (time ranges), target species (icons/names)
- [ ] Tap day card to expand details (or navigate to Day Detail view)
- [ ] Day Detail view shows:
  - Full score breakdown with reasons
  - Weather summary (temp, wind, conditions)
  - Tide chart/timeline (high/low times, rising/falling indicators)
  - Sunrise/sunset times
  - Recommended species with brief "why" explanations
  - Gear suggestions (bait/lure, line weight, leader, rig hints)
  - "Why this score" expandable section with structured reasons
- [ ] Loading state while fetching forecast
- [ ] Error state if API fails (with retry button)
- [ ] Cached data indicator ("Last updated: X minutes ago")
- [ ] Pull-to-refresh (mobile)
- [ ] Back button returns to previous screen

**Layout**:

- Header: Location name, favourite toggle, back button
- Score summary bar (today's score prominently displayed)
- 7-day cards (swipeable on mobile)
- Day detail: Full-screen or modal overlay with scrollable content

### 4. Species Screen (Optional MVP Enhancement)

**Purpose**: Detailed species information and why it's recommended

**Acceptance Criteria**:

- [ ] Accessible from Forecast screen (tap species name/icon)
- [ ] Species name, common names, image (if available)
- [ ] Seasonality chart/calendar (when best to target)
- [ ] Preferred conditions (water temp, weather, tide state)
- [ ] Gear recommendations specific to species
- [ ] "Why recommended now" explanation based on current forecast
- [ ] Back navigation

**Layout**:

- Header: Species name, back button
- Image/illustration
- Information sections (scrollable)
- Recommendation rationale

### 5. References Screen

**Purpose**: Data sources, attribution, fishing regulations, disclaimers

**Acceptance Criteria**:

- [ ] Data sources section:
  - Weather: Open-Meteo attribution
  - Sunrise/Sunset: Provider attribution
  - Tides: WorldTides attribution
  - Victorian fisheries data sources (if applicable)
- [ ] Fishing regulations link (external, to official Victorian Fisheries Authority)
- [ ] Safety disclaimer (informational only, not legal advice)
- [ ] App version/build info
- [ ] "Last data update" timestamp
- [ ] No external links for core features (tides/weather/sun) - all in-app
- [ ] References page may include external citations/links for regulations only

**Layout**:

- Header: "References & Info"
- Sections: Data Sources, Regulations, Safety, About
- Scrollable content

### 6. Disclaimer Screen (or modal)

**Purpose**: Legal and safety disclaimers

**Acceptance Criteria**:

- [ ] Displayed on first launch (one-time, dismissible)
- [ ] Accessible from References screen
- [ ] States: "Informational only, not legal advice"
- [ ] Safety warnings (weather conditions, water safety)
- [ ] Fishing regulations reminder (check local rules)
- [ ] "I understand" acknowledgment (stores in localStorage)

**Layout**:

- Modal overlay or dedicated screen
- Scrollable disclaimer text
- Acknowledge button

## User Stories

### Core Functionality

1. **As an angler**, I want to see today's best fishing location so I can plan my trip quickly.
2. **As an angler**, I want to search for a specific location so I can check conditions there.
3. **As an angler**, I want to see a 7-day forecast so I can plan ahead.
4. **As an angler**, I want to know the best bite times so I can maximize my chances.
5. **As an angler**, I want gear recommendations so I know what to bring.
6. **As an angler**, I want to save favourite locations so I can access them quickly.
7. **As an angler**, I want to use my current location so I don't have to search manually.

### Edge Cases & Error Handling

8. **As an angler**, if geolocation is denied, I still want to use the app via search.
9. **As an angler**, if the API is down, I want to see cached data with a clear indicator.
10. **As an angler**, if I'm offline, I want to see the last cached forecast.
11. **As an angler**, if a location has no data, I want a clear message explaining why.
12. **As an angler**, if cache is stale (>24 hours), I want to know the data may be outdated.
13. **As an angler**, if my device doesn't support geolocation, I can still use search.
14. **As an angler**, if the tides API fails, I want a fallback mode (mock tides or graceful degradation).

## Non-Negotiable Requirements

### Core Features Must Run In-App

- **NO external links for tides, weather, or sunrise/sunset data** in core forecast functionality
- All data must be fetched via backend API and displayed in-app
- References page may include external links for regulations/attribution only
- PWA must function as standalone app (installable, offline-capable shell)

### Data Requirements

- Weather: Free API (Open-Meteo, no key required)
- Sunrise/Sunset: Free or minimal-friction provider (prefer no key)
- Tides: Low-cost paid API (WorldTides, budget <$5 USD/month with caching)
- All API responses cached in SQLite to minimize external calls
- Cache TTL strategy (LOCKED): Weather (1 hour), Sun (7 days), Tides (12 hours)

### Performance Targets

- **First Load**: <3 seconds on 3G connection (target: <2s on 4G)
- **Time to Interactive**: <4 seconds
- **Cached Response**: <500ms (forecast from cache)
- **API Response**: <2 seconds (aggregated forecast endpoint)
- **PWA Install Prompt**: Appears after 2nd visit (standard PWA criteria)
- **Offline Shell**: Loads instantly, shows last cached data

### Accessibility Basics

- **Tap Targets**: Minimum 44x44px (iOS) / 48x48dp (Android)
- **Color Contrast**: WCAG AA minimum (4.5:1 for normal text, 3:1 for large text)
- **Keyboard Navigation**: All interactive elements focusable and operable via keyboard (desktop)
- **Screen Reader**: Semantic HTML, ARIA labels for icons/buttons
- **Text Scaling**: Supports up to 200% zoom without horizontal scrolling
- **Focus Indicators**: Visible focus rings on all interactive elements

## Technical Constraints

- **PHP Version**: 7.3.33 ONLY (no 7.4+ features)
- **Database**: SQLite via PDO
- **Hosting**: Shared cPanel PHP hosting
- **Frontend**: React + TypeScript + Vite, TailwindCSS
- **PWA**: Installable, offline-capable, service worker required

## Success Metrics (MVP)

- App loads and displays forecast for any location
- Favourites persist across sessions
- Offline mode shows cached data
- All core features work without external navigation
- Health check endpoint returns all green
- No PHP 7.4+ syntax errors
- PWA installable on Chrome/Edge desktop and mobile

## Open Questions

- Should species screen be in MVP or deferred? **DECISION: Include as optional enhancement, accessible from forecast but not required for core flow.**
- Should we support multiple favourite lists (e.g., "Summer Spots", "Winter Spots")? **DECISION: MVP = single favourites list, expandable later.**
- How to handle locations outside Victoria in MVP? **DECISION: Show "Coming soon" or gracefully degrade (no data) with message.**
- Should we include historical data/comparisons? **DECISION: No, MVP focuses on forward-looking forecasts only.**
