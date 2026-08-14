<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1815 — Interface admin des cotisations sociales : CRUD national
 * (platform_admin) + simulation avec/sans plafond légal.
 */
class SocialContributionAdminControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-social-admin@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;
    }

    public function test_platform_admin_crud_national_contributions(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $created = $this->postJson('/api/v1/admin/social-contributions', [
            'country_code' => 'CM',
            'name' => 'CNPS Vieillesse',
            'code' => 'CNPS_CM_VIE',
            'type' => 'employee',
            'rate' => 4.2,
            'cap' => 750000,
            'effective_from' => '2026-01-01',
        ])->assertCreated()->json('data');

        $this->assertNull($created['company_id']);
        $this->assertSame(750000.0, (float) $created['cap']);
        $id = $created['id'];

        $this->getJson('/api/v1/admin/social-contributions?country_code=CM&type=employee')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/admin/social-contributions/{$id}", [
            'rate' => 4.4,
        ])->assertOk()->assertJsonPath('data.rate', 4.4);

        $this->deleteJson("/api/v1/admin/social-contributions/{$id}")->assertStatus(204);
        $this->assertSame(0, SocialContribution::query()->count());
    }

    public function test_tenant_isolation_national_rows_are_null_company(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/social-contributions', [
            'country_code' => 'SN',
            'name' => 'IPRES',
            'code' => 'IPRES_SN_EMP',
            'type' => 'employee',
            'rate' => 5.6,
            'cap' => 432000,
            'effective_from' => '2026-01-01',
        ])->assertCreated();

        // L'index tenant (manager) ne voit PAS les lignes nationales admin.
        $this->assertSame(
            1,
            SocialContribution::query()->where('company_id', null)->count(),
        );
    }

    public function test_simulation_ignore_caps_flag(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        // CM avec plafond 750k : brut 900k → assiette plafonnée.
        $capped = $this->postJson('/api/v1/admin/payroll/simulate', [
            'country_code' => 'CM',
            'gross_salary' => 900000,
        ])->assertOk()->json('data');

        // Sans plafond : assiette = brut complet → cotisation plus élevée.
        $uncapped = $this->postJson('/api/v1/admin/payroll/simulate', [
            'country_code' => 'CM',
            'gross_salary' => 900000,
            'ignore_caps' => true,
        ])->assertOk()->json('data');

        $this->assertNotEquals($capped['social_employee'], $uncapped['social_employee']);

        // Plafonné : 4,2 % × 750 000 = 31 500.
        $this->assertSame(31500.0, (float) $capped['social_employee']);
        // Non plafonné : 4,2 % × 900 000 = 37 800.
        $this->assertSame(37800.0, (float) $uncapped['social_employee']);
    }
}
