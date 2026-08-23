# Matrice RBAC HR — issue #5262

**Date** : 2026-08-22 · **Portée** : module HR (API `/api/v1`), tenant-scoped.
**Source de vérité** : cette matrice reflète l'**application réelle** (policies + guards) — tout écart constaté est un bug à corriger. Couverture : `tests/Feature/HR/RbacMatrixTest.php`.

## Rôles

| Rôle (`role` / `manager_role`) | Description |
|---|---|
| `employee` | Employé non-manager — self-service uniquement |
| `manager` / `principal` | Direction — accès tenant large |
| `manager` / `rh` | Gestion RH — dossier, contrats, paie partielle |
| `manager` / `dept` | Manager de département — scopé à son département (PA2-SEC-002) |
| `manager` / `superviseur` | Superviseur d'équipe — scopé à son équipe directe (PA2-SEC-003) |
| `manager` / `comptable` | Comptable — salaires, paie, pas d'édition RH |

## Matrice d'accès

Légende : ✅ autorisé · ❌ refusé (403) · 🔒 scopé (visible uniquement périmètre) · ⚪ non concerné.

| Ressource / Action | employee | principal | rh | dept | superviseur | comptable |
|---|---|---|---|---|---|---|
| **Employés — liste** `GET /employees` | ❌ | ✅ | ✅ | 🔒 | 🔒 | ✅ |
| **Employé — fiche** `GET /employees/{id}` | 🔒 (soi-même) | ✅ | ✅ | 🔒 | 🔒 | ✅ |
| **Employé — création** `POST /employees` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Employé — édition** `PUT/PATCH /employees/{id}` | 🔒 (soi-même) | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Employé — archivage** `POST /employees/{id}/archive` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Salaire visible** (`salary_base` dans EmployeeResource) | 🔒 (soi-même) | ✅ | ✅ | 🔒 | 🔒 | ✅ |
| **Contrats** CRUD + lifecycle | 🔒 (les siens) | ✅ | ✅ | 🔒 | 🔒 | ❌ (lecture via paie) |
| **Contrats — templates pays** `GET /contracts/templates` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Contrats — signature** `POST /contracts/{id}/sign` | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Évaluations** (workflow draft→submitted→acknowledged) | 🔒 (les siennes) | ✅ | ✅ | 🔒 | 🔒 | ❌ |
| **Événements de carrière** `GET /career-events` | 🔒 (les siens) | ✅ | ✅ | 🔒 | 🔒 | ❌ |
| **Événements de carrière** create/approve/apply | ❌ | ✅ | ✅ | 🔒 | 🔒 | ❌ |
| **Départements / Positions** CRUD | ❌ | ✅ | ✅ | 🔒 (lecture) | ❌ | ❌ |
| **Organigramme** `GET /org-chart`, `/departments/{id}/hierarchy` | 🔒 (lecture) | ✅ | ✅ | 🔒 | 🔒 | ✅ |
| **Self-service** `/me/*` (profil, career, contrats, loans, trainings, pay-slips) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Données bancaires** (`iban`, `bank_account`, `national_id`) | jamais exposées (chiffrées au repos, EncryptedCast) | | | | | |
| **Cross-tenant** (toute ressource d'une autre société) | ❌ 404/403 — fail-closed (scope `BelongsToCompany` + gardes explicites) | | | | | |

## Gardes qui appliquent la matrice

| Garde | Fichier |
|---|---|
| `EmployeePolicy` (viewAny/view/create/update/archive) | `app/Policies/EmployeePolicy.php` |
| `ContractPolicy` + gardes controller (principal/rh) | `app/Policies/ContractPolicy.php`, `ContractController` |
| `EvaluationPolicy` (PA2-SEC-002/003) | `app/Policies/EvaluationPolicy.php` |
| `CareerEventPolicy` (PA2-SEC-002/003) | `app/Policies/CareerEventPolicy.php` |
| `DepartmentPolicy` / `PositionPolicy` | `app/Policies/` |
| Scope tenant global `BelongsToCompany` | `app/Shared/Traits/BelongsToCompany.php` |
| Masquage salarial défensif (issue #5262) | `EmployeeResource::toArray()` |
| Chiffrement au repos (iban/bank_account/national_id) | casts `EncryptedCast` sur `Employee` |

## Notes

- **dept/superviseur** : voient les salaires de LEUR équipe (nécessaire aux approbations) mais n'éditent jamais de dossier hors périmètre.
- **comptable** : lit les salaires/paie, n'édite ni dossier RH ni contrat (pas `principal`/`rh`).
- Les données bancaires ne transitent **jamais** par l'API (masquage total) — uniquement en import/export interne chiffré.
