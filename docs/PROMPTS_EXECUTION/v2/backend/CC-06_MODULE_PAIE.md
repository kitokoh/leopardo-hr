# CC-06 — Module Paie (PayrollService + PDF async)
# Agent : Claude Code
# Durée : 8-10 heures
# Prérequis : CC-05 vert (Absences + Avances OK)

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test --filter="Absence|Advance|Attendance|Config|Employee|Auth"  # 0 failure
# Vérifier que les données nécessaires existent
php artisan tinker
>>> HRModel::where('country', 'DZ')->first()  # Doit retourner les taux algériens
>>> Employee::first()->salary_base           # Champ salary_base (PAS gross_salary — n'existe pas)
```

---

## ENDPOINTS À IMPLÉMENTER

```
POST  /api/v1/payroll/calculate          → simulation (ne valide pas, ne persiste pas)
POST  /api/v1/payroll/validate           → génère les bulletins (async — retourne job_id)
GET   /api/v1/payroll                    → liste des bulletins
GET   /api/v1/payroll/{id}              → détail complet
GET   /api/v1/payroll/{id}/pdf          → télécharger le PDF généré
GET   /api/v1/payroll/status/{job_id}   → statut du job async
GET   /api/v1/payroll/export-bank       → export CSV virement bancaire
```

Payloads exacts : `02_API_CONTRATS_COMPLET.md` — Section 8.

---

## TESTS À ÉCRIRE EN PREMIER

### Tests unitaires PayrollService (sans HTTP)

```php
// tests/Unit/PayrollServiceTest.php

// Valeurs exactes Algérie : taux CNAS salarié = 9%, CNAS patronal = 26%, IRG tranches progressives
it('calculates correct net salary for Algeria — simple case', function () {
    $service = app(\App\Services\PayrollService::class);
    $hrModel = HRModel::where('country', 'DZ')->first();

    $result = $service->calculateForEmployee(
        employee: Employee::factory()->make(['salary_base' => 60000, 'country' => 'DZ']),
        month: 4,
        year: 2026,
        hrModel: $hrModel,
        overtimeHours: 0,
        absenceDeductionDays: 0,
        latePenaltyMinutes: 0,
        activeAdvanceAmount: 0
    );

    // Brut = 60000
    // CNAS salarié (9%) = 5400
    // Base imposable = 60000 - 5400 = 54600
    // IRG (tranches DZ) : calculé selon barème officiel
    // Net = 60000 - 5400 - IRG

    expect($result['salary_base'])->toBe(60000.0);
    expect($result['deductions']['social_security'])->toBe(5400.0);
    expect($result['net_salary'])->toBeGreaterThan(40000.0); // vérification de cohérence
    expect($result['net_salary'])->toBeLessThan(60000.0);
});

it('overtime pay is included in gross total', function () {
    $service = app(\App\Services\PayrollService::class);
    $hrModel = HRModel::where('country', 'DZ')->first();

    $result = $service->calculateForEmployee(
        employee: Employee::factory()->make(['salary_base' => 60000]),
        month: 4,
        year: 2026,
        hrModel: $hrModel,
        overtimeHours: 10, // 10 heures sup à 150%
        absenceDeductionDays: 0,
        latePenaltyMinutes: 0,
        activeAdvanceAmount: 0
    );

    expect($result['overtime_pay'])->toBeGreaterThan(0.0);
    expect($result['gross_total'])->toBeGreaterThan(60000.0);
});

it('advance is deducted and amount_remaining is updated', function () {
    $service = app(\App\Services\PayrollService::class);
    $hrModel = HRModel::where('country', 'DZ')->first();
    $employee = Employee::factory()->create(['salary_base' => 60000]);
    SalaryAdvance::factory()->create([
        'employee_id'       => $employee->id,
        'status'            => 'approved',
        'amount'            => 30000,
        'amount_remaining'  => 30000,
        'monthly_deduction' => 10000,
    ]);

    $result = $service->calculateForEmployee(
        employee: $employee,
        month: 4, year: 2026,
        hrModel: $hrModel,
        overtimeHours: 0, absenceDeductionDays: 0, latePenaltyMinutes: 0,
        activeAdvanceAmount: 10000 // la mensualité
    );

    expect($result['deductions']['advance_deduction'])->toBe(10000.0);
    $advance = SalaryAdvance::where('employee_id', $employee->id)->first()->fresh();
    expect($advance->amount_remaining)->toBe(20000.0); // 30000 - 10000
});

it('fully repaid advance is marked as repaid', function () {
    $service = app(\App\Services\PayrollService::class);
    $employee = Employee::factory()->create(['salary_base' => 60000]);
    $advance = SalaryAdvance::factory()->create([
        'employee_id'       => $employee->id,
        'status'            => 'approved',
        'amount'            => 10000,
        'amount_remaining'  => 10000,
        'monthly_deduction' => 10000,
    ]);

    $service->calculateForEmployee(
        employee: $employee,
        month: 4, year: 2026,
        hrModel: HRModel::where('country', 'DZ')->first(),
        overtimeHours: 0, absenceDeductionDays: 0, latePenaltyMinutes: 0,
        activeAdvanceAmount: 10000
    );

    $advance->refresh();
    expect($advance->status)->toBe('repaid');
    expect($advance->amount_remaining)->toBe(0.0);
});

