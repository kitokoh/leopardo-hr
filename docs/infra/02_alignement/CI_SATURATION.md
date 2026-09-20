# CI — Saturation de la file GitHub Actions (issue #2131)

> Constat : 2026-08-14 — 39+ runs `queued` et 12 `in_progress` en même temps,
> des runs `Tests - Leopardo RH`/`Backend Coverage Gate` annulés en cascade à
> chaque push, le dernier commit de `main` sans checks requis terminés pendant
> 20+ minutes.

## Analyse racine

1. **Annulation en cascade des checks REQUIS sur `main`** (cause principale) :
   `coverage-gate.yml`, `actionlint.yml` et `architecture-check.yml` portent
   les 5 checks requis de la protection de `main`. Ils se déclenchent sur
   chaque push `main` avec `cancel-in-progress: true` et un groupe par
   `ref` (`refs/heads/main`). Chaque nouveau merge annulait donc le run du
   commit précédent → le dernier commit de `main` restait `pending` (check
   requis jamais terminé) pendant toute la durée de la rafale de merges.
2. **Rafales de merges** : merges en batch (7 PR en ~3 min le 2026-08-14)
   sans merge queue → chaque merge relance toute la chaîne
   Tests → Deploy → E2E/OWASP/ZAP.
3. **E2E/OWASP** : groupe par `ref` + `cancel-in-progress: true` → chaque
   nouveau déploiement annulait le run e2e/zap du précédent (travail gaspillé
   + file saturée par les re-déclenchements).

## Merge queue — alternative documentée (critère d'acceptation n°1)

**La merge queue GitHub n'est PAS disponible sur ce plan** (vérifié via
GraphQL le 2026-08-14 : le champ `Repository.mergeQueueEnabled` n'existe pas
sur le schéma — compte personnel plan gratuit). Implémentation impossible
tant que le plan ne change pas.

Alternative appliquée (mêmes effets visés : sérialisation + non-annulation
des runs utiles) :

