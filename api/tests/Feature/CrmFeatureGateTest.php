<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Infrastructure\Services\CrmFeature;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5742 (CRM PRE) — feature flags et kill switch par tenant.
 *
 * Le CRM client est un opt-in plateforme (flag tenant `crm`, ADR-CRM-004),
 * évalué 100 % côté serveur. Kill switch global prime sur tout. Les canaux
 * d'intégration sont fermés par défaut. Le frontend ne peut jamais
 * s'auto-autoriser (les écritures passent par le PATCH plateforme
 * super-admin uniquement).
 */
class CrmFeatureGateTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function bindTenant(Company $company): void
    {
        $this->app->instance('current_company', $company);
    }

    private function registerGateRoute(): void
    {
        Route::middleware(['crm.enabled'])->get('/_test/crm-gate', fn () => response()->json(['ok' => true]));
    }

    // ── Gate middleware ──────────────────────────────────────────────────────

    public function test_gate_blocks_when_tenant_flag_off(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $this->bindTenant($company);
        $this->registerGateRoute();

        $this->getJson('/_test/crm-gate')
            ->assertStatus(403)
            ->assertJsonPath('error', 'CRM_FEATURE_DISABLED');
    }

    public function test_gate_allows_when_tenant_flag_on(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true, 'crm' => true]]);
        $this->bindTenant($company);
        $this->registerGateRoute();

        $this->getJson('/_test/crm-gate')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_kill_switch_blocks_even_when_tenant_flag_on(): void
    {
        config()->set('crm.kill_switch.enabled', true);
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true, 'crm' => true]]);
        $this->bindTenant($company);
        $this->registerGateRoute();

        $this->getJson('/_test/crm-gate')
            ->assertStatus(403)
            ->assertJsonPath('error', 'CRM_KILL_SWITCH_ACTIVE');
    }

    public function test_gate_fails_closed_without_tenant_context(): void
    {
        $this->registerGateRoute();

        $this->getJson('/_test/crm-gate')
            ->assertStatus(403)
            ->assertJsonPath('error', 'CRM_FEATURE_DISABLED');
    }

    // ── Évaluation serveur ───────────────────────────────────────────────────

    public function test_enabled_is_false_for_null_company(): void
    {
        config()->set('crm.enabled', true);
        $this->assertFalse(CrmFeature::enabled(null));
    }

    public function test_integrations_are_closed_by_default(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true, 'crm' => true]]);

        // Global par défaut false + métadonnées vides → fermé.
        foreach (CrmFeature::INTEGRATIONS as $key) {
            $this->assertFalse(CrmFeature::integrationEnabled($key, $company));
        }
    }

    public function test_integration_requires_global_and_tenant_authorization(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create([
            'features' => ['rh' => true, 'crm' => true],
            'metadata' => ['crm.integrations.whatsapp' => ['enabled' => true]],
        ]);

        // Global off → fermé même si le tenant l'a demandé.
        config()->set('crm.integrations.whatsapp.enabled', false);
        $this->assertFalse(CrmFeature::integrationEnabled('whatsapp', $company));

        // Global on + tenant on → ouvert.
        config()->set('crm.integrations.whatsapp.enabled', true);
        $this->assertTrue(CrmFeature::integrationEnabled('whatsapp', $company));

        // Global on + tenant off → fermé.
        /** @var \App\Core\Tenant\Domain\Models\Company $companyNoOptIn */
        $companyNoOptIn = Company::factory()->create(['features' => ['crm' => true]]);
        $this->assertFalse(CrmFeature::integrationEnabled('whatsapp', $companyNoOptIn));

        // Kill switch prime sur le reste.
        config()->set('crm.kill_switch.enabled', true);
        $this->assertFalse(CrmFeature::integrationEnabled('whatsapp', $company));
    }

    public function test_unknown_integration_key_is_never_enabled(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['crm' => true]]);
        config()->set('crm.integrations.telegram.enabled', true);

        $this->assertFalse(CrmFeature::integrationEnabled('telegram', $company));
    }

    public function test_status_exposes_server_side_truth(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create([
            'features' => ['rh' => true, 'crm' => true],
            'metadata' => ['crm.integrations.email' => ['enabled' => true]],
        ]);
        config()->set('crm.integrations.email.enabled', true);

        $status = CrmFeature::status($company);

        $this->assertTrue($status['enabled']);
        $this->assertFalse($status['kill_switch']);
        $this->assertArrayHasKey('whatsapp', $status['integrations']);
        $this->assertArrayHasKey('email', $status['integrations']);
        $this->assertArrayHasKey('sms', $status['integrations']);
        $this->assertTrue($status['integrations']['email']['enabled']);
        $this->assertFalse($status['integrations']['whatsapp']['enabled']);
    }

    // ── Activation plateforme + anti auto-autorisation frontend ─────────────

    public function test_super_admin_can_activate_crm_and_toggle_is_audited(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['rh' => true, 'crm' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.crm', true);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'crm.feature.enabled',
            'module' => 'platform',
        ]);

        // Désactivation journalisée aussi.
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['rh' => true, 'crm' => false],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'crm.feature.disabled',
        ]);
    }

    public function test_tenant_client_cannot_self_authorize_feature_flags(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        /** @var \App\Core\Auth\Domain\Models\Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        // Le PATCH des flags est réservé au super-admin (auth:super_admin_api) :
        // un client tenant ne peut ni activer ni lire les flags via cette route.
        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['crm' => true],
        ])->assertStatus(401);

        $company->refresh();
        $this->assertFalse($company->hasFeature('crm'));
    }

    public function test_crm_flag_defaults_to_disabled(): void
    {
        /** @var \App\Core\Tenant\Domain\Models\Company $company */
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $this->assertFalse($company->hasFeature('crm'));
    }
}
