<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5861 (MAT-003) — Frontière routes & Policies platform/tenant.
 *
 * Vérifie qu'une route platform n'est pas exposée dans l'espace tenant et
 * inversement, conformément au registre dev-hub/governance/route-owners.json
 * et à la garde dev-hub/tools/check-route-owner-guard.sh.
 *
 * Surfaces sensibles testées : /api/v1/platform/* (guard super_admin_api),
 * /api/v1/metrics (exception platform documentée), /api/v1/absences (tenant).
 */
class RouteOwnerGuardTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_tenant_employee_cannot_reach_platform_surface(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($manager);

        // Surface platform (guard super_admin_api) : un tenant → 401, pas 200.
        $this->getJson('/api/v1/platform/plans')
            ->assertStatus(401);

        // Exception platform documentée hors prefix /platform (route-owners.json) : 401.
        $this->getJson('/api/v1/metrics')
            ->assertStatus(401);
    }

    public function test_platform_route_has_no_tenant_alias(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($manager);

        // Aucune route tenant ne pointe vers la surface platform : 404, pas un
        // fallback vers le contrôleur platform.
        $this->getJson('/api/v1/tenant-plans')
            ->assertStatus(404);

        $this->getJson('/api/v1/platform/plans/extra')
            ->assertStatus(404);
    }

    public function test_tenant_route_without_context_returns_401(): void
    {
        // Aucun token : TenantMiddleware répond 401 UNAUTHENTICATED.
        $this->getJson('/api/v1/absences')
            ->assertStatus(401)
            ->assertJsonPath('error', 'UNAUTHENTICATED');
    }

    public function test_cross_tenant_employee_cannot_read_other_tenant_absence(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        /** @var AbsenceType $absenceType */
        $absenceType = AbsenceType::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Congé payé',
            'code' => 'CP-'.substr($companyB->id, 0, 8),
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        /** @var Absence $absenceB */
        $absenceB = Absence::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'absence_type_id' => $absenceType->id,
            'days_count' => 3,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($managerA);

        // Le manager du tenant A ne doit jamais lire l'absence du tenant B : 404.
        $this->getJson('/api/v1/absences/'.$absenceB->id)
            ->assertStatus(404);
    }
}