| Objectif merge queue | Alternative appliquée (#2131) |
|---|---|
| Sérialiser les merges | Discipline de batch : ≤ 2 PR mergées/minute, attendre le vert des 5 checks requis entre chaque merge |
| Valider le ref de merge | Triggers `merge_group` déjà présents sur les 3 workflows requis (#2032) → activation en 1 config quand le plan le permettra |
| Ne pas annuler les runs utiles | `cancel-in-progress: ${{ github.event_name == 'pull_request' }}` sur tous les workflows lourds (tests, coverage, architecture, actionlint, payroll, web-marketing, mobile) — les runs `push main`/`merge_group` ne sont JAMAIS annulés |
| Éviter les re-déclenchements en cascade | E2E/OWASP groupés par SHA du déploiement déclencheur (`workflow_run.head_sha`) au lieu de `ref` |

## Budget de runs par PR (après #2131)

- **PR docs-only** (`docs/**`, `CHANGELOG.md`, `AGENTS.md`) : uniquement les
  3 workflows requis inconditionnels (coverage-gate, actionlint,
  architecture-check) + gardes légères → **≈ 5 runs** (contre 12+ avant).
- **PR backend réelle** : + Tests, Payroll CI, coverage jobs → **≤ 12 runs**.
- **PR front/web** : + web-marketing-ci, lighthouse (filtres `paths:` déjà
  présents) → **≤ 12 runs**.
- **Merge sur `main`** : les runs `push main` des checks requis ne sont plus
  annulés → les 5 checks requis sont verts ≤ 15 min après le merge (constat
  à re-vérifier sur la PR #2131 elle-même).

## Vérification (critère d'acceptation n°4)

- PR docs-only de test : compter les runs lancés (≤ 12) et vérifier qu'aucun
  run `Backend Coverage`/`Tests` n'est annulé par churn.
- PR backend réelle : idem.
- Après merge : vérifier que les 5 checks requis de `main` passent au vert
  ≤ 15 min.

## Fichiers touchés

- `.github/workflows/coverage-gate.yml`, `actionlint.yml`,
  `architecture-check.yml`, `payroll-ci.yml`, `web-marketing-ci.yml`,
  `mobile-apps-ci.yml` — `cancel-in-progress` conditionnel.
- `.github/workflows/e2e-staging.yml`, `owasp-zap.yml` — groupe par SHA.
- Issu : #2131 (Closes). Contexte historique : #1903 (constat), #2032
  (merge_group partiel), #2105/#2132 (path filters sur checks requis).

---

# Plan de désaturation CI (issue #7846, audit externe 2026-09-20)

> Audit réel du 2026-09-20 : 74 fichiers de workflows (77 workflows actifs côté
> API dont 2 dynamiques), ~30 guards/reports de gouvernance, **18 workflows
> planifiés** (20 crons). Données runs vérifiées via
> `GET /repos/kitokoh/leopardo-hr/actions/workflows/{id}/runs?per_page=100`.

## 1. Inventaire des crons (état avant #7846)

| Workflow | Cron | Runs/jour | Nature | Constat runs (100 derniers) |
|---|---|---:|---|---|
| `queue-supervision.yml` | `*/5 * * * *` | 288 | Supervision prod (sonde HTTP sans credentials) | 99 success — **CONSERVÉ** : justification écrite DoD #5282 (panne queue détectée < 15 min : intervalle 5 min + seuil stale 10 min) |
| `ci-observability.yml` | `*/10 * * * *` → `7 * * * *` | 144 → 24 | Rapport informatif non bloquant | 100 success, aucune alerte critique — **ESPACÉ dans cette PR** (#7846) |
| `deploy-drift-guard.yml` | `13,43 * * * *` | 48 | Garde drift deploy | **33 failure / 6 success** : garde rouge en boucle = bruit ; corriger la cause ou espacer à 2 h (étape 2) |
| `launch-observability-smoke.yml` | `*/30 * * * *` | 48 | Sonde surfaces publiques | 74 success / 26 failure : candidat horaire (étape 2) |
| `cleanup-orphan-runs.yml` | `17 */2 * * *` | 12 | Ménage file Actions | **95/100 runs CANCELLED** (concurrency) : le ménage se neutralise lui-même ; passer quotidien (étape 2) |
| `deploy-main-catchup.yml` | `23 * * * *` | 24 | Rattrapage deploy | 72/72 success (quasi toujours no-op) : candidat 4×/jour (étape 2) |
| `admin-pages-deploy-guard.yml` | `17 */6 * * *` | 4 | Garde deploy admin | acceptable |
| Quotidiens : `branch-hygiene`, `branch-protection-guard`, `bc-batch-branch-protocol`, `crm-branch-protocol`, `issue-governance-guard`, `database-backup` (×2) | 1×/jour | ~7 | hygiène/sécurité/backup | acceptables |
| Hebdo/mensuels : `codeql`, `secret-history-scan`, `fix-feat-ratio-report`, `lighthouse`, `ci-saturation-report`, backup mensuel | — | <1 | sécurité/rapport | acceptables |

## 2. Actions implémentées dans la PR #7846 (étape 1 — sans risque)

1. **`deploy-staging.yml` SUPPRIMÉ** (9,6 Ko de code mort). Vérifié :
   aucun staging provisionné (#7256/#1485), dispatch-only depuis 2026-09-13
   et **zéro run depuis** (dernier run : push du 2026-09-13, gate
   skipped-green) ; aucune référence active dans `.github/workflows/**`
   (seul un commentaire historique dans `branch-protection-guard.yml` et la
   ligne du README, mise à jour pour garder la trace). Restauration :
   `git log -- .github/workflows/deploy-staging.yml`.
2. **`e2e-staging.yml` ne teste plus la prod automatiquement** : le
   déclencheur `workflow_run` post-deploy (Playwright contre la prod
   free-tier Render à CHAQUE push main, 5 119 runs cumulés, 11 échecs sur
   les 100 derniers, throttling 429 documenté #7317) est désactivé avec
   commentaire justifié ; `workflow_dispatch` conservé pour le smoke à la
   demande. Ré-activation conditionnée à un staging réel.
3. **`ci-observability.yml` : `*/10` → horaire** (144 → 24 runs/jour).
   Rapport purement informatif, non bloquant, 100 % success sur 100 runs.
4. **`queue-supervision.yml` `*/5` VOLONTAIREMENT INCHANGÉ** : supervision
   d'incident prod avec justification écrite (DoD #5282 : détection < 15 min ;
   à `*/10`, le pire cas passe à 20 min > DoD). Sonde HTTP sans checkout ni
   secret sensible (#7694) — coût unitaire minimal.

Gain étape 1 : ~120 runs planifiés/jour en moins + fin des runs E2E prod par
push (≈ 10-30/jour selon les rafales de merge) + 1 workflow mort en moins.

## 3. Étapes suivantes (hors périmètre de cette PR — issues dédiées)

### Étape 2 — crons restants (gain ~60 runs/jour, risque faible)
- `deploy-drift-guard.yml` : d'abord CORRIGER le rouge chronique (33/100
  failure) puis `13,43 * * * *` → `13 */2 * * *` (48 → 12/jour).
- `cleanup-orphan-runs.yml` : `*/2 h` → quotidien (12 → 1/jour) ; 95 % des
  runs s'annulent entre eux, la valeur marginale est nulle.
- `launch-observability-smoke.yml` : `*/30` → horaire (48 → 24/jour) après
  analyse des 26 % d'échecs (cold start Render vs vraies pannes).
- `deploy-main-catchup.yml` : horaire → `23 */6 * * *` (24 → 4/jour), le
  rattrapage est quasi toujours no-op.

### Étape 3 — fusion des guards à deux têtes (gain ~3 workflows)
- `fix-feat-ratio-guard.yml` + `fix-feat-ratio-report.yml` → un workflow à
  2 jobs (guard PR bloquant + rapport hebdo).
- `secret-scan.yml` + `secret-history-scan.yml` → un workflow (job push +
  job cron hebdo historique).
- `design-token-sync.yml` + `web-design-tokens.yml` → un guard tokens unique
  conditionné par `paths:`.

### Étape 4 — revue des ~30 guards (gouvernance)
Chaque guard doit justifier **≥ 1 détection réelle sur 90 jours** (données :
`ci-saturation-report.yml`). Guard sans détection → fusion dans un guard
composite `paths:`-conditionné ou rétrogradation en check local
(`dev-hub/tools/`). Candidats à examiner en priorité : les guards protocole
de branche (bc-batch/crm), `content-naming-guard`, `horizontal-tools-parity`,
`public-promises-guard`.

## 4. Matrice risque / gain

| Action | Gain (runs/jour) | Risque | Mitigation |
|---|---:|---|---|
| Suppression `deploy-staging.yml` (fait) | ~0 (déjà dormant) + dette −9,6 Ko | Quasi nul : aucun run depuis 2026-09-13, aucun staging | Historique git + trace README |
| Désactivation trigger auto `e2e-staging.yml` (fait) | 10-30 | **Moyen** : perte du smoke post-deploy automatique | Dispatch manuel conservé ; `launch-observability-smoke` (30 min) + `owasp-zap` post-deploy couvrent la disponibilité prod ; ré-activation dès staging réel |
| `ci-observability` horaire (fait) | 120 | Faible : détection PR-sans-checks retardée de ≤ 60 min (rapport non bloquant) | dispatch manuel |
| `queue-supervision` inchangé | 0 | — | DoD #5282 écrit ; re-challenger si un worker dédié avec alerting applicatif arrive (#7649) |
| Étape 2 (crons restants) | ~60 | Faible-moyen (drift détecté moins vite) | corriger le rouge chronique AVANT d'espacer |
| Étapes 3-4 (fusions guards) | ~10-20 + latence PR réduite | Moyen (perte de granularité des checks requis) | migration check requis par check requis, jamais en lot |

Réf. : issue #7846, audit externe 2026-09-20, PR d'implémentation étape 1.
