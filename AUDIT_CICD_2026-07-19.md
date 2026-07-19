# 🔍 AUDIT CI/CD — Leopardo RH (2026-07-19)

> Périmètre : les 28 workflows GitHub Actions sous `.github/workflows/`, `dependabot.yml`, et leur cohérence avec la structure réelle du monorepo.
> Méthode : lecture exhaustive de chaque fichier + validation outillée (`actionlint` v1.7.12 avec intégration `shellcheck` 0.10.0) + vérification croisée avec l'arborescence du repo et l'historique git.
> Ne pas confondre avec `AUDIT.md` (audit fonctionnel du 2026-07-01, section 6 uniquement CI) : ce document est un audit CI/CD dédié, plus profond et à jour au 2026-07-19.

---

## 📋 Résumé exécutif

| Catégorie | Constat | Sévérité |
|---|---|---|
| Bug YAML actif | `tests.yml` contient un fragment de script orphelin (lignes 753-754) issu d'une fusion/rebase ratée — 4 steps mobiles legacy sont invoqués mais n'existent plus dans le job | 🔴 Critique |
| Chemin cassé | `release.yml` build encore `front/mobile` (supprimé depuis #754, remplacé par `front/mobile_apps/*`) — tout tag `v*` échoue ce job | 🔴 Critique |
| Dependabot mal configuré | `directory: "/mobile"` n'existe pas ; écosystème `npm` limité à `/api`, oubliant `front/web`, `front/admin-dashboard`, `front/web-offline` ; aucun écosystème `github-actions` | 🟠 Élevé |
| Duplication massive | Le bloc setup PHP+PostgreSQL+Redis (~50 lignes) est copié-collé à l'identique dans `tests.yml` (×2), `coverage-gate.yml`, `backend-jobs-ci.yml` — 4 copies désynchronisables | 🟠 Élevé |
| Reusable workflows morts | `_setup-php.yml` et `_setup-flutter.yml` existent, sont bien conçus, mais ne sont appelés par **aucun** autre workflow | 🟡 Moyen |
| Pas de pinning par SHA | Toutes les actions tierces (`shivammathur/setup-php`, `subosito/flutter-action`, `treosh/lighthouse-ci-action`, `wzieba/Firebase-Distribution-Github-Action`, `trufflesecurity/trufflehog@main`) sont épinglées par tag mutable, pas par SHA | 🟠 Élevé (supply chain) |
| Action obsolète | `treosh/lighthouse-ci-action@v10` tourne sur un runtime Node trop ancien pour GitHub Actions (détecté par actionlint) | 🟡 Moyen |
| `trufflehog@main` | Scan de secrets épinglé sur la branche `main` du projet tiers, donc non reproductible et vecteur d'attaque potentiel si le repo amont est compromis | 🟠 Élevé |
| Versions d'actions incohérentes | Mélange `actions/checkout@v4` et `@v5`, `actions/upload-artifact@v4` et `@v5` dans des workflows différents | 🟡 Moyen |
| Secret réel dans l'historique | `AUDIT.md` documente un mot de passe Redis Upstash committé en clair dans l'historique git (repo public) — toujours non résolu | 🔴 Critique (déjà connu, non-CI mais impacte la sécurité globale) |
| CodeQL PHP non couvert | Le job "CodeQL (Backend)" est un stub qui ne fait qu'écrire un message — aucune analyse statique de sécurité réelle sur le code PHP (SAST) | 🟠 Élevé |
| Complexité `tests.yml` | 983 lignes, 9 jobs, logique de détection de changements dupliquée avec `deploy-main.yml` (patterns de chemin divergents à surveiller) | 🟡 Moyen |
| Déploiement en cascade fragile | `deploy-main.yml` se déclenche sur `workflow_run` de `tests.yml`/`web-ci.yml` ; `deploy-staging.yml` se déclenche indépendamment sur push vers `main` avec sa propre logique de polling (jusqu'à 10 min) — deux pipelines de déploiement parallèles et faiblement coordonnés | 🟡 Moyen |
| `owasp-zap.yml` / `e2e-staging.yml` déclenchés par `workflow_run` sur "Deploy - Leopardo RH" | Cohérent, mais aucun des deux ne vérifie `conclusion == 'success'` du déploiement `deploy-api` spécifiquement (seulement du workflow parent) | 🟡 Moyen |
| Secrets vs Variables | Bon usage global de `vars.*` pour les seuils non sensibles, mais pas de documentation centralisée des secrets requis par workflow (dispersés dans `AUDIT.md`) | 🟢 Faible |
| Bonnes pratiques présentes | `concurrency` avec `cancel-in-progress` cohérent, `permissions` least-privilege déclarées sur presque tous les workflows top-level, path filters sur la plupart des CI, `continue-on-error` + gates explicites bien utilisés dans `tests.yml`, secrets validés avant usage (`database-backup.yml`, `deploy-main.yml`) | 🟢 Positif |

**Total : 3 bugs actifs cassant des jobs, 1 dette de sécurité supply-chain notable, 1 duplication structurelle majeure, plusieurs incohérences de versioning.**

---

## 1. 🔴 Bugs actifs (cassent des workflows aujourd'hui)

### 1.1 `tests.yml` — fragment de script orphelin (lignes ~740-800)

Constat outillé (`actionlint` + `shellcheck`) :

```
tests.yml:749:9: shellcheck SC1089 error: Parsing stopped here. Is this keyword correctly matched up?
tests.yml:749:92: property "mobile_smoke_build" is not defined in object type {...}
tests.yml:779:13: property "mobile_analyze" is not defined ...
tests.yml:785:13: property "mobile_tests" is not defined ...
tests.yml:791:13: property "mobile_coverage_gate" is not defined ...
tests.yml:797:13: property "mobile_smoke_build" is not defined ...
```

Le job `backend-coverage` se termine à la ligne 751 par `exit 1`, puis un bloc orphelin apparaît :

```yaml
      - name: Fail coverage job if threshold gate failed
        if: steps.backend_coverage_gate.outcome == 'failure'
        run: |
          echo "Backend coverage threshold not met."
          exit 1

            echo "- Smoke build: ${{ steps.mobile_smoke_build.outcome }}"
          } > "${summary_file}"

      - name: Upload mobile quality summary
        ...
      - name: Fail mobile job if analyze failed
        if: steps.mobile_analyze.outcome == 'failure'
        ...
```

Ces 5 derniers steps du job `backend-coverage` référencent des `steps.mobile_*` qui **n'ont jamais été définis dans ce job** (probablement les restes d'un ancien job `mobile-tests` supprimé lors d'un refactor, mal fusionné). Conséquence :
- Le YAML reste syntaxiquement valide (les steps existent, juste avec des conditions toujours fausses), donc **le workflow ne casse pas au parsing**, mais ces 5 steps sont du code mort qui s'exécute à chaque run, uploade un artefact `legacy-mobile-quality-summary` vide/inexistant, et pollue les logs avec des `if` toujours skippés.
- Risque réel : un futur refactor qui renomme `backend_coverage_gate` cassera silencieusement ces conditions déjà cassées, sans qu'on s'en rende compte.

**Recommandation** : supprimer entièrement ce fragment orphelin (5 steps, lignes ~753-800) du job `backend-coverage`. La CI mobile "légitime" est déjà gérée par `mobile-apps-ci.yml`.

### 1.2 `release.yml` — référence à `front/mobile` supprimé

```yaml
      - name: Build legacy release APK
        working-directory: front/mobile
        run: |
          flutter pub get
          flutter build apk --release ...
```

`front/mobile` a été supprimé du repo dans le commit `cdc9a6a8` (#754, "Feat/p0 commercial conversion") au profit de `front/mobile_apps/{employee,manager,hr,platform_admin,core}`. Tout push d'un tag `v*` va faire échouer le job `build-artifacts` sur `working-directory: front/mobile` (dossier inexistant) — bien que `create-release` (le job qui crée réellement la release GitHub) réussisse avant, donc l'échec est visible mais moins visible qu'un blocage total.

**Recommandation** : soit retirer complètement ce job legacy (les APKs multi-app sont déjà distribués via `mobile-distribute.yml`), soit le réécrire en matrice sur `front/mobile_apps/*` comme le fait `mobile-distribute.yml`.

### 1.3 `dependabot.yml` — chemin `pub` inexistant + couverture npm incomplète

```yaml
  - package-ecosystem: "pub"
    directory: "/mobile"       # ❌ n'existe pas, devrait être un des front/mobile_apps/*
    ...
  - package-ecosystem: "npm"
    directory: "/api"          # npm dans /api sert le tooling frontend Laravel (Vite), pas les vrais front-ends
```

Conséquences :
- **Aucune mise à jour Dependabot n'est jamais proposée pour les apps Flutter** (5 pubspec.yaml sous `front/mobile_apps/*`), alors que Dependabot pour `pub` est configuré mais pointe dans le vide.
- **`front/web` (Next.js, exposé publiquement), `front/admin-dashboard` (Vue) et `front/web-offline` n'ont aucune surveillance de vulnérabilités npm automatisée.** Seul `api/package.json` (assets Vite du backend) est couvert.
- Aucun écosystème `github-actions` n'est déclaré : les versions d'actions tierces (`shivammathur/setup-php@v2`, `subosito/flutter-action@v2`, etc.) ne reçoivent jamais de PR de mise à jour automatique, y compris pour des CVE.

**Recommandation** : reconfigurer `dependabot.yml` avec un `directory` par app Flutter réelle, ajouter 3 entrées `npm` (`front/web`, `front/admin-dashboard`, `front/web-offline`), ajouter un écosystème `github-actions` avec `directory: "/"`.

---

## 2. 🟠 Dette de sécurité supply-chain

### 2.1 Pas de pinning par SHA sur les actions tierces (non-GitHub)

Actions concernées, épinglées par tag mutable :
- `shivammathur/setup-php@v2`
- `subosito/flutter-action@v2`
- `treosh/lighthouse-ci-action@v10`
- `wzieba/Firebase-Distribution-Github-Action@v1`
- `dawidd6/action-send-mail@v3`
- `trufflesecurity/trufflehog@main` ⚠️ le plus risqué : pointe vers une branche, pas un tag

Un tag Git peut être déplacé par le mainteneur (ou par un attaquant compromettant le compte du mainteneur) sans que le SHA change de vérité — c'est le vecteur classique des attaques de la chaîne d'approvisionnement CI/CD (cf. incident `tj-actions/changed-files` 2025). Les actions officielles `actions/*` et `github/codeql-action/*` sont maintenues par GitHub et présentent un risque bien plus faible ; ce n'est pas le cas des actions tierces ci-dessus.

**Recommandation** : épingler par SHA complet (`uses: shivammathur/setup-php@<sha>  # v2.x.x`) au moins pour `trufflehog` (accès direct aux secrets du repo) et idéalement pour toutes les actions tierces non-GitHub. Dependabot peut maintenir ces SHA à jour automatiquement une fois l'écosystème `github-actions` ajouté (voir 1.3).

### 2.2 Versions d'actions GitHub incohérentes entre workflows

```
actions/checkout@v4   → architecture-check.yml, i18n-enterprise.yml, lighthouse.yml
actions/checkout@v5   → tous les autres (23 fichiers)
actions/upload-artifact@v4 → majorité
actions/upload-artifact@v5 → k6-load-smoke.yml, launch-api-profile-smoke.yml, launch-observability-smoke.yml, deploy-main.yml (mobile artifact)
```

Pas un bug en soi, mais une incohérence qui complique la maintenance et peut cacher des comportements différents entre workflows (v5 de `checkout` change par exemple le comportement par défaut de `persist-credentials`). À uniformiser sur la dernière version stable partout.

### 2.3 CodeQL PHP non fonctionnel

```yaml
  analyze-backend:
    name: CodeQL (Backend)
    steps:
      - name: Backend CodeQL compatibility note
        run: |
          cat >> "$GITHUB_STEP_SUMMARY" <<'EOF'
          ## CodeQL (Backend)
          This repository's backend is PHP, but GitHub CodeQL does not recognize `php`
          as a supported language in this workflow environment.
          ...
```

Ce job ne fait qu'écrire un message dans le step summary — **aucune analyse SAST n'est réellement exécutée sur le code PHP**, alors que c'est le langage backend principal (auth, RBAC, paiements Stripe/Chargily, multi-tenant). Le commentaire indique correctement que CodeQL ne supporte pas officiellement PHP en action native, mais des alternatives existent (voir plan d'action).

### 2.4 Secret réel déjà exposé dans l'historique git (rappel, hors périmètre CI strict)

`AUDIT.md` (section finale) documente qu'un vrai mot de passe Upstash Redis a été committé en clair dans l'historique et reste récupérable par quiconque clone le repo public. Ce n'est pas un bug de configuration CI, mais cela affaiblit directement la valeur du `secret-scan.yml` (TruffleHog scanne les futurs commits, pas l'historique déjà public) : le secret exposé n'est plus détectable comme "nouveau" par TruffleHog une fois qu'il est déjà dans main depuis longtemps, sauf scan explicite `--since-commit` sur tout l'historique.

**Recommandation immédiate (hors CI, déjà notée dans AUDIT.md, toujours non cochée)** : rotation du mot de passe Upstash + purge d'historique coordonnée (BFG/filter-repo).

---

## 3. 🟠 Duplication structurelle et dette de maintenance

### 3.1 Setup PHP+PostgreSQL+Redis dupliqué 4 fois

Le bloc suivant (services `postgres`/`redis`, `.env` inline, bootstrap des schémas `public`/`shared_tenants`, migrations) est copié à l'identique dans :
- `tests.yml` job `backend-tests` (~90 lignes)
- `tests.yml` job `backend-coverage` (~90 lignes, quasi identique)
- `coverage-gate.yml` (~90 lignes)
- `backend-jobs-ci.yml` (~90 lignes)

= environ **360 lignes dupliquées** dans le repo pour la même logique. Un reusable workflow `_setup-php.yml` existe déjà et couvre l'essentiel (PHP, cache Composer, `.env`, install) mais :
1. N'est appelé par personne.
2. Ne couvre pas le bootstrap spécifique multi-tenant (`shared_tenants` schema + migrations `public`/`tenant`).

**Impact concret** : quand la stratégie multi-tenant a changé (ajout du schema `shared_tenants`), il a fallu modifier ce bloc à 4 endroits. Un oubli à l'un des 4 endroits produirait une CI qui teste sur une base de données mal migrée, silencieusement.

### 3.2 `_setup-flutter.yml` mort également

```yaml
# _setup-flutter.yml (reusable, jamais appelé)
```
`mobile-apps-ci.yml`, `mobile-distribute.yml`, `deploy-main.yml` (distribute-mobile), et `release.yml` répètent chacun `actions/setup-java@v5` + `subosito/flutter-action@v2` avec des paramètres quasi identiques.

### 3.3 Logique de détection de changements (`paths-filter` maison) dupliquée avec des regex divergentes

`tests.yml` (`detect-changes`) et `deploy-main.yml` (`Detect changed areas for deployment gating`) réimplémentent chacun, en bash, une détection de fichiers changés via `git diff`/`git show`, avec des motifs `grep -E` presque identiques mais **pas strictement synchronisés** :

```
tests.yml:        '^(front/admin-dashboard/|\.github/workflows/web-ci\.yml)'
deploy-main.yml:  '^(front/admin-dashboard/|\.github/workflows/web-ci\.yml|\.github/workflows/deploy-main\.yml|docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS\.md|docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS\.md)'
```

Ce n'est pas un bug aujourd'hui (le second est un sur-ensemble raisonnable du premier), mais c'est fragile : toute future modification de périmètre "web_changed" doit être répercutée manuellement aux deux endroits, sans garde-fou. L'action officielle `dorny/paths-filter` (ou équivalent) éliminerait cette classe de bug pour un coût de maintenance quasi nul.

---

## 4. 🟡 Fiabilité des pipelines de déploiement

### 4.1 Deux pipelines de déploiement faiblement coordonnés

- `deploy-main.yml` : déclenché par `workflow_run` sur `Tests - Leopardo RH` et `Web CI - Leopardo Admin`, avec revérification explicite des conclusions via l'API GitHub (`github-script`) — **design solide et défensif** (commentaire en dur explique le raisonnement de sécurité, bon signe).
- `deploy-staging.yml` : déclenché indépendamment sur `push: [main]`, avec sa propre logique de polling manuel (boucle JS jusqu'à 10 minutes cherchant un check nommé contenant "Backend" et "PHP") pour attendre la CI.

Problèmes :
- Ces deux workflows peuvent démarrer en parallèle sur le même push et déployer sur Render à des moments différents sans se coordonner (pas de `needs` croisé, pas de `concurrency` partagée).
- Le polling de `deploy-staging.yml` cherche un check dont le nom contient `"Backend"` ET `"PHP"` — fragile si le nom du job change (ex. renommage `"Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)"` → cassé silencieusement si quelqu'un renomme juste ce job).
- En cas de timeout (10 min), `deploy-staging.yml` déploie **quand même**, de manière "optimiste" (`core.warning('CI did not complete in time. Deploying anyway (optimistic).')`) — c'est documenté et intentionnel, mais c'est un choix de fiabilité risqué à faire valider explicitement par l'équipe plutôt que de le laisser en config par défaut.

### 4.2 `owasp-zap.yml` et `e2e-staging.yml` ne vérifient pas quel job du déploiement a réussi

Les deux se déclenchent sur `workflow_run` de `"Deploy - Leopardo RH"` avec `types: [completed]` et vérifient `github.event.workflow_run.conclusion == 'success'`. Mais `deploy-main.yml` a 3 jobs (`prepare`, `deploy-api`, `distribute-mobile`) : la "conclusion" du workflow parent est un succès seulement si tous les jobs déclenchés réussissent, ce qui est correct dans ce cas précis — mais si `distribute-mobile` échoue pour une raison indépendante de l'API (ex: build Flutter cassé), tout le workflow "Deploy" est marqué en échec, et `owasp-zap.yml`/`e2e-staging.yml` sont alors **skippés même si le déploiement API a réellement réussi**. Couplage excessif entre le statut mobile et le statut du smoke-test API.

---

## 5. 🟢 Points forts à conserver

- **Permissions least-privilege** déclarées explicitement sur presque tous les workflows (`contents: read` par défaut, élévation ciblée uniquement où nécessaire : `contents: write` pour auto-fix Pint/release, `security-events: write` pour CodeQL).
- **Path filters** bien utilisés sur la majorité des workflows pour éviter les runs inutiles (corrigé depuis l'audit fonctionnel du 2026-07-01).
- **Secrets validés avant usage** avec message explicite si absent (`database-backup.yml`, `deploy-main.yml`, `deploy-staging.yml`) plutôt que d'échouer avec une erreur curl cryptique.
- **Gate de déploiement défensif** dans `deploy-main.yml` avec commentaire explicite sur le raisonnement anti-fork/anti-pull_request_target.
- **Coverage gate progressif** documenté (`BACKEND_COVERAGE_MIN` configurable, avec objectif de ratchet vers 65%).
- **`database-backup.yml`** bien conçu : chiffrement `age` optionnel, validation de secrets avant exécution, séparation backup quotidien / drill mensuel de restauration.
- **CODEOWNERS** et **Dependabot** existent (même si mal configurés pour ce dernier) — la structure gouvernance est en place, il manque juste la maintenance.

---

## 6. Références croisées

- Audit fonctionnel précédent : `AUDIT.md` (section 6, 2026-07-01/07-05) — les points CI qu'il listait (paths filter, pattern `web_changed`) ont bien été corrigés depuis ; ce document ne les retraite pas.
- Audit sécurité API en cours sur la branche `audit/api-security-2026-07-19` (hors périmètre CI, complémentaire).
- Sortie brute `actionlint` : voir `docs/validation/actionlint-2026-07-19.txt` (généré par cet audit, à archiver).
