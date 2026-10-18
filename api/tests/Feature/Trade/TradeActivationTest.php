<?php

declare(strict_types=1);

namespace Tests\Feature\Trade;

use App\Core\Feature\Infrastructure\Services\FeatureFlag;
use App\Core\Solutions\SolutionActivator;
use App\Core\Solutions\SolutionCatalogue;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Modules\Trade\Domain\Manifests\TradeManifest;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRADE-001 (#8476, BC-36 TRADE) — activation de la verticale International
 * Trade OS.
 *
 * Contrats vérifiés :
 *  - la solution `trade` est enregistrée au catalogue (manifest complet) ;
 *  - l'activation pose le flag `trade`, installe les permissions du manifest
 *    aux managers `principal` (BOS-013) et est idempotente ;
 *  - la clé est enregistrée aux 3 points obligatoires (leçon #7220) :
 *    ModuleRegistry + feature-flags + KNOWN_MODULES — fail-closed par défaut
 *    et exposée dans la carte de flags (admin plateforme).
 */
class TradeActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_trade_solution_is_registered_in_catalogue(): void
    {
        $manifest = app(SolutionCatalogue::class)->resolve('trade');

        self::assertInstanceOf(TradeManifest::class, $manifest);
        self::assertSame('trade', $manifest->code());
        self::assertSame('pilot', $manifest->maturity());
        self::assertSame('international_trade', $manifest->industry()->value);
        self::assertSame([], $manifest->requiredModules());
        self::assertSame(
            ['trade.admin', 'trade.purchase', 'trade.sales', 'trade.logistics', 'trade.reports'],
            array_keys($manifest->permissions()),
        );
    }

    public function test_activation_sets_feature_flag(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $result = app(SolutionActivator::class)->activate($company, 'trade');

        self::assertSame('activated', $result['status']);

        $fresh = $company->fresh();
        self::assertNotNull($fresh);
        self::assertTrue($fresh->hasFeature('trade'));
    }

    public function test_reactivation_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('trade', true);
        $company->save();

        $result = app(SolutionActivator::class)->activate($company, 'trade');

        self::assertSame('already_active', $result['status']);
    }

    public function test_activation_installs_manifest_permissions_on_principal(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $principal = \App\Core\Auth\Domain\Models\Employee::factory()->manager()->create([
            'company_id' => $company->id,
        ]);

        app(SolutionActivator::class)->activate($company, 'trade');

        foreach (['trade.admin', 'trade.purchase', 'trade.sales', 'trade.logistics', 'trade.reports'] as $code) {
            self::assertTrue(
                EmployeeModuleGrant::query()
                    ->where('company_id', $company->id)
                    ->where('employee_id', $principal->id)
                    ->where('module_key', $code)
                    ->exists(),
                "Le grant {$code} doit être installé pour le principal.",
            );
        }
    }

    public function test_trade_is_registered_in_known_modules(): void
    {
        self::assertContains(
            'trade',
            Company::KNOWN_MODULES,
            'trade doit être dans Company::KNOWN_MODULES pour être activable par la plateforme (leçon #7220).',
        );
    }

    public function test_trade_is_fail_closed_by_default_and_exposed_in_flag_map(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);

        self::assertFalse($company->hasFeature('trade'));

        $map = FeatureFlag::for($company);

        self::assertArrayHasKey('trade', $map);
        self::assertFalse($map['trade']);
    }
}
