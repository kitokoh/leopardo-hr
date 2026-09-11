# P01 — Validation & mise sur le marché (« quand dit-on OK pour le marché ? »)

> **Statut :** ratifié v1.0 (2026-09-11) — **Dernière revue :** 2026-09-11 — revue mensuelle (dernier jour ouvré)
> **Propriétaire :** PM (décision) + gardien technique (preuves)
> **Portée :** toute sortie livrée à des utilisateurs réels : pilote encadré, bêta, disponibilité
> générale (GA), par surface et par BC vertical. Hors champ : le merge quotidien sur `main`
> (régime dev continu, voir P07) et les releases internes sans utilisateur externe.
> **Ancrage existant :** `docs/validation/RELEASE_READINESS_GATE.md` (gate 26/26),
> `docs/validation/PILOT_RELEASE_GO_NOGO_CHECKLIST.md`, `docs/GESTION_PROJET/GO_NO_GO_MVP.md`,
> `docs/architecture/RELEASE_TRAIN.md` (train hebdo + matrice de compat), `docs/GESTION_PROJET/RUNBOOK_BETA_ACCEPTANCE.md`.

## 1. Objet

Définir la **marche à suivre unique** pour déclarer un lot « bon pour le marché », avec des paliers
progressifs et des preuves datées — pour qu'un agent (ou le PM) puisse répondre sans ambiguïté à :
*« Ce qu'on vient de livrer, peut-on le mettre sur le marché ? »*

Le constat qui motive ce protocole : le dépôt dispose déjà de gates riches (26/26, Go/No-Go pilote,
train hebdo) mais **dispersés et datés** ; aucune procédure ne dit *qui* déclenche *quoi* et *quelle
preuve fraîche* est exigée à chaque palier. Ce protocole organise l'existant, il n'invente pas une
nouvelle qualité.

## 2. Paliers de sortie (vocabulaire opposable)

| Palier | Public | Déclencheur | Bloquant si absent |
|---|---|---|---|
| **Pilote encadré** | Clients pilotes nommés, contrat de pilote (`docs/focus/PILOTES.md`, `docs/pilotes/`) | Décision PM après gate pilote | Fiche de qualification pilote, runbook bêta |
| **Bêta / Early Access** | Liste d'attente, N tenants max | Décision PM | Gates P01 §4 verts, canal de feedback |
| **GA (General Availability)** | Tout le monde | Décision PM (revue de lancement) | Gates P01 §4 verts + ops prod prouvées + communication |

Une **verticale** (Restaurant, Travel, Fuel, Edu, Delivery…) passe ses propres paliers : une
verticale en pilote ne rend pas la plateforme entière GA. Règle : **on annonce le palier atteint
par surface et par BC, jamais « le produit » en bloc** (cf. `README.md` — product map par domaine).

## 3. Règles

1. **Toute sortie externe commence par une issue « Release X — <palier> »** dans GitHub Projects,
   avec la checklist du palier (template `docs/validation/PILOT_RELEASE_GO_NOGO_CHECKLIST.md`).
2. **La preuve prime sur l'intention** : chaque ligne de la checklist cite un artefact daté
   (run CI vert, rapport `docs/validation/`, runbook, capture). Une case sans preuve = rouge.
