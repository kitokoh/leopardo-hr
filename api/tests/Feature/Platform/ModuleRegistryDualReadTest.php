<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Feature\Domain\Models\FeatureKillSwitch;
use App\Core\Feature\Domain\ModuleRegistry;
use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Feature\Infrastructure\Services\FeatureFlagRegistry;
use App\Core\Feature\Infrastructure\Services\FeatureKillSwitchService;
use App\Core\Feature\Infrastructure\Services\ModuleRegistryGateway;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * BOS-011 (#8198, ADR-0026, spec §5) — dual-read du registre unifié
 * modules / features / solutions.
 *
 * Critères d'acceptation couverts (issue #8198) :
 *  - (2) parité feature map : `hasFeature`/`FeatureFlag::for` identiques en
 *    modes legacy / dual / registry sur un panel de tenants représentatifs
 *    (features vide, partielle, clé inconnue stockée, flag par défaut) ;
 *  - (3) sortie `/auth/me` identique : snapshot exact de la carte exposée
 *    (`EmployeeResource` sérialise `FeatureFlag::for($company)` — la clé
 *    `fleet` ajoutée en fin est la correction documentée de la désync) ;
 *  - (4) kill switch DB prioritaire dans tous les cas (état stocké préservé,
 *    retour à l'état antérieur à la désactivation) ; refus fail-closed d'un
 *    kill sur clé inconnue ou `killable: false` (FR-7) ;
 *  - mode dual : divergence journalisée sans PII (company_id + clé +
 *    valeurs), LEGACY servie ; aucune divergence ⇒ aucun log.
 *
 * Harness : fixture `CreatesMvpSchema` + table `feature_kill_switches`
 * créée à la volée (même protocole que FeatureKillSwitchTest — aucun
 * `migrate:fresh`, qui purgerait le schéma tenant partagé).
 */
class ModuleRegistryDualReadTest extends TestCase
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

    public function test_feature_map_is_identical_across_modes_for_representative_tenants(): void
    {
        /** @var array<string, array<string, bool>> $fixtures */
        $fixtures = [
            'features_vides' => [],
            'activation_partielle' => ['finance' => true],
            'mixte' => ['cameras' => true, 'leo_ai' => false],
            'cle_inconnue_stockee' => ['module_inconnu' => true],
        ];

        foreach ($fixtures as $scenario => $features) {
            /** @var Company $company */
            $company = Company::factory()->create(['features' => $features]);

            /** @var array<string, bool> $legacyMap */
            $legacyMap = FeatureFlag::for($company->fresh());

            config(['module-registry.mode' => ModuleRegistryGateway::MODE_DUAL]);
            /** @var array<string, bool> $dualMap */
            $dualMap = FeatureFlag::for($company->fresh());

            config(['module-registry.mode' => ModuleRegistryGateway::MODE_REGISTRY]);
            /** @var array<string, bool> $registryMap */
            $registryMap = FeatureFlag::for($company->fresh());

            self::assertSame($legacyMap, $dualMap, "mode dual ({$scenario}) : la carte servie doit être la legacy, à l'identique.");
            self::assertSame($legacyMap, $registryMap, "mode registry ({$scenario}) : parité stricte avec la legacy exigée.");

            config(['module-registry.mode' => ModuleRegistryGateway::MODE_LEGACY]);
        }
    }

    public function test_auth_me_feature_map_snapshot_is_stable(): void
    {
        // Snapshot exact (clés, valeurs ET ordre) de la carte `features` telle
        // que /auth/me la sérialise via EmployeeResource. La clé `fleet`
        // apparaît en FIN : ajout additif (désync corrigée, #8198) — aucune
        // clé préexistante ne change de position ni de valeur.
        /** @var array<string, bool> $expected */
        $expected = [
            'ai_cloud_allowed' => false,
            'rh' => true,
            'finance' => false,
            'cameras' => true,
            'muhasebe' => false,
            'leo_ai' => false,
            'training' => false,
            'fuel_station' => false,
            'accounting' => false,
            'crm' => false,
            'restaurant' => false,
            'restaurantmanager' => false,
            'edumanager' => false,
            'healthmanager' => false,
            'travelagency' => false,
            'pharmacy' => false,
            'company_showcase' => false,
            'retail' => false,
            'communication' => false,
            'hospitality' => false,
            'fleet' => false,
        ];

        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['cameras' => true]]);

        self::assertSame($expected, FeatureFlag::for($company->fresh()), 'snapshot legacy (/auth/me).');

        config(['module-registry.mode' => ModuleRegistryGateway::MODE_REGISTRY]);
        self::assertSame($expected, FeatureFlag::for($company->fresh()), 'snapshot registry — identique au legacy.');
    }

    public function test_dual_mode_logs_divergence_and_serves_legacy(): void
    {
        $logPath = $this->captureAuditLog();

        config(['module-registry.mode' => ModuleRegistryGateway::MODE_DUAL]);
        // Divergence forcée : la source legacy dit default=true, le registre false.
        config(['feature-flags.flags.cameras.default' => true]);

        // LEGACY servie (fail-safe pendant la transition).
        self::assertTrue(FeatureFlag::enabled('cameras', null), 'dual : la valeur legacy est servie.');

        $log = (string) file_get_contents($logPath);
        self::assertStringContainsString('module_registry.divergence', $log);
        self::assertStringContainsString('"key":"cameras"', $log);
        self::assertStringContainsString('"legacy":true', $log);
        self::assertStringContainsString('"registry":false', $log);
        // Sans PII : contexte + clé + valeurs + correlation uniquement.
        self::assertStringNotContainsString('"company_name"', $log);
    }

    public function test_dual_mode_without_divergence_logs_nothing(): void
    {
        $logPath = $this->captureAuditLog();

        config(['module-registry.mode' => ModuleRegistryGateway::MODE_DUAL]);

        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['finance' => true]]);

        FeatureFlag::for($company);
        FeatureFlag::enabled('rh', null);

        $log = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        self::assertStringNotContainsString('module_registry.divergence', $log, 'Parité présente ⇒ zéro divergence journalisée.');
    }

    public function test_kill_switch_db_stays_priority_over_stored_activation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['leo_ai' => true]]);
        $killSwitches = app(FeatureKillSwitchService::class);

        self::assertTrue($company->hasFeature('leo_ai'));

        // Kill DB actif ⇒ coupé partout, dans tous les modes, SANS toucher à
        // l'activation stockée (aucune suppression de données).
        $killSwitches->kill('leo_ai', 'Incident de test #8198', 'test');

        foreach ([ModuleRegistryGateway::MODE_LEGACY, ModuleRegistryGateway::MODE_DUAL, ModuleRegistryGateway::MODE_REGISTRY] as $mode) {
            config(['module-registry.mode' => $mode]);
            self::assertFalse($company->fresh()->hasFeature('leo_ai'), "kill DB prioritaire (mode {$mode}).");

            /** @var array<string, bool> $map */
            $map = FeatureFlag::for($company->fresh());
            self::assertFalse($map['leo_ai'], "for() respecte le kill DB (mode {$mode}).");
        }

        config(['module-registry.mode' => ModuleRegistryGateway::MODE_LEGACY]);
        $company->refresh();
        self::assertTrue((bool) ($company->features['leo_ai'] ?? false), 'L activation stockée est préservée.');

        // Désactivation ⇒ retour à l'état antérieur (idempotent).
        $killSwitches->revive('leo_ai', 'test');
        self::assertTrue($company->fresh()->hasFeature('leo_ai'));
    }

    public function test_kill_switch_refuses_non_killable_and_unknown_keys(): void
    {
        $logPath = $this->captureAuditLog();
        $killSwitches = app(FeatureKillSwitchService::class);

        // Socle killable:false ⇒ refus fail-closed, journalisé, aucune écriture.
        try {
            $killSwitches->kill('rh', 'tentative de coupure du socle', 'test');
            self::fail('Le kill du socle rh (killable:false) aurait dû être refusé.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('killable:false', $e->getMessage());
        }

        // Clé inconnue du registre ⇒ refus fail-closed, journalisé.
        try {
            $killSwitches->kill('cle_inconnue', 'clé hors registre', 'test');
            self::fail('Le kill d\'une clé inconnue aurait dû être refusé.');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('inconnue', $e->getMessage());
        }

        self::assertSame(0, FeatureKillSwitch::query()->where('is_active', true)->count(), 'Aucun kill refusé ne doit être écrit.');

        $log = (string) file_get_contents($logPath);
        self::assertStringContainsString('feature_kill_switch.rejected', $log);
        self::assertStringContainsString('not_killable', $log);
        self::assertStringContainsString('unknown_feature_key', $log);
    }

    public function test_registry_mode_serves_registry_source(): void
    {
        // Legacy (défaut) : version historique conservée (contrat console).
        self::assertSame('1.0.0', app(FeatureFlagRegistry::class)->version());

        config(['module-registry.mode' => ModuleRegistryGateway::MODE_REGISTRY]);
        $registry = app(FeatureFlagRegistry::class);

        self::assertSame(ModuleRegistry::VERSION, $registry->version());
        self::assertContains('fleet', $registry->knownKeys());
        self::assertFalse($registry->enabled('fleet', null), 'fleet : fail-closed par défaut.');
        self::assertTrue($registry->enabled('rh', null), 'rh : socle actif par défaut.');
        self::assertFalse($registry->enabled('cle_inconnue', null), 'inconnu : fail-closed.');
    }

    /**
     * Redirige le canal d'audit vers un fichier de test isolé et le purge.
     */
    private function captureAuditLog(): string
    {
        $logPath = storage_path('logs/module-registry-dual-read-test.log');

        if (is_file($logPath)) {
            unlink($logPath);
        }

        config(['logging.channels.audit' => [
            'driver' => 'single',
            'path' => $logPath,
            'level' => 'debug',
        ]]);
        Log::forgetChannel('audit');

        return $logPath;
    }
}
