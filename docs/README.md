# FishingInsights Documentation

Complete documentation set for FishingInsights MVP implementation.

## Documentation Structure

This documentation is organized into two categories:

### 📋 **Specification Documents** (`/docs/spec/`)

**These documents are AUTHORITATIVE and define the implementation requirements.**

These documents contain the official specifications, requirements, and locked decisions that must be followed during implementation. They are the source of truth for:
- Product requirements and acceptance criteria
- Architecture and API contracts
- Database schema
- Scoring algorithms
- Deployment procedures
- Risk mitigations

**Files:**
- `REQUIREMENTS.md` - Product overview, user personas, screens, acceptance criteria
- `ARCHITECTURE.md` - Monorepo structure, API endpoints, JSON contracts, security model
- `DATABASE-SCHEMA.md` - SQLite table definitions, indexes, seed data
- `SCORING-MODEL.md` - Score calculation (0-100), weights, reasons structure
- `DATA-SOURCES-AND-ATTRIBUTION.md` - API providers, fields used, attribution requirements
- `DEPLOYMENT.md` - cPanel deployment steps, rollback plan, API key rotation
- `RISK-REGISTER.md` - Top risks, mitigations, likelihood and impact
- `DECISIONS-AND-MILESTONES.md` - Consolidated decisions, Day 1-7 checklist

**⚠️ IMPORTANT:** Specification documents take precedence over analysis documents. If there is a conflict, the specification is authoritative.

### 📊 **Analysis Documents** (`/docs/analysis/`)

**These documents are ANALYSIS-ONLY and provide insights about the current implementation.**

These documents contain analysis, reports, and findings about the codebase. They are useful for:
- Understanding current implementation state
- Identifying performance issues
- Assessing risks in existing code
- Planning improvements

**Files:**
- `CODEBASE-SCAN-REPORT.md` - PHP version assumptions, date/timezone handling, cache logic, API responses
- `RISK-ASSESSMENT-REPORT.md` - High-risk vs safe-to-change files, implicit contracts
- `PERFORMANCE-AND-SAFETY-ANALYSIS.md` - Performance bottlenecks, API credit waste, error handling gaps
- `STATE-REPORT.md` - Server setup, URLs, routing, single source of truth
- `DATE-FIX-SUMMARY.md` - Summary of date range fixes (historical reference)

**⚠️ IMPORTANT:** Analysis documents describe the current state and identify issues. They do NOT override specification documents. If analysis suggests changes, those changes must be implemented and then the specification documents should be updated to reflect the new state.

## Quick Start

1. **New to the project?** Start with:
   - `spec/DECISIONS-AND-MILESTONES.md` - Overview and implementation checklist
   - `spec/REQUIREMENTS.md` - Product requirements and user stories
   - `spec/ARCHITECTURE.md` - Technical architecture and API contracts

2. **Implementing features?** Reference:
   - `spec/ARCHITECTURE.md` - API endpoints and JSON contracts
   - `spec/DATABASE-SCHEMA.md` - Database structure
   - `spec/SCORING-MODEL.md` - Scoring algorithms

3. **Deploying?** Follow:
   - `spec/DEPLOYMENT.md` - Step-by-step deployment guide

4. **Troubleshooting?** Check:
   - `analysis/STATE-REPORT.md` - Current server setup and URLs
   - `analysis/PERFORMANCE-AND-SAFETY-ANALYSIS.md` - Known issues and bottlenecks
   - `analysis/RISK-ASSESSMENT-REPORT.md` - High-risk files and implicit contracts

## Key Constraints (Locked)

These constraints are defined in the specification documents and must be followed:

- **PHP 7.3.33 ONLY** - No PHP 7.4+ syntax
- **All core features in-app** - No external links for weather/sun/tides
- **Primary endpoint**: `/api/forecast.php`
- **Timezone**: `Australia/Melbourne` with ISO 8601 timestamps
- **Cache TTLs**: Weather (1h), Sun (7d), Tides (12h)
- **Tide change windows**: +/- 1 hour around each high/low tide event
- **Cache key format**: `{lat}:{lng}:{start_date}:{days}` (provider separate)

## Documentation Maintenance

- **Specification documents** should be updated when requirements or architecture change
- **Analysis documents** should be updated when codebase changes affect the analysis
- **Never modify specification documents** based solely on analysis findings without proper review
- **Always update specifications** after implementing changes that affect requirements or architecture

## Questions?

If you find inconsistencies between documents:
1. **Spec vs Spec conflict**: Review `spec/DECISIONS-AND-MILESTONES.md` for the canonical decision
2. **Analysis vs Spec conflict**: Specification is authoritative, analysis may be outdated
3. **Analysis vs Analysis conflict**: Both may be valid for different aspects, review both
