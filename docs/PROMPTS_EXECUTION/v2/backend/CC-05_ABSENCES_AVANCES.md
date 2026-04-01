# CC-05 — Module Absences + Avances sur salaire
# Agent : Claude Code
# Durée : 5-6 heures
# Prérequis : CC-04 vert

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test --filter="Attendance|Config|Employee|Auth|Infrastructure"  # 0 failure
```

---

## ENDPOINTS ABSENCES

```
GET    /api/v1/absences                        → liste (employé = siennes, manager = équipe)
POST   /api/v1/absences                        → soumettre une demande
GET    /api/v1/absences/{id}                   → détail
PUT    /api/v1/absences/{id}/approve           → approuver (manager)
PUT    /api/v1/absences/{id}/reject            → rejeter avec motif (manager)
PUT    /api/v1/absences/{id}/cancel            → annuler (employé, si status=pending)
CRUD   /api/v1/absence-types                   → types de congés (manager seulement)
```

## ENDPOINTS AVANCES

```
GET    /api/v1/advances                        → liste
POST   /api/v1/advances                        → demander une avance
GET    /api/v1/advances/{id}                   → détail avec échéancier
PUT    /api/v1/advances/{id}/approve           → approuver (manager)
PUT    /api/v1/advances/{id}/reject            → rejeter (manager)
POST   /api/v1/advances/{id}/repayment         → enregistrer un remboursement manuel
```

---

## TESTS À ÉCRIRE EN PREMIER

```php
// tests/Feature/Absences/AbsenceTest.php

it('employee can submit absence request', function () {
    [$company, $employee, $token] = createEmployeeWithToken(['leave_balance' => 12]);

    $this->withToken($token)
         ->postJson('/api/v1/absences', [
             'absence_type_id' => AbsenceType::factory()->paid()->create()->id,
             'start_date'      => '2026-05-01',
             'end_date'        => '2026-05-05',
             'reason'          => 'Congé annuel',
         ])
         ->assertStatus(201)
         ->assertJson(['data' => ['status' => 'pending']]);
});

it('absence rejected when leave balance insufficient', function () {
    [$company, $employee, $token] = createEmployeeWithToken(['leave_balance' => 1]);

    $this->withToken($token)
         ->postJson('/api/v1/absences', [
             'absence_type_id' => AbsenceType::factory()->paid()->create()->id,
             'start_date'      => '2026-05-01',
             'end_date'        => '2026-05-10', // 8 jours ouvrables
         ])
         ->assertStatus(422)
         ->assertJson(['error' => 'INSUFFICIENT_LEAVE_BALANCE']);
});

it('overlapping absence request is rejected', function () {
    [$company, $employee, $token] = createEmployeeWithToken(['leave_balance' => 20]);
    AbsenceType::factory()->paid()->create();

    // Première demande
    $this->withToken($token)->postJson('/api/v1/absences', [
        'absence_type_id' => 1,
        'start_date'      => '2026-05-01',
        'end_date'        => '2026-05-05',
    ]);

    // Deuxième demande qui chevauche
    $this->withToken($token)
         ->postJson('/api/v1/absences', [
             'absence_type_id' => 1,
             'start_date'      => '2026-05-03', // chevauche
             'end_date'        => '2026-05-08',
         ])
         ->assertStatus(422)
         ->assertJson(['error' => 'OVERLAP_WITH_EXISTING']);
});

it('approval deducts leave balance and sends push notification', function () {
    [$company, $manager, $managerToken] = createManagerWithToken('rh');
    $employee = Employee::factory()->create(['leave_balance' => 12]);
    $absence = Absence::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'pending',
        'days_count'  => 5,
    ]);

    Notification::fake(); // Mock des notifications push

    $this->withToken($managerToken)
         ->putJson("/api/v1/absences/{$absence->id}/approve")
         ->assertStatus(200);

    $employee->refresh();
    expect($employee->leave_balance)->toBe(7); // 12 - 5

    Notification::assertSentTo($employee, AbsenceApprovedNotification::class);
});

it('cancellation refunds leave balance', function () {
    [$company, $employee, $token] = createEmployeeWithToken(['leave_balance' => 7]);
    $absence = Absence::factory()->create([
        'employee_id' => $employee->id,
        'status'      => 'approved',
        'days_count'  => 5,
    ]);

    $this->withToken($token)
         ->putJson("/api/v1/absences/{$absence->id}/cancel")
         ->assertStatus(200);

    $employee->refresh();
    expect($employee->leave_balance)->toBe(12); // 7 + 5 recrédités
});

it('monthly leave accrual adds 2.5 days', function () {
    $employee = Employee::factory()->create(['leave_balance' => 10.0]);

    $this->artisan('leave:accrue-monthly');

    $employee->refresh();
    expect($employee->leave_balance)->toBe(12.5);
});

// tests/Feature/Advances/AdvanceTest.php

