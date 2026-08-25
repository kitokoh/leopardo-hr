# Feature Specification: Module Comptabilité — Journal des écritures + clôture de période (Closes #5234)

**Feature Branch**: `mod/accounting/5234-journal`
**Issue**: #5234 (P2, backend, compliance)
**Created**: 2026-08-23
**Status**: Implementation

## Contexte

Le socle data Comptabilité (#5221, mergé) fournit `accounting_documents` (facture, avoir, reçu…),
`accounting_document_lines`, `accounting_payments` et `accounting_settings`. Cette issue livre la
**première brique du journal comptable** (Phase C du concept doc §4.2, réduite au périmètre de l'issue) :
écritures débit/crédit **dérivées** des documents et paiements, journal consultable par période,
export standard pour l'expert-comptable, clôture de période simple.

**Décisions de cadrage** (périmètre minimal, aucun conflit avec les branches en cours #5223/#5225) :

- Le posting est **explicite et idempotent** (service appelable + endpoint de re-posting) — il n'est PAS
  branché automatiquement sur la création de document (les workflows #5223/#5225 arrivent par d'autres PR).
- Seuls les documents à **impact comptable** sont passés : `invoice` et `credit_note` (statut ≠ draft/cancelled).
  `proforma`, `quote`, `delivery_note` et `receipt` n'ont pas d'écriture propre — le `receipt` est la preuve
  d'encaissement, c'est le **paiement** qui porte le mouvement de trésorerie (évite le double comptage).
- Plan de comptes **PCF/SYSCOHADA simplifié** : 411 Clients, 70 Ventes, 709 RRR, 4457 TVA collectée,
  512 Banques, 53 Caisse (si méthode cash).
- Écritures **toujours équilibrées** (Σ débit = Σ crédit) — invariant vérifié par le service à chaque
  posting ET par les tests sur le journal complet.

## User Stories

### US1 — Une facture produit des écritures équilibrées (P1)

**Independent Test**: test Feature — après `JournalPostingService::postDocument($facture)`, 3 lignes
`accounting_journal_entries` (411 D total_ttc / 70 C subtotal_ht / 4457 C tax_amount) avec Σ débit = Σ crédit.

**Scenarios**:
1. **Given** une facture `sent` (HT 100, TVA 19), **When** posting, **Then** 3 écritures, balance 0, période `2026-08` dérivée de `issue_date`.
2. **Given** un avoir, **When** posting, **Then** écritures inversées (709 D / 4457 D / 411 C), balance 0.
3. **Given** un proforma / devis / BL / reçu ou un document `draft`/`cancelled`, **When** posting, **Then** aucune écriture.
4. **Given** un paiement banque, **When** posting, **Then** 512 D / 411 C, balance 0 ; **Given** paiement cash → 53 Caisse.

### US2 — Le posting est idempotent et ré-conciliable (P1)

**Independent Test**: deux `postDocument()` successifs → toujours 3 lignes pour la facture (updateOrCreate), pas de doublon.

### US3 — Le journal se consulte par période et s'exporte pour l'expert (P1)

**Independent Test**: `GET /api/v1/accounting/journal?period=2026-08` retourne les écritures + totaux ;
`GET /api/v1/accounting/journal/export.csv?period=2026-08` retourne un CSV UTF-8 (BOM), séparateur `;`,
colonnes `date;piece;libelle;compte;intitule;debit;credit`, lignes neutralisées anti-injection CSV, ligne de total équilibrée.

### US4 — La clôture de période fige le journal (P2)

**Independent Test**: `POST /api/v1/accounting/journal/periods/2026-08/close` crée la période close ;
toute tentative de posting (document ou paiement) daté dans une période close lève `PeriodClosedException` (422).

## Requirements

- **FR-001**: migration tenant additive `2026_08_23_000003_create_accounting_journal_tables.php` :
  - `accounting_journal_entries` : `company_id` (uuid, non nullable), `entry_date` (date), `period` (char(7) `YYYY-MM`),
    `source_type` (string: document|payment), `source_id` (bigint), `account_code` (string), `account_label` (string),
    `debit`/`credit` (decimal 15,2, défaut 0, check `debit=0 or credit=0`), `description` (string nullable),
    index `(company_id, period)`, `(company_id, entry_date)`, unique `(company_id, source_type, source_id, account_code)`.
  - `accounting_closed_periods` : `company_id` (non nullable), `period` (char(7)), `closed_by` (string), `closed_at`,
    unique `(company_id, period)`.
- **FR-002**: modèles `AccountingJournalEntry`, `AccountingClosedPeriod` (Domain/Models, trait `BelongsToCompany`, casts).
- **FR-003**: `JournalPostingService` (Infrastructure/Services) : `postDocument()`, `postPayment()`, `postSource()`,
  `entriesForPeriod()`, `closePeriod()`, `isPeriodClosed()`, `isPeriodBalanced()` — transactions DB, invariants.
- **FR-004**: exceptions domaine `UnbalancedJournalEntryException`, `PeriodClosedException` (Domain/Exceptions).
- **FR-005**: `JournalCsvExporter` (Infrastructure/Exports) : closure streamée, BOM, `CsvCellSanitizer::neutralize()`,
  ligne de total, contrôle équilibre en tête (échoue vite si déséquilibré).
- **FR-006**: `AccountingJournalController` + `JournalPeriodRequest` (validation `period:YYYY-MM`) :
  - `GET /api/v1/accounting/journal` (comptable) → `{ period, entries[], totals{debit,credit}, balanced }`
  - `GET /api/v1/accounting/journal/export.csv` (comptable) → CSV streamé
  - `POST /api/v1/accounting/journal/periods/{period}/close` (comptable) → 201 + `{ period, closed_at }`
  - `POST /api/v1/accounting/documents/{document}/journal` (comptable) → re-posting idempotent du document + ses paiements
  - routes dans `api/routes/modules/accounting.php` (require dans `api.php`), RBAC comptable (permission `accounting.journal`)
- **FR-007**: OpenAPI (3 paths + 1 param), miroir `dev-hub` régénéré, note dans `SCENARIOS_TEST_API_GITHUB_ACTIONS.md`.
- **FR-008**: tests Feature `AccountingJournalTest` (US1→US4) + isolation tenant ; CHANGELOG root + api.

## Hors périmètre

- Aucun branchement automatique du posting sur #5222/#5223/#5225 (PR séparées) — endpoints explicites seulement.
- Pas de plan comptable complet paramétrable, pas d'intégration Expense/Paie → écritures (Phase C ultérieure).
- Pas de catalogues i18n module (portés par #5224/#5225, fichiers en conflit sinon) — messages d'erreur domaine en français,
  validations FormRequest natives Laravel.

## Success Criteria

- Tous les postings équilibrés (invariant testé unitairement + sur journal complet) ;
- `JournalPostingService` idempotent ; période close = immutable (422) ;
- export du mois exploitable par un expert : BOM UTF-8, `;`, comptes, totaux, anti-injection CSV ;
- tests verts + PHPStan Strict 0 erreur + Pint PASS + redocly exit 0 ; `Closes #5234` dans la PR ; CHANGELOG.
