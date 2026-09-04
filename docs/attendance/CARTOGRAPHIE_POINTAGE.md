# Cartographie du pointage — Attendance comme bounded context (ATT-001, #6760)

> Référentiel : `docs/attendance/CARTOGRAPHIE_POINTAGE.md` — issue #6760 (ATT-001).
> Objet : cartographier les tables, modèles, routes, services, jobs, événements,
> appareils et tests du pointage, et fixer **Attendance** comme propriétaire des
> événements de présence.
> Statut : 2026-09-02 — fondation du lot Attendance IA (#6760→#6777).

## 1. Périmètre & définition

**Attendance** (module `api/app/Modules/Attendance`) est le bounded context
propriétaire des **événements de présence** : preuve de présence (check-in /
check-out / multi-événements), modes de pointage, corrections, fermetures de
journée/période, appareils de pointage (kiosques, terminaux ZKTeco) et
enrôlement biométrique.

Ce qui appartient à chaque contexte :

| Sujet | Propriétaire | Note |
|---|---|---|
| Événements de présence (`attendance_logs`) | **Attendance** | source de vérité des heures |
| Correction / clôture / régularité | **Attendance** | `attendance_correction_requests`, `attendance_day_closures` |
| Kiosques & terminaux (`attendance_kiosks`, `zkteco_devices`) | **Attendance** | identification d'appareil |
| Enrôlement biométrique & consentement | **Attendance** | `biometric_enrollment_requests` (consentement), `biometric_enrollments` (gabarits, BIO-003 #6764) |
| Identité de l'employé (flags `biometric_face_enabled`, `biometric_fingerprint_enabled`, `badge_number`, `zkteco_id`, matricule) | **Identity / BC-04 HR** | consommé par Attendance (lecture) |
| Scoping multi-tenant (`company_id`, schémas) | **Tenant** | mécanique partagée `BelongsToCompany`, `TenantManager` |
| Calcul de paie (heures, HS, anomalies) | **Payroll** | consomme la **projection** `AttendanceLog` — jamais les adaptateurs biométriques |

## 2. Tables (schéma tenant)

| Table | Modèle | Rôle |
|---|---|---|
| `attendance_logs` | `Domain/Models/AttendanceLog.php` | événement de présence (date, check_in/out, `method`, `biometric_type`, `source_device_code`, `external_event_id`, `synced_from_offline`, `work_type`, `punch_meta`, `punch_photo_path`, statut, heures) |
| `attendance_kiosks` | `Domain/Models/AttendanceKiosk.php` | kiosque de pointage (`device_code` haché #5588, `sync_token_hash`, `biometric_mode`, statut) |
| `biometric_enrollment_requests` | `Domain/Models/BiometricEnrollmentRequest.php` | demande d'activation biométrique (consentement mobile → approbation manager) |
| `biometric_enrollments` *(à créer — BIO-003, #6764)* | `Domain/Models/BiometricEnrollment.php` | gabarits biométriques versionnés, chiffrés, tenant-scoped |
| `attendance_correction_requests` | `Domain/Models/AttendanceCorrectionRequest.php` | demandes de correction de pointage |
| `attendance_day_closures` / `attendance_period_closures` | `DayClosure` / `PeriodClosure` | fermetures (aucun pointage sur jour clos) |
| `attendance_mode_settings` / `employee_attendance_preferences` | `AttendanceModeSettings` / `EmployeeAttendancePreference` | modes de pointage entreprise / préférences employé |
| `geo_attendance_sessions` / `employee_location_events` | `GeoAttendanceSession` / `EmployeeLocationEvent` | pointage géo |
| `zkteco_devices` / `zkteco_sync_logs` | `ZktecoDevice` / `ZktecoSyncLog` | terminaux ZKTeco & historiques de sync |
| `kiosk_announcements` | `KioskAnnouncement` | annonces affichées au kiosque |
| `employees` (colonnes biométriques) | `Core/Auth/Domain/Models/Employee.php` | `biometric_face_enabled`, `biometric_fingerprint_enabled`, `biometric_consent_at`, `badge_number`, `zkteco_id` |

## 3. Points d'entrée (routes) — état réel

Routes RH/manager (`api/routes/modules/rh.php`, middleware `auth:sanctum` + `tenant`) :

- `POST /attendance/check-in`, `POST /attendance/check-out`, `GET /attendance/today`, `GET /attendance`, `GET /attendance/anomalies`, `GET /attendance/regularity`, `GET /attendance/monthly-report`
- `POST|GET /attendance/corrections` (+ approve/reject, proof)
- `GET /biometric-enrollment-requests` (+ approve/reject, manager)
- `POST /kiosks` (manager — provisioning : retourne `device_code` + `sync_token` une seule fois)

Kiosque public (`api/routes/modules/rh.php` + `integrations.php`, middleware `throttle:kiosk-punch` + `kiosk.search_path`, auth `X-Kiosk-Token`) :

- `GET /kiosks/{deviceCode}/roster`, `POST /kiosks/{deviceCode}/punch`, `POST /kiosks/{deviceCode}/sync`
- `POST /kiosks/{deviceCode}/employee-info|qr-punch`, `GET /kiosks/{deviceCode}/announcements|leave-balance` (`integrations.php`)

ZKTeco (`api/routes/modules/integrations.php`) :

- CRUD `/zkteco/devices` (manager), `POST /zkteco/devices/{serialNumber}/push-users`
- `POST /zkteco/heartbeat/{serialNumber}`, `POST /zkteco/sync-attendance/{serialNumber}` (middleware `zkteco.device`, auth `X-Device-Token`)

Pointage géo (`api/app/Modules/Attendance/routes/geo.php`, préfixe `/api/v1/attendance`) :

- `GET /config`, `PUT /preferences`, `POST /geo-events`, sessions géo, validation manager, `mode-settings`, day-closures

Self-service employé : `POST /api/v1/auth/biometric-enrollment` (GET/POST, `routes/api.php`), `/me/qr-profile` (QR de pointage #3365).

## 4. Services & flux

Le moteur central est `Infrastructure/Services/AttendanceService.php` — toutes
les entrées convergent vers `checkIn()` / `checkOut()` / `importExternalPunch()`
(ce dernier idempotent via `external_event_id`, garde jour clos, multi-événements
`work_type`). La photo de pointage (`ensurePunchPhotoProvided`), le géofencing
(`alertManagersIfOutsideGeofence`) et le calcul d'heures (`HoursCalculator`) y
sont branchés.

| Flux | Service | Chemin |
|---|---|---|
| Check-in/out mobile | `AttendanceService` | `Infrastructure/Services/AttendanceService.php` |
| Kiosque (punch + sync offline) | `KioskAttendanceService` | `Infrastructure/Services/KioskAttendanceService.php` |
| Terminaux ZKTeco (register, heartbeat, pull) | `ZktecoIntegrationService` | `Infrastructure/Services/ZktecoIntegrationService.php` |
| Enrôlement consentement (mobile) | `BiometricEnrollmentService` | `Infrastructure/Services/BiometricEnrollmentService.php` |
| Pointage géo | `GeoSessionManager`, `AttendanceGeofenceService` | `Infrastructure/Services/` |
| Fermetures & heures | `AttendanceDayClosureService`, `AttendanceHoursCalculator` | `Infrastructure/Services/` |
| Modes entreprise | `Application/Actions/Set{Company,Employee}AttendanceMode` | `Application/Actions/` |
| Jobs/Edge (offline) | `Modules/EdgeSync` (EdgeNode, `ProcessSyncQueueJob`, Push/Register actions) | `api/app/Modules/EdgeSync/` |

## 5. Événements

- `App\Events\AttendanceCheckedIn` / `AttendanceCheckedOut` (centraux, dispatchés
  par `AttendanceService`) — consommés par `AuditLogger` + `WebhookListener`.
- **AttendanceRecorded.v1** (`App\Events\AttendanceRecorded`, ATT-003 #6768) :
  événement versionné unifié (tenant, employé, type, occurred_at_utc, méthode de
  vérification, résultat, kiosque, corrélation) publié pour découpler Payroll du
  moteur biométrique. *(À créer dans le lot ATT-003.)*

## 6. Diagramme de flux

```mermaid
flowchart LR
    M[Mobile] -->|check-in/out| AS[AttendanceService]
    K[Kiosque X-Kiosk-Token] -->|/kiosks/{code}/punch| KAS[KioskAttendanceService]
    K -->|/kiosks/{code}/sync offline| KAS
    Z[Terminal ZKTeco X-Device-Token] -->|sync-attendance| ZIS[ZktecoIntegrationService]
    G[Géo/GPS] -->|geo-events| GSM[GeoSessionManager]
    Q[QR signé] -->|qr-punch| KAS
    AS --> AL[(attendance_logs)]
    KAS --> AS
    ZIS --> AS
    GSM --> AS
    AL -->|AttendanceRecorded.v1| EV[Événements]
    AL -->|projection lecture seule| P[Payroll]
    BE[BiometricEnrollment] -->|enrôlement/consentement| EMP[(employees flags)]
```

## 7. Invariants & règles (cette issue)

- **R-1 (propriété)** : Attendance est l'unique propriétaire des événements de
  présence. Aucun autre BC n'écrit dans `attendance_logs` hors des services
  d'Attendance.
- **R-2 (frontière Payroll)** : Payroll consomme la projection validée
  `AttendanceLog` (lecture) et les événements — jamais les adaptateurs
  biométriques, services kiosque ou modèles d'appareils d'Attendance.
- **R-3 (frontière inverse)** : Attendance n'importe jamais Payroll.
- **R-4 (garde CI)** : `dev-hub/tools/check-attendance-boundary.sh` (câblé dans
  `.github/workflows/architecture-check.yml`, job Hygiene Guards) fait échouer la
  CI sur toute violation de R-2/R-3. Ne pas contourner via l'allowlist
  d'isolation (#5584) : la paire `Payroll → Attendance` y est tolérée pour
  l'héritage `AttendanceLog` uniquement.
- **R-5 (adaptateurs)** : les moteurs biométriques (visage, empreinte, OCR)
  sont branchés derrière des contrats (`Core/AI`, BIO-001 #6762, AI-001 #6770) ;
  Attendance, Payroll et FuelStation n'importent jamais de SDK fournisseur
  (garde QLT-003 #6777).

## 8. Tests existants (état réel, non exhaustif)

- Feature : `tests/Feature/Attendance/*` (CheckIn, CheckOut, MultiPunch,
  CorrectionWorkflow, Geo*, PunchPhoto, ManualUpdate, Reports…),
  `tests/Feature/Security/KioskCrossTenantIsolationTest.php`,
  `tests/Feature/Security/KioskDeviceCodeAtRestTest.php`,
  `tests/Feature/Attendance/KioskMultiEventPunchTest.php`,
  `tests/Feature/Attendance/KioskSyncSkippedEventsTest.php`,
  `tests/Feature/Attendance/ZktecoControllerTest.php`,
  `tests/Feature/Attendance/ZktecoSyncMethodEnforcementTest.php`,
  `tests/Feature/Edge/*` (offline), `tests/Feature/Payroll/PayrollAttendanceAnomalyApiTest.php`.
- Unit : `tests/Unit/Attendance/*` (AttendanceHoursCalculator, AttendanceService…).

## 9. Issues de la fondation

- ATT-002 #6761 (modèle multi-méthodes) → `Domain/Enums/VerificationMethod.php`,
  `Domain/Enums/VerificationResult.php`, `Domain/Support/PunchRecordingPolicy.php`.
- BIO-001 #6762 / AI-001 #6770 (contrats) → `Core/AI`.
- ATT-003 #6768 (événement versionné), BIO-002→BIO-009, AI-002, QLT-001→003 :
  voir `docs/attendance/` et les issues liées.
