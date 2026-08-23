# Feature Specification: Pack Maroc 100 % — audit légal 2026 + golden tests (issue #5248)

**Feature Branch**: `mod/payroll/5248-pack-ma-100pct`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5248 — `MoroccoPayrollRules` existe mais doit être complété pour atteindre 100 % : audit contre les textes (CNSS plafonnée, AMO, IR mensuel, mutuelle), exports, golden tests, docs.

## Problème

1. **Barème IR obsolète** : le barème codé (0–30 000 à 0 %, max 38 %) est celui d'avant la réforme. La Loi de Finances 2025 (CGI art. 73-I) a relevé le seuil d'exonération à **40 000 MAD/an**, baissé le taux marginal à **37 %**, et révisé les tranches intermédiaires (30 % pour 80–100 k, 34 % pour 100–180 k). Déductions forfaitaires associées : 4 000 / 10 000 / 18 000 / 22 000 / 27 400.
2. **Abattement frais professionnels obsolète** : le code applique 35 % plafonné 30 000. Depuis la LF 2023 (CGI art. 59-I) : **35 % si brut annuel imposable < 78 000 MAD, 25 % si ≥ 78 000, plafond 35 000 MAD/an** (plancher 2 500 conservé).
3. **Cotisations incomplètes** : CNSS 4,48/8,98 % plafonnée 6 000 et AMO 2,26/4,11 % non plafonnée sont correctes, mais il manque : **IPE** (perte d'emploi) 0,19 % salarié / 0,38 % employeur plafonnée 6 000, **allocations familiales** 6,40 % employeur non plafonnée, **taxe de formation professionnelle** 1,60 % employeur non plafonnée.
4. **SMIG obsolète** : 3 111 MAD (2024) → **3 422,72 MAD/mois** (17,92 MAD/h × 191 h, en vigueur 2026).
5. **Golden tests** : seulement 3 cas ; l'issue exige ≥ 15 cas calculés à la main.

## Décision

Dans `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/MoroccoPayrollRules.php` :

1. `defaultTaxSlabs()` → barème 2026 (CGI art. 73-I, LF 2025) : 0–40 000 0 % · 40 001–60 000 10 % (4 000) · 60 001–80 000 20 % (10 000) · 80 001–100 000 30 % (18 000) · 100 001–180 000 34 % (22 000) · > 180 000 37 % (27 400).
2. `moroccoProfessionalExpensesAbatement()` → LF 2023 : 35 % si annuel < 78 000, 25 % si ≥ 78 000, plancher 2 500, plafond 35 000.
3. `socialContributions()` → ajout IPE_EMP 0,19 % / IPE_PAT 0,38 % (cap 6 000), AF_PAT 6,40 % (sans cap), TFP_PAT 1,60 % (sans cap). `calculateSocialCharges()` recalculé via `computeContribution()` par ligne (constitution §III).
4. `minimumWage()` → 3 422,72.
5. `confidenceLevel()` reste `pilot` (validation expert-comptable local requise avant `production` — constitution §III).

## User Scenarios & Testing

### User Story 1 — Un salarié marocain est calculé selon les règles 2026 (Priority: P1)

**Independent Test**: `php artisan test --filter=GoldenMaPayrollTest` → ≥ 15 scénarios verts (PostgreSQL 16, CI payroll-ci.yml).

**Acceptance Scenarios** (chaque valeur calculée à la main dans le commentaire du test, sources `docs/payroll/MA_COMPLIANCE.md`) :

1. **Given** un salarié au SMIG (3 422,72 MAD), **Then** IR = 0,00 (assiette annuelle après abattement < 40 000), net = 3 185,53.
2. **Given** un ouvrier à 5 000 MAD, **Then** salarié = 346,50, employeur = 1 073,50, IR = 0.
3. **Given** un cadre moyen à 10 000 MAD, **Then** salarié = 506,20 (CNSS 268,80 + AMO 226,00 + IPE 11,40), employeur = 1 772,60, IR mensuel ≈ 636,11.
4. **Given** un haut salaire à 60 000 MAD, **Then** salarié = 1 636,20, employeur = 7 827,60, IR mensuel ≈ 18 232,11 (tranche 37 %).
5. **Given** un salaire exactement au plafond CNSS (6 000), **Then** CNSS/IPE plafonnées, IR = 29,64.
6. **Given** un salaire à 7 500 MAD (assiette annuelle ≥ 78 000), **Then** abattement 25 %, IR = 224,21.
7. **Given** les bornes du barème, **Then** `legalReferenceTaxSlabs()` = table 2026 exacte.
8. **Given** l'abattement, **Then** `moroccoProfessionalExpensesAbatement()` : 35 % sous 78 000, 25 % au-delà, plafond 35 000, plancher 2 500.
9. **Given** les paramètres pays, **Then** SMIG 3 422,72, MAD, Africa/Casablanca, 44 h, HS 25 %, repos dimanche.

## Edge Cases

- Mode simulation `withCapsEnabled(false)` : CNSS/IPE non plafonnées.
- `thirteenthMonthMandatory()` = false (pas de 13ᵉ mois légal MA), traitement `fully_taxable`.
- Changement de taux = mise à jour simultanée doc compliance + golden + CHANGELOG (constitution §III).
- `rulesVersion()` change : le fingerprint inclut slabs + cotisations.

## Validation

- `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` (level 8) vert sur les fichiers touchés.
- `vendor/bin/pint --test` vert.
- Golden MA (≥ 15 cas) + suite Payroll existante sans régression (94 tests maintenus).
- `docs/payroll/MA_COMPLIANCE.md` mis à jour (sources : CGI art. 59-I/73-I, LF 2023/2025, CNSS.ma, CLEISS, Upsilon Consulting 2026).
