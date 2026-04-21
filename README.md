# Traffic Distribution System (TDS)

Traffic Distribution System is a full-stack platform for campaign routing, traffic distribution, conversion tracking, and KPI reporting.
This repository is a monorepo with a Laravel API backend and a Next.js admin frontend, designed for local development with Docker Compose.

## Table of Contents

- [Tech Stack](#tech-stack)
- [Repository Structure](#repository-structure)
- [Core Capabilities](#core-capabilities)
- [Quick Start (Docker)](#quick-start-docker)
- [Environment Variables](#environment-variables)
- [Local Development Workflows](#local-development-workflows)
- [Testing and Quality Checks](#testing-and-quality-checks)
- [Useful API Endpoints](#useful-api-endpoints)
- [Troubleshooting](#troubleshooting)
- [Documentation](#documentation)

## Tech Stack

- **Frontend:** Next.js 15, React 18, TypeScript, Tailwind CSS
- **Backend:** Laravel 12, PHP 8.3
- **Data Stores:** MySQL 8.4, Redis 7
- **Runtime Infrastructure:** Docker Compose, Nginx
- **Test Tooling:** PHPUnit, Vitest, Playwright, Node test runner

## Repository Structure

```text
.
|-- backend/      # Laravel API, queue worker/scheduler logic, tests
|-- frontend/     # Next.js admin UI, component/integration tests
|-- infra/        # Nginx, frontend dev image (`docker/`), local infra
|-- docs/         # API and architecture notes
|-- scripts/      # Optional local setup helpers (e.g. frontend npm install)
|-- docker-compose.yml
`-- README.md
```

## Core Capabilities

- Domain, campaign, lander, and offer management via internal API endpoints
- Dynamic user authentication with login/registration and personal access tokens
- Campaign lifecycle controls (`activate`, `pause`, `archive`)
- Public redirect and click tracking endpoints
- Tracking script/event ingestion
- Manual conversion ingestion
- KPI and A/B testing reports
- Shopify webhook ingestion
- Background processing with dedicated worker and scheduler containers

## Quick Start (Docker)

### Prerequisites

- Docker Engine
- Docker Compose plugin (`docker compose`)

### 1) Configure environment files

From repository root:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

Set `DOCKER_UID` and `DOCKER_GID` in the root `.env` to `id -u` and `id -g` so processes inside the frontend container match your host user. The frontend image entrypoint fixes ownership on `frontend/node_modules` and `frontend/.next` at start, then drops privileges to those IDs.

### 2) Start the full stack

```bash
docker compose up --build
```

The `frontend` service runs `npm install` before `next dev`; packages land in **`frontend/node_modules` on your host** (bind mount), so you can use your IDE and run **`npm run dev` in `frontend/`** without a second install, as long as the stack has completed that install at least once.

If you need **`node_modules` before** the first successful `frontend` container start, run (uses Docker; no host Node required):

```bash
./scripts/install-frontend-deps.sh
```

Rebuild backend images first, then install frontend deps:

```bash
./scripts/install-frontend-deps.sh --rebuild
```

**Docker Desktop (Mac / Windows):** the container is Linux. If you rely on **native** npm modules, prefer running Next **inside** the `frontend` container, or run `npm install` **on the host** (not only in Docker) when using host `npm run dev`, so binaries match your OS.

Services started:

- `nginx` on `http://localhost`
- `frontend` (Next.js dev server behind nginx)
- `backend` (Laravel app on internal port `8000`)
- `worker` (queue worker)
- `scheduler` (Laravel scheduler loop)
- `db` (MySQL, exposed as `localhost:3307`)
- `redis` (Redis, exposed as `localhost:6379`)

On each **`backend` container start**, the stack runs **`php artisan migrate --force`** after `composer install` and before `php artisan serve`, so a fresh MySQL volume gets schema without a manual step. This is intentional for **local Compose only**; production deploys should run migrations in a controlled release step, not on every app boot.

Optional: run migrations again or interactively after code changes:

```bash
docker compose exec backend php artisan migrate
```

Optional one-time demo data (not run automatically — avoids duplicate rows on restart):

```bash
docker compose exec backend php artisan db:seed
```

### 3) Verify the stack

Frontend:

```bash
curl -i http://localhost/
```

Health endpoint:

```bash
curl -i http://localhost/api/health
```

### 4) Authenticate in the dashboard

- Open `http://localhost/auth`
- Register a user (or log in with an existing user)
- After authentication, the frontend stores your bearer token and sends it to `/api/internal/*` automatically

## Environment Variables

### Root (`.env`)

Compose/project-level settings (container naming + database defaults).

### Backend (`backend/.env`)

Important keys:

- `APP_ENV`, `APP_DEBUG`, `APP_URL`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST`
- `AUTH_TOKEN_TTL_MINUTES` (lifetime for dynamic personal access tokens)
- `SHOPIFY_WEBHOOK_SECRET`

### Frontend (`frontend/.env`)

Important keys:

- `TDS_BACKEND_URL` (server-side URL to Laravel API)
- `NEXT_PUBLIC_TDS_PUBLIC_ORIGIN` (browser-facing origin used in tracking URL generation)

## Authentication Model

- Backend protected API endpoints under `/api/v1/*` require dynamic bearer authentication.
- Use:
  - `POST /api/v1/auth/register`
  - `POST /api/v1/auth/login`
  - `POST /api/v1/auth/logout`
  - `GET /api/v1/me`
- Frontend route `/auth` handles login/registration and stores the issued token for subsequent API calls.

## Local Development Workflows

### Run commands inside containers

Backend artisan/composer:

```bash
docker compose exec backend php artisan migrate   # after pulling new migrations (also runs on each backend start)
docker compose exec backend composer test
```

Frontend npm scripts:

```bash
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
```

Install or refresh frontend dependencies via Docker (writes into `./frontend/node_modules` on the host):

```bash
./scripts/install-frontend-deps.sh
```

Same thing without the script:

```bash
docker compose run --rm --no-deps frontend npm install
```

### Rebuild cleanly after dependency or Dockerfile changes

Use **`docker compose up --build`** (the `--build` flag applies to **`up`**, not to `docker compose` alone). Recreate containers and rebuild images defined in Compose:

```bash
docker compose down
docker compose up --build
```

## Testing and Quality Checks

### Frontend (`frontend/`)

- Type check: `npm run lint`
- Unit tests: `npm run test:unit`
- Full test suite: `npm run test`
- E2E tests: `npm run test:e2e`

### Backend (`backend/`)

- Basic lint checks: `composer lint`
- PHPUnit suite: `composer test`

### CI baseline

The CI workflow runs:

- Frontend install + lint + tests
- Backend install + lint + tests
- Migration check: `php artisan migrate --force --ansi`

## Useful API Endpoints

### Public

- `GET /api/health`
- `GET /api/campaign/{campaignSlug}`
- `GET /api/r/{campaignSlug}` (legacy alias)
- `GET /api/tracker/{campaignId}.js`
- `GET /api/click`
- `POST /api/v1/events`
- `POST /api/v1/webhooks/shopify/orders`

### Internal (`/api/v1`, protected by dynamic bearer token auth)

- `GET /me`
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET|POST|PATCH|DELETE /domains...`
- `GET|POST|PATCH /campaigns...`
- `POST /campaigns/{id}/activate|pause|archive`
- `GET|POST|PATCH|DELETE /landers...`
- `GET|POST|PATCH|DELETE /offers...`
- `GET|POST|PATCH|DELETE /campaigns/{campaignId}/targeting-rules...`
- `POST /conversions/manual`
- `GET /reports/kpi`
- `GET /reports/ab-tests`
- `GET /ops/sync-runs`

## Troubleshooting

- If **local** `npm run dev` in `frontend/` fails with **`next: not found`**, install dependencies: `./scripts/install-frontend-deps.sh`, or start the stack once so the `frontend` container runs `npm install`, or run `docker compose run --rm --no-deps frontend npm install`. Use `docker compose up --build` (not `docker compose --build`) to rebuild service images and start the stack.
- If the browser shows **nginx `502 Bad Gateway`** on `/dashboard` or `/` right after `docker compose up`, the stack was likely still booting (Next `npm install` or Laravel `composer install`). Wait until `docker compose ps` shows **healthy** for `frontend` and `backend`, then refresh; nginx is configured to start only after both pass their health checks.
- If **local** `npm run dev` in `frontend/` still hits `EACCES` under `frontend/.next`, run `sudo chown -R "$(id -u):$(id -g)" frontend/.next frontend/node_modules` once, confirm `DOCKER_UID` / `DOCKER_GID` in root `.env`, then `docker compose up --build` so the frontend entrypoint can manage permissions.
- If backend requests fail with unauthorized errors, log in via `/auth` and ensure a valid bearer token is present.
- If MySQL is unavailable, check container health: `docker compose ps`.
- If queue-dependent flows are delayed, verify `worker` container is running.
- If scheduled jobs are missing, verify `scheduler` container is running.

## Documentation

- API contract: `docs/API.md`
- Laravel routes: `backend/routes/api.php`
- Docker stack definition: `docker-compose.yml`
- Frontend dev image + entrypoint: `infra/docker/frontend.Dockerfile`, `infra/docker/frontend-entry.sh`
