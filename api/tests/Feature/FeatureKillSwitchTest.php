<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Feature\Domain\Models\FeatureKillSwitch;
use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * MAT-010 (#5868) — feature flags & kill switch.
 *
 * Couvre le kill switch global : fail-closed dans `Company::hasFeature`
 * (point d'intégration unique des gates modules), idempotence, API
 * super-admin (401 pour l'espace tenant), persistance + audit.
 *
 * Harness : fixture `CreatesMvpSchema` (schéma partagé + companies) + table
 * `feature_kill_switches` créée à la volée — aucun `migrate:fresh` (qui
 * purgerait le schéma tenant partagé et casserait les autres tests du run).
 */
class FeatureKillSwitchTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        if (! Schema::hasTable('feature_kill_switches')) {
            Schema::create('feature_kill_switches', function (Blueprint $table): void {
                $table->id();
                $table->string('feature_key', 64)->unique();
                $table->boolean('is_active')->default(false);
                $table->string('reason', 500)->nullable();
                $table->string('toggled_by', 191)->nullable();
                $table->timestampTz('toggled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_kill_switch_makes_a_feature_fail_closed(): void
    {
        $company = Company::factory()->create(['features' => ['cameras' => true]]);

        $this->assertTrue($company->refresh()->hasFeature('cameras'));

        app(FeatureKillSwitchService::class)->kill('cameras', 'Incident en cours');

        $this->assertFalse($company->refresh()->hasFeature('cameras'));
        $this->assertTrue(app(FeatureKillSwitchService::class)->isKilled('cameras'));
    }

    public function test_kill_switch_only_affects_the_targeted_feature(): void
    {
        $company = Company::factory()->create([
            'features' => ['cameras' => true, 'finance' => true],
        ]);

        app(FeatureKillSwitchService::class)->kill('cameras', 'Maintenance');

        $this->assertFalse($company->refresh()->hasFeature('cameras'));
        $this->assertTrue($company->refresh()->hasFeature('finance'));
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
        // Harness sans feature_kill_switches (simulation) : la résolution
        // retombe sur l'ancien comportement (aucun kill actif).
        DB::statement('DROP TABLE IF EXISTS feature_kill_switches');

        $company = Company::factory()->create(['features' => ['finance' => true]]);

        $this->assertTrue($company->refresh()->hasFeature('finance'));
    }

    public function test_platform_api_requires_super_admin(): void
    {
        $this->getJson('/api/v1/platform/feature-kill-switches')->assertUnauthorized();

        // Un utilisateur authentifié hors garde super-admin (espace tenant)
        // ne peut pas accéder aux routes kill switch.
        Sanctum::actingAs(
            new SuperAdmin(['id' => 1, 'name' => 'Tenant', 'email' => 'tenant@leopardo.test']),
            ['*']
        );

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

        // Contrôle positif : le super-admin lit la liste (200).
        $this->getJson('/api/v1/platform/feature-kill-switches')->assertOk();

        $this->postJson('/api/v1/platform/feature-kill-switches', [
            'feature_key' => 'leo_ai',
            'reason' => 'Incident LLM',
        ])->assertOk();

        $this->assertTrue(app(FeatureKillSwitchService::class)->isKilled('leo_ai'));

        $row = FeatureKillSwitch::query()->where('feature_key', 'leo_ai')->first();

        if (! $row instanceof FeatureKillSwitch) {
            $this->fail('Ligne kill switch absente en base');
        }

        $this->assertTrue((bool) $row->is_active);
        $this->assertSame('Incident LLM', $row->reason);
        $this->assertSame('1', $row->toggled_by);

        $this->deleteJson('/api/v1/platform/feature-kill-switches/leo_ai')->assertOk();

        $this->assertFalse(app(FeatureKillSwitchService::class)->isKilled('leo_ai'));
        $this->assertFalse((bool) $row->refresh()->is_active);
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
