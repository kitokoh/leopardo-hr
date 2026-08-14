<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\Planning\Domain\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #2217 [SECURITY][P1] — RBAC Fleet & Planning.
 *
 * Un employé lambda (role=employee) ne doit PAS pouvoir :
 *  - CRUD véhicules, position GPS live, live-map, maintenance, alertes,
 *    trips (module Fleet — groupe `api.manager`) ;
 *  - créer/modifier/affecter des plannings (ScheduleController store/update/
 *    assignEmployees → isManager()).
 * Le manager conserve un accès complet.
 */
class FleetPlanningRbacTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        return [$company, $manager, $employee];
    }

    public function test_employee_is_forbidden_from_all_fleet_routes(): void
    {
        [$company, , $employee] = $this->actors();
        $vehicle = Vehicle::query()->create([
            'company_id' => $company->id,
            'plate_number' => 'DZ-2217',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $routes = [
            ['post', '/api/v1/vehicles', []],
            ['put', "/api/v1/vehicles/{$vehicle->id}", []],
            ['delete', "/api/v1/vehicles/{$vehicle->id}", []],
            ['post', "/api/v1/vehicles/{$vehicle->id}/assign", ['employee_id' => $employee->id]],
            ['post', "/api/v1/vehicles/{$vehicle->id}/unassign", []],
            ['get', "/api/v1/vehicles/{$vehicle->id}/position", []],
            ['get', '/api/v1/fleet/live-map', []],
            ['get', '/api/v1/fleet/overview', []],
            ['post', '/api/v1/vehicle-maintenance', []],
            ['post', '/api/v1/vehicle-alerts/1/acknowledge', []],
            ['post', '/api/v1/tracking/sync-devices', []],
            ['get', '/api/v1/planning/weekly-optimization', []],
            ['get', '/api/v1/planning/shift-rebalancing', []],
        ];

        foreach ($routes as [$method, $url, $payload]) {
            $response = match ($method) {
                'post' => $this->postJson($url, $payload),
                'put' => $this->putJson($url, $payload),
                'delete' => $this->deleteJson($url),
                default => $this->getJson($url),
            };
            $response->assertForbidden();
        }
    }

    public function test_manager_keeps_fleet_access(): void
    {
        [$company, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/fleet/overview')->assertOk();
        $this->getJson('/api/v1/fleet/live-map')->assertOk();
        $this->getJson('/api/v1/planning/weekly-optimization')->assertOk();
    }

    public function test_employee_is_forbidden_from_schedule_mutations(): void
    {
        [$company, , $employee] = $this->actors();
        Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/schedules', ['name' => 'Hack', 'start_time' => '08:00', 'end_time' => '17:00', 'work_days' => [1]])->assertForbidden();

        $schedule = Schedule::query()->firstOrFail();
        $this->putJson("/api/v1/schedules/{$schedule->id}", ['name' => 'Hack'])->assertForbidden();
        $this->patchJson("/api/v1/schedules/{$schedule->id}", ['name' => 'Hack'])->assertForbidden();
        $this->postJson("/api/v1/schedules/{$schedule->id}/assign-employees", ['employee_ids' => [$employee->id]])->assertForbidden();
    }

    public function test_manager_can_mutate_schedules(): void
    {
        [$company, $manager] = $this->actors();

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/schedules', [
            'name' => 'Standard',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'work_days' => [1, 2, 3, 4, 5],
        ])->assertStatus(201);

        $schedule = Schedule::query()->firstOrFail();
        $this->putJson("/api/v1/schedules/{$schedule->id}", ['name' => 'Équipe A'])->assertOk();
    }
}
