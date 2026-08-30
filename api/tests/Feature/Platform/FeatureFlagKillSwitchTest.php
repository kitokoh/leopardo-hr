<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlagAuditRecorder;
use App\Core\Feature\Infrastructure\Services\FeatureFlagRegistry;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * MAT-010 (#5868) — Feature flags et kill switch (BC-01 PLATFORM).
 *
 * Critères d'acceptation :
 *  - activation/désactivation par tenant, solution, provider et version avec
 *    audit ;
 *  - un module peut être stoppé sans suppression de données ;
 *  - l'état est fail-closed (flag inconnu → désactivé) et audité.
 */
class FeatureFlagKillSwitchTest extends TestCase
{
    use RefreshTenantDatabase;

    private function registry(): FeatureFlagRegistry
    {
        return app(FeatureFlagRegistry::class);
    }

    public function test_registry_is_versioned_and_lists_known_flags(): void
    {
        self::assertSame('1.0.0', $this->registry()->version());
        self::assertSame(FeatureFlag::version(), $this->registry()->version());

        $known = $this->registry()->knownKeys();
        foreach (['rh', 'finance', 'cameras', 'muhasebe', 'leo_ai', 'fuel_station'] as $key) {
            self::assertContains($key, $known);
        }
    }

    public function test_unknown_flag_is_fail_closed(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['unknown_module' => true]]);

        self::assertFalse($this->registry()->enabled('unknown_module', $company));
        self::assertFalse($this->registry()->enabled('does_not_exist', null));
        self::assertFalse(FeatureFlag::enabled('does_not_exist', $company));
    }

    public function test_tenant_activation_is_resolved_from_company_features(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['finance' => true, 'leo_ai' => false]]);

        self::assertTrue($this->registry()->enabled('finance', $company));
        self::assertFalse($this->registry()->enabled('leo_ai', $company));
        self::assertTrue($this->registry()->enabled('rh', $company)); // socle actif par défaut

        // Sans company : retombée sur le défaut versionné.
        self::assertTrue($this->registry()->enabled('rh', null));
        self::assertFalse($this->registry()->enabled('finance', null));
    }

    public function test_solution_scope_flag_resolves_per_tenant(): void
    {
        $without = Company::factory()->create(['features' => []]);
        $with = Company::factory()->create(['features' => ['fuel_station' => true]]);

        self::assertFalse($this->registry()->enabled('fuel_station', $without));
        self::assertTrue($this->registry()->enabled('fuel_station', $with));
    }

    public function test_kill_switch_stops_module_without_deleting_data(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['leo_ai' => true]]);

        self::assertTrue($this->registry()->enabled('leo_ai', $company));

        // Kill switch global (config) : le flag est coupé pour TOUS les tenants
        // alors que l'activation stockée est inchangée (aucune suppression).
        config(['feature-flags.kill_switches' => ['leo_ai' => true]]);

        self::assertFalse($this->registry()->enabled('leo_ai', $company));
        $company->refresh();
        self::assertTrue($company->hasFeature('leo_ai'), 'le kill switch ne doit pas supprimer l\'activation stockée');
    }

    public function test_kill_switch_env_override_wins(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['cameras' => true]]);

        config(['feature-flags.kill_switches' => []]);
        putenv('FEATURE_FLAG_KILL_CAMERAS=1');
        self::assertFalse($this->registry()->enabled('cameras', $company));
        putenv('FEATURE_FLAG_KILL_CAMERAS');
        self::assertTrue($this->registry()->enabled('cameras', $company));
    }

    public function test_audit_recorder_writes_before_after_per_flag(): void
    {
        $company = Company::factory()->create(['features' => ['finance' => false]]);

        (new FeatureFlagAuditRecorder)->record(
            companyId: $company->id,
            flagKey: 'finance',
            previousValue: false,
            newValue: true,
            source: 'test',
            actorUserId: 7,
        );

        $row = DB::table('feature_flag_audits')->where('company_id', $company->id)->first();

        self::assertNotNull($row);
        self::assertSame('finance', $row->flag_key);
        self::assertFalse((bool) $row->previous_value);
        self::assertTrue((bool) $row->new_value);
        self::assertSame('test', $row->source);
        self::assertSame(7, (int) $row->actor_user_id);

        // Pas de bascule → pas de ligne (idempotence de l'audit).
        (new FeatureFlagAuditRecorder)->record($company->id, 'finance', true, true, 'test');
        self::assertSame(1, DB::table('feature_flag_audits')->where('company_id', $company->id)->count());
    }

    public function test_super_admin_feature_update_is_audited(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['finance' => false, 'cameras' => false]]);

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();
        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->patchJson("/api/v1/platform/companies/{$company->id}/features", [
            'features' => ['finance' => true, 'cameras' => true, 'rh' => false],
        ])
            ->assertOk()
            ->assertJsonPath('data.features.rh', true)
            ->assertJsonPath('data.features.finance', true)
            ->assertJsonPath('data.features.cameras', true)
            ->assertJsonPath('data.registry_version', '1.0.0');

        $audits = DB::table('feature_flag_audits')
            ->where('company_id', $company->id)
            ->orderBy('flag_key')
            ->get();

        // Deux bascules réelles (finance, cameras) ; rh est verrouillé → pas d'audit.
        self::assertCount(2, $audits);
        self::assertSame(['cameras', 'finance'], $audits->pluck('flag_key')->all());
        self::assertTrue($audits->every(fn ($a) => $a->new_value === true));
    }
}
