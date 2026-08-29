# Rapport de maturité — BC-04 HR

> **DEP-BC04 (issue #5880)** — Deep maturity, BC-04 Human Resources.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : 04.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-04).

## Périmètre

RH : employés, contrats, départements, profils, documents RH et cycle de vie
employé. `api/app/Modules/HR` (Application/Domain/Infrastructure/Interfaces/
Providers/routes), routes `/api/v1/hr*`, `/api/v1/employees*`, `/api/v1/contracts*`,
`/api/v1/departments*`, invitations, imports, exports, évaluations, départs.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet + sous-dossier `routes` propre ; domain models (Employee, Contract, Department, Evaluation, CareerEvent, DepartureNotice…). Vocabulaire RH documenté dans les specs (`docs/specifications/`). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (employees, contracts, departments…), FK et index présents, `check-migrations-tenant-schema.sh` vert. |
| D3 | Tenant | 🟢 PRÉSENT | Tous les modèles RH scopés (BelongsToCompany), Employee auto-rempli `company_id`, cross-tenant testés (CrossTenantIndexIsolationTest). |
| D4 | API | 🟢 PRÉSENT | 20+ contrôleurs HR, routes versionnées `/api/v1`, Requests/Resources, OpenAPI couvert (garde #5577/#2893), errors sûres (404 tenant-safe). |
| D5 | Autorisation | 🟢 PRÉSENT | EmployeePolicy, ContractPolicy, DepartmentPolicy, ensureManager/principal, invitation-first (401), guards `api.manager`. |
| D6 | Transactions | 🟢 PRÉSENT | Création employé avec hash password persisté (test dédié), import avec race guard (EmployeeImportRaceTest), départ avec validation de cycle. |
| D7 | Asynchronisme | 🟡 PARTIEL | Mails RH résilients (EmployeeMailResilienceTest), pas de jobs RH asynchrones majeurs (imports synchrones bornés). |
| D8 | Sécurité | 🟢 PRÉSENT | PII RH (SensitiveDataEncryptor pour données sensibles), audit via AuditLogController (exports/listes audités), secrets jamais dans les fixtures. |
| D9 | Frontend | 🟢 PRÉSENT | Admin dashboard (RH), apps mobile manager/hr (rôles, congés, documents), PWA. |
| D10 | Performance | 🟡 PARTIEL | Pagination sur les listes employés/documents, index dédiés ; budgets p95/p99 non versionnés (MAT-014 en cours par un autre agent). |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit trail RH (AuditLogController), runbooks ops globaux. |
| D12 | Produit | 🟡 PARTIEL | Golden journey employé (onboarding → contrat → documents) partiellement couvert ; pas de seed pilote RH dédié (seeders CRM/Fuel existent pour d'autres BC). |

## Vérification locale (preuve)

```
php artisan test --filter="EmployeeCreatePersistsPasswordHashTest|ContractByCountryTest|EmployeeDocumentTest"
→ 21 passed (108 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Golden journey RH** (D12) : test end-to-end onboarding → contrat → document
   → départ, avec seed pilote déterministe (pattern `CrmPilotSeeder`).
2. **Jobs RH asynchrones** (D7) : passer l'import employé en job `TenantScopedJob`
   (contrat BC-02) avec retry borné + DLQ, plutôt que synchrone borné.
3. **Budgets performance** (D10) : verrouiller p95/p99 sur les listes employés
   paginées une fois le référentiel MAT-014 mergé.
4. **Contrat de données** : formaliser les invariants de cycle de vie
   (actif → départ → archive) avec tests de transition d'état dédiés.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
