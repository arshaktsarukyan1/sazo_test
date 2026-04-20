# TDS API Contract (Initial)

## Internal (`/api/v1`)

- `GET /me`
- `GET /domains`
- `POST /domains`
- `GET /domains/{id}`
- `PATCH /domains/{id}`
- `DELETE /domains/{id}`
- `GET /campaigns`
- `POST /campaigns`
- `GET /campaigns/{id}`
- `PATCH /campaigns/{id}`
- `POST /campaigns/{id}/activate`
- `POST /campaigns/{id}/pause`
- `POST /campaigns/{id}/archive`
- `POST /conversions/manual`
- `GET /reports/kpi`
- `GET /reports/ab-tests`
- `GET /ops/sync-runs`

## Public

- `GET /health`
- `GET /campaign/{campaignSlug}` (legacy: `GET /r/{campaignSlug}`)
- `GET /tracker/{campaignId}.js`
- `GET /click`
- `POST /api/v1/events`
- `POST /api/v1/webhooks/shopify/orders`
