# ENDPOINTS_SENSIBLES — Matrice isolation tenant & RBAC (revue #5445)

Date : 2026-08-25 · Portée : endpoints export / téléchargement / PDF / rapports / preuves.
Méthode : inventaire routes (`grep export|download|pdf|reports|upload|proof`) + lecture de code de chaque contrôleur + tests cross-tenant (`tests/Feature/Security/SensitiveEndpointsTenantIsolationTest.php`).

**Garantie attendue (fail-closed #3727)** : ressource d'un autre tenant → **404** (jamais 403, pas de fuite d'existence) ; rôle insuffisant → 403 ; fichiers → `Content-Disposition` + stockage local ; exports volumineux → RBAC manager.

## Matrice

| Endpoint | RBAC | Scoping tenant | Fail-closed | Statut |
|---|---|---|---|---|
| `GET /export/employees` | `api.manager:principal,rh,comptable` | `tableForCompany(company_id)` | n/a (pas de ressource) | ✅ |
| `GET /export/attendance` | idem | idem | n/a | ✅ |
| `GET /export/pay-slips` | idem | idem + `isManager()` | n/a | ✅ |
| `GET /export/absences` | idem | idem | n/a | ✅ |
| `GET /export/training` | idem | idem | n/a | ✅ |
| `GET /export/contracts` | idem | idem | n/a | ✅ |
| `GET /export/vehicles` | idem | idem | n/a | ✅ |
| `GET /export/history` | idem | idem | n/a | ✅ |
| `GET /export/payroll-journal` | idem | idem + audit `recordSensitive` | n/a | ✅ |
| `GET /export/payroll-ledger` | idem | idem | n/a | ✅ |
| `GET /export/accounting-od` | idem | idem | n/a | ✅ |
| `GET /me/pay-slips/{id}/pdf` | owner \| manager | `company_id === actor` | **404** | ✅ + test |
| `GET /pay-slips/{id}/pdf` (manager) | manager | idem | **404** | ✅ |
| `GET /me/payment-documents/{id}/download` | owner \| manager | `where(company_id)` | **404** | ✅ |
| `GET /payroll-runs/{id}/export` | manager | `company_id === actor` | **404** | ✅ |
| `POST /payroll-runs/{id}/bank-export` | manager | `company_id === actor` | **404** | ✅ |
| `GET /bank-exports/{id}/download` | manager | `company_id === actor` | **404** | ✅ |
| `GET /salary-advances/{id}/proof` | owner \| manager | `company_id === actor` | **404** | ✅ + test |
| `GET /absences/{id}/proof` | owner \| manager | `company_id === actor` | **404** | ✅ + test |
| `GET /cabinet/documents/{id}/download` | owner | `company_id === actor` (**fix #5445**) | **404** | ✅ + test |
| `GET /contracts/{id}/pdf` | manager | vérifié (contrat scopé entreprise) | **404** | ✅ |
| `GET /audit-logs/export-csv` | principal | `forCompany(actor)` | n/a | ✅ |
| `GET /reports/vat-declaration` | comptable/principal | `currentCompany()` | n/a | ✅ |
| `GET /conversations/{thread}/messages/{message}/attachment` | participant | `findForActor` (company + participant) | **404** | ✅ |
| `GET /privacy/export` | self (RGPD) | données de l'acteur uniquement | n/a | ✅ |
| `GET /fleet/reports/{fuel,mileage,maintenance-due}` | manager | `where(company_id)` | n/a | ✅ |
| `GET /hr-reports` (plateforme) | super-admin | plateforme | n/a | ✅ |
| `GET /billing/invoices/{id}/pdf` | manager | vérifié (facture scopée entreprise) | **404** | ✅ |
| `GET /{absence}/proof` (RH legacy) | manager | idem absences | **404** | ✅ |

## Écarts corrigés dans cette revue

- **`CabinetDocumentController::authorizeOwnership`** : retournait **403** pour un document d'un autre tenant (fuite d'existence) → ajout du contrôle `company_id` AVANT le test de propriété → **404** fail-closed. (Tests : `test_cabinet_document_download_cross_tenant_returns_404`.)

## Garanties transverses constatées

- Tous les téléchargements servent les fichiers depuis `Storage::disk('local')` (jamais de path utilisateur brut dans le nom — `original_name` stocké en base) ; `Content-Disposition: attachment` posé (pay-slips, exports CSV, bank-exports).
- Les exports payroll (`/export/payroll-journal`, pay-slips PDF, bank-exports) passent par `auditLogger->recordSensitive` (traçabilité).
- Middleware `tenant` + `auth:sanctum` sur tous les groupes sensibles ; `throttle:api` global.
- Pattern dominant : `$resource->company_id !== $actor->company_id → abort(404)` puis RBAC → 403.

## Suivi

- Tests : `tests/Feature/Security/SensitiveEndpointsTenantIsolationTest.php` (5 scénarios : preuves absence/avance, cabinet, export RBAC, pay-slip PDF).
- Prochaine revue recommandée : endpoints d'écriture (store/update) des mêmes modules — non couverts ici (lecture seule).
