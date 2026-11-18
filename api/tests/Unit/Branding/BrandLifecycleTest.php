<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Exceptions\BrandPublishException;
use App\Modules\Branding\Domain\Models\Brand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #9578 — invariants de l'agrégat Brand.
 *
 * Ces tests n'ont pas besoin de base : les règles testées (slug, transitions,
 * locales, identité) sont des fonctions pures du modèle. Les invariants qui
 * dépendent du SCHÉMA (unicité du défaut, unicité du host) sont couverts par
 * les tests de feature, parce que c'est la base qui les garantit.
 */
/**
 * #9578 — publication et suspension sur l'agrégat.
 */
final class BrandLifecycleTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $identity
     */
    private function brand(array $identity = ['display_name' => 'Chez Léa']): Brand
    {
        $brand = new Brand;
        $brand->slug = 'chez-lea';
        $brand->name = 'Chez Léa';
        $brand->status = BrandStatus::Draft;
        $brand->identity = $identity;
        $brand->palette = ['primary' => '#0F766E', 'accent' => '#0D9488', 'surface' => '#F8FAFC'];

        return $brand;
    }

    public function test_publish_requires_display_name(): void
    {
        $this->expectException(BrandPublishException::class);
        $this->expectExceptionMessage('BRAND_PUBLISH_MISSING_DISPLAY_NAME');

        $this->brand(['tagline' => 'sans nom'])->publish();
    }

    public function test_publish_sets_status_and_date(): void
    {
        $brand = $this->brand();
        $brand->publish();

        $this->assertSame(BrandStatus::Published, $brand->status);
        $this->assertNotNull($brand->published_at);
        $this->assertTrue($brand->isPubliclyVisible());
    }

    /**
     * Republier ne déplace pas `published_at` : le sitemap et l'ordre de
     * l'annuaire s'appuient dessus.
     */
    public function test_republish_keeps_original_date(): void
    {
        $brand = $this->brand();
        $brand->publish();
        $first = $brand->published_at;

        $brand->publish();

        $this->assertSame($first, $brand->published_at);
    }

    public function test_suspend_requires_allowlisted_reason(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BRAND_SUSPENSION_REASON_INVALID');

        $this->brand()->suspend('because');
    }

    public function test_suspend_cuts_public_access_immediately(): void
    {
        $brand = $this->brand();
        $brand->publish();
        $brand->suspend('fraud');

        $this->assertFalse($brand->isPubliclyVisible());
        $this->assertTrue($brand->isSuspended());
        $this->assertSame('fraud', $brand->suspension_reason);
        $this->assertNull($brand->published_at);
    }

    public function test_suspended_brand_cannot_be_published(): void
    {
        $brand = $this->brand();
        $brand->suspend('fraud');

        $this->expectException(BrandPublishException::class);
        $this->expectExceptionMessage('BRAND_PUBLISH_SUSPENDED');

        $brand->publish();
    }

    public function test_unsuspend_returns_to_draft_not_published(): void
    {
        $brand = $this->brand();
        $brand->publish();
        $brand->suspend('tenant_request');
        $brand->unsuspend();

        $this->assertFalse($brand->isSuspended());
        $this->assertSame(BrandStatus::Draft, $brand->status);
        $this->assertFalse($brand->isPubliclyVisible());
    }

    public function test_display_name_falls_back_to_name(): void
    {
        $brand = $this->brand([]);

        $this->assertSame('Chez Léa', $brand->displayName());
    }
}

/**
 * #9578 — locales publiées.
 */
