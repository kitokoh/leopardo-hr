# Architecture CI/CD — Leopardo RH

> Dernière mise à jour : 2026-07-26

## Vue d'ensemble

Le repo est en **trunk-based development** : il n'existe pas de branche `develop` ni de
branche `staging` (vérifié via `git ls-remote` — seules `main` et des branches
`feature/*`/`fix/*` courte durée de vie existent). Tout part de branches courtes
fusionnées dans `main` par PR (branche protégée), et les déploiements staging/production
sont enchaînés automatiquement après les checks CI sur `main` via `workflow_run`
(pas de push direct déclenchant un déploiement) :

```
PR → merge sur main
    │
    ├─ workflow_run("Tests - Leopardo RH" sur main) → deploy-staging.yml → Render staging
    └─ workflow_run("Tests - Leopardo RH" / "Web CI - Leopardo Admin") → deploy-main.yml → Render production
```

## Workflows GitHub Actions

| Workflow | Déclencheur | Rôle |
|----------|-------------|------|
| `tests.yml` | PR + push main | Tests PHP + couverture |
| `coverage-gate.yml` | PR main (api/) | Gate couverture min (voir "Gates de qualité") |
| `mobile-apps-ci.yml` | PR + push (front/mobile_apps/) | Tests Flutter + analyze |
| `web-ci.yml` | PR + push (front/admin-dashboard/) | Build + lint admin-dashboard (Vue/Vite) |
| `openapi-ci.yml` | PR + push (api/openapi.yaml, dev-hub/openapi/) | Validation spec OpenAPI |
| `architecture-check.yml` | PR | Vérification DDD boundaries + PHPStan modules/strict |
| `deploy-staging.yml` | `workflow_run` sur "Tests - Leopardo RH" (branche main) | Deploy staging Render |
| `deploy-main.yml` | `workflow_run` sur "Tests - Leopardo RH" / "Web CI - Leopardo Admin" | Deploy production Render |
| `secret-scan.yml` | PR | Scan fuites secrets (TruffleHog) |
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
- **Depuis #1485** : `deploy-staging.yml` échoue (fail-fast) si `STAGING_API_URL` ou `RENDER_STAGING_DEPLOY_HOOK_URL` ne sont pas configurés — les fallbacks vers les valeurs/secret de production ont été retirés. Tant qu'aucune app Render staging réelle n'existe, ce workflow restera rouge sur main (signal volontaire, pas un déploiement masqué de la prod).

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
- **PHPStan** : 3 configurations distinctes coexistent (voir `api/phpstan*.neon`) :

  | Config | Niveau | Job CI | Bloquant ? |
  |--------|--------|--------|------------|
  | `phpstan-modules.neon` | 5 | `architecture-check.yml` (job `phpstan-modules`) | Oui — requis en branch protection |
  | `phpstan.neon` | `max` | non exécuté en CI actuellement | — |
  | `phpstan-strict.neon` | 8 | `architecture-check.yml` (`continue-on-error`) | Non — informatif seulement |

- **Coverage minimum** : `BACKEND_COVERAGE_MIN` (variable GitHub). Défaut actuel
  (`DEFAULT_BACKEND_COVERAGE_MIN`) = **60%** à la fois dans `tests.yml` et
  `coverage-gate.yml` (les deux workflows sont alignés depuis la résolution de
  l'issue #1310). La cible "ratchet" (à relever progressivement) mentionnée dans
  `coverage-gate.yml` est 65%, mais ce n'est pas (encore) la valeur par défaut appliquée.
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

Voir aussi `docs/CI_CD_SECRETS.md` pour le détail complet des secrets et de leur rotation.

## Architecture Redis

Toutes les queues partagent la même instance Upstash Redis (TLS).
Priority par queue :

1. `notifications` — push mobile temps réel
2. `emails` — envoi d'emails
3. `pdf` — génération de bulletins de salaire
4. `payroll` — calculs de paie (jobs lourds)
5. `default` — tout le reste

## Modèle de branches

Le repo suit un modèle **trunk-based** : il n'y a pas de branche `develop`/`staging`
longue durée (vérifié via `git ls-remote` — seule `main` persiste, le reste sont des
branches de travail courtes). Le staging est déployé automatiquement depuis `main`
après CI verte (voir "Vue d'ensemble" ci-dessus), pas via une branche dédiée.

```
main            ← production ET source du deploy staging (protégée, PR obligatoires)
feature/issue-N ← travail sur une issue GitHub spécifique
fix/*           ← corrections de bugs
hotfix/*        ← correctifs urgents prod
```

> Historique : une précédente version de ce document décrivait un modèle `main`
> (production) / `develop` (staging) avec push direct déclenchant les déploiements.
> Ce modèle a été abandonné (migration vers trunk-based) et n'est plus représentatif
> du fonctionnement actuel du CI/CD ; conservé ici uniquement à titre de contexte.
