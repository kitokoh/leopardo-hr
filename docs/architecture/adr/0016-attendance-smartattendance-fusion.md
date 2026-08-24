# ADR 0016 - Fusion des modules Attendance et SmartAttendance

## Statut

**Proposée** — issue #5264, décision fondateur requise pour approbation.

**Date** : 2026-08-23

## Contexte

Deux modules de pointage coexistent dans le monolithe modulaire (`api/app/Modules/`) :

| Module | Rôle | Tables | Modèles | Services | API |
|---|---|---|---|---|---|
| **Attendance** (historique) | Pointage kiosque, ZKTeco, mobile, approbations, corrections, synchronisation calendrier | 12 | 12 | 10 | `/api/v1/attendance/*`, `/zkteco/*`, kiosque web |
| **SmartAttendance** (récent) | Pointage géolocalisé (GPS) : sessions entrée/sortie, géofence, préférences de mode | 4 | 4 | 2 | `/api/v1/smart-attendance/*` (11 routes) |

### Audit comparatif

**Attendance** — 12 tables tenant : `attendance_logs` (table de **fait centrale**), `attendance_kiosks`, `zkteco_devices`, `zkteco_sync_logs`, `biometric_enrollment_requests`, `attendance_correction_requests`, `approval_workflows`, `approval_requests`, `approval_decisions`, `calendar_connections`, `calendar_events`, `kiosk_announcements`.

`attendance_logs` est le point d'entrée unique du pointage pour toute la plateforme. Ses consommateurs hors module : `PayrollCalculator`, `PayrollCycleService`, `PayrollAnomalyService` (Paie), `EstimationService` (Planning), `EmployeeController`/`HrReportController`/`MeController`/`PrivacyController` (HR), `SyncEngineService` (EdgeSync), `IntentEngine` (AI), `FleetController`/`VehicleController` (Fleet), `ConversationService` (Notification), `PlatformCompanyHealthService` (Platform), `DashboardController`/`MyDashboardController`/`WebEmployeeController` (web), événements `AttendanceCheckedIn`/`AttendanceCheckedOut`, commandes `AutoCloseAttendanceCommand`/`PilotReportCommand`, middlewares (`AuthenticateZktecoDevice`) et policies (`AttendancePolicy`, `ApprovalRequestPolicy`).

Le workflow d'approbation (`approval_workflows`/`approval_requests`/`approval_decisions`) est **générique et partagé** via le trait `Approvable` (`app/Shared/Traits/Approvable.php`), utilisé par les absences et les notes de frais — il n'appartient pas conceptuellement au pointage seul.

