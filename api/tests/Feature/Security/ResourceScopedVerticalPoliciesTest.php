<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Fleet\Domain\Models\Vehicle;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Policies\TravelOfficePolicy;
use App\Policies\VehiclePolicy;
use Tests\Support\AssignsResourceAccess;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7600 (R3 de l'épique #7597) — miroir de `ResourceScopedRbacTest`
 * pour la généralisation du patron ressource-scopé aux verticales :
 *
 *  - Fleet (`VehiclePolicy`) : « tout manager voit tout » ne survit pas à la
 *    première assignation `vehicle` ;
 *  - TravelAgency (`TravelOfficePolicy`) : le motif mort
 *    `hasManagerRole(..., 'manager')` est remplacé par l'assignation
 *    `travel_office` ;
 *  - progressivité (#7598) : comportement historique conservé tant que le
 *    type n'est pas assigné dans l'entreprise.
 */
class ResourceScopedVerticalPoliciesTest extends TestCase
{
    use AssignsResourceAccess;
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $migration = require database_path('migrations/tenant/2026_09_17_000001_7598_create_employee_resource_assignments_table.php');
        $migration->up();

        $this->company = Company::query()->create([
            'name' => 'Transports Exemple',
            'slug' => 'transports-exemple',
            'sector' => 'transport',
            'country' => 'SN',
            'city' => 'Dakar',
            'email' => 'contact@transports.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeVehicle(string $plate): Vehicle
    {
        $vehicle = new Vehicle;
        $vehicle->forceFill([
            'company_id' => $this->company->id,
            'plate_number' => $plate,
            'status' => 'active',
        ])->save();

        return $vehicle;
    }

    private function makeOffice(): TravelOffice
    {
        // Le schéma MVP ne porte que les colonnes minimales de
        // `travel_offices` (id, company_id) — suffisant pour la policy.
        $office = new TravelOffice;
        $office->forceFill([
            'company_id' => $this->company->id,
        ])->save();

        return $office;
    }

    public function test_fleet_behaviour_is_unchanged_before_any_assignment(): void
    {
        $vehicle = $this->makeVehicle('DK-100-AA');
        $policy = new VehiclePolicy;

        // Historique : tout manager voit/gère, l'employé non.
        $this->assertTrue($policy->view($this->manager, $vehicle));
        $this->assertTrue($policy->update($this->manager, $vehicle));
        $this->assertFalse($policy->view($this->employee, $vehicle));
    }

    public function test_fleet_becomes_fail_closed_once_a_vehicle_is_assigned(): void
    {
        $mine = $this->makeVehicle('DK-200-AA');
        $other = $this->makeVehicle('DK-300-AA');
        $policy = new VehiclePolicy;

        $this->assignResourceAccess($this->employee, 'vehicle', (int) $mine->id, 'manage', $this->principal);

        // L'assigné gère SON véhicule, pas l'autre.
        $this->assertTrue($policy->view($this->employee, $mine));
        $this->assertTrue($policy->update($this->employee, $mine));
        $this->assertTrue($policy->assignDriver($this->employee, $mine));
        $this->assertFalse($policy->view($this->employee, $other));
        $this->assertFalse($policy->update($this->employee, $other));

        // Le principal garde tout ; la lecture `rh` reste ouverte, mais la
        // gestion `rh` sans assignation tombe (rh = lecture, conception §3.3).
        $this->assertTrue($policy->view($this->principal, $other));
        $this->assertTrue($policy->update($this->principal, $other));
        $this->assertTrue($policy->view($this->manager, $other));
        $this->assertFalse($policy->update($this->manager, $other));
    }

    public function test_travel_office_scoping_replaces_the_dead_manager_role(): void
    {
        $mine = $this->makeOffice();
        $other = $this->makeOffice();
        $policy = new TravelOfficePolicy;

        // La valeur morte 'manager' ne donne rien : seul principal/rh écrit
        // avant la première assignation.
        $deadRole = new Employee;
        $deadRole->forceFill([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
        ]);
        $this->assertFalse($policy->create($deadRole));
        $this->assertFalse($policy->update($deadRole, $mine));

        $this->assignResourceAccess($this->employee, 'travel_office', (int) $mine->id, 'manage', $this->principal);

        $this->assertTrue($policy->view($this->employee, $mine));
        $this->assertTrue($policy->update($this->employee, $mine));
        $this->assertFalse($policy->update($this->employee, $other));
        $this->assertFalse($policy->view($this->employee, $other));

        // Créer un NOUVEAU bureau reste un acte company-wide (principal).
        $this->assertFalse($policy->create($this->employee));
        $this->assertTrue($policy->create($this->principal));
    }
}
