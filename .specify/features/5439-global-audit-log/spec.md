# Feature Specification: Journal d'audit global (HR/Payroll/Attendance/Planning) + rétention RGPD (#5439)

**Feature Branch**: `mod/platform/5439-global-audit-log`

**Created**: 2026-08-25

**Status**: Implementation

**Input**: Issue #5439 — trou de traçabilité constaté (seul l'audit métier Accounting/Payroll était couvert ; les flux sensibles HR/Attendance/Planning/Auth ne sont pas tracés). Exigence RGPD (loi 18-07, politique AuditLog du repo) : un admin ne doit pas pouvoir modifier sans laisser de trace.

## Problème (vérifié 2026-08-25)

- `audit_logs` (table tenant + modèle `App\Core\Auth\Domain\Models\AuditLog`) **existe déjà** (depuis 2026-05-10, migrations idempotentes `schemaTableExists`/`schemaHasColumn`) et est utilisé ponctuellement (DataAccessAuditLogger, commandes, listener `App\Listeners\AuditLogger`).
- Manquent : (1) colonnes `module` et `request_id` ; (2) une **API d'écriture unifiée** (`AuditLog::record(...)`) ; (3) le **câblage** sur les flux sensibles ciblés ; (4) les **endpoints de lecture** RBAC + tenant-scoped ; (5) la **rétention configurable** par entreprise.

## Décision

Étendre l'existant (pas de nouvelle table) :

1. **Migration additive** (guards `schemaTableExists`/`schemaHasColumn`, pattern #1962) : colonnes `module` (string 100, nullable, index) et `request_id` (string 64, nullable, index) sur `audit_logs`.
2. **`AuditLog::record()`** — helper statique unifié : `record(string $module, string $action, ?Model $subject, ?Employee $actor, array $oldValues = [], array $newValues = [], ?string $requestId = null, array $metadata = [])`. Résout `company_id` (actor → subject → contexte tenant), `ip_address`/`user_agent` depuis la requête courante quand disponible, `created_at = now()`.
3. **Câblage ciblé** (lot 1, liste figée) :
   - Attendance : `AttendanceService::importExternalPunch()` / `recalculateLog()` (corrections/import — module `attendance`, action `attendance.import`/`attendance.recalculate`).
   - Payroll : `PayrollService::validate()` (validation de run — module `payroll`, action `payroll.validate`) + `delete()` (`payroll.delete`).
   - Planning : `AbsenceService::approve()/reject()/cancel()` (module `planning`, actions `planning.absence.approve|reject|cancel`) — **déjà touché par #5448, même branche de code, zéro conflit**.
   - HR : `DepartureService::registerDeparture()` (module `hr`, action `hr.departure.register`).
   - Auth : `LogoutAction::execute()` (module `auth`, action `auth.logout` — révocation de jeton).
4. **Lecture** : `GET /api/v1/audit-logs` — filtres `module`, `auditable_type`, `auditable_id`, `user_id`, `date_from`, `date_to` ; pagination (per_page ≤ 100) ; RBAC `api.manager:rh,principal` (le manager RH/principal lit l'audit de SA société ; l'employé → 403) ; isolation tenant stricte `company_id` (404 cross-tenant sur le détail). `GET /api/v1/audit-logs/{id}` — détail, 404 si hors tenant.
5. **Rétention RGPD** : clé `CompanySetting` `audit_retention_months` (défaut 36) ; `audit:purge` (existant) étendu pour purger **par entreprise** selon sa politique + journalisation de la purge dans `audit_logs` (action `audit.purge`) ; schedule mensuel dans le scheduler.
6. **Gates** : OpenAPI régénéré + `--check`, i18n ×4 (`errors.php` codes 403/404), CHANGELOG, tests Feature complets.

## Tests (DoD)

- Écriture : `approve()` d'une absence → entrée `audit_logs` avec module=planning, avant/après, actor.
- Lecture : employé 403 ; manager RH/principal 200 ; filtre module/entité/date ; pagination.
- Isolation tenant : détail d'une entrée d'une autre société → 404.
- Rétention : purge avec `CompanySetting` 1 mois supprime les vieilles entrées et journalise `audit.purge`.
- Non-régression : suites Attendance/Planning/HR/Payroll existantes.

## Hors périmètre (lot 1)

- Endpoint de configuration de la rétention (se fait en DB/admin — clé `CompanySetting` documentée).
- Traçage exhaustif de toutes les mutations (lots suivants).
- Fusion avec l'audit Accounting (`accounting_audit_logs`, #5273 — reste dans son module).
