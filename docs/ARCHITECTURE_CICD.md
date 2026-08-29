# Architecture CI/CD — Leopardo RH

> Dernière mise à jour : 2026-08-29

## Vue d'ensemble

Le repo est en **trunk-based development** : il n'existe pas de branche `develop` ni de
branche `staging` (vérifié via `git ls-remote` — seules `main` et des branches
`feature/*`/`fix/*` courte durée de vie existent). Tout part de branches courtes
fusionnées dans `main` par PR (branche protégée).

> ⚠️ **Mise à jour (2026-08-29)** : la section ci-dessous décrivait un déclenchement des
> déploiements via `workflow_run` sur les checks de `main`. Ce mécanisme a été remplacé par un
> déclenchement direct sur `push: main` (issues #3545/#4359) — un `workflow_run` empilé sur les
> checks requis provoquait des runs annulés en cascade sous rafale de merges, laissant `main`
> sans déploiement (voir `docs/infra/02_alignement/CI_SATURATION.md`). `workflow_run` reste géré
> en défense en profondeur dans le script de `deploy-main.yml`/`deploy-staging.yml`, mais n'est
> plus le déclencheur principal. Le doc canonique et à jour des workflows est
> `.github/workflows/README.md` — s'y référer en cas de doute.

```
PR → merge sur main (push)
    │
    ├─ push: main → deploy-staging.yml → Render staging (fail-fast si non configuré, #1485)
    └─ push: main → deploy-main.yml → Render production (après vérification des checks requis du SHA)
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
| `deploy-staging.yml` | `push: main` (unique déclencheur depuis #3545/#4359) | Deploy staging Render |
| `deploy-main.yml` | `push: main` (unique déclencheur depuis #3545/#4359) | Deploy production Render |
| `secret-scan.yml` | PR | Scan fuites secrets (TruffleHog) |
| `codeql.yml` | schedule + PR | Analyse sécurité statique |

## Environnements

### Production (Render)
- **API** : `gestionemployerbackend` (Web Service, Docker, plan starter)
- **Queue Worker** : `leopardo-queue-worker` (Background Worker, plan starter)
  - Queues : `webhooks`, `audit`, `notifications`, `emails`, `pdf`, `payroll`, `documents`, `default`
- **Scheduler** : `leopardo-scheduler` (Background Worker, `schedule:run` every 60s, plan starter)
- **Base de données** : PostgreSQL 16 (Render managed, plan starter)
- **Cache/Session** : Redis interne Render (`leopardo-redis`, plan free, issue #3774)
- **Queue** : `QUEUE_CONNECTION=database` (table Postgres `jobs`, pas Redis — décision #5578 pour
  ne pas dépendre d'un quota Redis externe ; drainée par le worker dédié + le fallback GitHub
  Actions `queue-worker-fallback.yml`)

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

## Architecture Queue

> ⚠️ Mise à jour (2026-08-29) : cette section décrivait une instance Upstash Redis partagée.
> Depuis #5578, la queue tourne sur `database` (table Postgres `jobs`), pas Redis — voir
> `docs/OPS/RENDER_QUEUE_WORKERS.md` et `docs/GESTION_PROJET/RUNBOOK_RENDER_WORKERS.md`. Redis
> (interne Render, `leopardo-redis`) ne sert plus qu'au cache/session.

Toutes les queues sont drainées par le même worker (`leopardo-queue-worker`), consommées dans
l'ordre déclaré au démarrage (`--queue=webhooks,audit,notifications,emails,pdf,payroll,documents,default`) :

1. `webhooks` — accusés de réception webhooks entrants
2. `audit` — journalisation d'audit
3. `notifications` — push mobile temps réel
4. `emails` — envoi d'emails
5. `pdf` — génération de bulletins de salaire
6. `payroll` — calculs de paie (jobs lourds)
7. `documents` — traitement de documents
8. `default` — tout le reste

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

## Protection de branche main — procédure de merge admin sûre (issue #2011)

### Incident du 2026-08-14 (récidive possible)

Lors d'une vague de merges, la protection de `main` s'est retrouvée vidée
(`required_status_checks` supprimé, `required_approving_review_count` passé à 0)
par un outillage de merge qui fait un GET→PUT de la protection et écrase les
champs non ré-injectés. Conséquence : des merges **sans checks verts** possibles
sur un repo public.

### Règles à verrouiller

1. **INTERDIT** de modifier `required_status_checks` / `required_pull_request_reviews`
   depuis un script de merge. Le seul toggle acceptable est `enforce_admins`, et
   encore : préférer l'API REST `PUT /pulls/{n}/merge` avec un token admin.
2. Toute bascule temporaire (ex. `enforce_admins=false` pour un merge d'urgence)
   doit être suivie d'une **restauration canonique** puis d'une **vérification GET**
   (assertion : strict=true, 5 contexts, reviews=1, enforce_admins=true).
3. Le canonique vit dans `dev-hub/tools/branch-protection-canonical.json`.
   Restaurer avec :
   ```bash
   curl -X PUT -H "Authorization: Bearer $GITHUB_TOKEN" \
     -H "Accept: application/vnd.github+json" \
     https://api.github.com/repos/kitokoh/leopardo-hr/branches/main/protection \
     -d @dev-hub/tools/branch-protection-canonical.json
   ```
4. Garde automatique : workflow `Branch Protection Guard` (toutes les heures +
   dispatch) exécute `dev-hub/tools/check-branch-protection.sh` et échoue si la
   protection réelle dévie du canonique. En cas de rouge sur cette garde,
   restaurer immédiatement avec la commande ci-dessus avant tout autre merge.

### Merge standard (recommandé)

1. PR → checks GitHub Actions requis verts (`gh pr checks <n>`).
2. `gh pr merge <n> --merge --delete-branch` (ou l'API équivalente).
3. Vérifier `gh pr view <n> --json state,mergedAt,mergeCommit`.
4. Supprimer la branche distante (fait par `--delete-branch`) + `git remote prune origin`.
