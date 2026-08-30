# DEP-BC07 — Rapport de maturité BC-07 PAYROLL

> **Issue :** [DEP-BC07 #5883](https://github.com/kitokoh/leopardo-hr/issues/5883)
> **Contexte :** BC-07 — PAYROLL (périodes, règles, calculs, snapshots, bulletins, validations, exports paie)
> **Date :** 2026-08-30
> **Statut :** **Actif** — audit 12 dimensions du code sur `main`.

## 1. Cartographie (état `main`)

| Élément | État |
|---|---|
| `api/app/Modules/Payroll` | 138 fichiers — module le plus volumineux du monorepo (DDD complet : Domain/Models, Infrastructure/Services, Interfaces Api/V1, exports) |
| Routes | `/api/v1/payroll*` (payroll_engine.php : périodes, règles, calculs, validations, exports) |
| Registre BC | `BC-07` = PAYROLL, dépendances BC-04 (HR) / BC-08 (ACCOUNTING, écritures) / BC-13 (COMMS) |

Preuves de code : `PayrollCalculationAudit` (audit des calculs), `PayrollPaymentOrders`, écritures comptables de paie (`payroll_accounting_entries`), machine à états de validation (batch D #5960 : « machine à états, entitlements, facturation, recouvrement » DEP-BC21), exports paie (bank exports, formats étendus), i18n paie ×4 (fr/en/ar/tr), coverage ≥ 80 % vérifié en CI (`Coverage Payroll ≥ 80 %`).

## 2. Scorecard des 12 dimensions

| Dim | Domaine | Verdict | Constat / preuve |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Module DDD complet (138 fichiers), règles par pays/contrat, calculs audités, vocabulaire paie documenté |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (calculs, snapshots, ordres de paiement, écritures), index tenant-first, garde #1962 vert |
| D3 | Tenant | 🟢 PRÉSENT | Modèles scopés `BelongsToCompany`, tests cross-tenant, périodes paie par compagnie |
| D4 | API | 🟢 PRÉSENT | Routes `/api/v1/payroll*` versionnées, Requests/Resources, OpenAPI couvert, erreurs 422 métier |
| D5 | Autorisation | 🟢 PRÉSENT | Validation paie multi-niveaux (manager/rh/comptable), guards `api.manager`, policies par rôle |
| D6 | Transactions | 🟢 PRÉSENT | Calculs transactionnels avec audit (`PayrollCalculationAudit`), validations verrouillantes (PAYROLL_ALREADY_VALIDATED), écritures comptables cohérentes |
| D7 | Asynchronisme | 🟡 PARTIEL | Calculs lourds synchrones bornés ; exports asynchrones partiels (bank exports) |
| D8 | Sécurité | 🟢 PRÉSENT | PII salaires strictement tenant-scopées, `PayrollCalculationAudit` traçable, secrets jamais loggés, i18n des messages d'erreur |
| D9 | Frontend | 🟢 PRÉSENT | Écrans paie manager (admin dashboard + apps), bulletins, exports |
| D10 | Performance | 🟢 PRÉSENT | Coverage CI ≥ 80 % sur Payroll, index dédiés, snapshots de calcul ; budgets p95/p99 versionnés partiellement (MAT-014) |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés + corrélation (MAT-009), audit des calculs et validations, runbooks ops |
| D12 | Produit | 🟢 PRÉSENT | Cycle de paie complet couvert (période → règle → calcul → snapshot → validation → export), golden journey paie dans `golden-journeys.json` |

## 3. Vérification (preuve)

Suites sur `main` : `Payroll*Test` (calculs, périodes, validations, exports, écritures), `Coverage Payroll ≥ 80 %` (garde CI), tests Accounting interop (écritures de paie), `PayrollAccountEntriesTest`. Gardes locales : registre ✅, migrations ✅, OpenAPI ✅.

## 4. Recommandations (PR futures, non bloquantes)

1. **Calculs asynchrones** (D7) : passer les calculs de période en job `TenantScopedJob` avec résultat snapshot (pattern DEP-BC21).
2. **Budgets performance** (D10) : verrouiller p95/p99 sur les endpoints de calcul et d'export.
3. **Contrat d'événements paie** (D7) : outbox `payroll.calculation.completed.v1` pour les consommateurs Accounting/Notification.

## 5. Non-régression

Aucun changement de code de production dans ce rapport — audit + documentation uniquement. CRM commercial plateforme intact.
