<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADR-0016 Phase 3 (issue #5354) — surface API de pointage consolidée.
 *
 * Les routes géo sont ré-exposées sous /api/v1/attendance/* (fichier
 * `Attendance/routes/geo.php`) ; les alias /smart-attendance/* restent
 * enregistrés (double enregistrement, bascule mobile, purge Phase 5).
 * Ce test verrouille le nouveau contrat SANS rupture de l'ancien.
 */
class GeoRoutesMigrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $managerRh;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);
        $this->company = $company;
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        $this->employee = $employee;
        /** @var Employee $managerRh */
        $managerRh = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);
        $this->managerRh = $managerRh;
    }

    public function test_employee_can_read_config_on_new_attendance_path(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/attendance/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['mode', 'gps_enabled']]);
    }

    public function test_alias_smart_attendance_config_still_works(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/smart-attendance/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['mode', 'gps_enabled']]);
    }

    public function test_manager_can_list_sessions_on_new_path(): void
    {
        Sanctum::actingAs($this->managerRh, ['*']);

        $this->getJson('/api/v1/attendance/geo-sessions')
            ->assertOk();
    }

    public function test_manager_alias_sessions_still_works(): void
    {
        Sanctum::actingAs($this->managerRh, ['*']);

        $this->getJson('/api/v1/smart-attendance/sessions')
            ->assertOk();
    }

    public function test_employee_cannot_access_manager_geo_sessions(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/attendance/geo-sessions')
            ->assertForbidden();
    }

    public function test_geo_event_creation_on_new_path(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->postJson('/api/v1/attendance/geo-events', [
            'latitude' => 36.7538,
            'longitude' => 3.0588,
            'event_type' => 'zone_enter',
        ])->assertStatus(201);
    }
}
