# Feature Specification: Golden tests DZ — cas limites complémentaires (issue #5244)

**Feature Branch**: `mod/payroll/5244-dz-golden-edge-cases`

**Created**: 2026-08-23

**Status**: Draft → Implemented

**Input**: Issue #5244 — ≥ 40 golden tests DZ (IRG, abattement, CNAS, mois
incomplet, congés, maladie, démission, primes, 13ᵉ mois, arrondis) +
fixtures réalistes. 43 méthodes golden existent déjà (#5149).

## Problème / État

- 43 méthodes golden DZ existent (bornes IRG, CNAS, prorata, HS, congés, fin
  de contrat, solde de tout compte, structure salariale).
- **Maladie** et **13ᵉ mois** ne sont PAS des fonctions du moteur actuel →
  testables seulement après les issues de complétion #5241/#5245 (documenté,
  hors périmètre de cette PR).
- Manques identifiés : bornes EXACTES de l'abattement IRG (plancher 12 000 /
  plafond 18 000 DZD/an à la frontière) et une suite « profils réalistes »
  (fixtures : SMIG, ouvrier, cadre, direction).

## Décision

Nouveau fichier `api/tests/Feature/Payroll/Golden/GoldenDzEdgeCasesTest.php`
(tests UNIQUEMENT, zéro changement moteur — pas de collision avec les
branches DZ en cours #5240/#5241/#5243/#5245) :

1. Borne plancher abattement : assiette 30 869,57 → IRG 1 500,00.
2. Borne plafond abattement : assiette 36 304,35 → IRG 2 250,00.
3. Profils réalistes (data provider) : SMIG 20 000, ouvrier qualifié 35 000,
   cadre confirmé 80 000, direction 500 000 — CNAS + IRG + net.
4. Détails pédagogiques par profil + garde « assiette IRG = brut − CNAS ».

## User Scenarios & Testing

### User Story 1 — Les cas limites DZ sont verrouillés (Priority: P1)

**Independent Test**: `php artisan test --filter=GoldenDzEdgeCasesTest` → 10 tests verts.

**Acceptance Scenarios** (valeurs calculées à la main, DZ_COMPLIANCE.md §1-§2) :

1. **Given** une assiette IRG de 30 869,57 DZD, **Then** impôt avant abattement
   2 500/mois → annuel 30 000 → abattement 12 000 (plancher exact) → IRG 1 500,00.
2. **Given** une assiette IRG de 36 304,35 DZD, **Then** impôt avant abattement
   3 750/mois → annuel 45 000 → abattement 18 000 (plafond exact) → IRG 2 250,00.
3. **Given** un SMIG (20 000 DZD), **Then** CNAS 1 800/5 200, IRG 0, net 18 200,00.
4. **Given** un cadre confirmé (80 000 DZD), **Then** CNAS 7 200/20 800,
   IRG 11 956,00, net 60 844,00.
5. **Given** une direction (500 000 DZD), **Then** CNAS 45 000/130 000,
   IRG 137 950,00, net 317 050,00.

## Edge Cases

- Bornes d'abattement testées À LA FRONTIÈRE (ni plancher ni plafond écartés).
- Profils couvrant les 3 tranches basses, la zone plafonnée et la tranche 35 %.
- Zéro dépendance base de données (F-13).

## Validation

- 10 nouveaux golden (30 assertions) + 43 existants sans régression.
- Pint PASS, PHPStan strict 0 erreur.
- Suite Payroll complète verte.