**SmartAttendance** — 4 tables tenant : `geo_attendance_sessions` (session GPS ouverte à l'entrée, fermée à la sortie, **devient un `attendance_log` uniquement après approbation** — FK `attendance_log_id`), `employee_location_events` (événements GPS minimisés RGPD : entrée/sortie de zone, consentement), `attendance_mode_settings` (mode de pointage entreprise : `forced_mode` null|gps_auto|qr|manual|mixed, `punch_photo_mode`, géofence entreprise), `employee_attendance_preferences` (préférence employé).

### Constats de couplage existant (le « déjà fusionné »)

1. **`GeoAttendanceSession` → `attendance_logs`** : les migrations `2026_06_29_000205` et `2026_08_09_000006` ajoutent la méthode `geo_auto` à `attendance_logs.method` ; l'approbation d'une session GPS (`ApproveGeoSession`) **crée un `attendance_log`**. Le module SmartAttendance écrit déjà dans la table de fait du module Attendance.
2. **`AttendanceService` → SmartAttendance** : le check-in mobile du module Attendance consomme `AttendanceModeSettings` (mode photo/géofence) et `AttendanceGeofenceService` pour valider le punch.
3. **Géofence déjà partagée mais deux chemins d'usage** : le calcul est centralisé dans `AttendanceGeofenceService` (module Attendance), consommé par `AttendanceService` (évaluation au punch mobile) **et** par `GeoSessionManager`/`ProcessGeoEntry` (SmartAttendance) via l'injection `GeofenceValidatorInterface` → `AttendanceGeofenceService` (`SmartAttendanceServiceProvider`). Deux points d'appel du même service : la fusion doit unifier les chemins d'usage, pas réécrire le calcul.
4. **Deux commandes de fermeture automatique** : `AutoCloseAttendanceCommand` (Attendance) et `AutoCloseGeoSessionsCommand` (SmartAttendance) font le même métier en parallèle.
5. **Apps mobiles** : `leopardo_employee`, `leopardo_hr`, `leopardo_manager` consomment **les deux** surfaces API (`features/attendance/` et `features/smart_attendance/`).
6. **Contrat OpenAPI** : les deux familles de chemins (`/attendance/*` ≈ 13 chemins, `/smart-attendance/*` 11 chemins) sont documentées dans `api/openapi.yaml`.

### Problème

La frontière entre les deux modules est **arbitraire et poreuse** : le pointage mobile peut passer par l'un ou l'autre selon la configuration entreprise ; le mode de pointage vit dans SmartAttendance mais pilote le comportement du check-in d'Attendance ; deux chemins d'usage de la géofence et deux fermetures automatiques coexistent. Pour le programme « Pointage 100 % » (#5264→#5269 : modes unifiés kiosque/géo/ZKTeco/mobile, corrections/validations, rapports, heures sup DZ), cette duplication est une dette structurelle qui se paiera à chaque feature.

## Options envisagées

### Option A — Cloisonnement strict (statut quo formalisé)

Garder deux modules et documenter des frontières claires.

- ✅ Aucun changement de code.
- ❌ Ne résout aucune duplication (géofence ×2 chemins, auto-close ×2, mode ×2) ; coût cumulé à chaque évolution du pointage ; incohérent avec la réalité du couplage déjà existant.

### Option B — Fusion complète immédiate

Déplacer tous les fichiers SmartAttendance dans Attendance, renommer namespaces et endpoints, supprimer le module.

- ✅ Un seul module à terme.
- ❌ Casse le contrat API mobile (`/smart-attendance/*`) d'un coup ; diff énorme et risqué ; chevauche avec les agents travaillant déjà sur Attendance (#5267 corrections, #5268 rapports) ; ni nécessaire ni prudent pour la décision d'architecture.

### Option C — Fusion progressive en 5 phases (recommandée)

Consolider autour d'`attendance_logs` comme table de fait unique, en gardant le contrat API stable pendant la transition. Alignée sur la stratégie de migration progressive de l'ADR-0007.

- ✅ Zéro rupture de contrat ; chaque phase est livrable et testable indépendamment ; compatible avec le travail en cours des agents #5267/#5268 (chemins de code disjoints phase par phase).
- ⚠️ Nécessite une discipline de transition (période de doublement).

## Décision

**Fusionner les deux modules en un module unique `Attendance`, progressivement, en 5 phases.** La cible : un seul module pointage DDD sous `api/app/Modules/Attendance/`, une seule table de fait (`attendance_logs`), une seule géofence, une seule fermeture automatique, une seule surface API (`/api/v1/attendance/*`), un seul point de configuration des modes.

**Critères de décision (demandés par l'issue) :**

| Critère | Poids | Constat | Verdict |
|---|---|---|---|
| **Volume** (employés/pointages) | Fort | `attendance_logs` centralise déjà toutes les méthodes (mobile, qr, biometric, manual, geo_auto) ; aucune table ne justifie un module séparé | Fusion |
| **Kiosque** (ZKTeco + kiosque web) | Fort | Tout vit dans Attendance (`attendance_kiosks`, `zkteco_devices`, `KioskController`, middleware ZKTeco) ; SmartAttendance n'apporte rien ici | Fusion |
| **Géo** (géofence, GPS) | Moyen | La géofence est **déjà centralisée** dans `AttendanceGeofenceService` (consommé par les deux modules — binding `GeofenceValidatorInterface` dans `SmartAttendanceServiceProvider`) ; les données GPS sont déjà dans `attendance_logs` (`gps_lat/gps_lng`, `punch_meta.geofence`) ; restent deux chemins d'usage à unifier | Fusion (une seule implémentation, un seul chemin) |
| **Mobile** (employee/hr/manager) | Fort | Les apps consomment déjà les deux surfaces ; la fusion progressive garde le contrat `/smart-attendance/*` jusqu'à la bascule | Fusion avec aliasing |

**Verdict : fusion**, sous réserve du volume (≤ 5 M de lignes `attendance_logs` par tenant sur PostgreSQL — largement dans la capacité d'un schéma tenant unique, cf. index existants `(employee_id, date)`, `(date, status)`).

## Plan de migration (chiffré)

Périmètre à migrer : **4 tables** (aucune fusion de table nécessaire), **4 modèles**, **2 services**, **6 actions**, **3 contrôleurs**, **11 routes API**, **6 fichiers de test Feature**, **3 apps mobiles** (employee/hr/manager), **2 commandes** à fusionner, **1 contrat OpenAPI** à consolider.

### Phase 1 — Formalisation (effort ~0,5 j·a ; livrable : cet ADR + spec `attendance-target`)
- ADR approuvée + spec cible indexée (`docs/architecture/POINTAGE_100PCT.md`).
- Aucun code modifié.

### Phase 2 — Unifier les chemins d'usage de la géofence et du mode (effort ~1 j·a)
- `AttendanceGeofenceService` est déjà l'unique implémentation (binding `GeofenceValidatorInterface`) ; la phase unifie les **chemins d'usage** (évaluation au punch mobile vs zone de session) en un seul point d'appel documenté.
- `attendance_mode_settings` reste la table canonique des modes (déjà consommée par `AttendanceService`) ; la doc de migration des modes (kiosque/géo/QR/manuel/mixte) est alignée avec #5265.

### Phase 3 — Migrer la surface API sans rupture (effort ~1,5 j·a)
- Les 11 routes `/smart-attendance/*` sont **ré-exposées** sous `/attendance/*` (ex. `/attendance/geo-sessions`, `/attendance/geo-sessions/{id}/approve`, `/attendance/mode-settings`) avec **alias temporaires** `/smart-attendance/*` → redirection 308 (ou double enregistrement de routes, puis bascule mobile).
- Contrat OpenAPI : les chemins aliasés sont marqués `deprecated: true` côté `/smart-attendance/*`.

### Phase 4 — Fusion des modèles/services/commandes (effort ~1,5 j·a)
- Les modèles SmartAttendance sont déplacés dans `Attendance/Domain/Models/` (namespace `App\Modules\Attendance\Domain\Models`), avec classes de re-export dépréciées (`class_alias` / classes fantômes `@deprecated`) pour les imports existants.
- `AutoCloseGeoSessionsCommand` fusionné dans `AutoCloseAttendanceCommand` (une seule fermeture automatique, même cycle).
- Tests : les 6 fichiers `tests/Feature/SmartAttendance/*` migrés vers `tests/Feature/Attendance/Geo*` sans perte de scénarios.

### Phase 5 — Nettoyage (effort ~0,5 j·a)
- Suppression des alias, des routes `/smart-attendance/*` résiduelles (après vérification des apps mobiles), du dossier `SmartAttendance/`, de `SmartAttendanceServiceProvider`.
- Garde CI : `check-unrouted-controllers.sh` + OpenAPI coverage gardent l'absence de tout chemin `/smart-attendance/*` ; aucun nouvel import `App\Modules\SmartAttendance\*` (garde grep dans Architecture Quality).

**Effort total estimé : ~5 j·a**, zéro migration de données destructive (aucune table fusionnée ; `geo_attendance_sessions` et `employee_location_events` conservent leur rôle, simplement rattachées au module Attendance), zéro perte de données (les FK `attendance_log_id` existantes restent inchangées).

**Séquencement recommandé** : Phases 2-3 après la vague W3 (post-ADR), Phase 4-5 en W5/W6 — à cheval avec les livrables #5265 (modes unifiés), #5267 (corrections) et #5268 (rapports) qui restent sur des chemins de code disjoints.

## Conséquences

- **Produit** : une seule notion de pointage pour l'utilisateur (kiosque, géo, ZKTeco, mobile = un seul « pointage ») ; le mode unifié #5265 devient naturel.
- **API** : le contrat `/smart-attendance/*` est préservé pendant la transition (compatibilité mobile), puis unifié sur `/attendance/*`.
- **Maintenance** : une seule géofence, une seule fermeture automatique, un seul module à tester et documenter — la dette de duplication disparaît.
- **Paie** : aucun changement pour `PayrollCalculator`/`PayrollCycleService` — ils consomment déjà `attendance_logs`, table inchangée.
- **RGPD** : `employee_location_events` conserve sa minimisation (pas de tracking continu) ; le rattachement au module Attendance ne change pas le régime de données.

## Règles opérationnelles

- **1 module = 1 surface API** : toute nouvelle route de pointage est ajoutée sous `/api/v1/attendance/*`, jamais sous `/smart-attendance/*`.
- **1 table de fait** : tout pointage (quelle que soit la méthode) finit dans `attendance_logs` ; les tables annexes (sessions, événements) ne sont que des contextes d'acquisition.
- **1 géofence** : `AttendanceGeofenceService` est l'unique implémentation ; interdiction d'ajouter une seconde logique de calcul de distance.
- **1 fermeture automatique** : `AutoCloseAttendanceCommand` uniquement.
- **Garde CI** : aucun nouvel import `App\Modules\SmartAttendance\*` dès la Phase 3 ; purger les alias en Phase 5 avec preuve mobile (contrat `validate-mobile-workflow-contracts.ps1`).
- **Mode entreprise** : `attendance_mode_settings` reste la source de vérité (forced_mode / punch_photo_mode / géofence) ; toute nouvelle option de mode passe par cette table.
- **Approbation** : le workflow `Approvable` reste générique et partagé (absences, notes de frais, sessions GPS, corrections) — ne pas dupliquer.
