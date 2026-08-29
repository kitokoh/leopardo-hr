# Rapport de maturité — BC-08 ACCOUNTING

> **DEP-BC08 (issue #5884)** — Deep maturity, BC-08 Accounting & Finance.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : wave maturité.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-08).

## Périmètre

ACCOUNTING = plan comptable, journaux, écritures, lettrage, exercices et FEC :
`api/app/Modules/Accounting` (13 modèles, 21 contrôleurs), routes
`/api/v1/accounting/*` (groupe RBAC `api.manager:principal,comptable`),
services `JournalService`/`AccountingWorkflowService`, exports FEC et CSV.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Vocabulaire complet (Document, JournalEntry, ChartAccount, FiscalYear, ClosedPeriod, Lettering, BankStatement, Payment, FEC) — le socle #5222/#5223/#5234/#5435 a structuré le contexte. |
| D2 | Données | 🟢 PRÉSENT | Tables tenant migrées, `company_id` sur les 13 modèles, index tenant-first (convention #1613), écritures avec débit/crédit, pièce, période (YYYY-MM). |
| D3 | Tenant | 🟢 PRÉSENT | **Les 13 modèles utilisent `BelongsToCompany`** (scope global fail-closed #3727) — l'isolation est portée par le trait, pas par des WHERE manuels (contrairement à BC-05). |
| D4 | API | 🟢 PRÉSENT | Routes versionnées (documents, journal, export FEC/CSV, lettrage, contacts, fiscal years, bank statements) documentées OpenAPI (couverture verrouillée), Requests dédiées (`JournalPeriodRequest`…). |
| D5 | Autorisation | 🟡 PARTIEL | RBAC par **middleware de route** (`api.manager:principal,comptable`) — **aucune Policy dédiée** (0 appel `authorize` sur les 21 contrôleurs). Cohérent et fonctionnel (matrice documentée en tête d'`accounting.php`), mais pas granulaire par objet/action (recommandation 1). |
| D6 | Transactions | 🟢 PRÉSENT | Écritures balancées (débit = crédit vérifié par le journal), clôture de période (fige le journal, `AccountingClosedPeriod`), lettrage, re-posting de document. |
| D7 | Asynchronisme | 🟡 PARTIEL | Exports FEC/CSV synchrones (StreamedResponse). Aucun job/outbox (MAT-008 en cours). |
| D8 | Sécurité | 🟢 PRÉSENT | FEC (données comptables sensibles) sous RBAC principal/comptable ; partage de documents encadré (`AccountingDocumentShare` + contrôle cross-tenant). |
| D9 | Frontend | 🟢 PRÉSENT | Web client (comptabilité : documents, journal, FEC), admin dashboard (états). Non audité en profondeur. |
| D10 | Performance | 🟢 PRÉSENT | Journal par période indexé, exports streamés, pagination bornée. Budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks comptabilité/FEC, observabilité structurée. |
| D12 | Produit | 🟡 PARTIEL | Parcours golden « document → écriture → clôture période → lettrage → FEC » partiellement testé (AccountingActivationTest, FEC tests) mais pas de golden journey versionné complet (MAT-013 en cours). |

## Correctif livré (PR de ce DEP)

**Verrouillage test de l'isolation cross-tenant du journal comptable**
(D3/D5/D8) — `api/tests/Feature/Accounting/AccountingTenantIsolationTest.php`
(2 scénarios, deux tenants) :

- `GET /api/v1/accounting/journal?period=2026-06` (manager A) → uniquement les
  écritures du tenant A (2 sur 5 seedées, A/B) — l'isolation par scope global
  est prouvée au niveau API ;
- lecture modèle (`AccountingJournalEntry::query()` en contexte tenant A) → 2
  écritures (preuve du scope `BelongsToCompany`, fail-closed #3727).

## Recommandations (non bloquantes, PR futures)

1. **Policies formelles** (D5) : introduire `AccountingPolicy` (viewAny/view sur
   documents, entries, FEC) avec les checks `company_id` explicites — la RBAC par
   middleware protège l'accès mais pas les transitions métier (ex. cancel d'un
   document payé) au niveau objet.
2. **Verrouiller la clôture de période** (D6) : test d'invariant — toute
   écriture sur une période close doit échouer (422) ; le re-posting après
   clôture est refusé.
3. **Golden journey comptable** (D12) : parcours end-to-end
   document → écritures balancées → clôture → lettrage → FEC, versionné.

## Non-régression

Aucun code de production modifié — correctif purement contractuel (tests +
rapport).
