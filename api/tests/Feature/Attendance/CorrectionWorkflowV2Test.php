<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendancePeriodClosure;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5267 — compléments du workflow de correction de pointage :
 * justificatif, verrouillage de période, audit trail, anti-fraude géo.
 */
class CorrectionWorkflowV2Test extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCorrection(Company $company, Employee $employee, string $date = '2026-05-27'): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $date,
            'requested_check_in' => Carbon::parse("{$date} 08:12:00", 'UTC'),
            'requested_check_out' => Carbon::parse("{$date} 17:20:00", 'UTC'),
            'reason' => 'Oubli du pointage mobile',
            'status' => 'pending',
        ]);
    }

    public function test_employee_attaches_proof_and_manager_downloads_it(): void
    {
        [$company, , $manager, $employee] = $this->fixture();

        Sanctum::actingAs($employee);

        $response = $this->call('POST', '/api/v1/attendance/corrections', [
            'date' => '2026-05-27',
            'requested_check_in' => '2026-05-27 08:12:00',
            'requested_check_out' => '2026-05-27 17:20:00',
            'reason' => 'Oubli du pointage mobile',
        ], [], ['proof' => UploadedFile::fake()->image('justificatif.jpg')], ['HTTP_ACCEPT' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.proof_url', fn (?string $url) => is_string($url) && str_contains($url, '/attendance/corrections/'));

        $correctionId = $response->json('data.id');
        $this->assertIsInt($correctionId);
        $stored = DB::table('attendance_correction_requests')->where('id', $correctionId)->first();
        $this->assertNotNull($stored?->proof_path, 'proof_path non enregistré');
        $this->assertIsString($stored->proof_path);
        $this->assertStringContainsString('attendance-corrections/proofs/', $stored->proof_path, 'chemin proof inattendu : '.$stored->proof_path);

        // L'employé propriétaire télécharge son justificatif.
        $this->getJson("/api/v1/attendance/corrections/{$correctionId}/proof")->assertOk();

        // Le manager du tenant aussi.
        Sanctum::actingAs($manager);
        $this->get("/api/v1/attendance/corrections/{$correctionId}/proof")
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        // Un manager d'un autre tenant → 404 (jamais de fuite).
        [$otherCompany, , $otherManager] = $this->fixture('company-b', 'b.test');
        Sanctum::actingAs($otherManager);
        $this->getJson("/api/v1/attendance/corrections/{$correctionId}/proof")->assertNotFound();
    }

    public function test_download_without_proof_returns_404(): void
    {
        [$company, , $manager, $employee] = $this->fixture();
        $correction = $this->makeCorrection($company, $employee);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/attendance/corrections/{$correction->id}/proof")
            ->assertNotFound();
    }

    public function test_correction_request_is_blocked_after_period_closed(): void
    {
        [$company, , $manager, $employee] = $this->fixture();

        $service = $this->app->make(AttendancePeriodClosureService::class);
        $service->closePeriod($company->id, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $manager);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/attendance/corrections', [
            'date' => '2026-05-27',
            'requested_check_in' => '2026-05-27 08:12:00',
            'requested_check_out' => '2026-05-27 17:20:00',
            'reason' => 'Oubli du pointage mobile',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'ATTENDANCE_PERIOD_CLOSED');

        // Une date hors période close reste acceptée.
        $this->postJson('/api/v1/attendance/corrections', [
            'date' => '2026-06-02',
            'requested_check_in' => '2026-06-02 08:12:00',
            'requested_check_out' => '2026-06-02 17:20:00',
            'reason' => 'Oubli du pointage mobile',
        ])->assertCreated();
    }

    public function test_correction_decisions_are_blocked_after_period_closed(): void
    {
        [$company, , $manager, $employee] = $this->fixture();
        $correction = $this->makeCorrection($company, $employee);

        $service = $this->app->make(AttendancePeriodClosureService::class);
        $service->closePeriod($company->id, Carbon::parse('2026-05-01'), Carbon::parse('2026-05-31'), $manager);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/attendance/corrections/{$correction->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'ATTENDANCE_PERIOD_CLOSED');

        $this->postJson("/api/v1/attendance/corrections/{$correction->id}/reject")
            ->assertStatus(422)
            ->assertJsonPath('error', 'ATTENDANCE_PERIOD_CLOSED');
    }

    public function test_correction_lifecycle_writes_audit_trail(): void
    {
        [$company, , $manager, $employee] = $this->fixture();

        Sanctum::actingAs($employee);

        $created = $this->postJson('/api/v1/attendance/corrections', [
            'date' => '2026-05-27',
            'requested_check_in' => '2026-05-27 08:12:00',
            'requested_check_out' => '2026-05-27 17:20:00',
            'reason' => 'Oubli du pointage mobile',
        ])->assertCreated();

        $correctionId = $created->json('data.id');
        $this->assertIsInt($correctionId);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'action' => 'attendance_correction_requested',
            'auditable_type' => AttendanceCorrectionRequest::class,
            'auditable_id' => $correctionId,
        ]);

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/attendance/corrections/{$correctionId}/approve")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'attendance_correction_approved',
            'auditable_id' => $correctionId,
        ]);

        // Rejet tracé aussi.
        $correction = AttendanceCorrectionRequest::query()->findOrFail($correctionId);
        $rejected = $this->makeCorrection($company, $employee, '2026-06-03');
        $this->postJson("/api/v1/attendance/corrections/{$rejected->id}/reject")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $manager->id,
            'action' => 'attendance_correction_rejected',
            'auditable_id' => $rejected->id,
        ]);
    }

    public function test_geo_session_conflict_is_flagged_in_list(): void
    {
        [$company, , $manager, $employee] = $this->fixture();

        // Session géo VALIDÉE : 08:00 → 17:00.
        GeoAttendanceSession::query()->create([
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'started_at' => '2026-05-27 08:00:00',
            'ended_at' => '2026-05-27 17:00:00',
            'check_in_lat' => 36.75,
            'check_in_lng' => 3.06,
            'status' => 'approved',
        ]);

        $conflicting = $this->makeCorrection($company, $employee); // 08:12 → 17:20
        $matching = AttendanceCorrectionRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-27',
            'requested_check_in' => Carbon::parse('2026-05-27 08:00:00', 'UTC'),
            'requested_check_out' => Carbon::parse('2026-05-27 17:00:00', 'UTC'),
            'reason' => 'Aligné géo',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/attendance/corrections')
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.anomaly', null);

        $byId = $this->getJson('/api/v1/attendance/corrections')->json('data');
        $this->assertIsArray($byId);
        $conflictPayload = collect($byId)->firstWhere('id', $conflicting->id);
        $this->assertIsArray($conflictPayload);

        $anomaly = $conflictPayload['anomaly'] ?? null;
        $this->assertIsArray($anomaly);
        $this->assertTrue($anomaly['flagged']);
        $this->assertSame('geo_session_conflict', $anomaly['reason']);
    }

    public function test_close_period_command_is_idempotent_and_validates_input(): void
    {
        [$company] = $this->fixture();

        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('attendance:close-period', ['--company' => 'company-a', '--month' => '2026-05'])->assertSuccessful()->expectsOutputToContain('clôturée');

        $this->assertSame(1, AttendancePeriodClosure::query()->where('company_id', $company->id)->count());

        // Idempotent : second run → toujours 1 clôture, pas de double audit.
        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('attendance:close-period', ['--company' => 'company-a', '--month' => '2026-05'])->assertSuccessful();
        $this->assertSame(1, AttendancePeriodClosure::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, AuditLog::query()->where('company_id', $company->id)->where('action', 'attendance_period_closed')->count());

        // --month invalide → échec.
        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('attendance:close-period', ['--company' => 'company-a', '--month' => '2026-13'])->assertFailed();

        // --company manquant → échec.
        // @phpstan-ignore-next-line method.nonObject (artisan() retourne PendingCommand|int)
        $this->artisan('attendance:close-period', ['--month' => '2026-05'])->assertFailed();
    }

    /**
     * @return array{Company, Schedule, Employee, Employee}
     */
    private function fixture(string $slug = 'company-a', string $domain = 'company.test'): array
    {
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$domain,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $manager = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Nadia',
            'last_name' => 'Manager',
            'email' => 'manager@'.$domain,
        ]);
        $manager->forceFill(['password_hash' => Hash::make('password123')])->save();
        $manager->forceFill([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ])->save();

        $employee = new Employee([
            'schedule_id' => $schedule->id,
            'first_name' => 'Amina',
            'last_name' => 'Test',
            'email' => 'employee@'.$domain,
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password123')])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();

        return [$company, $schedule, $manager, $employee];
    }
}
