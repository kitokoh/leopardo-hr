<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Feature\Domain\Models\PlatformFeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-010 (#5868) — Feature flags et kill switch (BC-01 PLATFORM).
 *
 * Couvre la matrice de résolution des kill switches plateforme :
 * dimensions global/module/tenant/solution/provider/version, fail-closed,
 * override par la dimension la plus spécifique, audit append-only, console
 * et API super-admin.
 */
class FeatureKillSwitchTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(bool $financeEnabled = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        $company->setFeature('finance', $financeEnabled);
        $company->save();

        return $company->fresh();
    }

    public function test_global_kill_switch_disables_module_even_when_enabled_for_tenant(): void
    {
        $company = $this->makeCompany();
        self::assertTrue(FeatureFlag::enabled('finance', $company));

        FeatureFlag::setFlag('finance', 'global', null, true, 'incident', 'ops@kitokoh.com');

        self::assertTrue(FeatureFlag::isKilled('finance'));
        self::assertFalse(FeatureFlag::resolve('finance', $company));
    }

    public function test_tenant_scoped_kill_switch_only_affects_target_tenant(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        FeatureFlag::setFlag('finance', 'tenant', (string) $companyA->id, true, 'tenant A en dégradé', 'ops');

        self::assertTrue(FeatureFlag::isKilled('finance', ['tenant_id' => (string) $companyA->id]));
        self::assertFalse(FeatureFlag::isKilled('finance', ['tenant_id' => (string) $companyB->id]));
        self::assertFalse(FeatureFlag::resolve('finance', $companyA));
        self::assertTrue(FeatureFlag::resolve('finance', $companyB));
    }

    public function test_solution_provider_and_version_dimensions_match_context(): void
    {
        $company = $this->makeCompany();

        FeatureFlag::setFlag('leo_ai', 'solution', 'fuel_station', true, 'sécurité solution', 'ops');
        self::assertTrue(FeatureFlag::isKilled('leo_ai', ['solution' => 'fuel_station']));
        self::assertFalse(FeatureFlag::isKilled('leo_ai', ['solution' => 'edu']));

        FeatureFlag::setFlag('leo_ai', 'provider', 'openai', true, 'provider en panne', 'ops');
        self::assertTrue(FeatureFlag::isKilled('leo_ai', ['provider' => 'openai']));
        self::assertFalse(FeatureFlag::isKilled('leo_ai', ['provider' => 'claude']));

        FeatureFlag::setFlag('leo_ai', 'version', '2.3.0', true, 'régression mobile', 'ops');
        self::assertTrue(FeatureFlag::isKilled('leo_ai', ['version' => '2.3.0']));
        self::assertFalse(FeatureFlag::isKilled('leo_ai', ['version' => '2.4.0']));

        // Résolution complète : module activé côté tenant mais tué par le kill switch.
        $company->setFeature('leo_ai', true);
        $company->save();
        self::assertFalse(FeatureFlag::resolve('leo_ai', $company->fresh(), ['version' => '2.3.0']));
    }

    public function test_most_specific_dimension_wins(): void
    {
        $company = $this->makeCompany();

        // Kill global… mais ré-autorisation explicite pour la version 2.4.0.
        FeatureFlag::setFlag('finance', 'global', null, true, 'incident global', 'ops');
        FeatureFlag::setFlag('finance', 'version', '2.4.0', false, 'hotfix 2.4.0 validé', 'ops');

        self::assertTrue(FeatureFlag::isKilled('finance', ['version' => '2.3.0']));
        self::assertFalse(FeatureFlag::isKilled('finance', ['version' => '2.4.0']));
        self::assertFalse(FeatureFlag::resolve('finance', $company, ['version' => '2.3.0']));
        self::assertTrue(FeatureFlag::resolve('finance', $company, ['version' => '2.4.0']));
    }

    public function test_module_dimension_kills_all_flags_of_module(): void
    {
        $company = $this->makeCompany();

        FeatureFlag::setFlag('finance', 'module', null, true, 'arrêt module finance', 'ops');

        self::assertTrue(FeatureFlag::isKilled('finance', ['module' => 'finance']));
        self::assertTrue(FeatureFlag::isKilled('finance'));
        self::assertFalse(FeatureFlag::resolve('finance', $company));
    }

    public function test_unknown_flag_remains_fail_closed(): void
    {
        $company = $this->makeCompany();

        self::assertFalse(FeatureFlag::enabled('does_not_exist', $company));
        self::assertFalse(FeatureFlag::resolve('does_not_exist', $company));
        self::assertFalse(FeatureFlag::isKilled('does_not_exist'));
    }

    public function test_history_is_append_only_and_skipped_on_noop(): void
    {
        FeatureFlag::setFlag('finance', 'global', null, true, 'raison 1', 'ops@kitokoh.com');

        /** @var PlatformFeatureFlag $flag */
        $flag = PlatformFeatureFlag::query()->where('flag_key', 'finance')->firstOrFail();
        self::assertCount(1, $flag->history);
        self::assertNull($flag->history[0]['from']);
        self::assertTrue($flag->history[0]['to']);
        self::assertSame('ops@kitokoh.com', $flag->history[0]['by']);

        // Même valeur → aucun ajout d'historique (idempotence d'écriture).
        FeatureFlag::setFlag('finance', 'global', null, true, 'raison 1', 'ops@kitokoh.com');
        $flag->refresh();
        self::assertCount(1, $flag->history);

        // Changement → nouvelle entrée avec avant/après.
        FeatureFlag::setFlag('finance', 'global', null, false, 'retour nominal', 'ops@kitokoh.com');
        $flag->refresh();
        self::assertCount(2, $flag->history);
        self::assertTrue($flag->history[1]['from']);
        self::assertFalse($flag->history[1]['to']);
    }

    public function test_console_command_sets_and_lists_flags(): void
    {
        $this->artisan('platform:feature-flag', [
            'flag' => 'cameras',
            '--on' => true,
            '--reason' => 'pilote caméras',
            '--actor' => 'cli-test',
        ])->assertSuccessful();

        self::assertTrue(FeatureFlag::isKilled('cameras'));

        $this->artisan('platform:feature-flag', [
            'flag' => 'cameras',
            '--off' => true,
        ])->assertSuccessful();

        self::assertFalse(FeatureFlag::isKilled('cameras'));

        $this->artisan('platform:feature-flag', ['--list' => true])->assertSuccessful();
    }

    public function test_console_command_requires_value_for_non_global_dimension(): void
    {
        $this->artisan('platform:feature-flag', [
            'flag' => 'finance',
            '--dimension' => 'tenant',
            '--off' => true,
        ])->assertFailed();
    }

    public function test_api_endpoint_requires_super_admin_auth(): void
    {
        $this->getJson('/api/v1/platform/feature-flags')->assertStatus(401);

        $this->postJson('/api/v1/platform/feature-flags', [
            'flag_key' => 'finance',
            'dimension' => 'global',
            'enabled' => true,
        ])->assertStatus(401);
    }

    public function test_api_endpoint_sets_flag_with_audit(): void
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = new SuperAdmin([
            'name' => 'Super Admin Test',
            'email' => 'sa-feature-flag@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => bcrypt('secret123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/platform/feature-flags', [
            'flag_key' => 'finance',
            'dimension' => 'global',
            'enabled' => true,
            'reason' => 'incident prod',
        ])->assertStatus(201)
            ->assertJsonPath('data.flag_key', 'finance')
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.changed_by', 'sa-feature-flag@leopardo-rh.com');

        $this->getJson('/api/v1/platform/feature-flags')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Validation des dimensions inconnues.
        $this->postJson('/api/v1/platform/feature-flags', [
            'flag_key' => 'finance',
            'dimension' => 'bogus',
            'enabled' => true,
        ])->assertStatus(422);
    }
}
