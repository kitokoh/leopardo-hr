<?php

declare(strict_types=1);

namespace Tests\Feature\Absences;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #2329 — le snapshot `leave_balances` (balance/used/pending) servi par
 * LeavePolicyController doit être synchronisé par AbsenceService :
 * create → pending += days ; approve → pending −= days, used += days ;
 * reject pending → pending −= days ; cancel → pending −= days.
 * La source de vérité reste la chaîne leave_balance_logs.
 */
class AbsenceLeaveBalanceSnapshotTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $manager;

    private Employee $employee;

    private AbsenceType $type;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::query()->create([
            'name' => 'Company Snapshot',
            'slug' => 'company-snapshot',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@snapshot.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
        /** @var Schedule $schedule */
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Day',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        /** @var AbsenceType $type */
        $type = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
        $this->type = $type;

        /** @var Employee $manager */
        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Mgr',
            'last_name' => 'Snapshot',
            'email' => 'mgr@snapshot.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Emp',
            'last_name' => 'Snapshot',
            'email' => 'emp@snapshot.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employee = $employee;

        // 20 jours de crédit initiaux (source de vérité = logs).
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);
    }

    public function test_create_reserves_pending_then_approve_moves_to_used(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-05',
            'reason' => 'Snapshot test',
        ]);
        $response->assertStatus(201);
        $absenceId = $response->json('data.id');

        // create → pending += 2
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => 2026,
            'pending' => 2.0,
            'used' => 0.0,
        ]);

        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/absences/{$absenceId}/approve")->assertStatus(200);

        // approve → pending −= 2, used += 2
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => 2026,
            'pending' => 0.0,
            'used' => 2.0,
        ]);

        // Le snapshot est servi par l'API des soldes.
        Sanctum::actingAs($this->employee);
        $balances = $this->getJson('/api/v1/employees/'.$this->employee->id.'/leave-balances?year=2026')
            ->assertOk()
            ->json();
        $this->assertSame(2.0, (float) $balances[0]['used']);
        $this->assertSame(0.0, (float) $balances[0]['pending']);
    }

    public function test_reject_pending_releases_reservation(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-07',
            'reason' => 'Rejected snapshot',
        ]);
        $absenceId = $response->json('data.id');

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'pending' => 2.0,
        ]);

        Sanctum::actingAs($this->manager);
        $this->putJson("/api/v1/absences/{$absenceId}/reject", ['rejected_reason' => 'Non justifié'])
            ->assertStatus(200);

        // reject (pending) → pending −= 2
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'pending' => 0.0,
            'used' => 0.0,
        ]);
    }

    public function test_cancel_pending_releases_reservation(): void
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/absences', [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-12',
            'reason' => 'Cancelled snapshot',
        ]);
        $absenceId = $response->json('data.id');

        $this->deleteJson("/api/v1/absences/{$absenceId}")->assertStatus(200);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee->id,
            'year' => 2026,
            'pending' => 0.0,
            'used' => 0.0,
        ]);
    }

    public function test_leave_balances_snapshot_is_tenant_isolated(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-co-snapshot',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'o@snapshot.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::query()->create([
            'company_id' => $otherCompany->id,
            'first_name' => 'Other',
            'last_name' => 'Emp',
            'email' => 'oe@snapshot.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Le snapshot de l'employé A ne doit pas apparaître pour l'employé B.
        Sanctum::actingAs($otherEmployee);
        $otherBalances = $this->getJson('/api/v1/employees/'.$otherEmployee->id.'/leave-balances?year=2026')
            ->assertOk()
            ->json();

        $this->assertCount(0, $otherBalances);
    }
}
