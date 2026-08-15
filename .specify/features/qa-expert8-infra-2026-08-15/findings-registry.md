# Findings Registry — Session QA Expert 8 (2026-08-15)

**Périmètre** : infrastructure & périphérie — zones non couvertes par les sessions experts 1-7 du 2026-08-15 : 39 workflows `.github/workflows/`, `edge/`, `dev-hub/`, `scripts/`, `postman/`, `render.yaml`, `docker-compose.yml`, `.devcontainer/`, `CODEOWNERS`, `.pre-commit-config.yaml`, proxy/middleware de `front/web`, routes↔OpenAPI, runtime production (smoke).

**Méthode anti-doublon** : chaque constat croisé avec (1) les 205+ issues ouvertes au 15:30 UTC, (2) les 130 branches distantes, (3) les PRs ouvertes. Constats déjà couverts → section « Exclus (déjà tracés) ».

## Smoke runtime production (2026-08-15 ~15:24 UTC)

| Cible | Résultat | Statut |
|---|---|---|
| `gestionemployerbackend.onrender.com/api/v1/health` | 200, `status:ok`, db 237ms, **version `4.23.5`** | ✅ UP (version stale → #3528) |
| `gestionemployer-backend.vercel.app/` | 200 (vitrine) | ✅ UP |
| `leopardo-rh.com` | HTTP 000 / NXDOMAIN | ❌ DOWN — déjà tracé **#3452** (action DNS propriétaire) |

Note : `PILOTAGE.md` annonce `PROGRAM_VERSION = 4.24.0` et `api/config/app.php:33` défaut `4.24.0` — la prod rapporte `4.23.5` à cause d'`api/.env.example:10` (#3528).

## Constats NOUVEAUX → issues créées

| # | Issue | Sévérité | Surface | Constat (preuve) |
|---|---|---|---|---|
| F1 | #3518 | P1 | tooling/ops | `backup_drill.sh:67-68,174` — `DROP SCHEMA CASCADE` sur `RESTORE_DB_URL` sans garde ≠ `DATABASE_URL` |
| F2 | #3519 | P1 | CI | `backend-jobs-ci.yml:10-12,21-23` — paths pré-modulaires → workflow jamais déclenché sur le code réel (WebhookDispatcher Billing etc.) |
| F3 | #3520 | P2 | security | postman collection:188,208,268 — `admin@leopardo-rh.com`/`password123` en clair (repo public, mot de passe historiquement réel cf. render.yaml) |
| F4 | #3521 | P2 | security | `staging-demo-auth-smoke.sh:13-17` — fallback `password123` par défaut |
| F5 | #3522 | P2 | web/security | `middleware.ts:24` — gate par présence seule du cookie `leopardo_token` ; HTML dashboard servi avec cookie forgé (validation client-side seule) |
| F6 | #3523 | P2 | web/api | `app/api/v1/[...path]/route.ts:64` — fetch proxy sans try/catch → 500 HTML au lieu de 502 JSON |
| F7 | #3528 | P2 | api/ops | `api/.env.example:10` `APP_VERSION=4.23.5` ≠ `config/app.php` 4.24.0 — prod /health stale (vérifié live) |
| F8 | #3529 | P2 | edge-sync/security | `edge/install.sh:63` — curl\|sh compose cloud, root, sans checksum/signature (cf. audit #1711) |
| F9 | #3530 | P2 | edge-sync | `docker-entrypoint.edge.sh` — `migrate/route:cache/event:cache || true` : boot « sain » sur schéma stale |
| F10 | #3531 | P2 | ops/infra | `render.yaml:9` service `leopardo-api` → hostname ≠ `gestionemployerbackend.onrender.com` (README:109, cors.php:33) |
| F11 | #3532 | P2 | CI/security | `cancel-in-progress: true` sur ~20 workflows keyed `github.ref` → CodeQL/scans annulés sur main (codeql.yml:30, secret-scan.yml:15, openapi-ci.yml:35) ; uploads security-events manquants (pattern #2131 non corrigé) |
| F12 | #3533 | P3 | process | CODEOWNERS:39-44 « 1 approval exigé » vs protection canonique 0 review / enforce_admins false (vérifié via API) |
| F13 | #3534 | P3 | CI | `fix-composer-lock.yml:13` — `contents: write` + push direct possible sur main (bypass des 5 checks) |
| F14 | #3535 | P3 | web/deps | front/web : next-mdx-remote, gray-matter, reading-time, rehype-slug, rehype-autolink-headings, remark-gfm, ts-node — 0 import dans src/ |
| F15 | #3536 | P3 | web/seo | `(landing)/integrations` sans layout.tsx (0 metadata propre) ; `guides/layout.tsx:8` « Checklist Paie 2024 » périmé |
| F16 | #3537 | P3 | api/edge-sync | `EdgeSyncDaemonCommand.php:30-34,41` — 6 `env()` hors `config/` → nulls silencieux après `config:cache` |
| F17 | #3538 | P3 | CI | `tests.yml:75-89` — stub `mobile-flutter-stable-compat` maintient un contexte hors protection canonique |

## Exclus (déjà tracés — vérifiés, pas de nouvelle issue)

| Constat | Couvert par |
|---|---|
| Drift OpenAPI (159 routes mesurées : /reports/*, /conversations, /api-tokens, departments & payrolls CRUD, webhooks Stripe/Chargily, /edge*, /hr/*, /auth/google, platform support-tickets) | **#3233** (P1, ~165 routes), #3061, #2675 — comptage cohérent, rien à ajouter |
| RateLimiter `trial-status` enregistré 2× (token+IP écrasé par IP-only) — reconfirmé `AppServiceProvider.php:186-190` vs `:226-230` | **#3366** |
| ~12 pages landing FR-only (about, testimonials, careers, videos, marketing, employes, comptabilite, documents…) | **#3334** (+ #3380 dashboard, #3379 checkout, #3443 pricing) |
| dev-hub tools référençant docs PLAN_ACTION2 supprimés (check-phpstan-baseline-delta.sh:155, check-plan-action2-claim.sh:67,97…) | **#3413** |
| Vitrine leopardo-rh.com NXDOMAIN | **#3452** (ops propriétaire) |
| Headers sécurité vitrine (HSTS/XFO/nosniff/Referrer/Permissions-Policy OK, CSP report-only documenté) | #1607 (décision datée, pas un bug) |
| Throttles auth (login/register/forgot/reset, web-login) — tous présents et corrects | — (conforme, rien à tracer) |
| dd/dump/var_dump/ray hors tests | — (aucun hit, conforme) |
| Mass-assignment (`$guarded=[]`, `Request::all()`→create) | — (aucun hit ; note latente : UpdateEmployeeDTO.php:57 fallback `$request->all()` non exploité aujourd'hui) |

## État CI / mergeability (snapshot 15:25 UTC)

- Protection `main` : 5 checks requis (Backend Coverage, PHPStan Strict, Module Structure Validator, Frontend ESLint+TS, actionlint+shellcheck), 0 review, enforce_admins=false.
- **Vercel non requis** — le check « Deployment rate limited — retry in 24 hours » (quota Pro) n'empêche PAS le merge ; il pollue néanmoins le rollup de toutes les PRs.
- CI fortement saturée (vagues multi-agents) : beaucoup de runs queued/cancelled — cf. #3532.
- ~55 PRs ouvertes, majorité `CONFLICTING` (duplication inter-agents) — triage Phase 2 en cours par ailleurs.

## Limites de la session

- Pas de runtime local PHP/Flutter/Docker dans cet environnement : validation API/mobile statique + CI (source de vérité, Constitution §IV).
- Boot edge (Docker) non testé dynamiquement — corrections #3529/#3530 validées statiquement + shellcheck.
