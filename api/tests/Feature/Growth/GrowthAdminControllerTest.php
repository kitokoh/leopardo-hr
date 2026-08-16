<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4320 — GrowthAdminController (administration des partenaires,
 * super-admin uniquement) : happy path + RBAC + isolation.
 */
class GrowthAdminControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    private function makeSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
            'password_hash' => Hash::make('admin'),
        ]);
    }

    public function test_super_admin_can_list_partners(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Partner::create([
            'user_id' => $employee->id,
            'referral_code' => 'P-LIST1',
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/growth/partners')
            ->assertOk()
            ->assertJsonStructure(['data' => [], 'meta' => ['total']])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_super_admin_can_update_partner_rate_with_reason(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $partner = Partner::create([
            'user_id' => $employee->id,
            'referral_code' => 'P-RATE1',
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/growth/partners/{$partner->id}/rate", [
            'rate' => 800,
            'reason' => 'Performance exceptionnelle constatée.',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('partners', [
            'id' => $partner->id,
            'default_commission_rate' => 800,
        ]);
    }

    public function test_update_rate_validates_reason_and_range(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $partner = Partner::create([
            'user_id' => $employee->id,
            'referral_code' => 'P-RATE2',
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/growth/partners/{$partner->id}/rate", ['rate' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->patchJson("/api/v1/platform/growth/partners/{$partner->id}/rate", [
            'rate' => 50001,
            'reason' => 'Taux hors plafond.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('rate');
    }

    public function test_tenant_manager_cannot_access_growth_admin(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/platform/growth/partners')->assertStatus(401);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/platform/growth/partners')->assertStatus(401);
    }
}
