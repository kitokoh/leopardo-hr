<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelOffice;
use App\Modules\TravelAgency\Domain\Models\TravelStaffAssignment;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7638 (TRAVEL-STAFF) — pont RH employee_id + affectations d'équipage.
 *
 * Couvre le CRUD /travel/staff-assignments (affectation bureau ET voyage),
 * la révocation, le RBAC (employé simple → 403), l'isolation cross-tenant
 * (404 sur la ressource, 422 sur un employee_id/scope étranger) et
 * l'intégration au manifeste de voyage (clé `crew`).
 */
class TravelStaffAssignmentTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function selfService(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        return $company;
    }

    public function test_principal_can_assign_hr_employee_to_office(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        $officeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $staff->id,
            'role' => 'agent',
            'office_id' => $officeId,
        ])->assertStatus(201)
            ->assertJsonFragment([
                'employee_id' => $staff->id,
                'role' => 'agent',
                'office_id' => $officeId,
                'status' => 'active',
            ]);
    }

    public function test_principal_can_assign_driver_to_trip_and_revoke(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $driver */
        $driver = Employee::factory()->create(['company_id' => $company->id]);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $created = $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $driver->id,
            'role' => 'driver',
            'trip_id' => $tripId,
        ])->assertStatus(201);

        $assignmentId = $created->json('data.id');
        self::assertIsInt($assignmentId);

        $this->postJson("/api/v1/travel/staff-assignments/{$assignmentId}/revoke")
            ->assertOk()
            ->assertJsonFragment(['status' => 'revoked']);

        // Idempotente : re-révoquer ne change rien et répond 200.
        $this->postJson("/api/v1/travel/staff-assignments/{$assignmentId}/revoke")
            ->assertOk()
            ->assertJsonFragment(['status' => 'revoked']);
    }

    public function test_duplicate_active_assignment_is_rejected_with_409(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        $officeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $payload = ['employee_id' => $staff->id, 'role' => 'controller', 'office_id' => $officeId];

        $this->postJson('/api/v1/travel/staff-assignments', $payload)->assertStatus(201);
        $this->postJson('/api/v1/travel/staff-assignments', $payload)->assertStatus(409);
    }

    public function test_scope_requires_exactly_one_of_office_or_trip(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        [$officeId, $tripId] = app(TenantManager::class)->withinTenant($company, function (): array {
            return [TravelOffice::factory()->create()->id, TravelTrip::factory()->create()->id];
        });

        // Aucun scope → 422.
        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $staff->id,
            'role' => 'agent',
        ])->assertStatus(422);

        // Les deux scopes → 422 (prohibits).
        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $staff->id,
            'role' => 'agent',
            'office_id' => $officeId,
            'trip_id' => $tripId,
        ])->assertStatus(422);
    }

    public function test_employee_of_another_tenant_is_rejected(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $foreignStaff */
        $foreignStaff = Employee::factory()->create(['company_id' => $companyB->id]);

        $this->principal($companyA);

        $officeId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $foreignStaff->id,
            'role' => 'agent',
            'office_id' => $officeId,
        ])->assertStatus(422);
    }

    public function test_office_of_another_tenant_is_rejected(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $foreignOfficeId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $principal = $this->principal($companyA);

        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $principal->id,
            'role' => 'agent',
            'office_id' => $foreignOfficeId,
        ])->assertStatus(422);
    }

    public function test_assignment_of_another_tenant_returns_404(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $foreignStaff */
        $foreignStaff = Employee::factory()->create(['company_id' => $companyB->id]);

        $assignmentId = app(TenantManager::class)->withinTenant($companyB, function () use ($foreignStaff): int {
            return TravelStaffAssignment::factory()->create([
                'employee_id' => $foreignStaff->id,
            ])->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/staff-assignments/{$assignmentId}")->assertStatus(404);
        $this->postJson("/api/v1/travel/staff-assignments/{$assignmentId}/revoke")->assertStatus(404);
        $this->deleteJson("/api/v1/travel/staff-assignments/{$assignmentId}")->assertStatus(404);
    }

    public function test_index_is_isolated_per_tenant_and_filterable(): void
    {
        $companyA = $this->company();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        /** @var Employee $foreignStaff */
        $foreignStaff = Employee::factory()->create(['company_id' => $companyB->id]);
        app(TenantManager::class)->withinTenant($companyB, function () use ($foreignStaff): void {
            TravelStaffAssignment::factory()->create(['employee_id' => $foreignStaff->id]);
        });

        $this->principal($companyA);

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $companyA->id]);
        $officeId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return TravelOffice::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $staff->id,
            'role' => 'agent',
            'office_id' => $officeId,
        ])->assertStatus(201);

        // Jamais l'affectation du tenant B.
        $this->getJson('/api/v1/travel/staff-assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['employee_id' => $staff->id]);

        $this->getJson('/api/v1/travel/staff-assignments?status=revoked')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/travel/staff-assignments?office_id={$officeId}&role=agent")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_self_service_employee_cannot_manage_assignments(): void
    {
        $company = $this->company();

        /** @var Employee $staff */
        $staff = Employee::factory()->create(['company_id' => $company->id]);

        [$officeId, $assignmentId] = app(TenantManager::class)->withinTenant($company, function () use ($staff): array {
            $office = TravelOffice::factory()->create();
            $assignment = TravelStaffAssignment::factory()->create([
                'employee_id' => $staff->id,
                'office_id' => $office->id,
            ]);

            return [$office->id, $assignment->id];
        });

        $this->selfService($company);

        $this->postJson('/api/v1/travel/staff-assignments', [
            'employee_id' => $staff->id,
            'role' => 'driver',
            'office_id' => $officeId,
        ])->assertStatus(403);

        $this->putJson("/api/v1/travel/staff-assignments/{$assignmentId}", ['role' => 'controller'])
            ->assertStatus(403);
        $this->postJson("/api/v1/travel/staff-assignments/{$assignmentId}/revoke")->assertStatus(403);
        $this->deleteJson("/api/v1/travel/staff-assignments/{$assignmentId}")->assertStatus(403);

        // La lecture reste ouverte à tout employé du tenant.
        $this->getJson('/api/v1/travel/staff-assignments')->assertOk();
        $this->getJson("/api/v1/travel/staff-assignments/{$assignmentId}")->assertOk();
    }

    public function test_update_changes_business_role(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);

        $assignmentId = app(TenantManager::class)->withinTenant($company, function () use ($principal): int {
            return TravelStaffAssignment::factory()->create([
                'employee_id' => $principal->id,
            ])->id;
        });

        $this->putJson("/api/v1/travel/staff-assignments/{$assignmentId}", ['role' => 'office_manager'])
            ->assertOk()
            ->assertJsonFragment(['role' => 'office_manager']);

        $this->putJson("/api/v1/travel/staff-assignments/{$assignmentId}", ['role' => 'pilote'])
            ->assertStatus(422);
    }

    public function test_trip_manifest_lists_active_crew_with_hr_names(): void
    {
        $company = $this->company();
        $this->principal($company);

        /** @var Employee $driver */
        $driver = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Aliou',
            'last_name' => 'Diallo',
        ]);

        /** @var Employee $controller */
        $controller = Employee::factory()->create(['company_id' => $company->id]);

        $tripId = app(TenantManager::class)->withinTenant($company, function () use ($driver, $controller): int {
            $trip = TravelTrip::factory()->create();

            TravelStaffAssignment::factory()->forTrip()->create([
                'employee_id' => $driver->id,
                'trip_id' => $trip->id,
            ]);

            // Une affectation RÉVOQUÉE ne doit pas apparaître dans l'équipage.
            TravelStaffAssignment::factory()->forTrip()->revoked()->create([
                'employee_id' => $controller->id,
                'role' => 'controller',
                'trip_id' => $trip->id,
            ]);

            return $trip->id;
        });

        $response = $this->getJson("/api/v1/travel/trips/{$tripId}/manifest")->assertOk();

        $crew = $response->json('crew');
        self::assertIsArray($crew);
        self::assertCount(1, $crew, 'seul l\'équipage ACTIF apparaît au manifeste');

        /** @var array<string, mixed> $member */
        $member = $crew[0];
        self::assertSame('driver', $member['role']);
        self::assertSame($driver->id, $member['employee_id']);
        self::assertSame('Aliou Diallo', $member['full_name']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/staff-assignments')->assertStatus(401);
    }
}
