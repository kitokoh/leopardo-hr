# MVP-03 — Module Pointage
# Agent : Claude Code
# Durée : 4-6 heures
# Prérequis : MVP-02 vert (Auth + Employés fonctionnels)

---

## CE QUE TU FAIS

Implémenter le système de pointage : check-in/check-out avec horodatage serveur, historique des pointages, et vue "aujourd'hui". GPS optionnel (désactivé par défaut).

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` (5 min)
2. Vérifie : `php artisan test` → 0 failure
3. Lis `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` — section Pointage
4. Lis le SQL de `attendance_logs` dans `07_SCHEMA_SQL_COMPLET.sql` L347-377

---

## ENDPOINTS À IMPLÉMENTER

| Méthode | Route | Description | RBAC |
|---------|-------|-------------|------|
| POST | `/api/v1/attendance/check-in` | Pointer l'arrivée | Employee: self |
| POST | `/api/v1/attendance/check-out` | Pointer le départ | Employee: self |
| GET | `/api/v1/attendance/today` | État du jour (check-in? check-out? heures?) | Employee: self, Manager: tous |
| GET | `/api/v1/attendance` | Historique paginé | Employee: self, Manager: filtrable |

---

## LOGIQUE MÉTIER (dans AttendanceService)

### Check-in
```
1. Vérifier qu'il n'y a PAS de check-in sans check-out pour aujourd'hui
   → sinon erreur "ALREADY_CHECKED_IN"
2. Créer attendance_log avec :
   - employee_id
   - date = today (timezone entreprise)
   - check_in = now() ← SERVEUR, JAMAIS le client
   - method = 'mobile'
   - gps_lat/gps_lng si fournis
   - status = 'incomplete'
   - schedule_id = schedule actuel de l'employé (snapshot)
3. Calculer le statut (ontime / late) en comparant avec schedule.start_time
```

### Check-out
```
1. Trouver le check-in ouvert d'aujourd'hui
   → sinon erreur "MISSING_CHECK_IN"
2. UPDATE (pas INSERT) :
   - check_out = now()
   - hours_worked = diff(check_in, check_out) en heures décimales
   - overtime_hours = max(0, hours_worked - schedule.overtime_threshold_daily)
   - status = calculé (ontime si check_in dans la tolérance)
```

### Today
```
Retourner pour chaque employé visible :
{
  employee_id, name,
  checked_in: bool,
  check_in_time: "08:02",
  check_out_time: null | "17:15",
  hours_worked: 0 | 8.5,
  status: "incomplete" | "ontime" | "late"
}
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/AttendanceController.php
app/Http/Requests/Attendance/CheckInRequest.php
app/Http/Requests/Attendance/CheckOutRequest.php
app/Services/AttendanceService.php
app/Models/AttendanceLog.php (avec trait BelongsToCompany)
app/Models/Schedule.php (avec trait BelongsToCompany)
app/Policies/AttendancePolicy.php
app/Http/Resources/AttendanceResource.php
```

---

## TESTS À ÉCRIRE (avant le code)

```php
// tests/Feature/Attendance/CheckInTest.php
it('employee can check in');
it('check-in uses server time not client time');
it('cannot check in twice without check out');
it('check-in records GPS if provided');
it('late status when check-in after tolerance');
it('ontime status when check-in within tolerance');

// tests/Feature/Attendance/CheckOutTest.php
it('employee can check out');
it('cannot check out without check in');
it('check-out calculates hours_worked');
it('check-out calculates overtime_hours');

// tests/Feature/Attendance/AttendanceListTest.php
it('employee sees only own attendance');
it('manager sees all employees attendance');
it('today endpoint returns current day status');

// tests/Feature/Attendance/TenantIsolationTest.php
it('company A attendance invisible to company B');
```

---

## RÈGLES CRITIQUES

- `check_in` et `check_out` = `TIMESTAMPTZ` en UTC en base
- Affichage = converti en timezone de l'entreprise (`company.timezone`)
- `hours_worked` = DECIMAL(5,2) — calculé UNIQUEMENT au check-out
- `session_number` = toujours 1 dans le MVP (pas de split-shift)
- UNIQUE(employee_id, date, session_number) → empêche les doublons
- GPS : stocker lat/lng mais ne PAS valider la zone dans le MVP

---

## PORTE VERTE → MVP-04

```
[ ] php artisan test → 0 failure (incluant MVP-01 + MVP-02)
[ ] POST /attendance/check-in → crée un log
[ ] POST /attendance/check-out → met à jour avec hours_worked
[ ] GET /attendance/today → statut du jour
[ ] GET /attendance → historique paginé
[ ] Double check-in bloqué
[ ] Check-out sans check-in bloqué
[ ] Isolation tenant → pointages company A ≠ company B
```

---

## COMMIT

```
feat(attendance): check-in/check-out with server timestamp + overtime calc
test(attendance): check-in, check-out, duplicate blocking, tenant isolation
```