3. **Seuils de qualité automatiques (non négociables au palier GA)** :
   - checks requis GitHub Actions verts sur `main` (liste `BRANCH_PROTECTION_REQUIRED.md`) ;
   - `release-readiness.ps1 -Strict` → **26/26** (`docs/validation/RELEASE_READINESS_GATE.md`) ;
   - coverage backend ≥ 65 %, module Payroll ≥ 80 % (gates `coverage-gate.yml`) ;
   - zéro issue **P0/P1 ouverte** sur le périmètre sorti (label `P0-critical` / `P1-high`) ;
   - e2e staging verts + parcours critiques couverts (`e2e-staging.yml`, `lighthouse.yml`) ;
   - sécurité : secret scan, CodeQL, OWASP ZAP baseline verts ;
   - matrice de compat à jour (`dev-hub/tools/release-compat-matrix.json`) : aucune app supportée
     sous son plancher d'API ;
   - **honnêteté des promesses** : aucune copie publique ne promet une capacité non livrée
     (précédents : #3257 desktop, #4202 savings badge, #3863 case studies).
4. **Le train hebdomadaire est le rythme** (`docs/architecture/RELEASE_TRAIN.md`) ; un palier
   externe ne sort que sur une **Release GitHub taguée `vX.Y.Z`** (déclencheur officiel de la
   topologie prod — voir P07), jamais sur un push `main` brut.
5. **UAT verticale avant palier** : pour chaque BC vertical concerné, la recette UAT datée existe
   (`docs/ops/RECETTE_UAT_*.md`) et les retours pilotes du mois sont triés (P0/P1 traités ou
   escaladés avec mitigation acceptée).
6. **Décision tracée** : le PM publie la décision Go/No-Go sur l'issue Release (commentaire),
   avec le palier autorisé et les conditions de sortie de palier. Pas de Go oral.
7. **Toute régression découverte après sortie** déclenche le runbook approprié
   (`docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md`, rollback `RUNBOOK_ROLLBACK.md`) et une entrée
   dans `docs/ops/INCIDENTS.md` — la sortie suivante intègre la leçon.

## 4. Définitions de fait

| Niveau | Définition | Preuve minimale |
|---|---|---|
| **DoR (issue prête)** | Issue étiquetée BC + priorité, critères d'acceptation mesurables, spec si périmètre significatif | Issue conforme template `feature.yml`/`bug.yml` ; label `Agent-Ready` si exécutable sans humain |
| **DoD (tâche finie)** | Code mergé sur `main`, CI verte, tests, CHANGELOG, issue fermée par `Closes` | PR mergée + checks verts (anti-ghost-close #4859) |
| **DoMarket (palier atteint)** | Gates §3 verts + UAT verticale + décision PM tracée + promesses honnêtes | Checklist datée avec preuves, score readiness, commentaire Go sur l'issue Release |

## 5. Gardes & automatisation

| Existant | À créer | Vérifie |
|---|---|---|
| `coverage-gate.yml`, `phpstan-*`, `architecture-check.yml`, `web-ci.yml`, `e2e-staging.yml`, `lighthouse.yml`, `owasp-zap.yml`, `codeql.yml`, `secret-scan.yml`, `mobile-apps-ci.yml`, `tests.yml` | — | Qualité continue |
| `dev-hub/tools/release-readiness.ps1` (26/26), `check-release-compat.sh`, `kpi-gate.sh`, `check-pilot-gates.sh` | — | Gates agrégés |
| `release.yml` (tag → Release) + `deploy-prod.yml` | — | Déclencheur prod |
| — | **Issue : gabarit d'issue `release-gate`** (checklist palier pré-remplie) | Standardiser les sorties |
| — | **Issue : workflow `release-report.yml`** (auto-commentaire de synthèse sur l'issue Release : état CI, coverage, issues P0/P1 ouvertes) | Automatiser la collecte de preuves |

## 6. Rôles

| Rôle | Responsabilités |
|---|---|
| PM | Décide le palier, tranche les No-Go, signe la communication de sortie (voir P03) |
| Gardien technique | Produit/valide les preuves, tient la checklist, escalade les P0/P1 |
| Agents (exécution) | Livrent avec DoD prouvée ; signalent toute promesse publique non tenue (P03) |

## 7. Indicateurs & preuves

- Score readiness (26/26) à chaque palier ; écart documenté si < 26.
- Nombre de sorties sans incident P1 dans les 7 jours (cible : 0).
- Délai entre décision PM et sortie effective (cible : ≤ 1 jour ouvré, la checklist est prête avant).
- Toute sortie produit un fichier de preuve daté dans `docs/validation/` (pattern existant
  `RELEASE_READINESS_REPORT_YYYY_MM_DD.md`).

## 8. Revue mensuelle — questions spécifiques

- [ ] Les paliers (pilote/bêta/GA) correspondent-ils encore à la stratégie commerciale ?
- [ ] La dernière sortie a-t-elle respecté toutes les cases ? Laquelle a failli et pourquoi ?
- [ ] Les seuils (coverage, 26/26) sont-ils encore les bons, ou trop stricts / trop laxistes ?

## 9. Historique

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Création — consolidation des gates existants en protocole de paliers |
| v1.0 | 2026-09-11 | Ratification — audit de l'état réel du dépôt (registre `REGISTRE_PROTOCOLES.md`) |
