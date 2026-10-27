<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
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
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        return $superAdmin;
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

    /**
     * #8523 (gotcha 7) — la garde `platform.permission` doit RÉELLEMENT
     * s'appliquer au groupe `/platform/growth`.
     *
     * Avant ce correctif, être authentifié sur le guard `super_admin_api`
     * suffisait : un compte plateforme de rôle `support` ou `marketing` — qui
     * ne porte pas `billing.*` — pouvait lire les partenaires, changer un taux
     * de commission et marquer un versement comme payé.
     */
    public function test_platform_role_without_billing_permission_is_forbidden(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $superAdmin->forceFill(['platform_role' => 'support'])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/growth/partners')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PLATFORM_PERMISSION_REQUIRED');

        $this->getJson('/api/v1/platform/growth/payouts')
            ->assertStatus(403)
            ->assertJsonPath('error', 'PLATFORM_PERMISSION_REQUIRED');
    }

    /**
     * #8523 (gotcha 7) — le rôle `finance` porte `billing.view` ET
     * `billing.manage` : la garde ne doit pas lui fermer la porte.
     */
    public function test_finance_platform_role_keeps_billing_access(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $superAdmin->forceFill(['platform_role' => 'finance'])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/growth/partners')->assertOk();
    }

    /**
     * #8523 (gotcha 7) — la décision DOIT laisser une trace d'audit attribuée
     * au compte plateforme appelant.
     *
     * Avant le correctif, `Auth::id()` interrogeait le guard par défaut
     * (`sanctum`, donc `users`) alors que la requête est authentifiée sur
     * `super_admin_api` : l'identifiant était `null`, le service était appelé
     * ZÉRO fois et l'endpoint répondait quand même `200 {"success":true}`.
     * Aucun audit n'était écrit — la décision financière était invisible.
     */
    public function test_rate_change_is_audited_against_the_platform_admin(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $partner = Partner::create([
            'user_id' => $employee->id,
            'referral_code' => 'P-AUDIT1',
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/growth/partners/{$partner->id}/rate", [
            'rate' => 750,
            'reason' => 'Performance exceptionnelle constatée.',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('partner_audit_logs', [
            'admin_id' => $superAdmin->id,
            'event' => 'rate_adjustment',
            'auditable_id' => (string) $partner->id,
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/platform/growth/partners')->assertStatus(401);
    }
}
