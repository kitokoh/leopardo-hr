<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Application\DTOs\CheckInDTO;
use App\Modules\Attendance\Domain\Exceptions\AttendanceDayClosedException;
use App\Modules\Attendance\Domain\Models\AttendanceDayClosure;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Fermeture de journée du pointage (issue #5265).
 *
 * Endpoints (manager/rh/principal) :
 *   GET    /api/v1/attendance/day-closures
 *   POST   /api/v1/attendance/day-closures
 *   POST   /api/v1/attendance/day-closures/{id}/validate
 *   DELETE /api/v1/attendance/day-closures/{id}
 *
 * Garde : un jour verrouillé refuse tout nouveau pointage
 * (check-in/check-out/import/approbation géo) → 409 ATTENDANCE_DAY_CLOSED.
 */
class AttendanceDayClosureTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $manager;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'ClosureCorp',
            'slug' => 'closure-corp',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'corp@closure.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        $this->schedule = Schedule::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $this->employee = $this->makeEmployee('emp@closure.test', 'employee');
        $this->manager = $this->makeEmployee('manager@closure.test', 'manager', 'rh');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeEmployee(string $email, string $role, ?string $managerRole = null): Employee
    {
        $employee = new Employee([
            'schedule_id' => $this->schedule->id,
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $employee->forceFill([
            'company_id' => $this->company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        return $employee;
    }

    private function lockPayload(int $employeeId, string $date): array
    {
        return ['employee_id' => $employeeId, 'date' => $date];
    }

    private function lockDay(int $employeeId, string $date): int
    {
        $response = $this->postJson('/api/v1/attendance/day-closures', $this->lockPayload($employeeId, $date));

        $response->assertStatus(201);

        return (int) $response->json('data.id');
    }

    private function createPendingGeoSession(Employee $employee, string $startedAt, string $endedAt): GeoAttendanceSession
    {
        /** @var GeoAttendanceSession */
        return GeoAttendanceSession::query()->create([
            'employee_id' => $employee->id,
            'company_id' => $this->company->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => 9 * 3600,
            'check_in_lat' => 36.7539,
            'check_in_lng' => 3.0589,
            'check_out_lat' => 36.7540,
            'check_out_lng' => 3.0590,
            'status' => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ]);
    }

    // ── Tests — CRUD fermeture ───────────────────────────────────────────────

    public function test_manager_can_lock_a_day(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/attendance/day-closures', $this->lockPayload($this->employee->id, '2026-04-06'));

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', AttendanceDayClosure::STATUS_LOCKED);
        $response->assertJsonPath('data.employee_id', $this->employee->id);
        $response->assertJsonPath('data.date', '2026-04-06');
        $response->assertJsonPath('data.locked_by', $this->manager->id);

        $this->assertDatabaseHas('attendance_day_closures', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-04-06',
            'status' => AttendanceDayClosure::STATUS_LOCKED,
        ]);
    }

    public function test_lock_is_idempotent(): void
    {
        Sanctum::actingAs($this->manager);

        $first = $this->lockDay($this->employee->id, '2026-04-06');
        $second = $this->lockDay($this->employee->id, '2026-04-06');

        $this->assertSame($first, $second);
        $this->assertSame(1, AttendanceDayClosure::query()->count());
    }

    public function test_manager_can_validate_a_locked_day(): void
    {
        Sanctum::actingAs($this->manager);

        $id = $this->lockDay($this->employee->id, '2026-04-06');

        $response = $this->postJson("/api/v1/attendance/day-closures/{$id}/validate", ['note' => 'Relu']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', AttendanceDayClosure::STATUS_VALIDATED);
        $response->assertJsonPath('data.validated_by', $this->manager->id);
        $this->assertNotNull($response->json('data.validated_at'));

        $this->assertDatabaseHas('attendance_day_closures', [
            'id' => $id,
            'status' => AttendanceDayClosure::STATUS_VALIDATED,
        ]);
    }

    public function test_destroy_unlocks_day_and_punches_work_again(): void
    {
        Sanctum::actingAs($this->manager);

        $id = $this->lockDay($this->employee->id, '2026-04-06');

        $this->deleteJson("/api/v1/attendance/day-closures/{$id}")->assertStatus(204);

        Sanctum::actingAs($this->employee);
        $this->travelTo('2026-04-06 08:00:00');

        $this->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.75, 'gps_lng' => 3.05])
            ->assertStatus(201);
    }

    public function test_list_filters_by_date_and_employee(): void
    {
        Sanctum::actingAs($this->manager);

        $this->lockDay($this->employee->id, '2026-04-06');
        $this->lockDay($this->employee->id, '2026-04-07');

        $this->getJson('/api/v1/attendance/day-closures?date=2026-04-06')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-04-06');

        $this->getJson("/api/v1/attendance/day-closures?employee_id={$this->employee->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ── Tests — garde 409 sur les pointages ──────────────────────────────────

    public function test_check_in_blocked_on_closed_day(): void
    {
        Sanctum::actingAs($this->manager);
        $this->lockDay($this->employee->id, '2026-04-06');

        Sanctum::actingAs($this->employee);
        $this->travelTo('2026-04-06 08:00:00');

        $this->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.75, 'gps_lng' => 3.05])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ATTENDANCE_DAY_CLOSED');

        $this->assertSame(0, AttendanceLog::query()->count());
    }

    public function test_check_out_blocked_on_closed_day(): void
    {
        Sanctum::actingAs($this->employee);
        $this->travelTo('2026-04-06 08:00:00');

        $this->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.75, 'gps_lng' => 3.05])
            ->assertStatus(201);

        Sanctum::actingAs($this->manager);
        $this->lockDay($this->employee->id, '2026-04-06');

        Sanctum::actingAs($this->employee);
        $this->travelTo('2026-04-06 08:30:00');

        $this->postJson('/api/v1/attendance/check-out', ['gps_lat' => 36.75, 'gps_lng' => 3.05])
            ->assertStatus(409)
            ->assertJsonPath('error', 'ATTENDANCE_DAY_CLOSED');
    }

    public function test_external_punch_blocked_on_closed_day(): void
    {
        Sanctum::actingAs($this->manager);
        $this->lockDay($this->employee->id, '2026-04-06');

        // Appel direct du service (pas de middleware HTTP) : binder le tenant.
        app()->instance('current_company', $this->company);

        $service = app(AttendanceService::class);

        $this->expectException(AttendanceDayClosedException::class);

        $service->importExternalPunch(
            $this->employee,
            new CheckInDTO(
                method: 'biometric',
                occurred_at: '2026-04-06 08:00:00',
                action: 'check_in',
                work_type: 'normal',
                source_device_code: 'ZKT-01',
            ),
        );
    }

    public function test_geo_approval_blocked_on_closed_day(): void
    {
        $session = $this->createPendingGeoSession($this->employee, '2026-04-06 08:00:00', '2026-04-06 17:00:00');

        Sanctum::actingAs($this->manager);
        $this->lockDay($this->employee->id, '2026-04-06');

        $this->postJson("/api/v1/attendance/geo-sessions/{$session->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error', 'ATTENDANCE_DAY_CLOSED');

        $this->assertSame(0, AttendanceLog::query()->count());
        $this->assertSame(GeoAttendanceSession::STATUS_PENDING_VALIDATION, $session->fresh()->status);
    }

    // ── Tests — convergence géo (calcul unifié) ──────────────────────────────

    public function test_geo_approval_deducts_schedule_break(): void
    {
        $session = $this->createPendingGeoSession($this->employee, '2026-04-06 08:00:00', '2026-04-06 17:00:00');

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/attendance/geo-sessions/{$session->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', GeoAttendanceSession::STATUS_APPROVED);

        /** @var AttendanceLog $log */
        $log = AttendanceLog::query()->firstOrFail();

        // Contrôle manuel : 08:00 → 17:00 = 9 h brutes ; − 60 min de pause = 8 h ;
        // seuil 8 h → 0 h supplémentaire (convergence géo/mobile, issue #5265).
        $this->assertSame('geo_auto', $log->method);
        $this->assertSame('8.00', $log->hours_worked);
        $this->assertSame('0.00', $log->overtime_hours);
    }

    // ── Tests — RBAC + isolation tenant ──────────────────────────────────────

    public function test_employee_cannot_manage_closures(): void
    {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/attendance/day-closures', $this->lockPayload($this->employee->id, '2026-04-06'))
            ->assertStatus(403);
        $this->getJson('/api/v1/attendance/day-closures')->assertStatus(403);
    }

    public function test_manager_without_role_cannot_manage_closures(): void
    {
        $plainManager = $this->makeEmployee('plain@closure.test', 'manager');

        Sanctum::actingAs($plainManager);

        $this->postJson('/api/v1/attendance/day-closures', $this->lockPayload($this->employee->id, '2026-04-06'))
            ->assertStatus(403);
    }

    public function test_cross_tenant_employee_rejected(): void
    {
        $otherCompany = Company::query()->create([
            'name' => 'OtherCorp',
            'slug' => 'other-corp',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'corp@other.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
        ]);

        $foreignEmployee = $this->makeEmployeeInCompany('foreign@other.test', $otherCompany);

        Sanctum::actingAs($this->manager);

        // Store cross-tenant → validation scopée entreprise (fail-closed).
        $this->postJson('/api/v1/attendance/day-closures', $this->lockPayload($foreignEmployee->id, '2026-04-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');

        // Destroy d'une fermeture d'un autre tenant → 404 (findOrFail scopé).
        $foreignClosure = AttendanceDayClosure::query()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $foreignEmployee->id,
            'date' => '2026-04-06',
            'status' => AttendanceDayClosure::STATUS_LOCKED,
            'locked_by' => $this->manager->id,
            'locked_at' => now(),
        ]);

        $this->deleteJson("/api/v1/attendance/day-closures/{$foreignClosure->id}")->assertStatus(404);
    }

    private function makeEmployeeInCompany(string $email, Company $company): Employee
    {
        $employee = new Employee([
            'email' => $email,
            'first_name' => 'Foreign',
            'last_name' => 'User',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        return $employee;
    }
}