it('late penalty never exceeds one day salary', function () {
    $service = app(\App\Services\PayrollService::class);

    // Employé avec 22 jours ouvrables, salaire 60000
    // Salaire journalier = 60000 / 22 = 2727 DZD
    // Même avec 500 minutes de retard, pénalité ≤ 2727
    $penalty = $service->calculateLatePenalties(
        employee: Employee::factory()->make(['salary_base' => 60000]),
        month: 4, year: 2026,
        totalMinutesLate: 500, // excessif
        workingDays: 22,
        mode: 'proportional'
    );

    $maxPenalty = 60000 / 22; // salaire journalier
    expect($penalty)->toBeLessThanOrEqual($maxPenalty);
});

it('calculates correct net for Morocco (different social rates)', function () {
    $service = app(\App\Services\PayrollService::class);
    $hrModel = HRModel::where('country', 'MA')->first();

    $result = $service->calculateForEmployee(
        employee: Employee::factory()->make(['salary_base' => 5000, 'country' => 'MA']),
        month: 4, year: 2026,
        hrModel: $hrModel,
        overtimeHours: 0, absenceDeductionDays: 0, latePenaltyMinutes: 0, activeAdvanceAmount: 0
    );

    // CNSS MA = 4.48%, AMO = 2.26%, IR progressif
    expect($result['deductions']['social_security'])->toBeCloseTo(5000 * 0.0674, 0);
    expect($result['net_salary'])->toBeGreaterThan(0);
});
```

### Tests Feature (avec HTTP)

```php
// tests/Feature/Payroll/PayrollTest.php

it('validate payroll returns job_id immediately', function () {
    Queue::fake();
    [$company, $manager, $token] = createManagerWithToken('comptable');
    Employee::factory()->count(5)->create();

    $response = $this->withToken($token)
         ->postJson('/api/v1/payroll/validate', [
             'month'    => 4,
             'year'     => 2026,
             'notes'    => 'Paie avril 2026',
         ]);

    $response->assertStatus(202)
             ->assertJsonStructure(['data' => ['job_id', 'status', 'employees_count']]);

    Queue::assertPushed(\App\Jobs\GeneratePayslipPdfJob::class);
});

it('GET /payroll/status/{job_id} returns current status', function () {
    [$company, $manager, $token] = createManagerWithToken('comptable');
    $batch = PayrollExportBatch::factory()->create(['status' => 'processing']);

    $this->withToken($token)
         ->getJson("/api/v1/payroll/status/{$batch->job_id}")
         ->assertStatus(200)
         ->assertJsonStructure(['data' => ['job_id', 'status', 'progress']]);
});

it('employee can only see their own payslips', function () {
    [$company, $employeeA, $tokenA] = createEmployeeWithToken();
    $employeeB = Employee::factory()->create();
    $payslipB = Payroll::factory()->create(['employee_id' => $employeeB->id]);

    $this->withToken($tokenA)
         ->getJson("/api/v1/payroll/{$payslipB->id}")
         ->assertStatus(403);
});

it('bank export generates CSV in correct format for Algeria', function () {
    [$company, $manager, $token] = createManagerWithToken('comptable');
    $batch = PayrollExportBatch::factory()->create(['status' => 'validated', 'country' => 'DZ']);
    Payroll::factory()->count(3)->create(['batch_id' => $batch->id]);

    $response = $this->withToken($token)
         ->getJson("/api/v1/payroll/export-bank?batch_id={$batch->id}&format=ccp");

    $response->assertStatus(200)
             ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Vérifier le format CCP algérien
    $csvContent = $response->getContent();
    expect($csvContent)->toContain('RIB'); // colonne attendue
});
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Tenant/PayrollController.php
app/Http/Requests/Tenant/ValidatePayrollRequest.php
app/Http/Resources/PayrollResource.php
app/Http/Resources/PayrollListResource.php
app/Models/Tenant/Payroll.php
app/Models/Tenant/PayrollExportBatch.php
app/Services/PayrollService.php          ← cœur des calculs métier
app/Services/BankExportService.php       ← génération CSV par pays
app/Jobs/GeneratePayslipPdfJob.php       ← 1 job par employé, parallélisé
app/Observers/PayrollObserver.php        ← audit modifications bulletins
resources/views/payslips/template.blade.php  ← template DomPDF
```

---

## PAYROLLSERVICE — FORMULE COMPLÈTE

```php
// app/Services/PayrollService.php

