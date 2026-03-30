# PROMPT CC-03 — Module Pointage (Attendance)
# Agent : CLAUDE CODE
# Phase : Semaine 3
# Prérequis : CC-02 (Auth) terminé et validé

---

## MISSION

Développer le module de pointage — la fonctionnalité la plus utilisée quotidiennement.

## ENDPOINTS

```
POST  /api/v1/attendance/check-in
POST  /api/v1/attendance/check-out
GET   /api/v1/attendance/today         ← CRITIQUE pour Flutter
GET   /api/v1/attendance               ← liste avec filtres
GET   /api/v1/attendance/{employee_id}
PUT   /api/v1/attendance/{id}          ← correction manuelle gestionnaire
POST  /api/v1/attendance/qrcode
POST  /api/v1/attendance/biometric     ← webhook ZKTeco
```

Payloads exacts : voir `02_API_CONTRATS_COMPLET.md` — Section 4.

## RÈGLES MÉTIER CRITIQUES (voir aussi 09_REGLES_METIER_COMPLETES.md)

```php
// 1. TOUJOURS horodatage serveur — JAMAIS le timestamp client
$checkIn = now(); // ✅
$checkIn = $request->timestamp; // ❌ INTERDIT

// 2. check-out = UPDATE sur la ligne existante — JAMAIS INSERT
$log = AttendanceLog::whereDate('date', today())->first();
$log->update(['check_out' => now()]); // ✅
AttendanceLog::create(['check_out' => now()]); // ❌ INTERDIT

// 3. Validation GPS : calculer la distance avec la zone du site
$distance = $this->haversineDistance($lat, $lng, $site->gps_lat, $site->gps_lng);
if ($distance > $site->gps_radius_meters) {
    return response()->json(['message' => 'GPS_OUTSIDE_ZONE'], 422);
}

// 4. GET /attendance/today retourne null si pas encore pointé (pas une erreur)
$log = AttendanceLog::whereDate('date', today())
    ->where('employee_id', auth()->id())
    ->first();
return new AttendanceTodayResource($log); // null si pas de log → data: null

// 5. Statut automatique selon le planning
$schedule = $employee->schedule;
$lateAt = Carbon::parse($schedule->start_time)->addMinutes($schedule->late_tolerance_minutes);
$status = $checkIn->lte($lateAt) ? 'ontime' : 'late';
```

## TESTS OBLIGATOIRES

```
AttendanceCheckInTest        → check-in crée un log avec timestamp serveur
AttendanceAlreadyCheckedIn   → 2ème check-in retourne 409
AttendanceCheckOutTest       → check-out UPDATE la ligne existante (pas INSERT)
AttendanceMissingCheckIn     → check-out sans check-in retourne 422
AttendanceGpsZoneTest        → GPS hors zone retourne 422 GPS_OUTSIDE_ZONE
AttendanceTodayNullTest      → GET /today retourne data:null si pas pointé
BiometricWebhookTest         → webhook ZKTeco avec device_token valide/invalide
```

## FICHIERS À CRÉER

```
app/Http/Controllers/Tenant/AttendanceController.php
app/Http/Controllers/Tenant/DeviceController.php
app/Http/Requests/Tenant/CheckInRequest.php
app/Http/Requests/Tenant/CheckOutRequest.php
app/Http/Resources/AttendanceLogResource.php
app/Models/Tenant/AttendanceLog.php
app/Models/Tenant/Site.php
app/Models/Tenant/Schedule.php
app/Services/AttendanceService.php         ← logique métier ici, pas dans le controller
app/Services/GpsValidationService.php
```

## COMMIT ATTENDU
```
feat: add attendance module with GPS validation, ZKTeco webhook, server-side timestamps
test: add attendance conflict and GPS zone tests
```

---
---

# PROMPT CC-04 — Modules Config Entreprise (Departments, Positions, Schedules, Sites)
# Agent : CLAUDE CODE
# Phase : Semaine 3-4 (en parallèle de CC-03)
# Prérequis : CC-02 terminé

---

## MISSION

Implémenter les 4 modules de configuration organisationnelle.
Ces modules sont nécessaires pour que les employés aient un département, un poste et un planning.

## ENDPOINTS

