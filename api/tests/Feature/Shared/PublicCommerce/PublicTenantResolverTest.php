<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\PublicCommerce;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-050 (#8208) — résolveur de tenant public mutualisé : fail-closed
 * uniforme (slug inconnu / suspendu / feature absente / opt-in faux) et
 * exécution dans le contexte tenant avec restauration du marqueur.
 */
class PublicTenantResolverTest extends TestCase
{
    use RefreshTenantDatabase;

    private PublicTenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(PublicTenantResolver::class);
    }

    public function test_company_by_slug_resolves_a_company_with_the_required_feature(): void
    {
        $company = $this->companyWithFeature('b2b_catalog');

        $resolved = $this->resolver->companyBySlug($company->slug, 'b2b_catalog');

        $this->assertSame((string) $company->id, (string) $resolved->id);
    }

    public function test_company_by_slug_is_fail_closed_on_unknown_or_empty_slug(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->resolver->companyBySlug('slug-inexistant', 'b2b_catalog');
    }

    public function test_company_by_slug_is_fail_closed_on_empty_slug(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->resolver->companyBySlug('   ', 'b2b_catalog');
    }

    public function test_company_by_slug_is_fail_closed_when_feature_is_missing(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $this->expectException(NotFoundHttpException::class);
        $this->resolver->companyBySlug($company->slug, 'b2b_catalog');
    }

    public function test_company_by_slug_is_fail_closed_on_suspended_company(): void
    {
        $company = $this->companyWithFeature('b2b_catalog');
        $company->forceFill(['status' => 'suspended'])->save();

        $this->expectException(NotFoundHttpException::class);
        $this->resolver->companyBySlug($company->slug, 'b2b_catalog');
    }

    public function test_company_by_slug_applies_the_opt_in_guard(): void
    {
        $company = $this->companyWithFeature('retail');

        $allowed = $this->resolver->companyBySlug($company->slug, 'retail', fn (Company $c): bool => true);
        $this->assertSame((string) $company->id, (string) $allowed->id);

        $this->expectException(NotFoundHttpException::class);
        $this->resolver->companyBySlug($company->slug, 'retail', fn (Company $c): bool => false);
    }

    public function test_failure_status_is_configurable_for_token_surfaces(): void
    {
        try {
            $this->resolver->companyBySlug('slug-inexistant', 'b2b_catalog', null, 401);
            $this->fail('Aucune exception levée.');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function test_within_tenant_sets_the_scope_marker_and_restores_it(): void
    {
        $company = $this->companyWithFeature('b2b_catalog');

        app()->forgetInstance('tenant_scope_required');

        $observed = $this->resolver->withinTenant($company, function (Company $tenant): array {
            return [
                'marker' => app()->bound('tenant_scope_required') && app('tenant_scope_required') === true,
                'current_company_id' => app(TenantManager::class)->current()?->id,
                'tenant_arg_id' => $tenant->id,
            ];
        });

        $this->assertTrue($observed['marker']);
        $this->assertSame((string) $company->id, (string) $observed['current_company_id']);
        $this->assertSame((string) $company->id, (string) $observed['tenant_arg_id']);

        // Après la sortie : marqueur retiré (aucun contexte antérieur ici).
        $this->assertFalse(app()->bound('tenant_scope_required'));
    }

    public function test_within_tenant_restores_a_pre_existing_marker_on_nested_use(): void
    {
        $company = $this->companyWithFeature('b2b_catalog');

        app()->instance('tenant_scope_required', true);

        $this->resolver->withinTenant($company, fn (Company $tenant): null => null);

        $this->assertTrue(app()->bound('tenant_scope_required'));

        app()->forgetInstance('tenant_scope_required');
    }

    /**
     * @return Company société active avec la feature activée
     */
    private function companyWithFeature(string $feature): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature($feature, true);
        $company->save();

        return $company;
    }
}
