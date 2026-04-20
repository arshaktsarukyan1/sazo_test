# TDS Data Model (MVP)

Core entities:

- `domains`
- `traffic_sources`
- `campaigns`
- `landers`
- `offers`
- `campaign_landers`
- `campaign_offers`
- `targeting_rules`
- `sessions`
- `visits`
- `clicks`
- `conversions`
- `cost_entries`
- `kpi_15m_aggregates`
- `kpi_daily_aggregates`

Notes:

- Keep `clicks` and `visits` indexed by `campaign_id`, `created_at`, `country`, `device_type`.
- Enforce unique keys on external conversion references (`shopify_order_id`).
- Add idempotency keys for webhook ingestion and spend sync runs.