public function calculateForEmployee(
    Employee $employee,
    int $month,
    int $year,
    HRModel $hrModel,
    float $overtimeHours,
    int $absenceDeductionDays,
    int $latePenaltyMinutes,
    float $activeAdvanceAmount
): array {
    $workingDays = $this->getWorkingDaysInMonth($month, $year, $employee->company->country);
    $dailyRate   = $employee->salary_base / $workingDays;
    $hourlyRate  = $employee->salary_base / ($workingDays * ($employee->schedule->expected_hours ?? 8));

    // 1. Brut de base
    $grossBase = $employee->salary_base;

    // 2. Heures supplémentaires (taux majoré selon config entreprise, défaut 150%)
    $overtimeRate = $employee->company->settings['overtime_rate'] ?? 1.5;
    $overtimePay  = round($overtimeHours * $hourlyRate * $overtimeRate, 2);

    // 3. Brut total
    $grossTotal = $grossBase + $overtimePay;

    // 4. Cotisations sociales (taux salarié selon pays)
    $socialSecurity = round($grossTotal * $hrModel->social_security_employee_rate, 2);

    // 5. Base imposable
    $taxableBase = $grossTotal - $socialSecurity;

    // 6. Impôt sur le revenu (tranches progressives selon pays)
    $incomeTax = $this->calculateIncomeTax($taxableBase, $hrModel);

    // 7. Pénalités de retard (plafonnées à 1 journée)
    $penaltyDeduction = $this->calculateLatePenalties(
        $employee, $month, $year, $latePenaltyMinutes, $workingDays,
        $employee->company->settings['payroll.penalty_mode'] ?? 'proportional'
    );

    // 8. Déduction avance active (mensualité)
    $advanceDeduction = $activeAdvanceAmount;

    // 9. Déduction absences non justifiées
    $absenceDeduction = round($absenceDeductionDays * $dailyRate, 2);

    // 10. Net à payer
    $netSalary = max(0, $grossTotal - $socialSecurity - $incomeTax - $penaltyDeduction - $advanceDeduction - $absenceDeduction);

    // 11. Mise à jour du solde avance
    $this->updateAdvanceRepayment($employee, $advanceDeduction);

    return [
        'salary_base'   => round($grossBase, 2),
        'overtime_pay'  => round($overtimePay, 2),
        'gross_total'   => round($grossTotal, 2),
        'working_days'  => $workingDays,
        'deductions' => [
            'social_security'   => $socialSecurity,
            'income_tax'        => round($incomeTax, 2),
            'penalty_deduction' => round($penaltyDeduction, 2),
            'advance_deduction' => round($advanceDeduction, 2),
            'absence_deduction' => round($absenceDeduction, 2),
        ],
        'net_salary' => round($netSalary, 2),
    ];
}

private function calculateLatePenalties(Employee $employee, int $month, int $year, int $totalMinutesLate, int $workingDays, string $mode): float
{
    if ($totalMinutesLate <= 0) return 0.0;

    $dailySalary = $employee->salary_base / $workingDays;
    $hourlySalary = $employee->salary_base / ($workingDays * 8);

    $penalty = match($mode) {
        'proportional' => ($totalMinutesLate / 60) * $hourlySalary,
        'bracket'      => $this->calculateBracketPenalty($totalMinutesLate, $hourlySalary, $employee),
        default        => 0.0,
    };

    // Plafond : jamais plus d'une journée de salaire
    return round(min($penalty, $dailySalary), 2);
}
```

---

## JOB ASYNC — GeneratePayslipPdfJob

```php
// app/Jobs/GeneratePayslipPdfJob.php
// Dispatché par PayrollController::validate() — 1 job par employé (parallèle)
// Utilise DomPDF avec le template resources/views/payslips/template.blade.php
// Stockage : storage/app/payslips/{year}/{month}/{employee_id}.pdf
// Met à jour Payroll->pdf_url et ->status = 'validated'
// Envoie notification push 'payslip_available' à l'employé
// Mise à jour progression du batch (PayrollExportBatch->progress)
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-07

```
[ ] php artisan test --filter="Unit\\\PayrollService" → 0 failure (cas DZ, MA, taux exacts)
[ ] php artisan test --filter="Feature\\\Payroll" → 0 failure
[ ] POST /payroll/validate → retourne job_id en < 200ms (async OK)
[ ] Job GeneratePayslipPdfJob → génère un PDF lisible
[ ] Avance déduite + amount_remaining mis à jour + status=repaid si soldé
[ ] Pénalité plafonnée à 1 journée de salaire (test passe)
[ ] pdf_url accessible et téléchargeable
[ ] php artisan test (global) → 0 failure
```

---

## COMMIT

```
feat: add payroll module with PayrollService supporting DZ, MA, TR, FR tax models
feat: add async PDF generation with GeneratePayslipPdfJob and DomPDF
feat: add bank export service with CCP/Barid format for Algeria
test: add 8 payroll unit tests with exact tax calculations
test: add 4 feature payroll tests including async status polling
```