```
CRUD /api/v1/departments     (GET, POST, PUT/{id}, DELETE/{id})
CRUD /api/v1/positions       (GET, POST, PUT/{id}, DELETE/{id})
CRUD /api/v1/schedules       (GET, POST, PUT/{id}, DELETE/{id})
CRUD /api/v1/sites           (GET, POST, PUT/{id}, DELETE/{id})
```

Payloads exacts : voir `02_API_CONTRATS_COMPLET.md` — Section 3.

## RÈGLES MÉTIER

- `DELETE /departments/{id}` → vérifier qu'aucun employé n'est dans ce département avant de supprimer (retourner 409 DEPARTMENT_HAS_EMPLOYEES si non vide)
- `DELETE /schedules/{id}` → vérifier qu'aucun employé n'utilise ce planning (retourner 409 SCHEDULE_IN_USE)
- Les 4 modules utilisent le cache Redis avec invalidation automatique (voir stratégie cache dans 01_PROMPT_MASTER_CLAUDE_CODE.md)
- RBAC : seuls Manager Principal et Manager RH peuvent faire des CRUD (voir 10_RBAC_COMPLET.md)

## CACHE REDIS (obligatoire)

```php
// Dans DepartmentController::index()
$departments = Cache::tags(["tenant:{$tenantUuid}"])
    ->remember("departments", 86400, fn() => Department::all());

// Dans DepartmentController::store() / update() / destroy()
Cache::tags(["tenant:{$tenantUuid}"])->forget("departments");
```

## COMMIT ATTENDU
```
feat: add org config modules (departments, positions, schedules, sites) with Redis cache
```

---
---

# PROMPT CC-05 — Modules Absences + Avances
# Agent : CLAUDE CODE
# Phase : Semaine 5-6
# Prérequis : CC-02, CC-04 terminés

---

## MISSION

Implémenter la gestion des absences et des avances sur salaire.

## ENDPOINTS ABSENCES

```
GET    /api/v1/absences
POST   /api/v1/absences
GET    /api/v1/absences/{id}
PUT    /api/v1/absences/{id}/approve
PUT    /api/v1/absences/{id}/reject
PUT    /api/v1/absences/{id}/cancel
CRUD   /api/v1/absence-types
```

## ENDPOINTS AVANCES

```
GET    /api/v1/advances
POST   /api/v1/advances
GET    /api/v1/advances/{id}
PUT    /api/v1/advances/{id}/approve
PUT    /api/v1/advances/{id}/reject
PUT    /api/v1/advances/{id}/repayment
```

Payloads exacts : voir `02_API_CONTRATS_COMPLET.md` — Sections 5 et 6.

## RÈGLES MÉTIER CRITIQUES

```php
// ABSENCES — Validation du solde
if ($request->absence_type->is_paid) {
    $daysNeeded = $this->calculateWorkingDays($request->start_date, $request->end_date);
    if ($employee->leave_balance < $daysNeeded) {
        return response()->json(['message' => 'INSUFFICIENT_LEAVE_BALANCE'], 422);
    }
}

// ABSENCES — Vérification chevauchement
$overlap = Absence::whereBetween('start_date', [$request->start_date, $request->end_date])
    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
    ->where('status', '!=', 'cancelled')
    ->exists();
if ($overlap) return response()->json(['message' => 'OVERLAP_WITH_EXISTING'], 422);

// ABSENCES — Mise à jour du solde à l'approbation
$employee->decrement('leave_balance', $absence->days_count);

// ABSENCES — Notification push à l'employé lors du changement de statut
$this->notificationService->sendToEmployee($employee, [
    'type' => 'absence_approved',
    'title' => 'Absence approuvée ✅',
    'body' => "Votre congé du {$absence->start_date} a été approuvé.",
    'data' => ['absence_id' => $absence->id],
]);

// AVANCES — Calcul mensualité
$monthlyDeduction = round($advance->amount / $request->repayment_months, 2);

// AVANCES — Un seul remboursement à la fois
$activeAdvance = SalaryAdvance::where('status', 'approved')
    ->where('amount_remaining', '>', 0)
    ->exists();
if ($activeAdvance) {
    return response()->json(['message' => 'ADVANCE_ALREADY_ACTIVE'], 422);
}
```

## TESTS OBLIGATOIRES

