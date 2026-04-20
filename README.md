# Traffic Distribution System (TDS)

Monorepo layout:

- `frontend`: Next.js app (TypeScript, App Router, Tailwind)
- `backend`: Laravel app
- `infra`: nginx and local infrastructure config
- `docs`: architecture and specifications

## Phase 0 bootstrap

This phase establishes a reproducible local environment.

### Prerequisites

- Docker + Docker Compose plugin

### Start the stack

```bash
docker compose up --build
```

### Verification checks

1. Frontend home page loads:

```bash
curl -i http://localhost/
```

2. Backend health endpoint returns `200`:

```bash
curl -i http://localhost/api/health
```

## CI baseline

GitHub Actions workflow: `.github/workflows/ci.yml`

- Frontend: `npm ci`, `npm run lint`, `npm test`
- Backend: `composer install`, `composer lint`, `composer test`
- Migration check: `php artisan migrate --force --ansi`
