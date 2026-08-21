# Feature Specification: Clôture DZ de bout en bout + benchmark 10 000 employés (Closes #5150)

**Issue**: #5150 (P1, spec-kit, dz-depth, payroll, perf) — suite de F-12/#1594.
**Branche**: `feat/5150-dz-cloture-benchmark` (claim marker poussé).
**Statut**: Spec → Implémenté (audit + correctifs + runbook).

## Contexte

La promesse produit : un comptable clôture la paie mensuelle DZ **sans
intervention dev** — draft → validée → réversible, avec exports et bulletins.
La spec F-12 (#1594, fermée 2026-08-09) a posé le protocole benchmark
(10 000 employés < 30 min) et la cible est atteinte (90,15 s, run 2026-08-09).

## Audit du flux actuel (constat 2026-08-20)

Le workflow F-11 existe et est testé (API + service + UI web #5017) :

| Étape | Endpoint | Statut |
|---|---|---|
| Création draft | `POST /api/v1/payroll-runs` | ✅ |
| Calcul (moteur réel, gardes #1767/#2221/#2332/#2555) | `POST /payroll-runs/{run}/calculate` | ✅ |
| Étape 1 — validation RH (audit `payroll_run_validated`) | `POST /payroll-runs/{run}/validate` | ✅ |
| Étape 2 — clôture comptable (verrouillage atomique, audit, archivage Cabinet #1817) | `POST /payroll-runs/{run}/lock` | ✅ |
| Revert — déverrouillage motivé (raison obligatoire, audit) | `POST /payroll-runs/{run}/unlock` | ✅ |
| Annulation (draft/calculated/validated) | `POST /payroll-runs/{run}/cancel` | ✅ |
| Régularisation d'un run locked/paid (#1818/#1942) | `POST /payroll-runs/{run}/regularize` | ✅ |
| Exports : journal CSV, export comptable, virement (SEPA/CCP/CPA/BNA DZ), CNAS DZ, bulletin PDF (mentions DZ) | `GET …/journal`, `GET …/export`, `POST …/bank-export`, `POST …/declarations/cnas-dz` | ✅ |
| Diffusion bulletins | `POST /payroll-runs/{run}/send-slips` | ✅ |
| UI web (calculer/valider/verrouiller/déverrouiller) | `front/web` #5017 | ✅ |

### Manques constatés (corrigés dans cette issue)

1. **Archivage Cabinet vide après une clôture API** : `validateRun` bascule
   les bulletins en `validated` AVANT `lock`, mais `ArchivePaySlipsToCabinetJob`
   filtrait `status = 'calculated'` → aucun document archivé pour le flux API.
   Correctif : filtre élargi à `calculated|validated|sent` + retrait de deux
   `fwrite(STDERR, "DBG …")` oubliés.
2. **Aucun test E2E API avec le moteur réel** : les tests API utilisaient un
   mock du calculateur ou des runs construits à la main. Correctif :
   `PayrollRunClosingE2ETest` (round trip complet + archivage).

### Limite assumée (documentée, pas de code)

Après déverrouillage, un run est `validated` : le recalcul reste refusé
(garde `[draft, calculated]`) — c'est l'invariant « l'original n'est jamais
modifié » (#1818/#1942). La correction d'un run clôturé passe par la
régularisation (`regularize`, réservée aux runs `locked`/`paid`), ou par
annulation + recréation pour un run `validated` jamais verrouillé.

## User Stories & Testing

### US1 — Le comptable clôture un cycle mensuel DZ sans intervention dev (P1)
**Acceptance Scenarios**:
1. Given un run DZ calculé, When le comptable appelle `validate`, Then le run
   passe `calculated → validated`, les bulletins passent `validated`, l'audit
   `payroll_run_validated` est écrit.
2. Given un run `validated`, When il appelle `lock`, Then le run passe
   `locked` (audit `payroll_run_locked`), les bulletins sont archivés au
   Cabinet (read-only), le run n'est plus recalculable ni annulable.
3. Given un run `locked`, When il appelle `unlock` sans raison, Then 422.
4. Given un run `locked` et une raison, When `unlock`, Then retour `validated`
   (audit `payroll_run_unlocked` avec raison, locked_by/locked_at effacés).
5. Given un run déverrouillé, When `lock` à nouveau, Then `locked` — totaux
   et nombre de bulletins strictement identiques (aucune perte).

### US2 — L'archivage Cabinet couvre le flux API (régression #5150) (P1)
**Acceptance Scenarios**:
1. Given une clôture API complète (calculate → validate → lock), Then un
   document Cabinet `payslip` read-only existe par bulletin, stocké sur le
   disque `private`, avec audit `payslip_archived`.

### US3 — Le benchmark 10 000 employés reste mesurable et < 30 min (P2)
**Acceptance Scenarios**:
1. Given un env PG+Redis, When `dev-hub/tools/payroll-benchmark.sh
   --employees=10000 --step=all`, Then la durée totale < 1800 s et le run est
   consigné dans `docs/payroll/BENCHMARK.md` (garde régression > 20 %).

## Plan technique

1. `api/app/Jobs/ArchivePaySlipsToCabinetJob.php` : filtre
   `whereIn('status', ['calculated', 'validated', 'sent'])` + retrait des
   `fwrite(DBG)`.
2. `api/tests/Feature/Payroll/PayrollRunClosingE2ETest.php` : round trip E2E
   moteur réel (draft → calculated → validated → locked → unlocked → locked,
   sans perte, audit trail) + archivage des bulletins `validated` au lock.
3. `docs/payroll/RUNBOOK_CLOTURE_DZ.md` : pas-à-pas comptable complet.
4. `docs/payroll/BULLETIN_DZ_MENTIONS.md` : case archivage cochée (#1817).
5. `docs/payroll/BENCHMARK.md` : statut F-12/#1594 à jour.
6. `CHANGELOG.md` [Unreleased] + spec (ce fichier).

## Hors périmètre

- Pas de pays hors DZ · pas de refactor du moteur de calcul · pas de
  transition `validated → calculated/draft` (invariant #1818/#1942) · pas de
  validation expert-comptable (action humaine phase 3).

## Tests

- `PayrollRunClosingE2ETest::test_full_closing_round_trip_via_api_with_real_engine`
- `PayrollRunClosingE2ETest::test_lock_archives_validated_slips_to_cabinet_via_api_flow`
- Existants : `PayrollClosingTest`, `PayrollRunClosingApiTest`,
  `PayrollRunStateMachineTest`, `PaySlipDzMentionsTest`,
  `PaySlipCabinetArchiveTest`, `BankExportGeneratorTest` — non régressés.
- CI : `payroll-ci.yml` (tests + coverage ≥ 80 %) sur PostgreSQL 16.