```
AbsenceInsufficientBalanceTest → solde insuffisant retourne 422
AbsenceOverlapTest             → chevauchement retourne 422
AbsenceApproveDeductsBalance   → approbation déduit du leave_balance
AbsenceCancelRecreditsBalance  → annulation recrédite le solde
LeaveBalanceTest               → acquisition mensuelle 2.5j/mois
```

## COMMIT ATTENDU
```
feat: add absence module with balance management and push notifications
feat: add salary advance module with repayment tracking
test: add absence balance and overlap tests
```

---
---

# PROMPT CC-06 — Module Paie (PayrollService)
# Agent : CLAUDE CODE
# Phase : Semaine 7-8
# Prérequis : CC-02, CC-04, CC-05 terminés

---

## MISSION

Développer le module de paie — le plus critique en calculs métier.
La génération de PDF est asynchrone (queue job).

## ENDPOINTS

```
POST  /api/v1/payroll/calculate         ← simulation, pas de validation
POST  /api/v1/payroll/validate          ← génère les bulletins (async)
GET   /api/v1/payroll                   ← liste avec filtres
GET   /api/v1/payroll/{id}              ← détail complet
GET   /api/v1/payroll/{id}/pdf          ← télécharger le PDF
GET   /api/v1/payroll/status/{job_id}   ← statut du job async
GET   /api/v1/payroll/export-bank       ← export CSV virement
```

Payloads exacts : voir `02_API_CONTRATS_COMPLET.md` — Section 8.

## FORMULE DE CALCUL (voir 09_REGLES_METIER_COMPLETES.md)

```php
// app/Services/PayrollService.php

public function calculateForEmployee(Employee $employee, int $month, int $year): array
{
    $settings = $this->getCompanySettings(); // Depuis cache Redis
    $hrModel = $this->getHRModel($settings->hr_model); // Taux cotisations par pays

    // 1. Brut de base
    $grossBase = $employee->gross_salary;

    // 2. Heures supplémentaires du mois
    $overtimePay = $this->calculateOvertimePay($employee, $month, $year);

    // 3. Brut total
    $grossTotal = $grossBase + $overtimePay;

    // 4. Cotisations sociales (selon pays)
    $socialSecurity = $grossTotal * $hrModel->social_security_rate;

    // 5. Base imposable
    $taxableBase = $grossTotal - $socialSecurity;

    // 6. Impôt sur le revenu (tranches progressives selon pays)
    $incomeTax = $this->calculateIncomeTax($taxableBase, $hrModel);

    // 7. Avances en cours
    $advanceDeduction = $this->getActiveAdvanceDeduction($employee);

    // 8. Absences non justifiées
    $absenceDeduction = $this->calculateAbsenceDeduction($employee, $month, $year);

    // 9. Net à payer
    $netSalary = $grossTotal - $socialSecurity - $incomeTax - $advanceDeduction - $absenceDeduction;

    // 10. Mettre à jour l'avance (amount_remaining - advanceDeduction)
    $this->updateAdvanceRepayment($employee, $advanceDeduction);

    return [
        'gross_salary' => round($grossBase, 2),
        'overtime_pay' => round($overtimePay, 2),
        'deductions' => [
            'social_security' => round($socialSecurity, 2),
            'income_tax' => round($incomeTax, 2),
            'advance_deduction' => round($advanceDeduction, 2),
            'absence_deduction' => round($absenceDeduction, 2),
        ],
        'net_salary' => round($netSalary, 2),
    ];
}
```

## JOB ASYNC — Génération PDF

```php
// app/Jobs/GeneratePayslipPdfJob.php
// Utilise DomPDF pour générer le bulletin
// Stocke dans storage/app/payslips/{year}/{month}/{employee_id}.pdf
// Met à jour PayrollSlip->pdf_url et ->status = 'validated'
// Envoie notification push "payslip_available" à l'employé
```

## TESTS OBLIGATOIRES

```
PayrollCalculationTest       → net = brut - cotisations - IR - retenues (valeurs exactes Algérie)
PayrollAdvanceDeductionTest  → avance déduite + amount_remaining mis à jour
PayrollAdvanceRepaidTest     → avance soldée → status = 'repaid'
PayrollAsyncJobTest          → validate retourne job_id, puis status = 'completed'
```

## COMMIT ATTENDU
```
feat: add payroll module with async PDF generation via DomPDF
test: add PayrollCalculationTest with real Algerian tax rates
```
