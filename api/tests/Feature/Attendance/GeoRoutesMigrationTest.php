<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADR-0016 Phases 3-5 (issues #5354, #5356) — surface API de pointage consolidée.
 *
 * Phase 3 : les routes géo sont exposées sous /api/v1/attendance/* (fichier
 * `Attendance/routes/geo.php`). Phase 5 : les alias /smart-attendance/* sont
 * purgés — le contrat est exclusivement sur /attendance/* (mobile basculé).
 * Ce test verrouille le nouveau contrat ET l'absence de l'ancien chemin.
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

    public function test_employee_can_read_config_on_attendance_path(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/attendance/config')
            ->assertOk()
            ->assertJsonStructure(['data' => ['mode', 'gps_enabled']]);
    }

    public function test_legacy_smart_attendance_alias_config_removed(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/smart-attendance/config')
            ->assertNotFound();
    }

    public function test_manager_can_list_sessions_on_attendance_path(): void
    {
        Sanctum::actingAs($this->managerRh, ['*']);

        $this->getJson('/api/v1/attendance/geo-sessions')
            ->assertOk();
    }

    public function test_legacy_smart_attendance_alias_sessions_removed(): void
    {
        Sanctum::actingAs($this->managerRh, ['*']);

        $this->getJson('/api/v1/smart-attendance/sessions')
            ->assertNotFound();
    }

    public function test_employee_cannot_access_manager_geo_sessions(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->getJson('/api/v1/attendance/geo-sessions')
            ->assertForbidden();
    }

    public function test_geo_event_creation_on_attendance_path(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        $this->postJson('/api/v1/attendance/geo-events', [
            'latitude' => 36.7538,
            'longitude' => 3.0588,
            'event_type' => 'zone_enter',
        ])->assertStatus(201);
    }
}
