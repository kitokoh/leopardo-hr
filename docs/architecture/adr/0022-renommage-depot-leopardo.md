# ADR 0022 - Renommage du dépôt `leopardo-hr` : options et plan

## Statut

**Proposé — décision owner requise.**

**Date** : 2026-09-20
**Décideurs** : propriétaire du dépôt (kitokoh) — proposition agent, issue #7848
**Réf.** : audit externe 2026-09-20 ; décision de positionnement #7428
(`docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`, `docs/REFERENTIEL_PRODUIT/MESSAGE.md`)

## Contexte

La décision #7428 (P03, 2026-09-16) a acté que Leopardo est une **suite
métier** (« business suite »), pas un « logiciel RH » : cette catégorie est
explicitement **interdite** sur toutes les surfaces de présentation. Or le
dépôt public s'appelle **`kitokoh/leopardo-hr`** — et le nom du dépôt est le
premier élément de présentation publique du projet (URL GitHub, résultats de
recherche, badges, clone command du README). `README.md` le présente d'ailleurs
comme « the open-source business suite », en contradiction frontale avec son
propre nom.

**Tension à trancher.** Le §4 de `POSITIONNEMENT_SUITE_METIER.md` a classé
`kitokoh/leopardo-hr` parmi les identifiants techniques « non renommables —
décision d'arrêt », au même titre que les bundle ids, clés Redis ou commandes
Artisan. L'audit externe (#7848) conteste ce classement : contrairement à une
clé Redis, le nom du dépôt **est** une surface de présentation. Renommer le
dépôt (options A ou C) revient donc à **amender le §4 de #7428** sur cette
seule ligne — c'est pourquoi la décision appartient à l'owner. Garder le nom
(option B) est conforme au §4 tel qu'écrit, à condition de documenter la
dérive de façon visible.

Deux mitigations rendent un éventuel renommage peu coûteux côté GitHub :

- GitHub **redirige automatiquement** les anciennes URLs d'un dépôt renommé
  (web, clones git, API REST via 301) tant qu'aucun dépôt ne reprend l'ancien
  nom.
- GitHub Pages, en revanche, **ne redirige pas** : `kitokoh.github.io/leopardo-hr`
  deviendrait `kitokoh.github.io/leopardo-suite` et l'ancienne URL casserait
  (canonical, og:url, liens du README, référencement).

## Inventaire réel des surfaces impactées

Mesuré sur le dépôt le 2026-09-20 : `rg -c 'leopardo-hr' --no-ignore --hidden -g '!.git'`
→ **378 occurrences dans 202 fichiers**, réparties ainsi :

