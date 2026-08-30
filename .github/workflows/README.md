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

### 🛡 Gardes anti-régression du job Hygiene Guards (`architecture-check.yml`)

| Garde | Détecte | Issue |
|---|---|---|
| `check-public-routes.sh` | Route publique canonique perdue dans `route:list` (controller non routé) | #5519 |
| `check-duplicate-use-imports.sh` | Double `use` / alias `as` dupliqué dans `routes/**` + `Providers/**` | #5519 |
| `check-providers-syntax.sh` | `*ServiceProvider.php` avec erreur de syntaxe (`php -l`) | #5519 |

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
| `deploy-staging.yml` | Push → main + dispatch (`push` est l'unique déclencheur direct depuis #3545/#4359 ; `workflow_run` reste géré en défense en profondeur dans le script) | Déploiement staging — **fail-fast si `STAGING_API_URL`/`RENDER_STAGING_DEPLOY_HOOK_URL` absents** (plus aucun fallback prod, issue #1485) |
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
| `post-merge-conventions-audit.yml` | Push → main | Audit non bloquant CHANGELOG/openapi.yaml/i18n après chaque merge (CONVENTIONS.md §4.3/§7) |
| `fix-feat-ratio-report.yml` | Schedule (lundi 07:00 UTC) + manuel | Rapport hebdomadaire du ratio fix/feat, KPI gouvernance (issue #5634) |

---

### 🛠️ Workflows de maintenance

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `fix-composer-lock.yml` | Manuel | Régénère composer.lock |

---

### 🔖 Gouvernance PR/issue

Le processus interne multi-agents issu de `docs/PLAN_ACTION2` (issue #1731) est clos depuis le
2026-07-26 — la gestion de projet active passe exclusivement par **GitHub Issues et GitHub
Projects** (`docs/PLAN_ACTION2/` est un redirect vers l'archive — voir
`docs/archive/PLAN_ACTION2/`). Les 4 workflows `plan-action2-*.yml` qui existaient encore ont
été nettoyés (2026-08-29) : ce qui restait spécifique au backlog PA2-* (collision de claim
multi-agent, signalement d'ID PA2-*, rapport de backlog, sync GitHub Projects) a été supprimé ;
ce qui était déjà une règle générale (indépendante de PLAN_ACTION2) a été renommé et conservé
ci-dessous.

| Fichier | Déclencheur | Rôle |
|---|---|---|
| `pr-issue-guard.yml` | PR du dépôt (opened/edited/synchronize) — sauté sur fork | Anti-doublon « une issue = une PR » (#5442) + exige que chaque PR référence une issue qu'elle ferme (PA2-OPS-008, **bloquant**, rappelé dans `PULL_REQUEST_TEMPLATE.md`) |

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
4. **`concurrency`** obligatoire avec `cancel-in-progress: ${{ github.event_name == 'pull_request' }}` — sur `main`, un push ne doit jamais annuler le run du commit précédent (sinon CodeQL/scans n'uploadent jamais leurs résultats pendant les vagues de merges — issues #2131, #3532)
5. **`FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true`** requis sur tous les workflows actifs
