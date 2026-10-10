<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * VTC-01 (#8357, BC-34 VTC) — activation de la verticale VTC/taxi.
 *
 * Fail-closed : l'activation est refusée tant que le core géospatial `geo`
 * (module requis du manifest, BC-33) n'est pas actif sur le tenant ; sinon
 * elle pose le flag `vtc` et installe les permissions déclarées (BOS-013).
 */
class VtcActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_activation_is_refused_without_geo(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        $this->expectException(SolutionMissingDependencyException::class);

        app(SolutionActivator::class)->activate($company, 'vtc');
    }

    public function test_activation_succeeds_once_geo_is_active(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('geo', true);
        $company->save();

        $result = app(SolutionActivator::class)->activate($company, 'vtc');

        self::assertSame('activated', $result['status']);
        self::assertSame([], $result['missing']);

        $fresh = $company->fresh();
        self::assertNotNull($fresh);
        self::assertTrue($fresh->hasFeature('vtc'));
    }

    public function test_reactivation_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('geo', true);
        $company->setFeature('vtc', true);
        $company->save();

        $result = app(SolutionActivator::class)->activate($company, 'vtc');

        self::assertSame('already_active', $result['status']);
    }
}
