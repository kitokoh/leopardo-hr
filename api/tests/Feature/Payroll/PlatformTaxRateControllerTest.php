<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\SocialContribution;
use App\Modules\Payroll\Domain\Models\TaxSlab;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADMIN-PAIE (#1813) — surface platform_admin du workflow de validation des
 * taux légaux : listing cross-tenant, approbation/rejet des barèmes et
 * cotisations en attente, audit trail immuable. RBAC : super_admin_api
 * uniquement.
 */
class PlatformTaxRateControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;
        $this->superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin-2@example.com',
            'password_hash' => Hash::make('password123'),
        ]);
    }

    public function test_pending_list_returns_slabs_and_contributions(): void
    {
        TaxSlab::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche en attente',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 23,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_PENDING_VALIDATION,
        ]);
        SocialContribution::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'CNAS en attente',
            'code' => 'CNAS_TEST_2',
            'type' => 'employee',
            'rate' => 9,
            'effective_from' => '2026-01-01',
            'status' => SocialContribution::STATUS_PENDING_VALIDATION,
        ]);

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/payroll/tax-rates/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.tax_slabs')
            ->assertJsonCount(1, 'data.social_contributions')
            ->assertJsonPath('data.tax_slabs.0.status', TaxSlab::STATUS_PENDING_VALIDATION);
    }

    public function test_platform_admin_can_approve_and_reject_contribution(): void
    {
        $contribution = SocialContribution::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'CNAS',
            'code' => 'CNAS_TEST_3',
            'type' => 'employee',
            'rate' => 9,
            'effective_from' => '2026-01-01',
            'status' => SocialContribution::STATUS_PENDING_VALIDATION,
        ]);

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson("/api/v1/platform/payroll/tax-rates/social-contributions/{$contribution->id}/reject", [
            'reason' => 'Taux non conforme au barème officiel',
        ])->assertOk()->assertJsonPath('data.status', SocialContribution::STATUS_DRAFT);

        $this->putJson("/api/v1/platform/payroll/tax-rates/social-contributions/{$contribution->id}/approve")
            ->assertUnprocessable(); // le rejet l'a repassée en draft, plus approuvable directement
    }

    public function test_platform_history_is_readable(): void
    {
        $slab = TaxSlab::query()->create([
            'company_id' => $this->company->id,
            'country_code' => 'DZ',
            'name' => 'Tranche IRG',
            'min_amount' => 0,
            'max_amount' => 50000,
            'rate' => 23,
            'effective_from' => '2026-01-01',
            'status' => TaxSlab::STATUS_DRAFT,
        ]);

        // La transition métier (soumission) écrit l'entrée d'audit.
        /** @var Employee $submitter */
        $submitter = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        app(\App\Modules\Payroll\Application\Services\TaxRateValidationWorkflow::class)
            ->submit($slab, $submitter);

        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/payroll/tax-rates/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'submitted');
    }

    public function test_tenant_users_cannot_access_platform_routes(): void
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/platform/payroll/tax-rates/pending')->assertUnauthorized();
        $this->getJson('/api/v1/platform/payroll/tax-rates/history')->assertUnauthorized();
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/v1/platform/payroll/tax-rates/pending')->assertUnauthorized();
    }
}
