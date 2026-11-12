# Module comptabilité — état des bloquants de l'audit

Ce document suit la remédiation des constats de l'audit du module comptable
(`api/app/Modules/Accounting`). Il sert de référence pour les issues ouvertes et
pour la revue de code.

Branche de travail : `feat/accounting-core-gaps-9367`.

## Bloquants traités

### #9367 — Comptabilité fournisseurs

**Constat** : `DocumentType` ne contenait que des documents de vente. Le compte
`401 Fournisseurs` existait au plan comptable mais n'était jamais mouvementé, et
une facture d'achat saisie en `invoice` produisait une écriture de vente.

**Traitement** :

- `DocumentType::PurchaseInvoice` / `PurchaseCreditNote`, avec `isPurchase()`,
  `isSales()`, `isPostable()`, `purchaseValues()`, `salesValues()` ;
- posting achats `D 607 + D 4456 / C 401`, avoir fournisseur en écriture inverse,
  décaissement `D 401 / C 512|53` ;
- `VatDeclarationService` : TVA déductible issue des factures d'achat, avoirs
  soustraits et non additionnés ;
- cohérence du tiers contrôlée (achat → fournisseur, vente → client) ;
- séries de numérotation achats distinctes (`FACF`, `AVOF`) ;
- export FEC : journal déduit du type de document, plus du préfixe de compte.

### #9368 — Plan comptable par pays dans le posting

**Constat** : `AccountingChartOfAccounts::for()` n'était appelé nulle part ;
`JournalPostingService` codait en dur `411`, `70`, `4457`, `512`, `53`. Un tenant
turc recevait `120/600/391` au provisioning et se faisait poster `411/70/4457`,
soit des comptes **absents de son plan**.

**Traitement** : `ChartAccountResolver` (plan comptable du tenant → registre pays
→ repli PCG explicite), `AccountingChartOfAccounts::for()` enfin consommé, plus
aucune constante de code de compte dans le posting.

### #9370 — Notion de journal

**Constat** : aucune entité journal. `FecExporter` devinait le journal depuis le
préfixe du compte, ce qui éclatait une facture entre `VE` et `OD` (la ligne de
TVA `4457` ne commence ni par 6, ni par 7, ni par `411`) et classait en `OD`
toutes les écritures d'un tenant non-PCG.

**Traitement** : table `accounting_journals` (code FEC 2-6 car., libellé, nature,
compte de trésorerie par défaut), colonne `journal_code` sur les écritures avec
backfill reproduisant l'ancienne heuristique, seed par référentiel
(`VE/AC/BQ/CA/PA/OD`, `SATIS/ALIS/BANKA/KASA`, `SAL/PUR/BNK/CSH`), résolution par
source, API CRUD (code immuable, suppression refusée en 409 si mouvementé).

### #9369 — Saisie d'écriture libre (journal d'OD)

**Constat** : aucune saisie manuelle. Impossible de passer une écriture
d'ouverture, une régularisation de TVA, une charge à payer (408), une dotation,
un virement interne ou une correction d'erreur.

**Traitement** : `accounting_manual_entries` + discriminant `line_no` (lève la
contrainte d'idempotence pour autoriser deux lignes sur un même compte),
`ManualJournalEntryService` (create/update/delete, équilibre, validation du plan
comptable, refus en période clôturée, pièce séquentielle par mois), API
`/journal/entries`.

## Bloquants encore ouverts

| Issue | Sujet | Pourquoi ce n'est pas fait ici |
|---|---|---|
| #9371 | Multi-devises converti à l'écriture | Touche le posting, les états financiers (`FinancialStatementService`), le FEC et la TVA ; nécessite une décision produit sur l'absorption des écarts de conversion. Non livré : le convertir à moitié produirait des bilans faux **silencieusement**, ce qui est pire que l'état actuel où le défaut est visible. |
| #9372 | TVA multi-taux par ligne | Dépend d'un référentiel de taux (`accounting_tax_rates`) et d'une ventilation en TVA déclarée ; le champ `tax_id` orphelin de `AccountingDocumentLine` doit être remplacé, pas doublé. |
| #9373 | Amortissements | Nouveau sous-domaine (immobilisations, plan, cession) ; le périmètre dépasse une correction. |
| #9374 | Policies d'autorisation | Matrice RBAC à valider avec le métier (un `comptable` peut-il clôturer ? supprimer un contact ?) — une matrice inventée serait pire que le middleware actuel. |
| #9375 | Analytique et budget | Nouveau sous-domaine. |
| #9376 | Écrans de facturation portail tenant | Front Next.js : les écrans dépendent de #9372 pour le multi-taux. |
| #9377 | App mobile tablette | `ios/` absent, app hors matrice de distribution, aucune adaptation responsive. |

## Vérification

Les tests de non-régression du module couvrent les quatre bloquants traités :

```
cd api && php vendor/bin/phpunit tests/Feature/Accounting --no-coverage
```

Suites ajoutées : `PurchaseAndChartResolverTest` (13 tests),
`ManualJournalEntryTest` (16 tests). Suites existantes alignées sur le
comportement corrigé là où elles verrouillaient l'ancien défaut
(`DocumentTypeTest` sur la liste des types, `VatDeclarationTest` sur la TVA
déductible, `FecExportTest` sur le code de journal).
