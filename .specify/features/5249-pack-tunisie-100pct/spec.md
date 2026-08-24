# Feature Specification: Pack Tunisie 100 % — audit légal 2026 + golden tests (issue #5249)

**Feature Branch**: `mod/payroll/5249-pack-tn-100pct`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5249 — `TunisiaPayrollRules` existe ; compléter : CNSS
(cotisations + plafonds), IR mensuel, ASSP (accidents du travail), bulletin
PDF, exports, golden tests.

## Problème

1. **Barème IRPP obsolète** : le barème codé (5 tranches, max 35 %) date
   d'avant la réforme. La **LF 2025 (art. 36, loi n° 2024-48 du 09/12/2024)**
   a instauré 8 tranches jusqu'à **40 %** (15/25/30/33/36/38 %), en vigueur
   depuis le 01/01/2025.
2. **Cotisations incomplètes** : CNSS 9,18/16,57 % sans plafond = correcte,
   mais il manque le **fonds d'assurance perte d'emploi** (LF 2025 art. 17 :
   0,50 % salarié + 0,50 % employeur) et l'**ASSP** (accidents du travail,
   0,4–4 % patronal selon secteur — valeur pilote 1,00 %).
3. **SMIG obsolète** : 480 TND (2024) → **554,736 TND/mois** (48 h, décret
   n° 2026-67, effet 01/01/2026).
4. **Golden tests** : 3 cas seulement ; l'issue exige ≥ 15 cas calculés à la main.

## Décision

Dans `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/TunisiaPayrollRules.php` :

1. `defaultTaxSlabs()` → barème 2026 (CGI art. 36 — LF 2025) : 8 tranches
   annuelles 0 % / 15 % / 25 % / 30 % / 33 % / 36 % / 38 % / 40 %.
2. `socialContributions()` → ajout PLE_TN_EMP 0,50 % / PLE_TN_PAT 0,50 % /
   ASSP_TN_PAT 1,00 % (pilot). `calculateSocialCharges()` via
   `computeContribution()` par ligne (constitution §III).
3. `minimumWage()` → 554,736 (48 h).
4. Abattement art. 39 (10 %, bornes 1 000–1 500) conservé — vérifié.
5. `confidenceLevel()` reste `pilot` (validation expert-comptable TN requise).

## User Scenarios & Testing

### User Story 1 — Un salarié tunisien est calculé selon les règles 2026 (Priority: P1)

**Independent Test**: `php artisan test --filter=GoldenTnPayrollTest` → 15 scénarios verts.

**Acceptance Scenarios** (chaque valeur calculée à la main dans le commentaire du test) :

1. **Given** un salarié au SMIG (554,736 TND), **Then** salarié 53,69,
   employeur 100,24, IRPP 0,16, net 500,89.
2. **Given** un ouvrier à 1 000 TND, **Then** salarié 96,80, employeur 180,70,
   IRPP 59,43.
3. **Given** un cadre moyen à 2 000 TND, **Then** salarié 193,60, employeur
   361,40, IRPP 275,25, net 1 531,15.
4. **Given** un cadre supérieur à 5 000 TND, **Then** IRPP 1 181,08.
5. **Given** un haut salaire à 10 000 TND, **Then** salarié 968,00, employeur
   1 807,00, IRPP 2 958,63 (tranche 40 %).
6. **Given** les exemples publiés (SmartPaie 2026), **Then** 25 000 → 4 750/an,
   35 000 → 7 900/an, 60 000 → 16 950/an.
7. **Given** les bornes de l'abattement art. 39, **Then** plancher 1 000 /
   plafond 1 500 appliqués.

## Edge Cases

- Pas de plafond général CNSS (le seuil « 6 × SMIG » concerne le régime
  complémentaire) — `cap => null` conservé.
- ASSP sectoriel 0,4–4 % : valeur pilote 1,00 % surchargeable en base.
- `thirteenthMonthMandatory()` = false ; traitement `fully_taxable`.
- Changement de taux = mise à jour simultanée doc + golden + CHANGELOG.

## Validation

- PHPStan strict (level 8) 0 erreur sur les fichiers touchés ; Pint PASS.
- Golden TN (15 cas) + suite Payroll sans régression.
- `docs/payroll/TN_COMPLIANCE.md` mis à jour (CGI art. 36/39, LF 2025,
  CNSS.tn, CLEISS, SmartPaie 2026, décret 2026-67).
