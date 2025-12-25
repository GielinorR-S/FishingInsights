# FishingInsights Documentation

Complete documentation set for FishingInsights MVP implementation.

## Documentation Index

### 1. [Requirements Specification](./requirements.md)
- Product overview and user personas
- Screen/page specifications with acceptance criteria
- User stories and edge cases
- Performance targets and accessibility requirements

### 2. [Architecture Specification](./architecture.md)
- Monorepo structure
- Frontend and backend architecture
- API endpoint specifications with JSON contracts
- Security model
- Hosting constraints and deployment plan
- **Health check endpoint specification**

### 3. [Database Schema](./database-schema.md)
- SQLite table definitions
- Indexes and rationale
- Database file location strategy
- Seed data and migration approach

### 4. [Scoring Model](./scoring-model.md)
- Score calculation (0-100) with weights
- Component breakdown (weather, tides, dawn/dusk, seasonality)
- Reasons structure
- Best bite windows algorithm
- Species and gear recommendations

### 5. [Data Sources and Attribution](./data-sources-and-attribution.md)
- API provider details (Open-Meteo, WorldTides)
- Fields used and request/response formats
- Attribution requirements
- References page specification

### 6. [Deployment Guide](./deployment.md)
- Step-by-step cPanel deployment
- Database setup and permissions
- Configuration files
- Rollback plan
- API key rotation

### 7. [Risk Register](./risk-register.md)
- Top risks and mitigations
- Likelihood and impact assessment
- Fallback strategies

### 8. [Decisions Locked & Milestones](./DECISIONS-AND-MILESTONES.md)
- Consolidated decisions list
- Day 1-7 implementation checklist
- Immediate next actions

## Quick Start

1. **Read First**: [Decisions Locked & Milestones](./DECISIONS-AND-MILESTONES.md) for overview
2. **Architecture**: Review [Architecture Specification](./architecture.md) for technical details
3. **Implementation**: Follow Day 1-7 checklist in [Decisions Locked & Milestones](./DECISIONS-AND-MILESTONES.md)

## Key Constraints

- **PHP 7.3.33 ONLY** - No PHP 7.4+ syntax
- **All core features in-app** - No external links for weather/sun/tides
- **Primary endpoint**: `/api/forecast.php`
- **Timezone**: `Australia/Melbourne` with ISO 8601 timestamps
- **Cache TTLs**: Weather (1h), Sun (7d), Tides (12h)

## Documentation Quality

- All ambiguity removed
- Decisions locked and documented
- Open questions resolved (or default decisions provided)
- Ready for implementation without blockers

