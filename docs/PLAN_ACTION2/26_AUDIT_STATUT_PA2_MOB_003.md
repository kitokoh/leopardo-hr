# Audit de statut — PA2-MOB-003 (issue #973, "Pointage employee multi-evenements")

**Method:** direct source inspection of the API attendance module and the `leopardo_employee` mobile app's attendance screen, cross-referenced against the ticket's acceptance criteria.

**Acceptance criteria (issue #973):** *"Arrivee pause reprise mission depart heure supp details jour"* — arrival, break, resume, mission, departure, overtime, day details.

---

## Finding: every listed event type and the day-detail view already exist and are tested

### Backend: multi-session, multi-`work_type` attendance is a first-class model

`App\Modules\Attendance\Infrastructure\Services\AttendanceService` (via `POST /api/v1/attendance/check-in` and `check-out`) already supports a `work_type` on every punch, with sessions numbered per day (`session_number`). The full set of mapped types is not limited to a plain in/out pair:

- `normal` — arrivee (arrival / normal check-in)
- `break` — pause
- `resume` — reprise (mapped from a `check-in` after a `break` check-out)
- `mission` — mission
- `travel` — deplacement/mission variant
- `overtime` — heure supplementaire, with `overtime_hours` computed and returned on check-out
- `training`, `other` — additional documented types

`GET /api/v1/attendance/today` returns the full list of the day's sessions plus a `summary` (session count, `is_working`, `current_work_type`, `break_minutes`) — i.e. the "details jour" (day details) requirement. `GET /api/v1/me/daily-summary` and `/api/v1/me/monthly-summary` aggregate hours/overtime/gross across sessions.

`api/tests/Feature/Attendance/MultiPunchTest.php` (`test_employee_can_create_multiple_sessions_in_one_day`, `test_today_returns_sessions_and_daily_summary`) exercises exactly the arrival -> break -> resume(overtime) -> departure sequence end to end, asserting session numbering, per-session `work_type`, computed `overtime_hours`, and the daily/monthly summary aggregates.

### Mobile (`leopardo_employee`): a dedicated, tested UI for every event type

`front/mobile_apps/leopardo_employee/lib/features/attendance/screens/attendance_screen.dart` implements a punch-type picker offering exactly `break`, `resume`, `overtime`, `mission`, `travel` (in addition to the default `normal` arrival/departure), and renders the day's sessions as a numbered list (`"Session ${session.sessionNumber} - ${_workTypeLabel(session.workType)}"`) with per-type colour coding (`_workTypeColor`) — i.e. the "details jour" view on the client side too.

### Kiosk parity (PA2-ATT-010, already shipped)

`App\Modules\Attendance\Infrastructure\Services\KioskAttendanceService` explicitly documents (inline comment, "PA2-ATT-010") that kiosk punches and offline-synced kiosk events feed the *same* multi-event `work_type` model as mobile, rather than being locked to plain check-in/out — so this feature is consistent across every punch surface (mobile, kiosk online, kiosk offline-sync), not mobile-only.

## Decision

**PA2-MOB-003 / issue #973 is functionally complete.** All six explicitly named events (arrivee/pause/reprise/mission/depart/heure supp) and the day-detail view are implemented server-side, exposed through the mobile UI, and covered by feature tests. No application code changes were needed for this ticket — it is a closing/bookkeeping audit, consistent with the pattern used for `PA2-MOB-010` (`24_AUDIT_STATUT_PA2_MOB_010.md`) and `PA2-ONB-001/002/003` (`25_AUDIT_STATUT_PA2_ONB_001_A_003.md`).

**Recommendation:** close issue #973 referencing this document.
