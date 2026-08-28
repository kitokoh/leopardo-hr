<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Feature\Domain\Models\FeatureKillSwitch;
use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MAT-010 (#5868) — feature flags & kill switch.
 *
 * Couvre le kill switch global : fail-closed dans `Company::hasFeature`
 * (point d'intégration unique des gates modules), idempotence, API
 * super-admin (401 pour l'espace tenant), persistance + audit.
 *
 * Harness : schéma `public` migré (feature_kill_switches + companies +
 * super_admins) — aucune dépendance tenant.
 */
class FeatureKillSwitchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET search_path TO public,shared_tenants');

        $this->artisan('migrate:fresh', [
            '--path' => 'database/migrations/public',
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    public function test_kill_switch_makes_a_feature_fail_closed(): void
    {
        $company = Company::factory()->create(['features' => ['cameras' => true]]);

        $this->assertTrue($company->fresh()->hasFeature('cameras'));

        app(FeatureKillSwitchService::class)->kill('cameras', 'Incident en cours');

        $this->assertFalse($company->fresh()->hasFeature('cameras'));
        $this->assertTrue(app(FeatureKillSwitchService::class)->isKilled('cameras'));
    }

    public function test_kill_switch_only_affects_the_targeted_feature(): void
    {
        $company = Company::factory()->create([
            'features' => ['cameras' => true, 'finance' => true],
        ]);

        app(FeatureKillSwitchService::class)->kill('cameras', 'Maintenance');

        $this->assertFalse($company->fresh()->hasFeature('cameras'));
        $this->assertTrue($company->fresh()->hasFeature('finance'));
    }

    public function test_kill_and_revive_are_idempotent(): void
    {
        $service = app(FeatureKillSwitchService::class);

        $service->kill('leo_ai', 'Raison');
        $service->kill('leo_ai', 'Autre raison'); // pas d'erreur ni de double audit

        $this->assertSame(1, FeatureKillSwitch::query()->where('feature_key', 'leo_ai')->count());

        $service->revive('leo_ai');
        $service->revive('leo_ai'); // déjà inactif : no-op

        $this->assertFalse($service->isKilled('leo_ai'));
    }

    public function test_kill_switch_is_graceful_when_table_is_missing(): void
    {
        // Harness public migré SANS feature_kill_switches (simulation) : la
        // résolution retombe sur l'ancien comportement (aucun kill actif).
        DB::statement('DROP TABLE IF EXISTS feature_kill_switches');

        $company = Company::factory()->create(['features' => ['finance' => true]]);

        $this->assertTrue($company->fresh()->hasFeature('finance'));
    }

    public function test_platform_api_requires_super_admin(): void
    {
        $this->getJson('/api/v1/platform/feature-kill-switches')->assertUnauthorized();

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/platform/feature-kill-switches')->assertUnauthorized();
        $this->postJson('/api/v1/platform/feature-kill-switches', [
            'feature_key' => 'cameras',
            'reason' => 'test',
        ])->assertUnauthorized();
    }

    public function test_super_admin_can_activate_and_deactivate_a_kill_switch(): void
    {
        Sanctum::actingAs(
            new SuperAdmin(['id' => 1, 'name' => 'Ops', 'email' => 'ops@leopardo.test']),
            ['*'],
            'super_admin_api'
        );

        $this->postJson('/api/v1/platform/feature-kill-switches', [
            'feature_key' => 'leo_ai',
            'reason' => 'Incident LLM',
        ])->assertOk();

        $this->assertTrue(app(FeatureKillSwitchService::class)->isKilled('leo_ai'));

        $row = FeatureKillSwitch::query()->where('feature_key', 'leo_ai')->first();

        if (! $row instanceof FeatureKillSwitch) {
            $this->fail('Ligne kill switch absente en base');

            return;
        }

        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('Incident LLM', $row->reason);
        $this->assertSame('1', $row->toggled_by);

        $this->deleteJson('/api/v1/platform/feature-kill-switches/leo_ai')->assertOk();

        $this->assertFalse(app(FeatureKillSwitchService::class)->isKilled('leo_ai'));
        $this->assertFalse((bool) $row->fresh()->is_active);
    }

    public function test_activate_requires_feature_key(): void
    {
        Sanctum::actingAs(
            new SuperAdmin(['id' => 1, 'name' => 'Ops', 'email' => 'ops@leopardo.test']),
            ['*'],
            'super_admin_api'
        );

        $this->postJson('/api/v1/platform/feature-kill-switches', ['reason' => 'sans cle'])
            ->assertUnprocessable();
    }
}
