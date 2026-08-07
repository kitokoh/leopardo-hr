# CI/CD Workflows — Leopardo HR

## Cartographie des workflows

### 🔁 Composite actions partagées (`.github/actions/`)
Ces actions composites remplacent les anciens workflows reutilisables `_setup-php.yml`
et `_setup-flutter.yml` (supprimés, voir `CHANGELOG.md` v4.23.4). Elles sont appelées
depuis les steps des workflows ci-dessous, pas declenchees directement.

| Action | Rôle |
|---|---|
| `.github/actions/setup-backend-db` | PHP + Composer + bootstrap migrations multi-tenant (public + shared_tenants) contre postgres/redis |
| `.github/actions/setup-flutter-android` | Java 17 + Flutter pour les builds Android (utilisee par `mobile-apps-ci.yml`, `mobile-distribute.yml`, `deploy-main.yml`) |

---

### ✅ Workflows principaux (déclenchés sur PR/push)

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `tests.yml` | PR → main/develop + push | Tests backend (PHPUnit/Pest) + mobile Flutter |
| `coverage-gate.yml` | PR → api/** | Seuil de couverture PHP (60%) |
| `backend-jobs-ci.yml` | PR → Jobs/Listeners | Tests des Jobs/Queues Laravel |
| `web-ci.yml` | PR → front/admin-dashboard | Lint + test + E2E Vue.js |
| `web-marketing-ci.yml` | PR → front/web | Lint + test + E2E Next.js |
| `mobile-apps-ci.yml` | PR → front/mobile | Build + tests Flutter |
| `architecture-check.yml` | PR → api | Vérifie les règles d'architecture DDD |
| `openapi-ci.yml` | PR → openapi | Validation spec OpenAPI |
| `codeql.yml` | Hebdomadaire + PR | Analyse de sécurité CodeQL |
| `secret-scan.yml` | Push | Détection de secrets commités |
| `dependabot.yml` | Automatique | Mises à jour dépendances |

---

### 🚀 Workflows de déploiement

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `deploy-main.yml` | Push → main | Déploiement production (Render) |
| `deploy-staging.yml` | `workflow_run` (Tests sur main) + dispatch | Déploiement staging — **fail-fast si `STAGING_API_URL`/`RENDER_STAGING_DEPLOY_HOOK_URL` absents** (plus aucun fallback prod, issue #1485) |
| `e2e-staging.yml` | Après deploy-staging | Tests E2E post-déploiement |
| `mobile-distribute.yml` | Manuel + tags | Distribution APK/IPA |
| `release.yml` | Tags v*.*.* | Création de release GitHub |

---

### 🔍 Workflows de qualité / observabilité

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `phpstan-baseline.yml` | Manuel | Régénère phpstan-baseline.neon |
| `lighthouse.yml` | PR/push → front/web + hebdomadaire | Audit Lighthouse (perf, a11y, SEO) + budget d'assets (`front/web/budget.json`), non bloquant (PA2-QA-008) |
| `owasp-zap.yml` | Manuel | Scan OWASP ZAP (sécurité API) |
| `k6-load-smoke.yml` | Manuel | Load test k6 |
| `i18n-enterprise.yml` | PR → shared/i18n | Validation et sync traductions |
| `database-backup.yml` | Schedule | Backup PostgreSQL |

---

### 🛠️ Workflows de maintenance

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `fix-composer-lock.yml` | Manuel | Régénère composer.lock |
| `plan-action2-project.yml` | PR → docs/PLAN_ACTION2 | Sync GitHub Projects |
| `plan-action2-claim-guard.yml` | PR (opened/edited/synchronize) | Garde-fou collision de claim multi-agent + signalement PR sans ID PA2-* (PA2-AUTO-011/004) |
| `plan-action2-post-merge-audit.yml` | Push → main | Audit non bloquant CHANGELOG/openapi.yaml/i18n apres chaque merge (PA2-AUTO-010) |

---

### Workflows smoke (Manuel)

| Fichier | Rôle |
|---|---|
| `launch-api-profile-smoke.yml` | Smoke test API profil prod |
| `launch-observability-smoke.yml` | Health check toutes les URLs prod |

---

## Règles de contribution CI

1. **Ne pas dupliquer la config PHP/Flutter** — utiliser les composite actions `.github/actions/setup-backend-db` et `.github/actions/setup-flutter-android`
2. **Nommer clairement** : `<scope>-<action>.yml` (ex: `api-lint.yml`, `mobile-test.yml`)
3. **path filters** obligatoires sur les PRs pour éviter de déclencher tout sur chaque push
4. **`concurrency`** obligatoire avec `cancel-in-progress: true`
5. **`FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true`** requis sur tous les workflows actifs
