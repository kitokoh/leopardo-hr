<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issues #2416 (LeaveCarryForward : solde reportable = balance − used −
 * pending) et #2418 (AbsenceService::create : le contrôle de solde réserve
 * les jours pending — plus de sur-réservation).
 */
class LeavePendingReservationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0: Company, 1: Employee, 2: AbsenceType, 3: LeavePolicy}
     */
    private function context(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        /** @var AbsenceType $type */
        $type = AbsenceType::factory()->create([
            'company_id' => $company->id,
            'deducts_leave' => true,
        ]);

        /** @var LeavePolicy $policy */
        $policy = LeavePolicy::query()->create([
            'company_id' => $company->id,
            'absence_type_id' => $type->id,
            'name' => 'Congés annuels',
            'accrual_type' => 'yearly',
            'accrual_amount' => 10,
            'carry_forward' => true,
            'carry_forward_max' => 30,
            'active' => true,
        ]);

        return [$company, $employee, $type, $policy];
    }

    // ── Issue #2416 : LeaveCarryForward ─────────────────────────────────

    public function test_carry_forward_deducts_pending_days(): void
    {
        [$company, $employee, $type, $policy] = $this->context();

        LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 10,
            'used' => 0,
            'pending' => 5, // 5 jours en attente (réservés par #2329)
            'year' => now()->year - 1,
        ]);

        $carry = $this->artisan('leave:carry-forward', ['--year' => now()->year - 1]);
        $this->assertInstanceOf(PendingCommand::class, $carry);
        // PendingCommand::assertExitCode() ne fait que POSER l'attente : la
        // commande ne s'exécute qu'au run() (ou au destructeur sinon) — sans
        // run() explicite, les assertions DB ci-dessous tournent AVANT la
        // commande (issue #5201).
        $carry->assertExitCode(0);
        $carry->run();

        // Reporté = 10 − 0 − 5 = 5 (et non 10 avant #2416).
        $carried = LeaveAccrual::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('type', 'carry_forward')
            ->sum('amount');

        $this->assertSame(5.0, (float) $carried);

        $newBalance = LeaveBalance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('absence_type_id', $type->id)
            ->where('year', now()->year)
            ->first();
        $this->assertNotNull($newBalance);
        $this->assertSame(5.0, (float) $newBalance->balance);
    }

    public function test_carry_forward_ignores_cancelled_pending(): void
    {
        [$company, $employee, $type, $policy] = $this->context();

        LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 10,
            'used' => 0,
            'pending' => 0, // demande annulée → pending libéré
            'year' => now()->year - 1,
        ]);

        $carry = $this->artisan('leave:carry-forward', ['--year' => now()->year - 1]);
        $this->assertInstanceOf(PendingCommand::class, $carry);
        // PendingCommand::assertExitCode() ne fait que POSER l'attente : la
        // commande ne s'exécute qu'au run() (ou au destructeur sinon) — sans
        // run() explicite, les assertions DB ci-dessous tournent AVANT la
        // commande (issue #5201).
        $carry->assertExitCode(0);
        $carry->run();

        $carried = LeaveAccrual::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('type', 'carry_forward')
            ->sum('amount');

        $this->assertSame(10.0, (float) $carried);
    }

    // ── Issue #2418 : contrôle de solde à la création ──────────────────

    public function test_create_blocks_second_request_when_pending_reserves_balance(): void
    {
        [$company, $employee, $type] = $this->context();

        LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'balance' => 1,
            'used' => 0,
            'pending' => 0,
            'year' => now()->year,
        ]);

        Sanctum::actingAs($employee);

        $firstDate = now()->addMonths(2)->nextWeekday();
        $secondDate = $firstDate->copy()->addWeekday();

        // Première demande : 1 jour ouvré → pending = 1.
        $this->postJson('/api/v1/absences', [
            'absence_type_id' => $type->id,
            'start_date' => $firstDate->format('Y-m-d'),
            'end_date' => $firstDate->format('Y-m-d'),
        ])->assertCreated();

        // Seconde demande : 1 jour ouvré → disponible = 1 − 0 − 1 = 0 → bloquée.
        $this->postJson('/api/v1/absences', [
            'absence_type_id' => $type->id,
            'start_date' => $secondDate->format('Y-m-d'),
            'end_date' => $secondDate->format('Y-m-d'),
        ])->assertStatus(422);
    }
}
