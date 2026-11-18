<?php

declare(strict_types=1);

namespace Tests\Unit\Branding;

use App\Modules\Branding\Domain\Enums\BrandStatus;
use App\Modules\Branding\Domain\Exceptions\BrandPublishException;
use App\Modules\Branding\Domain\Exceptions\BrandSlugException;
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
 * #9578 — locales publiées.
 */
final class BrandLocalesTest extends TestCase
{
    /**
     * @param  array<string, mixed>|null  $surface
     */
    private function brand(?array $surface): Brand
    {
        $brand = new Brand;
        $brand->slug = 'chez-lea';
        $brand->name = 'Chez Léa';
        $brand->status = BrandStatus::Draft;
        $brand->surface = $surface;

        return $brand;
    }

    public function test_no_declaration_means_all_supported_locales(): void
    {
        $locales = $this->brand(null)->publishedLocales();

        $this->assertContains('fr', $locales);
        $this->assertContains('ar', $locales);
        $this->assertContains('en', $locales);
    }

    public function test_explicit_list_restricts_locales(): void
    {
        $locales = $this->brand(['locales' => ['fr', 'en']])->publishedLocales();

        $this->assertSame(['fr', 'en'], $locales);
    }

    public function test_default_locale_is_always_included(): void
    {
        $locales = $this->brand(['locales' => ['en'], 'default_locale' => 'fr'])->publishedLocales();

        $this->assertSame(['fr', 'en'], $locales);
    }

    public function test_unknown_locale_is_dropped(): void
    {
        $locales = $this->brand(['locales' => ['fr', 'xx', 'klingon']])->publishedLocales();

        $this->assertSame(['fr'], $locales);
    }

    public function test_invalid_default_locale_falls_back_to_french(): void
    {
        $this->assertSame('fr', $this->brand(['default_locale' => 'xx'])->defaultLocale());
    }
}
