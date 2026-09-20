<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-32 HOSPITALITY (HOSP-003, #7945) — équipe par établissement :
 * affectations staff (422 cross-tenant, 409 doublon actif, restore à la
 * ré-affectation, soft delete au retrait) et RBAC ressource-scopé
 * `hospitality_property` progressif (fallback direction tant qu'aucune
 * assignation n'existe ; fail-closed dès la première — pattern #7598/#7599).
 */
class HospitalityPropertyStaffTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $admin;

    private Employee $lambda;

    private Employee $otherAdmin;

    private HospitalityProperty $property;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        $this->admin = $this->manager($this->company, 'principal');
        $this->lambda = $this->employee($this->company);
        $this->otherAdmin = $this->manager($this->otherCompany, 'principal');

        $this->property = $this->createProperty($this->company);
    }

    private function manager(Company $company, string $managerRole): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    private function createProperty(Company $company): HospitalityProperty
    {
        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Hôtel Test',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        return $property;
    }

    private function staffUrl(?HospitalityProperty $property = null): string
    {
        return '/api/v1/hospitality/properties/'.($property ?? $this->property)->getKey().'/staff';
    }

    // ── Cycle de vie des affectations ─────────────────────────────────

    public function test_staff_endpoints_require_authentication(): void
    {
        $this->getJson($this->staffUrl())->assertStatus(401);
    }

    public function test_admin_can_assign_update_remove_and_reassign(): void
    {
        Sanctum::actingAs($this->admin);

        // Affectation → 201 (employé embarqué).
        $created = $this->postJson($this->staffUrl(), [
            'employee_id' => $this->lambda->id,
            'role' => 'Réceptionniste',
        ])->assertStatus(201)->json('data');

        $this->assertSame($this->lambda->id, $created['employee_id']);
        $this->assertSame('Réceptionniste', $created['role']);
        $this->assertSame($this->lambda->id, $created['employee']['id']);

        // Doublon actif → 409 EMPLOYEE_ALREADY_ASSIGNED.
        $this->postJson($this->staffUrl(), ['employee_id' => $this->lambda->id])
            ->assertStatus(409);

        // Mise à jour du rôle descriptif.
        $this->patchJson($this->staffUrl().'/'.$created['id'], ['role' => 'Chef de réception'])
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'Chef de réception');

        // Retrait → 204 (soft delete) ; la liste n'expose plus la ligne.
        $this->deleteJson($this->staffUrl().'/'.$created['id'])->assertStatus(204);
        $this->getJson($this->staffUrl())->assertStatus(200)->assertJsonCount(0, 'data');

        // Ré-affectation → restauration de la MÊME ligne (pas de doublon).
        $restored = $this->postJson($this->staffUrl(), [
            'employee_id' => $this->lambda->id,
            'role' => 'Gouvernante',
        ])->assertStatus(201)->json('data');

        $this->assertSame($created['id'], $restored['id']);
        $this->assertSame('Gouvernante', $restored['role']);
        $this->assertSame(1, HospitalityPropertyStaff::query()->withTrashed()->count());
    }

    public function test_assigning_an_employee_of_another_tenant_is_a_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson($this->staffUrl(), ['employee_id' => $this->otherAdmin->id])
            ->assertStatus(422);
    }

    public function test_cross_tenant_property_and_assignment_are_404(): void
    {
        $foreignProperty = $this->createProperty($this->otherCompany);
        /** @var HospitalityPropertyStaff $foreignAssignment */
        $foreignAssignment = HospitalityPropertyStaff::query()->withoutGlobalScopes()->create([
            'company_id' => $this->otherCompany->id,
            'property_id' => $foreignProperty->getKey(),
            'employee_id' => $this->otherAdmin->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        // Établissement étranger → 404.
        $this->getJson($this->staffUrl($foreignProperty))->assertStatus(404);
        $this->postJson($this->staffUrl($foreignProperty), ['employee_id' => $this->lambda->id])
            ->assertStatus(404);

        // Affectation étrangère (même sous MON établissement) → 404.
        $this->patchJson($this->staffUrl().'/'.$foreignAssignment->getKey(), ['role' => 'x'])
            ->assertStatus(404);
        $this->deleteJson($this->staffUrl().'/'.$foreignAssignment->getKey())
            ->assertStatus(404);
    }

    public function test_assignment_of_another_property_is_a_404(): void
    {
        $otherProperty = $this->createProperty($this->company);
        /** @var HospitalityPropertyStaff $assignment */
        $assignment = HospitalityPropertyStaff::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'property_id' => $otherProperty->getKey(),
            'employee_id' => $this->lambda->id,
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($this->admin);

        // L'affectation appartient à un AUTRE établissement du même tenant.
        $this->patchJson($this->staffUrl().'/'.$assignment->getKey(), ['role' => 'x'])
            ->assertStatus(404);
    }

    // ── RBAC ressource-scopé progressif (#7598/#7599) ─────────────────

    public function test_before_any_assignment_lambda_is_denied_and_direction_manages(): void
    {
        Sanctum::actingAs($this->lambda);
        $this->getJson($this->staffUrl())->assertStatus(403);
        $this->postJson($this->staffUrl(), ['employee_id' => $this->lambda->id])->assertStatus(403);

        $rh = $this->manager($this->company, 'rh');
        Sanctum::actingAs($rh);
        $this->getJson($this->staffUrl())->assertStatus(200);
        $this->postJson($this->staffUrl(), ['employee_id' => $this->lambda->id])->assertStatus(201);
    }

    public function test_after_first_assignment_scoping_is_fail_closed(): void
    {
        $propertyB = $this->createProperty($this->company);

        // Le principal nomme un responsable de site (assignation `manage`
        // sur la propriété A) — via le registre plateforme, comme le ferait
        // l'endpoint RH /employees/{id}/resource-assignments.
        $assignment = new EmployeeResourceAssignment([
            'employee_id' => $this->lambda->id,
            'resource_type' => 'hospitality_property',
            'resource_id' => $this->property->getKey(),
            'access_level' => EmployeeResourceAssignment::LEVEL_MANAGE,
        ]);
        $assignment->company_id = $this->company->id;
        $assignment->save();

        // Le responsable assigné gère SON établissement…
        Sanctum::actingAs($this->lambda);
        $this->getJson($this->staffUrl())->assertStatus(200);
        $this->patchJson('/api/v1/hospitality/properties/'.$this->property->getKey(), ['city' => 'Nantes'])
            ->assertStatus(200);
        $this->postJson($this->staffUrl(), ['employee_id' => $this->admin->id, 'role' => 'Gérant'])
            ->assertStatus(201);

        // …mais PAS un autre établissement du même tenant (fail-closed).
        $this->getJson('/api/v1/hospitality/properties/'.$propertyB->getKey())->assertStatus(403);
        $this->patchJson('/api/v1/hospitality/properties/'.$propertyB->getKey(), ['city' => 'Hack'])
            ->assertStatus(403);
        $this->getJson('/api/v1/hospitality/properties/'.$propertyB->getKey().'/staff')->assertStatus(403);

        // Création d'établissement = company-wide → réservée au principal.
        $this->postJson('/api/v1/hospitality/properties', [
            'name' => 'Hack', 'code' => 'HCK-1', 'type' => 'hotel', 'country' => 'FR', 'currency' => 'EUR',
        ])->assertStatus(403);
    }

    public function test_after_first_assignment_rh_falls_back_to_read_only(): void
    {
        $assignment = new EmployeeResourceAssignment([
            'employee_id' => $this->lambda->id,
            'resource_type' => 'hospitality_property',
            'resource_id' => $this->property->getKey(),
            'access_level' => EmployeeResourceAssignment::LEVEL_VIEW,
        ]);
        $assignment->company_id = $this->company->id;
        $assignment->save();

        $rh = $this->manager($this->company, 'rh');
        Sanctum::actingAs($rh);

        // Lecture toujours permise (conception §3.3 : rh = lecture seule)…
        $this->getJson($this->staffUrl())->assertStatus(200);
        $this->getJson('/api/v1/hospitality/properties/'.$this->property->getKey())->assertStatus(200);

        // …mais la gestion est désormais refusée (scoping actif).
        $this->patchJson('/api/v1/hospitality/properties/'.$this->property->getKey(), ['city' => 'Hack'])
            ->assertStatus(403);
        $this->postJson($this->staffUrl(), ['employee_id' => $this->admin->id])->assertStatus(403);

        // Le principal conserve tous les droits.
        Sanctum::actingAs($this->admin);
        $this->postJson($this->staffUrl(), ['employee_id' => $this->admin->id, 'role' => 'Direction'])
            ->assertStatus(201);
    }
}
