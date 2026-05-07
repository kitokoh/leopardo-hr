# REGISTRE DES SCENARIOS DE TESTS

## Objectif

Fournir une source de verite unique pour savoir:

- quelles surfaces fonctionnelles doivent etre testees,
- dans quel document de scenarios elles sont decrites,
- quel workflow CI les execute,
- quels artefacts doivent etre produits avant un deploiement.

## Regle de gouvernance

Toute nouvelle fonctionnalite, extension de parcours critique ou changement de comportement dans:

- `api/`
- `mobile/`
- `admin-dashboard/`

doit mettre a jour:

1. le document de scenarios du domaine,
2. ou ce registre si le domaine, le workflow, les artefacts ou la criticite changent.

Le workflow `Governance Gates` bloque la PR si la surface fonctionnelle change sans mise a jour du registre ou du document de scenarios associe.

## Matrice canonique

| Domaine | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| API backend | `docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md` | `Tests - Leopardo RH` | JUnit unit/feature, logs, quality summary, coverage clover + HTML | Obligatoire |
| Mobile Flutter | `docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md` | `Tests - Leopardo RH` | `test-results.json`, `lcov.info`, quality summary, smoke APK | Obligatoire si `mobile/**` change |
| Web admin | `docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md` | `Web CI - Leopardo Admin` | rapport Playwright HTML, JUnit Playwright, traces, screenshots, videos en echec | Obligatoire si `admin-dashboard/**` change |
| Gouvernance repo | `tools/check-governance.ps1` + ce registre | `Tests - Leopardo RH` | journal CI, verifications changelog/scenarios | Obligatoire |
| Deploiement main | `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` | `Deploy - Leopardo RH` | healthcheck post-deploy, rollback hook si echec | Strictement bloque tant que les workflows requis ne sont pas verts |

## Definition "tests concluants"

Un SHA est deployable seulement si:

1. `Tests - Leopardo RH` est `success`,
2. `Web CI - Leopardo Admin` est `success` si le SHA touche `admin-dashboard/**`,
3. les artefacts minimums du domaine existent,
4. aucun job critique n'est `failure`, `cancelled` ou `timed_out`.

## Politique artefacts

### Backend

- `backend-test-reports`
- `backend-quality-summary`
- `backend-quality-reports`
- `backend-coverage-summary`
- `backend-coverage-reports`

### Mobile

- `mobile-quality-summary`
- `mobile-test-reports`
- APK smoke si build mobile actif

### Web

- `playwright-report`
- `test-results/junit.xml`
- traces Playwright sur premier retry
- videos Playwright retenues en echec

## Evolution attendue

Quand un domaine gagne une feature significative, ajouter:

- le scenario nominal,
- les refus RBAC / tenant,
- les erreurs de validation,
- les cas de resilience,
- l'artefact CI attendu.

## Extension 2026-05-07 - I18N enterprise partage

| Domaine transverse | Base de scenarios | Workflow source de verite | Artefacts minimums | Gate de deploiement |
|---|---|---|---|---|
| I18N partage backend/web/mobile | SCENARIOS_TEST_API_GITHUB_ACTIONS.md + SCENARIOS_TEST_MOBILE_FLUTTER.md + SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md | I18N Enterprise + workflows de surface | catalogues generes, checksums ersions.json, validation locale, endpoint distant syntaxiquement valide | Obligatoire si shared/i18n/** ou une surface synchronisee change |
