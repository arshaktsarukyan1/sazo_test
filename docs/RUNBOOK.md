# TDS Local Runbook

## Start stack

```bash
docker compose up --build
```

## Verify

- `http://localhost` serves frontend shell.
- `http://localhost/api/health` returns JSON `status=ok`.

## Initial backend install

Because backend is a skeleton, complete Laravel bootstrap when network is available:

1. Install dependencies in `backend/` with Composer.
2. Add Laravel standard files (`artisan`, `bootstrap/`, `config/`, `public/`, `vendor/`).
3. Run migrations:
   ```bash
   php artisan migrate
   ```
