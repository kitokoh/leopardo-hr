# Pointage 100 % — cadrage, spec cible et plan de migration

> **Document d'indexation du programme Pointage 100 %** (issues #5264→#5269).
> Source d'architecture : **ADR 0016** (`docs/architecture/adr/0016-attendance-smartattendance-fusion.md`).
> Spec cible : **`.specify/features/attendance-target/spec.md`**.

**État** : 2026-08-25 — ADR-0016 validée (PR #5318), **phases 2-5 MERGÉES** (#5362/#5364/#5381/#5419) — module SmartAttendance supprimé, contrat unique `/attendance/*`.

## Cadrage

Deux modules de pointage coexistaient (Attendance + SmartAttendance) avec une frontière poreuse :
géofence déjà partagée mais à deux chemins d'usage, fermetures automatiques dupliquées, mode de pointage hébergé dans SmartAttendance
mais consommé par le check-in d'Attendance. L'audit comparatif (ADR-0016) conclut que la table de
fait **`attendance_logs` est déjà le point d'entrée unique** de la plateforme (Paie, Planning, HR,
EdgeSync, rapports) et que SmartAttendance y écrit déjà (méthode `geo_auto` après approbation).

**Décision : fusion progressive** en 5 phases (ADR-0016) vers un module unique `Attendance`,
sans rupture de contrat API ni migration de données destructive.

## Liens

| Artéfact | Chemin |
|---|---|
| **ADR 0016** (décision + audit comparatif + plan chiffré) | `docs/architecture/adr/0016-attendance-smartattendance-fusion.md` |
| **Spec cible** (pointage 100 % — US, FR, critères) | `.specify/features/attendance-target/spec.md` |
| Registre des ADR | `docs/architecture/adr/README.md` |
| Tracker du programme 100 % | `docs/plan/PLAN_100PCT.md` |

## Issues du programme Pointage (backlog source)

| Issue | Sujet | Statut 2026-08-23 |
|---|---|---|
| #5264 | ADR fusion + spec cible (ce document) | ✅ ADR-0016 mergée (PR #5318) — phases 2-5 créées |
| #5353 | ADR Phase 2 — unifier les chemins d'usage géofence + mode | 🔵 PR (agent plateforme) |
| #5265 | Modes unifiés (kiosque, géo, ZKTeco, mobile) + règles de calcul | ⚪ ouverte |
| #5266 | Heures supplémentaires DZ — règles légales + intégration paie | ⚪ ouverte |
| #5267 | Corrections/validations — workflow d'approbation + audit | 🔵 PR #5314 (agent en cours) |
| #5268 | Rapports de pointage par période + exports CSV/PDF | 🔵 PR #5304 (agent en cours) |
| #5269 | Tests, i18n ×4, docs | ⚪ ouverte |

## Plan de migration (résumé chiffré — détail dans ADR-0016 §Plan)

- **Périmètre** : 4 tables, 4 modèles, 2 services, 6 actions, 3 contrôleurs, 11 routes API, 6 fichiers de test, 3 apps mobiles, 2 commandes, 1 contrat OpenAPI.
- **Effort** : ~5 j·a en 5 phases (formalisation → géofence/mode → API aliasée → fusion modèles/commandes → nettoyage).
- **Zéro perte de données** : aucune table fusionnée ; FK `attendance_log_id` inchangées.
- **Séquencement** : Phases 2-3 en W3 (post-approbation ADR), Phases 4-5 en W5/W6.

## Garde-fous

- Toute nouvelle route de pointage → `/api/v1/attendance/*` (jamais `/smart-attendance/*`).
- Géofence : implémentation unique `AttendanceGeofenceService` **+ chemin d'usage unique `GeofenceZoneService`** (ADR-0016 Phase 2, #5353 — garde CI `check-geofence-single-usage.sh`).
- Fermeture automatique : `AutoCloseAttendanceCommand` unique.
- Mode entreprise : `attendance_mode_settings` = source de vérité.
- Approbations : trait `Approvable` partagé (absences, frais, sessions, corrections).
- Garde CI : aucun import `App\Modules\SmartAttendance\*` après Phase 3 ; purge des alias prouvée par contrat mobile.

## Règles de calcul unifiées (#5265)

Depuis #5265, **tous les modes de pointage** (mobile, kiosque, ZKTeco, géo, import externe) passent par
`AttendanceHoursCalculator` (`api/app/Modules/Attendance/Infrastructure/Services/`) — une seule source de
vérité pour le retard (tolérance), les heures travaillées (pauses du planning déduites) et les heures
supplémentaires (seuil quotidien, cas `overtime`, types non travaillés `break`/`resume` #2686). Le mode
géo (`ApproveGeoSession`) a convergé : une session GPS approuvée déduit désormais les pauses comme le
mobile. Jeu doré de contrôle manuel : check-in 08:05 / check-out 17:35 (planning 08:00–17:00, pause 60,
tolérance 0, seuil 8 h) → retard 5 min, **8,5 h travaillées, 0,5 h HS** (`AttendanceHoursCalculatorTest`).

## Fermeture de journée (#5265)

Table tenant `attendance_day_closures` : verrou quotidien par `(company_id, employee_id, date)` — un jour
clos refuse **tout nouveau pointage** (check-in/check-out/import externe/approbation de session géo) en
**409 `ATTENDANCE_DAY_CLOSED`**. Cycle : `POST /api/v1/attendance/day-closures` (verrouillage, idempotent)
→ `POST /api/v1/attendance/day-closures/{id}/validate` (validation manager/RH, tracée `validated_by/at`) →
`DELETE /api/v1/attendance/day-closures/{id}` (levée du verrou). RBAC `api.manager:rh,principal`.

Complémentarité avec #5267 : le verrouillage de **période mensuelle** (`attendance_period_closures`, PR
#5314) cible les **corrections** (422 `ATTENDANCE_PERIOD_CLOSED`) ; la fermeture de **journée** cible les
**pointages** (409). Les sessions restées ouvertes à la clôture d'un jour sont traitées par
`attendance:auto-close` (fermeture système autorisée sur un jour clos).
