# Golden Tests — Paie & Comptabilité (MAT-007)

- **Statut :** ratifié — méthodologie des tests golden du monorepo
- **Date :** 2026-08-28
- **Garde CI :** `dev-hub/tools/check-golden-tests-required.sh` (toute
  modification du code métier Payroll/Accounting sans test golden est
  bloquée)

## Principe

Un **test golden** est un invariant dont les valeurs attendues sont
**calculées à la main** (loi, barème, plan de comptes, règle d'arrondi),
jamais reprises du code. Une divergence entre le calcul manuel et
l'implémentation = régression de conformité (paie) ou d'intégrité
(comptabilité).

Règle : **aucune modification sensible sans test golden.** Toute PR qui
touche `api/app/Modules/Payroll/` ou `api/app/Modules/Accounting/` (code
métier, hors Requests/Resources) doit ajouter ou modifier un golden test —
sinon le garde CI échoue.

## Emplacements

| Domaine | Dossier |
|---|---|
| Paie (par pays + invariants transverses) | `api/tests/Feature/Payroll/Golden/` |
| Comptabilité (écritures, arrondis, snapshots, clôtures) | `api/tests/Feature/Accounting/Golden/` |

## Méthodologie

1. **Calcul manuel d'abord** : poser les montants sur papier (ou en
   commentaire) avec la référence légale / comptable (`docs/payroll/{PAYS}_COMPLIANCE.md`,
   `docs/architecture/COMPTABILITE_CONCEPTION.md`).
2. **Tester l'invariant, pas l'implémentation** : montants finaux, arrondis
   aux centimes, périodes, équilibre débit/crédit, snapshots de clôture.
3. **Commenter le calcul** dans le test (les étapes + les références).
4. **Reproductibilité** : re-jouer l'opération (re-posting, re-run, replay)
   doit produire strictement le même résultat (idempotence).

## Couverture actuelle

### Paie (`api/tests/Feature/Payroll/Golden/`)
- Par pays : BF, CA, CG, CI, CM, DZ + edge cases (arrondis, heures sup,
  périodes partielles, indemnités, fins de contrat, matrice #5244).
- Conformité : chaque taux porte une référence légale
  (`confidenceLevel()` : `pilot`/`production`).

### Comptabilité (`api/tests/Feature/Accounting/Golden/`)
- `GoldenJournalPostingTest` (6 tests, 52 assertions) — invariants :
  1. facture équilibrée 411 D (TTC) / 70 C (HT) / 4457 C (TVA), montants
     calculés à la main, re-posting idempotent, totaux de période stables ;
  2. paiement = mouvement de trésorerie (512 banque / 53 caisse ↔ 411) ;
  3. avoir = contrepassation équilibrée (709 / 4457 / 411) ;
  4. arrondis aux centimes reproductibles (TVA 19 % sur 333,33 → 63,33) ;
  5. clôture de période = immutabilité (posting refusé, snapshot figé) ;
  6. snapshot multi-écritures reproductible (Σ débit = Σ crédit = 26 180).

## Règles de garde

- Le garde s'applique au **diff de la PR** : code Payroll/Accounting modifié
  → golden test exigé dans le même diff.
- Exemptions : `Requests`/`Resources` (contrats HTTP, pas de règles métier),
  tests eux-mêmes.
- Un golden test cassé = régression réelle : ne jamais « ajuster » le golden
  sans re-valider le calcul manuel.
