# Architecture CI/CD — Leopardo RH

> Dernière mise à jour : 2026-07-01

## Vue d'ensemble

```
git push
    │
    ├─ develop → staging deploy (auto)
    └─ main    → production deploy (auto)
```

## Workflows GitHub Actions

| Workflow | Déclencheur | Rôle |
|----------|-------------|------|
| `tests.yml` | PR + push develop/main | Tests PHP + couverture |
| `coverage-gate.yml` | PR develop/main (api/) | Gate couverture min 65% |
| `mobile-ci.yml` | PR + push (mobile/) | Tests Flutter + analyze |
| `web-ci.yml` | PR + push (front/web/) | Build Next.js + lint |
| `openapi-ci.yml` | PR + push (api/openapi/) | Validation spec OpenAPI |
| `architecture-check.yml` | PR | Vérification DDD boundaries |
| `deploy-staging.yml` | push develop | Deploy staging Render |
| `deploy-main.yml` | push main | Deploy production Render |
| `secret-scan.yml` | PR | Scan fuites secrets (GitLeaks) |
| `codeql.yml` | schedule + PR | Analyse sécurité statique |

## Environnements

### Production (Render)
- **API** : `leopardo-api` (Web Service, Docker)
- **Queue Worker** : `leopardo-queue-worker` (Background Worker)
  - Queues : `notifications`, `emails`, `pdf`, `payroll`, `default`
- **Scheduler** : `leopardo-scheduler` (Background Worker, every 60s)
- **Base de données** : PostgreSQL 16 (Render managed)
- **Cache/Queue** : Redis Upstash (TLS)

### Staging
- Même architecture, variables `APP_ENV=staging`

## Variables d'environnement requises

### Non optionnelles (erreur 500 si absentes)
| Variable | Usage |
|----------|-------|
| `GOOGLE_CLIENT_ID` | Login Google OAuth |
| `GOOGLE_CLIENT_SECRET` | Login Google OAuth |
| `GOOGLE_REDIRECT_URL` | Callback OAuth |
| `FIREBASE_PROJECT_ID` | Push notifications |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | Push notifications (JSON base64) |
| `CHARGILY_API_KEY` | Paiement Algérie |
| `CHARGILY_WEBHOOK_SECRET` | Vérification webhook Chargily |
| `STRIPE_SECRET_KEY` | Abonnements internationaux |
| `STRIPE_WEBHOOK_SECRET` | Vérification webhook Stripe |
| `APP_KEY` | Chiffrement Laravel |

### Optionnelles
| Variable | Défaut | Usage |
|----------|--------|-------|
| `MAIL_MAILER` | `smtp` | Transport email |
| `SENTRY_LARAVEL_DSN` | _(vide)_ | Error tracking |
| `TELESCOPE_ENABLED` | `false` | Laravel Telescope |

## Gates de qualité

### Backend
- **PHPStan** niveau 6 — bloquant
- **Coverage minimum** : 65% (variable `BACKEND_COVERAGE_MIN`)
- **Tests** : PHPUnit 11 sur PostgreSQL 16

### Mobile
- **Flutter analyze** — bloquant (no fatal errors)
- **Coverage minimum** : 21% (variable `MOBILE_COVERAGE_MIN`)

### OpenAPI
- Validation spec contre les routes Laravel (`openapi-ci.yml`)

## Secrets GitHub requis

```
RENDER_API_KEY           # Deploy hook Render
RENDER_SERVICE_ID_API    # ID du service Web API
RENDER_SERVICE_ID_WORKER # ID du Background Worker
SENTRY_AUTH_TOKEN        # Upload sourcemaps
```

## Architecture Redis

Toutes les queues partagent la même instance Upstash Redis (TLS).
Priority par queue :

1. `notifications` — push mobile temps réel
2. `emails` — envoi d'emails
3. `pdf` — génération de bulletins de salaire
4. `payroll` — calculs de paie (jobs lourds)
5. `default` — tout le reste

## Modèle de branches

```
main       ← production (protégée, PR obligatoires)
develop    ← staging (intégration continue)
feat/*     ← fonctionnalités
fix/*      ← corrections de bugs
hotfix/*   ← correctifs urgents prod
```
