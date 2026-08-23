# Feature Specification: Pack Turquie 100 % — audit légal 2026 + golden tests (issue #5253)

**Feature Branch**: `mod/payroll/5253-pack-tr-100pct`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5253 — `TurkeyPayrollRules` existe ; compléter : SGK
(işveren/sigortalı), işsizlik, damga vergisi, gelir vergisi, asgari ücret,
bulletin PDF tr, exports, golden tests.

## Problème

1. **SMIG obsolète** : 20 002 TRY (2024) → **33 030,00 TRY/mois** en 2026
   (décision Asgari Ücret Tespit Komisyonu, publiée CSGB — net officiel
   28 075,50 TRY).
2. **Barème IR obsolète** : tranches 2024 (110 k/230 k/580 k/3 M) → barème
   **salariés 2026** (G.V.K. art. 103, Resmî Gazete 31/12/2025) :
   190 000 (15 %) · 400 000 (20 %) · 1 500 000 (27 %) · 5 300 000 (35 %) ·
   au-delà (40 %), assiette annualisée × 12 puis / 12.
3. **Asgari ücret istisnası manquante** : depuis la loi n° 7346 du
   25/12/2022, l'impôt sur le revenu correspondant au SMIC net n'est pas
   prélevé (exonération valable pour TOUS les salaires). L'ancien code
   facturait l'IR même sous le SMIC.
4. **Damga vergisi absente** : taxe de timbre sur salaire binde 7,59
   (0,759 %), part ≤ SMIC exonérée depuis 2022 — à exposer comme ligne de
   déduction dédiée (mécanisme taxe forfaitaire, non déductible de l'assiette IR).
5. **Taux SGK obsolète** : employeur 20,5 % → **21,75 %** en 2026
   (MYÖ 12 % + GSS 7,5 % + KVSK 2,25 %, sans teşvik) ; plafond mensuel
   (tavan) **297 270 TRY** appliqué à toutes les cotisations.
6. **Golden tests** : 3 cas obsolètes ; l'issue exige ≥ 15 cas calculés à la main.

## Décision

Dans `api/app/Modules/Payroll/Infrastructure/Services/CountryRules/TurkeyPayrollRules.php` :

1. `minimumWage()` → 33 030,00 (2026, CSGB).
2. `defaultTaxSlabs()` → barème salariés 2026 (G.V.K. art. 103).
3. `calculateIncomeTax()` → progressif ANNUEL (× 12) / 12, PUIS soustraction
   de l'asgari ücret istisnası : `max(0, impôt − impôt sur SMIC net annuel)`
   (SMIC net = 33 030 × (1 − 0,14 − 0,01) = 28 075,50 TRY/mois).
4. `stampTax()` + `calculateBracketTax()` → damga vergisi 0,759 % sur la part
   du brut > SMIC ; `flatPayrollTaxLabel()` → « Damga vergisi (binde 7,59) ».
   La damga n'est PAS dans les cotisations salariales (non déductible de
   l'assiette IR — sinon le moteur taxable = brut − charges salarié la
   déduirait à tort).
5. `socialContributions()` / `calculateSocialCharges()` → SGK salarié 14 % +
   chômage 1 %, SGK employeur 21,75 % + chômage 2 %, TOUTES plafonnées au
   tavan 297 270 TRY via `computeContribution()` (constitution §III).
6. `confidenceLevel()` reste `pilot` (validation mali müşavir TR requise —
   même niveau que TN/MA).

## User Scenarios & Testing

### User Story 1 — Un salarié turc est calculé selon les règles 2026 (Priority: P1)

**Independent Test**: `php artisan test --filter=GoldenTrPayrollTest` → 15 scénarios verts.

**Acceptance Scenarios** (chaque valeur calculée à la main dans le commentaire du test) :

