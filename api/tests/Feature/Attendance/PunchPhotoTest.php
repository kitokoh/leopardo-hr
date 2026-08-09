<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Tests Feature — Pointage mobile avec photo obligatoire (issue #761).
 *
 * Quand AttendanceModeSettings::punch_photo_mode = 'photo_required', l'employé
 * doit fournir une photo pour pointer (check-in/check-out). Quand le mode est
 * 'kiosk' (ou null), la photo reste optionnelle.
 */
class PunchPhotoTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::query()->create([
            'name' => 'PhotoCorp',
            'slug' => 'photo-corp',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'photo@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8.0,
            'is_default' => true,
        ]);

        $this->employee = Employee::query()->create([
            'company_id' => $this->company->id,
            'schedule_id' => $schedule->id,
            'email' => 'employee@photo.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_check_in_without_photo_is_rejected_when_company_requires_photo(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/attendance/check-in');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PUNCH_PHOTO_REQUIRED');

        $this->assertSame(0, AttendanceLog::query()->count());
    }

    public function test_check_in_with_photo_succeeds_when_company_requires_photo(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        Sanctum::actingAs($this->employee);

        $photo = UploadedFile::fake()->image('punch.jpg');

        $response = $this->post('/api/v1/attendance/check-in', [
            'punch_photo' => $photo,
        ]);

        $response->assertStatus(201);

        $log = AttendanceLog::query()->firstOrFail();
        $this->assertNotNull($log->punch_photo_path);
        Storage::disk('local')->assertExists($log->punch_photo_path);
    }

    public function test_check_in_without_photo_succeeds_when_company_mode_is_kiosk(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'kiosk',
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/attendance/check-in');

        $response->assertStatus(201);

        $log = AttendanceLog::query()->firstOrFail();
        $this->assertNull($log->punch_photo_path);
    }

    public function test_check_in_without_photo_succeeds_when_no_mode_settings_configured(): void
    {
        // Aucun AttendanceModeSettings pour cette company : comportement historique inchangé.
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/attendance/check-in');

        $response->assertStatus(201);
    }

    /**
     * Ouvre directement une session de pointage (check-in) via une insertion SQL
     * brute, pour contourner un bug pré-existant (hors périmètre de ce ticket) :
     * en SQLite, le cast Eloquent 'date' stocke "Y-m-d H:i:s" alors que les
     * requêtes AttendanceService comparent à "Y-m-d", ce qui fait échouer le
     * flux check-in -> check-out complet dans cet environnement de test
     * (reproductible à l'identique sur main, sans rapport avec la photo).
     */
    private function openAttendanceSession(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('attendance_logs')->insertGetId([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => now('UTC')->toDateString(),
            'session_number' => 1,
            'check_in' => now('UTC'),
            'method' => 'mobile',
            'work_type' => 'normal',
            'status' => 'ontime',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    public function test_check_out_without_photo_is_rejected_when_company_requires_photo(): void
    {
        Sanctum::actingAs($this->employee);

        $this->openAttendanceSession();

        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        $response = $this->postJson('/api/v1/attendance/check-out');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'PUNCH_PHOTO_REQUIRED');
    }

    public function test_check_out_with_photo_succeeds_when_company_requires_photo(): void
    {
        Sanctum::actingAs($this->employee);

        $this->openAttendanceSession();

        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        $photo = UploadedFile::fake()->image('punch-out.jpg');

        $response = $this->post('/api/v1/attendance/check-out', [
            'punch_photo' => $photo,
        ]);

        $response->assertStatus(200);

        $log = AttendanceLog::query()->firstOrFail();
        $this->assertNotNull($log->punch_photo_path);
    }

    public function test_smart_attendance_config_exposes_requires_punch_photo(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/smart-attendance/config');

        $response->assertStatus(200);
        $response->assertJsonPath('data.requires_punch_photo', true);
    }

    public function test_employee_can_download_own_punch_photo(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id' => $this->company->id,
            'punch_photo_mode' => 'photo_required',
        ]);

        Sanctum::actingAs($this->employee);

        $photo = UploadedFile::fake()->image('punch.jpg');
        $this->post('/api/v1/attendance/check-in', ['punch_photo' => $photo])->assertStatus(201);

        $log = AttendanceLog::query()->firstOrFail();

        $response = $this->get("/api/v1/attendance/{$log->id}/punch-photo");

        $response->assertStatus(200);
    }
}
