# Rapport de maturité — BC-09 EXPENSE

> **DEP-BC09 (issue #5885)** — Deep maturity, BC-09 Expenses & Benefits.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 09.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-09).

## Périmètre

Dépenses, avances, prêts, remboursements et avantages, avec contrat Finance :
`api/app/Modules/Expense`, routes `/api/v1/expense-claims*`, intégration
Accounting (écritures). Le module est un module DDD complet depuis #5235
(`Domain/Infrastructure/Interfaces/Providers`, écritures comptables
ExpenseAccountingEntry + contrôleur dédié) ; seuls les modèles de notes de
frais (`ExpenseClaim`, `ExpenseItem`) restent sous contrat `Planning` —
l'ex-dérogation façade PA2-ARCH-011 (#1414) a été résorbée par #5235.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet depuis #5235 (Domain/Infrastructure/Interfaces/Providers — ExpenseAccountingEntry, listeners, services comptables) ; modèles de notes de frais sous contrat `Planning` (ex-dérogation façade #1414 résorbée). Vocabulaire : ExpenseClaim, ExpenseItem (Planning), ExpenseAccountingEntry (Expense). Invariants (approbation, double paiement) portés par les tests. |
| D2 | Données | 🟢 PRÉSENT | Tables tenant (`expense_claims`, `expense_items`), FK/index cohérents, migration `check-migrations-tenant-schema.sh` vert. |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés (BelongsToCompany), `company_id` auto-rempli, workflow testé cross-tenant (ExpenseClaimWorkflowTest). |
| D4 | API | 🟢 PRÉSENT | `ExpenseClaimController` (15 routes déclarées), Requests/Resources, erreurs sûres, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | ExpensePolicy + gardes manager/employee, approbation bornée (pas de double approbation — workflow testé). |
| D6 | Transactions | 🟢 PRÉSENT | Workflow approbation → écritures Accounting (`ExpenseAccountingEntriesFlowTest`) ; pas de double exécution (tests dédiés). |
| D7 | Asynchronisme | 🟡 PARTIEL | Aucun job Expense dédié (flux synchrone) ; les notifications passent par le canal global. |
| D8 | Sécurité | 🟢 PRÉSENT | Aucun secret dans le module ; PII dépense (justificatifs) gérée par le contrat Documents (BC-20). |
| D9 | Frontend | 🟢 PRÉSENT | Espaces web/mobile (demande + approbation de frais) — surface minimale mais présente. |
| D10 | Performance | 🟢 PRÉSENT | Index dédiés, pagination sur les listes ; volume modéré (pas de budget p95 spécifique — MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit via écritures Accounting (chaîne traçable dépense → écriture). |
| D12 | Produit | 🟡 PARTIEL | Parcours demande → approbation → écriture testé (33 tests locaux verts) ; pas de seed pilote ni de golden journey end-to-end dédié. |

## Vérification locale (preuve)

```
php artisan test --filter="ExpenseClaimControllerTest|ExpenseClaimWorkflowTest|ExpenseAccountingEntriesFlowTest"
→ 33 passed (88 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Contrat Planning** (D1) : les invariants Expense (approbation,
   remboursement unique, devise) restent portés par les modèles Planning —
   à matérialiser dans le module pour sortir du contrat (dérogation #1414,
   résorbée pour les couches, à re-auditer pour les modèles).
2. **Golden journey** (D12) : test end-to-end demande → approbation →
   écriture comptable → export, avec seed déterministe.
3. **Contrat Finance** : formaliser l'interface d'écriture vers Accounting
   (événement versionné plutôt qu'appel direct — cf. plan §4 BC-09).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
