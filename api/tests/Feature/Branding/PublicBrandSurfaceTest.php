<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9661 — la surface PUBLIQUE d'une Brand, de bout en bout.
 *
 * ## Pourquoi ces tests existent
 *
 * Le durcissement de `BrandResolver` (revalidation du cache, isolation par
 * tenant, host refusé) n'avait AUCUNE assertion : le code était raisonnable,
 * mais rien ne prouvait qu'il tenait. Ces tests mesurent le contrat observable
 * — le code HTTP et le payload — plutôt que l'implémentation, pour que le
 * resolver puisse être réécrit sans casser les tests.
 *
 * L'invariant central est l'uniformité : slug inconnu, brouillon, Brand
 * suspendue et tenant inaccessible doivent produire la MÊME réponse. Un 403
 * sur l'un d'eux confirmerait l'existence de la ressource et permettrait
 * d'énumérer le parc client.
 */
final class PublicBrandSurfaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function brand(Company $company, array $attributes = []): Brand
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $attributes): Brand {
            /** @var Brand $brand */
            $brand = Brand::create(array_merge([
                'company_id' => $company->id,
                'slug' => 'chez-lea',
                'name' => 'Chez Léa',
                'vertical' => BrandVertical::Restaurant->value,
                'status' => BrandStatus::Draft->value,
                'is_default' => true,
                'palette' => ['primary' => '#A3E635', 'accent' => '#0D9488', 'surface' => '#FFFFFF'],
                'identity' => ['display_name' => 'Chez Léa', 'tagline' => 'Cuisine de saison'],
                'surface' => ['locales' => ['fr', 'en'], 'default_locale' => 'fr'],
                'host' => 'chez-lea.sn',
            ], $attributes));

            return $brand;
        });
    }

    private function publish(Brand $brand): Brand
    {
        $brand->publish();
        // `publish()` ne fait que poser les attributs : sans `save()`, la ligne
        // reste `draft` en base et la surface publique la refuse.
        $brand->save();

        return $brand;
    }

    public function test_a_published_brand_is_served_by_slug_without_internal_state(): void
    {
        $company = $this->company();
        $this->publish($this->brand($company));

        $response = $this->getJson('/api/v1/public/brands/chez-lea');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'chez-lea');
        $response->assertJsonPath('data.identity.display_name', 'Chez Léa');

        foreach (['company_id', 'is_default', 'status', 'suspension_reason', 'id'] as $forbidden) {
            $response->assertJsonMissingPath('data.'.$forbidden);
        }
    }

    public function test_a_published_brand_is_served_by_its_host(): void
    {
        $company = $this->company();
        $this->publish($this->brand($company));

        // URL absolue : c'est elle qui porte l'en-tête `Host` lu par le
        // contrôleur (`$request->getHost()`), pas un en-tête HTTP arbitraire.
        $response = $this->getJson('http://chez-lea.sn/api/v1/public/brands/by-host');

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'chez-lea');
    }

    public function test_an_unknown_slug_is_a_404(): void
    {
        $this->getJson('/api/v1/public/brands/inconnue')->assertNotFound();
    }

    public function test_an_unknown_host_is_a_404(): void
    {
        $this->getJson('http://inconnu.example/api/v1/public/brands/by-host')
            ->assertNotFound();
    }

    /**
     * Un brouillon et un slug inconnu sont INDISTINGUABLES : c'est la
     * propriété anti-énumération, pas un détail d'implémentation.
     */
    public function test_a_draft_brand_is_indistinguishable_from_an_unknown_slug(): void
    {
        $company = $this->company();
        $this->brand($company);

        $draft = $this->getJson('/api/v1/public/brands/chez-lea');
        $unknown = $this->getJson('/api/v1/public/brands/autre-marque');

        $draft->assertNotFound();
        $unknown->assertNotFound();
        self::assertSame($draft->getContent(), $unknown->getContent());
    }

    public function test_a_suspended_brand_is_no_longer_served(): void
    {
        $company = $this->company();
        $brand = $this->publish($this->brand($company));

        $brand->suspend('fraud');
        $brand->save();

        $this->getJson('/api/v1/public/brands/chez-lea')->assertNotFound();
    }

    /**
     * Une entrée de cache ne vaut pas autorisation.
     *
     * Le cas mesuré : une entrée périmée dont la Brand n'est plus servable
     * (brouillon, suspendue, tenant inaccessible) ne doit JAMAIS être rendue.
     * Le resolver la jette et repart de la base — la réponse ne peut donc pas
     * contenir l'état périmé, même si la clé existe encore.
     *
     * Ce que le cache ne peut pas voir, et que ce test ne prétend pas couvrir :
     * une modification faite seulement en base reste invisible jusqu'au TTL
     * (60 s) ou jusqu'à une action qui purge — c'est pour cela que `publish`,
     * `suspend` et `update` purgent explicitement.
     */
    public function test_a_stale_cache_entry_is_never_served(): void
    {
        $company = $this->company();
        $published = $this->publish($this->brand($company));

        // Entrée périmée injectée directement : la même Brand, mais dans un
        // état qui n'est plus servable et avec une identité différente. Si le
        // resolver la rendait telle quelle, la réponse porterait « Perimee ».
        $stale = clone $published;
        $stale->status = BrandStatus::Draft;
        $stale->suspended_at = now();
        $stale->identity = ['display_name' => 'Perimee'];

        Cache::put('brand:public:slug:chez-lea', ['brand' => $stale, 'company' => $company], 60);

        $response = $this->getJson('/api/v1/public/brands/chez-lea');

        $response->assertOk();
        $response->assertJsonPath('data.identity.display_name', 'Chez Léa');

        // L'entrée a été remplacée par une résolution servable, pas conservée.
        $fresh = Cache::get('brand:public:slug:chez-lea');
        self::assertIsArray($fresh);

        $freshBrand = $fresh['brand'] ?? null;
        self::assertInstanceOf(Brand::class, $freshBrand);
        self::assertTrue($freshBrand->isPubliclyVisible());
    }

    /**
     * La boucle de résolution ne doit pas s'arrêter sur un tenant sans Brand :
     * un tenant créé avant l'autre, sans enseigne publiée, ne doit pas rendre
     * injoignable la Brand du tenant suivant.
     */
    public function test_a_tenant_without_brand_does_not_hide_another_tenant_s_brand(): void
    {
        $empty = $this->company();
        $company = $this->company();

        // Aucune Brand pour `$empty`.
        $this->publish($this->brand($company));

        $this->getJson('/api/v1/public/brands/chez-lea')
            ->assertOk()
            ->assertJsonPath('data.slug', 'chez-lea');

        self::assertSame(0, app(TenantManager::class)->withinTenant(
            $empty,
            fn (): int => Brand::query()->count(),
        ));
    }

    /**
     * Une société suspendue rend ses Brands injoignables, même publiées.
     */
    public function test_a_suspended_company_stops_serving_its_brands(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['status' => 'suspended']);

        $this->publish($this->brand($company));

        $this->getJson('/api/v1/public/brands/chez-lea')->assertNotFound();
    }
}