1. **Given** un salarié au SMIG 2026 (33 030 TRY), **Then** salarié 4 954,50,
   employeur 7 844,63, IR 0,00, damga 0,00, **net 28 075,50** (= net officiel CSGB).
2. **Given** un ouvrier à 40 000 TRY, **Then** salarié 6 000,00, employeur
   9 500,00, IR 1 231,57, damga 52,90, net 32 715,53.
3. **Given** un cadre moyen à 50 000 TRY, **Then** salarié 7 500,00, IR 3 526,57,
   net 38 844,63.
4. **Given** un cadre supérieur à 80 000 TRY, **Then** IR 10 411,57, net 57 231,93.
5. **Given** un haut salaire à 100 000 TRY, **Then** IR 15 001,57, net 69 490,13.
6. **Given** un très haut salaire à 250 000 TRY, **Then** IR 56 426,57, net 154 426,63.
7. **Given** un salaire ≥ tavan (300 000), **Then** cotisations plafonnées à
   297 270, salarié 44 590,50, employeur 70 601,63, IR 71 444,90, net 181 938,30.
8. **Given** la borne de l'istisna (assiette = SMIC net 28 075,50), **Then** IR 0,00.
9. **Given** 1 TRY de plus (28 075,58), **Then** IR 0,02 (l'exonération s'arrête).
10. **Given** les bornes du barème (annuel 190 000 / 400 000 / 1 500 000 /
    5 300 000), **Then** IR 0,00 / 1 051,57 / 25 801,57 / 136 634,90.
11. **Given** la damga, **Then** 0,00 au SMIC, 7,59 à SMIC + 1 000 ; libellé
    « Damga vergisi (binde 7,59) » (ligne dédiée).
12. **Given** les heures sup (İş Kanunu 4857 art. 41/63), **Then** seuil 45 h/sem,
    palier 1,5.
13. **Given** les métadonnées pays, **Then** TRY, tr, Europe/Istanbul, repos
    dimanche, cycle mensuel, `pilot`.

## Edge Cases

- **Plafond tavan** : assiette de TOUTES les cotisations plafonnée à
  297 270 TRY/mois (9 909 TRY/jour × 30, Resmî Gazete 31/12/2025).
- **İstisna plancher** : tout salaire ≤ SMIC net annuel (336 906 TRY)
  paie 0 IR — l'ancienne assertion 1 375,0 (110 000/an) devient 0,0.
- **Teşvik** : l'employeur peut bénéficier de 5 puan (16,75 %, imalat) ou
  2 puan (19,75 %) de réduction SGK — NON appliqués par défaut (défaut
  prudent sans incitation, surchargeable en base `social_contributions`).
- **Damga** : exonérée sur la part ≤ SMIC ; NON déductible de l'assiette IR.
- **Bulletin/export** : mentions légales TR déjà présentes dans
  `PaySlipPdfGenerator::COUNTRY_LEGAL` ; virement via formats multi-pays
  (`csv_generic`/`sepa_xml`, devise TRY via `CountryDefaults`) — aucun
  changement nécessaire hors règles.
- Changement de taux = mise à jour simultanée doc + golden + CHANGELOG.

## Validation

- Migration tenant backfill `2026_08_23_000004_backfill_tr_2026_payroll.php` (pattern backfill CI #1918) : remplace l'ancien barème 2024 des scopes TR seedés (sinon `taxSlabs()` résout la base AVANT le code — bulletins existants resteraient sur un barème abrogé ; un re-seed simple créerait 10 lignes actives chevauchantes) et aligne les 4 cotisations TR (taux 21,75 %, cap tavan 297 270). Lignes custom admin préservées.
- PHPStan strict (level 8) 0 erreur sur les fichiers touchés ; Pint PASS.
- Golden TR (15 cas) + suite Payroll sans régression.
- `docs/payroll/TR_COMPLIANCE.md` mis à jour (CSGB, SGK, GİB — Resmî Gazete
  31/12/2025, loi n° 7346).
