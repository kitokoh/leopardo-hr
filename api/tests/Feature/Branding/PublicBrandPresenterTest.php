<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Enums\BrandVertical;
use App\Modules\Branding\Domain\Models\Brand;
use App\Modules\Branding\Domain\PublicSurface\PublicBrandPresenter;
use App\Modules\Branding\Domain\Surface\Profiles\RestaurantSurfaceProfile;
use App\Modules\Branding\Domain\Surface\SurfaceProfileRegistry;
use Tests\TestCase;

/**
 * #9578 — le DTO public d'une Brand.
 *
 * Le point critique de ces tests : la LISTE BLANCHE. Une surface anonyme ne
 * doit exposer aucun état interne du tenant. Un champ ajouté au modèle demain
 * ne doit pas fuiter par défaut — c'est exactement ce qu'un `toArray()`
 * laisserait passer.
 */
final class PublicBrandPresenterTest extends TestCase
{
    private function presenter(): PublicBrandPresenter
    {
        return new PublicBrandPresenter(
            SurfaceProfileRegistry::build([new RestaurantSurfaceProfile]),
        );
    }

    private function brand(): Brand
    {
        $brand = new Brand;
        $brand->id = 42;
        $brand->company_id = '11111111-1111-1111-1111-111111111111';
        $brand->slug = 'chez-lea';
        $brand->name = 'Chez Léa';
        $brand->vertical = BrandVertical::Restaurant;
        $brand->status = BrandStatus::Published;
        $brand->is_default = true;
        $brand->host = 'chez-lea.sn';
        $brand->palette = ['primary' => '#A3E635', 'accent' => '#0D9488', 'surface' => '#FFFFFF'];
        $brand->identity = [
            'display_name' => 'Chez Léa',
            'tagline' => 'Cuisine de saison',
            'logo_url' => 'https://cdn.example.com/logo.png',
        ];
        $brand->surface = ['locales' => ['fr', 'en'], 'default_locale' => 'fr'];

        return $brand;
    }

    public function test_payload_contains_no_internal_state(): void
    {
        $payload = $this->presenter()->present($this->brand(), 'fr');

        foreach (['company_id', 'is_default', 'status', 'suspension_reason', 'suspended_at', 'id'] as $forbidden) {
            $this->assertArrayNotHasKey(
                $forbidden,
                $payload,
                sprintf('Le champ interne « %s » ne doit jamais atteindre une surface anonyme.', $forbidden),
            );
        }
    }

    public function test_payload_exposes_identity_and_palette(): void
    {
        $payload = $this->presenter()->present($this->brand(), 'fr');

        $this->assertSame('chez-lea', $payload['slug']);
        $this->assertSame('Chez Léa', $payload['identity']['display_name']);
        $this->assertSame('Cuisine de saison', $payload['identity']['tagline']);
        $this->assertSame('#a3e635', $payload['palette']['primary']);
    }

    /**
     * Le contraste est recalculé au rendu : le vert clair reçoit un texte
     * sombre, même si la colonne stockée disait blanc.
     */
    public function test_palette_foreground_is_recomputed_for_contrast(): void
    {
        $brand = $this->brand();
        $brand->palette = [
            'primary' => '#A3E635',
            'accent' => '#0D9488',
            'surface' => '#FFFFFF',
            'on_primary' => '#FFFFFF',
        ];

        $payload = $this->presenter()->present($brand, 'fr');

        $this->assertSame('#111827', $payload['palette']['on_primary']);
    }

    public function test_profile_of_the_vertical_is_included(): void
    {
        $payload = $this->presenter()->present($this->brand(), 'fr');

        $this->assertSame('restaurant', $payload['profile']['vertical']);
        $this->assertContains('restaurant.menu', $payload['profile']['block_types']);
        $this->assertSame('order', $payload['profile']['primary_cta']);
    }

    public function test_unknown_vertical_falls_back_to_generic_profile(): void
    {
        $brand = $this->brand();
        $brand->vertical = BrandVertical::Pharmacy; // pas de provider enregistré ici

        $payload = $this->presenter()->present($brand, 'fr');

        $this->assertSame('generic', $payload['profile']['vertical']);
    }

    public function test_arabic_locale_sets_rtl_direction(): void
    {
        $brand = $this->brand();
        $brand->surface = ['locales' => ['fr', 'ar'], 'default_locale' => 'fr'];

        $payload = $this->presenter()->present($brand, 'ar');

        $this->assertSame('rtl', $payload['direction']);
        $this->assertSame('ar', $payload['locale']);
    }

    public function test_latin_locale_sets_ltr_direction(): void
    {
        $payload = $this->presenter()->present($this->brand(), 'fr');

        $this->assertSame('ltr', $payload['direction']);
    }

    public function test_host_is_exposed_as_public_address(): void
    {
        $payload = $this->presenter()->present($this->brand(), 'fr');

        $this->assertSame('chez-lea.sn', $payload['surface']['host']);
    }
}
