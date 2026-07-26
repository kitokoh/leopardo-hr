# Real status audit — PA2-MKT-008 — 2026-07-25

Status: complete
Author: internal audit, KiloClaw agent
Scope: ticket `PA2-MKT-008` from `02_BACKLOG_ATOMIQUE.md` / `03_GITHUB_PROJECT_IMPORT.csv` / GitHub Issue #955, verified against the live deployment and current documentation (`front/web/vercel.json`, `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md`, `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md`).

## Ticket acceptance criteria

> A genuinely owned domain serves the vitrine without SSO or `noindex`; `leopardo.com` is removed from the docs as long as it has not been purchased.

## Finding per criterion

1. **A genuinely owned/controlled domain serves the vitrine, publicly, without SSO/auth** — Already done and re-verified live in this audit:
   - `curl -sI https://gestionemployer-backend.vercel.app/` returns `HTTP/2 200` with no `WWW-Authenticate` header and no auth redirect — the vitrine is reachable directly, with no Vercel deployment-protection SSO gate.
   - `curl -s https://gestionemployer-backend.vercel.app/robots.txt` returns a real, crawlable policy (`Allow: /`, explicit `Disallow` only for `/admin`, `/api`, `/auth`, `/dashboard`, `/.env`, `/.git`, `/node_modules`; `Sitemap: https://gestionemployer-backend.vercel.app/sitemap.xml`), i.e. a production robots policy, not a stub.
   - `front/web/vercel.json` carries no Vercel Password Protection / SSO configuration; `git.deploymentEnabled` only exposes `main` and `staging` (not `develop`), consistent with a public production branch.
2. **No blanket `noindex`** — Already done. The live `robots.txt` allows indexing (`Allow: /`, with `Googlebot`/`Bingbot` explicitly allowed); no `X-Robots-Tag: noindex` response header was observed on the root route.
3. **`leopardo.com` removed from the docs while unpurchased** — Already done (2026-07-21, per the existing `02_BACKLOG_ATOMIQUE.md` entry, re-verified in this audit):
   - `docs/DEPLOYMENT_PRODUCTION.md` opens with an explicit warning banner that `leopardo.com` is **not owned** for this product (a real, unrelated third-party US construction company site) and must not be treated as an active target; every remaining mention is explicitly framed as a generic "once a real domain is purchased" placeholder, with the actually-live URL (`gestionemployer-backend.vercel.app`) called out as the current value in use.
   - `docs/DEPLOYMENT_STAGING.md` documents `staging.leopardo.com` as **not deployed** (DNS not configured, domain not owned) rather than as an active staging target, with the same explicit ownership warning.
   - `docs/GUIDES/GUIDE_LIENS_PLATEFORME_ET_COMMUNICATION.md` and `.env.local.example` fallbacks were already migrated away from `leopardo.com` per the same 2026-07-21 pass.

## Conclusion

**PA2-MKT-008 was already done** as of 2026-07-21 (see the corresponding `02_BACKLOG_ATOMIQUE.md` entry), and every criterion was re-verified live against the actual production deployment for this audit rather than taken on faith. GitHub Issue #955 tracking this ticket was, however, never closed to reflect that status. No further application code or documentation change was required beyond closing the tracking issue.

## Verification

- Live HTTP checks against `https://gestionemployer-backend.vercel.app/` (root response headers) and `/robots.txt` (crawl policy) confirming no SSO/auth gate and no blanket noindex.
- Direct read of `front/web/vercel.json` confirming no password-protection/SSO config and a `main`/`staging`-only deployment gate.
- Direct read of `docs/DEPLOYMENT_PRODUCTION.md` and `docs/DEPLOYMENT_STAGING.md` confirming `leopardo.com` is documented only as a not-yet-purchased placeholder, never as an active target.
- No automated test added (documentation/audit-only ticket, no application code changed).
