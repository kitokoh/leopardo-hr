# Feature Specification: Pack Sénégal — cas limites golden + audit de complétion (issue #5250)

**Feature Branch**: `mod/payroll/5250-pack-sn-completion`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5250 — Pack Sénégal 100 % (IPRES, CSS, ITS, CFCE, bulletin
PDF, exports, ≥ 15 golden tests). Les règles sont déjà **production**
(validation expert-comptable 2026-08-18, #1912) et 20 golden tests existent.

## État de l'existant (vérifié)

- `SenegalPayrollRules` : IPRES T1 (5,6/8,4 % plaf. 432 000) + T2 cadres
  (2,4/3,6 % sur 432 000–1 296 000, par catégorie #1912), CSS famille 7 % +
  AT 1 % plafonnées 63 000, CFCE 3 %, IR 7 tranches 0→43 % (CGI art. 213),
  TRIMF 2026 (900→18 000), abattement 30 % du brut plafonné 75 000/mois
  (CGI art. 168), SMIG 64 305,43 (décret 2023-1710).
- Bulletin PDF : mention légale SN dans `PaySlipPdfGenerator::COUNTRY_LEGAL`
  (template générique). Export virement : formats multi-pays
  (`sepa_xml`/`csv_generic`) — génériques.
- Golden : 20 tests existants (≥ 15 requis) + `SnPayrollFixtures` (source
  de référence #2541).

## Manques identifiés

1. Frontières TRIMF (10 bornes) non verrouillées.
2. Plafond CSS à l'assiette exacte 63 000 vs au-delà.
3. Plafond d'abattement effectivement atteint (brut 300 000).
4. Déclenchement T2 par catégorie (ouvrier vs cadre à 600 000).
5. Profil cadre complet (charges + IR + TRIMF combinés).

## Décision

Nouveau fichier `api/tests/Feature/Payroll/Golden/GoldenSnEdgeCasesTest.php`
(15 tests, zéro changement moteur) + complément `SN_COMPLIANCE.md` + CHANGELOG.

## User Scenarios & Testing

1. **Given** un brut à chaque borne TRIMF ± 1, **Then** la taxe forfaitaire
   bascule exactement (900/1 800/3 600/7 200/12 000/18 000).
2. **Given** un brut ≥ 63 000, **Then** CSS famille/AT plafonnées (4 410 + 630).
3. **Given** un brut de 300 000, **Then** abattement plafonné 75 000 → IR 39 460
   + TRIMF 3 600 = 43 060.
4. **Given** un cadre à 600 000, **Then** T2 appliqué (salariale 28 224) ;
   **Given** un ouvrier, **Then** T1 seul (24 192).
5. **Given** un cadre à 600 000, **Then** IR 134 204,93 + TRIMF 3 600
   = 137 804,93.
6. **Given** les bruts de référence, **Then** le mode historique reste aligné
   sur `SnPayrollFixtures`.

## Validation

- 15 nouveaux golden (36 assertions) + 20 existants sans régression.
- Pint PASS, PHPStan strict 0 erreur.
- DoD #5250 : parcours pilote SN = bulletin (mention légale) + virement
  (format générique) — mécanismes déjà génériques, couverts par le référentiel.
