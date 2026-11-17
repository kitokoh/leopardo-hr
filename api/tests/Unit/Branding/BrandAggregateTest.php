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
final class BrandSlugTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function normalizableSlugs(): array
    {
        return [
            'simple' => ['chez-lea', 'chez-lea'],
            'uppercase' => ['ChezLea', 'chezlea'],
            'spaces' => ['Chez Lea', 'chez-lea'],
            'accents' => ['Café Léa', 'cafe-lea'],
            'turkish dotless i' => ['Işık', 'isik'],
            'multiple separators' => ['chez   lea', 'chez-lea'],
            'leading/trailing dashes' => ['--chez-lea--', 'chez-lea'],
            'underscores' => ['chez_lea', 'chez-lea'],
            'digits' => ['leopardo 24', 'leopardo-24'],
        ];
    }

    #[DataProvider('normalizableSlugs')]
    public function test_slug_is_normalized(string $raw, string $expected): void
    {
        $this->assertSame($expected, Brand::normalizeSlug($raw));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonNormalizableSlugs(): array
    {
        return [
            'empty' => [''],
            'spaces only' => ['   '],
            'punctuation only' => ['!!!'],
            'emoji only' => ['🍕'],
        ];
    }

    #[DataProvider('nonNormalizableSlugs')]
    public function test_unusable_slug_returns_null(string $raw): void
    {
        $this->assertNull(Brand::normalizeSlug($raw));
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this->expectException(BrandSlugException::class);
        $this->expectExceptionMessage('BRAND_SLUG_RESERVED');

        Brand::assertValidSlug('admin');
    }

    public function test_invalid_slug_raises(): void
    {
        $this->expectException(BrandSlugException::class);
        $this->expectExceptionMessage('BRAND_SLUG_INVALID');

        Brand::assertValidSlug('!!!');
    }

    public function test_valid_slug_is_returned_normalized(): void
    {
        $this->assertSame('cafe-lea', Brand::assertValidSlug('Café Léa'));
    }

    /**
     * Un slug de 160 caractères est la borne haute acceptée.
     */
    public function test_slug_length_boundary(): void
    {
        $ok = str_repeat('a', 160);
        $this->assertSame($ok, Brand::normalizeSlug($ok));

        $tooLong = str_repeat('a', 161);
        $this->assertNull(Brand::normalizeSlug($tooLong));
    }
}

/**
 * #9578 — transitions de statut.
 */
final class BrandStatusTest extends TestCase
{
    public function test_only_published_is_publicly_visible(): void
    {
        $this->assertTrue(BrandStatus::Published->isPubliclyVisible());
        $this->assertFalse(BrandStatus::Draft->isPubliclyVisible());
        $this->assertFalse(BrandStatus::Suspended->isPubliclyVisible());
    }

    /**
     * Une Brand suspendue ne se republie pas directement : elle doit repasser
     * par un brouillon conscient. C'est ce qui évite qu'une levée de
     * suspension remette en ligne du contenu non revu.
     */
    public function test_suspended_cannot_be_published_directly(): void
    {
        $this->assertFalse(BrandStatus::Suspended->canTransitionTo(BrandStatus::Published));
        $this->assertTrue(BrandStatus::Suspended->canTransitionTo(BrandStatus::Draft));
    }

    public function test_draft_can_be_published(): void
    {
        $this->assertTrue(BrandStatus::Draft->canTransitionTo(BrandStatus::Published));
    }

    public function test_forbidden_transition_raises(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('BRAND_STATUS_TRANSITION_FORBIDDEN');

        BrandStatus::Suspended->assertCanTransitionTo(BrandStatus::Published);
    }
}

/**
 * #9578 — publication et suspension sur l'agrégat.
 */
final class BrandLifecycleTest extends TestCase
{
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
final class BrandLocalesTest extends TestCase
{
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
