<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;

use Tests\TestCase;

/**
 * Tests Feature — Validation Manager/RH des sessions GPS
 *
 * Endpoints :
 *   POST /api/v1/smart-attendance/sessions/{id}/approve
 *   POST /api/v1/smart-attendance/sessions/{id}/reject
 */
class ManagerValidationTest extends TestCase
{
    
    use RefreshTenantDatabase;

    private Company $company;
    private Employee $employee;
    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name'         => 'ValidationCorp',
            'slug'         => 'validation-corp',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'corp@validation.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id'               => $this->company->id,
            'name'                     => 'Standard',
            'start_time'               => '08:00:00',
            'end_time'                 => '17:00:00',
            'late_tolerance_minutes'   => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default'               => true,
        ]);

        $this->employee = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'emp@validation.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        $this->manager = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'manager@validation.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'manager',
            'manager_role'  => 'rh',
            'status'        => 'active',
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createPendingSession(): GeoAttendanceSession
    {
        /** @var GeoAttendanceSession */
        return GeoAttendanceSession::query()->create([
            'employee_id'   => $this->employee->id,
            'company_id'    => $this->company->id,
            'started_at'    => now()->subHours(8),
            'ended_at'      => now()->subHour(),
            'duration_seconds' => 7 * 3600,
            'check_in_lat'  => 36.7539,
            'check_in_lng'  => 3.0589,
            'check_out_lat' => 36.7540,
            'check_out_lng' => 3.0590,
            'status'        => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ]);
    }

    // ── Tests ────────────────────────────────────────────────────────────────

    /**
     * Un manager peut approuver une session pending → status=approved,
     * un attendance_log_id est créé.
     */
    public function test_manager_can_approve_pending_session(): void
    {
        $session = $this->createPendingSession();

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/approve", [
            'note' => 'Validé — présence confirmée',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', GeoAttendanceSession::STATUS_APPROVED);

        $session->refresh();
        $this->assertSame(GeoAttendanceSession::STATUS_APPROVED, $session->status);
        $this->assertNotNull($session->attendance_log_id, 'Un attendance_log doit être créé');
        $this->assertNotNull($session->validated_by);
        $this->assertSame($this->manager->id, $session->validated_by);
    }

    /**
     * Un manager peut rejeter une session pending avec une raison.
     */
    public function test_manager_can_reject_pending_session(): void
    {
        $session = $this->createPendingSession();

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/reject", [
            'reason' => 'Position GPS suspecte, hors zone habituelle.',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', GeoAttendanceSession::STATUS_REJECTED);

        $session->refresh();
        $this->assertSame(GeoAttendanceSession::STATUS_REJECTED, $session->status);
        $this->assertNotNull($session->validation_note);
    }

    /**
     * Rejeter sans raison retourne 422.
     */
    public function test_reject_requires_reason(): void
    {
        $session = $this->createPendingSession();

        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/reject", [
            // 'reason' absent
        ]);

        $response->assertStatus(422);
    }

    /**
     * Après approbation, l'AttendanceLog créé possède method='geo_auto'.
     */
    public function test_approve_updates_attendance_log_method_to_geo_auto(): void
    {
        $session = $this->createPendingSession();

        Sanctum::actingAs($this->manager);

        $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/approve", [
            'note' => 'OK',
        ])->assertStatus(200);

        $session->refresh();
        $this->assertNotNull($session->attendance_log_id);

        $log = AttendanceLog::query()->find($session->attendance_log_id);
        $this->assertNotNull($log, 'AttendanceLog introuvable');
        $this->assertSame('geo_auto', $log->method);
    }

    /**
     * Un employé (rôle) ne peut pas approuver une session → 403.
     */
    public function test_employee_cannot_approve_sessions(): void
    {
        $session = $this->createPendingSession();

        Sanctum::actingAs($this->employee);

        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$session->id}/approve", [
            'note' => 'Tentative non autorisée',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Un manager ne peut pas approuver une session d'une autre company → 404.
     */
    public function test_manager_cannot_approve_cross_tenant_session(): void
    {
        // Créer une autre company avec sa propre session
        $otherCompany = Company::query()->create([
            'name'         => 'OtherCorp',
            'slug'         => 'other-corp',
            'sector'       => 'finance',
            'country'      => 'DZ',
            'city'         => 'Oran',
            'email'        => 'other@corp.test',
            'schema_name'  => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status'       => 'active',
            'timezone'     => 'UTC',
        ]);

        $otherSchedule = Schedule::query()->create([
            'company_id'               => $otherCompany->id,
            'name'                     => 'Standard',
            'start_time'               => '08:00:00',
            'end_time'                 => '17:00:00',
            'late_tolerance_minutes'   => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default'               => true,
        ]);

        $otherEmployee = Employee::query()->create([
            'company_id'    => $otherCompany->id,
            'schedule_id'   => $otherSchedule->id,
            'email'         => 'emp@other.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        // Session appartenant à l'autre company
        $foreignSession = GeoAttendanceSession::query()->create([
            'employee_id'      => $otherEmployee->id,
            'company_id'       => $otherCompany->id,
            'started_at'       => now()->subHours(8),
            'ended_at'         => now()->subHour(),
            'duration_seconds' => 7 * 3600,
            'check_in_lat'     => 35.6906,
            'check_in_lng'     => -0.6347,
            'status'           => GeoAttendanceSession::STATUS_PENDING_VALIDATION,
        ]);

        // Le manager de company A tente d'approuver la session de company B
        Sanctum::actingAs($this->manager);

        $response = $this->postJson("/api/v1/smart-attendance/sessions/{$foreignSession->id}/approve", [
            'note' => 'Cross-tenant attempt',
        ]);

        // Le contrôleur filtre par company_id → findOrFail → 404
        $response->assertStatus(404);
    }
}

