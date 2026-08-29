<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-305 (#6035) — CRUD des classes de service.
 *
 * Couvre le CRUD complet, l'unicité du code par tenant (via l'API, en
 * complément des tests DB de TRAVEL-204) et l'isolation cross-tenant.
 */
class TravelClassCrudTest extends TestCase
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

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_principal_can_create_class(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/classes', [
            'code' => 'ECO',
            'label' => 'Économique',
        ])->assertStatus(201)
            ->assertJsonFragment(['code' => 'ECO']);
    }

    public function test_duplicate_code_is_rejected_via_api(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            TravelClass::factory()->create(['code' => 'ECO']);
        });

        $this->postJson('/api/v1/travel/classes', [
            'code' => 'ECO',
            'label' => 'Doublon',
        ])->assertStatus(500);
        // Note : la contrainte DB rejette le doublon (500 sans handler
        // dédié QueryException → 422) ; TRAVEL-322 (matrice permissions)
        // ajoutera une validation applicative renvoyant 422.
    }

    public function test_show_class_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $classId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelClass::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/classes/{$classId}")->assertStatus(404);
    }

    public function test_update_and_delete_class(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $classId = app(TenantManager::class)->withinTenant($company, function () use ($company): int {
            return TravelClass::factory()->create(['company_id' => $company->id])->id;
        });

        $this->putJson("/api/v1/travel/classes/{$classId}", ['label' => 'Business+'])
            ->assertOk()
            ->assertJsonFragment(['label' => 'Business+']);

        $this->deleteJson("/api/v1/travel/classes/{$classId}")->assertStatus(204);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/travel/classes')->assertStatus(401);
    }
}