it('employee can request salary advance', function () {
    [$company, $employee, $token] = createEmployeeWithToken(['salary_base' => 60000]);

    $this->withToken($token)
         ->postJson('/api/v1/advances', [
             'amount'            => 20000,
             'reason'            => 'Urgence médicale',
             'repayment_months'  => 4,
         ])
         ->assertStatus(201)
         ->assertJson(['data' => ['status' => 'pending', 'monthly_deduction' => 5000]]);
});

it('cannot request advance when one is already active', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    SalaryAdvance::factory()->create([
        'employee_id'      => $employee->id,
        'status'           => 'approved',
        'amount_remaining' => 5000,
    ]);

    $this->withToken($token)
         ->postJson('/api/v1/advances', [
             'amount'           => 10000,
             'repayment_months' => 3,
         ])
         ->assertStatus(422)
         ->assertJson(['error' => 'ADVANCE_ALREADY_ACTIVE']);
});

it('approval stores monthly deduction', function () {
    [$company, $manager, $token] = createManagerWithToken('principal');
    $employee = Employee::factory()->create();
    $advance = SalaryAdvance::factory()->create([
        'employee_id'      => $employee->id,
        'status'           => 'pending',
        'amount'           => 30000,
        'repayment_months' => 3,
    ]);

    $this->withToken($token)
         ->putJson("/api/v1/advances/{$advance->id}/approve")
         ->assertStatus(200);

    $advance->refresh();
    expect($advance->status)->toBe('approved');
    expect($advance->monthly_deduction)->toBe(10000.0);
    expect($advance->amount_remaining)->toBe(30000.0);
});
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Tenant/AbsenceController.php
app/Http/Controllers/Tenant/AbsenceTypeController.php
app/Http/Controllers/Tenant/AdvanceController.php
app/Http/Requests/Tenant/StoreAbsenceRequest.php
app/Http/Requests/Tenant/RejectAbsenceRequest.php
app/Http/Requests/Tenant/StoreAdvanceRequest.php
app/Http/Resources/AbsenceResource.php
app/Http/Resources/AdvanceResource.php
app/Models/Tenant/Absence.php
app/Models/Tenant/AbsenceType.php
app/Models/Tenant/SalaryAdvance.php
app/Models/Tenant/LeaveBalanceLog.php
app/Services/AbsenceService.php
app/Observers/AbsenceObserver.php
app/Notifications/AbsenceApprovedNotification.php
app/Notifications/AbsenceRejectedNotification.php
app/Console/Commands/LeaveAccrueMonthly.php
```

---

## ABSENCESERVICE — MÉTHODES OBLIGATOIRES

```php
public function calculateWorkingDays(string $startDate, string $endDate, string $country): int
{
    $start    = Carbon::parse($startDate);
    $end      = Carbon::parse($endDate);
    $holidays = $this->getPublicHolidays($country, $start->year);
    $days     = 0;

    while ($start->lte($end)) {
        if (!$start->isWeekend() && !in_array($start->toDateString(), $holidays)) {
            $days++;
        }
        $start->addDay();
    }

    return $days;
}

public function hasOverlap(Employee $employee, string $startDate, string $endDate): bool
{
    return Absence::where('employee_id', $employee->id)
        ->where('status', '!=', 'cancelled')
        ->where(function ($query) use ($startDate, $endDate) {
            $query->whereBetween('start_date', [$startDate, $endDate])
                  ->orWhereBetween('end_date', [$startDate, $endDate])
                  ->orWhere(function ($q) use ($startDate, $endDate) {
                      $q->where('start_date', '<=', $startDate)
                        ->where('end_date', '>=', $endDate);
                  });
        })
        ->exists();
}

// Approbation TOUJOURS dans une transaction
public function approve(Absence $absence, Employee $approver): void
{
    DB::transaction(function () use ($absence, $approver) {
        $absence->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
        $absence->employee->decrement('leave_balance', $absence->days_count);

        LeaveBalanceLog::create([
            'employee_id' => $absence->employee_id,
            'type'        => 'deduction',
            'days'        => $absence->days_count,
            'reason'      => "Absence approuvée #{$absence->id}",
            'balance_after'=> $absence->employee->fresh()->leave_balance,
        ]);

        $absence->employee->notify(new AbsenceApprovedNotification($absence));
    });
}
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-06

```
[ ] php artisan test --filter="Absence|Advance" → 0 failure
[ ] Test solde insuffisant → 422
[ ] Test chevauchement → 422
[ ] Test approbation → solde decrementé + notification envoyée
[ ] Test annulation → solde recrédité
[ ] Test avance active → 422 si 2ème demande
[ ] Transaction approbation : si notification échoue, solde non décrémenté
[ ] php artisan test (global) → 0 failure
```

---

## COMMIT

```
feat: add absence module with balance management, overlap detection, push notifications
feat: add salary advance module with repayment tracking
feat: add leave:accrue-monthly cron command
test: add 9 absence and advance tests including edge cases
```
