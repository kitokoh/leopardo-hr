# Feature Specification: Flux Paie → Comptabilité — écritures salariales + ordre de virement (#5239)

**Feature Branch**: `mod/accounting/5239-payroll-accounting-bridge`

**Created**: 2026-08-24

**Status**: Complète — **Parties 1 (journal des écritures) ET 2 (ordres de virement) implémentées (PR #5392, branche `mod/accounting/5239-payroll-accounting-bridge`)** ; DoD vérifiés par tests Feature (golden DZ persisté, idempotence, RBAC, isolation tenant, workflow virement).

**Input**: Issue #5239 — [P1][backend][payroll][compliance] Flux Paie → Comptabilité :
écritures salariales automatiques + ordre de virement exécuté par le comptable (Phase C).
Références : `docs/architecture/COMPTABILITE_CONCEPTION.md` §6.3 (séparation des fonctions,
confirmée fondateur), EPIC #5219, socle #5256 (mergé), #5221 (socle data Accounting),
#5223 (workflow documents), #5363 (journal — en cours), #5365 (paiements — en cours).

## Problème

Un run de paie validé (`PayrollRun.status = validated`) contient toute l'information
nécessaire à la comptabilité (brut, charges patronales, net, cotisations, impôt retenu par
employé, par pays). Aujourd'hui :

- le moteur Payroll sait produire des **écritures équilibrées en lecture seule**
  (`PayrollAccountingExportService::journalLines()`, #5256) mais **rien ne les persiste**
  dans le module Accounting (pas de table de journal) ;
- l'employeur doit saisir manuellement ses écritures et préparer les virements — double
  saisie, risque d'écart ;
- le paiement des salaires (virement) n'a pas de workflow tracé « préparé → exécuté »
  ni de rapprochement avec `AccountingPayment`.

**Séparation des fonctions (FOCUS intact)** : Payroll reste **maître du calcul** (règles
pays, IRG/CNAS, bulletins) ; Accounting **consomme la paie validée en lecture seule**.
Aucune modification du moteur de calcul Payroll.

## Décision

### 1. Journal des écritures salariales (persistance)

Nouvelle table tenant **`accounting_journal_entries`** (alignée sur le journal #5363 s'il
merge avant l'implémentation) :

| Champ | Type | Règle |
|---|---|---|
| `id` | bigint PK | |
| `company_id` | FK companies | scopé tenant (fail-closed #3727) |
| `entry_date` | date | date du run |
| `payroll_run_id` | FK payroll_runs | **référence de traçabilité** |
| `pay_slip_id` | FK pay_slips | null possible (ligne de régularisation globale) |
| `employee_id` | FK employees | nullable |
| `account_code` | string(16) | issu de `PayrollCountryChartOfAccounts` |
| `account_label` | string(120) | libellé au moment de l'écriture (historisation) |
| `debit` | numeric(15,2) | exclusif avec `credit` (débit OU crédit) |
| `credit` | numeric(15,2) | idem |
| `reference` | string(64) | `PAYROLL-RUN-{id}` |
| `source` | string(32) | `payroll_run` |
| `created_by` | FK users | audit (comptable/principal) |
| `created_at` / `updated_at` | | |

**Idempotence** : `UNIQUE (payroll_run_id, pay_slip_id, account_code, debit, credit)` —
une régénération ne double jamais les écritures.

**Équilibre garanti par construction** : on persiste **exactement** le résultat de
`journalLines()` (débit = crédit par bulletin et par run, vérifié par test) — **zéro
re-calcul**, zéro risque d'écart avec le moteur.

### 2. Déclencheur (additif, hors moteur de calcul)

À la validation d'un run (`PayrollRunController::validateRun`), **dispatcher un événement
additif** `PayrollRunValidated` (nouveau — le contrôleur dispatch déjà `WarmPaySlipPdfPaths…`,
même pattern ; **aucune modification du calcul**). Listener côté Accounting :
`GeneratePayrollJournalEntries` — idempotent (UNIQUE ci-dessus), échoue en silence si le
plan comptable pays est indisponible (log + statut `pending` documenté), rattrapable par
commande `accounting:generate-payroll-entries --run={id}`.

### 3. Ordre de virement (workflow comptable)

Nouvelle table tenant **`accounting_payment_orders`** :

| Champ | Règle |
|---|---|
| `company_id`, `payroll_run_id` (UNIQUE) | un ordre par run validé |
| `status` | enum `draft → prepared → executed` |
| `total_net` | somme des nets du run (devise entreprise) |
| `export_format` | `cnep_dz` / `sepa_xml` / `csv_generic` (formats Payroll réutilisés) |
| `export_file` | fichier généré au statut `prepared` |
| `bank_reference`, `executed_at`, `executed_by` | posés à l'**exécution** par le comptable |
| `reconciled_payment_id` | FK `accounting_payments` après rapprochement |

Workflow : le comptable **prépare** l'ordre (génération de l'export banque depuis le net du
run — réutilise `BankExportGenerator`, formats Payroll, en lecture seule) puis **exécute**
(référence banque + date). **Note d'implémentation (PR #5393)** : `AccountingPayment` étant
scopé document (document_id NOT NULL), l'ordre porte lui-même la traçabilité du règlement
(banque, référence, date, exécutant) — l'exécution vaut rapprochement ; le champ
`reconciled_payment_id` reste disponible pour un futur rapprochement bancaire externe.

### 4. RBAC et traçabilité

- **comptable** : enregistre les écritures (déclencheur) + prépare/exécute l'ordre de virement ;
- **principal** : lecture (écritures + ordres) ;
- **RH** : ne touche qu'au run de paie (comportement existant — aucune surface nouvelle).
- Audit : `created_by`/`executed_by` + horodatage ; chaque écriture porte la référence du run.

## User Scenarios & Testing

### US1 — Un run validé génère ses écritures automatiquement (Priority: P1)

**Independent Test**: valider un run DZ de test → `accounting_journal_entries` créées
(équilibre débit = crédit par bulletin et par run, référence `PAYROLL-RUN-{id}`) ; re-validation
ou régénération → aucune doublure (UNIQUE).

**Acceptance Scenarios**:

1. **Given** un run DZ `validated` (bulletin brut 60 000, charges, net 46 000),
   **When** le déclencheur s'exécute, **Then** écritures D 641 / D 645 / C 421 / C 431 /
   C 4421 présentes et **équilibrées** (golden test, valeurs #5256).
2. **Given** une régénération du même run, **When** la commande s'exécute, **Then** aucun
   doublon (contrainte UNIQUE), statut `generated` conservé.
3. **Given** un run non validé, **When** on tente de générer, **Then** refus
   (422 `PAYROLL_RUN_NOT_VALIDATED`) — règle #2223 préservée.
4. **Given** un pays sans plan comptable, **When** le run est validé, **Then** écritures non
   générées + alerte tracée (statut `pending`), rattrapable après ajout du plan.

### US2 — Le comptable prépare puis exécute l'ordre de virement (Priority: P1)

**Independent Test**: `POST /api/v1/accounting/payment-orders` (run validé) → `prepared`
avec export banque ; `POST .../{id}/execute` (référence banque + date) → `executed` ;
rapprochement → `AccountingPayment` créé.

**Acceptance Scenarios**:

1. **Given** un run validé, **When** le comptable prépare l'ordre, **Then** statut `prepared`,
   export CNEP/SEPA généré (formats Payroll réutilisés, lecture seule).
2. **Given** un ordre `prepared`, **When** le comptable exécute avec référence banque + date,
   **Then** statut `executed`, champs renseignés, `AccountingPayment` rapproché.
3. **Given** un ordre `executed`, **When** on tente de le ré-exécuter, **Then** `422
   PAYMENT_ORDER_ALREADY_EXECUTED`.
4. **Given** un employé RH ou employé simple, **When** il appelle les endpoints ordres/écritures,
   **Then** `403` (RBAC comptable/principal uniquement).

### US3 — Traçabilité et isolation tenant (Priority: P1)

**Independent Test**: cross-tenant — le run de l'entreprise A ne produit jamais d'écriture
lisible par B (404 fail-closed, scope `BelongsToCompany`).

**Acceptance Scenarios**:

1. **Given** deux entreprises, **When** A valide un run, **Then** B ne voit ni les écritures
   ni les ordres de A (404).
2. **Given** une écriture, **When** on consulte sa référence, **Then** `PAYROLL-RUN-{id}` +
   `created_by` renseignés (audit).

## Critères d'acceptation (DoD #5239)

- [x] **Écritures équilibrées (débit = crédit) pour un run de paie DZ réel** (golden test)
- [x] **Virement préparé puis marqué exécuté + rapproché** (test)
- [x] **Traçabilité run → écritures vérifiée ; isolation tenant testée**
- [x] **Spec approuvée avant code** (cette spec) ; CHANGELOG
- [x] Aucune modification du moteur Payroll (FOCUS intact — diff Payroll limité au dispatch
      additif de l'événement, aucun calcul touché)
- [x] OpenAPI : opérations list/show écritures + ordres (create/prepare/execute) + schémas ;
      couverture routes 100 % ; i18n ×4 des messages

## Dépendances d'implémentation

- `#5363` (journal des écritures — PR en cours) : aligner le schéma si mergé ; sinon le
  présent schéma est autonome.
- `#5365` (paiements — PR en cours) : le rapprochement de l'ordre utilise `AccountingPayment`
  (existe sur main) ; à aligner avec les routes paiements si mergé.
- Événement `PayrollRunValidated` : création (dispatch additif dans `validateRun`).

## Hors périmètre

- Modification du moteur de calcul Payroll (interdit — FOCUS)
- Déclarations sociales automatisées (documentées seulement)
- Comptabilité en partie double complète (Phase C suivante)
- Paiement en ligne des factures (#5272 — ADR 0017, décision en cours)
