# Rapport de maturité — BC-08 ACCOUNTING

> **DEP-BC08 (issue #5884)** — Deep maturity, BC-08 Comptabilité.
> Audité le 2026-08-30. Agent propriétaire : 08.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-08, statut `active`).

## Périmètre

Plan comptable, journaux, écritures, exercices, lettrage, FEC et états
financiers. `api/app/Modules/Accounting`, routes
`api/routes/modules/accounting.php` (**61 routes**), multi-pays (PCG France,
plan DZ/MA…), documents comptables (factures, avoirs), relances.
Dépendances : BC-02 (tenant), BC-07 (PAYROLL — écritures de paie).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet : plan comptable, journaux, écritures, documents (numérotation paramétrable #5223, rendu PDF fr/ar RTL #5224), lettrage, exercices. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (accounting_journal_entries, chart of accounts…), index tenant (journal), provisioning automatique du plan comptable à la création d'entreprise (`ProvisionChartOfAccounts`), parité `CreatesMvpSchema`. |
| D3 | Tenant | 🟢 PRÉSENT | Écritures scopées `company_id` (fail-closed), journal 404-safe par tenant, tests d'isolation comptable (CrossTenant…). |
| D4 | API | 🟢 PRÉSENT | 61 routes versionnées (`/api/v1/accounting/*` : journal, écritures, documents, lettrage, exports), Requests/Resources, OpenAPI couvert (garde coverage). |
| D5 | Autorisation | 🟢 PRÉSENT | Rôle comptable (`isComptable()`, manager_role `comptable`), guards manager/comptable, accès documents borné. |
| D6 | Transactions | 🟢 PRÉSENT | Écritures en transactions (débit/crédit équilibrés, source-référencées), posting idempotent documenté (contrats BC-08 → Delivery COD), numérotation sans collision (garde séquences #5497). |
| D7 | Asynchronisme | 🟡 PARTIEL | `SendPaymentRemindersCommand` (relances J+7/J+15/J+30 idempotentes) ; exports/PDF générés dans le flux ou via jobs socle ; pas de DLQ dédiée comptabilité. |
| D8 | Sécurité | 🟢 PRÉSENT | Documents comptables = PII/paiements : URLs signées temporaires, audit trail, threat models paiements/POS (MAT-017), redaction logs. |
| D9 | Frontend | 🟢 PRÉSENT | Dashboard comptable web, documents PDF (fr + ar RTL), exports FEC/CSV. |
| D10 | Performance | 🟡 PARTIEL | `GET /api/v1/accounting/journal` au registre MAT-014 (p95 ≤ 500 ms, pagination) ; index volumétriques présents ; budgets p95/p99 non systématiques. |
| D11 | Exploitation | 🟡 PARTIEL | Runbook Accounting dédié existant (backup/restore/rollback, MAT-015) ; observabilité des jobs comptables partielle. |
| D12 | Produit | 🟢 PRÉSENT | Golden journey « clôture comptable » (GJ au registre MAT-013) ; démo exploitable 1 clic (SeedAccountingDemoCommand, données vitrine jamais réelles). |

## Vérification (preuve)

- **Tests** : `api/tests/Feature/Accounting*` — 38 fichiers, ~309 méthodes de
  test (statique) ; workflow approbation, flux écritures, relances, isolation.
- **Gardes** : registres MAT-013/014/015 cohérents (vérifiés localement).
- Exécution réelle en CI (checks requis) — aucune assertion dynamique prétendue ici.

## Recommandations (PR futures, non bloquantes)

1. **Posting inter-contextes** (D6) : formaliser le contrat d'écritures
   source-référencées (BC-08 ← BC-26 COD, BC-25, BC-24) avec idempotence
   testée par contrat (pattern `DeliveryAccountingContract` déjà en place).
2. **DLQ comptabilité** (D7) : dead-letter + replay (pattern BC-26-D07) pour
   les exports/relances.
3. **Budgets p95** (D10) : inscrire les endpoints de documents/lettrage au
   registre MAT-014.
4. **Invariants d'exercice** (D1/D6) : tests de clôture d'exercice (aucune
   écriture après clôture, réouverture contrôlée).

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
