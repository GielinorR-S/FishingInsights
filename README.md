# FishingInsights

A mobile-first Progressive Web App providing fishing forecasts for Victorian anglers.

## Tech Stack

- **Frontend**: React + TypeScript + Vite + TailwindCSS + PWA
- **Backend**: PHP 7.3.33 (plain PHP, no frameworks)
- **Database**: SQLite via PDO

## Project Structure

```
FishingInsights/
├── app/          # React frontend
├── api/          # PHP backend
├── data/         # Database and seed data
└── docs/         # Documentation
```

## Setup

### Frontend

```bash
cd app
npm install
npm run dev
```

### Backend

1. Copy `api/config.example.php` to `api/config.local.php`
2. Update `DB_PATH` in `config.local.php`
3. Set `WORLDTIDES_API_KEY` if available (optional - app works with mock mode)
4. Ensure PHP 7.3.33 with PDO SQLite extension

### Database

The database schema is created automatically on first run via `api/health.php`.

To seed initial data (DEV_MODE only):
1. Set `DEV_MODE = true` in `config.local.php`
2. Visit `/api/seed.php` or run via CLI

## Local Development (Windows)

### Quick Start

**Terminal 1 - Start PHP API Server (from repo root):**
```bash
php -S 127.0.0.1:8001 -t .
```

**Terminal 2 - Start Vite Dev Server:**
```bash
cd app
npm install
npm run dev
```

The frontend will be available at `http://localhost:3000` and will proxy API requests to the PHP server.

### Test URLs

Once both servers are running, test the backend:

- **Health Check**: http://127.0.0.1:8001/api/health.php
- **Seed Database** (DEV_MODE only): http://127.0.0.1:8001/api/seed.php
- **Forecast Endpoint**: http://127.0.0.1:8001/api/forecast.php?lat=-37.8&lng=144.9&days=7

### Backend Smoke Test

Run the smoke test to verify the backend is working:

```bash
php scripts/smoke_test.php
```

This will test:
- Health endpoint returns `status: ok`
- PDO SQLite extension is available
- Forecast endpoint returns 7 days of data

## API Endpoints

- `GET /api/health.php` - Health check
- `GET /api/forecast.php?lat=&lng=&days=7` - **PRIMARY** forecast endpoint
- `GET /api/weather.php` - Weather data (debugging)
- `GET /api/sun.php` - Sunrise/sunset (debugging)
- `GET /api/tides.php` - Tides data (debugging)

## Development

See `/docs` for complete documentation including:
- Architecture specification
- API contracts
- Scoring model
- Deployment guide

## License

MIT

