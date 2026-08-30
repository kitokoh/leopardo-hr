# Golden tests Accounting — MAT-007 (#5865)

> Programme de maturité BC-01 PLATFORM — issue [MAT-007 #5865](https://github.com/kitokoh/leopardo-hr/issues/5865).
> Méthodologie identique au programme FOCUS paie (`tests/Feature/Payroll/Golden/README.md`) :
> **chaque valeur attendue est calculée à la main**, jamais reprise de l'algorithme.

## Objectif

Vérrouiller les **invariants de non-régression** de la paie et de la comptabilité :
montants, arrondis, périodes et écritures restent reproductibles ; aucune
modification sensible sans test golden.

## Couverture

| Surface | Invariant verrouillé | Référence |
|---|---|---|
| Paie DZ (IRG/CNAS) | 40+ cas golden pays (DZ, CI, CM, FR, MA, SN, TN, TR, US, GB, GA, BF, TG, ML, CG…) | `tests/Feature/Payroll/Golden/` (programme FOCUS F-03) |
| Paie → comptabilité | Écritures salariales automatiques golden DZ : 12 lignes, débit = crédit = 138 000 | `GoldenAccountingInvariantsTest::test_golden_payroll_dz_accounting_entries_are_balanced` |
| Compta — facture | D 411 1 190 · C 70 1 000 · C 4457 190 (HT 1 000 + TVA 19 %) | `test_golden_dz_invoice_posting_is_balanced` |
| Compta — idempotence | Re-posting = rafraîchissement, jamais de doublon | `test_golden_posting_is_reproducible_and_idempotent` |
| Compta — trésorerie | Espèces → D 53 / C 411 · Virement → D 512 / C 411 | `test_golden_cash_payment_moves_treasury`, `test_golden_bank_payment_uses_bank_account` |
| Compta — période | Totaux de période par compte = calcul manuel (4 165 / 3 500 / 665 / 500), équilibre | `test_golden_period_totals_match_hand_calculation` |
| Compta — clôture | Période close = figée : tout posting refusé (`PeriodClosedException`), aucune écriture ajoutée | `test_closed_period_freezes_entries` |
| Compta — grand-livre | Solde courant cumulé (411 = 1 190) · écart 0 · balanced | `test_ledger_running_balance_is_cumulative` |
| Paie — snapshot | Audit de calcul immuable (append-only, pas de `updated_at`) | `test_payroll_calculation_audit_is_immutable_and_append_only` |

## Règles d'or (héritées du programme FOCUS)

1. **Jamais de calcul dupliqué** : la valeur attendue est un nombre en dur, pas
   une reformulation de l'algorithme.
2. **Référence** : chaque cas cite le calcul manuel (docs/payroll/*_COMPLIANCE.md
   pour la paie, le plan de comptes PCF/SYSCOHADA simplifié `#5234` pour la compta).
3. **Un cas par famille** : facture, avoir, paiement espèces/virement, clôture,
   grand-livre, écritures salariales, snapshot.
4. **Toute modification sensible** (taux, plan de comptes, règles de posting,
   arrondis) = mise à jour simultanée du référentiel + du test golden + du CHANGELOG.

## Comptage CI

Le rapport `dev-hub/tools/payroll-golden-report.sh` compte les cas golden paie ET
comptabilité (fichiers `tests/Feature/Payroll/Golden/*Test.php` et
`tests/Feature/Accounting/Golden*Test.php`) :

```bash
bash dev-hub/tools/payroll-golden-report.sh
::notice::GOLDEN_PAYROLL_CASES=…
::notice::GOLDEN_ACCOUNTING_CASES=…
```
