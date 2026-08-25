# Feature Specification: Module Comptabilité — Profondeur production (plan comptable, grand livre, balance, états financiers, FEC, exercices, lettrage)

**Feature Branch**: `mod/accounting/5422-consolidation-production`
**Issue**: #5422 (consolidation production module Comptabilité)
**Created**: 2026-08-25
**Status**: Implementation

## Contexte

Le module Comptabilité livrait facturation/trésorerie (documents, paiements, relances, TVA) + un journal
d'écritures débit/crédit (#5234). Le constat d'audit (2026-08-25) : **12 PRs portant la profondeur n'étaient
jamais mergées** (conflits, checks rouges) et il manquait les fondamentaux d'une comptabilité de production.
Cette issue consolide les PRs en attente PUIS ajoute la profondeur : plan comptable paramétrable, grand livre,
balance de vérification, bilan + compte de résultat, export FEC, exercices comptables + clôture, lettrage.

## Périmètre

### 1. Consolidation (11 PRs intégrées, conflits résolus une fois)

| PR | Issue | Contenu |
|---|---|---|
| #5408 | Refs #5411 | fix routes+openapi — require doublon accounting.php |
| #5363 | #5234 | Journal des écritures débit/crédit + clôture de période |
| #5357 | #5225 | Email documents + portail client sécurisé tokenisé |
| #5377 | #5273 | Audit log + rétention RGPD + purge |
| #5387 | #5274 | Données démo/seed + E2E parcours facture |
| #5395 | #5230 | Tableaux de bord comptables + export CSV impayés |
| #5388 | #5288 | Wizard d'activation Comptabilité |
| #5403 | #5233 | Portail client web — espace document sécurisé |
| #5397 | #5235 | Notes de frais approuvées → écritures comptables automatiques |
| #5394 | #5239 | Flux Paie → Comptabilité — écritures salariales + ordre de virement |
| #5416 | #5270 | Multi-devises + taux de change |

**Décision** : #5239 a deux implémentations concurrentes (#5392 côté Accounting vs #5394 côté Payroll).
Choix retenu : **#5394** (spec kit dédié, aucune collision avec les tables journal). #5392 = obsolète.

### 2. Profondeur de production (issue #5422)

**US1 — Plan comptable paramétrable (P1)** : table `accounting_chart_accounts` (company_id, code, label,
type asset/liability/equity/revenue/expense, classe PCG/SCF 1-8, is_system, is_active), provisionnée par
défaut à la création d'entreprise (listener `ProvisionChartOfAccounts`, idempotent), CRUD API
(GET/POST/PUT/DELETE `/accounting/chart*`). Comptes système non supprimables, code unique par entreprise.

**US2 — Grand livre + balance de vérification (P1)** : `GET /accounting/ledger?account_code&period` —
écritures paginées avec running balance continu entre pages et solde d'ouverture ;
`GET /accounting/balance?period` — totaux débits/crédits par compte, totaux généraux, indicateur
d'équilibre (écart < 0.005).

**US3 — Bilan + compte de résultat (P1)** : `GET /accounting/statements/balance-sheet?year` — actif
(immobilisations/stocks/créances/trésorerie), passif (dettes tiers/emprunts), capitaux propres + résultat
net, invariant total_actif = total_passif_et_capitaux ; `GET /accounting/statements/income-statement?period`
— produits/charges par sections (exploitation/financier/exceptionnel), résultat.

**US4 — Export FEC (P1)** : `GET /accounting/journal/export-fec?period` — fichier FEC DGFiP (13 colonnes :
JournalCode, JournalLib, EcritureNum, EcritureDate, CompteNum, CompteLib, PieceRef, PieceDate, Libelle,
Debit, Credit, Devise, MontantDevise), CSV UTF-8 BOM, anti-injection OWASP, EcritureNum séquentiel par pièce.

**US5 — Exercices comptables + clôture (P1)** : table `accounting_fiscal_years` (open/closed) ;
`GET/POST /accounting/fiscal-years`, `POST /accounting/fiscal-years/{year}/close` — calcule le résultat net,
écriture de report à nouveau (12/891, pièce CLO-{year}), fige les 12 périodes de l'année
(`accounting_closed_periods`), re-clôture → 422.

**US6 — Lettrage (P2)** : colonnes additives `letter`/`lettered_at` sur `accounting_journal_entries` ;
`POST /accounting/journal/lettering` (≥ 2 écritures, même compte, Σ débits = Σ crédits, lettre unique),
`DELETE /accounting/journal/lettering/{letter}`.

### 3. Fiabilisation

- Cron quotidien des relances (`accounting:send-payment-reminders` 06:00, `onOneServer`).
- Corrections de merges : provider cassé (seed #5387), routes dupliquées, relations payment orders
  (`payment_order_id` explicite), audit des encaissements (PaymentController), période TVA en DomainException.
- Fix pré-existant : imports SmartAttendance obsolètes post-ADR-0016 (contrôleur + test).

## User Stories (extraites)

### US1 — Plan comptable provisionné et paramétrable

**Independent Test**: `AccountingChartCrudTest` — entreprise créée → plan provisionné (≥ 70 comptes,
comptes 411/70/4457/512 présents) ; création compte analytique 201 → 201 ; suppression compte système → 422 ;
suppression compte avec écritures → 422 ; isolation tenant → 404.

### US2 — Balance équilibrée

**Independent Test**: `AccountingLedgerTest` — facture 1190 TTC + paiement → balance période : comptes
411/70/4457/512, totals 1690/1690, `balanced: true` ; grand livre 411 → running balance continu.

### US3 — Bilan équilibré

**Independent Test**: `FinancialStatementTest` — facture + avoir + charges → total_actif =
total_passif_et_capitaux, résultat net cohérent, `balanced: true` ; année vide → zéros équilibrés.

### US4 — FEC valide

**Independent Test**: `FecExportTest` — en-tête exact 13 colonnes, EcritureNum par pièce séquentiel,
Σ débit = Σ crédit, anti-injection `=CMD`, période vide → 422 FEC_NO_ENTRIES.

### US5 — Clôture d'exercice

**Independent Test**: `FiscalYearAndLetteringTest` — exercice 2026 avec écritures → close : écriture
CLO-2026 (12/891), périodes 2026-01..12 clôturées, status closed ; re-close → 422.

### US6 — Lettrage

**Independent Test**: même fichier — lettrage de 2 lignes équilibrées (411 D / 411 C) → letter posée ;
déséquilibré → 422 LETTERING_UNBALANCED ; comptes différents → 422 ; délettrage → 204.

## Non-périmètre (v2)

- Rapprochement bancaire automatique (flux de relevé) — reste manuel (`matched`).
- Paiement en ligne portail (#5272, ADR-0017 — décision fondateur requise).
- Amortissements, provisions, analytique multi-axes.
