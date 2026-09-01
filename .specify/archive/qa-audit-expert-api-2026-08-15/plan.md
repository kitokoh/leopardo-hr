# Plan: Audit expert API — 2026-08-15

**Input**: spec.md (US A1-A11) + Constitution + audit 2026-08-15 (routes, logiques, sécurité, i18n, config)

## Architecture / Décisions techniques

- **A1 Solde de congés — snapshot = source de vérité** : `AbsenceService::approve()` vérifie désormais `LeaveBalance` (snapshot, même source que `currentAvailableBalance`) avec `lockForUpdate` dans la transaction ; `LeaveBalanceLog` reste une piste d'audit (log `absence_approved` écrit après déduction). Le lookup de la balance est scopé `(employee_id, company_id, absence_type_id)` — jamais la dernière ligne globale (mélange de types/entreprises).
- **A2 Webhook email-bounce — fail-closed** : ajouter `services.mail_bounce_webhook.secret` dans `config/services.php` + clé `MAIL_BOUNCE_WEBHOOK_SECRET` dans `.env.example` ; si non configuré → `403` + `Log::warning`. Ne jamais `== ''` bypass.
- **A3 Stripe webhook** : seules les erreurs de signature → 400/401 ; toute exception de traitement → 500 (retry Stripe) ou enqueue.
- **A4 Pointage** : `lockForUpdate` sur la recherche de session ouverte (check-in/check-out) dans la transaction + migration index unique partiel `(employee_id, date, session_number)` si table compatible.
- **A5 leave-balances** : lire le paramètre de route `{employeeId}` (fallback query `employee_id` pour rétro-compat) → scope employé.
- **A6 jours ouvrés** : réutiliser le calendrier entreprise (`PublicHolidayService`) pour `days_count`, ou documenter la convention par type d'absence (décision produit à confirmer — défaut : jours ouvrés).
- **A7 payroll clipping** : `sumApprovedLeaveDays` clippe `days_count` sur `[period_start, period_end]`.
- **A8 OpenAPI** : script de comparaison routes↔paths (parseur déjà existant dans l'audit) ; aligner les noms de paramètres sur les routes ; ajouter les opérations manquantes prioritaires (webhooks publics, trial, user, onboarding invitation) ; régénérer le SDK via `generate-openapi-sdk.mjs` si la garde l'exige.
- **A9 verrous & transitions** : `create()` absence → transaction + `lockForUpdate` sur `LeaveBalance` ; `ExpenseClaim` → validation de transition (map états autorisés) ; `RequestTrialSignup` → rethrow / échec explicite si OTP ou CompanyRequest échoue (transaction).
- **A10 1/10e** : `referenceGross12Months` compte `calculated` + `validated`.
- **A11 hygiène** : retirer `temp_password` du JSON de verify ; labels via `lang/*` ; clamp `per_page` (1-100) ; clés ar/tr ; un seul verbe par action (PUT) ; précision complète overtime ; `NON_WORK_TYPES` étendu ; chunking `executeCalculateRun` ; log fail-open marketing ; `try/finally` kiosk search_path ; binding `current_company` middleware AI ; URLs env ; supprimer route dupliquée webhook test ; canonicaliser notifications (PATCH + POST mark-all-read, documenter).

## Phases

### Phase 1 — P1 bloquants (A1, A2)
- `AbsenceService::approve()` + tests (`LeaveApprovalAfterCreditTest`, race, isolation).
- Webhook email-bounce fail-closed + config + `.env.example` + tests.

### Phase 2 — P2 données & contrat (A3-A10)
- Stripe webhook 500, pointage lock, leave-balances scope, jours ouvrés, clipping paie, route dupliquée webhook test, notifications canonicales, OpenAPI (paths + param names), verrous absence/expense/trial, 1/10e calculated.

### Phase 3 — P3 hygiène (A11)
- temp_password, labels lang, per_page, clés ar/tr, verbes uniques, overtime, NON_WORK_TYPES, chunking, marketing secret, kiosk search_path, middleware AI, URLs env.

## Fichiers touchés (référence)

- `api/app/Modules/Planning/Infrastructure/Services/AbsenceService.php`
- `api/app/Modules/Planning/Interfaces/Api/V1/LeavePolicyController.php`
- `api/app/Modules/Notification/Interfaces/Api/V1/Controllers/EmailBounceWebhookController.php`
- `api/app/Modules/Billing/Interfaces/Api/V1/StripeWebhookController.php`
- `api/app/Modules/Attendance/Infrastructure/Services/AttendanceService.php` + migrations tenant
- `api/app/Modules/Expense/Interfaces/Api/V1/Controllers/ExpenseClaimController.php`
- `api/app/Modules/Payroll/Infrastructure/Services/PayrollCalculator.php`
- `api/app/Modules/Billing/Application/Actions/{RequestTrialSignup,VerifyTrialSignup}.php`
- `api/routes/modules/*.php`, `api/routes/api.php`
- `api/openapi.yaml`, `api/config/services.php`, `.env.example`
- `api/lang/{ar,tr,fr,en}/payroll.php`, `api/app/Modules/HR/Interfaces/Api/V1/Controllers/EmployeeController.php`
- `api/app/Modules/Marketing/Interfaces/Api/V1/Controllers/MarketingLeadController.php`
- `api/app/Modules/Attendance/Interfaces/Api/V1/KioskController.php`
- `api/routes/ai.php`, `api/config/cameras.php`, `api/config/demo.php`

## Contraintes

- Constitution §IV : PHPStan strict level 8 vert, Pint, tests avant/avec implémentation, isolation tenant testée sur tout endpoint sensible.
- Constitution §II : toute requête tenant scopée `company_id` ; migrations tenant dans `database/migrations/tenant/`.
- Constitution §VII : PR par issue avec `Closes #N`, CHANGELOG, branche supprimée après merge ; auto-assignation + marker branch avant de coder.
- Rétro-compat : ne pas casser `/me/leave-balances`, `/notifications/read-all`, `{webhookEndpoint}` (alias si nécessaire).
- Ne pas toucher aux branches/PR en cours des autres agents.
