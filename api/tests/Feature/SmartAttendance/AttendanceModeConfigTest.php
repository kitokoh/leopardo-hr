<?php

declare(strict_types=1);

namespace Tests\Feature\SmartAttendance;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\SmartAttendance\Domain\Models\AttendanceModeSettings;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;

use Tests\TestCase;

/**
 * Tests Feature — Configuration des modes de pointage
 *
 * Endpoints :
 *   GET  /api/v1/smart-attendance/config
 *   PUT  /api/v1/smart-attendance/preferences
 *   PUT  /api/v1/smart-attendance/mode-settings  (principal uniquement)
 */
class AttendanceModeConfigTest extends TestCase
{
    
    use RefreshTenantDatabase;

    private Company $company;
    private Employee $employee;
    private Employee $manager;
    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name'         => 'ModeCorp',
            'slug'         => 'mode-corp',
            'sector'       => 'tech',
            'country'      => 'DZ',
            'city'         => 'Alger',
            'email'        => 'mode@corp.test',
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
            'email'         => 'emp@mode.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'employee',
            'status'        => 'active',
        ]);

        $this->manager = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'manager@mode.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'manager',
            'manager_role'  => 'rh',
            'status'        => 'active',
        ]);

        $this->principal = Employee::query()->create([
            'company_id'    => $this->company->id,
            'schedule_id'   => $schedule->id,
            'email'         => 'principal@mode.test',
            'password_hash' => Hash::make('password'),
            'role'          => 'manager',
            'manager_role'  => 'principal',
            'status'        => 'active',
        ]);
    }

    // ── Tests GET /config ─────────────────────────────────────────────────────

    /**
     * GET /config retourne le mode forcé quand la company l'a défini.
     */
    public function test_get_config_returns_company_forced_mode(): void
    {
        AttendanceModeSettings::query()->create([
            'company_id'   => $this->company->id,
            'forced_mode'  => 'gps_auto',
            'gps_enabled'  => true,
            'latitude'     => 36.7538,
            'longitude'    => 3.0588,
            'radius_meters' => 200,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/smart-attendance/config');

        $response->assertStatus(200);
        $response->assertJsonPath('data.mode', 'gps_auto');
        $response->assertJsonPath('data.can_override', false);
        $response->assertJsonPath('data.gps_enabled', true);
    }

    /**
     * GET /config retourne forced_mode=null quand aucun mode forcé n'est configuré.
     * Le resolver doit retourner le mode 'manual' (défaut).
     */
    public function test_get_config_returns_null_forced_mode_when_not_set(): void
    {
        // Aucun AttendanceModeSettings pour cette company

        Sanctum::actingAs($this->employee);

        $response = $this->getJson('/api/v1/smart-attendance/config');

        $response->assertStatus(200);
        // Sans forced_mode, le mode par défaut est 'manual' et can_override=true
        $response->assertJsonPath('data.mode', 'manual');
        $response->assertJsonPath('data.can_override', true);
    }

    // ── Tests PUT /preferences ────────────────────────────────────────────────

    /**
     * Un employé peut définir sa préférence quand la company n'impose aucun mode.
     */
    public function test_employee_can_set_preference_when_no_forced_mode(): void
    {
        // Paramétrer la company pour autoriser l'override
        AttendanceModeSettings::query()->create([
            'company_id'              => $this->company->id,
            'forced_mode'             => null,
            'gps_enabled'             => true,
            'allow_employee_override' => true,
        ]);

        Sanctum::actingAs($this->employee);

        $response = $this->putJson('/api/v1/smart-attendance/preferences', [
            'preferred_mode'    => 'gps_auto',
            'gps_consent_given' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.preferred_mode', 'gps_auto');
        $response->assertJsonPath('data.gps_consent_given', true);
    }

    /**
     * Quand la company impose un mode forcé, la préférence employé est ignorée
     * dans la réponse de /config (le mode forcé domine).
     */
    public function test_employee_preference_ignored_when_company_has_forced_mode(): void
    {
        // Mode forcé défini
        AttendanceModeSettings::query()->create([
            'company_id'  => $this->company->id,
            'forced_mode' => 'manual',
            'gps_enabled' => false,
        ]);

        Sanctum::actingAs($this->employee);

        // Tenter de changer la préférence → 403 COMPANY_MODE_FORCED
        $response = $this->putJson('/api/v1/smart-attendance/preferences', [
            'preferred_mode'    => 'gps_auto',
            'gps_consent_given' => true,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('code', 'COMPANY_MODE_FORCED');

        // Vérifier que /config retourne bien le mode forcé
        $configResponse = $this->getJson('/api/v1/smart-attendance/config');
        $configResponse->assertJsonPath('data.mode', 'manual');
        $configResponse->assertJsonPath('data.can_override', false);
    }

    // ── Tests PUT /mode-settings ──────────────────────────────────────────────

    /**
     * Un manager avec rôle principal peut mettre à jour la config mode entreprise.
     */
    public function test_principal_can_update_company_mode_settings(): void
    {
        Sanctum::actingAs($this->principal);

        $response = $this->putJson('/api/v1/smart-attendance/mode-settings', [
            'forced_mode'             => 'gps_auto',
            'gps_enabled'             => true,
            'latitude'                => 36.7538,
            'longitude'               => 3.0588,
            'radius_meters'           => 150,
            'allow_employee_override' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.forced_mode', 'gps_auto');
        $response->assertJsonPath('data.gps_enabled', true);
        $response->assertJsonPath('data.radius_meters', 150);

        $this->assertDatabaseHas('attendance_mode_settings', [
            'company_id'  => $this->company->id,
            'forced_mode' => 'gps_auto',
        ]);
    }

    /**
     * Un manager non-principal (rôle rh) ne peut pas modifier la config mode → 403.
     */
    public function test_manager_cannot_update_company_mode_settings(): void
    {
        Sanctum::actingAs($this->manager); // rôle rh, pas principal

        $response = $this->putJson('/api/v1/smart-attendance/mode-settings', [
            'forced_mode' => 'manual',
            'gps_enabled' => false,
        ]);

        $response->assertStatus(403);
    }
}

