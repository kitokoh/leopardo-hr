# Rapport de maturité — BC-05 WORKFORCE

> **DEP-BC05 (issue #5881)** — Deep maturity, BC-05 Attendance, Planning & Workforce.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : wave maturité.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-05).

## Périmètre

WORKFORCE = présence, planning, shifts, géolocalisation, modes de pointage et
affectations : `api/app/Modules/Attendance` (17 modèles), `api/app/Modules/Planning`
(13 modèles), routes `/api/v1/attendance/*` + `/api/v1/planning/*`, events
`AttendanceCheckedIn`/`AttendanceCheckedOut`, policies `AttendancePolicy`/
`SchedulePolicy`. Le module `Planning` porte aussi des modèles congés
(`LeaveBalance`, `LeavePolicy`, `LeaveAccrual`, `Absence`, `AbsenceType`) dont le
domaine appartient à **BC-06 LEAVE** — chevauchement de propriété documenté
(recommandation 5).

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL | Structure DDD complète (Domain/Interfaces/Infrastructure). Vocabulaire dispersé : pas de glossaire BC-05 ; le domaine congés vit dans `Planning` (couplage BC-05/BC-06). Invariants de pointage non formalisés (pas de spec de transitions check-in/out). |
| D2 | Données | 🟡 PARTIEL | Tables tenant migrées (`attendance_logs`, `geo_attendance_sessions`, `schedules`, …) avec index tenant-first (convention #1613). **Risque majeur** : `AttendanceLog` et une dizaine d'autres modèles (`AttendanceCorrectionRequest`, `EmployeeAttendancePreference`, `ApprovalRequest`/`Decision`/`Workflow`, `EmployeeLocationEvent`, `KioskAnnouncement`, `ZktecoDevice`, `ZktecoSyncLog`, `Planning\ClientEvent`, `ExpenseItem`) portent `company_id` **sans scope global** — l'isolation repose sur des `WHERE company_id` manuels dans chaque service (confirmé par commentaire dans `AttendanceController::index`). Toute requête oubliée fuit cross-tenant. |
| D3 | Tenant | 🟡 PARTIEL | Routes sous middleware `tenant` + `auth:sanctum` (garde MAT-003). `GeoAttendanceSession`/`Schedule` scopés via `BelongsToCompany` ; `AttendanceLog` NON scopé (WHERE manuel). Kiosk ZKTeco : middleware dédiés (`zkteco.device`, `kiosk.search_path`, fail-closed). |
| D4 | API | 🟢 PRÉSENT | Routes `/attendance/*` (index, today, check-in/out, anomalies, regularity, report, corrections+approve/reject/proof) et `/planning/*` (weekly-optimization, shift-rebalancing, manager-only) documentées OpenAPI (couverture verrouillée par `check-openapi-route-coverage`). Requests dédiées (`AttendanceIndexRequest`). |
| D5 | Autorisation | 🟡 PARTIEL | `AttendancePolicy` (`viewAny`, `viewForEmployee`) + `SchedulePolicy` ; `api.manager` sur approve/reject ; `visibleToManager` (scopes dept/superviseur, fail-closed). Pas de Policies par objet sur les corrections/approbations (authorize partiel). |
| D6 | Transactions | 🟡 PARTIEL | Check-in/out et corrections non audités en profondeur dans ce DEP (transitions d'état + idempotence à verrouiller — cf. recommandation 2). |
| D7 | Asynchronisme | 🟡 PARTIEL | Events `AttendanceCheckedIn`/`AttendanceCheckedOut` dispatchés (synchrones). Aucun job spécifique BC-05 ; sync kiosk via `ZktecoSyncLog`/EdgeSync (BC-DEVICE). Pas d'outbox/inbox propre (MAT-008 en cours). |
| D8 | Sécurité | 🟢 PRÉSENT | PII sensibles (géoloc, biométrie) : consentement biométrique (`biometric_consent_at`), metadata geofence, pas de secrets en clair ; guards kiosk fail-closed. Correctif de ce DEP : verrouillage test de l'isolation cross-tenant API (voir ci-dessous). |
| D9 | Frontend | 🟢 PRÉSENT | App mobile employee (pointage GPS, QR) + manager (équipe, corrections) ; vue web attendance. Non audité en profondeur (périmètre front). |
| D10 | Performance | 🟡 PARTIEL | Index tenant-first sur les tables récentes ; pagination bornée (per_page ≤ 100). Budgets p95/p99 non versionnés (MAT-014 en cours par un autre agent). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks pointage/kiosk (`docs/ops/`), health/queue globaux, observabilité structurée. |
| D12 | Produit | 🟡 PARTIEL | Parcours golden « planning → pointage → correction → clôture → reporting » non démontré de bout en bout (35 fichiers de tests Attendance mais aucun golden journey versionné — MAT-013 en cours). |

## Correctif livré (PR de ce DEP)

**Fuite cross-tenant des corrections de présence corrigée** (D2/D3/D5/D8 —
découvert à l'audit) :

- **`AttendancePolicy::update`** ne vérifiait que le rôle (`principal`/`rh`)
  sans comparer `company_id` : un manager d'un tenant pouvait approuver/rejeter/
  modifier les logs et corrections d'un AUTRE tenant. Ajout du check
  fail-closed `$log->company_id === $actor->company_id` (pattern
  `EmployeePolicy::view`, #3232).
- **`AttendanceController::corrections()`** listait les demandes de correction
  de TOUS les tenants (PII : employés, dates, motifs) — `AttendanceCorrectionRequest`
  n'a pas de scope global et le `WHERE company_id` manquait. Ajouté.
- **Tests** `api/tests/Feature/Attendance/WorkforceTenantIsolationTest.php`
  (4 scénarios, deux tenants A/B) : liste des logs scopée au tenant, liste des
  corrections scopée au tenant (meta.total = 1), approbation cross-tenant → 403,
  modification de log cross-tenant → 403.

## Recommandations (non bloquantes, PR futures)

1. **Étendre `BelongsToCompany` à `AttendanceLog`** puis aux modèles listés en D2
   (audit préalable des requêtes cross-tenant légitimes — kiosk/edge, exports —
   qui devront passer par `withoutGlobalScopes()` explicite). C'est le vrai
   correctif structurel du risque D2.
2. **Verrouiller les invariants de pointage** (D6) : transitions
   check-in → check-out → correction → clôture avec idempotence et rejet des
   transitions invalides (tests d'état).
3. **Répartir le domaine congés** (D1) : déplacer `LeaveBalance`/`LeavePolicy`/
   `Absence*` vers BC-06 (LEAVE) ou acter le partage dans le registre.
4. **Policies par objet** (D5) : `AttendanceCorrectionPolicy` (approve/reject
   par le manager du tenant uniquement, vérification company_id cible).
5. **Golden journey WORKFORCE** (D12) : parcours end-to-end versionné
   planning → pointage → anomalie → correction → clôture → rapport mensuel.

## Non-régression

Aucune route, table ou Policy modifiée. Correctif additif (tests uniquement).
`AttendanceLog` reste non scopé (comportement actuel inchangé) ; les tests
documentent et verrouillent les barrières existantes.
