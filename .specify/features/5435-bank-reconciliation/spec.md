# Feature Specification: Module Comptabilité — Rapprochement bancaire (Closes #5435)

**Feature Branch**: `mod/accounting/5435-bank-reconciliation`
**Issue**: #5435 (P1, backend, compliance — Phase D)
**Created**: 2026-08-25
**Status**: Implementation

## Contexte

Le module Comptabilité couvre facturation, trésorerie et écritures (journal #5234, flux Paie → écritures #5239/#5394, notes de frais #5235/#5397, multi-devises #5270/#5416, consolidation #5422 mergée). Les paiements sont saisis manuellement (`PaymentRegistrationService` → `accounting_payments`, statuts `pending/recorded/matched`, `reconciled_at`) sans comparaison systématique avec le relevé bancaire. L'écart relevé ↔ comptabilité n'est détecté qu'à la main, et le lettrage des écritures est hors périmètre.

Cette issue livre la **Phase D : rapprochement bancaire** — import de relevé (CSV paramétrable), matching heuristique relevé ↔ paiements enregistrés, état de rapprochement, et lettrage des écritures concernées.

**Décisions de cadrage** :
- **Import CSV** : mapping de colonnes par entreprise (configurable via `accounting_settings.bank_statement_mapping`), séparateur, format de date, signe ; validation stricte ligne par ligne (pas d'échec silencieux, erreurs renvoyées par ligne, aucune ligne insérée si l'en-tête est invalide).
- **Idempotence** : clé unique `(company_id, statement_period, import_reference)` — ré-import du même relevé refusé (409, message explicite). L'unicité est portée par une contrainte DB `unique` + vérification applicative.
- **Matching heuristique** : lignes relevé ↔ `accounting_payments` sur (montant ± tolérance configurable, date ± N jours, référence/bénéficiaire) avec score de confiance ; correspondances non automatiques → file de matching manuel.
- **Lettrage** : le rapprochement d'un paiement marque l'écriture comptable correspondante lettrée (référence de lettrage partagée sur `journal_entries`).
- **RBAC** : routes sous `api.manager:principal,comptable` (rôle `comptable` dans le repo — pas `accountant`), isolation tenant fail-closed par le trait `BelongsToCompany`.
- **Portée** : pas d'import MT940/OFX en v1 (lot 2), pas de matching sur multi-devises (montant converti en devise société au moment du rapprochement, tolérance sur la devise société).

## User Stories

### US1 — Import d'un relevé bancaire (P1)

**Independent Test** : `tests/Feature/Accounting/BankStatementImportTest.php`

**Scenarios** :
- Given une entreprise avec `accounting_settings` par défaut, When je POST un CSV valide (`/api/v1/accounting/bank-statements/import`) avec `statement_period`, `import_reference` et le fichier, Then la réponse 201 contient `imported` lignes, le relevé a le statut `imported`, et chaque ligne est persistée avec `statement_line_id` utilisable.
- Given un CSV avec des lignes invalides (montant non numérique, date malformée, libellé vide), When j'importe, Then les lignes valides sont insérées et la réponse contient `errors[]` avec numéro de ligne et message (aucun échec silencieux).
- Given un relevé déjà importé avec la même clé `(company_id, period, import_reference)`, When je ré-importe, Then réponse 409 (doublon refusé, aucune ligne insérée).
- Given un CSV sans en-tête conforme au mapping, When j'importe, Then réponse 422 (validation échouée, aucune ligne insérée).
- Given un employé `employee` (non manager), When il POST l'import, Then 403.
- Given un manager du tenant B, When il tente d'accéder au relevé du tenant A, Then 404 (isolation tenant fail-closed).

### US2 — Rapprochement automatique (P1)

**Independent Test** : `tests/Feature/Accounting/BankReconciliationTest.php`

**Scenarios** :
- Given un relevé importé avec une ligne (montant 1190.00, date 2026-08-05, référence « VIR-2026-001 ») et un paiement enregistré identique (même montant, `received_at` 2026-08-05, référence « VIR-2026-001 »), When je POST `/api/v1/accounting/bank-statements/{statement}/reconcile`, Then le paiement passe `matched` avec `reconciled_at` renseigné, la ligne relevé est `matched`, et le score de confiance est 100 %.
- Given un paiement dont la référence diffère mais montant identique et date ± 2 jours, When je lance le matching, Then la correspondance est proposée avec un score < 100 % (matching approximatif) et reste en file de matching manuel.
- Given une ligne relevé sans paiement correspondant, When je lance le matching, Then la ligne reste `pending` et apparaît dans l'écart de l'état de rapprochement.
- Given un paiement déjà `matched` par ailleurs, When le matching tourne, Then il n'est jamais re-rapproché (idempotence).

### US3 — Matching manuel + lettrage (P2)

**Independent Test** : `tests/Feature/Accounting/BankReconciliationManualTest.php`

**Scenarios** :
- Given une ligne relevé `pending` et un paiement `recorded`, When un manager POST `/api/v1/accounting/bank-statement-lines/{line}/match` avec `payment_id`, Then les deux passent `matched`, l'écriture comptable du paiement est lettrée (`reconciled`), et la réponse confirme la référence de lettrage partagée.
- Given une ligne relevé déjà `matched`, When je tente de la re-matcher, Then 409.
- Given un paiement du tenant B, When un manager du tenant A tente de le matcher sur sa ligne, Then 404.

### US4 — État de rapprochement (P2)

**Independent Test** : `tests/Feature/Accounting/BankStatementStatusTest.php`

**Scenarios** :
- Given un relevé avec 5 lignes (3 matched, 1 pending, 1 écart), When je GET `/api/v1/accounting/bank-statements/{statement}/status`, Then la réponse contient solde initial/final attendus vs réels, lignes rapprochées/en attente, écart total.
- Given un relevé du tenant B, When un manager du tenant A le GET, Then 404.

## Requirements

- FR-001 — Les tables `bank_statements` et `bank_statement_lines` DOIVENT être des tables tenant avec `company_id` uuid NON nullable, indexées, créées via une migration additive et idempotente (`schemaTableExists`), nommée avec la référence d'issue #5431 (`2026_08_25_000003_5435_*`).
- FR-002 — `bank_statements` DOIT contenir : `statement_period` (string, ex. `2026-08`), `import_reference` (string), `opening_balance`/`closing_balance` (decimal nullable), `status` (`imported|reconciling|reconciled`), `file_hash` (string), `metadata` (encrypted:array), unique `(company_id, statement_period, import_reference)`.
- FR-003 — `bank_statement_lines` DOIT contenir : `statement_id` FK, `line_date` (date), `label` (string), `amount` (decimal signé), `external_reference` (string nullable), `status` (`pending|matched`), `matched_payment_id` FK nullable, `confidence` (int nullable), `category` (string nullable), unique `(statement_id, line_number)`.
- FR-004 — L'import CSV DOIT : valider l'en-tête contre le mapping (colonnes requises), parser ligne par ligne (`str_getcsv`), signaler chaque ligne invalide dans `errors[]` (numéro de ligne + message), refuser le doublon d'import (409) avant toute insertion, et retourner `{statement, imported, skipped, errors}`.
- FR-005 — Le mapping CSV DOIT être configurable par entreprise dans `accounting_settings.bank_statement_mapping` (colonnes `date,label,amount,reference` + séparateur + format date + signe), avec défaut `;`, `Y-m-d`, montants positifs = débit.
- FR-006 — Le matching DOIT comparer montant (valeur absolue ± tolérance configurable, défaut 0.01), date (± N jours, défaut 3), référence/bénéficiaire (bonus de score) ; le score DOIT être 100 % pour correspondance exacte (montant + date + référence), sinon < 100 % et proposé au matching manuel.
- FR-007 — Le rapprochement (auto ou manuel) DOIT passer le paiement `recorded → matched` avec `reconciled_at = now()` (réutilise `PaymentRegistrationService::reconcile`), marquer la ligne relevé `matched`, et lettrer l'écriture comptable associée si elle existe (référence de lettrage partagée).
- FR-008 — Toutes les routes DOIVENT être sous RBAC `api.manager:principal,comptable` et hériter du scope tenant fail-closed (`BelongsToCompany`) ; toute requête cross-tenant DOIT répondre 404.
- FR-009 — Les messages et codes d'erreur DOIVENT être i18n ×4 (fr/en/tr/ar) via `__('accounting.*')` et les codes machine `errors.php` ; les clés ajoutées DOIVENT passer `check-accounting-i18n.py`.
- FR-010 — Les nouveaux endpoints DOIVENT être documentés dans `api/openapi.yaml` (+ miroir + SDK régénérés, `make openapi-check` vert) et ne DOIVENT pas régresser les guards de coverage OpenAPI.

## Success Criteria

- SC-001 — `tests/Feature/Accounting/BankStatementImportTest.php` vert : import valide / en-tête invalide / doublon 409 / lignes partielles signalées / RBAC 403 / isolation 404.
- SC-002 — `tests/Feature/Accounting/BankReconciliationTest.php` vert : matching exact (score 100), approximatif (tolérance), sans correspondance (ligne pending), idempotence (jamais re-matché).
- SC-003 — `tests/Feature/Accounting/BankReconciliationManualTest.php` vert : matching manuel + lettrage effectif + 409 sur re-match + 404 cross-tenant.
- SC-004 — `tests/Feature/Accounting/BankStatementStatusTest.php` vert : état complet (soldes, lignes matched/pending, écart).
- SC-005 — Guards CI verts : PHPStan strict/level-max (fichiers neufs 0 baseline), Pint, coverage module ≥ 70 %, i18n ×4, openapi-check, migration guard #5431, hygiene.
- SC-006 — CHANGELOG ×2 mis à jour ; `.specify/features/5435-bank-reconciliation/` (spec + tasks) ; `docs/architecture/COMPTABILITE_CONCEPTION.md` § rapprochement complété.

## Assumptions

- Le rôle comptable est `comptable` dans ce repo (pas `accountant`) — la convention `api.manager:principal,comptable` s'applique.
- `accounting_payments.reference` est chiffré (`encrypted` cast) : le matching par référence se fait sur le libellé/montant/date, la référence étant comparée après déchiffrement en mémoire (jamais par requête SQL sur la colonne chiffrée).
- La devise des lignes relevé est la devise société (multi-devises hors périmètre v1, tolérance calculée en devise société).
- Le lettrage cible `journal_entries` si la colonne de lettrage existe (migration additive conditionnelle) ; sinon le rapprochement reste au niveau paiement/ligne (le lettrage comptable complet est porté par #5234/#5239).