| Catégorie | Occurrences | Fichiers | Criticité au renommage |
|---|---:|---:|---|
| Documentation (`docs/`) | 235 | 113 | Faible — URLs redirigées par GitHub ; migration par vagues |
| Racine (README badges/liens, CHANGELOG, `melos.yaml`, `package.json`, `package-lock.json`, AGENTS.md, `render.prod.yaml`, etc.) | 32 | 12 | Moyenne — vitrine du projet, badges shields.io (suivent la redirection API) |
| Outils & prompts agents (`dev-hub/`) | 40 | 22 | Moyenne — scripts `check-branch-protection.sh`, `kpi-gate.sh`, etc. passent `kitokoh/leopardo-hr` à l'API (redirection 301 suivie par `gh`/`curl -L`, mais à migrer) |
| Specs internes (`.specify/`, dont `constitution.md`) | 27 | 25 | Faible — historique ; **sauf** le titre « Leopardo HR Constitution » à corriger dans tous les cas (demande explicite de #7848) |
| Workflows CI (`.github/workflows/`) | 10 | 9 | **HAUTE** — dont **3 gardes** `if: github.repository == 'kitokoh/leopardo-hr'` (`queue-supervision.yml`, `pr-external-checks-report.yml`, `deploy-drift-guard.yml`) qui deviendraient **silencieusement fausses** après renommage → jobs désactivés sans erreur |
| API (`api/` : `openapi.yaml`, `config/cors.php`, migrations, lang) | 15 | 13 | Moyenne — `cors.php` référence l'origine Pages (voir risque Pages ci-dessous) |
| Site produit (`site/gh-pages/index.html` : canonical, og:url, og:image) | 11 | 1 | **HAUTE** — l'URL Pages change **sans redirection** |
| Front (`front/web`, `front/admin-dashboard` : footer, JSON-LD, tests) | 6 | 6 | Moyenne — liens publics et SEO |
| `scripts/README.md` | 2 | 1 | Faible |
| **Total** | **378** | **202** | |

Hors dépôt (non mesurable par `rg`, à traiter manuellement) :

- **Pulumi ESC** : environnement `solarnyxss/leopardo-hr/prod` (référencé en
  commentaire dans `render.prod.yaml`) — chemin externe, ne suit aucune
  redirection GitHub.
- **Render** : les blueprints (`render.yaml`, `render.prod.yaml`) ne
  contiennent pas le nom du repo, mais la **connexion GitHub des services
  Render** pointe vers le dépôt — à vérifier/reconnecter après renommage
  (Render suit généralement le renommage via l'id GitHub du repo, à confirmer
  dans le dashboard).
- **Vercel** (`front/web`, `front/travel-web`, `front/marketplace`) : même
  situation — les `vercel.json` ne citent pas le repo, mais la liaison Git des
  projets Vercel doit être vérifiée.
- Éventuels liens externes (issues d'autres dépôts, favoris, articles) —
  couverts par la redirection GitHub.

## Options comparées

### Option A — Renommer en `leopardo-suite` (ou `leopardo`) + migration ciblée, redirections GitHub pour le reste

- Renommage GitHub (Settings → Rename) : anciennes URLs web/clone/API
  **redirigées automatiquement**.
- **Vague 1 obligatoire (le jour même)** : les seules surfaces réellement
  cassantes — 3 gardes `github.repository ==` dans les workflows, arguments
  des scripts `dev-hub/tools/*`, URL GitHub Pages (canonical/og + `cors.php`
  + liens README), `melos.yaml`/`package.json`.
- Vagues suivantes **opportunistes** : docs, prompts, `.specify/` — non
  urgentes, les redirections couvrent la transition.
- Coût : ~1 PR de vague 1 (± 25 fichiers) + renommage 5 min. Risque résiduel :
  perte du référencement de l'ancienne URL Pages ; ancien nom à **réserver**
  (ne jamais recréer un repo `leopardo-hr`).

### Option B — Garder `leopardo-hr`, documenter la dérive

- Conforme au §4 de #7428 tel qu'écrit (« décision d'arrêt »).
- Ajouter un encart visible dans `README.md` (« le nom du dépôt est un
  identifiant historique figé, le produit est une suite métier ») + corriger
  le titre de `.specify/constitution.md`.
- Coût quasi nul, zéro risque CI/CD. Mais la contradiction demeure sur la
  première surface publique du projet, et chaque nouvel auditeur/contributeur
  la re-signalera (coût récurrent d'explication — déjà constaté par l'audit).

### Option C — Renommage + migration complète et immédiate des 378 références

- Même renommage que A, mais migration **exhaustive** en une vague : 202
  fichiers touchés, y compris archives d'audit, migrations SQL historiques,
  QA reports datés.
- Coût élevé (PR massive, revue difficile, conflits avec les branches en
  cours), bénéfice marginal : les redirections GitHub rendent la migration
  des références documentaires non urgente, et réécrire des documents
  **datés/archivés** (audits 2026-07/08, migrations) falsifie l'historique.
- Écartée sauf exigence particulière de l'owner.

## Recommandation (agent)

**Option A — renommer en `leopardo-suite`**, avec vague 1 ciblée sur les
surfaces cassantes et migration opportuniste du reste.

Arguments :

1. Le nom du dépôt est une **surface de présentation**, pas un identifiant
   purement technique : le classer « non renommable » au §4 de #7428 était une
   sur-généralisation. Les autres lignes du §4 (bundle ids, clés Redis,
   commandes Artisan, domaine) restent intouchées — le renommage du dépôt est
   le **seul** identifiant du §4 dont le coût de changement est quasi nul
   grâce aux redirections GitHub.
2. Les seules casses réelles sont **identifiées et bornées** : 3 gardes de
   workflows, l'URL Pages, les scripts dev-hub — toutes corrigées en une PR de
   vague 1 préparée **avant** le renommage.
3. `leopardo-suite` colle à la catégorie canonique « business suite » de
   MESSAGE.md ; `leopardo` seul est aussi acceptable (plus court, mais moins
   descriptif dans une recherche GitHub).
4. L'option B laisse un coût récurrent (chaque audit externe rouvrira le
   sujet) pour économiser ~1 journée de travail unique.

Si l'owner tranche pour B, cet ADR passe en « Décidé — nom conservé » et
l'encart README + la correction de `.specify/constitution.md` deviennent le
livrable.

## Plan d'exécution de l'option A (si retenue)

**Fenêtre recommandée** : hors heures de déploiement, aucun pipeline en cours,
aucune PR critique proche du merge (les PRs ouvertes suivent le renommage,
mais les checks en cours d'exécution peuvent être perturbés). Durée totale
estimée : ≤ 2 h, dont renommage effectif ~5 min.

1. **J-1 — Préparer la PR « vague 1 »** (sur branche, mergée juste avant ou
   juste après le renommage, au choix de l'owner) :
   - `.github/workflows/` : remplacer les 3 gardes
     `github.repository == 'kitokoh/leopardo-hr'` → `kitokoh/leopardo-suite`
     (`queue-supervision.yml`, `pr-external-checks-report.yml`,
     `deploy-drift-guard.yml`) + les 7 autres occurrences (artefacts APK,
     arguments de scripts dans `branch-protection-guard.yml`,
     `issue-governance-guard.yml`, commentaire `pages-deploy.yml`).
   - `dev-hub/tools/` : slugs `kitokoh/leopardo-hr` dans les 8 scripts.
   - `site/gh-pages/index.html` : canonical, og:url, og:image, lien GitHub.
   - `api/config/cors.php` : origine Pages.
   - `README.md` : badges shields.io, liens, clone command, arborescence.
   - `melos.yaml` (`name`, `repository`), `package.json` +
     `package-lock.json` (`name`).
   - `.specify/constitution.md` + `.specify/memory/constitution.md` : titre
     « Leopardo HR Constitution » → « Leopardo Constitution » (demandé par
     #7848 dans tous les cas).
   - ⚠️ `api/`, `.github/` et `docs/REFERENTIEL_PRODUIT/` sont dans les
     chemins critiques de `check-governance.ps1` → **entrée CHANGELOG requise**
     pour cette PR de vague 1.
2. **J0 — Renommage** : Settings → Rename → `leopardo-suite`. Vérifier
   immédiatement que `git ls-remote https://github.com/kitokoh/leopardo-hr`
   redirige.
3. **J0 — Vérifications post-renommage** :
   - Déclencher manuellement `queue-supervision`, `deploy-drift-guard`,
     `pr-external-checks-report` et vérifier que les jobs **s'exécutent**
     (gardes non silencieusement fausses).
   - Dashboard **Render** : services toujours connectés au repo, déclencher un
     deploy manuel de contrôle. Dashboard **Vercel** : idem pour les 3
     projets.
   - **Pulumi ESC** : renommer/aliaser `solarnyxss/leopardo-hr/prod` ou
     documenter que le chemin ESC reste historique (choix owner ; les
     commentaires de `render.prod.yaml` suivront).
   - GitHub Pages : vérifier `kitokoh.github.io/leopardo-suite` et re-publier
     (workflow `pages-deploy.yml`).
   - Mettre à jour les remotes locaux :
     `git remote set-url origin https://github.com/kitokoh/leopardo-suite.git`.
4. **J0 — Protection de l'ancien nom** : ne **jamais** recréer un dépôt
   `leopardo-hr` sous `kitokoh` (cela tuerait les redirections).
5. **J+7 — Vague 2 (opportuniste, non bloquante)** : `docs/` actifs
   (QUICKSTART, ops runbooks, contributing), `dev-hub/prompts/`,
   `front/web` (footer, JSON-LD). Les archives datées (`docs/archive/`,
   `docs/audits/`, migrations SQL, QA reports) ne sont **pas** réécrites.
6. **Amender** `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md` §4 :
   retirer la ligne « Dépôt, badges, canonical » du tableau « non renommable »
   et référencer cet ADR (entrée CHANGELOG requise — chemin critique).

### Risques CI/CD identifiés

| Risque | Gravité | Mitigation |
|---|---|---|
| Gardes `github.repository ==` silencieusement fausses → workflows désactivés sans erreur | Haute | Vague 1 avant/avec le renommage + déclenchement manuel de contrôle (étape 3) |
| URL GitHub Pages non redirigée (SEO, canonical, CORS) | Moyenne | Vague 1 (canonical + `cors.php`) ; accepter la perte de l'ancien référencement Pages |
| Render/Vercel : liaison Git à revalider | Moyenne | Vérification dashboard + deploy de contrôle (étape 3) |
| Pulumi ESC `solarnyxss/leopardo-hr/prod` : chemin externe inchangé | Faible | Décision owner : renommer l'env ESC ou le figer comme identifiant historique |
| Checks en cours pendant le renommage | Faible | Fenêtre calme, pas de PR proche du merge |
| Recréation accidentelle d'un repo `leopardo-hr` | Faible | Consigne explicite (étape 4), notée dans AGENTS.md en vague 2 |

## Checklist de migration des références

- [ ] **Vague 1 (bloquante, avec le renommage)**
  - [ ] `.github/workflows/` : 10 occurrences / 9 fichiers (dont 3 gardes `if: github.repository ==`)
  - [ ] `dev-hub/tools/` : 8 scripts (slug passé à l'API/`gh`)
  - [ ] `site/gh-pages/index.html` : 11 occurrences (canonical, og:*, liens)
  - [ ] `api/config/cors.php` : origine Pages (2 occurrences)
  - [ ] `README.md` : 11 occurrences (badges, liens, clone, arborescence)
  - [ ] `melos.yaml`, `package.json`, `package-lock.json` : noms de paquet/monorepo
  - [ ] `.specify/constitution.md` + `.specify/memory/constitution.md` : titre
  - [ ] Entrée `CHANGELOG.md` (chemins critiques touchés)
- [ ] **Post-renommage immédiat**
  - [ ] Run manuel des 3 workflows gardés — verts et non skippés
  - [ ] Render : 2 blueprints / services connectés + deploy de contrôle
  - [ ] Vercel : 3 projets (`front/web`, `front/travel-web`, `front/marketplace`) connectés
  - [ ] Pages re-publié sur la nouvelle URL
  - [ ] Pulumi ESC : décision consignée
  - [ ] Remotes locaux / machines CI auto-hébergées mis à jour
- [ ] **Vague 2 (opportuniste)**
  - [ ] `docs/` actifs (~235 occurrences / 113 fichiers, hors archives datées)
  - [ ] `dev-hub/prompts/` : 13 fichiers
  - [ ] `front/web` + `front/admin-dashboard` : 6 occurrences
  - [ ] `AGENTS.md`, `DEVELOPMENT.md`, `CONVENTIONS.md`, `SUPPORT.md`, `SECURITY.md`, `ARCHITECTURE.md`, `scripts/README.md`
  - [ ] `POSITIONNEMENT_SUITE_METIER.md` §4 amendé (réf. cet ADR)
- [ ] **Jamais** : réécrire les archives datées (`docs/archive/`, `docs/audits/`, migrations SQL `api/database/migrations/`, QA reports) — l'historique reste tel quel.

## Décision

**À trancher par l'owner** (issue #7848) :

- [ ] **Option A** — renommer en `leopardo-suite` (recommandée) — préciser le nom retenu : `leopardo-suite` / `leopardo` / autre : ______
- [ ] **Option B** — conserver `leopardo-hr` et documenter la dérive (conforme au §4 de #7428)
- [ ] **Option C** — renommage + migration exhaustive immédiate

## Références

- Issue #7848 (audit externe 2026-09-20) — nommage du dépôt
- Issue #7428 / `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md` (décision « suite métier », §4 identifiants figés)
- `docs/REFERENTIEL_PRODUIT/MESSAGE.md` (registre canonique P03)
- GitHub Docs — *Renaming a repository* (redirections web/git/API automatiques ; Pages non redirigé)
