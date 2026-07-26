# 🔍 Audit global consolidé — Leopardo RH
> Généré le 2026-07-26 par KiloClaw | Périmètre : code complet (API Laravel, front/web Next.js, front/admin-dashboard Vue, apps mobiles Flutter, edge, CI/CD), historique git complet, GitHub API en direct (Dependabot, Code Scanning, Secret Scanning, PRs/issues ouverts, statut des checks sur `main`), et un test en direct (GET) sur l'instance de production.
>
> **Méthode** : ce dépôt contient déjà ~30 documents d'audit internes très détaillés (`docs/audits/`, `docs/security/`, `docs/external-audits/`, `docs/PLAN_ACTION2/`). Plutôt que de dupliquer ce travail, cet audit (1) vérifie l'état **réel actuel** du code par rapport aux points déjà documentés, (2) exécute des scans automatisés qui n'apparaissent dans aucun document existant (gitleaks sur les 1331 commits, `npm audit`, GitHub Dependabot/Code Scanning API), et (3) consolide tout en une vue d'ensemble unique avec verdicts à jour.
>
> ⚠️ Accès révoqué en fin de session — token GitHub fourni par l'utilisateur, usage lecture uniquement, aucune écriture effectuée sur le dépôt (pas de push, pas de PR, pas de modification de secrets).

---

## 📋 Résumé exécutif

