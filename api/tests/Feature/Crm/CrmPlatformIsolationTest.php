<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5737 (CRM-PRE) — Frontière API CRM : un tenant ne peut JAMAIS atteindre
 * le CRM commercial plateforme, et le CRM commercial reste intact.
 *
 * Matrice de référence : docs/specifications/CRM_API_MATRICE_TENANT_PLATFORM.md
 * (ADR-CRM-002 : routes/menus/permissions strictement séparés).
 */
class CrmPlatformIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_tenant_manager_cannot_reach_commercial_crm_pipeline(): void
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

        // Surface tenant → CRM commercial : interdit (garde super_admin_api).
        $this->getJson('/api/v1/platform/crm/pipeline')
            ->assertStatus(401);

        // Aucune route tenant ne pointe vers PlatformCrmPipelineController :
        // une route tenant inconnue répond 404, pas une redirection vers
        // la surface platform.
        $this->getJson('/api/v1/crm/platform-pipeline')
            ->assertStatus(404);
    }

    public function test_super_admin_can_still_read_commercial_crm_pipeline(): void
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        $this->actingAs($superAdmin, 'super_admin_api')
            ->getJson('/api/v1/platform/crm/pipeline')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['leads', 'trials', 'active', 'rejected']]);
    }

    public function test_tenant_manager_cannot_reach_platform_company_routes(): void
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

        // Autres surfaces platform : également fermées au tenant.
        $this->getJson('/api/v1/platform/metrics/overview')->assertStatus(401);
        $this->getJson('/api/v1/platform/companies')->assertStatus(401);
    }
}
