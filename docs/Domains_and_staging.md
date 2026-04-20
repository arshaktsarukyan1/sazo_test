# Domain management and staging routing

This document supports **Phase 8** (multiple tracking domains): DNS, TLS, reverse proxy behaviour, and how to validate a new domain end-to-end on staging.

## DNS records

Point the **tracking hostname** (the value stored in `domains.name`, e.g. `track.customer.com`) at the **staging (or production) edge** that terminates HTTP(S) for the TDS stack.

Typical patterns:

- **A record**: `track.customer.com` → public IPv4 of your load balancer or VM.
- **AAAA record** (optional): same name → IPv6 if you use it.
- **CNAME**: allowed if your edge is a hostname (e.g. `tds-staging.example.net`); avoid CNAME at zone apex unless you use ALIAS/ANAME at your DNS provider.

Lowercase hostnames in the database match the HTTP `Host` header (case-insensitive at runtime). Do not include `https://` or a path in `domains.name`.

## SSL / TLS provisioning

1. Ensure DNS for the tracking hostname **resolves to your edge** before requesting certificates (HTTP-01 / ALPN challenges need correct routing).
2. Issue a certificate that covers the tracking hostname (single name or SAN). Options depend on your platform:
   - **Let’s Encrypt** (certbot, acme.sh, or the cloud LB’s managed certificate feature).
   - **Managed TLS** from your cloud provider (certificate attached to the HTTPS listener).
3. After the cert is active, confirm in a browser: `https://track.customer.com/up` (or your health URL) returns **200** over HTTPS without certificate warnings.

The Laravel app does not terminate TLS in the default Docker layout; **nginx (or another reverse proxy) in front of PHP** should handle TLS and forward decrypted traffic to the app, preserving `Host` and `X-Forwarded-Proto`.

## Reverse proxy routing expectations

The reference nginx config (`infra/nginx/default.conf`) routes these paths to the **API (Laravel)** upstream so tracking works on **any** `server_name` that resolves to that edge:

| Path prefix | Purpose |
|-------------|---------|
| `/api/` | Internal JSON API + public tracking POST under `/api/v1/...` |
| `/campaign/` | Campaign redirect entry (ads; weighted lander split) |
| `/r/` | Legacy campaign redirect (same handler as `/campaign/`) |
| `/click` | Offer click routing |
| `/tracker/` | JavaScript tracker asset |
| `/webhooks/` | Inbound webhooks |

Everything else is proxied to the **Next.js** admin UI.

Requirements:

- Forward **`Host`** unchanged (`proxy_set_header Host $host;`) so **domain-bound campaigns** can resolve: Laravel compares the request host to `domains.name` when `campaigns.domain_id` is set.
- Set **`X-Forwarded-Proto`** (`$scheme`) so the app can tell HTTP vs HTTPS behind TLS termination.
- Optional: `X-Forwarded-For` / `X-Real-IP` for IP-based features.

To add a **new** tracking domain on the same edge, you usually **do not** change nginx `server_name` if you use a catch-all (`_`) or a shared certificate; you add DNS + TLS SAN and register the domain in the TDS **Domains** admin with status **active** once DNS/TLS are correct.

## Staging end-to-end checklist (“new domain routed end-to-end”)

Run these on **staging** after creating the domain row and assigning it to a campaign:

1. **DNS**: `dig +short track.customer.com` (or your hostname) returns the staging edge IP/target.
2. **TLS**: `curl -sI https://track.customer.com/up` shows **HTTP/2 200** (or 200 on HTTP/1.1) with a valid certificate chain.
3. **Redirect**: With campaign `slug` **active** and landers configured, open  
   `https://track.customer.com/campaign/{slug}`  
   Expect **302** to a lander URL (or fallback if no landers).
4. **Wrong host**: Same `slug` on another hostname that is **not** assigned to the campaign should return **404** (domain binding enforced).
5. **Admin**: Internal API can list the domain and campaigns linked (`GET /api/v1/domains/{id}`).

If step 3 fails with 404, verify: campaign **status** is `active`, domain **status** is `active`, domain **is_active** is true, and `domains.name` exactly matches the browser host (no accidental `www.` mismatch).