| Domaine | Verdict | Détail |
|---|---|---|
| Vulnérabilité critique historique (`/demo-users`) | ✅ **Corrigée et déployée** | Code + prod vérifiés en direct : `GET /api/v1/demo-users` → **404** aujourd'hui |
| SSRF webhooks, révocation tokens Sanctum, CORS, TrustProxies | ✅ **Corrigées** | Vérifiées dans le code actuel (`NotPrivateUrl.php`, `tokens()->delete()`, `cors.php`, `bootstrap/app.php`) |
| Secret Redis Upstash committé en clair (historique git) | 🔴 **Toujours exploitable** | Confirmé par `gitleaks` sur l'historique complet ; rotation Upstash + purge d'historique non prouvées |
| Dépendances vulnérables (Dependabot) | 🟠 **9 alertes ouvertes**, 2 avec correctif trivial identifié | `sharp`/`next` (high, transitif, correctif proposé ci-dessous), `dompdf` (medium/low, PR #1285 ouverte), `shell-quote` (high, `npm audit fix` sans risque) |
| CI/CD supply-chain (Code Scanning) | 🔴 **10 alertes ouvertes, non documentées ailleurs** | 2 `error` (untrusted checkout + cache poisoning) + 1 `high` + 7 `warning` sur `deploy-main.yml`/`mobile-distribute.yml` |
| État CI sur `main` | 🔴 **3 checks rouges en ce moment même** | Composer Audit, Backend tests, Coverage — cause identique aux CVE dompdf, correctif déjà en PR #1285 |
| Secret scanning GitHub | ⚠️ **Désactivé** | La fonctionnalité native GitHub n'est pas activée sur ce repo public |
| RGPD / Loi 18-07 (DZ) / Loi 09-08 (MA) | ✅ **Majoritairement conforme** | Matrice de conformité existante, 1 point PARTIEL (politique de rétention documents) |
| RBAC / isolation multi-tenant | ✅ **Bon** | Isolation par `company_id` + `search_path` PostgreSQL sanitizé, policies documentées |
| XSS/CSRF admin dashboard | ✅ **Bon** | Aucun sink actif (`v-html`, `eval`), lint durci ; risque résiduel si XSS futur + token en `localStorage` |
| SQL injection | ✅ **Bon** | Aucune injection active trouvée, garde-fous documentés pour tri/filtre dynamique |

**Priorité n°1 réelle aujourd'hui** : ce n'est plus `/demo-users` (déjà corrigé) — c'est **le workflow `deploy-main.yml`**, qui a un pattern `workflow_run` + checkout privilégié flaggé par GitHub Code Scanning comme risque de cache poisoning / exécution de code non fiable en contexte privilégié, et **les 3 checks rouges actuels sur `main`** qui bloquent la confiance dans le pipeline de déploiement.

---

## 1. 🔴 Fuite historique — mot de passe Redis Upstash (toujours active)

Déjà documentée en détail dans `docs/security/SECURITY_INCIDENT_REDIS_2026-07.md` et `docs/audits/AUDIT.md` §2.3. Confirmation indépendante par scan complet de l'historique git avec `gitleaks` (1331 commits, ~34 MB) :

- Le mot de passe réel apparaît en clair dans plusieurs commits historiques (confirmé par `git log -p` local, non reproduit ici).
- Le code/documentation **actuels** (HEAD) sont propres — aucun secret réel dans l'arbre de travail actuel (vérifié par grep ciblé).
- **Aucune preuve dans le repo qu'une rotation Upstash ait eu lieu** ni qu'un `git filter-repo`/BFG ait été exécuté. L'historique reste public et clonable.

**Statut** : reste la seule action **P0 réellement non résolue** de tout l'historique d'audit du projet. Nécessite un accès humain aux dashboards Upstash/Render (hors périmètre de cet agent) et une décision coordonnée de `push --force` sur `main`.

---

## 2. 🔴 NOUVEAU — Alertes GitHub Code Scanning non documentées ailleurs (CI/CD Actions)

Ce point n'apparaît dans **aucun** document d'audit existant du dépôt. Trouvé via l'API GitHub Code Scanning (`/code-scanning/alerts`) :

| # | Règle | Sévérité | Fichier | Lignes |
|---|---|---|---|---|
| 28 | `actions/untrusted-checkout/critical` | error | `.github/workflows/deploy-main.yml` | 304-309 |
| 1 | `actions/untrusted-checkout/high` | error | `.github/workflows/deploy-main.yml` | 125-130 |
| 2 | `actions/cache-poisoning/code-injection` | error | `.github/workflows/deploy-main.yml` | 49 |
| 15,16,20 | `actions/excessive-secrets-exposure` | warning | `.github/workflows/deploy-main.yml` | 313, 404, 419 |
| 17,18,21,22 | `actions/excessive-secrets-exposure` | warning | `.github/workflows/mobile-distribute.yml` | 143, 279, 293, 164 |

### 2.1 Checkout de code non fiable en contexte privilégié (`error`, x2)

`deploy-main.yml` est déclenché par `workflow_run` (qui s'exécute avec les secrets du dépôt de base même quand le workflow amont provient d'une PR de fork) puis fait `actions/checkout@v7` avec `ref: ${{ steps.context.outputs.sha }}` — un SHA calculé à partir de `github.event.workflow_run.head_sha`.

Le workflow contient déjà une défense en profondeur commentée explicitement dans le code (lignes 60-67) : il vérifie `WR_EVENT == 'push'`, `WR_HEAD_BRANCH == 'main'` et `WR_HEAD_REPO == BASE_REPO` avant de considérer le run comme déployable. **Cette logique est correcte en intention** mais CodeQL/Zizmor la flague quand même parce que :
- Le `checkout` a lieu **avant** que `Verify required workflow conclusions for deployment SHA` (l'étape qui revalide via l'API GitHub les conclusions réelles des workflows requis) ne s'exécute — il existe une fenêtre où du code a déjà été checkouté sur la base d'un `head_sha` qui n'a, à ce stade du job, été validé que par des champs de l'event `workflow_run` (falsifiables en théorie si un attaquant contrôle un ancien commit sur une branche qui a existé sous le nom `main` — cas non applicable ici mais l'outil ne peut pas le prouver statiquement).
- Le second job `distribute-mobile` refait un `checkout@v7` avec `ref: ${{ needs.prepare.outputs.sha }}` (ligne 304-309) **après** que `deploy-api` ait tourné avec des secrets de production (`RENDER_DEPLOY_HOOK_URL`), dans le même run privilégié — c'est ce second checkout qui déclenche l'alerte `critical`.

**Recommandation** : ce n'est probablement pas une faille active exploitable aujourd'hui (les 3 conditions de garde sont correctes), mais l'outil a raison de la signaler comme pattern fragile. Actions concrètes :
1. Envisager de remplacer `workflow_run` par un `pull_request_target` combiné à un environment de déploiement protégé avec required reviewers, ou par un déclenchement direct `push` sur `main` avec un `concurrency` group (déjà présent) — ce qui élimine la classe de bug entière au lieu de la mitiger par validation de champs.
2. À défaut, déplacer la vérification des conclusions de workflow (`workflow_gate`) **avant** le premier `checkout`, en s'appuyant uniquement sur l'API (pas sur les champs de l'event) pour résoudre le SHA à checkouter.
3. Documenter explicitement dans le repo (comme fait pour les autres findings) pourquoi le risque résiduel est jugé acceptable, ou corriger — actuellement ce n'est **écrit nulle part**, contrairement à tous les autres audits de sécurité du projet qui documentent systématiquement leurs findings.

### 2.2 Cache poisoning via injection de code non privilégié

Ligne 49 : `WR_HEAD_BRANCH: ${{ github.event.workflow_run.head_branch }}` est injecté directement comme variable d'environnement shell dans un `run:` — un nom de branche contrôlé par un contributeur externe (dans un fork qui déclenche le workflow amont) pourrait théoriquement contenir des métacaractères shell exploités dans le contexte du job `prepare` (qui a `actions/cache` implicite via setup actions en aval). Le code utilise déjà `env:` (bonne pratique, évite l'injection directe dans le YAML `run:` string), donc l'exploitabilité réelle est faible, mais l'usage de `set -euo pipefail` + comparaisons `[[ ... ]]` sans guillemets sur une valeur potentiellement contrôlée par un attaquant reste le pattern que l'outil signale.

**Recommandation** : mineure, mais par défense en profondeur, valider `WR_HEAD_BRANCH` contre un pattern strict (`^[A-Za-z0-9_./-]+$`) avant toute utilisation, même si le comportement actuel (comparaison stricte `== "main"`) limite déjà l'impact.

### 2.3 Excessive Secrets Exposure (7 warnings)

`secrets[matrix.app.firebase_secret]` (accès dynamique à un secret par nom calculé) expose **tous** les secrets du repo/organisation au runner plutôt qu'un sous-ensemble explicite — limitation connue de GitHub Actions quand on utilise l'indexation dynamique `secrets[...]`. C'est un pattern répandu et il n'y a pas d'alternative native simple pour un déploiement matriciel par app mobile sans dupliquer le job 3 fois. Risque réel : si une des étapes du job est compromise (dépendance npm/action tierce malveillante), elle a un accès potentiel à la lecture de process env qui contient tous les secrets, pas seulement `FIREBASE_TOKEN`.

**Recommandation** : acceptable en l'état pour un repo de cette taille, mais à surveiller si le nombre de secrets sensibles (Stripe, DB) augmente. Alternative : un environment GitHub dédié par app mobile avec ses propres secrets scopés, ou passer par un service centralisé (Vault/1Password CI) qui ne matérialise qu'un secret à la fois.

**Aucune correction n'a été appliquée dans cette session** (audit en lecture uniquement) — ces 10 alertes doivent être triées par le mainteneur, probablement dans une future itération du travail déjà entamé en PR #875 (durcissement supply-chain CI, déjà mergée d'après `docs/external-audits/ORION_AUDIT_2026-07-19.md`, mais visiblement pas suffisante pour ces alertes spécifiques puisqu'elles sont toujours ouvertes).

---

## 3. 🟠 État réel de `main` — 3 checks rouges au moment de l'audit

Vérifié via l'API GitHub (`GET /commits/main/check-runs`) sur le HEAD actuel (`4e58bfe9`, 2026-07-26T00:18:25Z) :

- ❌ `Backend Security (Composer Audit)` — échoue avec 6 avisories `dompdf/dompdf` (CVE-2026-55550 à 55555, medium/low)
- ❌ `Backend Coverage (PHPUnit)`
- ❌ `Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)`
- ✅ `Backend Quality (Pint + PHPStan)`, `CodeQL (Backend)` — verts

**Ce n'est pas un point mort** : une PR corrective (**#1285**, ouverte le 2026-07-26 par le mainteneur lui-même, ~2h avant cet audit) bump déjà `dompdf/dompdf` 3.1.5→3.1.6 et corrige 4 erreurs PHPStan/types associées. Le log CI confirme que le point de blocage exact correspond aux CVE listées dans la PR. **Recommandation : merger #1285** — elle répond exactement au problème constaté, aucune action supplémentaire nécessaire de mon côté.

Confirmé via `GET /repos/.../dependabot/alerts?state=open` que ces mêmes 6 avisories `dompdf` (2 medium, 1 low visibles en double + duplication de sévérité) sont bien dans les 9 alertes Dependabot ouvertes.

---

## 4. 🟠 Dépendances vulnérables — État consolidé (Dependabot + `npm audit` local)

| Package | Écosystème | Sévérité | Statut |
|---|---|---|---|
| `dompdf/dompdf` (6 CVE) | Composer | medium/low | 🟡 Correctif en PR #1285 (non mergée) |
| `sharp` (via `next` optionalDependencies, CVE-2026-33327/28, 33590/91 libvips) | npm (`front/web`, `front/web-offline`) | high | 🔴 Non traité — `sharp@0.34.5` < 0.35.0 requis ; `npm audit fix --force` proposerait un downgrade `next@14.2.35` (régression majeure, **à ne pas appliquer aveuglément**) |
| `shell-quote` | npm (`api/`, transitif via `concurrently` devDependency) | high | 🟢 Correctif trivial disponible — voir ci-dessous |

**Sur `sharp`/`next`** : la version actuelle (`sharp@0.34.5`, requis par `next@16.2.11`/`16.2.12` comme `optionalDependency`) est bien **antérieure** à la version corrigée (`0.35.0+`). `npm view` confirme que `sharp@0.35.3` existe. Le correctif Dependabot suggéré (`npm audit fix --force`) propose de **downgrader Next.js vers 14.2.35**, ce qui serait une régression majeure incorrecte — il faut plutôt forcer `sharp` à `^0.35.3` via `overrides`/`resolutions` dans `package.json` en gardant `next@16.2.12`, puis vérifier que `next@16.2.x` accepte bien `sharp >=0.35` (à valider en CI, pas fait dans cette session faute d'environnement d'exécution Next.js complet).

**Recommandation immédiate** :
```json
// front/web/package.json et front/web-offline/package.json
"overrides": {
  "sharp": "^0.35.3"
}
```
Puis `npm install` + build de vérification.

**Sur `shell-quote`** (localisé après investigation complémentaire) : la première passe de cette
audit avait utilisé `npm audit --omit=dev`, ce qui masquait cette alerte car `shell-quote` est un
transitif de `concurrently` (`^10.0.3`), lui-même uniquement en `devDependencies` de `api/package.json`
(utilisé par le script `composer.json` `dev` pour lancer `php artisan serve` + `queue:listen` + `pail` +
`vite` en parallèle — jamais exécuté en production). Contrairement à `sharp`, **aucun changement
breaking n'est nécessaire** : `npm audit fix` (sans `--force`) résout proprement les deux vulnérabilités
(`shell-quote` + `concurrently`) en mettant à jour vers une version non-vulnérable de `concurrently`
dans la même plage majeure. Vérifié par `npm audit fix --dry-run` dans `api/` :
```
added 91 packages, and audited 92 packages
# npm audit report
found 0 vulnerabilities
```
**Action** : `cd api && npm audit fix` — sûr, sans risque de régression (dev-only), à appliquer sans délai.

---

## 5. ⚠️ Secret scanning GitHub natif désactivé

`GET /repos/kitokoh/leopardo-hr/secret-scanning/alerts` retourne `"Secret scanning is disabled on this repository."` — sur un **repo public** ayant déjà eu un incident de fuite de secret réel dans son historique (§1), c'est une lacune de defense-in-depth simple à corriger : GitHub secret scanning (push protection) est gratuit sur les repos publics et aurait bloqué la fuite Upstash au moment du commit si activé plus tôt.

**Recommandation** : activer Settings → Code security → Secret scanning + Push protection. Action purement administrative GitHub (pas de code), à faire par le/la propriétaire du repo.

---

## 6. ✅ Points déjà audités et confirmés à jour (vérification indépendante)

Vérification factuelle sur le code actuel (pas seulement lecture des rapports précédents) pour les points suivants, tous confirmés **toujours corrects** :

| Point | Vérifié comment | Résultat |
|---|---|---|
| `/api/v1/demo-users` gate | Lecture code `DemoUserController.php` + `GET` réel en prod | ✅ 404 en code et en prod |
| SSRF webhooks | Présence `app/Rules/NotPrivateUrl.php` + test `WebhookSsrfGuardTest.php` | ✅ Présents |
| Révocation tokens Sanctum au changement de mot de passe | `grep tokens()->delete()` dans `EmployeeService.php` | ✅ Présent |
| CORS `allowed_headers` restreint | Lecture `config/cors.php` | ✅ Liste explicite, pas de `*` |
| `trustProxies()` explicite | Lecture `bootstrap/app.php` | ✅ Présent ligne 58 |
| `demo_mode_enabled` défaut `false` | Lecture `config/app.php` | ✅ `env('DEMO_MODE_ENABLED', false)` |
| Aucun `.env` réel jamais committé | `git log --all --diff-filter=A --name-only \| grep .env$` + `git ls-files` | ✅ Aucun résultat |
| Aucun secret réel dans l'arbre de travail actuel | `grep -rn upstash.io` sur HEAD | ✅ Seulement des placeholders |

Ces points ne nécessitent **aucune action supplémentaire** — le travail de remédiation déjà effectué par l'équipe/les agents précédents est solide et vérifié indépendamment ici.

---

## 7. Composants non ré-audités en détail dans cette session

Par souci de non-duplication, les domaines suivants ont des documents d'audit dédiés déjà existants et jugés à jour (pas de raison de les refaire) :

- **RGPD / Loi 18-07 DZ / Loi 09-08 MA** → `docs/security/MATRICE_CONFORMITE_RGPD_LOI_18_07.md` (1 point PARTIEL : politique de rétention documents à formaliser au-delà de la commande `audit:purge`)
- **RBAC / matrice de routes** → `docs/security/RBAC_ROUTE_MATRIX.md`, `docs/security/RBAC_SYSTEM.md`, `docs/architecture/RBAC_AUDIT_REPORT.md`
- **XSS/CSRF admin dashboard** → `docs/security/ADMIN_CSRF_XSS_AUDIT.md` (bon état, risque résiduel documenté : token en `localStorage`)
- **Injection SQL** → `docs/security/SQL_INJECTION_AUDIT.md` (aucune injection trouvée, garde-fous documentés)
- **Accessibilité WCAG** → `docs/security/WCAG_ACCESSIBILITY_AUDIT.md`
- **Architecture modules DDD / dette technique** → `docs/PLAN_ACTION2/08_AUDIT_ARCHITECTURE_TECH.md`, `09_AUDIT_MODULES_API_STRUCTURE.md`
- **CI/CD bugs fonctionnels (paths filters, doublons)** → `docs/audits/AUDIT_CICD_2026-07-19.md` (déjà corrigé d'après vérification code)

---

## 📦 Checklist d'actions consolidée (nouveau, priorisé)

```
[ ] 🔴 P0 — Rotation du mot de passe Redis Upstash + purge de l'historique git (déjà documenté
    depuis 2026-07-01, jamais prouvé comme effectué). Action humaine hors périmètre code.
[ ] 🔴 P0 — Activer GitHub Secret Scanning + Push Protection sur le repo (actuellement désactivé).
    Action administrative GitHub, 2 minutes, aucun coût sur repo public.
[ ] 🟠 P1 — Merger la PR #1285 (déjà ouverte par le mainteneur) : corrige les 3 checks CI rouges
    sur main (dompdf CVE + PHPStan + drift i18n).
[ ] 🟠 P1 — Corriger la vulnérabilité sharp/libvips (high) dans front/web et front/web-offline via
    un override `"sharp": "^0.35.3"` dans package.json — NE PAS utiliser `npm audit fix --force`
    (proposerait un downgrade régressif de next 16→14.2.35).
[ ] 🟠 P1 — Trier et corriger les 10 alertes Code Scanning sur deploy-main.yml/mobile-distribute.yml
    (untrusted checkout x2 error, cache poisoning x1 error, secrets exposure x7 warning) — non
    documentées dans aucun audit existant du repo à ce jour.
[ ] 🟢 P1 (rapide) — Lancer `cd api && npm audit fix` pour résoudre shell-quote/concurrently
    (high, dev-only, 0 risque de régression — vérifié par dry-run : 0 vulnérabilité restante).
[ ] 🟡 P2 — Formaliser la politique de rétention documents au-delà de `audit:purge --older-than`
    (seul point PARTIEL de la matrice RGPD).
```

---

*Audit réalisé par KiloClaw à la demande de l'utilisateur, en lecture uniquement, avec un accès
GitHub temporaire (token révoqué en fin de session). Aucune modification de code, aucun push,
aucune création de PR/issue effectuée sur le dépôt `kitokoh/leopardo-hr`.*
